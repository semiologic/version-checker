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
			$plugins = sem_update_plugins::cache();
			
			$to_install = array();
			$to_upgrade = array();
			
			$installed = get_plugins();
			$response = get_transient('update_plugins');
			$response = is_object($response) ? (array) $response->response : array();

			foreach ( array_keys($plugins) as $slug ) {
				$file = "$slug/$slug.php";
				if ( !isset($installed[$file]) && $plugins[$slug]->download_link )
					$to_install[] = $slug;
				elseif ( version_compare($response[$file]->new_version, $current[$file]['Version'], '>') && $response[$file]->package )
					$to_upgrade[] = $slug;
			}
			
			if ( $_REQUEST['action'] == 'mass-install' )
				sem_update_plugins::mass_install($to_install);
			else
				sem_update_plugins::mass_upgrade($to_upgrade);
		} else {
			echo '<div class="wrap">' . "\n";
			
			screen_icon();
			
			echo '<h2>' . __('Semiologic Packages', 'version-checker') . '</h2>' . "\n";
			
			echo '<h3>' . __('Plugins', 'version-checker') . '</h3>' . "\n";
			
			sem_update_plugins::install_plugins_semiologic();
			
			echo '<h3>' . __('Themes', 'version-checker') . '</h3>' . "\n";
			
			sem_update_themes::install_themes_semiologic();
			
			echo '</div>' . "\n";
		}
	} # display()
} # sem_tools
?>