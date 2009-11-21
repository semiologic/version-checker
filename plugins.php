<?php
/**
 * sem_update_plugins
 *
 * @package Version Checker
 **/

class sem_update_plugins {
	/**
	 * plugins_api()
	 *
	 * @param false $res
	 * @param string $action
	 * @param array $args
	 * @return $res
	 **/

	function plugins_api($res, $action, $args) {
		switch ( $action ) {
		case 'plugin_information':
			return sem_update_plugins::info($res, $action, $args);
		
		default:
			return $res;
		}
	} # plugins_api()
	
	
	/**
	 * info()
	 *
	 * @param false $res
	 * @param string $action
	 * @param array $args
	 * @return $res
	 **/

	function info($res, $action, $args) {
		$sem_api_key = get_option('sem_api_key');
		
		if ( !$sem_api_key )
			return $res;
		
		$plugin = get_plugins("/$args->slug");
		if ( !$plugin )
			return $res;
		
		$plugin = current($plugin);
		if ( empty($plugin['PluginURI']) || !preg_match("!^https?://[^/]+.semiologic.com!", $plugin['PluginURI']) )
			return $res;
		
		global $wp_version;
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/info/0.1/plugins/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/info/plugins/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/info/trunk/plugins/" . $sem_api_key;
		}
		
		$body = array(
			'action' => 'info',
			'slug' => $args->slug,
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
		
		if ( $response !== false )
			return sem_update_plugins::parse($response);
		
		return $res;
	} # info()
	
	
	/**
	 * parse()
	 *
	 * @param object $obj
	 * @return object $obj
	 **/

	function parse($obj) {
		if ( is_array($obj) ) {
			foreach ( $obj as $k => $v ) {
				$v = sem_update_plugins::parse($v);
				if ( $obj[$k] )
					$obj[$k] = $v;
				else
				 	unset($obj[$k]);
			}
			return $obj;
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
		
		# dump header
		$header = array_shift($readme);
		$header = explode("\n", $header);
		
		$name = false;
		do {
			$name = array_shift($header);
			if ( preg_match("/^===\s*(.+?)\s*(?:===)?$/", $name, $name) )
				$obj->name = end($name);
		} while ( $header && !$obj->name );
		
		while ( $junk = array_shift($header) );
		
		$obj->short_description = Markdown(implode("\n", $header));
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
		$obj->author = '<a href="http://www.semiologic.com">'
			. strip_tags(__('Semiologic', 'version-checker'))
			. '</a>';
		
		foreach ( get_object_vars($obj) as $key => $val ) {
			switch ( $key ) {
			case 'name':
			case 'slug':
			case 'version':
			case 'requires':
			case 'tested':
			case 'last_updated':
			case 'author':
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

add_filter('plugins_api', array('sem_update_plugins', 'plugins_api'), 10, 3);
?>