<?php
/**
 *
 * Prune Notifications extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\prunenotifications\cron\task;

use phpbb\config\config;
use phpbb\log\log_interface;
use phpbb\user;
use phpbbmodders\prunenotifications\pruner;

/**
 * Deletes one batch of dormant members' notifications per run.
 *
 * While a backlog remains the task runs again after BACKLOG_INTERVAL;
 * once it is cleared, once a day. When a backlog has been cleared, the task
 * writes one admin log entry with the total and, if the admin turned it on,
 * optimizes the notifications table.
 */
class prune extends \phpbb\cron\task\base
{
	/** Seconds between runs while a backlog remains */
	public const BACKLOG_INTERVAL = 600;

	/** Seconds between runs once the backlog is cleared */
	public const IDLE_INTERVAL = 86400;

	/** @var config */
	protected $config;

	/** @var log_interface */
	protected $log;

	/** @var pruner */
	protected $pruner;

	/** @var user */
	protected $user;

	/**
	 * Constructor
	 *
	 * @param config        $config Config object
	 * @param log_interface $log    Log object
	 * @param pruner        $pruner Notification pruner
	 * @param user          $user   User object
	 */
	public function __construct(config $config, log_interface $log, pruner $pruner, user $user)
	{
		$this->config = $config;
		$this->log = $log;
		$this->pruner = $pruner;
		$this->user = $user;
	}

	/**
	 * Only run when the admin turned automatic pruning on.
	 *
	 * @return bool
	 */
	public function is_runnable()
	{
		return (bool) $this->config['prunenotifications_cron_enable'];
	}

	/**
	 * Run when the next scheduled time has passed.
	 *
	 * @return bool
	 */
	public function should_run()
	{
		return time() >= (int) $this->config['prunenotifications_next_run'];
	}

	/**
	 * Delete one batch, then schedule the next run.
	 *
	 * @return void
	 */
	public function run()
	{
		$batch_size = (int) $this->config['prunenotifications_batch'];
		$deleted = $this->pruner->prune_batch($this->pruner->get_cutoff(), $batch_size);

		if ($deleted > 0)
		{
			$this->config->increment('prunenotifications_cron_deleted', $deleted, false);
		}

		// A full batch means more may be waiting
		$backlog = $deleted >= max(pruner::BATCH_MIN, $batch_size);
		$this->config->set('prunenotifications_next_run', time() + ($backlog ? self::BACKLOG_INTERVAL : self::IDLE_INTERVAL), false);

		if ($backlog)
		{
			return;
		}

		$total = (int) $this->config['prunenotifications_cron_deleted'];
		if ($total > 0)
		{
			$this->config->set('prunenotifications_cron_deleted', 0, false);
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PRUNENOTIFICATIONS_CRON', false, [$total]);
		}

		if ($this->config['prunenotifications_auto_optimize'] && (int) $this->config['prunenotifications_unoptimized'] > 0 && $this->pruner->can_optimize())
		{
			$error = $this->pruner->optimize();

			if ($error === '')
			{
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PRUNENOTIFICATIONS_OPTIMIZED', false);
			}
			else
			{
				$this->log->add('critical', $this->user->data['user_id'], $this->user->ip, 'LOG_PRUNENOTIFICATIONS_OPTIMIZE_FAILED', false, [$error]);
			}
		}
	}
}
