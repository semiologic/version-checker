<?php
/**
 * sem_update_core
 *
 * @package Version Checker
 **/

add_action('admin_notices', array('sem_update_core', 'ob_start'), 1000);

class sem_update_core {
	/**
	 * ob_start()
	 *
	 * @return void
	 **/

	function ob_start() {
		global $action;
		
		if ( $_POST || $action != 'upgrade-core' )
			return;
		
		$update_core = get_transient('update_core');
		if ( !$update_core || empty($update_core->response) || empty($update_core->response->package) )
			return;
		
		ob_start(array('sem_update_core', 'ob_callback'));
		add_action('in_footer', array('sem_update_core', 'ob_flush'), -1000);
	} # ob_start()
	
	
	/**
	 * ob_flush()
	 *
	 * @return void
	 **/

	function ob_flush() {
		ob_end_flush();
	} # ob_flush()
	
	
	/**
	 * ob_callback()
	 *
	 * @param string $buffer
	 * @return string $buffer
	 **/

	function ob_callback($buffer) {
		$find_replace = array(
			__('Upgrade WordPress', 'version-checker') => __('Upgrade Semiologic Pro', 'version-checker'),
			__('You are using a development version of WordPress.', 'version-checker') => __('You are using a development version of Semiologic Pro.', 'version-checker'),
			__('There is a new version of WordPress available for upgrade', 'version_checker') => __('There is a new version of Semiologic Pro available for upgrade', 'version_checker'),
			__('You have the latest version of WordPress.', 'version-checker') => __('You have the latest version of Semiologic Pro.', 'version-checker')
			);
		return str_replace(array_keys($find_replace), array_values($find_replace), $buffer);
	} # ob_callback()
} # sem_update_core
?>