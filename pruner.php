<?php
/**
 *
 * Prune Notifications extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\prunenotifications;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;

/**
 * Deletes the notifications of dormant members and optimizes the
 * notifications table.
 *
 * A member is dormant when both their last visit and their registration date
 * are older than the cutoff. Checking the registration date as well covers
 * accounts that registered but never logged in (last visit 0) without
 * touching members who joined recently.
 *
 * Only rows in the notifications table are deleted. Members' notification
 * settings and subscriptions are not touched, so a member who comes back
 * starts with an empty notification list.
 */
class pruner
{
	/** Smallest and largest number of notifications deleted in one batch */
	public const BATCH_MIN = 100;
	public const BATCH_MAX = 50000;

	/** Smallest and largest dormant period, in days */
	public const DAYS_MIN = 30;
	public const DAYS_MAX = 36500;

	/** Seconds in a day */
	protected const DAY_SECONDS = 86400;

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var string Notifications table name */
	protected $notifications_table;

	/** @var string Users table name */
	protected $users_table;

	/**
	 * Constructor
	 *
	 * @param config           $config              Config object
	 * @param driver_interface $db                  Database connection
	 * @param string           $notifications_table Notifications table name
	 * @param string           $users_table         Users table name
	 */
	public function __construct(config $config, driver_interface $db, string $notifications_table, string $users_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->notifications_table = $notifications_table;
		$this->users_table = $users_table;
	}

	/**
	 * The time before which a member's last visit counts as dormant.
	 *
	 * @param int|null $now Current time, for tests; null for time()
	 * @return int Unix timestamp
	 */
	public function get_cutoff(int|null $now = null): int
	{
		$days = min(self::DAYS_MAX, max(self::DAYS_MIN, (int) $this->config['prunenotifications_days']));

		return ($now ?? time()) - $days * self::DAY_SECONDS;
	}

	/**
	 * Count the notifications that belong to dormant members.
	 *
	 * @param int $cutoff Unix timestamp from get_cutoff()
	 * @return int
	 */
	public function count_dormant(int $cutoff): int
	{
		$sql = $this->db->sql_build_query('SELECT', [
			'SELECT'	=> 'COUNT(n.notification_id) AS total',
			'FROM'		=> $this->dormant_from(),
			'WHERE'		=> $this->dormant_where($cutoff),
		]);
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/**
	 * Count every row in the notifications table.
	 *
	 * @return int
	 */
	public function count_all(): int
	{
		$sql = 'SELECT COUNT(notification_id) AS total
			FROM ' . $this->notifications_table;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/**
	 * Delete one batch of dormant members' notifications.
	 *
	 * The IDs are read first and then deleted by ID, because DELETE with a
	 * join or a LIMIT is written differently on every database.
	 *
	 * @param int $cutoff     Unix timestamp from get_cutoff()
	 * @param int $batch_size Most notifications to delete
	 * @return int Number of notifications deleted
	 */
	public function prune_batch(int $cutoff, int $batch_size): int
	{
		$batch_size = min(self::BATCH_MAX, max(self::BATCH_MIN, $batch_size));

		$sql = $this->db->sql_build_query('SELECT', [
			'SELECT'	=> 'n.notification_id',
			'FROM'		=> $this->dormant_from(),
			'WHERE'		=> $this->dormant_where($cutoff),
			'ORDER_BY'	=> 'n.notification_id',
		]);
		$result = $this->db->sql_query_limit($sql, $batch_size);

		$ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['notification_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($ids))
		{
			return 0;
		}

		$sql = 'DELETE FROM ' . $this->notifications_table . '
			WHERE ' . $this->db->sql_in_set('notification_id', $ids);
		$this->db->sql_query($sql);

		$deleted = (int) $this->db->sql_affectedrows();

		$this->config->increment('prunenotifications_unoptimized', $deleted, false);

		return $deleted;
	}

	/**
	 * Whether this database has an optimize command here.
	 *
	 * @return bool
	 */
	public function can_optimize(): bool
	{
		return $this->get_optimize_sql() !== '';
	}

	/**
	 * Optimize the notifications table, so the space freed by deleting rows
	 * can be reused or is given back to the disk.
	 *
	 * - MySQL and MariaDB: OPTIMIZE TABLE rebuilds the table and gives the
	 *   space back to the disk (with innodb_file_per_table, the default).
	 * - PostgreSQL: VACUUM ANALYZE lets the table reuse the space. VACUUM FULL
	 *   would give it back to the disk, but it locks the table for the whole
	 *   run, so it is left to the board's database administrator.
	 * - SQLite: VACUUM rebuilds the whole database file.
	 * - MSSQL: ALTER INDEX ALL ... REBUILD compacts the table inside the data
	 *   file. Shrinking the file itself is left to the database administrator.
	 *
	 * @return string Empty on success, otherwise the database's error message
	 */
	public function optimize(): string
	{
		$sql = $this->get_optimize_sql();

		if ($sql === '')
		{
			return $this->db->get_sql_layer();
		}

		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query($sql);
		$error = $this->db->get_sql_error_returned();
		$this->db->sql_return_on_error(false);

		if ($result === false)
		{
			return ($error['message'] ?? '') !== '' ? (string) $error['message'] : $this->db->get_sql_layer();
		}

		// MySQL reports most problems as rows of the result instead of errors
		if ($this->is_mysql())
		{
			while ($row = $this->db->sql_fetchrow($result))
			{
				if (isset($row['Msg_type']) && strtolower($row['Msg_type']) === 'error')
				{
					$this->db->sql_freeresult($result);
					return (string) $row['Msg_text'];
				}
			}
			$this->db->sql_freeresult($result);
		}

		$this->config->set('prunenotifications_unoptimized', 0, false);
		$this->config->set('prunenotifications_last_optimize', time(), false);

		return '';
	}

	/**
	 * The optimize statement for this database, or an empty string.
	 *
	 * @return string
	 */
	protected function get_optimize_sql(): string
	{
		switch ($this->db->get_sql_layer())
		{
			case 'mysqli':
				return 'OPTIMIZE TABLE ' . $this->notifications_table;

			case 'postgres':
				return 'VACUUM ANALYZE ' . $this->notifications_table;

			case 'sqlite3':
				return 'VACUUM';

			case 'mssqlnative':
			case 'mssql_odbc':
				return 'ALTER INDEX ALL ON ' . $this->notifications_table . ' REBUILD';
		}

		return '';
	}

	/**
	 * Whether the board runs on MySQL or MariaDB.
	 *
	 * @return bool
	 */
	protected function is_mysql(): bool
	{
		return $this->db->get_sql_layer() === 'mysqli';
	}

	/**
	 * FROM tables for sql_build_query(): notifications (n) and users (u).
	 *
	 * @return array
	 */
	protected function dormant_from(): array
	{
		return [
			$this->notifications_table	=> 'n',
			$this->users_table			=> 'u',
		];
	}

	/**
	 * WHERE clause joining notifications (n) to dormant users (u).
	 *
	 * @param int $cutoff Unix timestamp from get_cutoff()
	 * @return string
	 */
	protected function dormant_where(int $cutoff): string
	{
		return 'n.user_id = u.user_id
				AND u.user_lastvisit < ' . (int) $cutoff . '
				AND u.user_regdate < ' . (int) $cutoff;
	}
}
