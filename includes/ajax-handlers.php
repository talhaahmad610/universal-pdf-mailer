<?php
add_action('wp_ajax_updf_get_form_fields', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $plugin = sanitize_text_field($_POST['plugin']);
    $form_id = intval($_POST['form_id']);
    $fields = [];

    switch ($plugin) {
        case 'forminator':
            if (class_exists('Forminator_API')) {
                $form = Forminator_API::get_form($form_id);
                if ($form && isset($form->fields)) {
                    foreach ($form->fields as $field) {
                        $fields[] = ['key' => $field['slug'], 'label' => $field['field_label']];
                    }
                }
            }
            break;

        case 'gravityforms':
            if (class_exists('GFAPI')) {
                $form = GFAPI::get_form($form_id);
                if ($form && isset($form['fields'])) {
                    foreach ($form['fields'] as $field) {
                        if (!empty($field->label)) {
                            $fields[] = ['key' => $field->id, 'label' => $field->label];
                        }
                    }
                }
            }
            break;

        case 'wpforms':
            if (function_exists('wpforms')) {
                $form = wpforms()->form->get($form_id);
                if ($form && isset($form->post_content)) {
                    $data = json_decode($form->post_content, true);
                    if (isset($data['fields'])) {
                        foreach ($data['fields'] as $field) {
                            $fields[] = ['key' => $field['id'], 'label' => $field['label']];
                        }
                    }
                }
            }
            break;

        case 'cf7':
            $fields[] = ['key' => 'your-name', 'label' => 'Your Name'];
            $fields[] = ['key' => 'your-email', 'label' => 'Your Email'];
            break;
    }

    wp_send_json_success($fields);
});
