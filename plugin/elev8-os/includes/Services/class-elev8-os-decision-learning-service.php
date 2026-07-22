<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Calibrates recommendation confidence from measured, organization-specific outcomes.
 *
 * This is an explainable read model. It never rewrites historical outcomes, Patterns,
 * Observations, or source records, and it never authorizes execution.
 */
final class Elev8_OS_Decision_Learning_Service {
    private const MIN_SAMPLE = 3;
    private const MAX_ADJUSTMENT = 15;

    public static function init(): void {
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    /**
     * @return array<string,mixed>
     */
    public static function calibrate(int $base_confidence, string $classification, int $organization_unit_id = 0): array {
        $base_confidence = max(0, min(100, $base_confidence));
        $classification = sanitize_key($classification);
        $evidence = self::evidence($classification, $organization_unit_id);
        $sample_size = count($evidence);

        if ($sample_size < self::MIN_SAMPLE) {
            return [
                'base_confidence' => $base_confidence,
                'calibrated_confidence' => $base_confidence,
                'adjustment' => 0,
                'sample_size' => $sample_size,
                'positive' => self::count_positive($evidence),
                'negative' => self::count_negative($evidence),
                'scope' => $organization_unit_id ? 'organization' : 'platform',
                'classification' => $classification,
                'status' => 'insufficient_evidence',
                'explanation' => sprintf(
                    __('Confidence remains at %1$d%% because only %2$d measured comparable outcomes are available; at least %3$d are required.', 'elev8-os'),
                    $base_confidence,
                    $sample_size,
                    self::MIN_SAMPLE
                ),
            ];
        }

        $weighted_total = 0.0;
        foreach ($evidence as $item) { $weighted_total += (float) $item['weight']; }
        $average = $weighted_total / $sample_size; // -1.0 to 1.0.
        $evidence_strength = min(1.0, $sample_size / 10);
        $adjustment = (int) round($average * self::MAX_ADJUSTMENT * $evidence_strength);
        $calibrated = max(0, min(100, $base_confidence + $adjustment));

        return [
            'base_confidence' => $base_confidence,
            'calibrated_confidence' => $calibrated,
            'adjustment' => $adjustment,
            'sample_size' => $sample_size,
            'positive' => self::count_positive($evidence),
            'negative' => self::count_negative($evidence),
            'scope' => $organization_unit_id ? 'organization' : 'platform',
            'classification' => $classification,
            'status' => 'calibrated',
            'explanation' => self::explanation($base_confidence, $calibrated, $adjustment, $sample_size, $organization_unit_id),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function evidence(string $classification, int $organization_unit_id): array {
        $evidence = [];
        $evidence = array_merge($evidence, self::recommendation_outcomes($classification, $organization_unit_id));
        $evidence = array_merge($evidence, self::executive_outcomes($classification, $organization_unit_id));
        return $evidence;
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
            'posts_per_page' => 500,
            'fields' => 'ids',
            'meta_query' => $meta,
        ]);
        $items = [];
        foreach ($ids as $id) {
            $result = (string) get_post_meta($id, Elev8_OS_Recommendation_Outcome_Service::META_RESULT, true);
            $weight = self::recommendation_weight($result);
            if ($weight === null) { continue; }
            $recommendation_id = absint(get_post_meta($id, Elev8_OS_Recommendation_Outcome_Service::META_RECOMMENDATION_ID, true));
            $source_classification = sanitize_key((string) get_post_meta($recommendation_id, Elev8_OS_Intelligence_Recommendation_Service::META_CLASSIFICATION, true));
            if ($source_classification !== $classification) { continue; }
            $items[] = ['source' => 'recommendation_outcome', 'source_id' => (int) $id, 'result' => $result, 'weight' => $weight];
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
            'posts_per_page' => 500,
            'fields' => 'ids',
            'meta_query' => $meta,
        ]);
        $items = [];
        foreach ($ids as $id) {
            $result = (string) get_post_meta($id, '_elev8_effectiveness_result', true);
            $weight = self::executive_weight($result);
            if ($weight === null) { continue; }
            $follow_id = absint(get_post_meta($id, '_elev8_follow_through_id', true));
            $source_type = (string) get_post_meta($follow_id, '_elev8_source_type', true);
            $source_id = absint(get_post_meta($follow_id, '_elev8_source_id', true));
            $source_classification = '';
            if ($source_type === 'recommendation' && class_exists('Elev8_OS_Intelligence_Recommendation_Service')) {
                $source_classification = sanitize_key((string) get_post_meta($source_id, Elev8_OS_Intelligence_Recommendation_Service::META_CLASSIFICATION, true));
            } elseif ($source_type === 'pattern' && class_exists('Elev8_OS_Pattern_Detection_Service')) {
                $source = Elev8_OS_Pattern_Detection_Service::get($source_id);
                $source_classification = sanitize_key((string) ($source['classification'] ?? ''));
            }
            if ($source_classification !== $classification) { continue; }
            $items[] = ['source' => 'executive_decision_outcome', 'source_id' => (int) $id, 'result' => $result, 'weight' => $weight];
        }
        return $items;
    }

    private static function recommendation_weight(string $result): ?float {
        $weights = ['successful' => 1.0, 'partial' => 0.5, 'no_change' => 0.0, 'unsuccessful' => -1.0];
        return array_key_exists($result, $weights) ? $weights[$result] : null;
    }

    private static function executive_weight(string $result): ?float {
        $weights = ['effective' => 1.0, 'partial' => 0.5, 'no_change' => 0.0, 'ineffective' => -1.0];
        return array_key_exists($result, $weights) ? $weights[$result] : null;
    }

    /** @param array<int,array<string,mixed>> $evidence */
    private static function count_positive(array $evidence): int {
        return count(array_filter($evidence, static fn(array $item): bool => (float) $item['weight'] > 0));
    }

    /** @param array<int,array<string,mixed>> $evidence */
    private static function count_negative(array $evidence): int {
        return count(array_filter($evidence, static fn(array $item): bool => (float) $item['weight'] < 0));
    }

    private static function explanation(int $base, int $calibrated, int $adjustment, int $sample_size, int $organization_unit_id): string {
        $direction = $adjustment > 0 ? __('increased', 'elev8-os') : ($adjustment < 0 ? __('decreased', 'elev8-os') : __('remained unchanged', 'elev8-os'));
        $scope = $organization_unit_id ? __('this organization', 'elev8-os') : __('the available platform history', 'elev8-os');
        return sprintf(
            __('Confidence %1$s from %2$d%% to %3$d%% using %4$d measured comparable outcomes from %5$s. The maximum evidence adjustment is ±%6$d points.', 'elev8-os'),
            $direction,
            $base,
            $calibrated,
            $sample_size,
            $scope,
            self::MAX_ADJUSTMENT
        );
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['measured_outcomes_calibrate_recommendations'] = [
            'label' => __('Calibrates future recommendation confidence', 'elev8-os'),
            'from' => ['recommendation_outcome', 'executive_decision_outcome'],
            'to' => ['recommendation'],
            'directional' => true,
            'notes' => __('Measured organization-specific outcomes provide explainable confidence calibration without rewriting historical evidence or authorizing action.', 'elev8-os'),
        ];
        return $relationships;
    }
}
