<?php
/**
 * Email sending functions for marketing plugin.
 *
 * @package EmailMarketingPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Send test email using template
 *
 * @param string $email      Recipient email address.
 * @param int    $template_id Email template ID.
 * @return bool True on success, false on failure.
 */
function dg_em_send_test_email_with_template( $email, $template_id ) {
    if ( ! is_email( $email ) || ! $template_id ) {
        return false;
    }

    if ( class_exists( 'Yeemail_Builder_Frontend_Functions' ) ) {
        // Generate email content from template.
        $content = Yeemail_Builder_Frontend_Functions::creator_template([
            'id_template' => $template_id,
            'type'        => 'full',
        ]);

        // Generate your unsubscribe URL
        $unsubscribe_url = home_url( '/unsubscribe/?uid=' . email_encrypt_code($email) ); 
        // (adjust according to how you handle unsubscribe)

        // Build the replacement link
        $unsubscribe_link = '<a href="' . esc_url( $unsubscribe_url ) . '" target="_blank" style="color: #fff;font-weight: bold;">Unsubscribe</a>';

        // Replace [Unsubscribe] with the actual link
        $content = str_replace('[Unsubscribe]', $unsubscribe_link, $content);


        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $subject = get_the_title($template_id) ?: 'Your monthly insights from [Duke Godley]';
        
        return wp_mail( $email, $subject, $content, $headers );
    }
    return false;
}

/**
 * Process next scheduled email in queue
 */
function dg_em_process_next_scheduled_email() {
    
    // Query for next scheduled email.
    $args = [
        'post_type'      => 'dg_em_email_status',
        'post_status'    => 'scheduled',  // Only process scheduled emails
        'posts_per_page' => 1,
        'orderby'        => 'ID',
        'order'          => 'DESC',
    ];
    
    $posts = get_posts($args);

    // error_log( 'Send email processing-1' );

    if (empty($posts)){ 
        
        // error_log( 'Send email processing-2' );
        return; // Do not unschedule, just exit
    }

    $post = $posts[0];
    $email = get_post_meta($post->ID, '_dg_em_customer_email', true);
    $template_id = get_post_meta($post->ID, '_dg_em_template_id', true);
    
    // Update status to processing.
    wp_update_post([
        'ID'          => $post->ID,
        'post_status' => 'processing',  // Temporarily mark as 'processing'
    ]);

    try {
        // Attempt to send email.
        $sent = dg_em_send_test_email_with_template($email, $template_id);
        
        // error_log( 'Send email processing-3' );
        if ($sent) {
            $log = "Sent successfully at " . gmdate("d-m-Y h:i:s");
            $status = 'sent';  // Change status to 'sent' after email is successfully sent
        } else {
            $log = "Failed to send email at " . gmdate("d-m-Y h:i:s") . ". Possible reasons: SMTP issue, invalid template, or email blocked.";
            $status = 'failed';  // Mark as 'failed' if email is not sent
        }
        
        // Update log and status.
        wp_update_post([
            'ID'           => $post->ID,
            'post_content' => $log,
            'post_status'  => $status,
        ]);
        
    } catch (Exception $e) {
        $log = "CRON FAILED: " . $e->getMessage() . " at " . gmdate("d-m-Y h:i:s");
        wp_update_post([
            'ID'           => $post->ID,
            'post_content' => $log,
            'post_status'  => 'failed',  
        ]);
    }
}

/**
 * Encrypt code using OpenSSL
 *
 * @param string $code The code to encrypt.
 */
function email_encrypt_code( $code ) {
    if ( empty( $code ) ) {
        return '';
    }
    
    $default_secure_auth_key = email_decrypt_key();
	$key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : $default_secure_auth_key;
	// $encryption_key = base64_decode( $key );
	$iv = substr( openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) ), 0, 16 );
	$encrypted = openssl_encrypt( $code, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

	// Append the $iv variable to use for decrypting later.
	return base64_encode( $encrypted . '::' . $iv );
}

/**
 * Decrypt code using OpenSSL
 *
 * @param string $code The code to decrypt.
 */
function email_decrypt_code( $code ) {

    $default_secure_auth_key = email_decrypt_key();  
    $key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : $default_secure_auth_key;
    // $encryption_key = base64_decode( $key );

	// Grab the $iv from earlier, to decrypt.
	list( $encrypted_data, $iv ) = explode( '::', base64_decode( $code ), 2 );

	return openssl_decrypt( $encrypted_data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
}

/**
 * Decrypt key
 */
function email_decrypt_key(){

	return "bo7DP=/O+;n2^0uiwZk>=HkwQ#AEa3k/& wpgVKT70~Qp#Eb;Z.Klu@<E{Sd*X+')";
}

add_shortcode( 'unsubscribe_user', 'unsubscribe_user_shortcode' );
/**
 * Shortcode - Unsubscribe user.
 */
function unsubscribe_user_shortcode(){

	$uid = !empty( $_GET['uid'] ) ? (string) $_GET['uid'] : '';

    if( !empty( $uid ) ){

		$email_id = email_decrypt_code( $uid );

		// args
		$args = array(
			'posts_per_page'    => -1,
			'post_type'     => 'marketing_customers',
			'meta_query'    => array(
				'relation'      => 'AND',
				array(
					'key'       => '_dg_em_customer_email',
					'value'     => $email_id,
					'compare'   => '='
				),
			)
		);

		// query
		$the_query = new WP_Query( $args );

		if( $the_query->have_posts() ):

			while ( $the_query->have_posts() ) : $the_query->the_post(); 

				update_field( 'is_it_subscribed', 'no', get_the_ID() );

			endwhile;
			
		endif;
		
		wp_reset_query();
	}
}

add_shortcode( 'redirect_to_third_party', 'redirect_third_party_link' );
/**
 * Shortcode - Redirect third-party link.
 */
function redirect_third_party_link(){

    $redirect = !empty( $_GET['redirect'] ) ? $_GET['redirect'] : wp_get_referer();
    $back = !empty( $_GET['back'] ) ? $_GET['back'] : wp_get_referer();

    return '<div class="wavo-button" style="text-align: center;"><a href="'.$redirect.'" class="wavo-btn btn-curve btn-wit"><span class="button_text">Continue with external website</span></a> <a href="'.$back.'" class="wavo-btn btn-curve btn-wit"><span class="button_text">Go Back</span></a></div>';
 }