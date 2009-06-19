<?php
/**
 * sem_api_key
 *
 * @package Version Checker
 **/

add_action('settings_page_sem-api-key', array('sem_api_key', 'save_options'), 0);

class sem_api_key {
	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST )
			return;
		
		check_admin_referer('sem_api_key');
		
		$sem_api_key = trim(stripslashes($_POST['sem_api_key']));
		
		if ( $sem_api_key && !preg_match("/^[0-9a-f]{32}$/i", $sem_api_key) )
			$sem_api_key = '';
		
		if ( $sem_api_key !== get_option('sem_api_key') ) {
			update_option('sem_api_key', $sem_api_key);
			delete_transient('sem_memberships');
			delete_transient('sem_plugins');
		}
		
		if ( isset($_POST['sem_package']) ) {
			$sem_package = stripslashes($_POST['sem_package']);

			if ( !in_array($sem_package, array('wp', 'stable', 'bleeding')) )
				$sem_package = 'wp';
			
			update_option('sem_package', $sem_package);
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
		
		echo '<table class="form-table">' . "\n";
		
		$sem_api_key = get_option('sem_api_key');
		$sem_package = get_option('sem_package');
		$memberships = version_checker::get_memberships(true);
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('API Key', 'version-checker')
			. '</th>' . "\n"
			. '<td>'
			. '<input type="text" name="sem_api_key" class="widefat code"'
				. ' value="' . esc_attr($sem_api_key) . '"'
				. ' />'
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Core Updates', 'version-checker')
			. '</th>' . "\n"
			. '<td>'
			. '<p>'
			. __('Under Tools / Upgrade, use the following package to update my site:', ' version-checker')
			. '</p>' . "\n"
			. '<ul>'
			. '<li>'
			. '<label>'
			. '<input type="radio" name="sem_package" value="wp"'
				. checked($sem_package, 'wp', false)
				. ' />'
			. '&nbsp;'
			. __('The latest version of WordPress', 'version-checker')
			. '</label>'
			. '</li>' . "\n"
			. '<li>'
			. '<label>'
			. '<input type="radio" name="sem_package" value="stable"'
				. checked($sem_package, 'stable', false)
				. ' />'
			. '&nbsp;'
			. __('The latest version of Semiologic Pro (recommended)', 'version-checker')
			. '</label>'
			. '</li>' . "\n"
			. '<li>'
			. '<label>'
			. '<input type="radio" name="sem_package" value="bleeding"'
				. checked($sem_package, 'bleeding', false)
				. ' />'
			. '&nbsp;'
			. __('The bleeding edge version of Semiologic Pro (for test sites)', 'version-checker')
			. '</label>'
			. '</li>' . "\n"
			. '</ul>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		if ( $memberships ) {
			echo '<tr>' . "\n"
				. '<th scope="row">'
				. '<a href="http://oldbackend.semiologic.com/memberships.php' . ( $sem_api_key ? ( '?user_key=' . urlencode($sem_api_key) ) : '' ) . '">'
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
					echo sprintf(__('Expired %1$s - <a href="%2$s">Renew</a>'),
						date_i18n('F j, Y', strtotime($membership['expires'])),
						'http://oldbackend.semiologic.com/memberships.php'
							. ( $sem_api_key ? ( '?user_key=' . urlencode($sem_api_key) ) : '' ));
				} elseif ( strtotime($membership['expires']) <= time() + 2678400 ) { // 1 month
					echo sprintf(__('Expires %1$s - <a href="%2$s">Renew</a>'),
						date_i18n('F j, Y', strtotime($membership['expires'])),
						'http://oldbackend.semiologic.com/memberships.php'
							. ( $sem_api_key ? ( '?user_key=' . urlencode($sem_api_key) ) : '' ));
				} else {
					echo sprintf(__('Expires %s'), date_i18n('F j, Y', strtotime($membership['expires'])));
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
			. sprintf(__('Your Semiologic API key entitles you (as an individual Semiologic customer) to software updates from semiologic.com for as long as you\'ve a running membership.  You\'ll find your API Key in the <a href="%s">Semiologic back-end</a>.', 'version-checker'), 'http://oldbackend.semiologic.com' . ( $sem_api_key ? ( '?user_key=' . urlencode($sem_api_key) ) : '' ) )
			. '</p>' . "\n"
			. '<p>'
			. sprintf(__('The software itself will of course continue to work normally when your membership expires. Upgrades from semiologic.com will merely cease to work. It is, however, <a href="%s">highly recommended</a> that you keep your site up to date at all times.', 'version-checker'), 'http://www.semiologic.com/resources/wp-basics/why-upgrade/')
			. '</p>' . "\n"
			. '<p>'
			. __('Please do not share your API key, or use it for the benefit of others. It is a password in every respect, and you\'d be breaching our terms of use. If you or your organization aren\'t a site\'s primary user, that site should not be using your API key.', 'version-checker')
			. '</p>' . "\n"
			. '<p>'
			. sprintf(__('Please <a href="%s">email sales</a> or skype Denis (ddebernardy) for any further information.', 'version-checker'), 'mailto:sales@semiologic.com')
			. '</p>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '</table>' . "\n";

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'version-checker')) . '"'
				. ' />'
			. '</p>' . "\n";
		
		echo '</form>' . "\n"
			. '</div>' . "\n";
	} # edit_options()
} # sem_api_key
?>