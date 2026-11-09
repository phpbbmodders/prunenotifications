<?php
/**
 *
 * Prune Notifications extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\prunenotifications\migrations\v10x;

/**
 * Settings and the ACP page
 */
class install extends \phpbb\db\migration\migration
{
	/**
	 * @return bool
	 */
	public function effectively_installed()
	{
		return isset($this->config['prunenotifications_days']);
	}

	/**
	 * @return array
	 */
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	/**
	 * @return array
	 */
	public function update_data()
	{
		return [
			// Settings
			['config.add', ['prunenotifications_days', 730]],
			['config.add', ['prunenotifications_batch', 5000]],
			['config.add', ['prunenotifications_cron_enable', 0]],
			['config.add', ['prunenotifications_auto_optimize', 0]],

			// State, changed often, so kept out of the config cache
			['config.add', ['prunenotifications_next_run', 0, true]],
			['config.add', ['prunenotifications_cron_deleted', 0, true]],
			['config.add', ['prunenotifications_unoptimized', 0, true]],
			['config.add', ['prunenotifications_last_optimize', 0, true]],

			['module.add', [
				'acp',
				'ACP_CAT_DATABASE',
				[
					'module_basename'	=> '\phpbbmodders\prunenotifications\acp\main_module',
					'modes'				=> ['settings'],
				],
			]],
		];
	}
}
