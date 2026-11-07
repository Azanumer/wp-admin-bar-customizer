<?php
// Delete the plugin's option on uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wabc_settings' );
