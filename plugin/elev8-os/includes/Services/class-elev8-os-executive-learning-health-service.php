<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Read model for calibration coverage and measurement health.
 *
 * This service does not alter recommendations, outcomes, confidence, or source
 * records. It only explains whether enough measured evidence exists for the
 * Decision Learning service to calibrate future recommendations responsibly.
 */
final class Elev8_OS_Executive_Learning_Health_Service {
    private const CLASSIFICATIONS = ['risk', 'opportunity', 'decision', 'achievement', 'follow_up', 'information'];
    private const MIN_SAMPLE = 3;

    public static function init(): void {
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    /** @return array<string,mixed> */
    public static function report(int $organization_unit_id = 0): array {
        $rows = [];
        $totals = [
            'classifications' => 0,
            'ready' => 0,
            'developing' => 0,
            'no_evidence' => 0,
            'measured' => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'awaiting_measurement' => 0,
        ];

        foreach (self::CLASSIFICATIONS as $classification) {
            $row = self::classification_health($classification, $organization_unit_id);
            $rows[$classification] = $row;
            $totals['classifications']++;
            $totals[$row['health']]++;
            $totals['measured'] += $row['measured'];
            $totals['positive'] += $row['positive'];
            $totals['neutral'] += $row['neutral'];
            $totals['negative'] += $row['negative'];
            $totals['awaiting_measurement'] += $row['awaiting_measurement'];
        }

        $coverage = $totals['classifications'] > 0
            ? (int) round(($totals['ready'] / $totals['classifications']) * 100)
            : 0;

        return [
            'organization_unit_id' => $organization_unit_id,
            'scope_label' => self::scope_label($organization_unit_id),
            'minimum_sample' => self::MIN_SAMPLE,
            'coverage_percent' => $coverage,
            'totals' => $totals,
            'classifications' => $rows,
            'organizations' => self::organization_scopes(),
            'generated_at' => current_time('mysql'),
        ];
    }

    /** @return array<string,mixed> */
    public static function classification_health(string $classification, int $organization_unit_id = 0): array {
        $classification = sanitize_key($classification);
        $measured = self::measured_evidence($classification, $organization_unit_id);
        $awaiting = self::awaiting_measurement($classification, $organization_unit_id);
        $count = count($measured);
        $positive = count(array_filter($measured, static fn(array $item): bool => (float) $item['weight'] > 0));
        $neutral = count(array_filter($measured, static fn(array $item): bool => (float) $item['weight'] === 0.0));
        $negative = count(array_filter($measured, static fn(array $item): bool => (float) $item['weight'] < 0));
        $health = $count >= self::MIN_SAMPLE ? 'ready' : ($count > 0 ? 'developing' : 'no_evidence');

        return [
            'classification' => $classification,
            'label' => ucwords(str_replace('_', ' ', $classification)),
            'health' => $health,
            'measured' => $count,
            'positive' => $positive,
            'neutral' => $neutral,
            'negative' => $negative,
            'awaiting_measurement' => $awaiting,
            'needed_for_calibration' => max(0, self::MIN_SAMPLE - $count),
            'explanation' => self::health_explanation($health, $count, $awaiting),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function measured_evidence(string $classification, int $organization_unit_id): array {
        return array_merge(
            self::recommendation_outcomes($classification, $organization_unit_id),
            self::executive_outcomes($classification, $organization_unit_id)
        );
    }

    /** @return array<int,array<string,mixed>> */
    private static function recommendation_outcomes(string $classification, int $organization_unit_id): array {
        if (!class_exists('Elev8_OS_Recommendation_Outcome_Service') || !class_exists('Elev8_OS_Intelligence_Recommendation_Service')) { return []; }
        $meta = ['relation' => 'AND', ['key' => Elev8_OS_Recommendation_Outcome_Service::META_RESULT, 'compare' => 'EXISTS']];
        if ($organization_unit_id) {
            $meta[] = ['key' => Elev8_OS_Recommendation_Outcome_Service::META_ORGANIZATION, 'value' => $organization_unit_id, 'type' => 'NUMERIC'];
        }
        $ids = get_posts([
            'post_type' => Elev8_OS_Recommendation_Outcome_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'fields' => 'ids',
            'meta_query' => $meta,
        ]);
        $items = [];
        foreach ($ids as $id) {
            $result = (string) get_post_meta($id, Elev8_OS_Recommendation_Outcome_Service::META_RESULT, true);
            $weight = self::recommendation_weight($result);
            if ($weight === null) { continue; }
            $recommendation_id = absint(get_post_meta($id, Elev8_OS_Recommendation_Outcome_Service::META_RECOMMENDATION_ID, true));
            if (sanitize_key((string) get_post_meta($recommendation_id, Elev8_OS_Intelligence_Recommendation_Service::META_CLASSIFICATION, true)) !== $classification) { continue; }
            $items[] = ['source' => 'recommendation_outcome', 'source_id' => (int) $id, 'weight' => $weight];
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private static function executive_outcomes(string $classification, int $organization_unit_id): array {
        if (!class_exists('Elev8_OS_Executive_Decision_Effectiveness_Service') || !class_exists('Elev8_OS_Executive_Decision_Follow_Through_Service')) { return []; }
        $meta = ['relation' => 'AND', ['key' => '_elev8_effectiveness_result', 'compare' => 'EXISTS']];
        if ($organization_unit_id) {
            $meta[] = ['key' => '_elev8_organization_unit_id', 'value' => $organization_unit_id, 'type' => 'NUMERIC'];
        }
        $ids = get_posts([
            'post_type' => Elev8_OS_Executive_Decision_Effectiveness_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'fields' => 'ids',
            'meta_query' => $meta,
        ]);
        $items = [];
        foreach ($ids as $id) {
            $result = (string) get_post_meta($id, '_elev8_effectiveness_result', true);
            $weight = self::executive_weight($result);
            if ($weight === null) { continue; }
            $follow_id = absint(get_post_meta($id, '_elev8_follow_through_id', true));
            if (self::follow_through_classification($follow_id) !== $classification) { continue; }
            $items[] = ['source' => 'executive_decision_outcome', 'source_id' => (int) $id, 'weight' => $weight];
        }
        return $items;
    }

    private static function awaiting_measurement(string $classification, int $organization_unit_id): int {
        $count = 0;
        if (class_exists('Elev8_OS_Intelligence_Recommendation_Service') && class_exists('Elev8_OS_Recommendation_Outcome_Service')) {
            $meta = [
                'relation' => 'AND',
                ['key' => Elev8_OS_Intelligence_Recommendation_Service::META_CLASSIFICATION, 'value' => $classification],
                ['key' => Elev8_OS_Intelligence_Recommendation_Service::META_STATUS, 'value' => 'approved'],
            ];
            if ($organization_unit_id) {
                $meta[] = ['key' => Elev8_OS_Intelligence_Recommendation_Service::META_ORGANIZATION, 'value' => $organization_unit_id, 'type' => 'NUMERIC'];
            }
            $ids = get_posts([
                'post_type' => Elev8_OS_Intelligence_Recommendation_Service::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 1000,
                'fields' => 'ids',
                'meta_query' => $meta,
            ]);
            foreach ($ids as $recommendation_id) {
                $outcome = Elev8_OS_Recommendation_Outcome_Service::get_for_recommendation((int) $recommendation_id);
                if (!$outcome || in_array((string) ($outcome['result'] ?? 'unknown'), ['', 'unknown'], true)) { $count++; }
            }
        }

        if (class_exists('Elev8_OS_Executive_Decision_Effectiveness_Service')) {
            $meta = ['relation' => 'AND', ['key' => '_elev8_effectiveness_result', 'value' => 'unknown']];
            if ($organization_unit_id) {
                $meta[] = ['key' => '_elev8_organization_unit_id', 'value' => $organization_unit_id, 'type' => 'NUMERIC'];
            }
            $ids = get_posts([
                'post_type' => Elev8_OS_Executive_Decision_Effectiveness_Service::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 1000,
                'fields' => 'ids',
                'meta_query' => $meta,
            ]);
            foreach ($ids as $id) {
                $follow_id = absint(get_post_meta($id, '_elev8_follow_through_id', true));
                if (self::follow_through_classification($follow_id) === $classification) { $count++; }
            }
        }
        return $count;
    }

    private static function follow_through_classification(int $follow_id): string {
        if (!$follow_id) { return ''; }
        $source_type = (string) get_post_meta($follow_id, '_elev8_source_type', true);
        $source_id = absint(get_post_meta($follow_id, '_elev8_source_id', true));
        if ($source_type === 'recommendation' && class_exists('Elev8_OS_Intelligence_Recommendation_Service')) {
            return sanitize_key((string) get_post_meta($source_id, Elev8_OS_Intelligence_Recommendation_Service::META_CLASSIFICATION, true));
        }
        if ($source_type === 'pattern' && class_exists('Elev8_OS_Pattern_Detection_Service')) {
            $pattern = Elev8_OS_Pattern_Detection_Service::get($source_id);
            return sanitize_key((string) ($pattern['classification'] ?? ''));
        }
        return '';
    }

    /** @return array<int,array<string,mixed>> */
    private static function organization_scopes(): array {
        $ids = [];
        foreach ([
            [Elev8_OS_Intelligence_Recommendation_Service::POST_TYPE, Elev8_OS_Intelligence_Recommendation_Service::META_ORGANIZATION],
            [Elev8_OS_Recommendation_Outcome_Service::POST_TYPE, Elev8_OS_Recommendation_Outcome_Service::META_ORGANIZATION],
            [Elev8_OS_Executive_Decision_Effectiveness_Service::POST_TYPE, '_elev8_organization_unit_id'],
        ] as $source) {
            if (!post_type_exists($source[0])) { continue; }
            $posts = get_posts(['post_type' => $source[0], 'post_status' => 'publish', 'posts_per_page' => 1000, 'fields' => 'ids']);
            foreach ($posts as $post_id) {
                $id = absint(get_post_meta($post_id, $source[1], true));
                if ($id) { $ids[$id] = $id; }
            }
        }
        $items = [];
        foreach ($ids as $id) { $items[] = ['id' => $id, 'label' => self::scope_label($id)]; }
        usort($items, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        return $items;
    }

    private static function scope_label(int $organization_unit_id): string {
        if (!$organization_unit_id) { return __('All organization scopes', 'elev8-os'); }
        $title = get_the_title($organization_unit_id);
        return $title ? $title : sprintf(__('Organization #%d', 'elev8-os'), $organization_unit_id);
    }

    private static function health_explanation(string $health, int $measured, int $awaiting): string {
        if ($health === 'ready') {
            return sprintf(__('Calibration ready with %1$d measured outcomes. %2$d outcomes still await measurement.', 'elev8-os'), $measured, $awaiting);
        }
        if ($health === 'developing') {
            return sprintf(__('Learning is developing with %1$d measured outcomes. %2$d more are required; %3$d await measurement.', 'elev8-os'), $measured, max(0, self::MIN_SAMPLE - $measured), $awaiting);
        }
        return sprintf(__('No measured evidence is available yet. %d outcomes await measurement.', 'elev8-os'), $awaiting);
    }

    private static function recommendation_weight(string $result): ?float {
        $weights = ['successful' => 1.0, 'partial' => 0.5, 'no_change' => 0.0, 'unsuccessful' => -1.0];
        return array_key_exists($result, $weights) ? $weights[$result] : null;
    }

    private static function executive_weight(string $result): ?float {
        $weights = ['effective' => 1.0, 'partial' => 0.5, 'no_change' => 0.0, 'ineffective' => -1.0];
        return array_key_exists($result, $weights) ? $weights[$result] : null;
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['measured_outcomes_explain_learning_health'] = [
            'label' => __('Explains intelligence learning health', 'elev8-os'),
            'from' => ['recommendation_outcome', 'executive_decision_outcome'],
            'to' => ['recommendation'],
            'directional' => true,
            'notes' => __('A governed read model explains calibration coverage and missing measurements without changing confidence or source evidence.', 'elev8-os'),
        ];
        return $relationships;
    }
}
