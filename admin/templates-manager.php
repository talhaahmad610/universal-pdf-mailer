<?php
function updf_render_templates_manager() {
    global $wpdb;
    $table = $wpdb->prefix . 'updf_templates';
    $action = $_GET['action'] ?? '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Handle delete action
    if ($action === 'delete' && $id) {
        check_admin_referer('updf_delete_template_' . $id);
        $wpdb->delete($table, ['id' => $id]);
        echo '<div class="updated"><p>Template deleted successfully.</p></div>';
        $action = ''; // reset to list view
    }

    // Handle form submission (saving template)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
        if (!isset($_POST['updf_nonce']) || !wp_verify_nonce($_POST['updf_nonce'], 'updf_template_save')) {
            wp_die('Security check failed');
        }

        $data = [
            'name'              => sanitize_text_field($_POST['name']),
            'plugin'            => sanitize_text_field($_POST['plugin']),
            'form_id'           => sanitize_text_field($_POST['form_id']),
            'content'           => wp_kses_post($_POST['content']),
            'admin_emails'      => sanitize_text_field($_POST['admin_emails']),
            'admin_subject'     => sanitize_text_field($_POST['admin_subject']),
            'admin_body'        => wp_kses_post($_POST['admin_body']),
            'applicant_mode'    => sanitize_text_field($_POST['applicant_mode']),
            'manual_fields'     => sanitize_text_field($_POST['manual_fields']),
            'applicant_subject' => sanitize_text_field($_POST['applicant_subject']),
            'applicant_body'    => wp_kses_post($_POST['applicant_body']),
            'send_conditions'   => sanitize_text_field($_POST['send_conditions']),
        ];

        if ($id) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }

        echo '<div class="updated"><p>Template saved successfully.</p></div>';
    }

    if ($action === 'edit') {
        $template = $id ? $wpdb->get_row("SELECT * FROM $table WHERE id = $id") : null;
        $plugins = updf_get_supported_plugins();
        ?>
        <div class="wrap">
            <h1><?php echo $template ? 'Edit Template' : 'Add New Template'; ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('updf_template_save', 'updf_nonce'); ?>
                <table class="form-table">
                    <tr><th>Name</th><td><input type="text" name="name" value="<?php echo esc_attr($template->name ?? ''); ?>" required class="regular-text"></td></tr>
                    <tr><th>Form Plugin</th>
                        <td>
                            <select name="plugin" required>
                                <?php foreach ($plugins as $slug => $title): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($template->plugin ?? '', $slug); ?>><?php echo esc_html($title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Form ID</th><td><input type="text" name="form_id" value="<?php echo esc_attr($template->form_id ?? ''); ?>" required class="regular-text"></td></tr>

                    <tr><th>Admin Emails</th><td><input type="text" name="admin_emails" value="<?php echo esc_attr($template->admin_emails ?? get_option('admin_email')); ?>" class="regular-text" placeholder="Comma-separated"></td></tr>
                    <tr><th>Admin Email Subject</th><td><input type="text" name="admin_subject" value="<?php echo esc_attr($template->admin_subject ?? 'New Form Submission'); ?>" class="regular-text"></td></tr>
                    <tr><th>Admin Email Body</th><td><textarea name="admin_body" rows="3" class="large-text"><?php echo esc_textarea($template->admin_body ?? 'Please find the attached PDF.'); ?></textarea></td></tr>

                    <tr><th>Applicant Email Mode</th>
                        <td>
                            <select name="applicant_mode">
                                <option value="auto" <?php selected($template->applicant_mode ?? 'auto', 'auto'); ?>>Auto-detect emails</option>
                                <option value="manual" <?php selected($template->applicant_mode ?? 'auto', 'manual'); ?>>Use specified fields</option>
                            </select>
                        </td>
                    </tr>
                    <tr><th>Applicant Email Fields (if manual)</th><td><input type="text" name="manual_fields" value="<?php echo esc_attr($template->manual_fields ?? ''); ?>" class="regular-text" placeholder="Comma-separated field keys"></td></tr>
                    <tr><th>Applicant Email Subject</th><td><input type="text" name="applicant_subject" value="<?php echo esc_attr($template->applicant_subject ?? 'Your Submission Copy'); ?>" class="regular-text"></td></tr>
                    <tr><th>Applicant Email Body</th><td><textarea name="applicant_body" rows="3" class="large-text"><?php echo esc_textarea($template->applicant_body ?? 'Please find your submission attached.'); ?></textarea></td></tr>

                    <tr><th>Send Conditions</th><td><input type="text" name="send_conditions" value="<?php echo esc_attr($template->send_conditions ?? ''); ?>" class="regular-text" placeholder="Comma-separated required field keys"></td></tr>
                </table>

                <h2>Template Builder</h2>
                <div id="gjs" style="border:1px solid #ccc; height:600px;">
                    <?php echo $template->content ?? '<h1>Drag & Drop Elements Here</h1>'; ?>
                </div>
                <textarea id="gjs-html" name="content" style="display:none;"></textarea>

                <h3>Available Placeholders</h3>
                <div id="placeholder-panel" style="margin-bottom:10px;"></div>

                <p>
                    <label>Insert Placeholder:</label>
                    <select id="placeholder-insert">
                        <option value="">-- Select --</option>
                        <option value="[current_date]">[current_date]</option>
                        <option value="[current_time]">[current_time]</option>
                        <option value="[site_name]">[site_name]</option>
                        <option value="[Email]">[Email]</option>
                    </select>
                </p>

                <?php submit_button('Save Template'); ?>
                <a href="<?php echo admin_url('admin-post.php?action=updf_send_test&id=' . ($id ?? 0)); ?>" class="button button-secondary" target="_blank">Send Test PDF</a>

            </form>
        </div>

        <script src="https://unpkg.com/grapesjs"></script>
        <link href="https://unpkg.com/grapesjs/dist/css/grapes.min.css" rel="stylesheet"/>

        <script>
        var editor = grapesjs.init({
            container: '#gjs',
            height: '600px',
            fromElement: true,
            storageManager: false,
            panels: { defaults: [] }
        });

        document.querySelector('form').addEventListener('submit', function () {
            document.getElementById('gjs-html').value = editor.getHtml();
        });

        document.getElementById('placeholder-insert').addEventListener('change', function () {
            var val = this.value;
            if (val) {
                editor.insertComponent(val);
                this.value = '';
            }
        });

        // Fetch placeholders dynamically
        function fetchPlaceholders() {
            var plugin = document.querySelector('select[name="plugin"]').value;
            var form_id = document.querySelector('input[name="form_id"]').value;

            if (!plugin || !form_id) return;

            jQuery.post(ajaxurl, {
                action: 'updf_get_form_fields',
                plugin: plugin,
                form_id: form_id
            }, function (response) {
                if (response.success) {
                    var panel = jQuery('#placeholder-panel');
                    panel.empty();
                    response.data.forEach(function (field) {
                        panel.append('<button type="button" class="button insert-placeholder" data-placeholder="[' + field.key + ']">' + field.label + '</button> ');
                    });
                }
            });
        }

        jQuery(document).on('change', 'select[name="plugin"], input[name="form_id"]', fetchPlaceholders);

        jQuery(document).on('click', '.insert-placeholder', function () {
            editor.insertComponent(jQuery(this).data('placeholder'));
        });
        </script>
        <?php
    } else {
        // List templates
        $templates = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">PDF Templates</h1>
            <a href="<?php echo admin_url('admin.php?page=updf_templates&action=edit'); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th>Name</th>
                        <th>Plugin</th>
                        <th>Form ID</th>
                        <th>Admin Emails</th>
                        <th width="20%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($templates): ?>
                    <?php foreach ($templates as $tpl): ?>
                        <tr>
                            <td><?php echo intval($tpl->id); ?></td>
                            <td><?php echo esc_html($tpl->name); ?></td>
                            <td><?php echo esc_html($tpl->plugin); ?></td>
                            <td><?php echo esc_html($tpl->form_id); ?></td>
                            <td><?php echo esc_html($tpl->admin_emails); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=updf_templates&action=edit&id=' . $tpl->id); ?>">Edit</a> |
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=updf_templates&action=delete&id=' . $tpl->id), 'updf_delete_template_' . $tpl->id); ?>" onclick="return confirm('Delete this template?');">Delete</a> |
                                <a href="<?php echo admin_url('admin-post.php?action=updf_send_test&id=' . $tpl->id); ?>" target="_blank">Send Test</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No templates found. <a href="<?php echo admin_url('admin.php?page=updf_templates&action=edit'); ?>">Add one</a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
