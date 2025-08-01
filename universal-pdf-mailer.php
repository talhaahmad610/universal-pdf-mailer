<?php
/**
 * Plugin Name: Universal PDF Mailer
 * Description: Generate and send PDF copies of form submissions for multiple form plugins.
 * Version: 1.0
 * Author: Talha Ahmad
 */
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/form-detectors.php';
require_once plugin_dir_path(__FILE__) . 'admin/templates-manager.php';

class UniversalPDFMailer {
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'create_table']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'updf_templates';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id INT AUTO_INCREMENT,
            name VARCHAR(255),
            plugin VARCHAR(50),
            form_id VARCHAR(50),
            content LONGTEXT,
            css LONGTEXT,
            admin_emails TEXT,
            applicant_mode ENUM('auto','manual') DEFAULT 'auto',
            manual_fields TEXT,
            admin_subject VARCHAR(255),
            admin_body TEXT,
            applicant_subject VARCHAR(255),
            applicant_body TEXT,
            send_conditions TEXT,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Universal PDF Mailer',
            'PDF Templates',
            'manage_options',
            'updf_templates',
            'updf_render_templates_manager',
            'dashicons-media-document',
            25
        );
    }
}

add_action('admin_post_updf_send_test', function() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    global $wpdb;
    $table = $wpdb->prefix . 'updf_templates';
    $id = intval($_GET['id']);
    $template = $wpdb->get_row("SELECT * FROM $table WHERE id = $id");

    if (!$template) wp_die('Template not found.');

    // More complete sample data
    $sample_data = [
        'Name'          => 'John Doe',
        'Email'         => 'john@example.com',
        'Phone'         => '123-456-7890',
        'Address'       => '123 Main St',
        'signature'     => 'https://example.com/signature.png',
        'current_date'  => date('Y-m-d'),
        'current_time'  => date('H:i:s'),
        'site_name'     => get_bloginfo('name')
    ];

    require_once plugin_dir_path(__FILE__) . 'includes/pdf-generator.php';
    updf_generate_and_send_pdf($template->plugin, $template->form_id, $sample_data);

    wp_redirect(admin_url('admin.php?page=updf_templates&message=test_sent'));
    exit;
});

add_action('admin_notices', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'updf_templates' && isset($_GET['message']) && $_GET['message'] === 'test_sent') {
        echo '<div class="notice notice-success is-dismissible"><p>Test PDF has been sent successfully!</p></div>';
    }
});

new UniversalPDFMailer();
