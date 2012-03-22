<?php
/**
 * Facebook plugin's main file
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
 * "load_theme" hook callback, called on every page. Adds the "FB Login" sideblock for guests
 * and loads template as well as language for future uses
 * 
 * @return void
 */
function facebook_hook_load_theme()
{
	global $settings, $user_info, $scripturl, $context;

	loadPluginSource('Dragooon:WeFB', array('facebook', 'Subs-Plugin'));
	loadPluginTemplate('Dragooon:WeFB', 'templates/plugin');
	loadPluginLanguage('Dragooon:WeFB', 'languages/plugin');
	
	if (!facebook_enabled())
		return false;

	// Make sure we're properly registered with Facebook's real time updates
	if (empty($settings['facebook_real_time_token']) && empty($settings['facebook_real_time_token__temp']))
	{
		$verify_token = sha1(microtime() * mt_rand());

		$facebook = facebook_instance();

		updateSettings(array('facebook_real_time_token__temp' => $verify_token));

		$subscription = $facebook->api('/' . $settings['facebook_app_id'] . '/subscriptions', 'POST', array(
			'access_token' => facebook_app_token(),
			'object' => 'user',
			'fields' => 'name,feed,birthday',
			'callback_url' => $context['plugins_url']['Dragooon:WeFB'] . '/callback.php',
			'verify_token' => $verify_token,
		));
	}

	if ($user_info['is_guest'])
		wetem::first('sidebar', 'facebook_block');
}

/**
 * Handles "facebook" action and routes it to the correct sub-function
 * 
 * @return void
 */
function Facebook()
{
	global $context, $settings, $user_info;
	
	if (!facebook_enabled())
		return false;
	
	$areas = array(
		'login' => 'Facebook_login_redirect',
		'login_return' => 'Facebook_login_return',
		'register' => 'Facebook_register',
	);
	
	if (isset($_REQUEST['area']) && isset($areas[$_REQUEST['area']]))
		return $areas[$_REQUEST['area']]();
	
	redirectexit();
}

/**
 * Redirects the user to facebook login
 * 
 * @return void
 */
function Facebook_login_redirect()
{
	global $context, $scripturl, $settings, $user_info;
	
	// Initialise Facebook
	$facebook = facebook_instance();

	redirectexit($facebook->getLoginUrl(array(
		'redirect_uri' => $scripturl . '?action=facebook&area=login_return',
		'scope' => 'email,user_birthday,read_stream,user_status,publish_stream,offline_access',
	)));
}

/**
 * Actually logs in the user returning from Facebook, if not found, prompts the password field
 * if the user's a guest, otherwise assigns the facebook ID to the current member
 * 
 * @return void
 */
function Facebook_login_return()
{
	global $context, $settings, $user_info, $user_settings, $txt, $scripturl;
	
	if (!facebook_enabled())
		fatal_lang_error('facebook_disabled');
	
	loadSource(array('Subs-Auth', 'Subs-Login', 'Subs-Members', 'Subs-Graphics'));

	$facebook = facebook_instance();
	$user = $facebook->getUser();

	$me = $facebook->api('/me', 'GET', array(
		'fields' => 'username,name,email,birthday'
	));

	if (empty($me) || empty($me['id']))
		fatal_lang_error('facebook_invalid_request');

	// Are we an existing user?
	$request = wesql::query('
		SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt, passwd_flood
		FROM {db_prefix}members
		WHERE facebook_id = {string:fb_id}
		LIMIT 1',
		array(
			'fb_id' => $me['id'],
		)
	);
	$rows = wesql::num_rows($request);
	$user_settings = wesql::fetch_assoc($request);
	wesql::free_result($request);

	if ($rows > 0)
		// Log this user in
		DoLogin();
	// Otherwise register them if they're a guest
	elseif ($user_info['is_guest'])
	{
		if (empty($me['email']))
			fatal_lang_error('facebook_no_email');
		
		$_SESSION['facebook_info'] = $me;
		$context['page_title'] = $txt['facebook_create_password'];
		$context['facebook_info'] = $me;
		$context['facebook_pic_url'] = Facebook::$DOMAIN_MAP['graph'] . '/' . $me['id'] . '/picture';
		$context['facebook_requires_username'] = isReservedName($me['username']);
		wetem::load('facebook_create_password');
	}
	// Otherwise straight away assign them their FB ID
	else
	{
		updateMemberData((array) $user_info['id'], array(
			'facebook_id' => $me['id'],
		));

		redirectexit('action=profile;area=facebook');
	}
}

/**
 * Actually registers an user
 *
 * @return void
 */
function Facebook_register()
{
	global $context, $settings, $user_info, $txt;

	loadSource(array('Subs-Auth', 'Subs-Login', 'Subs-Members', 'Subs-Graphics'));
	loadLanguage('Login');
	
	if (!facebook_enabled())
		fatal_lang_error('facebook_disabled');
	
	if (empty($_SESSION['facebook_info']) || empty($_SESSION['facebook_info']['email']))
		redirectexit();
	
	// Check to make sure this user doesn't already exist
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}members
		WHERE facebook_id = {string:id}
		LIMIT 1',
		array(
			'id' => $_SESSION['facebook_info']['id'],
		)
	);
	if (wesql::num_rows($request) > 0)
		redirectexit('action=facebook;area=login');
	
	// We don't need to do much validating, we'll let registerMember handle those
	$regOptions = array(
		'password' => $_POST['passwd'],
		'password_check' => $_POST['passwd_check'],
		'username' => $_POST['username'],
		'interface' => 'guest',
		'auth_method' => 'password',
		'email' => $_SESSION['facebook_info']['email'],
		'check_reserved_name' => true,
		'check_password_strength' => true,
		'check_email_ban' => true,
		'send_welcome_email' => !empty($settings['send_welcomeEmail']),
		'require' => 'nothing',
		'extra_register_vars' => array(
			'real_name' => $_SESSION['facebook_info']['name'],
			'birthdate' => strftime('%Y-%m-%d', strtotime($_SESSION['facebook_info']['birthday'])),
		),
	);

	$id_member = registerMember($regOptions);
	if (is_array($id_member))
	{
		$context['registration_errors'] = $id_member;
		$context['page_title'] = $txt['facebook_create_password'];
		$context['facebook_info'] = $_SESSION['facebook_info'];
		$context['facebook_pic_url'] = Facebook::$DOMAIN_MAP['graph'] . '/' . $me['id'] . '/picture';
		$context['facebook_requires_username'] = isReservedName($_SESSION['facebook_info']['username']);
		wetem::load('facebook_create_password');
	}
	else
	{
		downloadAvatar(str_replace('https://', 'http://', Facebook::$DOMAIN_MAP['graph']) . '/' . $_SESSION['facebook_info']['id'] . '/picture?type=large', $id_member, $settings['avatar_max_width_upload'], $settings['avatar_max_height_upload']);
		updateMemberData((array) $id_member, array(
			'facebook_id' => $_SESSION['facebook_info']['id'],
			'facebook_fields' => 'real_name,birthdate,avatar',
		));

		// We run this since it is supposed to be run anyway
		call_hook('activate', array($regOptions['username']));

		// Facebook hook
		call_hook('facebook_register', array($id_member, $me['id']));

		setLoginCookie(60 * $settings['cookieTime'], $id_member, sha1(sha1(strtolower($regOptions['username']) . $regOptions['password']) . $regOptions['register_vars']['password_salt']));
		redirectexit('action=login2;sa=check;member=' . $id_member, $context['server']['needs_login_fix']);
	}
}