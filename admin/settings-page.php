<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
    add_menu_page('Secure Downloads', 'Secure Downloads', 'manage_options', 'sdp-settings', 'sdp_settings_page', 'dashicons-download', 58);
});

function sdp_settings_page(){
    global $wpdb;
    $table_files = $wpdb->prefix . 'sdp_files';
    $table_logs  = $wpdb->prefix . 'sdp_logs';

    // Save settings
    if (isset($_POST['sdp_save_settings'])) {
        check_admin_referer('sdp_save_settings');
        update_option('sdp_site_key', sanitize_text_field($_POST['sdp_site_key'] ?? ''));
        update_option('sdp_secret_key', sanitize_text_field($_POST['sdp_secret_key'] ?? ''));
        update_option('sdp_ip_limit', intval($_POST['sdp_ip_limit'] ?? 3));
        echo '<div class="updated"><p>✅ Settings saved.</p></div>';
    }

    // Reset logs
    if (isset($_POST['sdp_reset_logs'])) {
        check_admin_referer('sdp_reset_logs');
        $wpdb->query("TRUNCATE TABLE $table_logs");
        echo '<div class="updated"><p>🧹 Logs cleared.</p></div>';
    }

    // Add file
    if (isset($_POST['sdp_add_file'])) {
        check_admin_referer('sdp_add_file');
        $file_name = sanitize_text_field($_POST['file_name'] ?? '');
        $file_path = sanitize_text_field($_POST['file_path'] ?? '');
        if ($file_name && $file_path) {
            $wpdb->insert($table_files, ['file_name' => $file_name, 'file_path' => $file_path]);
            echo '<div class="updated"><p>✅ File added.</p></div>';
        } else {
            echo '<div class="error"><p>⚠️ Name and Path are required.</p></div>';
        }
    }

    // Update file
    if (isset($_POST['sdp_update_file'])) {
        check_admin_referer('sdp_update_file_' . intval($_POST['file_id']));
        $file_id   = intval($_POST['file_id']);
        $file_name = sanitize_text_field($_POST['file_name'] ?? '');
        $file_path = sanitize_text_field($_POST['file_path'] ?? '');
        $wpdb->update($table_files, ['file_name' => $file_name, 'file_path' => $file_path], ['id' => $file_id]);
        echo '<div class="updated"><p>✅ File updated.</p></div>';
    }

    // Delete file
    if (isset($_GET['sdp_delete'])) {
        $del_id = intval($_GET['sdp_delete']);
        check_admin_referer('sdp_delete_file_' . $del_id);
        $wpdb->delete($table_files, ['id' => $del_id]);
        echo '<div class="updated"><p>🗑️ File deleted.</p></div>';
    }

    $files = $wpdb->get_results("SELECT * FROM $table_files ORDER BY id DESC");
    $uploads = wp_get_upload_dir();
    ?>
    <div class="wrap">
        <h1>Secure Downloads</h1>

        <h2>Settings</h2>
        <form method="post">
            <?php wp_nonce_field('sdp_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">reCAPTCHA Site Key</th>
                    <td><input type="text" name="sdp_site_key" value="<?php echo esc_attr(get_option('sdp_site_key')); ?>" size="50"></td>
                </tr>
                <tr>
                    <th scope="row">reCAPTCHA Secret Key</th>
                    <td><input type="text" name="sdp_secret_key" value="<?php echo esc_attr(get_option('sdp_secret_key')); ?>" size="50"></td>
                </tr>
                <tr>
                    <th scope="row">Daily IP Download Limit</th>
                    <td><input type="number" name="sdp_ip_limit" value="<?php echo esc_attr(get_option('sdp_ip_limit', 3)); ?>" min="1"></td>
                </tr>
            </table>
            <p class="description">Files are expected under <code><?php echo esc_html($uploads['basedir']); ?></code> (URL: <code><?php echo esc_html($uploads['baseurl']); ?></code>).</p>
            <p class="submit"><button class="button button-primary" name="sdp_save_settings">Save Settings</button></p>
        </form>

        <form method="post" style="margin-top:10px;">
            <?php wp_nonce_field('sdp_reset_logs'); ?>
            <button class="button" name="sdp_reset_logs" onclick="return confirm('Clear all download logs?');">Reset IP Logs</button>
        </form>

        <hr>

        <h2>Protected Files</h2>
        <form method="post">
            <?php wp_nonce_field('sdp_add_file'); ?>
            <p>
                <label>Name:&nbsp;<input type="text" name="file_name" placeholder="pmjen.zip"></label>
                &nbsp;&nbsp;
                <label>Path:&nbsp;<input type="text" name="file_path" size="60" placeholder="pmjen.zip or cod4/mods/pmjen.zip or /wp-content/uploads/cod4/mods/pmjen.zip or full URL"></label>
            </p>
            <p class="description">Recommended: path relative to uploads (e.g. <code>cod4/mods/pmjen.zip</code>). Absolute URLs are also allowed.</p>
            <p><button class="button button-primary" name="sdp_add_file">Add File</button></p>
        </form>

        <h3>Files</h3>
        <table class="widefat fixed striped">
            <thead>
                <tr><th style="width:60px;">ID</th><th>Name</th><th>Path</th><th>Shortcode</th><th style="width:360px;">Actions</th></tr>
            </thead>
            <tbody>
            <?php if ($files): foreach($files as $f): ?>
                <tr>
                    <td><?php echo intval($f->id); ?></td>
                    <td><?php echo esc_html($f->file_name); ?></td>
                    <td><?php echo esc_html($f->file_path); ?></td>
                    <td><code>[secure_download id="<?php echo intval($f->id); ?>"]</code></td>
                    <td>
                        <form method="post" style="display:inline-block; margin-right:8px;">
                            <?php wp_nonce_field('sdp_update_file_' . intval($f->id)); ?>
                            <input type="hidden" name="file_id" value="<?php echo intval($f->id); ?>">
                            <input type="text" name="file_name" value="<?php echo esc_attr($f->file_name); ?>" style="width:160px;">
                            <input type="text" name="file_path" value="<?php echo esc_attr($f->file_path); ?>" style="width:300px;">
                            <button class="button" name="sdp_update_file">Edit</button>
                        </form>
                        <a class="button button-link-delete" style="color:#b32d2e;" href="<?php echo wp_nonce_url(admin_url('admin.php?page=sdp-settings&sdp_delete=' . intval($f->id)), 'sdp_delete_file_' . intval($f->id)); ?>" onclick="return confirm('Delete this file?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5">No files yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}
