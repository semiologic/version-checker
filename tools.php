<?php
/**
 * sem_tools
 *
 * @package Version Checker
 **/

class sem_tools {
	/**
	 * display()
	 *
	 * @return void
	 **/

	function display() {
		if ( !current_user_can('manage_options') || !current_user_can('install_plugins') || !current_user_can('install_themes') )
			return;
		
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/theme-install.php';
		
		if ( $_POST ) {
			if ( $_REQUEST['action'] == 'mass-install' ) {
				$sem_plugins = sem_update_plugins::cache();
				
				if ( !$sem_plugins ) {
					echo '<p>'
						. __('Plugin lookup failed. Please refresh this page in a few minutes to try again.', 'version-checker')
						. '</p>' . "\n";
					return;
				}
				
				$to_install = array();
				
				$installed = get_plugins();
				
				foreach ( array_keys($sem_plugins) as $slug ) {
					$file1 = "$slug/$slug.php";
					$file2 = "$slug.php";
					if ( !isset($installed[$file1]) && !isset($installed[$file2]) && $sem_plugins[$slug]->download_link )
						$to_install[] = $slug;
				}
				
				sem_update_plugins::mass_install($to_install);
			} else {
				$to_upgrade = array();
				
				$installed = get_plugins();
				$response = get_transient('update_plugins');
				$response = is_object($response) ? (array) $response->response : array();
				
				foreach ( $response as $file => $resp ) {
					if ( version_compare($response[$file]->new_version, $installed[$file]['Version'], '>') && $response[$file]->package )
						$to_upgrade[] = $resp->slug;
				}
				
				if ( !$to_upgrade ) {
					delete_transient('update_plugins');
					delete_transient('sem_update_plugins');
					
					echo '<div class="error">'
						. '<p>'
						. __('Nothing to do... Aborting.', 'version-checker')
						. '</p>'
						. '</div>' . "\n";
				} else {
					sem_update_plugins::mass_upgrade($to_upgrade);
				}
			}
		} else {
			echo '<div class="wrap">' . "\n";
			
			screen_icon();
			
			echo '<h2>' . __('Semiologic Packages', 'version-checker') . '</h2>' . "\n";
			
			echo '<h3>' . __('Plugins', 'version-checker') . '</h3>' . "\n";
			
			sem_update_plugins::install_plugins_semiologic();
			
			if ( get_option('template') != 'sem-reloaded' ) {
				echo '<h3>' . __('Themes', 'version-checker') . '</h3>' . "\n";
				sem_update_themes::install_themes_semiologic();
			}
			
			echo '</div>' . "\n";
		}
	} # display()
} # sem_tools
?>