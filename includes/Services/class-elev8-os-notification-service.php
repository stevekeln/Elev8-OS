<?php
/** Shared notification boundary. Email is the first supported channel. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Notification_Service {
    /**
     * @param string|string[] $to
     * @param string|string[] $headers
     * @param string[] $attachments
     */
    public static function send_email($to, string $subject, string $message, $headers = '', array $attachments = []): bool {
        $payload = apply_filters('elev8_os_notification_email_payload', [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
        ]);
        if (!is_array($payload) || empty($payload['to']) || empty($payload['subject'])) { return false; }
        $sent = wp_mail($payload['to'], (string) $payload['subject'], (string) ($payload['message'] ?? ''), $payload['headers'] ?? '', $payload['attachments'] ?? []);
        do_action('elev8_os_notification_email_sent', $sent, $payload);
        return (bool) $sent;
    }
}
