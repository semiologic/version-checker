<?php
@define('sem_api_key_debug', true);

class sem_api_key
{
	#
	# init()
	#

	function init()
	{
		add_action('admin_menu', array('sem_api_key', 'admin_menu'));
		
		add_filter('version_checker', array('sem_api_key', 'version_checker'));
		
		if ( get_option('sem_api_key') === false )
		{
			update_option('sem_api_key', '');
		}
	} # init()


	#
	# add_key()
	#

	function add_key($url, $return = null)
	{
		if ( $api_key = get_option('sem_api_key') )
		{
			return $url . ( strpos($url, '?') === false ? '?' : '&' ) . 'user_key=' . $api_key;
		}
		elseif ( isset($return) )
		{
			return $return;
		}
		else
		{
			return $url;
		}
	} # add_key()


	#
	# version_checker()
	#

	function version_checker($response)
	{
		if ( !current_user_can('administrator') ) return $response;
		
		$protected = apply_filters('sem_api_key_protected', array());
		
		foreach ( array_keys($response) as $file )
		{
			if ( in_array($response[$file]->url, $protected) )
			{
				$response[$file]->url = sem_api_key::add_key($response[$file]->url, '');
			}
			if ( in_array($response[$file]->package, $protected) )
			{
				$response[$file]->package = sem_api_key::add_key($response[$file]->package, '');
			}
		}

		return $response;
	} # version_checker()


	#
	# admin_menu()
	#

	function admin_menu()
	{
		if ( current_user_can('administrator') )
		{
			add_options_page(
				__('Semiologic API Key'),
				__('Semiologic API Key'),
				'administrator',
				__FILE__,
				array('sem_api_key', 'admin_page')
				);
		}
	} # admin_menu()


	#
	# admin_page()
	#

	function admin_page()
	{
		if ( $_POST['update_sem_api_key'] )
		{
			sem_api_key::update();

			echo "<div class=\"updated\">\n"
				. "<p>"
					. "<strong>"
					. __('Settings saved.')
					. "</strong>"
				. "</p>\n"
				. "</div>\n";
		}

		echo '<form method="post" action="">';

		if ( function_exists('wp_nonce_field') ) wp_nonce_field('sem_api_key');

		echo '<input type="hidden"'
			. ' name="update_sem_api_key"'
			. ' value="1"'
			. ' />';

		echo '<div class="wrap">'
			. '<h2>' . __('Semiologic Memberships') . '</h2>';

		echo '<table class="form-table">';
		
		$sem_api_key = get_option('sem_api_key');
		
		echo '<tr valign="top">'
		 	. '<th scrope="row">'
			. 'API Key'
			. '</th>'
			. '<td>'
			. '<input type="text"'
				. ' size="58" class="code"'
				. ' name="api_key"'
				. ' value="' . attribute_escape($sem_api_key) . '"'
				. ' />'
			. '</td>'
			. '</tr>';
		
		if ( $sem_api_key )
		{
			$sem_pro = false;
		
			echo '<tr valign="top">'
			 	. '<th scrope="row">'
				. '<a href="http://members.semiologic.com">Memberships</a>'
				. '</th>'
				. '<td>';
		
			if ( !$sem_api_key )
			{
				echo '<p>Please enter your API Key.</p>';
			}
			else
			{
				$url = "https://api.semiologic.com/memberships/0.1/$sem_api_key";
				
				$res = wp_remote_request($url);
				
				if ( sem_api_key_debug )
				{
					$res = array();
					
					$res['response']['code'] = 200;
					$res['body'] = '<?xml version="1.0" encoding="UTF-8" ?>
					<memberships>
					<membership>
					<name>Semiologic Pro</name>
					<key>sem_pro</key>
					<expires></expires>
					</membership>
					</memberships>
					';
				}
				
				if ( is_wp_error($res) )
				{
					echo '<div style="background-color: #ffebe8; border: solid 1px #c00; padding: 0px 10px;">' . "\n";
					
					echo '<p>The following errors occurred while trying to contact https://api.semiologic.com:</p>';
					
					echo '<ul style="margin-left: 1.8em; list-style: square;">';
					
					foreach ( $res->get_error_messages() as $msg )
					{
						echo '<li>' . $msg . '</li>';
					}
					
					echo '</ul>';
					
					echo '</div>' . "\n";
				}
				elseif ( $res['response']['code'] != 200 )
				{
					echo '<div style="background-color: #ffebe8; border: solid 1px #c00; padding: 0px 10px;">' . "\n";
					
					echo '<p>The following errors occurred while trying to contact https://api.semiologic.com:</p>';
					
					echo '<ul style="margin-left: 1.8em; list-style: square;">';
					
					$msg = $res['response']['code'] . ': ' . $res['response']['message'];
					echo '<li>' . $msg . '</li>';
					
					echo '</ul>';
					
					echo '</div>' . "\n";
				}
				else
				{
					$res = $res['body'];
					
					if ( preg_match_all("|
						<error>
							(.*)
						</error>
						|isUx", $res, $errors, PREG_SET_ORDER))
					{
						echo '<div style="background-color: #ffebe8; border: solid 1px #c00; padding: 0px 10px;">' . "\n";

						echo '<p>The following errors occurred while trying to contact https://api.semiologic.com:</p>';

						echo '<ul style="margin-left: 1.8em; list-style: square;">';

						foreach ( $errors as $msg )
						{
							$msg = $msg[1];
							$msg = strip_tags($msg);

							echo '<li>' . $msg . '</li>';
						}

						echo '</ul>';

						echo '</div>' . "\n";
					}
					else
					{
						preg_match_all("|
							<membership>\s*
							<name>(.*)</name>\s*
							<key>(.*)</key>\s*
							<expires>(.*)</expires>\s*
							</membership>
							|isUx", $res, $memberships, PREG_SET_ORDER);

						echo '<table width="100%" style="border: solid 1px #000; border-collapse: collapse;">'
							. '<tr>'
							. '<th style="border-bottom: solid 1px #000;">' . 'Membership' . '</th>'
							. '<th style="border-bottom: solid 1px #000;">' . 'Expires' . '</th>'
							. '</tr>';

						$date_format = get_option('date_format');

						foreach ( $memberships as $membership )
						{
							$name = $membership[1];
							$key = $membership[2];
							$expires = $membership[3];

							$name = strip_tags($name);
							$key = strip_tags($key);
							$expires = strip_tags($expires);

							if ( !$expires )
							{
								$expires = 'Never';
								$renew = '';
							}
							else
							{
								if ( strtotime($expires) >= time() )
								{
									$renew = '';
								}
								else
								{
									$renew = ' &rarr; <a href="http://members.semiologic.com">Renew</a>';
								}

								$expires = mysql2date($date_format, $expires);
							}

							if ( $key == 'sem_pro' )
							{
								$sem_pro = true;
							}

							echo '<tr>'
								. '<td>' . $name . '</td>'
								. '<td>' . $expires . $renew . '</td>'
								. '</tr>';
						}
					
					}
					
					echo '</table>';
				}
			}
		
			echo '</td>'
				. '</tr>';
			
			$package = get_option('sem_package');
			
			if ( $sem_pro )
			{
				if ( $package == 'wp' )
				{
					$package = 'stable';
					update_option('sem_package', $package);
				}
				
				echo '<tr valign="top">'
				 	. '<th scrope="row">'
					. 'Core Updates'
					. '</th>'
					. '<td>'
					. '<p>' . 'Under Tools / Upgrade, use the following package to update my site:' . '</p>'
					. '<ul>'
					. '<li>'
						. '<label>'
						. '<input type="radio" name="package"'
							. ' value="stable"'
							. ( $package == 'stable'
								? ' checked="checked"'
								: ''
								)
							. ' /> '
						. 'Semiologic Pro, Stable version'
						. '</label>'
						. '</li>'
					. '<li>'
						. '<label>'
						. '<input type="radio" name="package"'
							. ' value="bleeding"'
							. ( $package == 'bleeding'
								? ' checked="checked"'
								: ''
								)
							. ' /> '
						. 'Semiologic Pro, Bleeding Edge version'
						. '</label>'
						. '</li>'
					. '</ul>'
					. '</td>'
					. '</tr>';
			}
			else
			{
				if ( $package != 'wp' )
				{
					$package = 'wp';
					update_option('sem_package', $package);
				}
			}
		}
		
		$faq = <<<EOF

Entering your Semiologic API key is required to keep your site updated using packages located on semiologic.com. You'll find yours in the <a href="http://members.semiologic.com">Semiologic back-end</a>.

Automated updates using packages located on semiologic.com will cease to work when your membership expires. (The software itself will of course continue to work normally.)

Do not share your API key. It is a password in every respect. Please do not use it for the benefit of others either. If you or your organization aren't a site's primary user, that site should not be using your API key.

Please <a href="mailto:sales@semiologic.com">contact sales</a> for any further information.

EOF;

		$faq = wpautop($faq);

		echo '<tr>'
			. '<th scope="row">'
			. '<p>' . 'FAQ' . '</p>'
			. '</th>'
			. '<td>'
			. $faq
			. '</td>'
			. '</tr>';

		echo '</table>';

		echo '<div class="submit">';
		echo '<input type="submit" value="' . attribute_escape(__('Save Changes')) . '" />';
		echo '</div>';

?>


<?php
		echo '<div style="clear: both;"></div>';
		
		echo '</div>';

		echo '</form>';
	} # admin_page()


	#
	# update()
	#

	function update()
	{
		check_admin_referer('sem_api_key');

		$sem_api_key = $_POST['api_key'];

		if ( !preg_match("/^[0-9a-f]{32}$/", $sem_api_key) )
		{
			$sem_api_key = '';
		}
		
		$package = $_POST['package'];
		
		if ( !in_array($package, array('wp', 'stable', 'bleeding')) || !$sem_api_key )
		{
			$package = 'wp';
		}
		
		update_option('sem_api_key', $sem_api_key);
		update_option('sem_package', $package);
	} # update()
} # sem_api_key

sem_api_key::init();

?>