<?php
use Dompdf\Dompdf;
use Dompdf\Options;

function updf_generate_and_send_pdf($plugin, $form_id, $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'updf_templates';

    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE plugin = %s AND form_id = %s", $plugin, $form_id
    ));

    if (!$template) {
        error_log("UPDF: No template found for plugin: $plugin, form_id: $form_id");
        return;
    }

    // Check send conditions
    if (!empty($template->send_conditions)) {
        $conditions = array_map('trim', explode(',', $template->send_conditions));
        foreach ($conditions as $field) {
            if (empty($data[$field])) {
                error_log("UPDF: Skipping PDF generation due to missing send condition field: $field");
                return;
            }
        }
    }

    // Replace placeholders in template content
    $html = $template->content;
    foreach ($data as $key => $value) {
        $pattern = '/\[' . preg_quote($key, '/') . '\]/i';
        $html = preg_replace($pattern, esc_html($value), $html);

        // Handle variations with spaces and underscores
        $alt_key = str_replace([' ', '_'], ['_', ' '], $key);
        if ($alt_key !== $key) {
            $pattern_alt = '/\[' . preg_quote($alt_key, '/') . '\]/i';
            $html = preg_replace($pattern_alt, esc_html($value), $html);
        }
    }

    // Handle shortcodes
    $replacements = [
        '[current_date]' => date_i18n('Y-m-d'),
        '[current_time]' => date_i18n('H:i'),
        '[site_name]'    => get_bloginfo('name'),
    ];
    $html = str_replace(array_keys($replacements), array_values($replacements), $html);

    // Apply default styling for PDF
    $style = "
        <style>
            body { font-family: DejaVu Sans, sans-serif; margin: 20px; color: #333; }
            h1, h2, h3 { color: #444; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f8f8f8; }
            .footer { margin-top: 30px; font-size: 12px; color: #777; text-align: center; }
        </style>
    ";
    $html = $style . $html;

    // If template has no placeholders, auto-generate table
    if (strpos($html, '[') === false) {
        $html .= "<h2>Submitted Data</h2><table>";
        foreach ($data as $key => $value) {
            $html .= "<tr><th>" . esc_html($key) . "</th><td>" . nl2br(esc_html($value)) . "</td></tr>";
        }
        $html .= "</table>";
    }

    // Generate PDF
    $autoload_path = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
    } else {
        error_log("UPDF: Dompdf autoload not found at $autoload_path");
        return;
    }

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_output = $dompdf->output();

    // Save to temp file
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['path'] . '/submission_' . time() . '.pdf';
    file_put_contents($pdf_path, $pdf_output);

    // Prepare email recipients
    $admin_emails = array_map('trim', explode(',', $template->admin_emails ?: get_option('admin_email')));
    $to_applicant = [];

    if ($template->applicant_mode === 'auto') {
        foreach ($data as $k => $v) {
            if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $to_applicant[] = $v;
            }
        }
    } elseif ($template->applicant_mode === 'manual' && !empty($template->manual_fields)) {
        $fields = array_map('trim', explode(',', $template->manual_fields));
        foreach ($fields as $field) {
            if (!empty($data[$field]) && filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $to_applicant[] = $data[$field];
            }
        }
    }

    // Replace placeholders in email subject & body
    $replace_in_text = function ($text) use ($data, $replacements) {
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        foreach ($data as $key => $value) {
            $pattern = '/\[' . preg_quote($key, '/') . '\]/i';
            $text = preg_replace($pattern, $value, $text);

            // Handle variations with spaces and underscores
            $alt_key = str_replace([' ', '_'], ['_', ' '], $key);
            if ($alt_key !== $key) {
                $pattern_alt = '/\[' . preg_quote($alt_key, '/') . '\]/i';
                $text = preg_replace($pattern_alt, $value, $text);
            }
        }
        return $text;
    };

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $admin_subject     = $replace_in_text($template->admin_subject ?: 'New Form Submission');
    $admin_body        = $replace_in_text($template->admin_body ?: 'Please find the attached PDF.');
    $applicant_subject = $replace_in_text($template->applicant_subject ?: 'Your Submission Copy');
    $applicant_body    = $replace_in_text($template->applicant_body ?: 'Please find your submission attached.');

    // Send to admin
    foreach ($admin_emails as $email) {
        if (!wp_mail($email, $admin_subject, $admin_body, $headers, [$pdf_path])) {
            error_log("UPDF: Failed to send PDF to admin email: $email");
        }
    }

    // Send to applicant
    foreach ($to_applicant as $email) {
        if (!wp_mail($email, $applicant_subject, $applicant_body, $headers, [$pdf_path])) {
            error_log("UPDF: Failed to send PDF to applicant email: $email");
        }
    }

    unlink($pdf_path); // Clean up temp file
}
