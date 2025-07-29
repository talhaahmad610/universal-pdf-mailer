<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Return an array of supported form‑plugin slugs => human names
 */
function updf_get_supported_plugins() {
    return [
        'forminator'   => 'Forminator',
        'gravityforms' => 'Gravity Forms',
        'cf7'          => 'Contact Form 7',
        'wpforms'      => 'WPForms',
    ];
}

/**
 * Given a plugin slug and form ID, fetch that plugin’s forms
 * Returns an array of ['id'=>..., 'name'=>...] entries
 */
function updf_get_forms_for_plugin( $plugin ) {
    $forms = [];

    switch ( $plugin ) {
        case 'forminator':
            if ( class_exists( 'Forminator_API' ) ) {
                foreach ( Forminator_API::get_forms() as $form ) {
                    $forms[] = [ 'id' => $form->id, 'name' => $form->name ];
                }
            }
            break;

        case 'gravityforms':
            if ( class_exists( 'GFAPI' ) ) {
                foreach ( GFAPI::get_forms() as $form ) {
                    $forms[] = [ 'id' => $form['id'], 'name' => $form['title'] ];
                }
            }
            break;

        case 'cf7':
            $cf7_posts = get_posts( [
                'post_type'   => 'wpcf7_contact_form',
                'numberposts' => -1,
            ] );
            foreach ( $cf7_posts as $post ) {
                $forms[] = [ 'id' => $post->ID, 'name' => $post->post_title ];
            }
            break;

        case 'wpforms':
            if ( class_exists( 'WPForms' ) ) {
                $wpforms_posts = get_posts( [
                    'post_type'   => 'wpforms',
                    'numberposts' => -1,
                ] );
                foreach ( $wpforms_posts as $post ) {
                    $forms[] = [ 'id' => $post->ID, 'name' => $post->post_title ];
                }
            }
            break;
    }

    return $forms;
}

/**
 * Hook into the various form‑submit actions
 */
function updf_init_form_hooks() {
    add_action( 'forminator_custom_form_after_handle_submit', 'updf_handle_submission_forminator', 10, 2 );
    add_action( 'gform_after_submission',                   'updf_handle_submission_gravity',  10, 2 );
    add_action( 'wpcf7_mail_sent',                         'updf_handle_submission_cf7',      10, 1 );
    add_action( 'wpforms_process_complete',                'updf_handle_submission_wpforms',  10, 4 );
}
add_action( 'init', 'updf_init_form_hooks' );

/** Submission handlers (as before) **/
function updf_handle_submission_forminator( $form_id, $response ) {
    updf_generate_and_send_pdf( 'forminator', $form_id, $response['fields'] );
}

function updf_handle_submission_gravity( $entry, $form ) {
    updf_generate_and_send_pdf( 'gravityforms', $form['id'], $entry );
}

function updf_handle_submission_cf7( $contact_form ) {
    $submission = WPCF7_Submission::get_instance();
    if ( $submission ) {
        updf_generate_and_send_pdf( 'cf7', $contact_form->id(), $submission->get_posted_data() );
    }
}

function updf_handle_submission_wpforms( $fields, $entry, $form_data, $entry_id ) {
    $data = [];
    foreach ( $fields as $field ) {
        $data[ $field['name'] ] = $field['value'];
    }
    updf_generate_and_send_pdf( 'wpforms', $form_data['id'], $data );
}
