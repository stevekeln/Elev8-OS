<?php
/**
 * Read-only plugin usage discovery and migration-readiness reporting.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Plugin_Usage_Discovery_Service {

    private const CACHE_KEY = 'elev8_os_plugin_usage_discovery_v1';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Build or retrieve the current read-only discovery report.
     *
     * @return array<string,mixed>
     */
    public static function get_report(bool $force = false): array {
        if (!$force) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $report = self::build_report();
        set_transient(self::CACHE_KEY, $report, self::CACHE_TTL);
        return $report;
    }

    public static function clear_cache(): void {
        delete_transient(self::CACHE_KEY);
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_report(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active = (array) get_option('active_plugins', []);
        $network_active = is_multisite() ? (array) get_site_option('active_sitewide_plugins', []) : [];
        $content_usage = self::discover_content_usage();
        $tables = self::discover_custom_tables();
        $cron = self::discover_cron_hooks();
        $blocks = self::discover_registered_blocks();
        $post_types = self::discover_custom_post_types();

        $items = [];
        foreach ($plugins as $plugin_file => $data) {
            $identity = self::plugin_identity($plugin_file, $data);
            $findings = self::match_findings($identity, $content_usage, $tables, $cron, $blocks, $post_types);
            $is_active = in_array($plugin_file, $active, true) || isset($network_active[$plugin_file]);
            $guidance = self::guidance_for($identity, $is_active, $findings);

            $items[] = [
                'file' => $plugin_file,
                'name' => isset($data['Name']) ? (string) $data['Name'] : $plugin_file,
                'version' => isset($data['Version']) ? (string) $data['Version'] : '',
                'active' => $is_active,
                'identity' => $identity,
                'findings' => $findings,
                'finding_count' => array_sum(array_map('count', $findings)),
                'disposition' => $guidance['disposition'],
                'readiness' => $guidance['readiness'],
                'reason' => $guidance['reason'],
                'next_evidence' => $guidance['next_evidence'],
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['active'] !== $b['active']) {
                return $a['active'] ? -1 : 1;
            }
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'generated_at_utc' => gmdate('c'),
            'site_url' => site_url(),
            'plugin_count' => count($items),
            'active_count' => count(array_filter($items, static fn(array $item): bool => !empty($item['active']))),
            'plugins' => $items,
            'inventory' => [
                'shortcodes' => $content_usage['shortcodes'],
                'block_names' => $content_usage['blocks'],
                'custom_tables' => $tables,
                'cron_hooks' => $cron,
                'registered_blocks' => $blocks,
                'custom_post_types' => $post_types,
                'content_records_scanned' => $content_usage['records_scanned'],
            ],
            'limitations' => [
                __('Discovery is evidence, not permission to deactivate a plugin.', 'elev8-os'),
                __('Runtime-only hooks, remote webhooks, theme PHP calls, and external automations may not be visible in this scan.', 'elev8-os'),
                __('A Local migration test and rollback plan remain required before retirement.', 'elev8-os'),
            ],
        ];
    }

    /**
     * @param array<string,string> $data
     * @return array<string,mixed>
     */
    private static function plugin_identity(string $plugin_file, array $data): array {
        $directory = dirname($plugin_file);
        $slug = $directory === '.' ? basename($plugin_file, '.php') : basename($directory);
        $name = isset($data['Name']) ? (string) $data['Name'] : $slug;
        $text_domain = isset($data['TextDomain']) ? (string) $data['TextDomain'] : '';
        $tokens = self::tokens($slug . ' ' . $name . ' ' . $text_domain);
        $tokens = array_values(array_filter($tokens, static fn(string $token): bool => strlen($token) >= 4));

        return [
            'slug' => sanitize_key($slug),
            'text_domain' => sanitize_key($text_domain),
            'tokens' => array_values(array_unique($tokens)),
        ];
    }

    /**
     * @return array{shortcodes:array<int,array<string,mixed>>,blocks:array<int,array<string,mixed>>,records_scanned:int}
     */
    private static function discover_content_usage(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT ID, post_type, post_title, post_status, post_content FROM {$wpdb->posts} WHERE post_content <> '' AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY ID DESC LIMIT 5000",
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];
        $shortcodes = [];
        $blocks = [];

        foreach ($rows as $row) {
            $content = isset($row['post_content']) ? (string) $row['post_content'] : '';
            if (preg_match_all('/\[([A-Za-z][A-Za-z0-9_-]*)\b/', $content, $matches)) {
                foreach (array_unique($matches[1]) as $tag) {
                    $shortcodes[] = self::content_finding($row, strtolower((string) $tag));
                }
            }

            if (function_exists('parse_blocks')) {
                self::collect_blocks(parse_blocks($content), $row, $blocks);
            }
        }

        return [
            'shortcodes' => self::unique_findings($shortcodes, ['value', 'post_id']),
            'blocks' => self::unique_findings($blocks, ['value', 'post_id']),
            'records_scanned' => count($rows),
        ];
    }

    /** @param array<int,array<string,mixed>> $parsed @param array<string,mixed> $row @param array<int,array<string,mixed>> $results */
    private static function collect_blocks(array $parsed, array $row, array &$results): void {
        foreach ($parsed as $block) {
            $name = isset($block['blockName']) ? (string) $block['blockName'] : '';
            if ($name !== '') {
                $results[] = self::content_finding($row, strtolower($name));
            }
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::collect_blocks($block['innerBlocks'], $row, $results);
            }
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function content_finding(array $row, string $value): array {
        return [
            'value' => $value,
            'post_id' => isset($row['ID']) ? (int) $row['ID'] : 0,
            'post_type' => isset($row['post_type']) ? (string) $row['post_type'] : '',
            'post_status' => isset($row['post_status']) ? (string) $row['post_status'] : '',
            'title' => isset($row['post_title']) ? (string) $row['post_title'] : '',
            'edit_url' => isset($row['ID']) ? get_edit_post_link((int) $row['ID'], 'raw') : '',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function discover_custom_tables(): array {
        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');
        $tables = is_array($tables) ? $tables : [];
        $core = array_map(static fn(string $name): string => $wpdb->prefix . $name, [
            'posts','postmeta','users','usermeta','terms','termmeta','term_taxonomy','term_relationships','options','comments','commentmeta','links',
        ]);
        $results = [];
        foreach ($tables as $table) {
            $table = (string) $table;
            if (in_array($table, $core, true)) {
                continue;
            }
            $results[] = ['value' => $table, 'suffix' => strpos($table, $wpdb->prefix) === 0 ? substr($table, strlen($wpdb->prefix)) : $table];
        }
        return $results;
    }

    /** @return array<int,array<string,mixed>> */
    private static function discover_cron_hooks(): array {
        $cron = function_exists('_get_cron_array') ? _get_cron_array() : [];
        $summary = [];
        if (!is_array($cron)) {
            return [];
        }
        foreach ($cron as $timestamp => $hooks) {
            if (!is_array($hooks)) {
                continue;
            }
            foreach ($hooks as $hook => $events) {
                if (!isset($summary[$hook])) {
                    $summary[$hook] = ['value' => (string) $hook, 'next_run_utc' => gmdate('c', (int) $timestamp), 'event_count' => 0];
                }
                $summary[$hook]['event_count'] += is_array($events) ? count($events) : 1;
            }
        }
        return array_values($summary);
    }

    /** @return array<int,array<string,mixed>> */
    private static function discover_registered_blocks(): array {
        if (!class_exists('WP_Block_Type_Registry')) {
            return [];
        }
        $types = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $results = [];
        foreach ($types as $name => $type) {
            $results[] = ['value' => strtolower((string) $name)];
        }
        return $results;
    }

    /** @return array<int,array<string,mixed>> */
    private static function discover_custom_post_types(): array {
        $built_in = ['post','page','attachment','revision','nav_menu_item','custom_css','customize_changeset','oembed_cache','user_request','wp_block','wp_template','wp_template_part','wp_global_styles','wp_navigation','wp_font_family','wp_font_face'];
        $objects = get_post_types([], 'objects');
        $results = [];
        foreach ($objects as $name => $object) {
            if (in_array($name, $built_in, true)) {
                continue;
            }
            $results[] = [
                'value' => strtolower((string) $name),
                'label' => isset($object->labels->name) ? (string) $object->labels->name : (string) $name,
                'public' => !empty($object->public),
                'show_ui' => !empty($object->show_ui),
            ];
        }
        return $results;
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $content
     * @param array<int,array<string,mixed>> $tables
     * @param array<int,array<string,mixed>> $cron
     * @param array<int,array<string,mixed>> $blocks
     * @param array<int,array<string,mixed>> $post_types
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function match_findings(array $identity, array $content, array $tables, array $cron, array $blocks, array $post_types): array {
        $tokens = isset($identity['tokens']) && is_array($identity['tokens']) ? $identity['tokens'] : [];
        $slug = isset($identity['slug']) ? (string) $identity['slug'] : '';
        $needles = array_values(array_unique(array_filter(array_merge($tokens, [$slug]))));
        $matches = [
            'shortcodes' => self::filter_by_needles($content['shortcodes'], $needles),
            'content_blocks' => self::filter_by_needles($content['blocks'], $needles),
            'custom_tables' => self::filter_by_needles($tables, $needles),
            'cron_hooks' => self::filter_by_needles($cron, $needles),
            'registered_blocks' => self::filter_by_needles($blocks, $needles),
            'custom_post_types' => self::filter_by_needles($post_types, $needles),
        ];

        // Known namespace aliases that cannot be inferred reliably from a marketing plugin name.
        $aliases = self::known_aliases($slug);
        if ($aliases) {
            foreach ($matches as $type => $existing) {
                $source_map = [
                    'shortcodes' => $content['shortcodes'],
                    'content_blocks' => $content['blocks'],
                    'custom_tables' => $tables,
                    'cron_hooks' => $cron,
                    'registered_blocks' => $blocks,
                    'custom_post_types' => $post_types,
                ];
                $source = isset($source_map[$type]) ? $source_map[$type] : [];
                $matches[$type] = self::unique_findings(array_merge($existing, self::filter_by_needles($source, $aliases)), ['value', 'post_id']);
            }
        }
        return $matches;
    }

    /** @param array<int,array<string,mixed>> $items @param array<int,string> $needles @return array<int,array<string,mixed>> */
    private static function filter_by_needles(array $items, array $needles): array {
        if (!$needles) {
            return [];
        }
        return array_values(array_filter($items, static function (array $item) use ($needles): bool {
            $haystack = strtolower(implode(' ', array_map('strval', array_filter($item, 'is_scalar'))));
            foreach ($needles as $needle) {
                $needle = strtolower((string) $needle);
                if (strlen($needle) >= 3 && strpos($haystack, $needle) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }

    /** @return array<int,string> */
    private static function known_aliases(string $slug): array {
        $map = [
            'woocommerce' => ['woocommerce', 'wc_', 'wc-', 'product', 'shop_order'],
            'ameliabooking' => ['amelia', 'ameliaappointments', 'wpamelia'],
            'ultimate-member' => ['ultimatemember', 'um_', 'um-'],
            'pods' => ['pods', 'pod_'],
            'siteorigin-panels' => ['siteorigin', 'so-widget', 'panels'],
            'so-widgets-bundle' => ['siteorigin', 'so-widget'],
            'shortcodes-ultimate' => ['su_', 'su-'],
            'atum-stock-manager-for-woocommerce' => ['atum'],
            'wp-mail-smtp' => ['wp_mail_smtp', 'wp-mail-smtp'],
            'google-site-kit' => ['googlesitekit', 'google-site-kit'],
            'redirection' => ['redirection', 'red_'],
            'code-snippets' => ['code_snippets', 'snippet'],
        ];
        return $map[$slug] ?? [];
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,array<int,array<string,mixed>>> $findings
     * @return array<string,string>
     */
    private static function guidance_for(array $identity, bool $active, array $findings): array {
        $slug = isset($identity['slug']) ? (string) $identity['slug'] : '';
        $count = array_sum(array_map('count', $findings));
        $trusted = ['woocommerce','ameliabooking','wp-mail-smtp','google-site-kit','litespeed-cache','redirection','ultimate-member'];
        $migration = ['atum-stock-manager-for-woocommerce','dw-question-answer','kaya-qr-code-generator','code-snippets','wpcode-lite','pods','integromat-connector'];

        if ($slug === 'elev8-os') {
            return ['disposition' => 'Core platform', 'readiness' => 'Not applicable', 'reason' => __('Elev8 OS is the platform being evaluated.', 'elev8-os'), 'next_evidence' => __('Continue normal release validation.', 'elev8-os')];
        }
        if (in_array($slug, $trusted, true)) {
            return ['disposition' => 'Keep / audit later', 'readiness' => 'Protected dependency', 'reason' => __('This plugin currently provides trusted infrastructure or authoritative behavior.', 'elev8-os'), 'next_evidence' => __('Document ownership, integration health, and a tested migration plan before replacement.', 'elev8-os')];
        }
        if (in_array($slug, $migration, true)) {
            return ['disposition' => 'Migration candidate', 'readiness' => $count > 0 ? 'Dependencies discovered' : 'Discovery incomplete', 'reason' => $count > 0 ? __('The scan found site dependencies that must be migrated.', 'elev8-os') : __('No direct dependency was detected, but runtime and external usage still require review.', 'elev8-os'), 'next_evidence' => __('Map every finding to an Elev8 OS capability, migrate on Local, verify data, and preserve rollback.', 'elev8-os')];
        }
        if (!$active && $count === 0) {
            return ['disposition' => 'Inactive review', 'readiness' => 'Candidate for manual review', 'reason' => __('The plugin is inactive and no direct usage was detected by this scan.', 'elev8-os'), 'next_evidence' => __('Confirm stored data, theme calls, webhooks, licenses, and rollback before removal.', 'elev8-os')];
        }
        return ['disposition' => $active ? 'Audit later' : 'Inactive review', 'readiness' => $count > 0 ? 'Dependencies discovered' : 'Unclassified', 'reason' => $count > 0 ? __('The scan found one or more possible dependencies.', 'elev8-os') : __('No direct dependency was detected, but the plugin has not been fully classified.', 'elev8-os'), 'next_evidence' => __('Confirm ownership, data, public pages, runtime hooks, and external integrations.', 'elev8-os')];
    }

    /** @return array<int,string> */
    private static function tokens(string $value): array {
        return preg_split('/[^a-z0-9]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @param array<int,array<string,mixed>> $items @param array<int,string> $keys @return array<int,array<string,mixed>> */
    private static function unique_findings(array $items, array $keys): array {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $parts = [];
            foreach ($keys as $key) {
                $parts[] = isset($item[$key]) ? (string) $item[$key] : '';
            }
            $fingerprint = implode('|', $parts);
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $unique[] = $item;
        }
        return $unique;
    }
}
