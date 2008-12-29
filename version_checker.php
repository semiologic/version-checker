<?php
if ( !class_exists('sem_api_key') )
{
	include dirname(__FILE__) . '/sem-api-key.php';
}

class version_checker
{
	#
	# init()
	#

	function init()
	{
		remove_action('load-plugins.php', 'wp_update_plugins');
		
		add_action('load-plugins.php', array('version_checker', 'do_check_plugins'));
		
		add_filter('option_update_plugins', array('version_checker', 'update_plugins'));
		
		add_action('shutdown', array('version_checker', 'check_sem_pro'));
		add_action('admin_notices', array('version_checker', 'nag_user'));
		
		add_action('admin_init', array('version_checker', 'admin_init'));
		
		add_filter('sem_api_key_protected', array('version_checker', 'sem_api_key_protected'));
		
		if ( !( $package = get_option('sem_package')) || !( $api_key = get_option('sem_api_key') ) )
		{
			if ( $api_key )
			{
				$package = 'stable';
			}
			else
			{
				$package = 'wp';
			}
			
			update_option('sem_package', $package);
		}
		
		add_filter('option_update_core', array('version_checker', 'update_core'));
		add_action('load-update-core.php', array('version_checker', 'load_update_core'));
	} # init()
	
	
	#
	# fix_core_reinstall()
	#
	
	function fix_core_reinstall($buffer)
	{
		$from = '<form action="' . wp_nonce_url('update-core.php?action=do-core-upgrade', 'upgrade-core') . '"';
		$to = '<form action="' . wp_nonce_url('update-core.php?action=do-core-reinstall', 'upgrade-core') . '"';
		$buffer = str_replace($from, $to, $buffer);
		
		return $buffer;
	} # fix_core_reinstall()
	
	
	#
	# load_update_core()
	#
	
	function load_update_core()
	{
		remove_action('admin_notices', array('version_checker', 'nag_user'));
		
		if ( !$_POST && in_array(get_option('sem_package'), array('stable', 'bleeding')) )
		{
			# check version status
			$api_key = get_option('sem_api_key');
			$url = 'https://api.semiologic.com/memberships/0.1/sem_pro/' . urlencode($api_key);
			
			$expires = wp_remote_fopen($url);
			
			if ( !is_wp_error($res) && !preg_match("|<error>.*</error>|is", $expires) )
			{
				preg_match("|<expires>(.*)</expires>|isU", $expires, $expires);
				$expires = $expires[1];
				
				if ( $expires && strtotime($expires) < time() )
				{
					update_option('sem_package', 'wp');
				}
			}
		}
		
		
		if ( in_array(get_option('sem_package'), array('stable', 'bleeding')) )
		{
			add_filter('update_feedback', array('version_checker', 'extend_timeout'));
			ob_start(array('version_checker', 'update_core_captions'));
		
			if ( !$_POST )
			{
				# force an update check on load
				version_checker::check_sem_pro(true);
			}
		}
		
		if ( isset($_GET['action']) && $_GET['action'] == 'do-core-reinstall' )
		{
			# todo: remove the fix when this ticket gets fixed:
			# http://trac.wordpress.org/ticket/8724
			ob_start(array('version_checker', 'fix_core_reinstall'));
		}
	} # load_update_core()
	
	
	#
	# extend_timeout()
	#
	
	function extend_timeout($in)
	{
		# flush buffer
		while ( @ob_end_flush() );
		
		# restart buffer
		ob_start(array('version_checker', 'update_core_captions'));
		
		# add 5 minutes
		@set_time_limit(300);
		
		return $in;
	} # extend_timeout()
	
	
	#
	# update_core_captions()
	#
	
	function update_core_captions($buffer)
	{
		$package = get_option('sem_package');
		
		$version = ( $package == 'stable' ) ? 'stable version' : 'bleeding edge version';
		
		$captions = array(
			__('Upgrade WordPress') => 'Update Semiologic Pro',
			__('There is a new version of WordPress available for upgrade') => "There is a new $version of Semiologic Pro available for upgrade",
			__('You have the latest version of WordPress. You do not need to upgrade') => (
				!defined('sem_version')
				? "No version information found. Click Re-install to get the latest $version of Semiologic Pro."
				: "You have the latest $version of Semiologic Pro. You do not need to upgrade"
				),
			__('WordPress upgraded successfully') => 'Semiologic Pro upgraded successfully'
			);
		
		$find = array_keys($captions);
		$replace = array_values($captions);
		
		$buffer = str_replace($find, $replace, $buffer);
		
		return $buffer;
	} # update_core_captions()
	
	
	#
	# update_core()
	#
	
	function update_core($o)
	{
		if ( !is_admin() ) return $o;
		
		$package = get_option('sem_package');
		$api_key = get_option('sem_api_key');
		
		if ( !$api_key
			|| !in_array($package, array('stable', 'bleeding'))
			|| !is_array($o->updates)
			|| !current_user_can('administrator')
			) return $o;
		
		$versions = get_option('sem_versions');
		
		if ( !isset($versions['versions']) )
		{
			version_checker::check_sem_pro();
			$versions = get_option('sem_versions');
		}
		
		if ( !isset($versions['versions']) )
		{
			return $o;
		}
		
		$versions = $versions['versions'];
		
		if ( !isset($versions[$package]) ) return $o;
		
		$update = $o->updates[0];
		
		$update->current = $versions[$package];
		
		if ( $package == 'stable' )
		{
			$update->url = 'http://www.semiologic.com/members/sem-pro/download/';
			$update->package = 'http://www.semiologic.com/media/members/sem-pro/download/sem-pro.zip';
		}
		else
		{
			$update->url = 'http://www.semiologic.com/members/sem-pro/bleeding/';
			$update->package = 'http://www.semiologic.com/media/members/sem-pro/bleeding/sem-pro-bleeding.zip';
		}
		
		if ( !defined('sem_version')
			|| version_compare(sem_version, $versions[$package], '>=')
			)
		{
			$update->response = 'latest';
		}
		else
		{
			$update->response = 'upgrade';
		}
		
		$update->package .= '?user_key=' . urlencode($api_key);
		
		$o->updates = array($update);
		
		return $o;
	} # update_core()
	
	
	#
	# sem_api_key_protected()
	#
	
	function sem_api_key_protected($array)
	{
		$array[] = 'http://www.semiologic.com/media/software/wp-tweaks/wp-tweaks/version-checker.zip';
		
		return $array;
	} # sem_api_key_protected()
	
	
	#
	# admin_init()
	#
	
	function admin_init()
	{
		# kill wp notifications
		remove_filter( 'update_footer', 'core_update_footer' );
		remove_action( 'admin_notices', 'update_nag', 3 );
		
		global $wp_filter;

		$keys = array_keys((array) $wp_filter['in_admin_footer']);
		sort($keys);
		$key = $key[0] + 1000;
		add_action('in_admin_footer', array('version_checker', 'flush_footer'), $key);

		$keys = array_keys((array) $wp_filter['admin_footer']);
		sort($keys);
		$key = $key[0] - 1000;
		add_action('admin_footer', array('version_checker', 'display_links'), $key);
	} # admin_init()
	
	
	#
	# flush_footer()
	#
	
	function flush_footer()
	{
		ob_start();
	} # flush_footer()
	
	
	#
	# display_links()
	#
	
	function display_links()
	{
		ob_get_clean();
		
		$upgrade = apply_filters( 'update_footer', '' );
		
		if ( current_user_can('administrator') && defined('sem_version') )
		{
			echo '<a href="'
					. ( ( $api_key = get_option('sem_api_key') )
						? ( 'http://www.semiologic.com/members/sem-pro/?user_key=' . $api_key )
						: 'http://www.semiologic.com/members/sem-pro/'
						)
						. '">'
				. 'Semiologic Pro v.' . sem_version
				. '</a>'
				. ' &bull; '
				. '<a href="http://www.semiologic.com/resources/">'
				. __('Documentation &amp; Resources')
				. '</a>'
				. ' &bull; '
				. '<a href="http://forum.semiologic.com">'
				. __('Community Forum')
				. '</a>';
		}
		elseif ( defined('sem_version') )
		{
			echo '<a href="http://www.getsemiologic.com">'
				. 'Semiologic Pro v.' . sem_version
				. '</a>'
				. ' &bull; '
				. '<a href="http://www.semiologic.com/resources/">'
				. __('Documentation &amp; Resources')
				. '</a>';
		}
		else
		{
			echo '<a href="http://wordpress.org">'
				. 'WordPress v.' . $GLOBALS['wp_version']
				. '</a>'
				. ' &bull; '
				. '<a href="http://codex.wordpress.org">'
				. __('Documentation')
				. '</a>'
				. ' &bull; '
				. '<a href="http://wordpress.org/support">'
				. __('Support')
				. '</a>';
		}
		
		echo '<p id="footer-upgrade" class="alignright">' . $upgrade . '</p>' . "\n";
		echo '<div class="clear"></div>' . "\n";
		echo '</div>' . "\n";
	} # display_links()
	

	#
	# get_response()
	#
	
	function get_response($files)
	{
		$todo = array();
		$response = array();

		foreach ( $files as $file => $src )
		{
			$src = file_get_contents($src);
			
			if ( !preg_match("/Update Service:(.*)/i", $src, $service) )
			{
				continue;
			}

			$service = trim(end($service));

			if ( strpos($service, '://') === false )
			{
				$service = 'http://' . $service;
			}

			$response[$file]->service = $service;
			$todo[$service][] = $file;

			if ( preg_match("/Update Tag:(.*)/i", $src, $tag) )
			{
				$tag = trim(end($tag));
			}
			else
			{
				$tag = basename($file, '.php');
			}

			$response[$file]->tag = $tag;

			if ( preg_match("/Update URI:(.*)/i", $src, $url) )
			{
				$url = trim(end($url));
			}
			elseif ( preg_match("/Plugin URI:(.*)/i", $src, $url) )
			{
				$url = trim(end($url));
			}
			elseif ( preg_match("/Theme URI:(.*)/i", $src, $url) )
			{
				$url = trim(end($url));
			}
			else
			{
				$url = '';
			}

			$response[$file]->url = $url;

			if ( preg_match("/Version:(.*)/i", $src, $version) )
			{
				$version = trim(end($version));
			}
			else
			{
				$version = 0;
			}

			$response[$file]->version = $version;

			if ( preg_match("/Update Package:(.*)/i", $src, $package) )
			{
				$package = trim(end($package));
			}
			else
			{
				$package = '';
			}

			$response[$file]->package = $package;
		}
		
		foreach ( $todo as $service => $files )
		{
			if ( count($files) == 1 )
			{
				$file = current($files);
				$tag = $response[$file]->tag;

				$url = trailingslashit($service) . urlencode($tag);
			}
			else
			{
				$url = trailingslashit($service);

				foreach ( $files as $file )
				{
					$tags[] = urlencode($response[$file]->tag);
				}

				$url .= '?tag[]=' . implode('&tag[]=', $tags);
			}
			
			$new_version = wp_remote_fopen($url);
			
			if ( $new_version === false ) continue;

			if ( is_wp_error($new_version) || $new_version === false ) continue;
			
			if ( count($files) == 1 )
			{
				$response[$file]->new_version = trim(strip_tags($new_version));
			}
			else
			{
				$lines = split("\n", $new_version);

				foreach ( $lines as $line )
				{
					if ( $line )
					{
						list($tag, $new_version) = split(':', $line);
						$tag = trim(strip_tags($tag));
						$new_version = trim(strip_tags($new_version));

						$new_versions[$tag] = $new_version;
					}
				}

				foreach ( $files as $file )
				{
					$response[$file]->new_version = $new_versions[$response[$file]->tag];
				}
			}
		}
		
		foreach ( array_keys((array) $response) as $file )
		{
			if ( !version_compare(
					$response[$file]->new_version,
					$response[$file]->version,
					'>'
					)
				)
			{
				unset($response[$file]);
			}

		}
		
		$response = apply_filters('version_checker', $response);
		
		return $response;
	} # get_response()
	
	
	#
	# check_sem_pro()
	#
	
	function check_sem_pro($force = false)
	{
		$package = get_option('sem_package');
		
		if ( !in_array($package, array('stable', 'bleeding')) ) return false;
		
		$options = get_option('sem_versions');
		
		if ( $force
			|| !isset($options['last_checked'])
			|| $options['last_checked'] + 3600 * 24 * 2 < time()
			)
		{
			$url = 'http://version.semiologic.com/sem-pro/';
			
			$lines = wp_remote_fopen($url);
			
			if ( $lines === false)
			{
				$versions = array(
						'stable' => sem_version,
						'bleeding' => sem_version,
					);
			}
			else
			{
				$lines = split("\n", $lines);

				$versions = array();

				foreach ( $lines as $line )
				{
					if ( $line )
					{
						list($tag, $version) = split(':', $line);

						$tag = trim(strip_tags($tag));
						$version = trim(strip_tags($version));

						$versions[$tag] = $version;
					}
				}
			}
			
			$options = array(
				'last_checked' => time(),
				'versions' => $versions,
				);
			
			update_option('sem_versions', $options);
		}
	} # check_sem_pro()
	
	
	#
	# nag_user()
	#
	
	function nag_user()
	{
		if ( !defined('sem_version')
			|| !current_user_can('administrator')
			) return;
		
		$options = get_option('sem_versions');
		
		if ( !$options ) return;
		
		$versions = $options['versions'];
		
		if ( version_compare(sem_version, $versions['stable'], '<')
			|| version_compare(sem_version, $versions['stable'], '>')
				&& version_compare(sem_version, $versions['bleeding'], '<')
			)
		{
			echo '<div class="updated">'
				. '<p>'
				. sprintf('<strong>Version Checker Notice</strong> - A Semiologic Pro update is available (<a href="http://www.semiologic.com">more info</a>). Browse <a href="%s">Tools / Upgrade</a> to upgrade your site. <a href="http://www.semiologic.com/resources/wp-basics/why-upgrade/">Why this is important</a>.', trailingslashit(get_option('siteurl')) . 'wp-admin/update-core.php')
				. '</p>'
				. '</div>';
		}
	} # nag_user()


	#
	# check_plugins()
	#

	function check_plugins()
	{
		$options = get_option('version_checker');
		
		# debug:
		$options = array();
		
		if ( $options === false )
		{
			$options = array();
		}
		
		if ( !isset($options['plugins']['last_checked'])
			|| $options['plugins']['last_checked'] + 3600 * 24 * 2 < time()
			)
		{
			$files = array();
			
			foreach ( array_keys((array) get_plugins()) as $file )
			{
				$files[$file] = ABSPATH . PLUGINDIR . '/' . $file;
			}
			
			$response = version_checker::get_response($files);
			
			foreach ( array_keys((array) $response) as $file )
			{
				if ( !version_compare(
						$response[$file]->new_version,
						$response[$file]->version,
						'>'
						)
					)
				{
					unset($response[$file]);
				}
			}

			$options['plugins']['response'] = $response;
			$options['plugins']['last_checked'] = time();
			
			update_option('version_checker', $options);
		}
		
		# debug:
		#dump($options);
	} # check_plugins()
	
	
	#
	# update_plugins()
	#
	
	function update_plugins($update_plugins)
	{
		if ( !is_object($update_plugins) ) return $update_plugins;
		
		if ( ( $options = get_option('version_checker') ) === false )
		{
			version_checker::check_plugins();
			$options = get_option('version_checker');
		}
		
		if ( $update_plugins->response )
		{
			foreach ( (array) $update_plugins->checked as $plugin => $version )
			{
				if ( strpos($version, 'fork') !== false )
				{
					unset($update_plugins->response[$plugin]);
				}
			}
		}
		
		foreach ( array_keys((array) $options['plugins']['response']) as $file )
		{
			$extra = $options['plugins']['response'][$file];
			
			# disable this until we install amember
			unset($extra->package);
			
			$update_plugins->response[$file] = $extra;
		}
		
		return $update_plugins;
	} # update_plugins()
	
	
	#
	# do_check_plugins()
	#
	
	function do_check_plugins()
	{
		add_action('shutdown', 'wp_update_plugins');
		add_action('shutdown', array('version_checker', 'check_plugins'));
	} # do_check_plugins()
} # version_checker

version_checker::init();
?>