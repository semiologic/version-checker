<?php
/**
 * sem_api_key
 *
 * @package Version Checker
 **/

class sem_api_key {
	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST || !current_user_can('manage_options') )
			return;
		
		check_admin_referer('sem_api_key');
		
		$sem_api_key = trim(stripslashes($_POST['sem_api_key']));
		
		if ( $sem_api_key && !preg_match("/^[0-9a-f]{32}$/i", $sem_api_key) )
			$sem_api_key = '';
		
		$sem_packages = stripslashes($_POST['sem_packages']);

		if ( !in_array($sem_packages, array('stable', 'bleeding')) )
			$sem_packages = 'stable';
		
		update_option('sem_api_key', $sem_api_key);
		update_option('sem_packages', $sem_packages);
		
		delete_transient('sem_api_error');
		delete_transient('sem_memberships');
		foreach ( array('themes', 'plugins') as $transient ) {
			delete_transient('update_' . $transient);
			delete_transient('sem_update_' . $transient);
			delete_transient('sem_query_' . $transient);
		}
		
		echo '<div class="updated fade">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.', 'version-checker')
				. '</strong>'
			. '</p>' . "\n"
			. '</div>' . "\n";
	} # save_options()
	
	
	/**
	 * edit_options()
	 *
	 * @return void
	 **/

	function edit_options() {
		echo '<div class="wrap">' . "\n"
			. '<form method="post" action="">';

		wp_nonce_field('sem_api_key');

		screen_icon();
		
		echo '<h2>' . __('Semiologic API Key', 'version-checker') . '</h2>' . "\n";
		
		$sem_api_key = get_option('sem_api_key');
		$sem_packages = get_option('sem_packages');
		$memberships = version_checker::get_memberships();
		$sem_api_error = get_transient('sem_api_error');
		
		if ( $sem_api_error ) {
			echo '<div class="error">' . "\n";
			
			echo '<p>'
				. __('The following errors occurred while trying to contact api.semiologic.com:', 'version-checker')
				. '</p>' . "\n";
			
			echo '<ul class="ul-disc">' . "\n";
			
			foreach ( $sem_api_error as $error ) {
				echo '<li>'
					. $error
					. '</li>' . "\n";
			}
			
			echo '</ul>' . "\n";
			
			echo '<p>'
				. __('To worried users: the above errors do NOT prevent your site from working in any way; they merely mean it failed to receive update notifications.', 'version-checker')
				. '</p>' . "\n";
			
			echo '<p>'
				. __('Frequently, HTTP errors will be related to your server\'s configuration, and should be reported to your host. Before you do, however:', 'version-checker')
				. '</p>' . "\n";
			
			echo '<ol>' . "\n";
			
			echo '<li>'
				. sprintf(__('Install the <a href="%s">Core Control</a> plugin.', 'version-checker'), 'http://dd32.id.au/wordpress-plugins/?plugin=core-control')
				. '</li>' . "\n";
			
			echo '<li>'
				. __('Under Tools / Core Control, enable the HTTP Access Module.', 'version-checker')
				. '</li>' . "\n";
			
			echo '<li>'
				. __('Click &quot;External HTTP Access&quot; on the screen (the link is nearby the screen\'s title).', 'version-checker')
				. '</li>' . "\n";
			
			echo '<li>'
				. __('Disable the current HTTP transport. (It\'s probably not playing well with the secure http protocol, which is used to contact api.semiologic.com.)', 'version-checker')
				. '</li>' . "\n";
			
			echo '<li>'
				. __('Revisit this screen, and save changes to force a refresh.', 'version-checker')
				. '</li>' . "\n";
			
			echo '<li>'
				. __('Repeat the above steps if it\'s still failing, until you run out of available transports to try. (Oftentimes, at least one of them will succeed.)', 'version-checker')
				. '</li>' . "\n";
			
			echo '<li>'
				. __('Don\'t forget to re-enable the HTTP transports if they all fail.', 'version-checker')
				. '</li>' . "\n";
			
			echo '</ol>' . "\n";
			
			echo '<p>'
				. sprintf(__('In the event that the issue is clearly related to semiologic.com, please report it in the <a href="%s">Semiologic forum</a>.', 'version-checker'), 'http://forum.semiologic.com')
				. '</p>' . "\n";
			
			echo '</div>' . "\n";
		}
		
		
		echo '<table class="form-table">' . "\n";
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. ( !$sem_api_key
				? ( '<a href="http://oldbackend.semiologic.com/memberships.php">'
					. __('Semiologic API Key', 'version-checker')
					. '</a>' )
				: __('Semiologic API Key', 'version-checker')
				)
			. '</th>' . "\n"
			. '<td>'
			. '<input type="text" name="sem_api_key" class="widefat code"'
				. ' value="' . attribute_escape($sem_api_key) . '"'
				. ' />'
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Packages', 'version-checker')
			. '</th>' . "\n"
			. '<td>'
			. '<p>'
			. __('Keep WordPress, themes and plugins updated using:', ' version-checker')
			. '</p>' . "\n"
			. '<ul>'
			. '<li>'
			. '<label>'
			. '<input type="radio" name="sem_packages" value="stable"'
				. checked($sem_packages, 'stable', false)
				. ' />'
			. '&nbsp;'
			. __('Stable packages from wordpress.org and semiologic.com (recommended)', 'version-checker')
			. '</label>'
			. '</li>' . "\n"
			. '<li>'
			. '<label>'
			. '<input type="radio" name="sem_packages" value="bleeding"'
				. checked($sem_packages, 'bleeding', false)
				. ' />'
			. '&nbsp;'
			. __('Stable packages from wordpress.org, and bleeding edge packages from semiologic.com', 'version-checker')
			. '</label>'
			. '</li>' . "\n"
			. '</ul>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		if ( $sem_api_key ) {
			echo '<tr>' . "\n"
				. '<th scope="row">'
				. '<a href="http://oldbackend.semiologic.com/memberships.php?user_key=' . urlencode($sem_api_key) . '">'
				. __('Memberships', 'version-checker')
				. '</a>'
				. '</th>' . "\n"
				. '<td>'
				. '<table style="width: 100%; margin: 0px; padding: 0px;">' . "\n";
			
			foreach ( $memberships as $slug => $membership ) {
				echo '<tr>'
					. '<td>'
					. strip_tags($membership['name'])
					. '</td>' . "\n"
					. '<td style="width: 200px;">';
				if ( !$membership['expires'] ) {
					echo __('Never expires', 'version-checker');
				} elseif ( !version_checker::check($slug) ) {
					echo sprintf(__('Expired %1$s - <a href="%2$s">Renew</a>', 'version-checker'),
						date_i18n('F j, Y', strtotime($membership['expires'])),
						'http://oldbackend.semiologic.com/memberships.php'
							. ( $sem_api_key ? ( '?user_key=' . urlencode($sem_api_key) ) : '' ));
				} elseif ( strtotime($membership['expires']) <= time() + 2678400 ) { // 1 month
					echo sprintf(__('Expires %1$s - <a href="%2$s">Renew</a>'),
						date_i18n('F j, Y', strtotime($membership['expires'])),
						'http://oldbackend.semiologic.com/memberships.php'
							. ( $sem_api_key ? ( '?user_key=' . urlencode($sem_api_key) ) : '' ));
				} else {
					echo sprintf(__('Expires %s', 'version-checker'), date_i18n('F j, Y', strtotime($membership['expires'])));
				}

				echo '</td>' . "\n"
					. '</tr>' . "\n";
			}
			
			echo '</table>'
				. '</td>' . "\n"
				. '</tr>' . "\n";
		}
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('FAQ', 'version-checker')
			. '</th>' . "\n"
			. '<td>'
			. '<p>'
			. sprintf(__('Your Semiologic API key entitles you (as an individual Semiologic customer) to software updates from semiologic.com for as long as you\'ve a running membership.  You\'ll find your API Key in the <a href="%s">Semiologic back-end</a>.', 'version-checker'), 'http://oldbackend.semiologic.com')
			. '</p>' . "\n"
			. '<p>'
			. sprintf(__('The software itself will of course continue to work normally when your membership expires. Upgrades from semiologic.com will merely cease to work. It is, of course, <a href="%s">highly recommended</a> that you keep your membership current and your site up to date.', 'version-checker'), 'http://www.semiologic.com/resources/wp-basics/why-upgrade/')
			. '</p>' . "\n"
			. '<p>'
			. sprintf(__('Please do not share your API key, or use it for the benefit of others. It is a password in every respect, and you\'d be breaching our <a href="%s">terms of use</a>. If you or your organization aren\'t a site\'s primary user, that site should be using a separate API key.', 'version-checker'), 'http://members.semiologic.com/terms/')
			. '</p>' . "\n"
			. '<p>'
			. sprintf(__('Please <a href="%s">email sales</a>, or catch Denis on Skype or YIM (ID is ddebernardy on both), for any further information.', 'version-checker'), 'mailto:sales@semiologic.com')
			. '</p>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '</table>' . "\n";

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . attribute_escape(__('Save Changes', 'version-checker')) . '"'
				. ' />'
			. '</p>' . "\n";
		
		echo '</form>' . "\n"
			. '</div>' . "\n";
	} # edit_options()
} # sem_api_key

add_action('settings_page_sem-api-key', array('sem_api_key', 'save_options'), 0);
?>