<?php

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
			. '<h2>' . __('Semiologic API Key') . '</h2>';

		echo '<table class="form-table">';
		
		echo '<tr valign="top">'
		 	. '<th scrope="row">'
			. 'API Key'
			. '</th>'
			. '<td>'
			. '<input type="text"'
				. ' size="58" class="code"'
				. ' name="api_key"'
				. ' value="' . attribute_escape(get_option('sem_api_key')) . '"'
				. ' />'
			. '</td>'
			. '</tr>';
		
		$faq = <<<EOF

			<p>You'll find your Semiologic API key and your membership details in the <a href="http://members.semiologic.com">Semiologic back end</a>.</p>
			
			<p>Unless otherwise specified, software from semiologic.com is licensed under the <a href="http://www.mesoconcepts.com/license/">Mesoconcepts License</a>. Its terms are liberal: You're granted unrestricted use of the software, <strong>provided you're using it for yourself</strong>.</p>

			<p>Paying customers get a limited duration membership that entitles them to free updates and value-added services, such as automated updates. These extra services work provided you enter your API key in the above field, and for as long as your membership is running. (The software itself will continue to work normally after when the membership expires.)</p>

			<p>Do not share your API key. It is a password in every respect.</p>
			
			<p>Moreover, please do not use it for the benefit of others. If you (or your organization) aren't a site's primary user, that site should not be using your API key (*). If you wish to power your customers' sites with Semiologic Pro, please <a href="mailto:sales@semiologic.com">sign up as a reseller</a>.</p>
			
			<p>(*) We reserve the right to lock an API key if we monitor suspicious usage patterns.</p>

EOF;

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
		
		update_option('sem_api_key', $sem_api_key);
	} # update()
} # sem_api_key

sem_api_key::init();

?>