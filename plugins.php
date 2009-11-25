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
		if ( get_option('sem_api_key') )
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
		if ( $_POST )
			return;
		
		$args = array('browse' => 'semiologic', 'page' => $page);
		$api = plugins_api('query_plugins', $args);
		sem_update_plugins::display_plugins_table($api->plugins, $api->info['page'], $api->info['pages']);
	} # install_plugins_semiologic()
	
	
	/**
	 * display_plugins_table()
	 *
	 * @param array $plugins
	 * @param int $page
	 * @param int $pages
	 * @return void
	 **/

	function display_plugins_table($plugins, $page = 1, $pages = 1) {
		$sem_plugins = sem_update_plugins::cache();
		
		if ( !$sem_plugins ) {
			echo '<p>'
				. __('Plugin lookup failed. Please refresh this page in a few minutes to try again.', 'version-checker')
				. '</p>' . "\n";
			return;
		}
		
		$to_install = array();
		$to_upgrade = array();
		
		$installed = get_plugins();
		$response = get_transient('update_plugins');
		$response = is_object($response) ? (array) $response->response : array();
		
		foreach ( array_keys($sem_plugins) as $slug ) {
			$file = "$slug/$slug.php";
			if ( !isset($installed[$file]) && $sem_plugins[$slug]->download_link )
				$to_install[] = $slug;
			elseif ( version_compare($response[$file]->new_version, $current[$file]['Version'], '>') && $response[$file]->package )
				$to_upgrade[] = $slug;
		}
		
		$type = isset($_REQUEST['type']) ? stripslashes( $_REQUEST['type'] ) : '';
		$term = isset($_REQUEST['s']) ? stripslashes( $_REQUEST['s'] ) : '';
	
		$plugins_allowedtags = array('a' => array('href' => array(),'title' => array(), 'target' => array()),
									'abbr' => array('title' => array()),'acronym' => array('title' => array()),
									'code' => array(), 'pre' => array(), 'em' => array(),'strong' => array(),
									'ul' => array(), 'ol' => array(), 'li' => array(), 'p' => array(), 'br' => array());

?>
	<div class="tablenav">
		<div class="alignleft actions">
		<?php
		if ( $to_install ) {
			echo '<form method="post" action="" style="display: inline;">' . "\n"
				. '<input type="submit" class="button" value="' . esc_attr(sprintf(__('Mass Install (%s)', 'version-checker'), count($to_install))) . '" />'
				. '<input type="hidden" name="action" value="mass-install" />' . "\n";
			wp_nonce_field('mass-install');
			echo '</form>' . "\n";
		}
		if ( $to_upgrade ) {
			echo '<form method="post" action="" style="display: inline;">' . "\n"
				. '<input type="submit" class="button" value="' . esc_attr(sprintf(__('Mass Upgrade (%s)', 'version-checker'), count($to_upgrade))) . '" />'
				. '<input type="hidden" name="action" value="mass-upgrade" />' . "\n";
			wp_nonce_field('mass-upgrade');
			echo '</form>' . "\n";
		}
		?>
		</div>
		<?php
			$url = esc_url($_SERVER['REQUEST_URI']);
			if ( ! empty($term) )
				$url = add_query_arg('s', $term, $url);
			if ( ! empty($type) )
				$url = add_query_arg('type', $type, $url);

			$page_links = paginate_links( array(
				'base' => add_query_arg('paged', '%#%', $url),
				'format' => '',
				'prev_text' => __('&laquo;', 'version-checker'),
				'next_text' => __('&raquo;', 'version-checker'),
				'total' => $totalpages,
				'current' => $page
			));

			if ( $page_links )
				echo "\t\t<div class='tablenav-pages'>$page_links</div>";
?>
		<br class="clear" />
	</div>
	<table class="widefat" id="install-plugins" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" class="name"><?php _e('Name', 'version-checker'); ?></th>
				<th scope="col" class="num"><?php _e('Version', 'version-checker'); ?></th>
				<th scope="col" class="desc"><?php _e('Description', 'version-checker'); ?></th>
				<th scope="col" class="action-links"><?php _e('Actions', 'version-checker'); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<th scope="col" class="name"><?php _e('Name', 'version-checker'); ?></th>
				<th scope="col" class="num"><?php _e('Version', 'version-checker'); ?></th>
				<th scope="col" class="desc"><?php _e('Description', 'version-checker'); ?></th>
				<th scope="col" class="action-links"><?php _e('Actions', 'version-checker'); ?></th>
			</tr>
		</tfoot>

		<tbody class="plugins">
		<?php
			if( empty($plugins) )
				echo '<tr><td colspan="5">', __('No plugins match your request.', 'version-checker'), '</td></tr>';

			foreach( (array) $plugins as $plugin ){
				if ( is_object($plugin) )
					$plugin = (array) $plugin;
				
				$title = wp_kses($plugin['name'], $plugins_allowedtags);
				//Limit description to 400char, and remove any HTML.
				$description = strip_tags($plugin['description']);
				if ( strlen($description) > 400 )
					$description = mb_substr($description, 0, 400) . '&#8230;';
				//remove any trailing entities
				$description = preg_replace('/&[^;\s]{0,6}$/', '', $description);
				//strip leading/trailing & multiple consecutive lines
				$description = trim($description);
				$description = preg_replace("|(\r?\n)+|", "\n", $description);
				//\n => <br>
				$description = nl2br($description);
				$version = wp_kses($plugin['version'], $plugins_allowedtags);

				$name = strip_tags($title . ' ' . $version);

				$author = $plugin['author'];
				if( ! empty($plugin['author']) )
					$author = ' <cite>' . sprintf( __('By %s', 'version-checker'), $author ) . '.</cite>';

				$author = wp_kses($author, $plugins_allowedtags);

				if( isset($plugin['homepage']) )
					$title = '<a target="_blank" href="' . esc_attr($plugin['homepage']) . '">' . $title . '</a>';

				$action_links = array();
				if ( in_array($plugin['slug'], $to_install) ) {
					$action_links[] = '<a href="' . admin_url('plugin-install.php?tab=plugin-information&amp;plugin=' . $plugin['slug'] .
										'&amp;TB_iframe=true&amp;width=600&amp;height=550') . '" class="thickbox onclick" title="' .
										esc_attr($name) . '">' . __('Install', 'version-checker') . '</a>';
				} elseif ( in_array($plugin['slug'], $to_upgrade) ) {
					$action_links[] = '<a href="' . admin_url('plugin-install.php?tab=plugin-information&amp;plugin=' . $plugin['slug'] .
										'&amp;TB_iframe=true&amp;width=600&amp;height=550') . '" class="thickbox onclick" title="' .
										esc_attr($name) . '">' . __('Upgrade', 'version-checker') . '</a>';
				} elseif ( !$sem_plugins[$plugin['slug']]->download_link ) {
					$action_links[] = '<strong><a href="http://oldbackend.semiologic.com/order.php">'
						. __('Order', 'version-checker')
						. '</a></strong>';
				}
			?>
			<tr>
				<td class="name"><?php echo $title; ?></td>
				<td class="vers"><?php echo $version; ?></td>
				<td class="desc"><?php echo $description, $author; ?></td>
				<td class="action-links"><?php if ( !empty($action_links) )	echo implode(' | ', $action_links); ?></td>
			</tr>
			<?php
			}
			?>
		</tbody>
	</table>

	<div class="tablenav">
		<?php if ( $page_links )
				echo "\t\t<div class='tablenav-pages'>$page_links</div>"; ?>
		<br class="clear" />
	</div>

<?php
	} # display_plugins_table()
	
	
	/**
	 * mass_install()
	 *
	 * @param array $todo
	 * @return void
	 **/

	function mass_install($plugins) {
		include_once dirname(__FILE__) . '/upgrader.php';
		
		$url = 'tools.php?page=sem-upgrader&amp;action=' . urlencode($_REQUEST['action']);
		$title = __('Install Plugins', 'version-checker');
		$nonce = 'mass-install';
		$upgrader = new sem_upgrader( new sem_installer_skin( compact('title', 'nonce', 'url', 'plugin') ) );
		$upgrader->bulk_install($plugins);
	} # mass_install()
	
	
	/**
	 * mass_upgrade()
	 *
	 * @param array $todo
	 * @return void
	 **/

	function mass_upgrade($plugins) {
		include_once dirname(__FILE__) . '/upgrader.php';
		
		$url = 'tools.php?page=sem-upgrader&amp;action=' . urlencode($_REQUEST['action']);
		$title = __('Upgrade Plugins', 'version-checker');
		$nonce = 'mass-upgrade';
		$upgrader = new sem_upgrader( new sem_upgrader_skin( compact('title', 'nonce', 'url', 'plugin') ) );
		$upgrader->bulk_upgrade($plugins);
	} # mass_upgrade()
	
	
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
		
		$response = sem_update_plugins::cache();
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
		$plugins = sem_update_plugins::cache();
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
	 * cache()
	 *
	 * @param string $type
	 * @return array $plugins
	 **/

	function cache() {
		$response = get_transient('sem_query_plugins');
		if ( $response !== false )
			return $response;
		
		global $wp_version;
		$sem_api_key = get_option('sem_api_key');
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/info/0.1/plugins/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/info/plugins/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/info/trunk/plugins/" . $sem_api_key;
		}
		
		$body = array(
			'action' => 'query',
			);
		
		$options = array(
			'timeout' => 15,
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
			$response = sem_update_plugins::parse($response);
			set_transient('sem_query_plugins', $response, 7200);
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
				$v = sem_update_plugins::parse($v);
				if ( $v && is_object($v) && $v->slug )
					$res[$v->slug] = $v;
			}
			ksort($res);
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