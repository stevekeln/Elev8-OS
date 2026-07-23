<?php
/** Reusable trust layer for plain-language “Why?” explanations. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Explanation_Service {
    /** @return array<string,string> */
    public static function workflow_health(array $summary): array {
        $runs = (int)($summary['runs'] ?? 0);
        $failed = (int)($summary['failed'] ?? 0);
        return [
            'title' => __('Why this workflow status?', 'elev8-os'),
            'body' => sprintf(
                __('Elev8 OS found %1$d registered workflows and %2$d workflow runs during the last %3$d days. %4$d runs failed. Only events published to the shared Workflow Engine are included.', 'elev8-os'),
                (int)($summary['active_workflows'] ?? 0), $runs, (int)($summary['period_days'] ?? 7), $failed
            ),
        ];
    }

    /** @return array<string,string> */
    public static function metric(string $label, array $metric): array {
        $confidence = sanitize_text_field((string)($metric['confidence'] ?? __('Unavailable', 'elev8-os')));
        $diagnostic = sanitize_text_field((string)($metric['diagnostic'] ?? ''));
        return [
            'title' => sprintf(__('Why this %s?', 'elev8-os'), $label),
            'body' => $diagnostic !== ''
                ? sprintf(__('%1$s Confidence: %2$s.', 'elev8-os'), $diagnostic, $confidence)
                : sprintf(__('This value comes from the verified Business Intelligence service. Confidence: %s. Missing source data is shown as Unavailable.', 'elev8-os'), $confidence),
        ];
    }
}
