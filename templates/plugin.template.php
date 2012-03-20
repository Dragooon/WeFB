<?php
/**
 * Template file for facebook add-on
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