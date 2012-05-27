<?php
/**
 * Facebook plugin's real-time update callback handler
 * 
 * @package Dragooon:WeFB
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Licensed under "New BSD License (3-clause version)"
 *		http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

ob_start();

require_once('../../SSI.php');

global $settings, $context;

loadPluginSource('Dragooon:WeFB', array('facebook', 'Subs-Plugin'));
loadSource(array('Subs-Members', 'Subs-Graphics'));

$facebook = facebook_instance();

// Verifying?
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !empty($_GET['hub_mode']) &&
	$_GET['hub_mode'] == 'subscribe' && $_GET['hub_verify_token'] == $settings['facebook_real_time_token__temp'])
{
	updateSettings(array('facebook_real_time_token' => $settings['facebook_real_time_token__temp']));
	updateSettings(array('facebook_real_time_token__temp' => ''));

	ob_end_clean();

	echo $_GET['hub_challenge'];
	exit;
}
// Otherwise it has to be a POST request from Facebook
elseif ($_SERVER['REQUEST_METHOD'] != 'POST')
	exit;

$request = file_get_contents("php://input");

// Verify the request, otherwise skip it
$request_hash = substr($_SERVER['HTTP_X_HUB_SIGNATURE'], 5);
if (empty($request_hash) || hash_hmac('sha1', $request, $settings['facebook_app_secret']) != $request_hash)
	exit;

$request = json_decode($request, true);

// We only process user requests for now
if ($request['object'] != 'user')
	exit;

$users = array();
foreach ($request['entry'] as $entry)
	$users[$entry['uid']] = $entry['changed_fields'];

// Let's fetch the members for the Facebook IDs
$members = facebook_get_members(array_keys($users), true);

// No members found?
if (empty($members))
	exit;

// Sort thrugh the changes
$changes = array(
	'name' => array(),
	'birthday' => array(),
	'picture' => array(),
	'feed' => array(),
);
$hook_users = array();
foreach ($members as $mem)
{
	$changed_fields = $users[$mem['fbid']];

	$hook_users[$mem['id']] = array($mem['fbid'], $changed_fields, $mem['fields']);

	foreach ($changes as $tochange => $members)
		if (in_array($tochange == 'picture' ? 'pic' : $tochange, $changed_fields) && in_array($tochange, $mem['fields']))
			$changes[$tochange][$mem['id']] = $mem['fbid'];
}

// Call the hook
call_hook('facebook_update', $hook_users);
unset($hook_users);

// Do a combined request for name and birthdate from Facebook
if (!empty($changes['name']) || !empty($changes['birthdate']))
{
	$facebook_users = array_unique(array_merge($changes['name'], $changes['picture']));

	try {
		$facebook_users = $facebook->api('/', 'GET', array(
			'ids' => implode(',', $facebook_users),
			'fields' => 'name,birthday',
		));
	} catch (FacebookApiException $e) {
		log_error('Facebook API request failed: ' . $e->getMessage());
	}

	if (!empty($facebook_users))
	{
		// Update respected names
		foreach ($changes['name'] as $id_member => $id_facebook)
			if (isset($facebook_users[$id_facebook]))
				updateMemberData((array) $id_member, array(
					'real_name' => $facebook_users[$id_facebook]['name'],
				));
		// Update the birthdates
		foreach ($changes['birthday'] as $id_member => $id_facebook)
			if (isset($facebook_users[$id_facebook]))
				updateMemberData((array) $id_member, array(
					'birthdate' => strftime('%Y-%m-%d', strtotime($facebook_users[$id_facebook]['birthday'])),
				));
	}
}

// Update the profile pictures
foreach ($changes['picture'] as $id_member => $id_facebook)
	downloadAvatar(str_replace('https://', 'http://', Facebook::$DOMAIN_MAP['graph']) . '/' . $id_facebook . '/picture?type=large', $id_member, $settings['avatar_max_width_upload'], $settings['avatar_max_height_upload']);

// Update the latest thought
if (!empty($changes['feed']))
{
	// Basically facebook will ping us for every new post by the user including links, apps etc.
	// But we only need the actual status updates and not every other thing.
	// For the same reason duplicates are checked in order to avoid the same message being posted twice
	// or more times.
	$queries = array();
	foreach ($changes['feed'] as $id_member => $id_facebook)
		$queries[$id_member] = 'SELECT message FROM stream WHERE source_id = ' . $id_facebook . ' AND type = 46 LIMIT 1';
	
	$result = $facebook->api('/fql', 'GET', array(
		'q' => $queries,
	));

	$thoughts = array();
	foreach ($changes['feed'] as $id_member => $id_facebook)
	{
		foreach ($result['data'] as $data)
			if ($data['name'] == $id_member)
				$thoughts[$id_member] = $data['fql_result_set'][0]['message'];
	}

	foreach ($thoughts as $id_member => $message)
	{
		// Check for duplicates
		//!!! Should the message be stored per-member or site-wide?
		$cached_thought = cache_get_data('fb_last_thought_' . $id_member, 7 * 86400);

		if ($cached_thought != null && $cached_thought['message'] == $message)
			continue;

		wesql::query('
			INSERT IGNORE INTO {db_prefix}thoughts (id_parent, id_member, id_master, privacy, updated, thought)
			VALUES ({int:id_parent}, {int:id_member}, {int:id_master}, {string:privacy}, {int:updated}, {string:thought})',
			array(
				'id_parent' => 0,
				'id_member' => $id_member,
				'id_master' => 0,
				'privacy' => '-3',
				'updated' => time(),
				'thought' => $message,
			)
		);
		$last_thought = wesql::insert_id();

		// Cache this for several days in order to prevent duplicates
		cache_put_data('fb_last_thought_' . $id_member, array('message' => $message), 7 * 86400);

		if (!empty($last_thought))
			updateMemberData($id_member, array('personal_text' => $message));
	}
}
?>