<?php
/**
 * Template file for facebook add-on
 * 
 * @package Dragooon:WeFB
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Licensed under "New BSD License (3-clause version)"
 *		http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

function template_facebook_block()
{
	global $txt, $context, $settings, $scripturl;
	
	if (!empty($context['facebook_info']))
		return true;

	echo '
		<a href="', $scripturl, '?action=facebook;area=login">
			<img alt="', $txt['facebook_login'], '" src="', $context['plugins_url']['Dragooon:WeFB'], '/templates/images/login.png" />
		</a>';
}

function template_facebook_create_password()
{
	global $txt, $context, $settings, $scripturl;

	// Any errors?
	if (!empty($context['registration_errors']))
	{
		echo '
		<div class="register_error">
			<span>', $txt['registration_errors_occurred'], '</span>
			<ul class="reset">';

		// Cycle through each error and display an error message.
		foreach ($context['registration_errors'] as $error)
			echo '
				<li>', $error, '</li>';

		echo '
			</ul>
		</div>';
	}
	echo '
		<div>
			<we:cat>', $txt['facebook_create_password'], '</we:cat>
			<form action="', $scripturl, '?action=facebook;area=register" method="post" accept-charset="UTF-8" name="registration" id="registration">
				<div class="windowbg2 wrc">
					<fieldset>
						<dl class="register_form">
							<dt><label>', $txt['facebook_user'], '</label></dt>
							<dd>
								<div style="width: 240px; padding: 6px; border: 1px solid #CCCCCC; background: #E2E8F6;">
									<img src="', $context['facebook_pic_url'], '" border="0" style="float: left; width: 48px;" />
									<div style="float: right; width: 185px;">
										<strong>', $context['facebook_info']['name'], '</strong>
										<div class="smalltext">', $context['facebook_info']['email'], '</div>
									</div>
									<br style="clear: both;" />
								</div>
							</dd>
							<dt>
								<label for="username">
									', $txt['username'], '
								</label>
							</dt>
							<dd>
								<input type="text" id="username" name="username" value="', $context['facebook_requires_username'] ? '' : $context['facebook_info']['username'], '" />
							</dd>
							<dt>
								<label for="passwd">
									'. $txt['choose_pass'], '
									<dfn>', $txt['facebook_register_pass_subtext'], '</dfn>
								</label>
							</dt>
							<dd>
								<input type="password" id="passwd" name="passwd" value="" />
							</dd>
							<dt><label for="passwd_check">', $txt['verify_pass'], '</label></dt>
							<dd>
								<input type="password" id="passwd_check" name="passwd_check" value="" />
							</dd>
						</dl>
					</fieldset>
				</div>
				<div style="text-align: center;">
					<input class="submit" type="submit" value="', $txt['register'], '" />
				</div>
			</form>
		</div>';
}

function template_facebook_profile()
{
	global $scripturl, $txt, $context, $settings;

	echo '
	<we:cat>
		<img src="', $context['plugins_url']['Dragooon:WeFB'] . '/templates/images/adminicon.png" alt="" />
		', $txt['facebook'], '
	</we:cat>
	<p class="windowbg description">', $txt['facebook_profile_desc'], '</p>';

	if (empty($context['facebook']['id']))
	{
		echo '
		<div class="windowbg wrc" id="creator">
			<dl>
				<dt>
					<strong>', $txt['facebook_login'], '</strong>
					<dfn>', $txt['facebook_profile_login_desc'], '</dfn>
				</dt>
				<dd>
					<a href="', $scripturl, '?action=facebook;area=login">
						<img alt="', $txt['facebook_login'], '" src="', $context['plugins_url']['Dragooon:WeFB'], '/templates/images/login.png" />
					</a>
				</dd>
			</dl>
		</div>';
	}
	else
	{
		echo '
		<form action="', $scripturl, '?action=profile;area=facebook" id="creator" method="post">
			<div class="windowbg wrc">
				<dl>
					<dt>
						<strong>', $txt['facebook_status_ok'], '</strong>
						<dfn>', $txt['facebook_logged_in'], '</strong>
					</dt>
					<dd>
						', sprintf($txt['facebook_id'], $context['facebook']['id']), '
					</dd>
				</dl>
				<hr />
				<dl>
					<dt>
						<strong>', $txt['facebook_sync_name'], '</strong>
						<dfn>', $txt['facebook_sync_name_subtext'], '</strong>
					</dt>
					<dd>
						<input type="checkbox" name="facebook_fields[]" value="name"', in_array('name', $context['facebook']['fields']) ? ' checked' : '', ' />
					</dd>
					<dt>
						<strong>', $txt['facebook_sync_birthday'], '</strong>
						<dfn>', $txt['facebook_sync_birthday_subtext'], '</strong>
					</dt>
					<dd>
						<input type="checkbox" name="facebook_fields[]" value="birthday"', in_array('birthday', $context['facebook']['fields']) ? ' checked' : '', ' />
					</dd>
					<dt>
						<strong>', $txt['facebook_sync_feed'], '</strong>
						<dfn>', $txt['facebook_sync_feed_subtext'], '</strong>
					</dt>
					<dd>
						<input type="checkbox" name="facebook_fields[]" value="feed"', in_array('feed', $context['facebook']['fields']) ? ' checked' : '', ' />
					</dd>
					<dt>
						<strong>', $txt['facebook_sync_thoughttofeed'], '</strong>
						<dfn>', $txt['facebook_sync_thoughttofeed_subtext'], '</strong>
					</dt>
					<dd>
						<input type="checkbox" name="facebook_fields[]" value="thoughttofeed"', in_array('thoughttofeed', $context['facebook']['fields']) ? ' checked' : '', ' />
					</dd>
					<dt>
						<strong>', $txt['facebook_sync_topictofeed'], '</strong>
						<dfn>', $txt['facebook_sync_topictofeed_subtext'], '</strong>
					</dt>
					<dd>
						<input type="checkbox" name="facebook_fields[]" value="topictofeed"', in_array('topictofeed', $context['facebook']['fields']) ? ' checked' : '', ' />
					</dd>
				</dl>
				<hr />
				<div class="right">
					<input type="submit" name="save" value="', $txt['save'], '" class="submit" />
				</div>
			</div>
		</form>';
	}
}