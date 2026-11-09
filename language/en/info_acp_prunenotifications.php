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
	'ACP_PRUNENOTIFICATIONS'	=> 'Prune notifications',

	'LOG_PRUNENOTIFICATIONS_SETTINGS'	=> '<strong>Altered Prune Notifications settings</strong>',
	'LOG_PRUNENOTIFICATIONS_PRUNED'		=> '<strong>Deleted notifications of dormant members</strong><br>» %d notifications',
	'LOG_PRUNENOTIFICATIONS_CRON'		=> '<strong>Automatically deleted notifications of dormant members</strong><br>» %d notifications',
	'LOG_PRUNENOTIFICATIONS_OPTIMIZED'	=> '<strong>Optimized the notifications table</strong>',
	'LOG_PRUNENOTIFICATIONS_OPTIMIZE_FAILED'	=> '<strong>Could not optimize the notifications table</strong><br>» %s',
]);
