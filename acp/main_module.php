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
 * ACP module: hands the page to the admin controller
 */
class main_module
{
	/** @var string Form action URL */
	public $u_action;

	/** @var string Template file name */
	public $tpl_name;

	/** @var string Page title */
	public $page_title;

	/**
	 * @param string $id   Module id
	 * @param string $mode Module mode
	 * @return void
	 */
	public function main($id, $mode)
	{
		global $phpbb_container;

		$language = $phpbb_container->get('language');
		$language->add_lang('acp_prunenotifications', 'phpbbmodders/prunenotifications');

		$this->tpl_name = 'acp_prunenotifications';
		$this->page_title = $language->lang('ACP_PRUNENOTIFICATIONS');

		$controller = $phpbb_container->get('phpbbmodders.prunenotifications.admin.controller');
		$controller->set_page_url($this->u_action);
		$controller->handle();
	}
}
