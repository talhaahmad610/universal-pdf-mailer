<?php
use Dompdf\Dompdf;
use Dompdf\Options;

function updf_generate_and_send_pdf($plugin, $form_id, $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'updf_templates';

    // 1) Load template
    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE plugin = %s AND form_id = %s",
        $plugin, $form_id
    ));
    if (!$template) {
        error_log("UPDF: No template for {$plugin} / {$form_id}");
        return;
    }

    // 2) Optional send‐conditions
    if (!empty($template->send_conditions)) {
        foreach ( array_map('trim', explode(',', $template->send_conditions)) as $c ) {
            if (empty($data[$c])) {
                error_log("UPDF: Missing required field {$c}, skipping PDF");
                return;
            }
        }
    }

    // 3) Start HTML & inject your custom CSS
    $html  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
    $html .= "<style>{$template->css}</style>";
    $html .= '</head><body>';
    $html .= $template->content;

    // 4) Handle [all_data] marker
    if ( stripos($html, '[all_data]') !== false ) {
        $tbl  = '<h2>Submitted Data</h2>';
        $tbl .= '<table style="width:100%;border-collapse:collapse;margin-top:20px;">';
        $tbl .= '<thead><tr>'
              . '<th style="border:1px solid #ddd;padding:8px;">Field</th>'
              . '<th style="border:1px solid #ddd;padding:8px;">Value</th>'
              . '</tr></thead><tbody>';

        foreach ($data as $key => $val) {
            // if it’s an image URL, show the image
            if ( filter_var($val, FILTER_VALIDATE_URL)
              && preg_match('/\.(jpe?g|png|gif|svg)$/i', parse_url($val, PHP_URL_PATH)) ) {
                $display = '<img src="' . esc_url($val) . '" style="max-width:200px;height:auto;">';
            } else {
                $display = nl2br(esc_html($val));
            }
            $tbl .= '<tr>'
                  . '<td style="border:1px solid #ddd;padding:8px;">' . esc_html($key) . '</td>'
                  . '<td style="border:1px solid #ddd;padding:8px;">' . $display . '</td>'
                  . '</tr>';
        }

        $tbl .= '</tbody></table>';
        $html = str_ireplace('[all_data]', $tbl, $html);
    }

    // 5) Replace individual placeholders
    foreach ($data as $key => $val) {
        $ph = '[' . $key . ']';
        
        // Handle special placeholders differently
        if ($key === 'signature') {
            $html = str_ireplace($ph, '<img src="' . esc_url($val) . '" style="max-height:80px;">', $html);
        } else {
            $html = str_ireplace($ph, nl2br(esc_html($val)), $html);
        }
    }

    // 6) Standard shortcodes
    $shorts = [
        '[current_date]' => date_i18n('Y-m-d'),
        '[current_time]' => date_i18n('H:i'),
        '[site_name]'    => get_bloginfo('name'),
        '[all_data]'     => '', // Will be handled separately
    ];

    // Replace all shortcodes first
    $html = str_ireplace(array_keys($shorts), array_values($shorts), $html);

    $html .= '</body></html>';

    


    // 7) Render PDF with Dompdf
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log("UPDF: Cannot find Dompdf autoload at {$autoload}");
        return;
    }
    require_once $autoload;

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    // DEBUG: dump the exact HTML Dompdf will render
   $dump_path = dirname(__DIR__) . '/debug.html';
    $bytes = file_put_contents( $dump_path, $html );
    error_log("UPDF DEBUG: wrote {$bytes} bytes to {$dump_path}");

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_output = $dompdf->output();


    // 8) Save PDF to temp
    // Determine upload directory
    $up = wp_upload_dir();
    // DEBUG: confirm the directory
    error_log("UPDF: Upload directory is '{$up['basedir']}' (URL: {$up['baseurl']})");

    // Build the PDF path
    $pdf_path = $up['path'] . '/submission_' . time() . '.pdf';

    // Write the PDF and capture result
    $written = file_put_contents($pdf_path, $pdf_output);

    // DEBUG: confirm how many bytes were written (or if it failed)
    if ($written === false) {
        error_log("UPDF ERROR: file_put_contents failed when writing to {$pdf_path}");
    } else {
        error_log("UPDF: file_put_contents wrote {$written} bytes to {$pdf_path}");
    }


    // 9) Determine recipients
    $admin_emails  = array_map('trim', explode(',', $template->admin_emails ?: get_option('admin_email')));
    $to_applicant = [];

    if ($template->applicant_mode === 'auto') {
        // Strategy 1: Collect all fields with email values
        $email_fields = [];
        
        // First pass: Find fields with "email" in key and valid email value
        foreach ($data as $key => $value) {
            if (stripos($key, 'email') !== false && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $email_fields[$key] = sanitize_email($value);
            }
        }
        
        // Second pass: Find any other fields with email values
        foreach ($data as $key => $value) {
            if (!isset($email_fields[$key]) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $email_fields[$key] = sanitize_email($value);
            }
        }
        
        // Strategy 2: Handle field groups (parent/guardian/any)
        $grouped_emails = [];
        
        // Find field groups by common prefixes
        $prefixes = [];
        foreach (array_keys($data) as $key) {
            if (preg_match('/^(.+?)_/', $key, $matches)) {
                $prefix = $matches[1];
                if (!in_array($prefix, $prefixes)) {
                    $prefixes[] = $prefix;
                }
            }
        }
        
        // Collect emails from each group
        foreach ($prefixes as $prefix) {
            $group_emails = [];
            foreach ($data as $key => $value) {
                if (strpos($key, $prefix.'_') === 0 && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $group_emails[] = sanitize_email($value);
                }
            }
            
            // Only add group if it has emails and not already collected individually
            if (!empty($group_emails)) {
                foreach ($group_emails as $email) {
                    if (!in_array($email, $email_fields)) {
                        $grouped_emails = array_merge($grouped_emails, $group_emails);
                    }
                }
            }
        }
        
        // Combine all found emails
        $to_applicant = array_merge(array_values($email_fields), $grouped_emails);
    } else {
        // Manual mode: specified fields
        $manual_fields = array_map('trim', explode(',', $template->manual_fields));
        foreach ($manual_fields as $field) {
            if (!empty($data[$field]) && filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $to_applicant[] = sanitize_email($data[$field]);
            }
        }
    }

    // Final cleanup: remove duplicates and empty values
    $to_applicant = array_unique(array_filter($to_applicant));

    // 10) Prepare subjects & bodies with placeholders
    $replace_text = function($text) use ($data, $shorts) {
        $text = str_ireplace(array_keys($shorts), array_values($shorts), $text);
        foreach ($data as $k => $v) {
            $text = str_ireplace("[$k]", $v, $text);
        }
        return $text;
    };

    $headers           = ['Content-Type: text/html; charset=UTF-8'];
    $admin_subject     = $replace_text($template->admin_subject ?: 'New Form Submission');
    $admin_body        = $replace_text($template->admin_body    ?: 'Please find the attached PDF.');
    $applicant_subject = $replace_text($template->applicant_subject ?: 'Your Submission Copy');
    $applicant_body    = $replace_text($template->applicant_body    ?: 'Please find your submission attached.');

    // 11) Send emails
    foreach ($admin_emails as $email) {
        // Create unique file path for each email
        $unique_pdf_path = $up['path'] . '/submission_' . time() . '_admin.pdf';
        file_put_contents($unique_pdf_path, $pdf_output);
        
        wp_mail($email, $admin_subject, $admin_body, $headers, [$unique_pdf_path]);
        
        // Clean up after sending
        unlink($unique_pdf_path);
    }

    foreach ($to_applicant as $email) {
        // Create unique file path for each email
        $unique_pdf_path = $up['path'] . '/submission_' . time() . '_applicant.pdf';
        file_put_contents($unique_pdf_path, $pdf_output);
        
        wp_mail($email, $applicant_subject, $applicant_body, $headers, [$unique_pdf_path]);
        
        // Clean up after sending
        unlink($unique_pdf_path);
    }

    // Remove the temporary PDF
    unlink($pdf_path);
}
