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