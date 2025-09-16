<?php
/**
 * Shortcode and form handling for email marketing.
 *
 * @package EmailMarketingPlugin
 */

defined( 'ABSPATH' ) || exit;

// Register shortcode.
add_shortcode( 'email_marketing_form', 'dg_em_render_email_form' );

/**
 * Render email marketing form.
 *
 * @return string Form HTML.
 */
function dg_em_render_email_form() {
    ob_start();
    ?>
    <div class="contact email-marketing-container">
        <!-- Loading overlay -->
        <div id="dg-em-overlay" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.7);z-index:999;">
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
                <img src="<?php echo esc_url( includes_url( 'images/spinner-2x.gif' ) ); ?>" alt="Loading">
            </div>
        </div>
        
        <!-- Email signup form -->
        <form method="post" class="form" id="dg-em-form">
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <input type="text" name="dg_em_first_name" placeholder="First Name" required>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <input type="email" name="dg_em_email" placeholder="Email" required>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group checkbox-group" style="margin-bottom: 15px;">
                        <label>
                            <input type="checkbox" name="dg_em_consent" required>
                            <div>By submitting your contact details, you consent to being contacted by us for information about our services. <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" target="_blank">Privacy Policy & Data Protection.</a></div>
                        </label>
                    </div>
                </div>

                <div class="col-3">
                    <input type="submit" class="btn-curve btn-blc" value="Send Message">
                </div>
            </div>

            <!-- Security nonce -->
            <input type="hidden" name="dg_em_nonce" value="<?php echo esc_attr( wp_create_nonce( 'dg_em_nonce_action' ) ); ?>">
            <div id="dg-em-response"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Enqueue frontend scripts.
add_action( 'wp_enqueue_scripts', 'dg_em_enqueue_scripts' );

/**
 * Enqueue frontend scripts.
 */
function dg_em_enqueue_scripts() {
    wp_enqueue_script(
        'dg-em-front-general',
        plugin_dir_url( __FILE__ ) . '../assets/js/front-functions.js',
        [ 'jquery' ],
        '1.0',
        true
    );

    // Localize AJAX URL.
    wp_localize_script(
        'dg-em-front-general',
        'dg_em_ajax_obj',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ]
    );
}

// Handle form submission.
add_action( 'wp_ajax_dg_em_submit_form', 'dg_em_handle_form_submission' );
add_action( 'wp_ajax_nopriv_dg_em_submit_form', 'dg_em_handle_form_submission' );

/**
 * Handle form submission.
 */
function dg_em_handle_form_submission() {
    // Verify nonce.
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'dg_em_nonce_action' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }

    // Validate email.
    if ( ! isset( $_POST['dg_em_email'] ) || empty( $_POST['dg_em_email'] ) ) {
        wp_send_json_error( [ 'message' => 'Email address is missing or empty.' ] );
    }
    $email = sanitize_email( wp_unslash( $_POST['dg_em_email'] ) );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Invalid email address.' ] );
    }

    // Validate name.
    if ( ! isset( $_POST['dg_em_first_name'] ) || empty( $_POST['dg_em_first_name'] ) ) {
        wp_send_json_error( [ 'message' => 'First name is missing or empty.' ] );
    }
    $name = sanitize_text_field( wp_unslash( $_POST['dg_em_first_name'] ) );

    // Validate consent.
    if ( empty( $_POST['dg_em_consent'] ) ) {
        wp_send_json_error( [ 'message' => 'Please accept privacy policy.' ] );
    }
    
    // Check for existing subscriber.
    $existing = new WP_Query(
        [
            'post_type'      => 'marketing_customers',
            'post_status'    => [ 'private', 'publish' ],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => '_dg_em_customer_email',
                    'value'   => $email,
                    'compare' => '=',
                ],
            ],
        ]
    );

    if ( $existing->have_posts() ) {
        wp_send_json_error( [ 'message' => 'Thank you! You are already subscribed.' ] );
    }

    // Create new subscriber.
    $post_id = wp_insert_post(
        [
            'post_type'    => 'marketing_customers',
            'post_title'   => $name,
            'post_content' => '',
            'post_status'  => 'private',
        ]
    );

    if ( ! $post_id || is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Error creating record.' ] );
    }

    // Save metadata.
    add_post_meta( $post_id, '_dg_em_customer_email', $email );
    add_post_meta( $post_id, '_dg_em_customer_consent', 'yes' );
    add_post_meta( $post_id, 'is_it_subscribed', 'yes' );

    // Return success.
    wp_send_json_success( [ 'message' => 'Thank you for subscribing!' ] );
}