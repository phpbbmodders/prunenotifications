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
	'PRUNENOTIFICATIONS_TITLE_EXPLAIN'	=> 'Deletes the notifications of members who have not visited for a long time, then optimizes the notifications table. phpBB already deletes read notifications after the “Read notification expiration” time in the Load settings; unread notifications of members who never come back stay forever. Only notifications are deleted, never accounts, notification settings or subscriptions. Take a database backup before the first prune.',

	'PRUNENOTIFICATIONS_STATUS'			=> 'Notifications table',
	'PRUNENOTIFICATIONS_TOTAL'			=> 'Notifications in the table',
	'PRUNENOTIFICATIONS_DORMANT'		=> 'Notifications of dormant members',
	'PRUNENOTIFICATIONS_DORMANT_EXPLAIN'	=> 'Members whose last visit and registration are both before %s.',
	'PRUNENOTIFICATIONS_UNOPTIMIZED'	=> 'Deleted since the last optimize',
	'PRUNENOTIFICATIONS_LAST_OPTIMIZE'	=> 'Last optimized',

	'PRUNENOTIFICATIONS_DAYS'			=> 'Dormant after',
	'PRUNENOTIFICATIONS_DAYS_EXPLAIN'	=> 'A member is dormant when they have not visited for this many days. 730 days is two years.',
	'PRUNENOTIFICATIONS_DAYS_ERROR'		=> '“Dormant after” must be from %1$d to %2$d days.',
	'PRUNENOTIFICATIONS_BATCH'			=> 'Batch size',
	'PRUNENOTIFICATIONS_BATCH_EXPLAIN'	=> 'How many notifications are deleted in one step, both by “Prune now” and by each automatic run. Lower it if pruning makes the board slow.',
	'PRUNENOTIFICATIONS_BATCH_ERROR'	=> '“Batch size” must be from %1$d to %2$d.',
	'PRUNENOTIFICATIONS_CRON'			=> 'Prune automatically',
	'PRUNENOTIFICATIONS_CRON_EXPLAIN'	=> 'Uses phpBB cron to delete one batch every 10 minutes while dormant members’ notifications are waiting, then checks once a day.',
	'PRUNENOTIFICATIONS_AUTO_OPTIMIZE'	=> 'Optimize after pruning',
	'PRUNENOTIFICATIONS_AUTO_OPTIMIZE_EXPLAIN'	=> 'Optimize the notifications table automatically each time all dormant members’ notifications have been deleted. On a large table this can take a while.',

	'PRUNENOTIFICATIONS_ACTIONS'		=> 'Run now',
	'PRUNENOTIFICATIONS_PRUNE'			=> 'Prune now',
	'PRUNENOTIFICATIONS_PRUNE_EXPLAIN'	=> 'Deletes all notifications of dormant members, one batch at a time. Keep the page open until it finishes.',
	'PRUNENOTIFICATIONS_OPTIMIZE'		=> 'Optimize now',
	'PRUNENOTIFICATIONS_OPTIMIZE_EXPLAIN'	=> 'MySQL and MariaDB give the freed space back to the disk. PostgreSQL makes it reusable (VACUUM ANALYZE). SQLite rebuilds the whole database file. MSSQL rebuilds the table’s indexes.',
	'PRUNENOTIFICATIONS_OPTIMIZE_UNSUPPORTED'	=> 'Optimizing is not available for this database.',

	'PRUNENOTIFICATIONS_PRUNE_CONFIRM'	=> [
		1	=> 'Delete %1$d notification of members who have not visited since %2$s? This cannot be undone.',
		2	=> 'Delete %1$d notifications of members who have not visited since %2$s? This cannot be undone.',
	],
	'PRUNENOTIFICATIONS_PRUNE_PROGRESS'	=> 'Deleted %1$d notifications so far, %2$d left. The next batch starts automatically.',
	'PRUNENOTIFICATIONS_PRUNE_DONE'		=> [
		0	=> 'There were no notifications of dormant members to delete.',
		1	=> 'Deleted %d notification of dormant members.',
		2	=> 'Deleted %d notifications of dormant members.',
	],
	'PRUNENOTIFICATIONS_NOTHING_TO_PRUNE'	=> 'There are no notifications of dormant members to delete.',
	'PRUNENOTIFICATIONS_OPTIMIZE_CONFIRM'	=> 'Optimize the notifications table now? On a large table this can take a while.',
	'PRUNENOTIFICATIONS_OPTIMIZE_DONE'		=> 'The notifications table was optimized.',
	'PRUNENOTIFICATIONS_OPTIMIZE_FAILED'	=> 'The notifications table could not be optimized: %s',

	'PRUNENOTIFICATIONS_SETTINGS_SAVED'	=> 'Prune Notifications settings saved.',
]);
