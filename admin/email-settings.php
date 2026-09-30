<?php
if (!defined('ABSPATH')) exit;

function updf_render_email_settings() {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updf_email_settings'])) {
        if (!isset($_POST['updf_email_nonce']) || !wp_verify_nonce($_POST['updf_email_nonce'], 'updf_email_settings')) {
            wp_die('Security check failed');
        }
        
        // Save settings
        update_option('updf_use_phpmailer', isset($_POST['use_phpmailer']));
        update_option('updf_from_email', sanitize_email($_POST['from_email']));
        update_option('updf_from_name', sanitize_text_field($_POST['from_name']));
        update_option('updf_reply_to', sanitize_email($_POST['reply_to']));
        
        // SMTP settings
        update_option('updf_smtp_host', sanitize_text_field($_POST['smtp_host']));
        update_option('updf_smtp_auth', isset($_POST['smtp_auth']));
        update_option('updf_smtp_username', sanitize_text_field($_POST['smtp_username']));
        update_option('updf_smtp_password', sanitize_text_field($_POST['smtp_password']));
        update_option('updf_smtp_secure', sanitize_text_field($_POST['smtp_secure']));
        update_option('updf_smtp_port', intval($_POST['smtp_port']));
        update_option('updf_smtp_allow_self_signed', isset($_POST['smtp_allow_self_signed']));
        
        // Handle test email
        if (isset($_POST['send_test_email'])) {
            $test_email = sanitize_email($_POST['test_email']);
            if (!empty($test_email) && is_email($test_email)) {
                require_once plugin_dir_path(__DIR__) . 'includes/email-sender.php';
                $result = updf_send_email(
                    $test_email,
                    'Test Email from Universal PDF Mailer',
                    '<p>This is a test email from your Universal PDF Mailer plugin. If you received this, your email configuration is working correctly!</p>',
                    []
                );
                
                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>Test email sent successfully to ' . esc_html($test_email) . '!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Failed to send test email. Please check your settings and try again.</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        }
    }
    
    // Get current settings
    $use_phpmailer = get_option('updf_use_phpmailer', false);
    $from_email = get_option('updf_from_email', get_option('admin_email'));
    $from_name = get_option('updf_from_name', get_bloginfo('name'));
    $reply_to = get_option('updf_reply_to', '');
    $smtp_host = get_option('updf_smtp_host', '');
    $smtp_auth = get_option('updf_smtp_auth', true);
    $smtp_username = get_option('updf_smtp_username', '');
    $smtp_password = get_option('updf_smtp_password', '');
    $smtp_secure = get_option('updf_smtp_secure', 'tls');
    $smtp_port = get_option('updf_smtp_port', 587);
    $smtp_allow_self_signed = get_option('updf_smtp_allow_self_signed', false);
    ?>
    
    <div class="wrap">
        <h1>Email Settings - Universal PDF Mailer</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('updf_email_settings', 'updf_email_nonce'); ?>
            <input type="hidden" name="updf_email_settings" value="1">
            
            <h2 class="title">General Email Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="use_phpmailer">Use PHPMailer</label>
                    </th>
                    <td>
                        <input type="checkbox" name="use_phpmailer" id="use_phpmailer" value="1" <?php checked($use_phpmailer, true); ?>>
                        <p class="description">Enable this to use PHPMailer as fallback if WordPress default mail fails. If unchecked, only WordPress mail will be used.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="from_email">From Email</label>
                    </th>
                    <td>
                        <input type="email" name="from_email" id="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text" required>
                        <p class="description">The email address that will appear as the sender.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="from_name">From Name</label>
                    </th>
                    <td>
                        <input type="text" name="from_name" id="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text" required>
                        <p class="description">The name that will appear as the sender.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="reply_to">Reply-To Email</label>
                    </th>
                    <td>
                        <input type="email" name="reply_to" id="reply_to" value="<?php echo esc_attr($reply_to); ?>" class="regular-text">
                        <p class="description">Optional: Email address for replies (leave empty to use From Email).</p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title">SMTP Settings (for PHPMailer)</h2>
            <p class="description">These settings are only used when PHPMailer is enabled. Configure your SMTP server details below.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smtp_host">SMTP Host</label>
                    </th>
                    <td>
                        <input type="text" name="smtp_host" id="smtp_host" value="<?php echo esc_attr($smtp_host); ?>" class="regular-text" placeholder="smtp.gmail.com">
                        <p class="description">Your SMTP server hostname (e.g., smtp.gmail.com, smtp.mailtrap.io).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smtp_port">SMTP Port</label>
                    </th>
                    <td>
                        <input type="number" name="smtp_port" id="smtp_port" value="<?php echo esc_attr($smtp_port); ?>" class="small-text" min="1" max="65535">
                        <p class="description">SMTP port (587 for TLS, 465 for SSL, 25 for non-secure).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smtp_secure">Encryption</label>
                    </th>
                    <td>
                        <select name="smtp_secure" id="smtp_secure">
                            <option value="tls" <?php selected($smtp_secure, 'tls'); ?>>TLS</option>
                            <option value="ssl" <?php selected($smtp_secure, 'ssl'); ?>>SSL</option>
                            <option value="" <?php selected($smtp_secure, ''); ?>>None</option>
                        </select>
                        <p class="description">Encryption method (TLS recommended for port 587, SSL for port 465).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smtp_auth">SMTP Authentication</label>
                    </th>
                    <td>
                        <input type="checkbox" name="smtp_auth" id="smtp_auth" value="1" <?php checked($smtp_auth, true); ?>>
                        <p class="description">Enable if your SMTP server requires authentication.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smtp_username">SMTP Username</label>
                    </th>
                    <td>
                        <input type="text" name="smtp_username" id="smtp_username" value="<?php echo esc_attr($smtp_username); ?>" class="regular-text">
                        <p class="description">Your SMTP username (usually your email address).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smtp_password">SMTP Password</label>
                    </th>
                    <td>
                        <input type="password" name="smtp_password" id="smtp_password" value="<?php echo esc_attr($smtp_password); ?>" class="regular-text">
                        <p class="description">Your SMTP password or app-specific password.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smtp_allow_self_signed">Allow Self-Signed Certificates</label>
                    </th>
                    <td>
                        <input type="checkbox" name="smtp_allow_self_signed" id="smtp_allow_self_signed" value="1" <?php checked($smtp_allow_self_signed, true); ?>>
                        <p class="description">Enable this only if you're using a self-signed SSL certificate (not recommended for production).</p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title">Test Email</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_email">Test Email Address</label>
                    </th>
                    <td>
                        <input type="email" name="test_email" id="test_email" class="regular-text" placeholder="test@example.com">
                        <button type="submit" name="send_test_email" class="button button-secondary" onclick="return confirm('Send test email?');">Send Test Email</button>
                        <p class="description">Enter an email address to test your email configuration.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Save Settings'); ?>
        </form>
        
        <div class="card">
            <h2>Popular SMTP Providers</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>SMTP Host</th>
                        <th>Port</th>
                        <th>Security</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Gmail</strong></td>
                        <td>smtp.gmail.com</td>
                        <td>587</td>
                        <td>TLS</td>
                    </tr>
                    <tr>
                        <td><strong>Outlook/Hotmail</strong></td>
                        <td>smtp-mail.outlook.com</td>
                        <td>587</td>
                        <td>TLS</td>
                    </tr>
                    <tr>
                        <td><strong>Yahoo</strong></td>
                        <td>smtp.mail.yahoo.com</td>
                        <td>587</td>
                        <td>TLS</td>
                    </tr>
                    <tr>
                        <td><strong>Mailtrap (Testing)</strong></td>
                        <td>smtp.mailtrap.io</td>
                        <td>2525</td>
                        <td>TLS</td>
                    </tr>
                </tbody>
            </table>
            <p><em>Note: For Gmail, you may need to use an App Password instead of your regular password. Enable 2-Step Verification and generate an App Password in your Google Account settings.</em></p>
        </div>
    </div>
    <?php
}

