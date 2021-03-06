<?php
/**
 * sem_update_themes
 *
 * @package Version Checker
 **/

class sem_update_themes {
    /**
     * sem_update_themes()
     */
    function __construct() {
        add_filter('install_themes_tabs', array($this, 'install_themes_tabs'));
        add_action('install_themes_semiologic', array($this, 'install_themes_semiologic'));

        add_filter('themes_api', array($this, 'themes_api'), 10, 3);
    }

    /**
	 * install_themes_tabs()
	 *
	 * @param array $tabs
	 * @return array $tabs
	 **/

	function install_themes_tabs($tabs) {
		if ( get_site_option('sem_api_key') )
			$tabs['semiologic'] = __('Semiologic', 'version-checker');
		return $tabs;
	} # install_themes_tabs()
	
	
	/**
	 * install_themes_semiologic()
	 *
	 * @param int $page
	 * @return void
	 **/

	static function install_themes_semiologic($page = 1) {
		global $version_checker;

		include_once $version_checker->plugin_path . 'sem-class-wp-themes-list-table.php';
		include_once $version_checker->plugin_path . 'sem-class-wp-theme-install-list-table.php';

		$args = array('browse' => 'semiologic', 'page' => $page);
		$api = themes_api('query_themes', $args);

		global $wp_list_table;
		$wp_list_table = new WP_Theme_Install_List_Table();

		$wp_list_table->items = $api->themes;
		$wp_list_table->set_pagination_args( array(
			'total_items' => $api->info['results'],
			'per_page' => 30,
		) );
		
//		display_themes($api->themes, $api->info['page'], $api->info['pages']);
		$wp_list_table->display();
	} # install_themes_semiologic()


    /**
     * themes_api()
     *
     * @param object $res
     * @param string $action
     * @param array $args
     * @return object $res
     */

	function themes_api($res, $action, $args) {
		if ( $res || !get_site_option('sem_api_key') )
			return $res;
		
		switch ( $action ) {
		case 'theme_information':
			return sem_update_themes::info($res, $action, $args);
		
		case 'query_themes':
		if ( !empty($args->browse) && $args->browse == 'semiologic' )
			return sem_update_themes::query($res, $action, $args);
		
		default:
			return $res;
		}
	} # themes_api()
	
	
	/**
	 * query()
	 *
	 * @param object $res
	 * @param string $action
	 * @param array $args
	 * @return mixed $res
	 **/

	function query($res, $action, $args) {
		$res = (object) array(
			'info' => array('page' => 1, 'pages' => 1, 'results' => 0),
			'themes' => array(),
			);
		
		$response = sem_update_themes::cache();
		if ( $response && is_array($response) ) {
			$res->info['results'] = count($response);
			$res->themes = $response;
			usort($res->themes, array($this, 'sort'));
		}
		
		return $res;
	} # query()
	
	
	/**
	 * info()
	 *
	 * @param object $res
	 * @param string $action
	 * @param array $args
	 * @return mixed $res
	 **/

	function info($res, $action, $args) {
		$themes = sem_update_themes::cache();
		if ( !isset($themes[$args->slug]) || empty($themes[$args->slug]->download_link) )
			return $res;
		
		if ( !preg_match("!^https?://[^/]+.semiologic.com!", $themes[$args->slug]->download_link) )
			return $res;
		
		return $themes[$args->slug];
	} # info()
	
	
	/**
	 * sort()
	 *
	 * @param object $a
	 * @param object $b
	 * @return int $sort
	 **/

	function sort($a, $b) {
		return strnatcmp($a->name, $b->name);
	} # sort()


    /**
     * cache()
     *
     * @internal param string $type
     * @return array $themes
     */

	function cache() {
		if ( class_exists('WP_Nav_Menu_Widget') )
			$response = get_site_transient('sem_query_themes');
		else
			$response = get_transient('sem_query_themes');
		if ( !version_checker_debug && $response !== false && !empty($response) )
			return $response;
		
		global $wp_version;
		$sem_api_key = get_site_option('sem_api_key');
		
		$url = sem_api_info . '/themes/' . $sem_api_key;
		
		$body = array(
			'action' => 'query',
			'packages' => get_site_option('sem_packages'),
			);
		
		$options = array(
			'timeout' => 15,
			'body' => $body,
            'user-agent' => 'WordPress/' . preg_replace("/\s.*/", '', $wp_version) . '; ' . get_bloginfo('url'),
//			'user-agent' => 'WordPress/' . preg_replace("/\s.*/", '', "3.2.1") . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = md5(serialize(array($url, $options)));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false && !empty($response)) {
			$response = sem_update_themes::parse($response);
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_query_themes', $response, 7200);
			else
				set_transient('sem_query_themes', $response, 7200);
		}
		
		return $response;
	} # cache()
	
	
	/**
	 * parse()
	 *
	 * @param object $obj
	 * @return object $obj
	 **/

    static function parse($obj) {
		if ( is_array($obj) ) {
			$res = array();
			foreach ( $obj as $k => $v ) {
				$v = sem_update_themes::parse($v);
				if ( $v && is_object($v) && $v->slug )
					$res[$v->slug] = $v;
			}
			$obj = null;
			ksort($res);
			return $res;
		} elseif ( !is_object($obj) ) {
			return false;
		}
		
		if ( empty($obj->readme) || !trim($obj->readme) )
			return false;
		
		if ( !function_exists('Markdown') )
			include_once dirname(__FILE__) . '/markdown/markdown.php';
		global $allowedposttags;
		$themes_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
		
		$readme = str_replace(array("\r\n", "\r"), "\n", $obj->readme);
		$readme = preg_split("/^\s*(==[^=].+?)\s*$/m", $readme, null, PREG_SPLIT_DELIM_CAPTURE);
		
		$header = array_shift($readme);
		$header = explode("\n", $header);

		do {
			$name = array_shift($header);
			if ( preg_match("/^===\s*(.+?)\s*(?:===)?$/", $name, $name) )
				$obj->name = end($name);
		} while ( $header && !$obj->name );
		
		while ( $field = array_shift($header) ) {
			if ( preg_match("/\s*Contributors\s*:\s*(.*)/i", $field, $author) )
				$obj->author = end($author);
		}
		
		$obj->description = wp_kses(Markdown(implode("\n", $header)), $allowedposttags);
		$obj->sections = array();
		
		if ( $readme ) {
			do {
				$section = array_shift($readme);
				$section = trim($section);

				if ( preg_match("/^==\s*(Description)\s*(?:==)?$/i", $section, $desc) ) {
					$desc = str_replace('-', '_', sanitize_title(end($desc)));
					$section = array_shift($readme);
					$section = preg_replace("/^=([^=].+?)=?$/m", "### $1", $section);
					$section = markdown($section);
					$section = wp_kses($section, $allowedposttags);
					$obj->sections[$desc] = $section;
				} elseif ( preg_match("/^==\s*(Change\s*Log)\s*(?:==)?$/i", $section, $desc) ) {
					$desc = str_replace('-', '_', sanitize_title(end($desc)));
					$section = array_shift($readme);
					$section = preg_replace("/^=([^=].+?)=?$/m", "### " . sprintf(__('Version %s', 'version-checker'), "$1"), $section);
					$section = markdown($section);
					$section = wp_kses($section, $allowedposttags);
					$obj->sections[$desc] = $section;
				}
			} while ( $readme );
		}
		
		# sanitize
		$obj->compatability = array();
		
		foreach ( get_object_vars($obj) as $key => $val ) {
			switch ( $key ) {
			case 'name':
			case 'slug':
			case 'version':
			case 'requires':
			case 'tested':
			case 'last_updated':
				$obj->$key = wp_kses($val, $themes_allowedtags);
				break;
			
			case 'rating':
			case 'num_ratings':
				$obj->$key = intval($val);
				break;
			
			case 'homepage':
			case 'download_link':
			case 'preview_url':
			case 'screenshot_url':
				$obj->$key = esc_url($val);
				break;
			
			case 'author':
				$url = esc_url($obj->homepage);
				if ( preg_match("!^https?://[^/]+.semiologic.com!", $url) )
					$url = 'http://www.semiologic.com';
				$obj->$key = '<a href="' . $url . '">'
					. str_replace('-', ' ', wp_kses($val, $themes_allowedtags))
					. '</a>';
				break;
			
			case 'description':
			case 'sections':
				break; # already sanitized
			
			default:
				unset($obj->$key);
				break;
			}
		}
		
		return $obj;
	} # parse()
} # sem_update_themes

$sem_update_themes = new sem_update_themes();
