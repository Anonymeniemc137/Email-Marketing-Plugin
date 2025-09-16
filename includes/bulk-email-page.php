<?php
/**
 * Bulk email admin interface.
 *
 * @package EmailMarketingPlugin
 */

defined( 'ABSPATH' ) || exit;

// Enqueue admin assets.
add_action( 'admin_enqueue_scripts', 'dg_em_enqueue_bulk_email_assets' );
/**
 * Enqueue bulk email assets.
 *
 * @param string $hook Current admin page.
 */
function dg_em_enqueue_bulk_email_assets( $hook ) {
    if ( 'email-marketing_page_dg_em_send_bulk_email' !== $hook ) {
        return;
    }
    
    wp_enqueue_style(
        'dg-em-select2-css',
        DG_EMAIL_MARKETING_URL . 'assets/css/select2.min.css',
        array(),
        '4.1.0'
    );

    wp_enqueue_script(
        'dg-em-select2-js',
        DG_EMAIL_MARKETING_URL . 'assets/js/select2.min.js',
        array( 'jquery' ),
        '4.1.0',
        true
    );

    wp_enqueue_script( 
        'dg-em-bulk-email-send', 
        DG_EMAIL_MARKETING_URL . 'assets/js/bulk-email-send.js', 
        array('jquery'), 
        '1.0', 
        true 
    );
}

// Handle form submission before rendering the page
add_action( 'admin_init', 'dg_em_process_bulk_email_submission' );

/**
 * Process bulk email form submission.
 */
function dg_em_process_bulk_email_submission() {
    // Verify nonce.
    if ( ! isset( $_POST['dg_em_bulk_email_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['dg_em_bulk_email_nonce'] ), 'dg_em_schedule_bulk_email' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

     // Validate required fields.
    if ( ! isset( $_POST['dg_em_send_email'], $_POST['dg_em_template'], $_POST['dg_em_send_type'] ) ) {
        return;
    }

    // Sanitize and process inputs
    global $wpdb;

    // Check if template is set and sanitize it
    $template_id = isset( $_POST['dg_em_template'] ) ? absint( $_POST['dg_em_template'] ) : 0;
    if ( !$template_id ) {
        return;
    }

    $send_type = isset( $_POST['dg_em_send_type'] ) ? sanitize_text_field( wp_unslash( $_POST['dg_em_send_type'] ) ) : '';
    $emails = [];

    // Get recipient emails.
    if ( 'all' === $send_type ) {
        $customers = get_posts( array(
            'post_type'      => 'marketing_customers',
            'post_status'    => ['private', 'publish'], // Include both
            'posts_per_page' => -1,
        ) );

        foreach ( $customers as $customer ) {
            $email = get_post_meta($customer->ID, '_dg_em_customer_email', true);
            if ($email) {
                $emails[] = $email;
            }
        }
    } elseif ( 'specific' === $send_type && ! empty( $_POST['dg_em_users'] ) ) {
        foreach ( (array) wp_unslash( $_POST['dg_em_users'] ) as $email ) {
            $emails[] = sanitize_email( $email );
        }
    }

    // Create email status entries.
    foreach ($emails as $email) {
        $post_id = wp_insert_post([
            'post_type'    => 'dg_em_email_status',
            'post_title'   => 'Email to ' . $email,
            'post_status'  => 'scheduled',
            'post_content' => 'Scheduled via bulk sender',
            'meta_input'   => [
                '_dg_em_customer_email' => $email,
                '_dg_em_template_id'   => $template_id,
            ],
        ]);
    }

    wp_safe_redirect(admin_url('admin.php?page=dg_em_status_page'));
    exit;
}

/**
 * Render bulk email interface.
 */
function dg_em_send_bulk_email_callback() {
    // Get templates and customers.
    $templates = get_posts(
        array(
            'post_type'      => 'yeemail_template',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        )
    );

    $customers = get_posts(
        array(
            'post_type'      => 'marketing_customers',
            'post_status'    => ['private', 'publish'], // Include both
            'posts_per_page' => -1,
            'meta_query'    => array(
				'relation'      => 'AND',
				array(
					'key'       => 'is_it_subscribed',
					'value'     => 'yes',
					'compare'   => '='
				),
			)
        )
    );

    // Check if form is submitted
    if ( isset( $_POST['dg_em_send_email'] ) ) {
        // Verify nonce
        check_admin_referer( 'dg_em_send_bulk_email_action', 'dg_em_send_bulk_email_nonce' );

        // Ensure template and send-to fields are set
        $template_id   = isset( $_POST['dg_em_template'] ) ? absint( $_POST['dg_em_template'] ) : 0;
        $send_to       = isset( $_POST['dg_em_send_to'] ) ? sanitize_text_field( wp_unslash( $_POST['dg_em_send_to'] ) ) : '';
        $customer_ids  = array();

        // Process based on send-to option
        if ( 'all' === $send_to ) {
            foreach ( $customers as $customer ) {
                $customer_ids[] = $customer->ID;
            }
        } elseif ( 'specific' === $send_to && isset( $_POST['dg_em_customers'] ) ) {
            $customer_ids = array_map( 'absint', $_POST['dg_em_customers'] );
        }

        global $wpdb;
        $now        = gmdate("d-m-Y h:i:s");

        foreach ( $customer_ids as $cid ) {
            $email = get_post_meta($cid, '_dg_em_customer_email', true);

            if ( is_email( $email ) ) {
                $post_id = wp_insert_post([
                    'post_type'    => 'dg_em_email_status',
                    'post_title'   => 'Email to ' . $email,
                    'post_status'  => 'scheduled',
                    'post_content' => 'Scheduled via bulk sender',
                    'meta_input'   => [
                        '_dg_em_customer_email' => $email,
                        '_dg_em_template_id'   => $template_id,
                    ],
                ]);
            }
        }

        echo '<div class="notice notice-success"><p>Email(s) processed.</p></div>';
    }
    ?>

    <div class="wrap">
        <h2>Send Bulk Email</h2>
        <form method="post">
            <?php wp_nonce_field( 'dg_em_send_bulk_email_action', 'dg_em_send_bulk_email_nonce' ); ?>

            <p>
                <label for="dg_em_template">Choose Email Template:</label>
                <select name="dg_em_template" id="dg_em_template" required>
                    <?php foreach ( $templates as $template ) : ?>
                        <option value="<?php echo esc_attr( $template->ID ); ?>">
                            <?php echo esc_html( $template->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label>Send To:</label><br>
                <label><input type="radio" name="dg_em_send_to" value="all" checked> All Customers</label><br>
                <label><input type="radio" name="dg_em_send_to" value="specific"> Specific Customers</label>
            </p>

            <div id="specific-users-container" style="display: none;">
                <label for="dg_em_customers">Select Customers:</label>
                <select name="dg_em_customers[]" id="dg_em_customers" multiple class="regular-text">
                    <?php foreach ( $customers as $customer ) :  ?>
                        <?php $email = get_post_meta($customer->ID, '_dg_em_customer_email', true); ?>
                        <?php if ( is_email( $email ) ) : ?>
                            <option value="<?php echo esc_attr( $customer->ID ); ?>">
                                <?php echo esc_html( $customer->post_title . ' (' . $email . ')' ); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <p><input type="submit" name="dg_em_send_email" value="Send Email" class="button button-primary"></p>
        </form>
    </div>

    <script>
        (function($){
            $(document).ready(function(){
                // Initialize Select2.
                $('#dg_em_customers').select2();

                // Toggle customer selection.
                function toggleCustomerSelect() {
                    const selected = $('input[name="dg_em_send_to"]:checked').val();
                    if (selected === 'specific') {
                        $('#specific-users-container').show();
                        $('#dg_em_customers').select2(); // Initialize only when visible
                    } else {
                        $('#specific-users-container').hide();
                        $('#dg_em_customers').select2('destroy'); // Optional cleanup
                    }
                }

                toggleCustomerSelect();
                $('input[name="dg_em_send_to"]').on('change', toggleCustomerSelect);
            });
        })(jQuery);
    </script>
<?php
}