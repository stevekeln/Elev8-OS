<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Converts verified Elev8 OS business facts into a motivating Artist Success model.
 * This service never manufactures trends or comparisons when history is unavailable.
 */
final class Elev8_OS_Artist_Success_Service {
    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    public static function build(WP_User $user, array $snapshot): array {
        $gps = Elev8_OS_Business_GPS_Service::build($user, $snapshot);
        $timeline = (array) ($gps['timeline'] ?? []);
        $score = isset($gps['score']) && is_numeric($gps['score']) ? (int) $gps['score'] : null;
        $wins = self::recent_wins($timeline);
        $journey = self::journey($score, (array) ($snapshot['achievements'] ?? []));
        $plan = array_slice((array) ($gps['opportunities']['items'] ?? []), 0, 3);
        $weekly_target = 5;
        $weekly_progress = min($weekly_target, self::events_this_week($timeline));

        return [
            'greeting' => self::greeting(),
            'first_name' => self::first_name($user, $snapshot),
            'headline' => self::headline($snapshot, $wins),
            'momentum' => self::momentum($snapshot, $timeline),
            'weekly_goal' => [
                'current' => $weekly_progress,
                'target' => $weekly_target,
                'percent' => (int) round(($weekly_progress / $weekly_target) * 100),
                'remaining' => max(0, $weekly_target - $weekly_progress),
            ],
            'wins' => $wins,
            'journey' => $journey,
            'thirty_minute_plan' => $plan,
            'estimated_impact' => self::known_total($plan),
        ];
    }

    /** @param array<int,WP_User> $users @return array<string,mixed> */
    public static function gallery_health(array $users): array {
        $scores = [];
        $categories = [];
        $attention = 0;
        foreach ($users as $user) {
            if (!($user instanceof WP_User)) { continue; }
            $snapshot = Elev8_OS_Artist_Business_Service::get_snapshot($user);
            $score = $snapshot['score']['score'] ?? null;
            if (is_numeric($score)) { $scores[] = (int) $score; }
            foreach ((array) ($snapshot['score']['components'] ?? []) as $key => $component) {
                if (is_numeric($component['score'] ?? null)) { $categories[$key][] = (int) $component['score']; }
            }
            if ((int) ($score ?? 0) < 70) { $attention++; }
        }
        $health = $scores ? (int) round(array_sum($scores) / count($scores)) : null;
        $category_scores = [];
        foreach ($categories as $key => $values) {
            $category_scores[$key] = (int) round(array_sum($values) / count($values));
        }
        return ['score' => $health, 'label' => self::health_label($health), 'categories' => $category_scores, 'attention' => $attention, 'artists' => count($scores)];
    }

    private static function first_name(WP_User $user, array $snapshot): string {
        $artist = is_array($snapshot['artist'] ?? null) ? $snapshot['artist'] : [];
        $name = trim((string) ($artist['firstName'] ?? $user->first_name));
        return $name !== '' ? $name : ($user->display_name ?: __('Artist', 'elev8-os'));
    }
    private static function greeting(): string {
        $hour = (int) current_time('G');
        if ($hour < 12) { return __('Good morning', 'elev8-os'); }
        if ($hour < 18) { return __('Good afternoon', 'elev8-os'); }
        return __('Good evening', 'elev8-os');
    }
    private static function headline(array $snapshot, array $wins): string {
        if (!empty($wins)) { return __('Your business is moving. Take a moment to celebrate the progress below.', 'elev8-os'); }
        if ((int) ($snapshot['classes']['upcoming_count'] ?? 0) > 0) { return __('You have upcoming opportunities. One focused action today can build momentum.', 'elev8-os'); }
        return __('Your next win starts with one clear action today.', 'elev8-os');
    }
    private static function momentum(array $snapshot, array $timeline): array {
        $week = self::events_this_week($timeline);
        if ($week >= 5) { return ['key' => 'growing', 'label' => __('Growing', 'elev8-os'), 'message' => __('You have strong verified activity this week.', 'elev8-os')]; }
        if ($week >= 2) { return ['key' => 'steady', 'label' => __('Steady', 'elev8-os'), 'message' => __('Your business is active. Complete the next recommended action to keep moving.', 'elev8-os')]; }
        if ((int) ($snapshot['score']['score'] ?? 0) >= 70) { return ['key' => 'steady', 'label' => __('Stable', 'elev8-os'), 'message' => __('Your foundation is healthy, but recent verified activity is limited.', 'elev8-os')]; }
        return ['key' => 'attention', 'label' => __('Needs attention', 'elev8-os'), 'message' => __('Start with the first action in your 30-minute plan.', 'elev8-os')];
    }
    private static function events_this_week(array $timeline): int {
        $start = strtotime('monday this week', current_time('timestamp'));
        $count = 0;
        foreach ($timeline as $event) { if ((int) ($event['timestamp'] ?? 0) >= $start) { $count++; } }
        return $count;
    }
    private static function recent_wins(array $timeline): array {
        $wins = [];
        foreach ($timeline as $event) {
            if (in_array((string) ($event['type'] ?? ''), ['sale','class','achievement','artwork','activity'], true)) { $wins[] = $event; }
            if (count($wins) >= 3) { break; }
        }
        return $wins;
    }
    private static function journey(?int $score, array $achievements): array {
        $earned = count(array_filter($achievements, static fn($a) => !empty($a['earned'])));
        $levels = [
            ['name' => __('Beginning Artist', 'elev8-os'), 'min' => 0],
            ['name' => __('Teaching Artist', 'elev8-os'), 'min' => 35],
            ['name' => __('Professional Artist', 'elev8-os'), 'min' => 55],
            ['name' => __('Featured Artist', 'elev8-os'), 'min' => 70],
            ['name' => __('Community Leader', 'elev8-os'), 'min' => 85],
            ['name' => __('Master Artist', 'elev8-os'), 'min' => 95],
        ];
        $effective = min(100, (int) ($score ?? 0) + min(10, $earned * 2));
        $current = $levels[0]; $next = null;
        foreach ($levels as $index => $level) {
            if ($effective >= $level['min']) { $current = $level; $next = $levels[$index + 1] ?? null; }
        }
        return ['current' => $current['name'], 'next' => $next['name'] ?? null, 'progress' => $effective, 'levels' => $levels];
    }
    private static function known_total(array $items): ?float {
        $total = 0.0; $known = false;
        foreach ($items as $item) { if (is_numeric($item['estimated_value'] ?? null)) { $total += (float) $item['estimated_value']; $known = true; } }
        return $known ? $total : null;
    }
    private static function health_label(?int $score): string {
        if ($score === null) { return __('Unavailable', 'elev8-os'); }
        if ($score >= 85) { return __('Excellent', 'elev8-os'); }
        if ($score >= 70) { return __('Strong', 'elev8-os'); }
        if ($score >= 50) { return __('Improving', 'elev8-os'); }
        return __('Needs attention', 'elev8-os');
    }
}
