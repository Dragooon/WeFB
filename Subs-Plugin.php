<?php
/**
 * Contains helper functions needed for the addon
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
 * @version 0.001 "We're not there yet"
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