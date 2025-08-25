<?php
if (!defined('ABSPATH')) exit;

function sdp_create_db(){
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $files = $wpdb->prefix . 'sdp_files';
    $logs  = $wpdb->prefix . 'sdp_logs';

    $sql1 = "CREATE TABLE IF NOT EXISTS $files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        file_path TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    $sql2 = "CREATE TABLE IF NOT EXISTS $logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NOT NULL,
        ip VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (file_id),
        INDEX (ip),
        INDEX (created_at)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
}
