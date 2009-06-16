<?php
/*
Plugin Name: Version Checker
Plugin URI: http://www.semiologic.com/software/version-checker/
Description: Allows to hook into WordPress' version checking API with in a distributed environment.
Version: 2.0 alpha
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: version-checker-info
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('version-checker', null, dirname(__FILE__) . '/lang');

if ( !defined('sem_version_checker_debug') )
	define('sem_version_checker_debug', false);


/**
 * version_checker
 *
 * @package Version Checker
 **/

add_option('sem_api_key', '');
add_option('sem_package', 'wp');

if ( is_admin() && function_exists('get_transient') ) {
	add_action('admin_menu', array('version_checker', 'admin_menu'));
	
	foreach ( array(
		'load-plugins.php',
		'load-update-core.php',
		'load-settings_page_sem-api-key',
		'wp_version_check',
		) as $hook )
		add_action($hook, array('version_checker', 'get_memberships'));
	
	foreach ( array(
		'load-update-core.php',
		'wp_version_check',
		) as $hook )
		add_action($hook, array('version_checker', 'get_core'));
	
	foreach ( array(
		'load-plugins.php',
		'wp_update_plugins',
		) as $hook )
		add_action($hook, array('version_checker', 'get_plugins'));
	
	add_filter('transient_update_plugins', array('version_checker', 'update_plugins'));
} elseif ( is_admin() ) {
	add_action('admin_notices', array('version_checker', 'add_warning'));
}

class version_checker {
	/**
	 * get_memberships()
	 *
	 * @param bool $force
	 * @return array $memberships, false on failure
	 **/

	function get_memberships() {
		$sem_api_key = get_option('sem_api_key');
		
		if ( !$sem_api_key )
			return false;
		
		$obj = get_transient('sem_memberships');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->response = false;
		}
		
		$current_filter = current_filter();
		
		if ( $current_filter == 'load-settings_page_sem-api-key' ) {
			$timeout = 120; // user might place an order here
		} elseif ( in_array($current_filter, array('load-plugins.php', 'load-update-core.php')) ) {
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
		} else {
			$url = "http://api.semiologic.com/memberships/trunk/" . $sem_api_key;
		}
		
		$body = array(
			'php_version' => phpversion(),
			'mysql_version' => $wpdb->db_version(),
			);
		
		$options = array(
			'timeout' => 3,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
		);
		
		$raw_response = wp_remote_post($url, $options);
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response ) {
			$obj->response = $response; // keep old response in case of error
			set_transient('sem_memberships', $obj);
		}
		
		return $obj->response;
	} # get_memberships()
	
	
	/**
	 * get_core()
	 *
	 * @return array $response
	 **/

	function get_core() {
	} # get_core()
	
	
	/**
	 * get_plugins()
	 *
	 * @return array $response
	 **/

	function get_plugins() {
		$sem_api_key = get_option('sem_api_key');
		
		if ( !$sem_api_key )
			return array();
		
		$obj = get_transient('sem_plugins');
		
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
		$timeout = 0;
		if ( $obj->last_checked >= time() - $timeout )
			return $obj->response;
		
		global $wpdb;
		global $wp_version;
		
		if ( !function_exists('get_plugins') )
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		
		$obj->last_checked = time();
		set_transient('sem_plugins', $obj);
		
		if ( !sem_version_checker_debug ) {
			$url = "https://api.semiologic.com/version/0.2/plugins/" . $sem_api_key;
		} else {
			$url = "http://api.semiologic.com/version/trunk/plugins/" . $sem_api_key;
		}
		
		$check = get_plugins();
		
		foreach ( $check as $key => $plugin )
			$check[$key] = $plugin['Version'];
		
		$body = array(
			'php_version' => phpversion(),
			'mysql_version' => $wpdb->db_version(),
			'active' => get_option('active_plugins'),
			'check' => $check,
			);
		
		$options = array(
			'timeout' => 3,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
		);
		
		$raw_response = wp_remote_post($url, $options);
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = array();
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response ) {
			$obj->checked = $check;
			$obj->response = $response; // keep old response in case of error
			set_transient('sem_plugins', $obj);
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
		static $new_ops;
		
		if ( isset($new_ops) )
			return $new_ops;
		
		if ( !is_object($ops) )
			$ops = new stdClass;
		
		if ( !is_array($ops->checked) )
			$ops->checked = array();
		
		if ( !is_array($ops->response) )
			$ops->response = array();
		
		foreach ( $ops->checked as $plugin => $version ) {
			if ( strpos($version, 'fork') !== false && isset($ops->response[$plugin]) )
				unset($ops->response[$plugin]);
		}
		
		$ops->response = array_merge($ops->response, version_checker::get_plugins());
		
		$new_ops = $ops;
		
		return $new_ops;
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

function sem_core() {
	if ( !class_exists('sem_core') )
		include dirname(__FILE__) . '/core.php';
}

add_action('load-update-core.php', 'sem_core');

function sem_plugins() {
	if ( !class_exists('sem_plugins') )
		include dirname(__FILE__) . '/plugins.php';
}

add_action('load-plugin-install.php', 'sem_plugins');
?>