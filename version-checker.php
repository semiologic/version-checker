<?php
/*
Plugin Name: Version Checker
Plugin URI: http://www.semiologic.com/software/version-checker/
Description: Allows to update plugins, themes, and Semiologic Pro using packages from semiologic.com
Version: 2.0 RC6
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: version-checker
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('version-checker', false, dirname(plugin_basename(__FILE__)) . '/lang');

if ( !defined('version_checker_debug') )
	define('version_checker_debug', false);

if ( !defined('FS_TIMEOUT') )
	define('FS_TIMEOUT', 900); // 15 minutes


/**
 * version_checker
 *
 * @package Version Checker
 **/

class version_checker {
	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		if ( !get_option('sem_api_key') ) {
			$hub = @ preg_match("|/usr/local/www/[^/]+/www/|", ABSPATH) && file_exists('/etc/semiologic') && is_readable('/etc/semiologic');
			if ( $hub && ( $api_key = trim(file_get_contents('/etc/semiologic')) ) && preg_match("/^[0-9a-f]{32}$/i", $api_key) )
				update_option('sem_api_key', $api_key);
		}
		remove_action('admin_notices', 'update_nag', 3);
		add_action('admin_notices', array('version_checker', 'update_nag'), 3);
		add_action('admin_notices', array('version_checker', 'extra_update_nag'), 4);
		add_action('settings_page_sem-api-key', array('version_checker', 'update_nag'), 9);
		add_filter('update_footer', array('version_checker', 'core_update_footer'), 20);
		add_filter('admin_footer_text', array('version_checker', 'admin_footer_text'), 20);
	} # init()
	
	
	/**
	 * update_nag()
	 *
	 * @return void
	 **/

	function update_nag() {
		global $pagenow, $page_hook;
		
		if ( !current_user_can('manage_options') )
			return version_checker::sem_news_feed();
		elseif ( 'update-core.php' == $pagenow || 'settings_page_sem-api-key' == $page_hook && current_filter() == 'admin_notices' )
			return;
		
		if ( 'settings_page_sem-api-key' == $page_hook && $_POST )
			wp_version_check();
		
		$cur = get_preferred_from_update_core();
		$sem_pro_version = get_option('sem_pro_version');
		if ( empty($cur->response) || empty($cur->package) || $cur->response != 'upgrade' ) {
			version_checker::sem_news_feed();
			if ( !get_option('sem_api_key') )
				version_checker::api_key_nag();
			return;
		}
		
		if ( version_checker::check('sem-pro') && !$sem_pro_version ) {
			$msg = sprintf(__('Browse <a href="%1$s">Tools / Upgrade</a> to install Semiologic Pro %2$s.', 'version-checker'),
				'update-core.php',
				$cur->current);
		} else {
			$msg = sprintf(__('<strong>WordPress %1$s is available</strong>! Please see the <a href="%2$s">release notes</a>, if any, before <a href="%3$s">upgrading your site</a>.', 'version-checker'),
				$cur->current,
				'http://www.semiologic.com',
				'update-core.php');
		}
		
		$msg = '<p>' . $msg . '</p>' . "\n";
		
		$extra = '';
		$hub = @ preg_match("|/usr/local/www/[^/]+/www/|", ABSPATH) && file_exists('/etc/semiologic');
		if ( $hub ) {
			$extra = '<p>'
				. sprintf(__('Note: It is faster and safer to upgrade using the <a href="%s">account management system</a> (AMS).'), 'https://ams.hub.org')
				. '</p>' . "\n";
		}
		
		echo '<div id="update-nag">' . "\n"
			. $msg
			. $extra
			. '</div>' . "\n";
	} # update_nag()
	
	
	/**
	 * extra_update_nag()
	 *
	 * @return void
	 **/

	function extra_update_nag() {
		global $pagenow, $page_hook;
		if ( in_array($pagenow, array('update.php', 'update-core.php')) || !current_user_can('manage_options') || get_option('sem_packages') != 'stable' )
			return;
		
		$plugins_todo = false;
		$active_plugins = get_option('active_plugins');
		
		if ( $active_plugins ) {
			$plugins_response = get_transient('update_plugins');
			if ( $plugins_response && !empty($plugins_response->response) ) {
				foreach ( $plugins_response->response as $plugin => $details ) {
					if ( $details->package && in_array($plugin, $active_plugins) )
						$plugins_todo = true;
						break;
				}
			}
		}
		
		$themes_todo = false;
		$template = get_option('template');
		$stylesheet = get_option('stylesheet');
		
		if ( $template && $stylesheet ) {
			$themes_response = get_transient('update_themes');
			if ( $themes_response && !empty($themes_response->response) ) {
				foreach ( array('template', 'stylesheet') as $theme ) {
					if ( !empty($themes_response->response[$$theme]) ) {
						$themes_todo = true;
						break;
					}
				}
			}
		}
		
		if ( $plugins_todo && $pagenow == 'plugins.php' ) {
			$plugins_todo = false;
			$plugins = get_plugins();
			
			foreach ( array_keys($plugins_response->response) as $plugin ) {
				if ( $plugins[$plugin]['Version'] == $plugins_response->response[$plugin]->new_version ) {
					delete_transient('update_plugins');
					delete_transient('sem_update_plugins');
					break;
				}
			}
		}
		
		if ( $themes_todo && $pagenow == 'themes.php' ) {
			$themes_todo = false;
			$themes = get_themes();
			
			foreach ( $themes as $details ) {
				if ( $details['Stylesheet'] == $$theme ) {
					if ( $themes_response->response[$$theme]['new_version'] == $details['Version'] )
					delete_transient('update_themes');
					delete_transient('sem_update_themes');
					break;
				}
			}
		}
		
		if ( !$plugins_todo && !$themes_todo )
			return;
		
		echo '<div id="extra_update_nag">' . "\n";
		
		if ( $plugins_todo ) {
			echo '<p>'
				. sprintf(__('<strong>A new version is available for one or more of your plugins</strong>. <a href="%s">Please update now!</a>', 'version-checker'), 'plugins.php?plugin_status=upgrade')
				. '</p>' . "\n";
		} elseif ( $themes_todo ) {
			echo '<p>'
				. sprintf(__('<strong>A new version is available for your theme</strong>. <a href="%s">Please update now!</a>', 'version-checker'), 'themes.php')
				. '</p>' . "\n";
		}
		
		echo '</div>' . "\n";
	} # extra_update_nag()
	
	
	/**
	 * api_key_nag()
	 *
	 * @return void
	 **/

	function api_key_nag() {
		if ( current_filter() == 'settings_page_sem-api-key' )
			return;
		
		echo '<div class="error">' . "\n"
			. '<p>'
			. sprintf(__('The Version Checker plugin is almost ready. Please enter your <a href="%s">Semiologic API key</a> to receive update notifications for packages hosted on semiologic.com.', 'version-checker'), 'options-general.php?page=sem-api-key')
			. '</p>' . "\n"
			. '</div>';
	} # api_key_nag()
	
	
	/**
	 * sem_news_css()
	 *
	 * @return void
	 **/

	function sem_news_css() {
		$pref = version_checker::get_news_pref();
		
		if ( $pref == 'false' )
			return;
		
		$position = ( 'rtl' == get_bloginfo( 'text_direction' ) ) ? 'left' : 'right';
		
		echo <<<EOS
<style type="text/css">
#sem_news_feed {
	position: absolute;
	top: 4.5em;
	margin: 0;
	padding: 0;
	$position: 175px;
	width: 320px;
	font-size: 11px;
}

#dolly {
	display: none;
}

#extra_update_nag {
	margin-top: 32px;
	line-height: 29px;
	font-size: 12px;
	border-width: 1px 0;
	border-style: solid none;
	text-align: center;
	background-color: #fffeeb;
	border-color: #ccc;
	color: #555;
}
</style>
EOS;
	} # sem_news_css()
	
	
	/**
	 * sem_news_feed()
	 *
	 * @return bool false
	 **/

	function sem_news_feed() {
		global $upgrading;
		
		if ( !empty($upgrading) || version_checker::get_news_pref() == 'false' )
			return;
		
		add_filter('wp_feed_cache_transient_lifetime', array('version_checker', 'sem_news_timeout'));
		$feed = fetch_feed('http://www.semiologic.com/news/wordpress/feed/');
		remove_filter('wp_feed_cache_transient_lifetime', array('version_checker', 'sem_news_timeout'));
		
		if ( is_wp_error($feed) || !$feed->get_item_quantity() )
			return;
		
		$dev_news_url = strip_tags($feed->get_permalink());
		foreach ( $feed->get_items(0,1) as $item ) {
			$title = $item->get_title();
			$link = $item->get_permalink();
			$content = $item->get_content();
			$content = strip_tags($content);
			$excerpt_length = 55;
			$words = explode(' ', $content, $excerpt_length + 1);
			if (count($words) > $excerpt_length) {
				array_pop($words);
				array_push($words, '[...]');
				$content = implode(' ', $words);
			}
			echo '<div id="sem_news_feed">' . "\n"
				. sprintf(__('<a href="%1$s" title="Semiologic Development News">Dev News</a>: <a href="%2$s" title="%3$s">%4$s</a>', 'version-checker'),  clean_url($dev_news_url), clean_url($link), $content, $title)
				. '</div>' . "\n";
			break;
		}
		
		return;
	} # sem_news_feed()
	
	
	/**
	 * sem_news_timeout()
	 *
	 * @return void
	 **/

	function sem_news_timeout($timeout) {
		return 3600;
	} # sem_news_timeout()
	
	
	/**
	 * get_news_pref()
	 *
	 * @param int $user_id
	 * @return string true|false
	 **/

	function get_news_pref($user_id = null) {
		if ( !$user_id ) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
		}
		
		$pref = get_usermeta($user_id, 'sem_news');
		
		if ( $pref === '' ) {
			$user = new WP_User($user_id);
			$pref = $user->has_cap('edit_posts') || $user->has_cap('edit_pages')
				? 'true'
				: 'false';
			update_usermeta($user_id, 'sem_news', $pref);
		}
		
		return $pref;
	} # get_news_pref()
	
	
	/**
	 * edit_news_pref()
	 *
	 * @param object $user
	 * @return void
	 **/

	function edit_news_pref($user) {
		$pref = version_checker::get_news_pref($user->ID);
		
		echo '<h3>'
			. __('Semiologic Development News', 'version-checker')
			. '</h3>' . "\n";
		
		echo '<table class="form-table">' . "\n"
			. '<tr>'
			. '<th scope="row">'
			. __('Semiologic Development News', 'version-checker')
			. '</th>' . "\n"
			. '<td>'
			. '<label>'
			. '<input type="checkbox" name="sem_news"'
				. checked($pref, 'true', false)
				. ' />'
			. '&nbsp;'
			. __('Keep me updated with Semiologic Development News when browsing the admin area.', 'version-checker')
			. '</label>'
			. '</td>'
			. '</tr>' . "\n"
			. '</table>' . "\n";
	} # edit_news_pref()
	
	
	/**
	 * save_news_pref()
	 *
	 * @param int $user_ID
	 * @return void
	 **/

	function save_news_pref($user_ID) {
		if ( !$_POST )
			return;
		
		update_usermeta($user_ID, 'sem_news', isset($_POST['sem_news']) ? 'true' : 'false');
	} # save_news_pref()
	
	
	/**
	 * core_update_footer()
	 *
	 * @param string $msg
	 * @return string $msg
	 **/

	function core_update_footer($msg = '') {
		if ( !current_user_can('manage_options') || get_option('sem_pro_version') )
			return $msg;
		
		$update_core = get_transient('update_core');
		if ( empty($update_core->response) || empty($update_core->response->package) )
			return $msg;
		
		$cur = get_preferred_from_update_core();
		if ( !empty($cur->response) && $cur->response == 'upgrade' )
			return sprintf('<strong>' . __( '<a href="%1$s">Get Semiologic Pro Version %2$s</a>', 'version-checker') . '</strong>', 'update-core.php', $cur->current);
		
		return $msg;
	} # core_update_footer()
	
	
	/**
	 * admin_footer_text()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function admin_footer_text($text = '') {
		if ( get_option('sem_pro_version') && version_checker::get_news_pref() != 'false' ) {
			$text .= ' | <a href="http://www.semiologic.com">'
				. __('Semiologic', 'version-checker')
				. '</a>';
		}
		
		return $text;
	} # admin_footer_text()
	
	
	/**
	 * http_request_args()
	 *
	 * @param array $args
	 * @param string $url
	 * @return array $args
	 **/

	function http_request_args($args, $url) {
		#dump($url);
		
		if ( !preg_match("/^https?:\/\/([^\/]+)\.semiologic\.com\/media\/([^\/]+)/i", $url, $match) )
			return $args;
		
		if ( $match[1] != 'members' && $match[2] != 'members' )
			return $args;
		
		$cookies = version_checker::get_auth();
		
		$args['cookies'] = array_merge((array) $args['cookies'], $cookies);
		if ( preg_match("/^https?:\/\/members\.semiologic\.com\/media\/sem-pro\//i", $url) )
			$args['timeout'] = 600;
		else
			$args['timeout'] = 300;
		
		version_checker::force_flush();
		
		return $args;
	} # http_request_args()
	
	
	/**
	 * get_auth()
	 *
	 * @return array $cookies
	 **/

	function get_auth() {
		$sem_api_key = get_option('sem_api_key');
		
		if ( !$sem_api_key )
			wp_die(__('The Url you\'ve tried to access is restricted. Please enter your Semiologic API key.', 'version_checker'));
		
		$cookies = get_transient('sem_cookies');
		
		if ( $cookies !== false )
			return $cookies;
		
		global $wp_version;
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/auth/0.1/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/auth/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/auth/trunk/" . $sem_api_key;
		}
		
		$options = array(
			'timeout' => 3,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = serialize(array($url, $options));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) ) {
			wp_die($raw_response);
		} elseif ( 200 != $raw_response['response']['code'] ) {
			wp_die(__('An error occurred while trying to authenticate you on Semiologic.com in order to access a members-only package. More often than not, this will be due to a network problem (e.g., semiologic.com is very busy) or an incorrect API key.', 'version_checker'));
		} else {
			$cookies = $raw_response['cookies'];
			set_transient('sem_cookies', $cookies, 1800); // half hour
			return $cookies;
		}
	} # get_auth()
	
	
	/**
	 * get_memberships()
	 *
	 * @param bool $force
	 * @return array $memberships, false on failure
	 **/

	function get_memberships() {
		$sem_api_key = get_option('sem_api_key');
		
		if ( !$sem_api_key )
			return array();
		
		$obj = get_transient('sem_memberships');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->response = array();
		}
		
		$current_filter = current_filter();
		
		if ( $current_filter == 'load-settings_page_sem-api-key' && is_object($obj->response['sem-pro']) && $obj->response['sem-pro']->expires ) {
			# user might decide to place an order here
			if ( strtotime($obj->response['sem-pro']->expires) <= time() + 2678400 ) {
				$timeout = 120;
			} else {
				$timeout = 3600;
			}
		} elseif ( in_array($current_filter, array('load-plugins.php', 'load-update-core.php', 'load-settings_page_sem-api-key')) ) {
			$timeout = 3600;
		} else {
			$timeout = 43200;
		}
		
		if ( $obj->last_checked >= time() - $timeout )
			return $obj->response;
		
		global $wpdb;
		global $wp_version;
		
		$obj->last_checked = time();
		set_transient('sem_memberships', $obj);
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/memberships/0.2/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/memberships/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/memberships/trunk/" . $sem_api_key;
		}
		
		$body = array(
			'php_version' => phpversion(),
			'mysql_version' => $wpdb->db_version(),
			'locale' => apply_filters('core_version_check_locale', get_locale()),
			);
		
		$options = array(
			'timeout' => 3,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = serialize(array($url, $options));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) )
			set_transient('sem_api_error', $raw_response->get_error_messages());
		else
			delete_transient('sem_api_error');
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) { // keep old response in case of error
			if ( $obj->response != $response ) {
				delete_transient('sem_update_core');
				delete_transient('sem_update_themes');
				delete_transient('sem_update_plugins');
			}
			
			$obj->response = $response;
			set_transient('sem_memberships', $obj);
		}
		
		return $obj->response;
	} # get_memberships()
	
	
	/**
	 * get_core()
	 *
	 * @param string $checked
	 * @return array $response
	 **/

	function get_core($checked = null) {
		$sem_api_key = get_option('sem_api_key');
		
		$sem_pro_version = get_option('sem_pro_version');
		
		if ( !$sem_api_key || $sem_pro_version || !version_checker::check('sem-pro') )
			return array();
		
		$obj = get_transient('sem_update_core');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->checked =  array('sem-pro' => $sem_pro_version);
			$obj->response = null;
		}
		
		if ( current_filter() == 'load-update-core.php' ) {
			$timeout = 3600;
		} else {
			$timeout = 43200;
		}
		
		if ( is_array($checked) && $checked != $obj->checked )
			$timeout = 0;
		
		if ( $obj->last_checked >= time() - $timeout )
			return $obj->response;
		
		global $wp_version;
		
		$obj->last_checked = time();
		set_transient('sem_update_core', $obj);
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/version/0.2/core/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/version/core/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/version/trunk/core/" . $sem_api_key;
		}
		
		$check = array('sem-pro' => $sem_pro_version);
		
		$obj->checked = $check;
		set_transient('sem_update_core', $obj);
		
		$body = array(
			'check' => $check,
			'packages' => get_option('sem_packages'),
			'locale' => apply_filters('core_version_check_locale', get_locale()),
			);
		
		$options = array(
			'timeout' => 3,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = serialize(array($url, $options));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) )
			set_transient('sem_api_error', $raw_response->get_error_messages());
		else
			delete_transient('sem_api_error');
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) { // keep old response in case of error
			$obj->response = $response;
			set_transient('sem_update_core', $obj);
		}
		
		return $obj->response;
	} # get_core()
	
	
	/**
	 * update_core()
	 *
	 * @param object $ops
	 * @return object $ops
	 **/

	function update_core($ops) {
		$sem_pro_version = get_option('sem_pro_version');
		
		if ( $sem_pro_version )
			return $ops;
		
		if ( !is_object($ops) )
			$ops = new stdClass;
		
		if ( !is_array($ops->checked) )
			$ops->checked = array('sem-pro' => $sem_pro_version);
		
		if ( !is_array($ops->updates) )
			$ops->response = array();
		
		$ops->response = version_checker::get_core($ops->checked);
		
		if ( is_object($ops->response) && !empty($ops->response->package) ) {
			$ops->updates = array($ops->response);
		}
		
		return $ops;
	} # update_core()
	
	
	/**
	 * get_themes()
	 *
	 * @param array $checked
	 * @return array $response
	 **/

	function get_themes($checked = null) {
		$sem_api_key = get_option('sem_api_key');
		
		if ( !$sem_api_key )
			return array();
		
		$obj = get_transient('sem_update_themes');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->checked = array();
			$obj->response = array();
		}
		
		if ( current_filter() == 'load-themes.php' ) {
			$timeout = 3600;
		} else {
			$timeout = 43200;
		}
		
		if ( is_array($checked) && $checked && $obj->checked && $obj->checked != $checked ) {
			delete_transient('sem_update_themes');
			delete_transient('update_themes');
			return false;
		}
		
		if ( $obj->last_checked >= time() - $timeout )
			return $obj->response;
		
		global $wp_version;
		
		if ( !function_exists('get_themes') )
			require_once ABSPATH . 'wp-includes/theme.php';
		
		$obj->last_checked = time();
		set_transient('sem_update_themes', $obj);
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/version/0.2/themes/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/version/themes/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/version/trunk/themes/" . $sem_api_key;
		}
		
		$to_check = get_themes();
		$check = array();
		
		foreach ( $to_check as $themes )
			$check[$themes['Stylesheet']] = $themes['Version'];
		
		$obj->checked = $check;
		set_transient('sem_update_themes', $obj);
		
		$body = array(
			'check' => $check,
			'packages' => get_option('sem_packages'),
			'locale' => apply_filters('core_version_check_locale', get_locale()),
			);
		
		$options = array(
			'timeout' => 3,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = serialize(array($url, $options));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) )
			set_transient('sem_api_error', $raw_response->get_error_messages());
		else
			delete_transient('sem_api_error');
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) { // keep old response in case of error
			foreach ( $response as $key => $package )
				$response[$key] = (array) $package;
			$obj->response = $response;
			set_transient('sem_update_themes', $obj);
		}
		
		return $obj->response;
	} # get_themes()
	
	
	/**
	 * update_themes()
	 *
	 * @param object $ops
	 * @return object $ops
	 **/

	function update_themes($ops) {
		if ( !is_object($ops) )
			$ops = new stdClass;
		
		if ( !is_array($ops->checked) )
			$ops->checked = array();
		
		if ( !is_array($ops->response) )
			$ops->response = array();
		
		foreach ( $ops->checked as $plugin => $version ) {
			if ( isset($ops->response[$plugin]) && strpos($version, 'fork') !== false )
				unset($ops->response[$plugin]);
		}
		
		$extra = version_checker::get_themes($ops->checked);
		
		if ( $extra === false )
			return false;
		
		$ops->response = array_merge($ops->response, $extra);
		
		if ( isset($ops->response['semiologic']) )
			unset($ops->response['semiologic']['package']);
		
		return $ops;
	} # update_themes()
	
	
	/**
	 * get_plugins()
	 *
	 * @param array $checked
	 * @return array $response
	 **/

	function get_plugins($checked = null) {
		$sem_api_key = get_option('sem_api_key');
		
		if ( !$sem_api_key )
			return array();
		
		$obj = get_transient('sem_update_plugins');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->checked = array();
			$obj->response = array();
		}
		
		if ( current_filter() == 'load-plugins.php' ) {
			$timeout = 3600;
		} else {
			$timeout = 43200;
		}
		
		if ( is_array($checked) && $checked && $obj->checked && $obj->checked != $checked ) {
			delete_transient('sem_update_plugins');
			delete_transient('update_plugins');
			return false;
		}
		
		if ( $obj->last_checked >= time() - $timeout )
			return $obj->response;
		
		global $wp_version;
		
		if ( !function_exists('get_plugins') )
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		
		$obj->last_checked = time();
		set_transient('sem_update_plugins', $obj);
		
		if ( !version_checker_debug ) {
			$url = "https://api.semiologic.com/version/0.2/plugins/" . $sem_api_key;
		} elseif ( version_checker_debug == 'localhost' ) {
			$url = "http://localhost/~denis/api/version/plugins/" . $sem_api_key;
		} else {
			$url = "https://api.semiologic.com/version/trunk/plugins/" . $sem_api_key;
		}
		
		$to_check = get_plugins();
		$check = array();
		
		foreach ( $to_check as $file => $plugin )
			$check[$file] = $plugin['Version'];
		
		$obj->checked = $check;
		set_transient('sem_update_plugins', $obj);
		
		$body = array(
			'check' => $check,
			'packages' => get_option('sem_packages'),
			'locale' => apply_filters('core_version_check_locale', get_locale()),
			);
		
		$options = array(
			'timeout' => 3,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = serialize(array($url, $options));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) )
			set_transient('sem_api_error', $raw_response->get_error_messages());
		else
			delete_transient('sem_api_error');
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) { // keep old response in case of error
			$obj->response = $response;
			set_transient('sem_update_plugins', $obj);
		}
		
		return $obj->response;
	} # get_plugins()
	
	
	/**
	 * update_plugins()
	 *
	 * @param object $ops
	 * @return object $ops
	 **/

	function update_plugins($ops) {
		if ( !is_object($ops) )
			$ops = new stdClass;
		
		if ( !is_array($ops->checked) )
			$ops->checked = array();
		
		if ( !is_array($ops->response) )
			$ops->response = array();
		
		foreach ( $ops->checked as $plugin => $version ) {
			if ( isset($ops->response[$plugin]) && strpos($version, 'fork') !== false )
				unset($ops->response[$plugin]);
		}
		
		$extra = version_checker::get_plugins($ops->checked);
		
		if ( $extra === false )
			return false;
		
		$ops->response = array_merge($ops->response, $extra);
		
		return $ops;
	} # update_plugins()
	
	
	/**
	 * check()
	 *
	 * @param string $membership
	 * @return bool $running
	 **/

	function check($membership) {
		$memberships = version_checker::get_memberships();
		
		if ( !isset($memberships[$membership]['expires']) )
			return false;
		elseif ( !$memberships[$membership]['expires'] )
			return true;
		else
			return time() <= strtotime($memberships[$membership]['expires']);
	} # check()
	
	
	/**
	 * admin_menu()
	 *
	 * @return void
	 **/

	function admin_menu() {
		add_options_page(
			__('Semiologic API Key', 'version-checker'),
			__('Semiologic API Key', 'version-checker'),
			'manage_options',
			'sem-api-key',
			array('sem_api_key', 'edit_options')
			);
	} # admin_menu()
	
	
	/**
	 * add_warning()
	 *
	 * @return void
	 **/

	function add_warning() {
		echo '<div class="error">'
			. '<p>'
			. __('The Version Checker plugin requires WP 2.8 or later.', 'version-checker')
			. '</p>'
			. '</div>' . "\n";
	} # add_warning()
	
	
	/**
	 * update_feedback()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function update_feedback($in = null) {
		global $wp_filesystem;
		
		if ( !$_POST || !is_object($wp_filesystem) )
			return $in;
		
		version_checker::reconnect_ftp();
		version_checker::force_flush();
		
		return $in;
	} # update_feedback()
	
	
	/**
	 * force_flush()
	 *
	 * @return void
	 **/

	function force_flush() {
		echo "\n\n<!-- Deal with browser-related buffering by sending some incompressible strings -->\n\n";
		
		for ( $i = 0; $i < 5; $i++ )
			echo "<!-- abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono -->\n\n";
		
		while ( ob_get_level() )
			ob_end_flush();
		
		@ob_flush();
		@flush();
		@set_time_limit(FS_TIMEOUT);
	} # force_flush()
	
	
	/**
	 * reconnect_ftp()
	 *
	 * @return void
	 **/

	function reconnect_ftp() {
		global $wp_filesystem;
		
		if ( !$wp_filesystem || !is_object($wp_filesystem) )
			return;
		
		if ( strpos($wp_filesystem->method, 'ftp') !== false ) {
			if ( $wp_filesystem->link ) {
				@ftp_close($wp_filesystem->link);
				$wp_filesystem->connect();
				if ( @ftp_get_option($wp_filesystem->link, FTP_TIMEOUT_SEC) < FS_TIMEOUT )
					@ftp_set_option($wp_filesystem->link, FTP_TIMEOUT_SEC, FS_TIMEOUT);
			} elseif ( $wp_filesystem->ftp && $wp_filesystem->ftp->_connected ) {
				$wp_filesystem->ftp->quit();
				$wp_filesystem->connect();
				if ( $wp_filesystem->ftp->_timeout < FS_TIMEOUT )
					$wp_filesystem->ftp->SetTimeout(FS_TIMEOUT);
			}
		}
	} # reconnect_ftp()
	
	
	/**
	 * maybe_flush()
	 *
	 * @param mixed $response
	 * @param mixed $call
	 * @return mixed $response
	 **/

	function maybe_flush($response = null, $call = null) {
		if ( $call != 'response' )
			return $response;
		
		version_checker::force_flush();
		version_checker::reconnect_ftp();
		
		return $response;
	} # maybe_flush()
	
	
	/**
	 * option_ftp_credentials()
	 *
	 * @return void
	 **/

	function option_ftp_credentials($in) {
		global $wp_filesystem;
		
		if ( !is_admin() || !$_POST || defined('FTP_BASE')
			|| !is_array($in) || empty($in['connection_type'])
			|| !$wp_filesystem || !is_object($wp_filesystem)
			|| strpos($in['connection_type'], 'ftp') === false
			|| !$wp_filesystem->link && !$wp_filesystem->ftp )
			return $in;
		
		$ftp_base = $wp_filesystem->abspath();
		
		if ( $ftp_base )
			define('FTP_BASE', $ftp_base);
		
		version_checker::force_flush();
		version_checker::reconnect_ftp();
		
		if ( class_exists('sem_update_core') && $wp_filesystem->abspath() ) {
			if ( $files = glob(WP_CONTENT_DIR . '/sem-pro*.zip') ) {
				foreach ( $files as $file ) {
					$dir = $wp_filesystem->find_folder(dirname($file));
					$file = $dir . basename($file);
					$wp_filesystem->delete($file);
				}
			}
			
			version_checker::reconnect_ftp();

			if ( $folders = glob(WP_CONTENT_DIR . '/upgrade/sem-pro*/') ) {
				foreach ( $folders as $folder ) {
					$folder = $wp_filesystem->find_folder($folder);
					show_message(sprintf(__('Cleaning up %1$s. Based on our testing, this step can readily take about 10 minutes without the slightest amount of feedback from WordPress. You can avoid it by deleting your %2$s folder using your FTP software before proceeding.', 'version-checker'), $folder, basename($folder)));
					version_checker::force_flush();
					$wp_filesystem->delete($folder, true);
					version_checker::reconnect_ftp();
				}
			}
			
			show_message(__('Starting upgrade... Again, this can take several minutes without any feedback from WordPress.', 'version-checker'));
			version_checker::force_flush();
			
			add_action('http_api_debug', array('version_checker', 'maybe_flush'), 100, 2);
		}
		
		return $in;
	} # option_ftp_credentials()
} # version_checker


function sem_api_key() {
	if ( !class_exists('sem_api_key') )
		include dirname(__FILE__) . '/sem-api-key.php';
}

add_action('load-settings_page_sem-api-key', 'sem_api_key');

function sem_update_core() {
	if ( function_exists('apache_setenv') )
		@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);

	if ( !class_exists('sem_update_core') )
		include dirname(__FILE__) . '/core.php';
}

add_action('load-update-core.php', 'sem_update_core');

function sem_update_plugins() {
	if ( function_exists('apache_setenv') )
		@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);

	if ( !class_exists('sem_update_plugins') )
		include dirname(__FILE__) . '/plugins.php';
}

add_action('load-plugin-install.php', 'sem_update_plugins');

function sem_update_themes() {
	if ( function_exists('apache_setenv') )
		@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);
}

add_action('load-theme-install.php', 'sem_update_themes');

add_option('sem_api_key', '');
add_option('sem_pro_version', '');
add_option('sem_packages', 'stable');

if ( !isset($sem_pro_version) )
	$sem_pro_version = '';

if ( $sem_pro_version && get_option('sem_pro_version') !== $sem_pro_version ) {
	update_option('sem_pro_version', $sem_pro_version);
	delete_transient('sem_update_core');
}

wp_cache_add_non_persistent_groups(array('sem_api'));

if ( is_admin() && function_exists('get_transient') ) {
	add_action('admin_menu', array('version_checker', 'admin_menu'));
	
	foreach ( array(
		'load-settings_page_sem-api-key',
		'load-update-core.php',
		'load-themes.php',
		'load-plugins.php',
		'wp_version_check',
		) as $hook )
		add_action($hook, array('version_checker', 'get_memberships'), 11);
	
	foreach ( array(
		'load-update-core.php',
		'wp_version_check',
		) as $hook )
		add_action($hook, array('version_checker', 'get_core'), 12);
	
	foreach ( array(
		'load-themes.php',
		'wp_update_themes',
		) as $hook )
		add_action($hook, array('version_checker', 'get_themes'), 12);
	
	foreach ( array(
		'load-plugins.php',
		'wp_update_plugins',
		) as $hook )
		add_action($hook, array('version_checker', 'get_plugins'), 12);
	
	add_filter('http_request_args', array('version_checker', 'http_request_args'), 10, 2);
	add_action('admin_init', array('version_checker', 'init'));
	
	add_action('admin_head', array('version_checker', 'sem_news_css'));
	add_action('edit_user_profile', array('version_checker', 'edit_news_pref'));
	add_action('show_user_profile', array('version_checker', 'edit_news_pref'));
	add_action('profile_update', array('version_checker', 'save_news_pref'));
	
	add_filter('update_feedback', array('version_checker', 'update_feedback'), 100);
	add_action('option_ftp_credentials', array('version_checker', 'option_ftp_credentials'));
} elseif ( is_admin() ) {
	add_action('admin_notices', array('version_checker', 'add_warning'));
}

add_filter('transient_update_core', array('version_checker', 'update_core'));
add_filter('transient_update_themes', array('version_checker', 'update_themes'));
add_filter('transient_update_plugins', array('version_checker', 'update_plugins'));
?>