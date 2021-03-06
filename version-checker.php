<?php
/*
Plugin Name: Version Checker
Plugin URI: http://www.semiologic.com/software/version-checker/
Description: Allows to update plugins, themes, and Semiologic Pro using packages from semiologic.com
Version: 2.11
Author: Denis de Bernardy & Mike Koepke
Author URI: https://www.semiologic.com
Text Domain: version-checker
Domain Path: /lang
License: Dual licensed under the MIT and GPLv2 licenses
*/

/*
Terms of use
------------

This software is copyright Denis de Bernardy & Mike Koepke, and is distributed under the terms of the MIT and GPLv2 licenses.
**/


if ( !defined('FS_TIMEOUT') )
	define('FS_TIMEOUT', 900); // 15 minutes

if ( !defined('sem_api_memberships') )
	define('sem_api_memberships', 'https://api.semiologic.com/memberships/0.2');

if ( !defined('sem_api_auth') )
	define('sem_api_auth', 'https://api.semiologic.com/auth/0.1');

if ( !defined('sem_api_info') )
	define('sem_api_info', 'https://api.semiologic.com/info/0.1');

if ( !defined('sem_api_version') )
	define('sem_api_version', 'https://api.semiologic.com/version/0.2');

if (!defined('STRICTER_PLUGIN_UPDATES'))
	define('STRICTER_PLUGIN_UPDATES', true);

if ( !defined('version_checker_debug') )
	define('version_checker_debug', false);

/**
 * version_checker
 *
 * @package Version Checker
 **/

class version_checker {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}


	/**
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @return  void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain(
			$domain,
			FALSE,
			dirname(plugin_basename(__FILE__)) . '/lang'
		);
	}

	/**
	 * Constructor.
	 *
	 *
	 */

    public function __construct() {
	    $this->plugin_url    = plugins_url( '/', __FILE__ );
        $this->plugin_path   = plugin_dir_path( __FILE__ );
        $this->load_language( 'version-checker' );

	    add_action( 'plugins_loaded', array ( $this, 'init' ) );
    }

    /**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		if ( !get_site_option('sem_api_key') ) {
			$hub = @ preg_match("|/usr/local/www/[^/]+/www/|", ABSPATH) && file_exists('/etc/semiologic') && is_readable('/etc/semiologic');
			if ( $hub && ( $api_key = trim(file_get_contents('/etc/semiologic')) ) && preg_match("/^[0-9a-f]{32}$/i", $api_key) )
				update_site_option('sem_api_key', $api_key);
		}
		remove_action('admin_notices', 'update_nag', 3);
		add_action('admin_notices', array($this, 'update_nag'), 3);
		add_filter('admin_footer_text', array($this, 'admin_footer_text'), 20);


		// more stuff: register actions and filters
		if ( is_admin() && function_exists('get_transient') ) {
			add_action('admin_menu', array($this, 'admin_menu'));

		foreach ( array(
		    'load-settings_page_sem-api-key',
		    'load-update-core.php',
		    'load-themes.php',
		    'load-plugins.php',
		    'wp_version_check',
		    'load-tools_page_sem-tools',
		    ) as $hook )
		    add_action($hook, array($this, 'get_memberships'), 11);

		foreach ( array(
		    'load-themes.php',
		    'wp_update_themes',
		    'load-tools_page_sem-tools',
		    ) as $hook )
		    add_action($hook, array($this, 'get_themes'), 12);

		foreach ( array(
		    'load-plugins.php',
		    'wp_update_plugins',
		    'load-tools_page_sem-tools',
		    ) as $hook )
		    add_action($hook, array($this, 'get_plugins'), 12);

		add_filter('http_request_args', array($this, 'http_request_args'), 1000, 2);
		add_action('admin_init', array($this, 'init'));

		add_action('admin_enqueue_scripts', array($this, 'sem_news_css'));
		add_action('admin_enqueue_scripts', array($this, 'sem_tools_css'));
		add_action('edit_user_profile', array($this, 'edit_news_pref'));
		add_action('show_user_profile', array($this, 'edit_news_pref'));
		add_action('profile_update', array($this, 'save_news_pref'));

		add_filter('update_feedback', array($this, 'update_feedback'), 100);
		add_action('option_ftp_credentials', array($this, 'option_ftp_credentials'));

		add_action('update-custom_bulk-activate-plugins', array($this, 'bulk_activate_plugins'));
		add_action('admin_footer', array($this, 'sem_news_feed'));

		foreach ( array(
		    'load-update-core.php',
		    'load-update.php',
		    'load-tools_page_sem-tools',
		    ) as $hook )
		    add_action($hook, array($this, 'maybe_disable_streams'), -1000);

		} elseif ( is_admin() ) {
			add_action('admin_notices', array($this, 'add_warning'));
		}

		add_filter('transient_update_themes', array($this, 'update_themes'));
		add_filter('site_transient_update_themes', array($this, 'update_themes'));
		add_filter('transient_update_plugins', array($this, 'update_plugins'));
		add_filter('site_transient_update_plugins', array($this, 'update_plugins'));

		# - Drop plugin upgrades when the slugs don't match
		if (STRICTER_PLUGIN_UPDATES)
			add_filter( 'site_transient_update_plugins', array($this,'disable_upgrades_plugin_name'), 1000 );

		# Fix curl SSL
		add_filter('http_api_curl', array($this, 'curl_ssl'));

		add_action('load-settings_page_sem-api-key', array($this, 'sem_api_key'));

		add_action('load-update-core.php', array($this, 'sem_update_core'));

		add_action('load-tools_page_sem-tools', array($this, 'sem_tools'));

		add_action('load-update-core.php', array($this, 'sem_update_plugins'));
		add_action('load-plugin-install.php', array($this, 'sem_update_plugins'));
		add_action('load-update.php', array($this, 'sem_update_plugins'));
		add_action('load-tools_page_sem-tools', array($this, 'sem_update_plugins'));

		add_action('load-theme-install.php', array($this, 'sem_update_themes'));
		add_action('load-update.php', array($this, 'sem_update_themes'));
		add_action('load-tools_page_sem-tools', array($this, 'sem_update_themes'));

		# WP 3.0 upgrade + work around broken add_site_option
		if ( !get_site_option('sem_packages') ) {
			if ( get_option('sem_api_key') ) {
				update_site_option('sem_api_key', get_option('sem_api_key'));
				update_site_option('sem_packages', get_option('sem_packages'));
				delete_option('sem_api_key');
				delete_option('sem_packages');
			} else {
				update_site_option('sem_api_key', '');
				update_site_option('sem_packages', 'stable');
			}
		}

		wp_cache_add_non_persistent_groups(array('sem_api'));

//		add_action('upgrader_process_complete', array($this, 'upgrade_complete'));

	} # init()
	


	function sem_api_key() {
		if ( !class_exists('sem_api_key') )
			include $this->plugin_path . '/sem-api-key.php';
	}


	function sem_tools() {
		if ( function_exists('apache_setenv') )
			@apache_setenv('no-gzip', 1);
		@ini_set('zlib.output_compression', 0);
		@ini_set('implicit_flush', 1);

		if ( !class_exists('sem_tools') )
			include $this->plugin_path . '/tools.php';

		wp_enqueue_style('plugin-install');
		wp_enqueue_script('plugin-install');
		wp_enqueue_style('themes');
		wp_enqueue_script('theme');
		add_thickbox();
		wp_enqueue_script( 'updates' );

		#$folder = plugin_dir_url(__FILE__);
		#wp_enqueue_script('sem-quicksearch-js', $folder . 'js/quicksearch.js', array('jquery'), '20100121', true);
	} # sem_tools()

	function sem_update_themes() {
		if ( function_exists('apache_setenv') )
			@apache_setenv('no-gzip', 1);
		@ini_set('zlib.output_compression', 0);
		@ini_set('implicit_flush', 1);

		if ( !class_exists('sem_update_themes') )
			include $this->plugin_path . '/themes.php';
	}

	function sem_update_plugins() {
		if ( function_exists('apache_setenv') )
			@apache_setenv('no-gzip', 1);
		@ini_set('zlib.output_compression', 0);
		@ini_set('implicit_flush', 1);

		wp_enqueue_script( 'updates' );

		if ( !class_exists('sem_update_plugins') )
			include $this->plugin_path . '/plugins.php';
	}

	function sem_update_core() {
		if ( function_exists('apache_setenv') )
			@apache_setenv('no-gzip', 1);
		@ini_set('zlib.output_compression', 0);
		@ini_set('implicit_flush', 1);

		if ( !class_exists('sem_update_core') )
			include $this->plugin_path . '/core.php';
	}

	function update_complete( $args ) {
		if ( class_exists('WP_Nav_Menu_Widget') ) {
			delete_site_transient('sem_update_plugins');
		} else {
			delete_transient('sem_update_plugins');
		}
	}

	/**
	 * update_nag()
	 *
	 * @return void
	 **/

	function update_nag() {
		global $pagenow, $page_hook, $wp_version;
		
		if ( !current_user_can('manage_options') )
			return;
		
		if ( function_exists('is_super_admin') && !is_super_admin() )
			return;
		
		if ( in_array($pagenow, array('update.php', 'update-core.php', 'upgrade.php')) || $page_hook == 'tools_page_sem-tools' )
			return;
		
		$msg = array();
		$sem_api_key = get_site_option('sem_api_key');
		
		if ( $page_hook != 'settings_page_sem-api-key' ) {
			if ( !$sem_api_key ) {
				remove_action('admin_footer', array($this, 'sem_news_feed'));
				$msg[] = '<p>'
					. sprintf(__('The Version Checker plugin is almost ready. Please enter your <a href="%s">Semiologic API key</a> to manage your Semiologic packages.', 'version-checker'), 'options-general.php?page=sem-api-key')
					. '</p>' . "\n";
			}
		} elseif ( $page_hook == 'settings_page_sem-api-key' ) {
			if ( $sem_api_key || $_POST && !empty($_POST['sem_api_key']) ) {
				remove_action('admin_footer', array($this, 'sem_news_feed'));
				$msg[] = '<p>'
					. sprintf(__('Browse <a href="%s">Tools / Semiologic</a> to manage Semiologic packages on your site.', 'version-checker'), 'tools.php?page=sem-tools')
					. '</p>' . "\n";
			} else {
				remove_action('admin_footer', array($this, 'sem_news_feed'));
				$msg[] = '<p>'
					. __('Tools / Semiologic becomes available once this screen is configured. Browsing it will allows you to manage Semiologic packages on your site.', 'version-checker')
					. '</p>' . "\n";
			}
		}
		
		$plugins_todo = false;
		$plugins_count = 0;
		$active_plugins = get_option('active_plugins');
		
		if ( $active_plugins && $pagenow != 'plugins.php' || $pagenow == 'plugins.php' ) {
			if ( class_exists('WP_Nav_Menu_Widget') )
				$plugins_response = get_site_transient('update_plugins');
			else
				$plugins_response = get_transient('update_plugins');
			
			if ( $plugins_response && !empty($plugins_response->response) ) {
				foreach ( $plugins_response->response as $plugin => $details ) {
					if ( $details->package ) {
						$plugins_count++;
						if ( !$plugins_todo && in_array($plugin, $active_plugins) )
							$plugins_todo = true;
					}
				}
			}
			
			if ( $plugins_todo ) {
				if ( !function_exists('get_plugins') )
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$plugins = get_plugins();
				foreach ( array_keys($plugins) as $plugin ) {
					if ( !isset($plugins_response->response[$plugin]) )
						continue;
					if ( version_compare($plugins[$plugin]['Version'], $plugins_response->response[$plugin]->new_version, '>=') ) {
						$plugins_count--;
						$plugins_todo &= $plugins_count;
						if ( class_exists('WP_Nav_Menu_Widget') ) {
							delete_site_transient('update_plugins');
							delete_site_transient('sem_update_plugins');
						} else {
							delete_transient('update_plugins');
							delete_transient('sem_update_plugins');
						}
						if ( !$plugins_todo )
							break;
					}
				}
			}
		}
		
		$themes_todo = false;
		$template = get_option('template');
		$stylesheet = get_option('stylesheet');
		
		if ( $template == 'sem-reloaded' && $stylesheet && $pagenow != 'themes.php' ) {
			if ( class_exists('WP_Nav_Menu_Widget') )
				$themes_response = get_site_transient('update_themes');
			else
				$themes_response = get_transient('update_themes');
			
			if ( $themes_response && !empty($themes_response->response) ) {
				foreach ( array('template', 'stylesheet') as $theme ) {
					if ( !empty($themes_response->response[$$theme]) ) {
						$themes_todo = true;
						break;
					}
				}
			}
			
			if ( $themes_todo ) {
				if ( !function_exists('get_themes') )
					require_once ABSPATH . 'wp-includes/theme.php';
				$themes = ( class_exists('wp_get_themes' )) ? wp_get_themes() : get_themes();
				foreach ( $themes as $theme ) {

					if ( !in_array($theme['Template'], array('sem-reloaded', 'sem-pinnacle', 'sem-pinnacle-featured-child')) )
						continue;
					
					# type-transposition: convert from array to object if applicable
					if ( is_array($themes_response->response[$template]) ) {
						$themes_response->response[$template] = (object) $themes_response->response[$template];
					}
					
					if ( version_compare($theme['Version'], $themes_response->response[$template]->new_version, '>=') ) {
						$themes_todo = false;
						if ( class_exists('WP_Nav_Menu_Widget') ) {
							delete_site_transient('update_themes');
							delete_site_transient('sem_update_themes');
						} else {
							delete_transient('update_themes');
							delete_transient('sem_update_themes');
						}
					}
				}
			}
		}
		
		global $wp_db_version;
		$core_todo = false;
		$cur = get_preferred_from_update_core();
		if ( !empty($cur->response) && !empty($cur->package) &&
			$cur->response == 'upgrade'
			#&&
			# dump new version nags
			#!preg_match("/^\d+\.\d+$/", $cur->current) &&
			# dump 3.0 nags for WP 2.9.2 users
			#!( $wp_version == '2.9.2' && preg_match("/^3\.0/", $cur->current) )
			) {
			$core_todo = $cur->current;
		}
		
		if ( get_site_option( 'wpmu_upgrade_site' ) != $wp_db_version )
			remove_action('admin_footer', array($this, 'sem_news_feed'));
		
		if ( $core_todo || $plugins_todo || $themes_todo ) {
			remove_action('admin_footer', array($this, 'sem_news_feed'));
			
			if ( $themes_todo ) {
				$msg[] = '<p>'
					. sprintf(
						__('A <a href="%1$s">theme update</a> is available! (Upgrading the Semiologic theme will <a href="%2$s">retain your customizations</a>.)', 'version-checker'),
						'themes.php',
						'http://www.semiologic.com/software/sem-reloaded/')
					. '</p>' . "\n";
			}
			if ( $plugins_todo ) {
				$button = '';
				if ( get_site_option('sem_api_key') ) {
					$button = '<input type="submit" class="button" value="' . esc_attr(sprintf(__('Mass Upgrade (%s)', 'version-checker'), $plugins_count)) . '" />'
						. '<input type="hidden" name="action" value="mass-upgrade" />' . "\n"
						. wp_nonce_field('mass-upgrade', null, null, false);
				}
				$msg[] = '<form method="post" action="tools.php?page=sem-tools" style="display: inline;">' . "\n"
					. '<p>'
					. ( $pagenow != 'plugins.php'
						? sprintf(
							__('<a href="%1$s">Plugin updates</a> are available! %2$s', 'version-checker'),
							'plugins.php?plugin_status=upgrade',
							$button)
						: sprintf(
							__('Plugin updates are available! %s', 'version-checker'),
							$button)
						)
					. '</p>' . "\n"
					. '</form>' . "\n";
			}
			if ( $core_todo && !$plugins_todo && !$themes_todo ) {
				$msg[] = '<p>'
					. sprintf(__('<a href="%1$s">WordPress %2$s</a> is available! Please upgrade your site.', 'version-checker'),
					'update-core.php',
					$core_todo)
					. '</p>' . "\n";
				
				$hub = @ preg_match("|/usr/local/www/[^/]+/www/|", ABSPATH) && file_exists('/etc/semiologic');
				if ( $hub ) {
					$msg[] = '<p>'
							. sprintf(__('<strong>Note</strong>: you can use <a href="%s">AMS</a> to upgrade WordPress and Semiologic software.', 'version-checker'), 'https://ams.hub.org')
							. '</p>' . "\n";
				}
			}
		}
		
		$php_version = phpversion();
		if ( !version_compare($php_version, '5.0', '>=') ) {
			$msg[] = '<p class="error" style="padding: 10px;">'
				. sprintf(__('<strong>Security Warning</strong>: This site is using an <strong><a href="%1$s">extremely outdated</a></strong> version of PHP (%2$s). Please contact your host, and <s>request</s> <strong><u>insist</u></strong> that they upgrade or reconfigure this accordingly. Alternatively, consider switching to a <a href="%3$s">better host</a>.', 'version-checker'), 'http://www.php.net/archive/2007.php#2007-07-13-1', $php_version, 'http://members.semiologic.com/hosting/')
				. '</p>' . "\n";
		}
		
		if ( $template == 'semiologic' || $stylesheet == 'semiologic' ) {
			$msg[] = '<p>'
				. sprintf(__('<strong>Important Notice</strong>: The theme that you are using has been replaced in favor of the current <a href="%1$s">Semiologic Reloaded theme</a>. Enhancements to this legacy theme have all but stopped, though limited efforts may occur from time to time to address compatibility issues with current WordPress versions. The newer Semiologic Reloaded theme has new layouts, widgets, dropdown menus, <a href="%2$s">over 60 skins</a>, and a custom CSS editor; it also has slightly narrower widths (750px vs 770px, and 950px vs 970px). Please resize your site\'s header image, if necessary, and <a href="%3$s">switch to the new theme</a>.', 'version-checker'), 'http://www.semiologic.com/software/sem-reloaded/', 'http://skins.semiologic.com', 'themes.php')
				. '</p>' . "\n";
			$theme = get_option('current_theme');
			if ( trim($theme) == 'Semiologic' )
				delete_option('current_theme');
		}
		
		if ( $msg ) {
			echo '<div id="update-nag" style="display:block; margin-top:50px; text-align:left;">' . "\n"
				. implode('', $msg)
				. '</div>' . "\n";
		}
	} # update_nag()
	
	/**
	 * sem_tools_css()
	 *
	 * @return void
	 **/

	function sem_tools_css() {
		echo <<<EOS
<style type="text/css">
.tools_page_sem-tools #availablethemes .action-links li {
	float: left;
	margin: 0 10px;
}

.tools_page_sem-tools #availablethemes .action-links .theme-detail {
	display: none;
}

.tools_page_sem-tools #availablethemes .available-theme {
	float: left;
    padding: 0 50px 0 0;
}

.tools_page_sem-tools .tablenav.themes {
	display: none;
}

.tools_page_sem-tools .plugins .name {
	max-width: 200px;
    overflow: hidden;
    word-wrap: normal;
    white-space: nowrap;
    text-overflow: ellipsis;
}

</style>
EOS;
	} # sem_tools_css()


	/**
	 * sem_news_css()
	 *
	 * @return void
	 **/

	function sem_news_css() {
		if ( version_checker::get_news_pref() == 'false' )
			return;
		
		$position = ( is_rtl() ) ? 'left' : 'right';
		$top = 3.5;

		// WP 3.4+
        if ( class_exists( 'WP_Theme') )
            $top = 1.2;

		global $wp_ozh_adminmenu;
		
		if ( $wp_ozh_adminmenu )
			$top += 2.3;

		echo <<<EOS
<style type="text/css">
#sem_news_feed {
	position: absolute;
	top: ${top}em;
	margin: 0;
	padding: 0;
	$position: 175px;
	width: 320px;
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
		
		if ( !empty($upgrading) || version_checker::get_news_pref() == 'false' )
			return;
		
		if ( class_exists('WP_Nav_Menu_Widget') )
			$sem_news_error = get_site_transient('sem_news_error');
		else
			$sem_news_error = get_transient('sem_news_error');
		
		if ( !$sem_news_error ) {
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_news_error', time() - 3600);
			else
				set_transient('sem_news_error', time() - 3600);
		}
		if ( $sem_news_error + 3600 > time() )
			return;
		
		add_filter('wp_feed_cache_transient_lifetime', array($this, 'sem_news_timeout'));
		$feed = fetch_feed('http://www.semiologic.com/news/wordpress/feed/');
		remove_filter('wp_feed_cache_transient_lifetime', array($this, 'sem_news_timeout'));

		if ( is_wp_error($feed) || !$feed->get_item_quantity() ) {
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_news_error', time() + 3600);
			else
				set_transient('sem_news_error', time() + 3600);
			return;
		}
		
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
			$func = function_exists('esc_url') ? 'esc_url' : 'clean_url';
			
			echo '<div id="sem_news_feed">' . "\n"
				. sprintf(__('<a href="%1$s" title="Semiologic Development News">Dev News</a>: <a href="%2$s" title="%3$s">%4$s</a>', 'version-checker'),  call_user_func($func, $dev_news_url), call_user_func($func, $link), $content, $title)
				. '</div>' . "\n";
			break;
		}
		
		return;
	} # sem_news_feed()


    /**
     * sem_news_timeout()
     *
     * @param $timeout
     * @return int
     */

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
		
		$pref = get_user_meta($user_id, 'sem_news', true);
		
		if ( $pref === '' ) {
			$user = new WP_User($user_id);
			$pref = $user->has_cap('edit_posts') || $user->has_cap('edit_pages')
				? 'true'
				: 'false';
			update_user_meta($user_id, 'sem_news', $pref);
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
		
		update_user_meta($user_ID, 'sem_news', isset($_POST['sem_news']) ? 'true' : 'false');
	} # save_news_pref()
	
	
	/**
	 * admin_footer_text()
	 *
	 * @param string $text
	 * @return string $text
	 **/

	function admin_footer_text($text = '') {
		if ( current_user_can('unfiltered_html') ) {
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
			$args['timeout'] = 1800;
		else
			$args['timeout'] = 600;
		
		version_checker::force_flush();
		
		$transport = _wp_http_get_object();
		
		// wp 3.2 compat
		if (method_exists($transport, '_getTransport')) {
			// before 3.2:	
			$transport = $transport->_getTransport();
			if ($transport) {
				$transport = current($transport);
				$transport = get_class($transport);
			}			
		} else {
			// with 3.2:
			$transport = $transport->_get_first_available_transport($args, $url);
		}
		
		if ( !$transport )
			wp_die(__('No valid HTTP transport seems to be available to complete your request', 'health-check'));
		
		if ( $transport == 'WP_Http_Fopen' ) {
			if ( is_null( $args['headers'] ) )
				$args['headers'] = array();

			if ( isset($args['headers']['User-Agent']) ) {
				$args['user-agent'] = $args['headers']['User-Agent'];
				unset($args['headers']['User-Agent']);
			}

			if ( isset($args['headers']['user-agent']) ) {
				$args['user-agent'] = $args['headers']['user-agent'];
				unset($args['headers']['user-agent']);
			}

			WP_Http::buildCookieHeader($args);
			
			if ( ! is_array($args['headers']) ) {
				$processedHeaders = WP_Http::processHeaders($args['headers']);
				$args['headers'] = $processedHeaders['headers'];
			}

			if ( !empty($args['headers']) ) {
				$user_agent_extra_headers = '';
				foreach ( $args['headers'] as $header => $value )
					$user_agent_extra_headers .= "\r\n$header: $value";
				ini_set('user_agent', $args['user-agent'] . $user_agent_extra_headers);
			} else {
				ini_set('user_agent', $args['user-agent']);
			}
		}

		return $args;
	} # http_request_args()
	
	
	/**
	 * get_auth()
	 *
	 * @return array $cookies
	 **/

	function get_auth() {
		$sem_api_key = get_site_option('sem_api_key');
		
		if ( !$sem_api_key )
			wp_die(__('The Url you\'ve tried to access is restricted. Please enter your Semiologic API key.', 'version_checker'));
		
		if ( class_exists('WP_Nav_Menu_Widget') )
			$cookies = get_site_transient('sem_cookies');
		else
			$cookies = get_transient('sem_cookies');
		
		if ( $cookies !== false )
			return $cookies;
		
		global $wp_version;
		
		$url = sem_api_auth . '/' . $sem_api_key;
		
		$options = array(
			'timeout' => 15,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = md5(serialize(array($url, $options)));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) ) {
			wp_die($raw_response);
		} elseif ( 200 != $raw_response['response']['code'] ) {
			wp_die(sprintf(__('An error occurred while trying to authenticate you on Semiologic.com in order to access a members-only package. It generally has one of three causes. The most common is, no transport is available to complete the request. (The <a href="%1$s">Core Control</a> plugin will tell you.) The second is that your <a href="%2$s">API key</a> is incorrect, or your <a href="%3$s">membership</a> is expired. The third that there is a network problem (e.g., semiologic.com is very busy). Please double check the two first, and try again in a few minutes.', 'version_checker'), 'http://wordpress.org/extend/plugins/core-control/', 'http://members.semiologic.com', 'http://members.semiologic.com/memberships.php'));
		} else {
			$cookies = $raw_response['cookies'];
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_cookies', $cookies, 1800); // half hour
			else
				set_transient('sem_cookies', $cookies, 1800); // half hour
			return $cookies;
		}
	} # get_auth()


    /**
     * get_memberships()
     *
     * @internal param bool $force
     * @return array $memberships, false on failure
     */

	static function get_memberships() {
		$sem_api_key = get_site_option('sem_api_key');
		
		if ( !$sem_api_key )
			return array();
		
		if ( class_exists('WP_Nav_Menu_Widget') )
			$obj = get_site_transient('sem_memberships');
		else
			$obj = get_transient('sem_memberships');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->response = array();
		}
		
		$current_filter = current_filter();
		
		$precondition = (bool) ('settings_page_sem-api-key' == $current_filter && isset($obj->response['sem-pro'])); 
		
		# type-transposition: convert from array to object if applicable
		if ($precondition && is_array($obj->response['sem-pro'])) {
			$obj->response['sem-pro'] = (object) $obj->response['sem-pro']; 
		}
		
		if ( $precondition && $obj->response['sem-pro']->expires ) {
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
		
		if ( ( $obj->last_checked >= time() - $timeout ) || $_POST && $current_filter != 'settings_page_sem-api-key' )
			return $obj->response;
		
		global $wpdb;
		global $wp_version;
		
		$obj->last_checked = time();
		if ( class_exists('WP_Nav_Menu_Widget') )
			set_site_transient('sem_memberships', $obj);
		else
			set_transient('sem_memberships', $obj);
		
		$url = sem_api_memberships . '/' . $sem_api_key;
		
		$body = array(
			'php_version' => phpversion(),
			'mysql_version' => $wpdb->db_version(),
			'locale' => apply_filters('core_version_check_locale', get_locale()),
			);
		
		$options = array(
			'timeout' => 15,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = md5(serialize(array($url, $options)));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) ) {
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_api_error', $raw_response->get_error_messages());
			else
				set_transient('sem_api_error', $raw_response->get_error_messages());
		} else {
			if ( class_exists('WP_Nav_Menu_Widget') )
				delete_site_transient('sem_api_error');
			else
				delete_transient('sem_api_error');
		}
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) { // keep old response in case of error
			if ( $obj->response != $response ) {
				if ( class_exists('WP_Nav_Menu_Widget') ) {
					delete_site_transient('sem_update_core');
					delete_site_transient('sem_update_themes');
					delete_site_transient('sem_update_plugins');
					delete_site_transient('sem_query_plugins');
					delete_site_transient('sem_query_themes');
				} else {
					delete_transient('sem_update_core');
					delete_transient('sem_update_themes');
					delete_transient('sem_update_plugins');
					delete_transient('sem_query_plugins');
					delete_transient('sem_query_themes');
				}
			}
			
			$obj->response = $response;
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_memberships', $obj);
			else
				set_transient('sem_memberships', $obj);
		}
		
		return $obj->response;
	} # get_memberships()
	
	
	/**
	 * get_themes()
	 *
	 * @param array $checked
	 * @return array $response
	 **/

	function get_themes($checked = null) {
		$sem_api_key = get_site_option('sem_api_key');
		
		if ( !$sem_api_key )
			return array();
		
		if ( class_exists('WP_Nav_Menu_Widget') )
			$obj = get_site_transient('sem_update_themes');
		else
			$obj = get_transient('sem_update_themes');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->checked = array();
			$obj->response = array();
		}
		
		if ( in_array(current_filter(), array('load-themes.php', 'load-tools_page_sem-tools')) ) {
			$timeout = 3600;
		} else {
			$timeout = 43200;
		}
		
		if ( is_array($checked) && $checked && $obj->checked && $obj->checked != $checked ) {
			if ( class_exists('WP_Nav_Menu_Widget') ) {
				delete_site_transient('sem_update_themes');
				delete_site_transient('update_themes');
			} else {
				delete_transient('sem_update_themes');
				delete_transient('update_themes');
			}
			return false;
		}
		
		if ( ( $obj->last_checked >= time() - $timeout ) || $_POST )
			return $obj->response;
		
		global $wp_version;
		
		if ( !function_exists('get_themes') )
			require_once ABSPATH . 'wp-includes/theme.php';
		
		$obj->last_checked = time();
		if ( class_exists('WP_Nav_Menu_Widget') )
			set_site_transient('sem_update_themes', $obj);
		else
			set_transient('sem_update_themes', $obj);
		
		$url = sem_api_version . '/themes/' . $sem_api_key;

        if ( function_exists( 'wp_get_themes') )
            $to_check = wp_get_themes();
        else
            $to_check = get_themes();

		$check = array();
		foreach ( $to_check as $themes )
			$check[$themes['Stylesheet']] = $themes['Version'];
		
		$obj->checked = $check;
		if ( class_exists('WP_Nav_Menu_Widget') )
			set_site_transient('sem_update_themes', $obj);
		else
			set_transient('sem_update_themes', $obj);
		
		$body = array(
			'check' => $check,
			'packages' => get_site_option('sem_packages'),
			'locale' => apply_filters('core_version_check_locale', get_locale()),
			);
		
		$options = array(
			'timeout' => 15,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = md5(serialize(array($url, $options)));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) ) {
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_api_error', $raw_response->get_error_messages());
			else
				set_transient('sem_api_error', $raw_response->get_error_messages());
		} else {
			if ( class_exists('WP_Nav_Menu_Widget') )
				delete_site_transient('sem_api_error');
			else
				delete_transient('sem_api_error');
		}
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) { // keep old response in case of error
			foreach ( $response as $key => $package )
				$response[$key] = (array) $package;
			$obj->response = $response;
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_update_themes', $obj);
			else
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
		
		if ( empty($ops->checked) || !is_array($ops->checked) )
			$ops->checked = array();
		
		if ( empty($ops->response) || !is_array($ops->response) )
			$ops->response = array();
		
		foreach ( $ops->checked as $plugin => $version ) {
			if ( isset($ops->response[$plugin]) && strpos($version, 'fork') !== false )
				unset($ops->response[$plugin]);
		}
		
		$extra = version_checker::get_themes($ops->checked);
		
		if ( $extra === false )
			return $ops;
		
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
		$sem_api_key = get_site_option('sem_api_key');
		
		if ( !$sem_api_key )
			return array();
		
		if ( class_exists('WP_Nav_Menu_Widget') )
			$obj = get_site_transient('sem_update_plugins');
		else
			$obj = get_transient('sem_update_plugins');
		
		if ( !is_object($obj) ) {
			$obj = new stdClass;
			$obj->last_checked = false;
			$obj->checked = array();
			$obj->response = array();
		}
		
		if ( in_array(current_filter(), array('load-plugins.php', 'load-tools_page_sem-tools')) ) {
			$timeout = 3600;
		} else {
			$timeout = 43200;
		}
		
		if ( is_array($checked) && $checked && $obj->checked && $obj->checked != $checked ) {
			if ( class_exists('WP_Nav_Menu_Widget') ) {
				delete_site_transient('sem_update_plugins');
				delete_site_transient('update_plugins');
			} else {
				delete_transient('sem_update_plugins');
				delete_transient('update_plugins');
			}
			return false;
		}
		
		if ( ( $obj->last_checked >= time() - $timeout ) || $_POST )
			return $obj->response;
		
		global $wp_version;
		
		if ( !function_exists('get_plugins') )
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		
		$obj->last_checked = time();
		if ( class_exists('WP_Nav_Menu_Widget') )
			set_site_transient('sem_update_plugins', $obj);
		else
			set_transient('sem_update_plugins', $obj);
		
		$url = sem_api_version . '/plugins/' . $sem_api_key;
		
		$to_check = get_plugins();
		$check = array();
		
		foreach ( $to_check as $file => $plugin )
			$check[$file] = $plugin['Version'];
		
		$obj->checked = $check;
		if ( class_exists('WP_Nav_Menu_Widget') )
			set_site_transient('sem_update_plugins', $obj);
		else
			set_transient('sem_update_plugins', $obj);
		
		$body = array(
			'check' => $check,
			'packages' => get_site_option('sem_packages'),
			'locale' => apply_filters('core_version_check_locale', get_locale()),
			);
		
		$options = array(
			'timeout' => 15,
			'body' => $body,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			);
		
		$cache_id = md5(serialize(array($url, $options)));
		$raw_response = wp_cache_get($cache_id, 'sem_api');
		
		if ( $raw_response === false ) {
			$raw_response = wp_remote_post($url, $options);
			wp_cache_set($cache_id, $raw_response, 'sem_api');
		}
		
		if ( is_wp_error($raw_response) ) {
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_api_error', $raw_response->get_error_messages());
			else
				set_transient('sem_api_error', $raw_response->get_error_messages());
		} else {
			if ( class_exists('WP_Nav_Menu_Widget') )
				delete_site_transient('sem_api_error');
			else
				delete_transient('sem_api_error');
		}
		
		if ( is_wp_error($raw_response) || 200 != $raw_response['response']['code'] )
			$response = false;
		else
			$response = @unserialize($raw_response['body']);
		
		if ( $response !== false ) { // keep old response in case of error
			$obj->response = $response;
			if ( class_exists('WP_Nav_Menu_Widget') )
				set_site_transient('sem_update_plugins', $obj);
			else
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
		
		if ( empty($ops->checked) || !is_array($ops->checked) )
			$ops->checked = array();
		
		if ( empty($ops->response) || !is_array($ops->response) )
			$ops->response = array();
		
		foreach ( $ops->checked as $plugin => $version ) {
			if ( isset($ops->response[$plugin]) && strpos($version, 'fork') !== false )
				unset($ops->response[$plugin]);
		}
		
		$extra = version_checker::get_plugins($ops->checked);
		
		if ( $extra === false )
			return $ops;
		
		$ops->response = array_merge($ops->response, $extra);
		
		return $ops;
	} # update_plugins()
	
	
	/**
	 * check()
	 *
	 * @param string $membership
	 * @return bool $running
	 **/

	static function check($membership) {
		$memberships = version_checker::get_memberships();
		$memberships[$membership] = (array) $memberships[$membership];
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
		if ( function_exists('is_super_admin') && !is_super_admin() )
			return;
		
		add_options_page(
			__('Semiologic API Key', 'version-checker'),
			__('Semiologic API Key', 'version-checker'),
			'manage_options',
			'sem-api-key',
			array('sem_api_key', 'edit_options')
			);
		
		if ( get_site_option('sem_api_key') ) {
			add_management_page(
				__('Semiologic', 'version-checker'),
				__('Semiologic', 'version-checker'),
				'manage_options',
				'sem-tools',
				array('sem_tools', 'display')
				);
		}
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

	static function force_flush() {
		echo "\n\n<!-- Deal with browser-related buffering by sending some incompressible strings -->\n\n";
		
		for ( $i = 0; $i < 5; $i++ )
			echo "<!-- abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono -->\n\n";
		
		while ( ob_get_level() )
			ob_end_flush();
		
		@flush();
		@set_time_limit(FS_TIMEOUT);
	} # force_flush()
	
	
	/**
	 * reconnect_ftp()
	 *
	 * @return void
	 **/

	static function reconnect_ftp() {
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
     * @param mixed
     * @return mixed
     */

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
					show_message(sprintf(__('Cleaning up %1$s. This step can readily take about 10 minutes without the slightest amount of feedback from WordPress. You can avoid it by deleting your %2$s folder using your FTP software before proceeding.', 'version-checker'), $folder, basename($folder)));
					version_checker::force_flush();
					$wp_filesystem->delete($folder, true);
					version_checker::reconnect_ftp();
				}
			}
			
			if ( $folders = glob(WP_CONTENT_DIR . '/upgrade/wordpress*/') ) {
				foreach ( $folders as $folder ) {
					$folder = $wp_filesystem->find_folder($folder);
					show_message(sprintf(__('Cleaning up %1$s. This step can readily take about 10 minutes without the slightest amount of feedback from WordPress. You can avoid it by deleting your %2$s folder using your FTP software before proceeding.', 'version-checker'), $folder, basename($folder)));
					version_checker::force_flush();
					$wp_filesystem->delete($folder, true);
					version_checker::reconnect_ftp();
				}
			}
			
			show_message(__('Starting upgrade... Please note that this can take several minutes without any feedback from WordPress.', 'version-checker'));
			version_checker::force_flush();
			
			add_action('http_api_debug', array($this, 'maybe_flush'), 100, 2);
		}
		
		return $in;
	} # option_ftp_credentials()
	
	
	/**
	 * bulk_activate_plugins()
	 *
	 * @return void
	 **/

	function bulk_activate_plugins() {
		if ( !current_user_can('activate_plugins') )
			wp_die(__('You do not have sufficient permissions to activate plugins for this blog.', 'version-checker'));
		
		check_admin_referer('bulk-activate-plugins');

        $plugins = array ();
		if ( !empty($_GET['plugins']) ) {
			$plugins = (array) $_GET['plugins'];
			$plugins = array_filter($plugins, create_function('$plugin', 'return !is_plugin_active($plugin);') ); //Only activate plugins which are not already active.
		}
		
		if( ! isset($_GET['failure']) && ! isset($_GET['success']) ) {
			wp_redirect( 'update.php?action=bulk-activate-plugins&failure=true&plugins[]=' . implode('&plugins[]=', $plugins) . '&_wpnonce=' . $_GET['_wpnonce'] );
			foreach ( $plugins as $plugin )
				activate_plugin($plugin);
			wp_redirect( 'update.php?action=bulk-activate-plugins&success=true&plugins[]=' . implode('&plugins[]=', $plugins) . '&_wpnonce=' . $_GET['_wpnonce'] );
			die();
		}
		iframe_header( __('Bulk Plugin Activation', 'version-checker'), true );
		if ( isset($_GET['success']) )
			echo '<p>' . __('Plugins activated successfully.', 'version-checker') . '</p>';

		if ( isset($_GET['failure']) ) {
			echo '<p>' . __('Plugins failed to reactivate due to a fatal error.', 'version-checker') . '</p>';
			if ( defined('E_RECOVERABLE_ERROR') )
				error_reporting(E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR);
			else
				error_reporting(E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING);
			
			@ini_set('display_errors', true); //Ensure that Fatal errors are displayed.
			foreach ( $plugins as $plugin )
				include_once WP_PLUGIN_DIR . '/' . $plugin;
		}
		iframe_footer();
	} # bulk_activate_plugins()
	
	
	/**
	 * maybe_disable_streams()
	 *
	 * @return void
	 **/

	function maybe_disable_streams() {
		add_filter('use_streams_transport', array($this, 'use_streams_transport'));
	} # maybe_disable_streams()


    /**
     * use_streams_transport()
     *
     * @param $use
     * @return bool
     */

	function use_streams_transport($use) {
		if ( !$use )
			return false;
		
		// --with-curlwrappers is extremely buggy, see #11888
		ob_start();
		phpinfo(1);
		$info = ob_get_contents();
		ob_end_clean();
		if ( strpos($info, '--with-curlwrappers') !== false )
			return false;
		
		return $use;
	} # use_streams_transport()
	
	
	/**
	 * Disable SSL validation for Curl
	 *
	 * @param resource $ch
	 * @return resource $ch
	 **/
	function curl_ssl($ch)
	{
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		return $ch;
	}


	function disable_upgrades_plugin_name($updates) {
		if (empty($updates) || empty($updates->response)) {
			return $updates;
		}

		foreach ($updates->response as $key => $response) {
			$slug = strpos($key, '/') !== false ? dirname($key) : basename($key, '.php');
			if ($slug != $response->slug) {
			   unset($updates->response[$key]);
			}
		}

		return $updates;
	}
} # version_checker

$version_checker = version_checker::get_instance();