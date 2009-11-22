<?php
/**
 * sem_update_plugins
 *
 * @package Version Checker
 **/

class sem_update_plugins {
	/**
	 * install_plugins_tabs()
	 *
	 * @param array $tabs
	 * @return array $tabs
	 **/

	function install_plugins_tabs($tabs) {
		$tabs['semiologic'] = __('Semiologic', 'version-checker');
		return $tabs;
	} # install_plugins_tabs()
	
	
	/**
	 * install_plugins_semiologic()
	 *
	 * @param int $page
	 * @return void
	 **/

	function install_plugins_semiologic($page = 1) {
		$args = array('browse' => 'semiologic', 'page' => $page);
		$api = plugins_api('query_plugins', $args);
		display_plugins_table($api->plugins, $api->info['page'], $api->info['pages']);
	} # install_plugins_semiologic()
	
	
	/**
	 * plugins_api()
	 *
	 * @param false $res
	 * @param string $action
	 * @param array $args
	 * @return $res
	 **/

	function plugins_api($res, $action, $args) {
		if ( $res || !get_option('sem_api_key') )
			return $res;
		
		switch ( $action ) {
		case 'plugin_information':
			return sem_update_plugins::info($res, $action, $args);
		
		case 'query_plugins':
		if ( !empty($args->browse) && $args->browse == 'semiologic' )
			return sem_update_plugins::query($res, $action, $args);
		
		default:
			return $res;
		}
	} # plugins_api()
	
	
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
			'plugins' => array(),
			);
		
		$response = version_checker::query('plugins');
		if ( $response && is_array($response) ) {
			$res->info['results'] = count($response);
			$res->plugins = $response;
			usort($res->plugins, array('sem_update_plugins', 'sort'));
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
		$plugins = version_checker::query('plugins');
		if ( !isset($plugins[$args->slug]) || empty($plugins[$args->slug]->download_link) )
			return $res;
		
		if ( !preg_match("!^https?://[^/]+.semiologic.com!", $plugins[$args->slug]->download_link) )
			return $res;
		
		return $plugins[$args->slug];
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
	 * parse()
	 *
	 * @param object $obj
	 * @return object $obj
	 **/

	function parse($obj) {
		if ( is_array($obj) ) {
			$res = array();
			foreach ( $obj as $k => $v ) {
				$v = sem_update_plugins::parse($v);
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
		$plugins_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
		
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
		
		$obj->short_description = wp_kses(Markdown(implode("\n", $header)), $allowedposttags);
		$obj->description = $obj->short_description;
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
				$obj->$key = wp_kses($val, $plugins_allowedtags);
				break;
			
			case 'rating':
			case 'num_ratings':
				$obj->$key = intval($val);
				break;
			
			case 'homepage':
			case 'download_link':
				$obj->$key = clean_url($val);
				break;
			
			case 'author':
				$url = clean_url($obj->homepage);
				if ( preg_match("!^https?://[^/]+.semiologic.com!", $url) )
					$url = 'http://www.semiologic.com';
				$obj->$key = '<a href="' . $url . '">'
					. str_replace('-', ' ', wp_kses($val, $plugins_allowedtags))
					. '</a>';
				break;
			
			case 'short_description':
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
} # sem_update_plugins

add_filter('install_plugins_tabs', array('sem_update_plugins', 'install_plugins_tabs'));
add_action('install_plugins_semiologic', array('sem_update_plugins', 'install_plugins_semiologic'));

add_filter('plugins_api', array('sem_update_plugins', 'plugins_api'), 10, 3);
?>