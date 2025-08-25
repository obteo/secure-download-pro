<?php
if (!defined('ABSPATH')) exit;

function sdp_verify_recaptcha($token){
    $secret = trim((string) get_option('sdp_secret_key'));
    if ($secret === '') {
        // If secret key not set, skip verification (allow download)
        return true;
    }
    if (!$token) return false;

    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => $secret,
            'response' => $token
        ],
        'timeout' => 10
    ]);
    if (is_wp_error($response)) return false;
    $result = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($result)) return false;
    return !empty($result['success']) && (float)($result['score'] ?? 0) >= 0.3;
}
