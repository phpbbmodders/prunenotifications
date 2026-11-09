<?php
/**
 *
 * Prune Notifications extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\prunenotifications\acp;

/**
 * ACP module information
 */
class main_info
{
	/**
	 * @return array Module definition
	 */
	public function module()
	{
		return [
			'filename'	=> '\phpbbmodders\prunenotifications\acp\main_module',
			'title'		=> 'ACP_PRUNENOTIFICATIONS',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_PRUNENOTIFICATIONS',
					'auth'	=> 'ext_phpbbmodders/prunenotifications && acl_a_board',
					'cat'	=> ['ACP_CAT_DATABASE'],
				],
			],
		];
	}
}
