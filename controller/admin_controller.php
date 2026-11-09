<?php
/**
 *
 * Prune Notifications extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\prunenotifications\controller;

use phpbb\config\config;
use phpbb\language\language;
use phpbb\log\log_interface;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\prunenotifications\pruner;

/**
 * ACP page: settings, the number of notifications that would be deleted,
 * and buttons to prune and optimize now.
 */
class admin_controller
{
	/** Form key and link-hash name for this page */
	protected const FORM_NAME = 'phpbbmodders_prunenotifications';

	/** @var config */
	protected $config;

	/** @var language */
	protected $language;

	/** @var log_interface */
	protected $log;

	/** @var pruner */
	protected $pruner;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var string Form action URL */
	protected $u_action = '';

	/**
	 * Constructor
	 *
	 * @param config        $config   Config object
	 * @param language      $language Language object
	 * @param log_interface $log      Log object
	 * @param pruner        $pruner   Notification pruner
	 * @param request       $request  Request object
	 * @param template      $template Template object
	 * @param user          $user     User object
	 */
	public function __construct(config $config, language $language, log_interface $log, pruner $pruner, request $request, template $template, user $user)
	{
		$this->config = $config;
		$this->language = $language;
		$this->log = $log;
		$this->pruner = $pruner;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
	}

	/**
	 * Set the URL the page's forms and links point to.
	 *
	 * @param string $u_action ACP module URL
	 * @return void
	 */
	public function set_page_url(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	/**
	 * Handle the page: a prune step, a confirmed action, saved settings, or
	 * the page itself.
	 *
	 * @return void
	 */
	public function handle(): void
	{
		$this->language->add_lang('acp_prunenotifications', 'phpbbmodders/prunenotifications');

		$action = $this->request->variable('action', '');

		if ($action === 'prune_step')
		{
			if (!check_link_hash($this->request->variable('hash', ''), self::FORM_NAME))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$this->prune_step($this->request->variable('deleted', 0));
			return;
		}

		if ($this->request->is_set_post('prune') || $action === 'prune')
		{
			$this->confirm_prune();
			return;
		}

		if ($this->request->is_set_post('optimize') || $action === 'optimize')
		{
			$this->confirm_optimize();
			return;
		}

		add_form_key(self::FORM_NAME);

		$errors = [];
		if ($this->request->is_set_post('submit'))
		{
			$errors = $this->save_settings();
		}

		$this->display($errors);
	}

	/**
	 * Validate and store the settings.
	 *
	 * Ends the request with a confirmation message when they are saved.
	 *
	 * @return array Error messages, if any
	 */
	protected function save_settings(): array
	{
		$errors = [];

		if (!check_form_key(self::FORM_NAME))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		$days = $this->request->variable('prunenotifications_days', 0);
		$batch = $this->request->variable('prunenotifications_batch', 0);
		$cron_enable = $this->request->variable('prunenotifications_cron_enable', 0) ? 1 : 0;
		$auto_optimize = $this->request->variable('prunenotifications_auto_optimize', 0) ? 1 : 0;

		if ($days < pruner::DAYS_MIN || $days > pruner::DAYS_MAX)
		{
			$errors[] = $this->language->lang('PRUNENOTIFICATIONS_DAYS_ERROR', pruner::DAYS_MIN, pruner::DAYS_MAX);
		}

		if ($batch < pruner::BATCH_MIN || $batch > pruner::BATCH_MAX)
		{
			$errors[] = $this->language->lang('PRUNENOTIFICATIONS_BATCH_ERROR', pruner::BATCH_MIN, pruner::BATCH_MAX);
		}

		if (!empty($errors))
		{
			return $errors;
		}

		// Turning automatic pruning on starts it at the next cron run
		if ($cron_enable && !$this->config['prunenotifications_cron_enable'])
		{
			$this->config->set('prunenotifications_next_run', 0, false);
		}

		$this->config->set('prunenotifications_days', $days);
		$this->config->set('prunenotifications_batch', $batch);
		$this->config->set('prunenotifications_cron_enable', $cron_enable);
		$this->config->set('prunenotifications_auto_optimize', $auto_optimize);

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PRUNENOTIFICATIONS_SETTINGS');

		trigger_error($this->language->lang('PRUNENOTIFICATIONS_SETTINGS_SAVED') . adm_back_link($this->u_action));

		return [];
	}

	/**
	 * Ask before pruning; once confirmed, run the first batch.
	 *
	 * @return void
	 */
	protected function confirm_prune(): void
	{
		$cutoff = $this->pruner->get_cutoff();

		if (confirm_box(true))
		{
			$this->prune_step(0);
			return;
		}

		$count = $this->pruner->count_dormant($cutoff);
		if ($count === 0)
		{
			trigger_error($this->language->lang('PRUNENOTIFICATIONS_NOTHING_TO_PRUNE') . adm_back_link($this->u_action));
		}

		confirm_box(false, $this->language->lang('PRUNENOTIFICATIONS_PRUNE_CONFIRM', $count, $this->user->format_date($cutoff)), build_hidden_fields([
			'action'	=> 'prune',
		]));

		// The confirm box was cancelled
		redirect($this->u_action);
	}

	/**
	 * Delete one batch, then either refresh to the next batch or finish.
	 *
	 * @param int $deleted_so_far Notifications deleted by earlier batches of this run
	 * @return void
	 */
	protected function prune_step(int $deleted_so_far): void
	{
		$cutoff = $this->pruner->get_cutoff();
		$batch_size = (int) $this->config['prunenotifications_batch'];

		$deleted = $this->pruner->prune_batch($cutoff, $batch_size);
		$deleted_so_far = max(0, $deleted_so_far) + $deleted;

		// A full batch means more may be waiting
		if ($deleted >= max(pruner::BATCH_MIN, $batch_size))
		{
			$remaining = $this->pruner->count_dormant($cutoff);

			if ($remaining > 0)
			{
				meta_refresh(1, $this->u_action . '&amp;action=prune_step&amp;deleted=' . $deleted_so_far . '&amp;hash=' . generate_link_hash(self::FORM_NAME));
				trigger_error($this->language->lang('PRUNENOTIFICATIONS_PRUNE_PROGRESS', $deleted_so_far, $remaining));
			}
		}

		$message = $this->language->lang('PRUNENOTIFICATIONS_PRUNE_DONE', $deleted_so_far);

		if ($deleted_so_far > 0)
		{
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PRUNENOTIFICATIONS_PRUNED', false, [$deleted_so_far]);
		}

		if ($this->config['prunenotifications_auto_optimize'] && (int) $this->config['prunenotifications_unoptimized'] > 0 && $this->pruner->can_optimize())
		{
			$message .= '<br><br>' . $this->optimize_message($this->pruner->optimize());
		}

		trigger_error($message . adm_back_link($this->u_action));
	}

	/**
	 * Ask before optimizing; once confirmed, optimize and report.
	 *
	 * @return void
	 */
	protected function confirm_optimize(): void
	{
		if (!$this->pruner->can_optimize())
		{
			trigger_error($this->language->lang('PRUNENOTIFICATIONS_OPTIMIZE_UNSUPPORTED') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if (confirm_box(true))
		{
			$error = $this->pruner->optimize();
			trigger_error($this->optimize_message($error) . adm_back_link($this->u_action), $error === '' ? E_USER_NOTICE : E_USER_WARNING);
		}

		confirm_box(false, $this->language->lang('PRUNENOTIFICATIONS_OPTIMIZE_CONFIRM'), build_hidden_fields([
			'action'	=> 'optimize',
		]));

		redirect($this->u_action);
	}

	/**
	 * Log an optimize result and describe it.
	 *
	 * @param string $error Empty on success, otherwise the database's error message
	 * @return string Message for the admin
	 */
	protected function optimize_message(string $error): string
	{
		if ($error === '')
		{
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PRUNENOTIFICATIONS_OPTIMIZED');
			return $this->language->lang('PRUNENOTIFICATIONS_OPTIMIZE_DONE');
		}

		$this->log->add('critical', $this->user->data['user_id'], $this->user->ip, 'LOG_PRUNENOTIFICATIONS_OPTIMIZE_FAILED', false, [$error]);
		return $this->language->lang('PRUNENOTIFICATIONS_OPTIMIZE_FAILED', utf8_htmlspecialchars($error));
	}

	/**
	 * Show the settings form and the current numbers.
	 *
	 * @param array $errors Error messages from a failed save
	 * @return void
	 */
	protected function display(array $errors): void
	{
		$cutoff = $this->pruner->get_cutoff();
		$last_optimize = (int) $this->config['prunenotifications_last_optimize'];

		$this->template->assign_vars([
			'S_ERROR'		=> !empty($errors),
			'ERROR_MSG'		=> implode('<br>', $errors),

			'PRUNENOTIFICATIONS_DAYS'		=> (int) $this->config['prunenotifications_days'],
			'PRUNENOTIFICATIONS_BATCH'		=> (int) $this->config['prunenotifications_batch'],
			'S_PRUNENOTIFICATIONS_CRON'		=> (bool) $this->config['prunenotifications_cron_enable'],
			'S_PRUNENOTIFICATIONS_AUTO_OPTIMIZE'	=> (bool) $this->config['prunenotifications_auto_optimize'],

			'DAYS_MIN'		=> pruner::DAYS_MIN,
			'DAYS_MAX'		=> pruner::DAYS_MAX,
			'BATCH_MIN'		=> pruner::BATCH_MIN,
			'BATCH_MAX'		=> pruner::BATCH_MAX,

			'CUTOFF_DATE'		=> $this->user->format_date($cutoff),
			'DORMANT_COUNT'		=> $this->pruner->count_dormant($cutoff),
			'TOTAL_COUNT'		=> $this->pruner->count_all(),
			'UNOPTIMIZED_COUNT'	=> (int) $this->config['prunenotifications_unoptimized'],
			'LAST_OPTIMIZE'		=> $last_optimize ? $this->user->format_date($last_optimize) : $this->language->lang('NEVER'),
			'S_CAN_OPTIMIZE'	=> $this->pruner->can_optimize(),

			'U_ACTION'		=> $this->u_action,
		]);
	}
}
