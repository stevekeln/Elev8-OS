<?php
if (!defined('ABSPATH')) { exit; }

/** Converts verified gaps into prioritized, explainable revenue opportunities. */
final class Elev8_OS_Opportunity_Engine {
    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    public static function build(array $snapshot): array {
        $items = [];
        $classes = (array) ($snapshot['classes'] ?? []);
        $assets = (array) ($snapshot['assets'] ?? []);
        $sales = (array) ($snapshot['sales'] ?? []);
        $profile = (array) ($snapshot['profile'] ?? []);
        $booked = is_numeric($classes['booked_value'] ?? null) ? (float) $classes['booked_value'] : null;
        $students = max(0, (int) ($classes['student_count'] ?? 0));
        $avg_seat = ($booked !== null && $students > 0) ? $booked / $students : null;
        $seats = is_numeric($classes['seats_available'] ?? null) ? max(0, (int) $classes['seats_available']) : null;

        if ($seats !== null && $seats > 0) {
            $estimate = $avg_seat !== null ? $avg_seat * min($seats, 4) : null;
            $items[] = self::item('fill_classes', __('Fill open class seats', 'elev8-os'), sprintf(_n('%d verified seat is still available.', '%d verified seats are still available.', $seats, 'elev8-os'), $seats), $estimate, 'classes', 'high');
        }
        if ((int) ($assets['available'] ?? 0) > 0) {
            $items[] = self::item('promote_artwork', __('Feature available artwork', 'elev8-os'), sprintf(_n('%d piece is available to promote.', '%d pieces are available to promote.', (int) $assets['available'], 'elev8-os'), (int) $assets['available']), null, 'artwork', 'medium');
        }
        if ((int) ($assets['qr_scans'] ?? 0) === 0 && (int) ($assets['public'] ?? 0) > 0) {
            $items[] = self::item('qr', __('Put artwork QR codes to work', 'elev8-os'), __('Give in-person visitors a direct path to the artwork story and purchase page.', 'elev8-os'), null, 'artwork', 'medium');
        }
        if (empty($profile['complete'])) {
            $items[] = self::item('profile', __('Finish the public artist profile', 'elev8-os'), __('A complete profile improves customer confidence before they book or buy.', 'elev8-os'), null, 'website', 'medium');
        }
        if (!empty($sales['available']) && (int) ($sales['count_month'] ?? 0) === 0 && (int) ($assets['views'] ?? 0) > 0) {
            $items[] = self::item('convert_views', __('Turn artwork views into a sale', 'elev8-os'), __('Customers are viewing the work, but no paid artwork sale is verified this month.', 'elev8-os'), null, 'artwork', 'high');
        }

        $known = 0.0; $known_count = 0;
        foreach ($items as $item) { if ($item['estimated_value'] !== null) { $known += (float) $item['estimated_value']; $known_count++; } }
        return ['items'=>array_slice($items,0,6),'known_total'=>$known_count ? $known : null,'estimate_note'=>__('Dollar values appear only when verified price and capacity data support the calculation.', 'elev8-os')];
    }

    private static function item(string $id,string $title,string $reason,?float $value,string $action,string $priority): array {
        return ['id'=>$id,'title'=>$title,'reason'=>$reason,'estimated_value'=>$value,'action'=>$action,'priority'=>$priority];
    }
}
