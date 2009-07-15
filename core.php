<?php
/**
 * sem_update_core
 *
 * @package Version Checker
 **/

add_action('admin_notices', array('sem_update_core', 'ob_start'), 1000);
add_filter('update_feedback', array('sem_update_core', 'update_feedback'), 0);

class sem_update_core {
	/**
	 * update_feedback()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function update_feedback($in = null) {
		static $done = false;
		global $wp_filesystem;
		
		if ( $done || !$_POST || !is_object($wp_filesystem) )
			return $in;
		
		if ( is_a($wp_filesystem, 'WP_Filesystem_FTPext') && $wp_filesystem->link ) {
			if ( @ftp_get_option($wp_filesystem->link, FTP_TIMEOUT_SEC) < 300 )
				@ftp_set_option($wp_filesystem->link, FTP_TIMEOUT_SEC, 600);
			$done = true;
		}
		
		return $in;
	} # update_feedback()
	
	
	/**
	 * ob_start()
	 *
	 * @return void
	 **/

	function ob_start() {
		global $action;
		
		if ( $_POST || $action != 'upgrade-core' ) {
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
				
				ob_start(array('sem_update_core', 'wp_2_8_ob_callback'));
				add_action('in_footer', array('sem_update_core', 'ob_flush'), -1000);
			} elseif ( version_checker::check('sem-pro') ) {
				# give some extra resources to WP
				if ( function_exists('memory_get_usage') && ( (int) @ini_get('memory_limit') < 128 ) )
					@ini_set('memory_limit', '128M');
				@set_time_limit(600);

				echo '<div class="updated fade">' . "\n";

				echo '<p><strong>'
					. __('Some preemptive troubleshooting...', 'version-checker')
					. '</strong></p>' . "\n";

				echo '<p>'
					. __('Do not interrupt a core upgrade once it\'s started. It can take several minutes to complete - albeit never more than 10 minutes.', 'version-checker')
					. '</p>' . "\n";

				echo '<p>'
					. __('Frequently, failed upgrades will be related to your server\'s configuration, and should be reported to your host. Before you do, however:', 'version-checker')
					. '</p>' . "\n";

				echo '<ol>' . "\n";

				echo '<li>'
					. sprintf(__('Install the <a href="%s">Core Control</a> plugin.', 'version-checker'), 'http://dd32.id.au/wordpress-plugins/?plugin=core-control')
					. '</li>' . "\n";

				echo '<li>'
					. __('Under Tools / Core Control, enable the HTTP Access and the Filesystem Modules.', 'version-checker')
					. '</li>' . "\n";

				echo '</ol>' . "\n";

				echo '<p>'
					. __('If the download or the unzip is failing:', 'version-checker') . "\n"
					. '</p>' . "\n";

				echo '<ol>' . "\n";

				echo '<li>'
					. __('Click &quot;External HTTP Access&quot; on the screen (the link is nearby the screen\'s title).', 'version-checker')
					. '</li>' . "\n";

				echo '<li>'
					. __('Disable the current HTTP transport.', 'version-checker')
					. '</li>' . "\n";

				echo '<li>'
					. __('Revisit this screen, and save try again.', 'version-checker')
					. '</li>' . "\n";

				echo '<li>'
					. __('Repeat the above steps if it\'s still failing, until you run out of available transports to try. (Oftentimes, at least one of them will succeed.)', 'version-checker')
					. '</li>' . "\n";

				echo '<li>'
					. __('Don\'t forget to re-enable the HTTP transports if they all fail.', 'version-checker')
					. '</li>' . "\n";

				echo '</ol>' . "\n";

				echo '<p>'
					. __('If the upgrade starts properly, but fails while the files are still being copied:', 'version-checker')
					. '</p>' . "\n";

				echo '<ol>' . "\n";

				echo '<li>'
					. __('Click &quot;Filesystem Access&quot; and verify that the PHP FTP Extension is not being used.', 'version-checker')
					. '</li>' . "\n";

				echo '<li>'
					. __('If it is, disable it, so that the PHP FTP Sockets method gets used instead.', 'version-checker')
					. '</li>' . "\n";

				echo '</ol>' . "\n";
				
				echo '<p>'
					. __('Expanding on the previous point, many hosts configure servers in such a way that the FTP connection dies before WP upgrades completely. There is code in the Version Checker plugin that seeks to fix this, but it doesn\'t work on all servers. If you frequently experience FTP slowdowns or timeouts on your server, you may even want to disable the PHP FTP Extension without even trying it.', 'version-checker')
					. '</p>' . "\n";

				echo '</div>' . "\n";
			}
			
			return;
		}
		
		$update_core = get_transient('update_core');
		if ( !$update_core || empty($update_core->response) || empty($update_core->response->package) )
			return;
		
		ob_start(array('sem_update_core', 'ob_callback'));
		add_action('in_footer', array('sem_update_core', 'ob_flush'), -1000);
	} # ob_start()
	
	
	/**
	 * ob_flush()
	 *
	 * @return void
	 **/

	function ob_flush() {
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
			__('Upgrade WordPress', 'version-checker') => __('Upgrade Semiologic Pro', 'version-checker'),
			__('You are using a development version of WordPress.', 'version-checker') => __('You are using a development version of Semiologic Pro.', 'version-checker'),
			__('There is a new version of WordPress available for upgrade', 'version_checker') => __('There is a new version of Semiologic Pro available for upgrade', 'version_checker'),
			__('You have the latest version of WordPress.', 'version-checker') => __('You have the latest version of Semiologic Pro.', 'version-checker')
			);
		return str_replace(array_keys($find_replace), array_values($find_replace), $buffer);
	} # ob_callback()
} # sem_update_core
?>