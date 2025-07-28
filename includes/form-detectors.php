<?php
function updf_init_form_hooks() {
    // Forminator
    add_action('forminator_custom_form_after_handle_submit', 'updf_handle_submission_forminator', 10, 2);

    // Gravity Forms
    add_action('gform_after_submission', 'updf_handle_submission_gravity', 10, 2);

    // Contact Form 7
    add_action('wpcf7_mail_sent', 'updf_handle_submission_cf7', 10, 1);

    // WPForms
    add_action('wpforms_process_complete', 'updf_handle_submission_wpforms', 10, 4);
}
add_action('init', 'updf_init_form_hooks');

function updf_handle_submission_forminator($form_id, $response) {
    $data = $response['fields'];
    updf_generate_and_send_pdf('forminator', $form_id, $data);
}

function updf_handle_submission_gravity($entry, $form) {
    $data = $entry;
    updf_generate_and_send_pdf('gravityforms', $form['id'], $data);
}

function updf_handle_submission_cf7($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        $data = $submission->get_posted_data();
        updf_generate_and_send_pdf('cf7', $contact_form->id(), $data);
    }
}

function updf_handle_submission_wpforms($fields, $entry, $form_data, $entry_id) {
    $data = [];
    foreach ($fields as $field) {
        $data[$field['name']] = $field['value'];
    }
    updf_generate_and_send_pdf('wpforms', $form_data['id'], $data);
}
