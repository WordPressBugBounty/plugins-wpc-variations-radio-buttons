<?php
defined( 'ABSPATH' ) || exit;

register_activation_hook( defined( 'WOOVR_LITE' ) ? WOOVR_LITE : WOOVR_FILE, 'woovr_activate' );
register_deactivation_hook( defined( 'WOOVR_LITE' ) ? WOOVR_LITE : WOOVR_FILE, 'woovr_deactivate' );
add_action( 'admin_init', 'woovr_check_version' );

function woovr_check_version() {
	if ( ! empty( get_option( 'woovr_version' ) ) && ( get_option( 'woovr_version' ) < WOOVR_VERSION ) ) {
		wpc_log( 'woovr', 'upgraded' );
		update_option( 'woovr_version', WOOVR_VERSION, false );
	}
}

function woovr_activate() {
	wpc_log( 'woovr', 'installed' );
	update_option( 'woovr_version', WOOVR_VERSION, false );
}

function woovr_deactivate() {
	wpc_log( 'woovr', 'deactivated' );
}

if ( ! function_exists( 'wpc_log' ) ) {
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action
		];

		update_option( 'wpc_logs', $logs, false );
	}
}