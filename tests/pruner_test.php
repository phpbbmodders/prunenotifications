<?php
/**
 *
 * Prune Notifications extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\prunenotifications\tests;

use phpbbmodders\prunenotifications\cron\task\prune;
use phpbbmodders\prunenotifications\pruner;

/**
 * Pruning and optimizing on whichever database the test run uses.
 *
 * Fixture (cutoff 1700000000):
 * - user 1 (anonymous): never visited, registered long ago, 2 notifications: dormant
 * - user 2: visited after the cutoff, 3 notifications: kept
 * - user 3: last visit before the cutoff, 150 notifications: dormant
 * - user 4: never logged in, registered before the cutoff, 2 notifications: dormant
 * - user 5: never logged in, registered after the cutoff, 1 notification: kept
 */
class pruner_test extends \phpbb_database_test_case
{
	protected const CUTOFF = 1700000000;
	protected const DORMANT = 154;
	protected const TOTAL = 158;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var pruner */
	protected $pruner;

	protected static function setup_extensions()
	{
		return ['phpbbmodders/prunenotifications'];
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/fixtures/notifications.xml');
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->db = $this->new_dbal();
		$this->config = new \phpbb\config\config([
			'prunenotifications_days'			=> 730,
			'prunenotifications_batch'			=> 100,
			'prunenotifications_cron_enable'	=> 1,
			'prunenotifications_auto_optimize'	=> 0,
			'prunenotifications_next_run'		=> 0,
			'prunenotifications_cron_deleted'	=> 0,
			'prunenotifications_unoptimized'	=> 0,
			'prunenotifications_last_optimize'	=> 0,
		]);
		$this->pruner = new pruner($this->config, $this->db, 'phpbb_notifications', 'phpbb_users');
	}

	/**
	 * User IDs that still have notifications, sorted.
	 *
	 * @return array
	 */
	protected function remaining_user_ids(): array
	{
		$result = $this->db->sql_query('SELECT DISTINCT user_id FROM phpbb_notifications ORDER BY user_id');
		$ids = array_map('intval', array_column($this->db->sql_fetchrowset($result), 'user_id'));
		$this->db->sql_freeresult($result);

		return $ids;
	}

	public function test_counts()
	{
		$this->assertSame(self::DORMANT, $this->pruner->count_dormant(self::CUTOFF));
		$this->assertSame(self::TOTAL, $this->pruner->count_all());
	}

	public function test_cutoff_clamps_days()
	{
		$this->assertSame(1000000 - 730 * 86400, $this->pruner->get_cutoff(1000000));

		$this->config->set('prunenotifications_days', 1);
		$this->assertSame(1000000 - pruner::DAYS_MIN * 86400, $this->pruner->get_cutoff(1000000));
	}

	public function test_prune_in_batches_keeps_active_and_new_members()
	{
		$this->assertSame(100, $this->pruner->prune_batch(self::CUTOFF, 100));
		$this->assertSame(54, $this->pruner->prune_batch(self::CUTOFF, 100));
		$this->assertSame(0, $this->pruner->prune_batch(self::CUTOFF, 100));

		$this->assertSame(0, $this->pruner->count_dormant(self::CUTOFF));
		$this->assertSame(self::TOTAL - self::DORMANT, $this->pruner->count_all());
		$this->assertSame([2, 5], $this->remaining_user_ids());
		$this->assertSame(self::DORMANT, (int) $this->config['prunenotifications_unoptimized']);
	}

	public function test_batch_size_is_clamped()
	{
		// Below the minimum counts as the minimum
		$this->assertSame(pruner::BATCH_MIN, $this->pruner->prune_batch(self::CUTOFF, 1));
	}

	public function test_optimize()
	{
		$this->pruner->prune_batch(self::CUTOFF, 1000);

		$this->assertTrue($this->pruner->can_optimize(), 'no optimize command for ' . $this->db->get_sql_layer());
		$this->assertSame('', $this->pruner->optimize());
		$this->assertSame(0, (int) $this->config['prunenotifications_unoptimized']);
		$this->assertGreaterThan(0, (int) $this->config['prunenotifications_last_optimize']);

		// The table still works after optimizing
		$this->assertSame(self::TOTAL - self::DORMANT, $this->pruner->count_all());
	}

	public function test_cron_works_through_backlog_then_logs_and_optimizes()
	{
		// The cron task uses get_cutoff(); 730 days before now covers the fixture's
		// dormant members (last visits in 2020 and earlier) but not users 2 and 5,
		// whose dates are in 2033 so the test keeps working
		$this->config->set('prunenotifications_auto_optimize', 1);

		$user = $this->createMock('\phpbb\user');
		$user->data = ['user_id' => 2];
		$user->ip = '127.0.0.1';

		$log = $this->createMock('\phpbb\log\log_interface');
		$log->expects($this->exactly(2))
			->method('add')
			->willReturnCallback(function ($mode, $user_id, $ip, $operation, $time = false, $data = []) {
				static $call = 0;
				$call++;
				if ($call === 1)
				{
					$this->assertSame('LOG_PRUNENOTIFICATIONS_CRON', $operation);
					$this->assertSame([self::DORMANT], $data);
				}
				else
				{
					$this->assertSame('LOG_PRUNENOTIFICATIONS_OPTIMIZED', $operation);
				}
				return 1;
			});

		$task = new prune($this->config, $log, $this->pruner, $user);
		$this->assertTrue($task->is_runnable());
		$this->assertTrue($task->should_run());

		// First run: a full batch, so it comes back soon and logs nothing yet
		$task->run();
		$this->assertSame(self::DORMANT - 100, $this->pruner->count_dormant($this->pruner->get_cutoff()));
		$this->assertLessThanOrEqual(time() + prune::BACKLOG_INTERVAL, (int) $this->config['prunenotifications_next_run']);
		$this->assertFalse($task->should_run());

		// Second run: the rest, then the log entry and the optimize
		$this->config->set('prunenotifications_next_run', 0, false);
		$task->run();
		$this->assertSame([2, 5], $this->remaining_user_ids());
		$this->assertGreaterThan(time() + prune::BACKLOG_INTERVAL, (int) $this->config['prunenotifications_next_run']);
		$this->assertSame(0, (int) $this->config['prunenotifications_cron_deleted']);
		$this->assertSame(0, (int) $this->config['prunenotifications_unoptimized']);
	}

	public function test_cron_off_by_default_setting()
	{
		$this->config->set('prunenotifications_cron_enable', 0);
		$task = new prune($this->config, $this->createMock('\phpbb\log\log_interface'), $this->pruner, $this->createMock('\phpbb\user'));

		$this->assertFalse($task->is_runnable());
	}
}
