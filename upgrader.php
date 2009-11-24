<?php
if ( !class_exists('WP_Upgrader') )
	include ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * sem_upgrader
 *
 * @package Version Checker
 **/

class sem_upgrader extends Plugin_Upgrader {
	var $bulk = true;
	
	/**
	 * maintenance_mode()
	 *
	 * @param bool $enable
	 * @return void
	 **/
	
	function maintenance_mode($enable = false) {
		global $wp_filesystem;
		$file = $wp_filesystem->abspath() . '.maintenance';
		if ( $enable ) {
			$this->skin->feedback('maintenance_start');
			// Create maintenance file to signal that we are upgrading
			$maintenance_string = '<?php $upgrading = ' . time() . '; ?>';
			$wp_filesystem->delete($file);
			$wp_filesystem->put_contents($file, $maintenance_string, FS_CHMOD_FILE);
		} else if ( !$enable && $wp_filesystem->exists($file) ) {
			$this->skin->feedback('maintenance_end');
			$wp_filesystem->delete($file);
		}
	} # maintenance_mode()
	
	
	/**
	 * run()
	 *
	 * @param array $options
	 * @return void
	 **/

	function run($options) {
		$defaults = array( 	'package' => '', //Please always pass this.
							'destination' => '', //And this
							'clear_destination' => false,
							'clear_working' => true,
							'is_multi' => false,
							'hook_extra' => array() //Pass any extra $hook_extra args here, this will be passed to any hooked filters.
						);
		
		$options = wp_parse_args($options, $defaults);
		extract($options);
		
		//Connect to the Filesystem first.
		$res = $this->fs_connect( array(WP_CONTENT_DIR, $destination) );
		if ( ! $res ) //Mainly for non-connected filesystem.
			return false;
		
		if ( is_wp_error($res) ) {
			$this->skin->error($res);
			return $res;
		}
		
		if ( !$is_multi ) // call $this->header separately if running multiple times
			$this->skin->header();
		
		$this->skin->before();
		
		//Download the package (Note, This just returns the filename of the file if the package is a local file)
		$download = $this->download_package( $package );
		if ( is_wp_error($download) ) {
			$this->skin->error($download);
			return $download;
		}
		
		//Unzip's the file into a temporary directory
		$working_dir = $this->unpack_package( $download );
		if ( is_wp_error($working_dir) ) {
			$this->skin->error($working_dir);
			return $working_dir;
		}
		
		//With the given options, this installs it to the destination directory.
		$result = $this->install_package( array(
											'source' => $working_dir,
											'destination' => $destination,
											'clear_destination' => $clear_destination,
											'clear_working' => $clear_working,
											'hook_extra' => $hook_extra
										) );
		$this->skin->set_result($result);
		if ( is_wp_error($result) ) {
			$this->skin->error($result);
			$this->skin->feedback('process_failed');
		} else {
			//Install Suceeded
			$this->skin->feedback('process_success');
		}
		$this->skin->after();
		
		if ( !$is_multi )
			$this->skin->footer();
		
		version_checker::force_flush();
		
		return $result;
	} # run()
	
	
	/**
	 * bulk_upgrade()
	 *
	 * @param array $plugins
	 * @return void
	 **/

	function bulk_upgrade($plugins) {
		$this->init();
		$this->upgrade_strings();
		$current = get_transient('update_plugins');
		
		add_filter('upgrader_clear_destination', array(&$this, 'delete_old_plugin'), 10, 4);
		
		$this->skin->header();
		
		// Connect to the Filesystem first.
		$res = $this->fs_connect( array(WP_CONTENT_DIR, WP_PLUGIN_DIR) );
		if ( ! $res ) {
			$this->skin->footer();
			return false;
		}
		
		$this->maintenance_mode(true);
		
		$all = count($plugins);
		$i = 1;
		foreach ( $plugins as $plugin ) {
			$plugin = "$plugin/$plugin.php";
			$this->show_before = sprintf( '<h4>' . __('Updating plugin %1$d of %2$d...', 'version-checker') . '</h4>', $i, $all );
			$i++;
			
			if ( !isset( $current->response[ $plugin ] ) ) {
				$this->skin->set_result(false);
				$this->skin->error('up_to_date');
				$this->skin->after();
				$results[$plugin] = false;
				continue;
			}
			
			if ( !( $i % 10 ) ) # reconnect every 10 plugin
				version_checker::reconnect_ftp();
			
			// Get the URL to the zip file
			$r = $current->response[ $plugin ];
			
			$this->skin->plugin_active = is_plugin_active($plugin);
			
			$result = $this->run(array(
						'package' => $r->package,
						'destination' => WP_PLUGIN_DIR,
						'clear_destination' => true,
						'clear_working' => true,
						'is_multi' => true,
						'hook_extra' => array(
									'plugin' => $plugin
						)
					));
			
			$results[$plugin] = $this->result;
			
			// Prevent credentials auth screen from displaying multiple times
			if ( false === $result )
				break;
		}
		
		$this->sem_permissions();
		
		$this->maintenance_mode(false);
		$this->skin->footer();
		
		// Cleanup our hooks, incase something else does a upgrade on this connection.
		remove_filter('upgrader_clear_destination', array(&$this, 'delete_old_plugin'));
		
		// Force refresh of plugin update information
		delete_transient('update_plugins');
		delete_transient('sem_update_plugins');
		
		return $results;
	} # bulk_upgrade()
	
	
	/**
	 * bulk_install()
	 *
	 * @param array $plugins
	 * @return void
	 **/

	function bulk_install($plugins) {
		$this->init();
		$this->upgrade_strings();
		$this->install_strings();
		$sem_plugins = sem_update_plugins::cache();
		
		$this->skin->header();
		
		// Connect to the Filesystem first.
		$res = $this->fs_connect( array(WP_CONTENT_DIR, WP_PLUGIN_DIR) );
		if ( ! $res ) {
			$this->skin->footer();
			return false;
		}
		
		$this->maintenance_mode(true);
		
		$all = count($plugins);
		$i = 1;
		foreach ( $plugins as $plugin ) {
			$this->show_before = sprintf( '<h4>' . __('Installing plugin %1$d of %2$d...', 'version-checker') . '</h4>', $i, $all );
			$i++;
			
			if ( !isset( $sem_plugins[ $plugin ] ) ) {
				$this->skin->set_result(false);
				$this->skin->error('no_package');
				$this->skin->after();
				$results[$plugin] = false;
				continue;
			}
			
			if ( !( $i % 10 ) ) # reconnect every 10 plugin
				version_checker::reconnect_ftp();
			
			// Get the URL to the zip file
			$r = $sem_plugins[ $plugin ];
			
			$this->skin->plugin_active = false;
			
			$result = $this->run(array(
						'package' => $r->download_link,
						'destination' => WP_PLUGIN_DIR,
						'clear_destination' => false,
						'clear_working' => true,
						'is_multi' => true,
						'hook_extra' => array(
									'plugin' => $plugin
						)
					));
			
			$results[$plugin] = $this->result;
			
			// Prevent credentials auth screen from displaying multiple times
			if ( false === $result )
				break;
		}
		
		$this->sem_permissions();
		
		$this->maintenance_mode(false);
		
		if ( function_exists('activate_plugins') && current_user_can('activate_plugins') ) {
			$to_activate = array();

			foreach ( array_keys($sem_plugins) as $plugin ) {
				if ( in_array($plugin, $plugins) && !is_wp_error($results[$plugin]) )
					$to_activate["$plugin/$plugin.php"] = $sem_plugins[$plugin]->name;
			}
			
			if ( $to_activate ) {
				show_message(sprintf(__('Attempting to activate %s.', 'version-checker'), implode(', ', $to_activate)));
				echo '<iframe style="border:0;overflow:hidden" width="100%" height="170px" src="' . wp_nonce_url('update.php?action=bulk-activate-plugins&plugins[]=' . implode('&plugins[]=', array_keys($to_activate)), 'bulk-activate-plugins') .'"></iframe>';
			}
		}
		
		$this->skin->footer();
		
		// Force refresh of plugin update information
		delete_transient('update_plugins');
		delete_transient('sem_update_plugins');
		
		return $results;
	} # bulk_install()
	
	
	/**
	 * sem_permissions()
	 *
	 * @return void
	 **/
	
	function sem_permissions() {
		global $wp_filesystem;
		
		foreach ( array(
			'.htaccess',
			'wp-config.php',
			) as $file ) {
			if ( !is_writable(ABSPATH . $file) ) {
				show_message(sprintf(__('Changing %s permissions...', 'version-checker'), $file));
				$wp_dir = trailingslashit($wp_filesystem->abspath());
				$wp_filesystem->chmod($wp_dir . $file, 0666);
			}
		}
		
		if ( !is_writable(WP_CONTENT_DIR) ) {
			show_message(__('Changing wp-content permissions...', 'version-checker'));
			$content_dir = $wp_filesystem->find_folder(WP_CONTENT_DIR);
			$wp_filesystem->chmod($content_dir, 0777);
		}
	} # sem_permissions()
	
	
	/**
	 * sem_activate()
	 *
	 * @param string $plugin
	 * @return void
	 **/

	function sem_activate($plugin) {
		$defaults = array(
			'ad-manager',
			'auto-thickbox',
			'contact-form',
		 	'feedburner',
			'fuzzy-widgets',
			'google-analytics',
			'inline-widgets',
			'mediacaster',
			'newsletter-manager',
			'nav-menus',
			'redirect-manager',
			'related-widgets',
			'script-manager',
			'sem-admin-menu',
			'sem-bookmark-me',
			'sem-fancy-excerpt',
			'sem-fixes',
			'sem-frame-buster',
			'sem-seo',
			'sem-subscribe-me',
			'silo',
			'version-checker',
			'widget-contexts',
			'wp-hashcash',
			);
		
		return in_array($plugin, $defaults) && !is_plugin_active($plugin);
	} # sem_activate()
} # sem_upgrader


/**
 * sem_upgrader_skin
 *
 * @package Version Checker
 **/

class sem_upgrader_skin extends Plugin_Upgrader_Skin {
	/**
	 * after()
	 *
	 * @return void
	 **/

	function after() {
		return;
	} # after()
} # sem_upgrader_skin


/**
 * sem_installer_skin
 *
 * @package Version Checker
 **/

class sem_installer_skin extends Plugin_Installer_Skin {
	/**
	 * after()
	 *
	 * @return void
	 **/

	function after() {
		return;
	} # after()
} # sem_installer_skin
?>