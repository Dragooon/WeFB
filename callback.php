<?php
/**
 * Facebook plugin's real-time update callback handler
 * 
 * @package Dragooon:WeFB
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Without express written permission from the author, you cannot redistribute, in any form,
 *		modified or unmodified versions of the file or the package.
 *		The header in all the source files must remain intact
 * 
 *		Failure to comply with the above will result in lapse of the agreement, upon which you must
 *		destory all copies of this package, or parts of it, within 48 hours.
 * 
 *		THIS PACKAGE IS PROVIDED "AS IS" AND WITHOUT ANY WARRANTY. ANY EXPRESS OR IMPLIED WARRANTIES,
 *		INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
 *		PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHORS BE LIABLE TO ANY PARTY FOR
 *		ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES 
 *		ARISING IN ANY WAY OUT OF THE USE OR MISUSE OF THIS PACKAGE.
 *
 * @version 1.0
 */

require_once('../../SSI.php');

global $settings, $context;

loadPluginSource('Dragooon:WeFB', array('facebook', 'Subs-Plugin'));

$facebook = facebook_instance();

// Verifying?
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !empty($_GET['hub_mode']) &&
	$_GET['hub_mode'] == 'subscribe' && $_GET['hub_verify_token'] == $settings['facebook_real_time_token__temp'])
{
	updateSettings(array('facebook_real_time_token' => $settings['facebook_real_time_token__temp']));
	updateSettings(array('facebook_real_time_token__temp' => ''));

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
$request = wesql::query('
	SELECT id_member, facebook_id, facebook_fields
	FROM {db_prefix}members
	WHERE facebook_id IN ({array_string:fbids})',
	array(
		'fbids' => array_keys($users),
	)
);
$members = array();
while ($row = wesql::fetch_assoc($request))
	$members[$row['id_member']] = array(
		'id' => $row['id_member'],
		'fbid' => $row['facebook_id'],
		'fields' => explode(',', $row['facebook_fields']),
	);
wesql::free_result($request);

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
		if (in_array($tochange, $changed_fields) && in_array($tochange, $mem['fields']))
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

	// Check for duplicates
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}thoughts
		WHERE id_member IN ({array_int:members})
			AND thought IN ({array_string:thoughts})',
		array(
			'members' => array_keys($thoughts),
			'thoughts' => array_values($thoughts),
		)
	);
	$duplicates = array();
	while ($row = wesql::fetch_assoc($request))
		$duplicates[] = $row['id_member'];
	wesql::free_result($request);

	foreach ($thoughts as $id_member => $message)
	{
		$id_facebook = $changes['feed'][$id_member];
		
		if (in_array($id_member, $duplicates))
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

		if (!empty($last_thought))
			updateMemberData($id_member, array('personal_text' => $message));
	}
}
?>