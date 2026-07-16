<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Builds the artist's daily command-center view from the verified business snapshot.
 * This service contains no presentation markup so future mobile, AI, and API clients can reuse it.
 */
final class Elev8_OS_Command_Center_Service {
    private const SCORE_META = 'elev8_os_business_score_history';

    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    public static function build(WP_User $user, array $snapshot): array {
        $score = isset($snapshot['score']['score']) && is_numeric($snapshot['score']['score'])
            ? (int) $snapshot['score']['score']
            : null;

        $momentum = self::score_momentum((int) $user->ID, $score);
        $activity = self::activity_feed($snapshot);
        $priorities = array_slice((array) ($snapshot['recommendations'] ?? []), 0, 3);

        return [
            'generated_at' => current_time('mysql'),
            'score_momentum' => $momentum,
            'priorities' => $priorities,
            'activity' => $activity,
            'activity_available' => !empty($activity),
        ];
    }

    /** @return array<string,mixed> */
    private static function score_momentum(int $user_id, ?int $score): array {
        if ($score === null || $user_id <= 0) {
            return ['available' => false, 'current' => null, 'previous' => null, 'change' => null, 'direction' => 'unknown'];
        }

        $history = get_user_meta($user_id, self::SCORE_META, true);
        $history = is_array($history) ? $history : [];
        $today = wp_date('Y-m-d');
        $previous = null;

        foreach (array_reverse($history, true) as $date => $value) {
            if ((string) $date !== $today && is_numeric($value)) {
                $previous = (int) $value;
                break;
            }
        }

        $history[$today] = $score;
        if (count($history) > 45) {
            $history = array_slice($history, -45, null, true);
        }
        update_user_meta($user_id, self::SCORE_META, $history);

        $change = $previous === null ? null : $score - $previous;
        $direction = $change === null ? 'new' : ($change > 0 ? 'up' : ($change < 0 ? 'down' : 'steady'));

        return [
            'available' => true,
            'current' => $score,
            'previous' => $previous,
            'change' => $change,
            'direction' => $direction,
        ];
    }

    /** @param array<string,mixed> $snapshot @return array<int,array<string,mixed>> */
    private static function activity_feed(array $snapshot): array {
        $items = [];
        $sales = (array) ($snapshot['sales'] ?? []);
        $assets = (array) ($snapshot['assets'] ?? []);
        $upcoming = (array) ($snapshot['upcoming'] ?? []);
        $achievements = (array) ($snapshot['achievements'] ?? []);

        foreach ((array) ($sales['recent'] ?? []) as $sale) {
            $items[] = [
                'type' => 'sale',
                'icon' => 'cart',
                'title' => sprintf(__('Artwork sold: %s', 'elev8-os'), (string) ($sale['title'] ?? __('Artwork', 'elev8-os'))),
                'detail' => isset($sale['total']) && is_numeric($sale['total']) ? self::money((float) $sale['total']) : __('Paid order', 'elev8-os'),
                'timestamp' => (int) ($sale['date'] ?? 0),
            ];
        }

        if (!empty($upcoming[0])) {
            $next = (array) $upcoming[0];
            $timestamp = strtotime((string) ($next['start'] ?? '')) ?: 0;
            $items[] = [
                'type' => 'class',
                'icon' => 'calendar-alt',
                'title' => sprintf(__('Upcoming class: %s', 'elev8-os'), (string) ($next['name'] ?? __('Class', 'elev8-os'))),
                'detail' => $timestamp ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : __('Schedule verified in Amelia', 'elev8-os'),
                'timestamp' => $timestamp,
            ];
        }

        if (!empty($assets['most_viewed']) && is_array($assets['most_viewed'])) {
            $asset = $assets['most_viewed'];
            $views = (int) ($asset['public_view_count'] ?? 0);
            if ($views > 0) {
                $items[] = [
                    'type' => 'engagement',
                    'icon' => 'visibility',
                    'title' => sprintf(__('Most viewed artwork: %s', 'elev8-os'), (string) ($asset['title'] ?? __('Artwork', 'elev8-os'))),
                    'detail' => sprintf(_n('%s verified view', '%s verified views', $views, 'elev8-os'), number_format_i18n($views)),
                    'timestamp' => 0,
                ];
            }
        }

        $qr_scans = (int) ($assets['qr_scans'] ?? 0);
        if ($qr_scans > 0) {
            $items[] = [
                'type' => 'qr',
                'icon' => 'smartphone',
                'title' => __('Customers are scanning your artwork', 'elev8-os'),
                'detail' => sprintf(_n('%s verified QR scan', '%s verified QR scans', $qr_scans, 'elev8-os'), number_format_i18n($qr_scans)),
                'timestamp' => 0,
            ];
        }

        foreach ($achievements as $achievement) {
            if (empty($achievement['earned'])) { continue; }
            $items[] = [
                'type' => 'achievement',
                'icon' => 'awards',
                'title' => sprintf(__('Achievement unlocked: %s', 'elev8-os'), (string) ($achievement['title'] ?? __('Milestone', 'elev8-os'))),
                'detail' => __('Your business reached this verified milestone.', 'elev8-os'),
                'timestamp' => 0,
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
        });

        return array_slice($items, 0, 8);
    }

    private static function money(float $value): string {
        if (function_exists('wc_price')) {
            return wp_strip_all_tags((string) wc_price($value));
        }
        return '$' . number_format_i18n($value, 2);
    }
}
