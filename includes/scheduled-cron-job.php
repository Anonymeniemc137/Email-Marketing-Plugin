<?php
/**
 * Bulk email admin interface.
 *
 * @package EmailMarketingPlugin
 */
 
defined( 'ABSPATH' ) || exit;

// Hook for your scheduled event
add_action( 'dg_em_process_scheduled_emails', 'dg_em_process_next_scheduled_email' );

/**
 * Clear cron.
 */
function dg_em_clear_cron() {
    // error_log( 'Clear cron -1' );
    wp_clear_scheduled_hook( 'dg_em_process_scheduled_emails' );
}


/**
 * Schedule email processing cron job.
 */
function dg_em_schedule_cron() {
    if ( ! wp_next_scheduled( 'dg_em_process_scheduled_emails' ) ) {
        // error_log( 'Scheduled cron -1' );
        wp_schedule_event( time(), 'every_minute', 'dg_em_process_scheduled_emails' );
    }
}

add_filter( 'cron_schedules', 'dg_em_add_cron_interval' );
/**
 * Add custom cron interval.
 */
function dg_em_add_cron_interval( $schedules ) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => esc_html__( 'Every Minute', 'email-marketing' ),
    ];
    return $schedules;
}