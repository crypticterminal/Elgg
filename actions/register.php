<?php
/**
 * Elgg registration action
 */

elgg_make_sticky_form('register');

if (!elgg_get_config('allow_registration')) {
	return elgg_error_response(elgg_echo('registerdisabled'));
}

// Get variables
$username = get_input('username');
$password = get_input('password', null, false);
$password2 = get_input('password2', null, false);
$email = get_input('email');
$name = get_input('name');
$friend_guid = (int) get_input('friend_guid', 0);
$invitecode = get_input('invitecode');

try {
	if (trim($password) == "" || trim($password2) == "") {
		throw new RegistrationException(elgg_echo('RegistrationException:EmptyPassword'));
	}

	if (strcmp($password, $password2) != 0) {
		throw new RegistrationException(elgg_echo('RegistrationException:PasswordMismatch'));
	}

	$guid = register_user($username, $password, $name, $email);
	if (!$guid) {
		return elgg_error_response(elgg_echo('registerbad'));
	}

	$new_user = get_user($guid);

	// allow plugins to respond to self registration
	// note: To catch all new users, even those created by an admin,
	// register for the create, user event instead.
	// only passing vars that aren't in ElggUser.
	$params = [
		'user' => $new_user,
		'password' => $password,
		'friend_guid' => $friend_guid,
		'invitecode' => $invitecode
	];

	// @todo should registration be allowed no matter what the plugins return?
	if (!elgg_trigger_plugin_hook('register', 'user', $params, true)) {
		$ia = elgg_set_ignore_access(true);
		$new_user->delete();
		elgg_set_ignore_access($ia);
		// @todo this is a generic messages. We could have plugins
		// throw a RegistrationException, but that is very odd
		// for the plugin hooks system.
		throw new RegistrationException(elgg_echo('registerbad'));
	}

	elgg_clear_sticky_form('register');

	if ($new_user->isEnabled()) {
		// if exception thrown, this probably means there is a validation
		// plugin that has disabled the user
		try {
			login($new_user);
			// set forward url
			$session = elgg_get_session();
			if ($session->has('last_forward_from')) {
				$forward_url = $session->get('last_forward_from');
				$forward_source = 'last_forward_from';
			} else {
				// forward to main index page
				$forward_url = '';
				$forward_source = null;
			}
			$params = [
				'user' => $new_user,
				'source' => $forward_source,
			];
			$forward_url = elgg_trigger_plugin_hook('login:forward', 'user', $params, $forward_url);
			return elgg_ok_response('', elgg_echo('registerok', [elgg_get_site_entity()->getDisplayName()]), $forward_url);
		} catch (LoginException $e) {
			return elgg_error_response($e->getMessage());
		}
	}

	return elgg_ok_response();
} catch (RegistrationException $r) {
	return elgg_error_response($r->getMessage());
}

return elgg_ok_response();
