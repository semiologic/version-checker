<?php
/**
 * sem_update_themes
 *
 * @package Version Checker
 **/

class sem_update_themes {
	/**
	 * install_themes_tabs()
	 *
	 * @param array $tabs
	 * @return array $tabs
	 **/

	function install_themes_tabs($tabs) {
		$tabs['semiologic'] = __('Semiologic', 'version-checker');
		return $tabs;
	} # install_themes_tabs()
	
	
	/**
	 * install_themes_semiologic()
	 *
	 * @param int $page
	 * @return void
	 **/

	function install_themes_semiologic($page = 1) {
		$args = array('browse' => 'semiologic', 'page' => $page);
		$api = themes_api('query_themes', $args);
		display_themes($api->themes, $api->info['page'], $api->info['pages']);
	} # install_themes_semiologic()
	
	
	/**
	 * themes_api()
	 *
	 * @param false $res
	 * @param string $action
	 * @param array $args
	 * @return $res
	 **/

	function themes_api($res, $action, $args) {
		if ( $res || !get_option('sem_api_key') )
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
	 * @param false $res
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
			usort($res->themes, array('sem_update_themes', 'sort'));
		}
		
		return $res;
	} # query()
	
	
	/**
	 * info()
	 *
	 * @param false $res
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
	 * @param string $type
	 * @return array $themes
	 **/

	function cache() {
		$response = get_transient('sem_query_themes');
		$response = false;
		if ( $response !== false )
			return $response;
		
		global $wp_version;
		$sem_api_key = get_option('sem_api_key');
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/info/0.1/themes/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/info/themes/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/info/trunk/themes/" . $sem_api_key;
		}
		
		$body = array(
			'action' => 'query',
			);
		
		$options = array(
			'timeout' => 3,
			'body' => $body,
			'user-agent' => 'WordPress/' . preg_replace("/\s.*/", '', $wp_version) . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = serialize(array($url, $options));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) {
			$response = sem_update_themes::parse($response);
			set_transient('sem_query_themes', $response, 900);
		}
		
		return $response;
	} # cache()
	
	
	/**
	 * parse()
	 *
	 * @param object $obj
	 * @return object $obj
	 **/

	function parse($obj) {
		if ( is_array($obj) ) {
			$res = array();
			foreach ( $obj as $k => $v ) {
				$v = sem_update_themes::parse($v);
				if ( $v && is_object($v) && $v->slug )
					$res[$v->slug] = $v;
			}
			$obj = null;
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
		
		$name = false;
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
				$obj->$key = clean_url($val);
				break;
			
			case 'author':
				$url = clean_url($obj->homepage);
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

add_filter('install_themes_tabs', array('sem_update_themes', 'install_themes_tabs'));
add_action('install_themes_semiologic', array('sem_update_themes', 'install_themes_semiologic'));

add_filter('themes_api', array('sem_update_themes', 'themes_api'), 10, 3);
?>