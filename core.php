<?php
/**
 * sem_update_core
 *
 * @package Version Checker
 **/

class sem_update_core {
	/**
	 * update_feedback()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function update_feedback($in = null) {
		ob_start(array('sem_update_core', 'ob_callback'));
		return $in;
	} # update_feedback()
	
	
	/**
	 * ob_start()
	 *
	 * @return void
	 **/

	function ob_start() {
		$update_core = get_transient('update_core');
		if ( !$update_core || empty($update_core->response) || empty($update_core->response->package) )
			return;
		
		echo '<div class="updated fade">' . "\n";
		
		echo '<p>'
			. __('<strong>Important</strong>! Do not interrupt a core upgrade once it\'s started. Doing so can potentially leave your site in a dysfunctional state. It can take several minutes to complete -- albeit seldom more than 15 minutes.', 'version-checker')
			. '</p>' . "\n";

		echo '<p>'
			. sprintf(__('Also note that the Version Checker plugin proactively works around known issues in the WP Upgrade API. <a href="%1$s">There</a> <a href="%2$s">are</a> <a href="%3$s">some</a>, so please make sure you\'re using its latest version before proceeding (<a href="%4$s">change log</a>).', 'version-checker'),
			'http://core.trac.wordpress.org/ticket/10140',
			'http://core.trac.wordpress.org/ticket/10407',
			'http://core.trac.wordpress.org/ticket/10541',
			'http://www.semiologic.com/software/version-checker')
			. '</p>';
		
		echo '<p>'
			. sprintf(__('Lastly, and this <strong>applies to <a href="%1$s">Hub users</a> only</strong>, it is <strong>much</strong> faster (and much safer) to upgrade from your hosting account\'s control panel than from this screen.', 'version-checker'), 'http://members.semiologic.com/hosting/')
			. '</p>' . "\n";

		echo '</div>' . "\n";
		
		global $action;
		if ( !$_POST && $action == 'upgrade-core' ) {
			ob_start(array('sem_update_core', 'ob_callback'));
			add_action('in_footer', array('sem_update_core', 'ob_flush'), -1000);
			
			return;
		}
		
		global $wp_version;
		$bail = false;
		
		if ( $wp_version == '2.8' ) {
			$file = file_get_contents(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
			$bail = strpos($file, '$wp_filesystem->delete($wp_dir,') !== false;
		}
		
		if ( $bail ) {
			echo '<div class="error">' . "\n";
			
			echo '<p><strong><big>'
				. __('This automated upgrade has been cancelled because it can result in your site getting deleted.', 'version-checker')
				. '</big></strong></p>' . "\n";
			
			echo '<p>'
				. __('Your WordPress 2.8 installation has a critical bug (which is fixed in 2.8.1) whereby, if the automated upgrader fails while the files are being copied, the upgrader will <strong>delete</strong> all of your site\'s files (including your attachments, and other sites you may have in the same directory).', 'version-checker')
				. '</p>' . "\n";
			
			echo '<p>'
				. sprintf(__('Download and edit your site\'s <code>%s</code> file using notepad; find this line in the Core_Upgrader class (around lines 650-710):', 'version-checker'), 'wp-admin/includes/class-wp-upgrader.php')
				. '</p>' . "\n";
			
			echo '<pre><code>$wp_filesystem->delete($wp_dir, true);</code></pre>' . "\n";
			
			echo '<p>'
				. __('Change it to:', 'version-checker')
				. '</p>' . "\n";
			
			echo '<pre><code>$wp_filesystem->delete($working_dir, true);</code></pre>' . "\n";
			
			echo '<p>'
				. sprintf(__('Then, re-upload the file to your site and <a href="%s">start over</a>. You will no longer see this warning if it\'s safe to proceed.', 'version-checker'), 'update-core.php')
				. '</p>' . "\n";
			
			echo '</div>' . "\n";
			
			version_checker::force_flush();
			ob_start(array('sem_update_core', 'wp_2_8_ob_callback'));
			add_action('in_footer', array('sem_update_core', 'ob_flush'), -1000);
			
			return;
		} elseif ( !version_checker::check('sem-pro') ) {
			return;
		}
		
		# give some extra resources to WP
		@ini_set('memory_limit', '256M');

		echo '<div class="updated fade">' . "\n";

		echo '<p>'
			. __('As you await feedback from the WP upgrader, here are some preemptive troubleshooting tips in the event it fails...', 'version-checker')
			. '</p>' . "\n";

		echo '<p>'
			. __('The single most common reason upgrades fail are server and network timeouts. They can have all sorts of origins, but the most frequent one (FTP timeouts) is related to your server\'s configuration and should be reported to your host. Before you do, however...', 'version-checker')
			. '</p>' . "\n";

		echo '<p>'
			. __('WP 2.9 introduces an FS_TIMEOUT constant for FTP connections. Version Checker introduces the same in WP 2.8, and makes it default to 15 minutes (900 seconds). It\'s a garguantuan value by any measure -- but we found it necessary on slower hosts. If yours is even slower than slow, increase it by adding a define in your wp-config.php file, e.g.:', 'version-checker')
			. '</p>' . "\n";

		echo '<p><code>define(\'FS_TIMEOUT\', 1800); // 30 minutes</code></p>' . "\n";

		echo '<p>'
			. __('Now, on to the frequently encountered issues in case you get one of them... If the download is failing altogether, or if WP reports the zip is corrupt (a PCLZIP_ERR of some kind):', 'version-checker') . "\n"
			. '</p>' . "\n";

		echo '<ol>' . "\n";

		echo '<li>'
			. __('Start by trying again unless there is an obvious server configuration problem. There could have been a network problem, it could be that the originating server is getting hammered by a spike of downloads; it could be anything...', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. sprintf(__('If it continues to fail, activate the <a href="%s">Core Control</a> plugin, and enable the HTTP Access module under Tools / Upgrade.', 'version-checker'), 'http://dd32.id.au/wordpress-plugins/?plugin=core-control')
			. '</li>' . "\n";

		echo '<li>'
			. __('Click &quot;External HTTP Access&quot; under Tools / Upgrade (near the screen\'s title), and disable the current HTTP transport.', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. __('Revisit this screen, and try again.', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. __('Repeat the above steps if it\'s still failing, until you run out of available transports to try. (Oftentimes, at least one of them will succeed.)', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. __('Don\'t forget to re-enable the HTTP transports if they all fail. (In this case, the odds are it\'s failing due to the way the server is configurated, and you should definitely report the issue to your host.)', 'version-checker')
			. '</li>' . "\n";

		echo '</ol>' . "\n";

		echo '<p>'
			. __('If unzipping is failing (in this case, WP will typically report that it\'s failing to copy a file, always the same one, over and over):', 'version-checker') . "\n"
			. '</p>' . "\n";

		echo '<ol>' . "\n";

		echo '<li>'
			. __('Start by trying again. It might work this time.', 'version-checker')
			. '</li>' . "\n";
		
		echo '<li>'
			. __('If it fails again, consider emptying the wp-content/upgrade folder from your site using FTP software. It potentially contains folders that WP attempts to delete using poorly optimized code.', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. sprintf(__('If it continues to fail, activate the <a href="%s">Core Control</a> plugin, and enable the Filestem module under Tools / Core Control.', 'version-checker'), 'http://dd32.id.au/wordpress-plugins/?plugin=core-control')
			. '</li>' . "\n";

		echo '<li>'
			. __('Click &quot;Filesystem Access&quot; under Tools / Core Control (near the screen\'s title), and switch to using the PHP FTP Sockets method if you are using the PHP FTP Extension. On some servers (though not all) the Sockets method works better than the built-in method.', 'version-checker')
			. '</li>' . "\n";

		echo '</ol>' . "\n";

		echo '<p>'
			. __('If the file copying starts but bails at some point:', 'version-checker') . "\n"
			. '</p>' . "\n";

		echo '<ol>' . "\n";
		
		echo '<li>'
			. __('Don\'t browse away from this screen. Especially not if a new WP version is involved. Your site might be unavailable due to an incomplete WP upgrade, and you want this screen to be around if this is the case. Load Tools / Upgrade in a new tab or window.', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. __('If you can, start by trying again. It might work this time.', 'version-checker')
			. '</li>' . "\n";
		
		echo '<li>'
			. __('If it fails again, consider emptying the wp-content/upgrade folder from your site using FTP software. It potentially contains folders that WP attempts to delete using poorly optimized code.', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. sprintf(__('If it continues to fail, activate the <a href="%s">Core Control</a> plugin, and enable the Filestem module under Tools / Core Control.', 'version-checker'), 'http://dd32.id.au/wordpress-plugins/?plugin=core-control')
			. '</li>' . "\n";

		echo '<li>'
			. __('Click &quot;Filesystem Access&quot; under Tools / Core Control (near the screen\'s title), and switch to using the PHP FTP Sockets method if you are using the PHP FTP Extension. On some servers (though not all) the Sockets method works better than the built-in method.', 'version-checker')
			. '</li>' . "\n";

		echo '<li>'
			. sprintf(__('If it persists in failing, or if this screen is no longer available, you\'re in for a manual <a href="%1$s">install</a> or <a href="%2$s">upgrade</a>.', 'version-checker'), 'http://members.semiologic.com/sem-pro/install/', 'http://members.semiologic.com/sem-pro/upgrade/')
			. '</li>' . "\n";
		
		echo '<li>'
			. __('If you see a "there was a failed upgrade" sort of message in your admin area after a manual upgrade, it is due to the presence of a .maintenance file in your site\'s root folder. Delete it to remove it.', 'version-checker')
			. '</li>' . "\n";

		echo '</ol>' . "\n";
		
		echo '<p>'
			. sprintf(__('And yes, it\'s <strong>much faster</strong> (and much safer) to upgrade from the control panel when you\'re <strong>hosted with us on <a href="%s">hub</a></strong>.', 'version-checker'), 'http://members.semiologic.com/hosting/')
			. '</p>' . "\n";
		
		echo '</div>' . "\n";
		
		version_checker::force_flush();
	} # ob_start()
	
	
	/**
	 * ob_flush()
	 *
	 * @return void
	 **/

	function ob_flush() {
		while ( ob_get_level() )
			ob_end_flush();
	} # ob_flush()
	
	
	/**
	 * wp_2_8_ob_callback()
	 *
	 * @param string $buffer
	 * @return string $buffer
	 **/

	function wp_2_8_ob_callback($buffer) {
		return preg_replace('/<form\b.+<\/form>/s', '', $buffer);
	} # wp_2_8_ob_callback()
	
	
	/**
	 * ob_callback()
	 *
	 * @param string $buffer
	 * @return string $buffer
	 **/

	function ob_callback($buffer) {
		$find_replace = array(
			__('Upgrade WordPress') => __('Upgrade Semiologic Pro', 'version-checker'),
			__('You are using a development version of WordPress.') => __('You are using a development version of Semiologic Pro.', 'version-checker'),
			__('There is a new version of WordPress available for upgrade') => __('There is a new version of Semiologic Pro available for upgrade', 'version_checker'),
			__('You have the latest version of WordPress.') => __('You have the latest version of Semiologic Pro.', 'version-checker')
			);
		return str_replace(array_keys($find_replace), array_values($find_replace), $buffer);
	} # ob_callback()
} # sem_update_core

add_action('admin_notices', array('sem_update_core', 'ob_start'), 1000);
add_filter('update_feedback', array('sem_update_core', 'update_feedback'), 200);
?>