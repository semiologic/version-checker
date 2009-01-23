<?php
/*
Plugin Name: Version Checker
Plugin URI: http://www.semiologic.com/software/wp-tweaks/version-checker/
Description: Allows to hook into WordPress' version checking API with in a distributed environment.
Version: 1.3 RC
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Update Package: https://members.semiologic.com/media/plugins/version-checker/version-checker.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/

if ( is_admin() )
{
	if ( version_compare($GLOBALS['wp_version'], '2.7', '>=') )
	{
		include dirname(__FILE__) . '/version_checker.php';
	}
	else
	{
		function version_checker_warning()
		{
			echo '<div class="error">'
				. '<p>' . 'The Version Checker plugin requires WP 2.7 or later.' . '</p>'
				. '</div>' . "\n";
		} # version_checker_warning()
		
		add_action('admin_notices', 'version_checker_warning');
	}
}
?>