<?php
if (!defined('ABSPATH')) exit;

/**
 * Send email with PDF attachment
 * Tries wp_mail() first, falls back to PHPMailer if enabled
 */
function updf_send_email($to, $subject, $body, $attachments = [], $headers = []) {
    // Default headers
    $default_headers = ['Content-Type: text/html; charset=UTF-8'];
    $final_headers = array_merge($default_headers, $headers);
    
    // Try WordPress mail first
    $wp_mail_result = wp_mail($to, $subject, $body, $final_headers, $attachments);
    
    if ($wp_mail_result) {
        return true;
    }
    
    // If wp_mail failed and PHPMailer is enabled, use PHPMailer
    $use_phpmailer = get_option('updf_use_phpmailer', false);
    if ($use_phpmailer) {
        return updf_send_with_phpmailer($to, $subject, $body, $attachments, $headers);
    }
    
    return false;
}

/**
 * Send email using PHPMailer (fallback)
 */
function updf_send_with_phpmailer($to, $subject, $body, $attachments = [], $headers = []) {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('UPDF: PHPMailer autoload not found');
        return false;
    }
    
    require_once $autoload;
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Get email settings
        $from_email = get_option('updf_from_email', get_option('admin_email'));
        $from_name = get_option('updf_from_name', get_bloginfo('name'));
        
        // Configure PHPMailer
        $mail->isSMTP();
        $mail->Host = get_option('updf_smtp_host', '');
        $mail->SMTPAuth = get_option('updf_smtp_auth', true);
        $mail->Username = get_option('updf_smtp_username', '');
        $mail->Password = get_option('updf_smtp_password', '');
        $mail->SMTPSecure = get_option('updf_smtp_secure', 'tls'); // tls or ssl
        $mail->Port = intval(get_option('updf_smtp_port', 587));
        $mail->CharSet = 'UTF-8';
        
        // Allow self-signed certificates if enabled
        if (get_option('updf_smtp_allow_self_signed', false)) {
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }
        
        // Set sender
        $mail->setFrom($from_email, $from_name);
        
        // Set recipient
        $mail->addAddress($to);
        
        // Add reply-to if set
        $reply_to = get_option('updf_reply_to', '');
        if (!empty($reply_to)) {
            $mail->addReplyTo($reply_to);
        }
        
        // Set content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = wp_strip_all_tags($body);
        
        // Add attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("UPDF: Email sent successfully via PHPMailer to {$to}");
            return true;
        }
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("UPDF: PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
    
    return false;
}

