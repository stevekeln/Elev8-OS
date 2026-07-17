<?php
/**
 * Reusable, transparent business recommendation engine.
 *
 * Business Intelligence and trusted platform services supply facts. Rule
 * providers turn those facts into ranked actions. Dashboards, reports,
 * notifications, and a future LLM coach can all consume the same result.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Recommendation_Service {
    private const PROFILES_OPTION = 'elev8_os_artist_profiles';

    /**
     * Return ranked recommendations for one WordPress artist account.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_recommendations(WP_User $user, int $limit = 5): array {
        $context = self::build_context($user);
        $recommendations = [];

        foreach (self::providers() as $provider) {
            $items = call_user_func($provider, $context);
            if (!is_array($items)) { continue; }
            foreach ($items as $item) {
                $normalized = self::normalize($item);
                if ($normalized !== null) { $recommendations[$normalized['id']] = $normalized; }
            }
        }

        /**
         * Filter generated recommendations before ranking.
         *
         * @param array<string,array<string,mixed>> $recommendations
         * @param array<string,mixed>               $context
         * @param WP_User                           $user
         */
        $recommendations = apply_filters('elev8_os_recommendations', $recommendations, $context, $user);
        if (!is_array($recommendations)) { return []; }

        $recommendations = array_values(array_filter($recommendations, 'is_array'));
        usort($recommendations, [__CLASS__, 'compare']);

        return array_slice($recommendations, 0, max(1, min(25, $limit)));
    }

    /** @return array<string,mixed> */
    public static function build_context(WP_User $user): array {
        $artist = class_exists('Elev8_OS_Identity_Service')
            ? Elev8_OS_Identity_Service::artist_for_user($user)
            : null;
        $artist_id = is_array($artist) ? absint($artist['id'] ?? 0) : 0;

        $profiles = get_option(self::PROFILES_OPTION, []);
        $profile = ($artist_id > 0 && is_array($profiles) && isset($profiles[$artist_id]) && is_array($profiles[$artist_id]))
            ? $profiles[$artist_id]
            : [];

        $assets = class_exists('Elev8_OS_Asset_Service')
            ? Elev8_OS_Asset_Service::get_for_owner((int) $user->ID)
            : [];

        $asset_summary = [
            'total' => count($assets), 'public' => 0, 'available' => 0,
            'sold' => 0, 'featured' => 0, 'incomplete' => 0,
            'high_interest_unsold' => [],
        ];
        foreach ($assets as $asset) {
            $status = sanitize_key((string) ($asset['status'] ?? ''));
            if ((int) ($asset['public_visibility'] ?? 0) === 1) { $asset_summary['public']++; }
            if ($status === 'available') { $asset_summary['available']++; }
            if ($status === 'sold') { $asset_summary['sold']++; }
            if ((int) ($asset['is_featured'] ?? 0) === 1) { $asset_summary['featured']++; }
            if (class_exists('Elev8_OS_Asset_Service') && Elev8_OS_Asset_Service::calculate_completeness($asset) < 70) {
                $asset_summary['incomplete']++;
            }
            $views = absint($asset['public_view_count'] ?? 0);
            $scans = absint($asset['qr_scan_count'] ?? 0);
            if ($status === 'available' && ($views + $scans) >= 10) {
                $asset_summary['high_interest_unsold'][] = [
                    'id' => absint($asset['id'] ?? 0),
                    'title' => sanitize_text_field((string) ($asset['title'] ?? __('Artwork', 'elev8-os'))),
                    'views' => $views,
                    'scans' => $scans,
                ];
            }
        }
        usort($asset_summary['high_interest_unsold'], static function(array $a, array $b): int {
            return (($b['views'] + $b['scans']) <=> ($a['views'] + $a['scans']));
        });

        $class_snapshot = class_exists('Elev8_OS_My_Classes_Module')
            ? Elev8_OS_My_Classes_Module::get_dashboard_snapshot($user)
            : ['available' => false, 'summary' => [], 'upcoming' => []];

        return [
            'user' => $user,
            'artist' => $artist,
            'artist_id' => $artist_id,
            'profile' => $profile,
            'profile_completeness' => self::profile_completeness($profile),
            'assets' => $assets,
            'asset_summary' => $asset_summary,
            'classes' => $class_snapshot,
            'urls' => [
                'artwork' => self::portal_url('artwork'),
                'classes' => self::portal_url('classes'),
                'website' => self::portal_url('website'),
                'edit_website' => self::portal_url('edit_website'),
            ],
        ];
    }

    /** @return array<int,callable> */
    private static function providers(): array {
        $providers = [
            [__CLASS__, 'profile_recommendations'],
            [__CLASS__, 'artwork_recommendations'],
            [__CLASS__, 'class_recommendations'],
            [__CLASS__, 'sales_recommendations'],
        ];
        $filtered = apply_filters('elev8_os_recommendation_providers', $providers);
        return is_array($filtered) ? array_values(array_filter($filtered, 'is_callable')) : $providers;
    }

    /** @return array<int,array<string,mixed>> */
    public static function profile_recommendations(array $context): array {
        $profile = $context['profile'];
        $score = (int) $context['profile_completeness'];
        $items = [];
        if ($score < 100) {
            $missing = [];
            if (trim((string) ($profile['bio'] ?? '')) === '') { $missing[] = __('artist story', 'elev8-os'); }
            if (trim((string) ($profile['profile_photo'] ?? '')) === '') { $missing[] = __('profile photo', 'elev8-os'); }
            if (trim((string) ($profile['cover_image'] ?? '')) === '') { $missing[] = __('cover image', 'elev8-os'); }
            if (trim((string) ($profile['social_1_url'] ?? $profile['social'] ?? '')) === '') { $missing[] = __('social link', 'elev8-os'); }
            $items[] = self::make(
                'profile.complete',
                __('Complete your artist profile', 'elev8-os'),
                $missing ? sprintf(__('Add your missing %s so customers can better understand and trust your work.', 'elev8-os'), implode(', ', array_slice($missing, 0, 3))) : __('Review your artist profile and finish the remaining public information.', 'elev8-os'),
                $score < 50 ? 'high' : 'medium',
                'profile',
                $score < 50 ? 88 : 64,
                __('Improve customer trust', 'elev8-os'),
                __('This appears because your verified profile completeness is %d%%.', 'elev8-os'),
                [$score],
                __('Update Profile', 'elev8-os'),
                $context['urls']['edit_website']
            );
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    public static function artwork_recommendations(array $context): array {
        $summary = $context['asset_summary'];
        $items = [];
        if ((int) $summary['total'] === 0) {
            $items[] = self::make('artwork.first', __('Add your first artwork', 'elev8-os'), __('Create one asset record to power your storefront, inventory, QR page, and WooCommerce checkout.', 'elev8-os'), 'high', 'artwork', 100, __('Create something customers can buy', 'elev8-os'), __('This appears because no artwork records were found for your account.', 'elev8-os'), [], __('Add Artwork', 'elev8-os'), $context['urls']['artwork'] . '#elev8-artwork-editor');
        } elseif ((int) $summary['available'] === 0) {
            $items[] = self::make('artwork.available', __('Make artwork available for sale', 'elev8-os'), __('You have artwork records, but none are currently verified as available.', 'elev8-os'), 'high', 'inventory', 95, __('Restore products to your storefront', 'elev8-os'), __('This appears because the Asset Engine found zero available items.', 'elev8-os'), [], __('Review Artwork', 'elev8-os'), $context['urls']['artwork']);
        }
        if ((int) $summary['incomplete'] > 0) {
            $items[] = self::make('artwork.incomplete', __('Finish your artwork listings', 'elev8-os'), sprintf(_n('%d artwork listing is under 70%% complete.', '%d artwork listings are under 70%% complete.', (int) $summary['incomplete'], 'elev8-os'), (int) $summary['incomplete']), 'medium', 'artwork', 70, __('Give customers better buying information', 'elev8-os'), __('This appears because title, imagery, pricing, story, or other listing details are missing.', 'elev8-os'), [], __('Complete Listings', 'elev8-os'), $context['urls']['artwork']);
        }
        if ((int) $summary['available'] >= 2 && (int) $summary['featured'] === 0) {
            $items[] = self::make('artwork.feature', __('Feature one of your strongest pieces', 'elev8-os'), __('Choose a featured artwork so it appears first in your storefront and is ready for future Gallery Mode.', 'elev8-os'), 'medium', 'marketing', 58, __('Lead customers to your best work', 'elev8-os'), __('This appears because you have multiple available pieces and no featured artwork.', 'elev8-os'), [], __('Choose Featured Artwork', 'elev8-os'), $context['urls']['artwork']);
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    public static function class_recommendations(array $context): array {
        $classes = $context['classes'];
        if (empty($classes['available'])) { return []; }
        $summary = is_array($classes['summary'] ?? null) ? $classes['summary'] : [];
        $upcoming = is_array($classes['upcoming'] ?? null) ? $classes['upcoming'] : [];
        $count = isset($summary['upcoming_count']) && is_numeric($summary['upcoming_count']) ? (int) $summary['upcoming_count'] : null;
        $items = [];
        if ($count === 0) {
            $items[] = self::make('classes.schedule', __('Add an upcoming class', 'elev8-os'), __('No future class date is currently verified for your Amelia artist account.', 'elev8-os'), 'high', 'classes', 90, __('Create another way to earn revenue', 'elev8-os'), __('This appears because Amelia returned zero upcoming classes.', 'elev8-os'), [], __('Manage Classes', 'elev8-os'), $context['urls']['classes']);
        }
        foreach ($upcoming as $class) {
            if (!isset($class['seats_left']) || !is_numeric($class['seats_left'])) { continue; }
            $seats_left = (int) $class['seats_left'];
            if ($seats_left > 0 && $seats_left <= 3) {
                $name = sanitize_text_field((string) ($class['name'] ?? __('Your class', 'elev8-os')));
                $items[] = self::make('classes.nearly-full.' . sanitize_title($name), sprintf(__('%s is nearly full', 'elev8-os'), $name), sprintf(_n('Only %d verified seat remains. Share the booking link or consider another date.', 'Only %d verified seats remain. Share the booking link or consider another date.', $seats_left, 'elev8-os'), $seats_left), 'high', 'classes', 92, __('Capture current customer demand', 'elev8-os'), __('This appears because Amelia reports three or fewer seats remaining.', 'elev8-os'), [], __('View Class', 'elev8-os'), $context['urls']['classes']);
                break;
            }
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    public static function sales_recommendations(array $context): array {
        $interest = $context['asset_summary']['high_interest_unsold'];
        if (!$interest) { return []; }
        $asset = $interest[0];
        $total = (int) $asset['views'] + (int) $asset['scans'];
        return [self::make(
            'sales.review-interest.' . (int) $asset['id'],
            sprintf(__('Review “%s”', 'elev8-os'), $asset['title']),
            sprintf(__('This available piece has %d verified public views and QR scans. Review its price, story, images, and placement.', 'elev8-os'), $total),
            'high', 'sales', 94,
            __('Turn customer interest into a sale', 'elev8-os'),
            __('This appears because the piece has at least 10 verified interactions and remains available.', 'elev8-os'),
            [], __('Review Artwork', 'elev8-os'),
            add_query_arg('edit_artwork', (int) $asset['id'], $context['urls']['artwork'])
        )];
    }

    /** @return array<string,mixed> */
    private static function make(string $id, string $title, string $description, string $priority, string $category, int $score, string $impact, string $reason_format, array $reason_args, string $action_label, string $action_url): array {
        return [
            'id' => sanitize_key($id), 'title' => $title, 'description' => $description,
            'priority' => $priority, 'category' => $category, 'score' => $score,
            'estimated_impact' => $impact,
            'reason' => $reason_args ? vsprintf($reason_format, $reason_args) : $reason_format,
            'action_label' => $action_label, 'action_url' => $action_url,
            'dismissable' => false,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function normalize(array $item): ?array {
        $id = sanitize_key((string) ($item['id'] ?? ''));
        $title = sanitize_text_field((string) ($item['title'] ?? ''));
        if ($id === '' || $title === '') { return null; }
        $priorities = ['critical', 'high', 'medium', 'low', 'informational'];
        $priority = sanitize_key((string) ($item['priority'] ?? 'medium'));
        if (!in_array($priority, $priorities, true)) { $priority = 'medium'; }
        return [
            'id' => $id,
            'title' => $title,
            'description' => sanitize_text_field((string) ($item['description'] ?? '')),
            'priority' => $priority,
            'category' => sanitize_key((string) ($item['category'] ?? 'business')),
            'score' => max(0, min(100, absint($item['score'] ?? 0))),
            'estimated_impact' => sanitize_text_field((string) ($item['estimated_impact'] ?? __('Unavailable', 'elev8-os'))),
            'reason' => sanitize_text_field((string) ($item['reason'] ?? __('Unavailable', 'elev8-os'))),
            'action_label' => sanitize_text_field((string) ($item['action_label'] ?? __('View', 'elev8-os'))),
            'action_url' => esc_url_raw((string) ($item['action_url'] ?? '')),
            'dismissable' => !empty($item['dismissable']),
        ];
    }

    private static function compare(array $a, array $b): int {
        $weights = ['critical' => 5, 'high' => 4, 'medium' => 3, 'low' => 2, 'informational' => 1];
        $priority_compare = ($weights[$b['priority']] ?? 0) <=> ($weights[$a['priority']] ?? 0);
        return $priority_compare !== 0 ? $priority_compare : ((int) $b['score'] <=> (int) $a['score']);
    }

    private static function profile_completeness(array $profile): int {
        $checks = [
            trim((string) ($profile['bio'] ?? '')) !== '',
            trim((string) ($profile['profile_photo'] ?? '')) !== '',
            trim((string) ($profile['cover_image'] ?? '')) !== '',
            trim((string) ($profile['medium'] ?? '')) !== '',
            trim((string) ($profile['specialties'] ?? '')) !== '',
            trim((string) ($profile['social_1_url'] ?? $profile['social'] ?? '')) !== '',
            !empty($profile['public_enabled']),
        ];
        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }

    private static function portal_url(string $key): string {
        return class_exists('Elev8_OS_Portal_Page_Manager')
            ? Elev8_OS_Portal_Page_Manager::get_url($key)
            : home_url('/');
    }
}
