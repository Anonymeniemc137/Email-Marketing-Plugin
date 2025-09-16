<?php
/**
 * Plugin Name: Email Marketing
 * Description: Manage email subscribers and send marketing emails.
 * Version: 1.2
 * Author: Mihir Dave
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'DG_EMAIL_MARKETING_PATH', plugin_dir_path( __FILE__ ) );
define( 'DG_EMAIL_MARKETING_URL', plugin_dir_url( __FILE__ ) );

// Include plugin files.
require DG_EMAIL_MARKETING_PATH . 'includes/shortcode-form.php';
require DG_EMAIL_MARKETING_PATH . 'includes/bulk-email-page.php';
require DG_EMAIL_MARKETING_PATH . 'includes/manage-status.php';
require DG_EMAIL_MARKETING_PATH . 'includes/send-email-functions.php';
require DG_EMAIL_MARKETING_PATH . 'includes/scheduled-cron-job.php';

// Activation/deactivation hooks.
register_activation_hook( __FILE__, 'dg_em_schedule_cron' );
register_deactivation_hook( __FILE__, 'dg_em_clear_cron' );

/**
 * Register custom post types.
 */
add_action( 'init', 'dg_em_register_post_types' );
function dg_em_register_post_types() {
    
    // Marketing Customers CPT.
    $labels = array(
		'name'                  => _x( 'Customers', 'Post type general name', 'Duke' ),
		'singular_name'         => _x( 'Customers', 'Post type singular name', 'Duke' ),
		'menu_name'             => _x( 'Customers', 'Admin Menu text', 'Duke' ),
		'name_admin_bar'        => _x( 'Customer', 'Add New on Toolbar', 'Duke' ),
		'add_new'               => __( 'Add New', 'Duke' ),
		'add_new_item'          => __( 'Add New Customer', 'Duke' ),
		'new_item'              => __( 'New Customer', 'Duke' ),
		'edit_item'             => __( 'Edit Customer', 'Duke' ),
		'view_item'             => __( 'View Customer', 'Duke' ),
		'all_items'             => __( 'All Customers', 'Duke' ),
		'search_items'          => __( 'Search Customers', 'Duke' ),
		'parent_item_colon'     => __( 'Parent Customers:', 'Duke' ),
		'not_found'             => __( 'No customers found.', 'Duke' ),
		'not_found_in_trash'    => __( 'No customers found in Trash.', 'Duke' ),
		'featured_image'        => _x( 'Customers Cover Image', 'Overrides the Featured Image phrase for this post type. Added in 4.3', 'Duke' ),
		'set_featured_image'    => _x( 'Set cover image', 'Overrides the Set featured image phrase for this post type. Added in 4.3', 'Duke' ),
		'remove_featured_image' => _x( 'Remove cover image', 'Overrides the Remove featured image phrase for this post type. Added in 4.3', 'Duke' ),
		'use_featured_image'    => _x( 'Use as cover image', 'Overrides the Use as featured image phrase for this post type. Added in 4.3', 'Duke' ),
		'archives'              => _x( 'Customer archives', 'The post type archive label used in nav menus. Default Post Archives. Added in 4.4', 'Duke' ),
		'insert_into_item'      => _x( 'Insert into Customer', 'Overrides the Insert into post/Insert into page phrase (used when inserting media into a post). Added in 4.4', 'Duke' ),
		'uploaded_to_this_item' => _x( 'Uploaded to this Customer', 'Overrides the Uploaded to this post/Uploaded to this page phrase (used when viewing media attached to a post). Added in 4.4', 'Duke' ),
		'filter_items_list'     => _x( 'Filter books list', 'Screen reader text for the filter links heading on the post type listing screen. Default Filter posts list/Filter pages list. Added in 4.4', 'Duke' ),
		'items_list_navigation' => _x( 'Books list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default Posts list navigation/Pages list navigation. Added in 4.4', 'Duke' ),
		'items_list'            => _x( 'Books list', 'Screen reader text for the items list heading on the post type listing screen. Default Posts list/Pages list. Added in 4.4', 'Duke' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,
		'publicly_queryable' => false,
		'show_ui'            => true,
		'show_in_menu'       => 'dg_em_menu',
		'query_var'          => false,
		'rewrite'            => array( 'slug' => 'marketing_customers' ),
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title' ),
	);

	register_post_type( 'marketing_customers', $args );
    
    // Email Status CPT.
    register_post_type(
        'dg_em_email_status',
        [
            'labels'             => [
                'name'               => 'Email Status',
                'singular_name'      => 'Email Status',
                'all_items'          => 'All Email Statuses',
                'menu_name'          => 'Email Statuses',
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'email-marketing',
            'supports'           => [ 'title', 'editor' ],
            'show_in_rest'       => true,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'rewrite'            => false,
            'publicly_queryable' => false,
        ]
    );
    
    // Custom statuses.
    $statuses = [
        'scheduled' => 'Scheduled',
        'failed'    => 'Failed',
        'sent'      => 'Sent',
        'processing'=> 'Processing',
    ];
    
    foreach ( $statuses as $status => $label ) {
        register_post_status(
            $status,
            [
                'label'                     => $label,
                'public'                    => true,
                'internal'                  => false,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 
                    "{$label} <span class=\"count\">(%s)</span>", 
                    "{$label} <span class=\"count\">(%s)</span>", 
                    'email-marketing' 
                ),
            ]
        );
    }
}

/**
 * Add email column to customers list.
 */
add_filter( 'manage_marketing_customers_posts_columns', 'dg_em_add_email_column' );
function dg_em_add_email_column( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        $new_columns[ $key ] = $title;
        if ( 'title' === $key ) {
            $new_columns['customer_email'] = 'Email';
            $new_columns['is_it_subscribed'] = 'Is it subscribed?';
        }
    }
    return $new_columns;
}

/**
 * Display email in customers list.
 */
add_action( 'manage_marketing_customers_posts_custom_column', 'dg_em_show_email_column', 10, 2 );
function dg_em_show_email_column( $column, $post_id ) {
    if ( 'customer_email' === $column ) {
        echo esc_html( get_post_meta( $post_id, '_dg_em_customer_email', true ) );
    }
    if ( 'is_it_subscribed' === $column ) {
        echo esc_html( get_post_meta( $post_id, 'is_it_subscribed', true ) );
    }
}

/**
 * Add admin menu items.
 */
add_action( 'admin_menu', 'dg_em_add_email_marketing_menu' );
function dg_em_add_email_marketing_menu() {
    
    add_menu_page(
        'Email Marketing',
        'Email Marketing',
        'manage_options',
        'dg_em_menu',
        '',
        'dashicons-email-alt'
    );

    add_submenu_page(
        'dg_em_menu',
        'Send Bulk Email',
        'Send Bulk Email',
        'manage_options',
        'dg_em_send_bulk_email',
        'dg_em_send_bulk_email_callback'
    );

    add_submenu_page( 
        'dg_em_menu', 
        'Status', 
        'Status', 
        'manage_options', 
        'dg_em_status_page', 
        'dg_em_status_page_callback' 
    );
}

/* Force fully make private  */
function force_private_cpt( $post_id, $post, $update ) {
    // Only run for your CPT
    if ( $post->post_type === 'marketing_customers' ) {

        // Skip if post is being trashed or deleted
        if ( in_array( $post->post_status, [ 'trash' ], true ) ) {
            return;
        }

        // Prevent infinite loop only for this CPT
        remove_action( 'save_post', 'force_private_cpt', 10 );

        // Force private if not already
        if ( $post->post_status !== 'private' ) {
            wp_update_post( array(
                'ID'          => $post_id,
                'post_status' => 'private',
            ));
        }

        // Re-attach only for this CPT
        add_action( 'save_post', 'force_private_cpt', 10, 3 );
    }
}
add_action( 'save_post', 'force_private_cpt', 10, 3 );
