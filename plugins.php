<?php
/**
 * sem_plugins
 *
 * @package Version Checker
 **/

add_filter('plugins_api', array('sem_plugins', 'plugins_api'), 10, 3);

class sem_plugins {
	/**
	 * plugins_api()
	 *
	 * @param false $res
	 * @param string $action
	 * @param array $args
	 * @return $res
	 **/

	function plugins_api($res, $action, $args) {
		if ( $action != 'plugin_information' )
			return $res;
		
		foreach ( version_checker::get_plugins() as $file => $resp ) {
			if ( $resp->slug == $args->slug ) {
				global $wp_version;
				
				$plugin = current(get_plugins('/' . $resp->slug));
				
				$res = new stdClass;
				$res->version = $resp->new_version;
				$res->author = !empty($plugin['AuthorURI'])
					? ( '<a href="' . esc_url($plugin['AuthorURI']) .'">'
						. strip_tags($plugin['Author'])
						. '</a>' )
					: strip_tags($plugin['Author']);
				$res->homepage = $resp->url;
				$res->download_link = $resp->package;
				$res->slug = $resp->slug;
				$res->sections = array(
					'description' => $plugin['Description'],
					'compatibility' => __('Compatible with the latest version of WordPress.', 'version-checker'),
					);
				break;
			}
		}
		
		return $res;
	} # plugins_api()
} # sem_plugins
?>