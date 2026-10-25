<?php
/**
 * Borra los ajustes y el estado guardado al desinstalar el plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mw_progreso_settings' );
delete_option( 'mw_progreso_state' );
wp_clear_scheduled_hook( 'mw_progreso_sync' );
wp_clear_scheduled_hook( 'mw_progreso_sync_soon' );
