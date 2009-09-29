<?php
/*
Plugin Name: Version Checker
Plugin URI: http://www.semiologic.com/software/version-checker/
Description: Allows to update plugins, themes, and Semiologic Pro using packages from semiologic.com
Version: 2.0 RC3
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

if ( !defined('sem_version_checker_debug') )
	define('sem_version_checker_debug', false);


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
		remove_action('admin_notices', 'update_nag', 3);
		add_action('admin_notices', array('version_checker', 'update_nag'), 3);
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
		
		if ( 'update-core.php' == $pagenow || !current_user_can('manage_options') )
			return version_checker::sem_news_feed();
		elseif ( 'settings_page_sem-api-key' == $page_hook && current_filter() == 'admin_notices' )
			return;
		
		if ( 'settings_page_sem-api-key' == $page_hook && $_POST )
			wp_version_check();
		
		$cur = get_preferred_from_update_core();
		
		if ( !isset($cur->response) || !isset($cur->package) || $cur->response != 'upgrade' || !current_user_can('manage_options') ) {
			if ( current_user_can('manage_options') ) {
				version_checker::sem_news_feed();
				if ( !get_option('sem_api_key') )
					version_checker::api_key_nag();
				return;
			} else {
				return;
			}
		}
		
		if ( version_checker::check('sem-pro') ) {
			if ( get_option('sem_pro_version') ) {
				$msg = sprintf(__('Semiologic Pro %1$s is available! <a href="%2$s">Please update now</a>.', 'version-checker'),
					$cur->current,
					'update-core.php');
			} else {
				$msg = sprintf(__('Browse <a href="%1$s">Tools / Upgrade</a> to install Semiologic Pro %2$s.', 'version-checker'),
					'update-core.php',
					$cur->current);
			}
		} else {
			$msg = sprintf(__('WordPress %1$s is available! Be wary of not <a href="%2$s">upgrading</a> before checking your plugin and theme compatibility.', 'version-checker'),
				$cur->current,
				'update-core.php');
		}
		
		echo '<div id="update-nag">' . "\n"
			. $msg
			. '</div>' . "\n";
	} # update_nag()
	
	
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
			. sprintf(__('Version Checker is almost ready. Please enter your <a href="%s">Semiologic API key</a> to receive update notifications.', 'version-checker'), 'options-general.php?page=sem-api-key')
			. '</p>' . "\n"
			. '</div>';
	} # api_key_nag()
	
	
	/**
	 * sem_news_css()
	 *
	 * @return void
	 **/

	function sem_news_css() {
		$pref = get_user_option('sem_news');
		
		if ( $pref !== false && $pref == 'false' )
			return;
		
		$position = ( 'rtl' == get_bloginfo( 'text_direction' ) ) ? 'left' : 'right';
		
		echo <<<EOS
<style type="text/css">
#sem_news_feed {
	position: absolute;
	top: 4.5em;
	margin: 0;
	padding: 0;
	$position: 155px;
	width: 300px;
	font-size: 11px;
}

#dolly {
	display: none;
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
		$pref = get_user_option('sem_news');
		
		if ( !empty($upgrading) || $pref !== false && $pref == 'false' )
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
		
		return false;
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
	 * edit_sem_news_prefs()
	 *
	 * @return void
	 **/

	function edit_sem_news_prefs() {
		echo '<h3>'
			. __('Semiologic Development News', 'version-checker')
			. '</h3>' . "\n";
		
		$pref = get_user_option('sem_news');
		
		echo '<table class="form-table">' . "\n"
			. '<tr>'
			. '<th scope="row">'
			. __('Semiologic Development News', 'version-checker')
			. '</th>' . "\n"
			. '<td>'
			. '<label>'
			. '<input type="checkbox" name="sem_news"'
				. checked($pref === false || $pref == 'true', true, false)
				. ' />'
			. '&nbsp;'
			. __('Keep me updated with Semiologic Development News when browsing the admin area.', 'version-checker')
			. '</label>'
			. '</td>'
			. '</tr>' . "\n"
			. '</table>' . "\n";
	} # edit_sem_news_prefs()
	
	
	/**
	 * undocumented function
	 *
	 * @return void
	 **/

	function save_sem_news_prefs($user_ID) {
		if ( !$_POST )
			return;
		
		update_user_option($user_ID, 'sem_news', isset($_POST['sem_news']) ? 'true' : 'false', true);
	} # save_sem_news_prefs()
	
	
	/**
	 * core_update_footer()
	 *
	 * @param string $msg
	 * @return string $msg
	 **/

	function core_update_footer($msg = '') {
		global $wp_version;
		$update_core = get_transient('update_core');
		
		if ( empty($update_core->response) || empty($update_core->response->package) )
			return $msg;
		
		$sem_pro_version = get_option('sem_pro_version');
		
		if ( !current_user_can('manage_options') )
			return sprintf(__('<a href="%1$s">Semiologic Pro</a> Version %2$s', 'version-checker'), 'http://www.getsemiologic.com', $sem_pro_version);

		$cur = get_preferred_from_update_core();
		if ( ! isset( $cur->current ) )
			$cur->current = '';
		
		if ( ! isset( $cur->url ) )
			$cur->url = '';
		
		if ( ! isset( $cur->response ) )
			$cur->response = '';
		
		switch ( $cur->response ) {
		case 'development':
			return sprintf(__('You are using a development version of Semiologic Pro (%1$s). Cool! Please <a href="%2$s">stay updated</a>.', 'version-checker'), $sem_pro_version, 'update-core.php');
		
		case 'upgrade':
			if ( current_user_can('manage_options') ) {
				return sprintf('<strong>' . __( '<a href="%1$s">Get Semiologic Pro Version %2$s</a>', 'version-checker') . '</strong>', 'update-core.php', $cur->current);
			}

		case 'latest':
		default:
			return sprintf(__('Semiologic Pro Version %s', 'version-checker'), $sem_pro_version);
		}
	} # core_update_footer()
	
	
	/**
	 * admin_footer_text()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function admin_footer_text($text = '') {
		$sem_pro_version = get_option('sem_pro_version');
		$update_core = get_transient('update_core');
		
		if ( !$sem_pro_version || empty($update_core->response) || empty($update_core->response->package) )
			return $text;
		
		return '<span id="footer-thankyou">'
			. sprintf(__('Thank you for creating with <a href="%s">Semiologic Pro</a>.', 'version-checker'), 'http://www.getsemiologic.com')
			. '</span> | '
			. __('<a href="http://www.semiologic.com/resources/">Resources</a>', 'version-checker')
			. ' | '
			. __('<a href="http://forum.semiologic.com">Community</a>', 'version-checker');
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
		
		if ( !preg_match("/https?:\/\/([^\/]+).semiologic.com\/media\/([^\/]+)/i", $url, $match) )
			return $args;
		
		if ( $match[1] != 'members' && $match[2] != 'members' )
			return $args;
		
		$cookies = version_checker::get_auth();
		
		$args['cookies'] = array_merge((array) $args['cookies'], $cookies);
		$args['timeout'] = 180;
		
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
		
		if ( !sem_version_checker_debug ) {
			$url = "https://api.semiologic.com/auth/0.1/" . $sem_api_key;
		} elseif ( sem_version_checker_debug == 'localhost' ) {
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
		
		if ( !sem_version_checker_debug ) {
			$url = "https://api.semiologic.com/memberships/0.2/" . $sem_api_key;
		} elseif ( sem_version_checker_debug == 'localhost' ) {
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
		
		if ( !$sem_api_key || !version_checker::check('sem-pro') )
			return array();
		
		$obj = get_transient('sem_update_core');
		
		$sem_pro_version = get_option('sem_pro_version');
		
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
		
		if ( !sem_version_checker_debug ) {
			$url = "https://api.semiologic.com/version/0.2/core/" . $sem_api_key;
		} elseif ( sem_version_checker_debug == 'localhost' ) {
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
		if ( !is_object($ops) )
			$ops = new stdClass;
		
		if ( !is_array($ops->checked) )
			$ops->checked = array('sem-pro' => get_option('sem_pro_version'));
		
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
		
		if ( !sem_version_checker_debug ) {
			$url = "https://api.semiologic.com/version/0.2/themes/" . $sem_api_key;
		} elseif ( sem_version_checker_debug == 'localhost' ) {
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
		
		if ( !sem_version_checker_debug ) {
			$url = "https://api.semiologic.com/version/0.2/plugins/" . $sem_api_key;
		} elseif ( sem_version_checker_debug == 'localhost' ) {
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
} # version_checker


function sem_api_key() {
	if ( !class_exists('sem_api_key') )
		include dirname(__FILE__) . '/sem-api-key.php';
}

add_action('load-settings_page_sem-api-key', 'sem_api_key');

function sem_update_core() {
	if ( !class_exists('sem_update_core') )
		include dirname(__FILE__) . '/core.php';
}

add_action('load-update-core.php', 'sem_update_core');

function sem_update_plugins() {
	if ( !class_exists('sem_update_plugins') )
		include dirname(__FILE__) . '/plugins.php';
}

add_action('load-plugin-install.php', 'sem_update_plugins');

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
	add_action('edit_user_profile', array('version_checker', 'edit_sem_news_prefs'));
	add_action('show_user_profile', array('version_checker', 'edit_sem_news_prefs'));
	add_action('profile_update', array('version_checker', 'save_sem_news_prefs'));
} elseif ( is_admin() ) {
	add_action('admin_notices', array('version_checker', 'add_warning'));
}

add_filter('transient_update_core', array('version_checker', 'update_core'));
add_filter('transient_update_themes', array('version_checker', 'update_themes'));
add_filter('transient_update_plugins', array('version_checker', 'update_plugins'));
?>