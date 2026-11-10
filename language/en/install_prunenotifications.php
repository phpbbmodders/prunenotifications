<?php
/**
 *
 * Prune Notifications extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'PRUNENOTIFICATIONS_NOT_ENABLEABLE'	=> 'Prune Notifications could not be enabled. It requires phpBB 3.3.19 or later and PHP 8.1 or later.',
]);
