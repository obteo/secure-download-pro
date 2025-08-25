<?php
if (!defined('ABSPATH')) exit;

function sdp_resolve_uploads_path($raw_path){
    $raw_path = trim((string)$raw_path);
    if ($raw_path === '') return '';

    // If it's a full URL, return as-is (handled by redirect).
    if (preg_match('#^https?://#i', $raw_path)) {
        return $raw_path;
    }

    // WordPress uploads base
    $uploads = wp_get_upload_dir();
    $basedir = rtrim($uploads['basedir'], '/'); // e.g., /home/.../wp-content/uploads
    $baseurl = rtrim($uploads['baseurl'], '/');

    // If it's already under basedir (absolute path), keep it
    if (strpos($raw_path, $basedir) === 0) {
        return $raw_path;
    }

    // If it starts with /wp-content/uploads, map to basedir
    if (strpos($raw_path, '/wp-content/uploads') === 0) {
        $rel = substr($raw_path, strlen('/wp-content/uploads'));
        return $basedir . $rel;
    }

    // If it starts with /, treat as relative to document root
    if (strpos($raw_path, '/') === 0) {
        // Try to map to uploads by removing everything before /wp-content/uploads
        if (strpos($raw_path, '/wp-content/uploads/') === 0) {
            $rel = substr($raw_path, strlen('/wp-content/uploads/'));
            return $basedir . '/' . ltrim($rel, '/');
        }
        // Fallback: try document root
        $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($docroot) {
            return $docroot . $raw_path;
        }
    }

    // Otherwise treat as relative to uploads (recommended)
    return $basedir . '/' . ltrim($raw_path, '/');
}

function sdp_handle_form(){
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!isset($_POST['sdp_file_id'])) return;

    // Nonce
    $file_id = intval($_POST['sdp_file_id']);
    $nonce   = isset($_POST['sdp_nonce']) ? sanitize_text_field($_POST['sdp_nonce']) : '';
    if (!wp_verify_nonce($nonce, 'sdp_download_' . $file_id)) {
        wp_die('Security check failed (invalid nonce).');
    }

    global $wpdb;
    $table_files = $wpdb->prefix . 'sdp_files';
    $table_logs  = $wpdb->prefix . 'sdp_logs';

    $file = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_files WHERE id=%d", $file_id));
    if (!$file) wp_die('⚠️ File not found in database.');

    // reCAPTCHA verify (if configured)
    $token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
    if (!sdp_verify_recaptcha($token)) {
        wp_die('⚠️ reCAPTCHA verification failed.');
    }

    // IP limit check
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $limit = intval(get_option('sdp_ip_limit', 3));
    $cnt   = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_logs WHERE ip=%s AND created_at >= (NOW() - INTERVAL 1 DAY)",
        $ip
    )));
    if ($cnt >= $limit) {
        wp_die('⚠️ Daily download limit reached for your IP.');
    }

    $raw_path = $file->file_path;
    $resolved = sdp_resolve_uploads_path($raw_path);

    // If it's an URL, redirect
    if (preg_match('#^https?://#i', $resolved)) {
        // Log before redirect
        $wpdb->insert($table_logs, ['file_id' => $file_id, 'ip' => $ip]);
        wp_redirect($resolved);
        exit;
    }

    if (!$resolved || !file_exists($resolved)) {
        // For admins, include resolved path hint
        if (current_user_can('manage_options')) {
            wp_die('⚠️ File missing on server. Resolved path: ' . esc_html($resolved));
        }
        wp_die('⚠️ File missing on server.');
    }

    // Log download
    $wpdb->insert($table_logs, ['file_id' => $file_id, 'ip' => $ip]);

    // Clean output buffers
    while (ob_get_level()) { ob_end_clean(); }

    // Send file
    $mime = function_exists('mime_content_type') ? mime_content_type($resolved) : 'application/octet-stream';
    if (!$mime) $mime = 'application/octet-stream';

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($resolved) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($resolved));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    $fh = fopen($resolved, 'rb');
    if ($fh) {
        while (!feof($fh)) {
            echo fread($fh, 8192);
        }
        fclose($fh);
    } else {
        readfile($resolved);
    }
    exit;
}
