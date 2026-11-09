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

/**
 * Prune Notifications extension base
 */
class ext extends \phpbb\extension\base
{
	/**
	 * Refuse to enable below the minimum phpBB and PHP versions.
	 *
	 * @return bool|string True if enableable, otherwise the reason
	 */
	public function is_enableable()
	{
		if (!$this->check_phpbb_version() || !$this->check_php_version())
		{
			$language = $this->container->get('language');
			$language->add_lang('install_prunenotifications', 'phpbbmodders/prunenotifications');

			return $language->lang('PRUNENOTIFICATIONS_NOT_ENABLEABLE');
		}

		return true;
	}

	/**
	 * Require phpBB 3.3.19 due to several security fixes in the phpBB software.
	 *
	 * @return bool
	 */
	public function check_phpbb_version()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.19', '>=');
	}

	/**
	 * Requires PHP 8.3.36 due to security fixes.
	 *
	 * @return bool
	 */
	public function check_php_version()
	{
		return PHP_VERSION_ID >= 80336;
	}
}
