<?php
/*
Plugin Name: Secure Download PRO
Description: Protect multiple files in /wp-content/uploads with reCAPTCHA v3, IP-based limits, and an admin panel (Add/Edit/Delete + Reset logs). Uses a full-width lime-green responsive button.
Version: 4.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

define('SDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SDP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SDP_PLUGIN_DIR . 'includes/db-functions.php';
require_once SDP_PLUGIN_DIR . 'includes/captcha-verify.php';
require_once SDP_PLUGIN_DIR . 'includes/download-handler.php';
require_once SDP_PLUGIN_DIR . 'admin/settings-page.php';

register_activation_hook(__FILE__, 'sdp_create_db');

// Frontend assets
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('sdp-style', SDP_PLUGIN_URL . 'assets/style.css', [], '1.0');
    // Font Awesome 4 (user requested)
    wp_enqueue_style('sdp-fa4', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', [], '4.7.0');
    // reCAPTCHA v3
    $site_key = get_option('sdp_site_key');
    if ($site_key) {
        wp_enqueue_script('sdp-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, [], null, true);
        wp_add_inline_script('sdp-recaptcha', "grecaptcha.ready(function(){grecaptcha.execute('".$site_key."', {action: 'download'}).then(function(token){document.querySelectorAll('form.sdp-form').forEach(function(f){var i=document.createElement('input');i.type='hidden';i.name='g-recaptcha-response';i.value=token;f.appendChild(i);});});});");
    }
});

// Handle form submit
add_action('init', 'sdp_handle_form');

// Shortcode
add_shortcode('secure_download', function($atts){
    $atts = shortcode_atts(['id' => 0], $atts, 'secure_download');
    global $wpdb;
    $table = $wpdb->prefix . 'sdp_files';
    $file  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", intval($atts['id'])));
    if (!$file) return '<p>⚠️ File not found.</p>';
    $nonce = wp_create_nonce('sdp_download_' . $file->id);
    ob_start(); ?>
    <form method="post" class="sdp-form" style="max-width:100%;margin:20px 0;">
        <input type="hidden" name="sdp_file_id" value="<?php echo esc_attr($file->id); ?>">
        <input type="hidden" name="sdp_nonce" value="<?php echo esc_attr($nonce); ?>">
        <button type="submit" class="download-btn">
            <i class="fa fa-download" aria-hidden="true"></i>
            <span>Download <?php echo esc_html($file->file_name); ?></span>
        </button>
    </form>
    <?php return ob_get_clean();
});
