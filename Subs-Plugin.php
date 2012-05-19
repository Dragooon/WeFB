<?php
/**
 * Contains helper functions needed for the addon
 * 
 * @package Dragooon:WeFB
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Licensed under "New BSD License (3-clause version)"
 *		http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly');

/**
 * Checks whether facebook is enabled here or not
 * 
 * @return bool
 */
function facebook_enabled()
{
	global $settings;
	
	return !empty($settings['facebook_app_id']) && !empty($settings['facebook_app_secret']);
}

/**
 * Returns the facebook class' instance
 * 
 * @return Facebook
 */
function facebook_instance()
{
	global $settings;
	
	return new Facebook(array(
		'appId' => $settings['facebook_app_id'],
		'secret' => $settings['facebook_app_secret'],
	));
}

/**
 * Returns a freshly requested App access token from Facebook
 * This function is redundant from BaseFacebook::getApplicationAccessToken
 *
 * @return string
 */
function facebook_app_token()
{
	global $settings;

	return $settings['facebook_app_id'] . '|' . $settings['facebook_app_secret'];
}

/**
 * Return's the facebook info for this user, tries to cache the data in order to improve effeciency
 *
 * @param mixed $id_member
 * @param bool $by_id_facebook Use this if you want to search by id_facebook instead of id_member
 * @return array Array of member's info if $id_member is array, otherwise a single dimensional array with just the member's info
 */
function facebook_get_members($id_member, $by_id_facebook = false)
{
	$members = array();

	// Let's try to see if we got some cached
	if ($by_id_facebook)
		$cache_key = 'fb_id_facebook_';
	else
		$cache_key = 'fb_id_member_';
	foreach ((array) $id_member as $k => $id)
	{
		$cache_data = cache_get_data($cache_key . $id, 86400);

		if ($cache_data !== null)
		{
			if (is_array($id_member))
				unset($id_member[$k])
			else
				return $cache_data;

			$members[$cache_data['id_member']] = $cache_data;
		}
	}

	if (!empty($id_member))
	{
		// Let's fetch the members for the Facebook IDs/fields
		$request = wesql::query('
			SELECT id_member, facebook_id, facebook_fields
			FROM {db_prefix}members
			WHERE ' . ($by_id_facebook ? 'facebook_ids' : 'id_member') . ' IN ({array_string:ids})',
			array(
				'ids' => (array) $id_member,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$members[$row['id_member']] = array(
				'id' => $row['id_member'],
				'fbid' => $row['facebook_id'],
				'fields' => explode(',', $row['facebook_fields']),
			);

			cache_put_data($cache_key . ($by_id_facebook ? $row['facebook_id'] : $row['id_member']), $members[$row['id_member']], 86400);
		}
		wesql::free_result($request);
	}

	if (!is_array($id_member))
		return $members[$id_member];
	return $members;
}