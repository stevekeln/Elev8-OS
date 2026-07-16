<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Converts verified business facts into a focused, reusable growth plan.
 * The service is intentionally UI-agnostic so reports and future AI coaches
 * can consume the same priorities as the Artist Business Center.
 */
final class Elev8_OS_Growth_Plan_Service {
    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    public static function build(array $snapshot): array {
        $score = is_array($snapshot['score'] ?? null) ? $snapshot['score'] : [];
        $components = is_array($score['components'] ?? null) ? $score['components'] : [];
        $recommendations = is_array($snapshot['recommendations'] ?? null) ? $snapshot['recommendations'] : [];

        $ranked = [];
        foreach ($components as $key => $component) {
            if (empty($component['available']) || !is_numeric($component['score'] ?? null)) { continue; }
            $ranked[] = [
                'key' => sanitize_key((string) $key),
                'label' => self::component_label((string) $key),
                'score' => max(0, min(100, (int) $component['score'])),
                'weight' => (int) ($component['weight'] ?? 0),
            ];
        }
        usort($ranked, static function(array $a, array $b): int {
            if ($a['score'] === $b['score']) { return $b['weight'] <=> $a['weight']; }
            return $a['score'] <=> $b['score'];
        });

        $priorities = [];
        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) { continue; }
            $priorities[] = [
                'title' => sanitize_text_field((string) ($recommendation['title'] ?? '')),
                'message' => sanitize_text_field((string) ($recommendation['message'] ?? '')),
                'action' => sanitize_key((string) ($recommendation['action'] ?? 'dashboard')),
                'priority' => sanitize_key((string) ($recommendation['priority'] ?? 'medium')),
            ];
        }

        return [
            'focus' => $ranked[0] ?? null,
            'components' => $ranked,
            'priorities' => array_slice($priorities, 0, 3),
            'completed_count' => count(array_filter($ranked, static fn(array $item): bool => $item['score'] >= 80)),
        ];
    }

    private static function component_label(string $key): string {
        $labels = [
            'profile' => __('Profile', 'elev8-os'),
            'artwork' => __('Artwork', 'elev8-os'),
            'classes' => __('Classes', 'elev8-os'),
            'sales' => __('Sales', 'elev8-os'),
            'engagement' => __('Engagement', 'elev8-os'),
            'website' => __('Website', 'elev8-os'),
        ];
        return $labels[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
