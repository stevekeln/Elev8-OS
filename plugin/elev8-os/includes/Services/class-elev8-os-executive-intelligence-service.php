<?php
/**
 * Rule-based Executive Intelligence for Elev8 OS.
 *
 * Converts verified Attention, Dashboard, and Business Intelligence data into
 * a concise executive brief. It never invents missing business data.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Executive_Intelligence_Service {

    /**
     * Build the verified executive intelligence package.
     *
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $metrics
     * @return array<string,mixed>
     */
    public static function build(array $summary, array $metrics): array {
        $attention = is_array($summary['attention'] ?? null) ? $summary['attention'] : [];
        $items = is_array($attention['items'] ?? null) ? $attention['items'] : [];

        return [
            'generated_at' => current_time('mysql'),
            'brief' => self::brief($summary, $metrics),
            'decisions' => self::decisions($items),
            'wins' => self::wins($summary, $metrics),
            'timeline' => self::timeline($items),
            'opportunities' => self::opportunities($summary, $metrics),
        ];
    }

    /** @return array<string,mixed> */
    private static function brief(array $summary, array $metrics): array {
        $attention = is_array($summary['attention'] ?? null) ? $summary['attention'] : [];
        $total = (int) ($attention['total'] ?? 0);
        $critical = (int) ($attention['critical'] ?? 0);
        $high = (int) ($attention['high'] ?? 0);
        $name = wp_get_current_user()->display_name;

        $headline = $critical > 0
            ? __('Immediate decisions are waiting.', 'elev8-os')
            : ($total > 0 ? __('A few items need your direction.', 'elev8-os') : __('The verified operating queue is clear.', 'elev8-os'));

        $lines = [];
        if ($critical > 0) {
            $lines[] = sprintf(_n('%d critical item requires action.', '%d critical items require action.', $critical, 'elev8-os'), $critical);
        }
        if ($high > 0) {
            $lines[] = sprintf(_n('%d high-priority item is waiting.', '%d high-priority items are waiting.', $high, 'elev8-os'), $high);
        }

        $applications = is_array($summary['applications'] ?? null) ? $summary['applications'] : [];
        if (!empty($applications['available']) && (int) ($applications['attention'] ?? 0) > 0) {
            $count = (int) $applications['attention'];
            $lines[] = sprintf(_n('%d event application needs review.', '%d event applications need review.', $count, 'elev8-os'), $count);
        }

        $reservations = is_array($summary['reservations'] ?? null) ? $summary['reservations'] : [];
        if (!empty($reservations['available']) && (int) ($reservations['upcoming'] ?? 0) > 0) {
            $count = (int) $reservations['upcoming'];
            $lines[] = sprintf(_n('%d reservation group is upcoming this week.', '%d reservation groups are upcoming this week.', $count, 'elev8-os'), $count);
        }

        $change = is_array($metrics['booked_value_change'] ?? null) ? $metrics['booked_value_change'] : [];
        if (!empty($change['available']) && is_numeric($change['value'] ?? null)) {
            $value = (float) $change['value'];
            $lines[] = sprintf(__('Booked value is %s compared with last month.', 'elev8-os'), ($value > 0 ? '+' : '') . number_format_i18n($value, 1) . '%');
        }

        if (!$lines) {
            $lines[] = __('No verified urgent changes are available right now.', 'elev8-os');
        }

        return [
            'greeting' => sprintf(__('Good %1$s, %2$s.', 'elev8-os'), self::daypart(), $name !== '' ? $name : __('Steve', 'elev8-os')),
            'headline' => $headline,
            'lines' => array_slice($lines, 0, 5),
        ];
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    private static function decisions(array $items): array {
        $decisions = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $severity = (string) ($item['severity'] ?? 'normal');
            $title = (string) ($item['title'] ?? __('Decision required', 'elev8-os'));
            $action = __('Review', 'elev8-os');
            if (strpos((string) ($item['id'] ?? ''), 'event-applications:') === 0) {
                $action = __('Review Applications', 'elev8-os');
            } elseif (strpos((string) ($item['id'] ?? ''), 'operations:') === 0) {
                $action = __('Read Manager Note', 'elev8-os');
            } elseif (strpos((string) ($item['id'] ?? ''), 'reservations:') === 0) {
                $action = __('Review Reservations', 'elev8-os');
            } elseif (strpos((string) ($item['id'] ?? ''), 'work:') === 0) {
                $action = __('Open Work', 'elev8-os');
            }
            $decisions[] = [
                'severity' => $severity,
                'title' => $title,
                'summary' => (string) ($item['summary'] ?? ''),
                'source' => (string) ($item['source'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'icon' => (string) ($item['icon'] ?? 'yes-alt'),
                'action' => $action,
            ];
        }
        return array_slice($decisions, 0, 6);
    }

    /** @return array<int,array<string,string>> */
    private static function wins(array $summary, array $metrics): array {
        $wins = [];
        $attention = is_array($summary['attention'] ?? null) ? $summary['attention'] : [];
        if ((int) ($attention['critical'] ?? 0) === 0) {
            $wins[] = ['title' => __('No critical operating issues', 'elev8-os'), 'detail' => __('The verified attention queue has no critical items.', 'elev8-os'), 'icon' => 'yes-alt'];
        }

        $change = is_array($metrics['booked_value_change'] ?? null) ? $metrics['booked_value_change'] : [];
        if (!empty($change['available']) && is_numeric($change['value'] ?? null) && (float) $change['value'] > 0) {
            $wins[] = ['title' => __('Booked value is growing', 'elev8-os'), 'detail' => sprintf(__('Up %s from last month.', 'elev8-os'), number_format_i18n((float) $change['value'], 1) . '%'), 'icon' => 'chart-line'];
        }

        $reservations = is_array($summary['reservations'] ?? null) ? $summary['reservations'] : [];
        if (!empty($reservations['available']) && (int) ($reservations['upcoming'] ?? 0) > 0) {
            $wins[] = ['title' => __('Upcoming guest activity', 'elev8-os'), 'detail' => sprintf(_n('%d reservation group is scheduled this week.', '%d reservation groups are scheduled this week.', (int) $reservations['upcoming'], 'elev8-os'), (int) $reservations['upcoming']), 'icon' => 'tickets-alt'];
        }

        return array_slice($wins, 0, 4);
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    private static function timeline(array $items): array {
        $timeline = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['created_at'])) {
                continue;
            }
            $timeline[] = [
                'title' => (string) ($item['title'] ?? __('Activity', 'elev8-os')),
                'source' => (string) ($item['source'] ?? __('Elev8 OS', 'elev8-os')),
                'created_at' => (string) $item['created_at'],
                'url' => (string) ($item['url'] ?? ''),
                'icon' => (string) ($item['icon'] ?? 'clock'),
            ];
        }
        return array_slice($timeline, 0, 6);
    }

    /** @return array<int,array<string,string>> */
    private static function opportunities(array $summary, array $metrics): array {
        $items = [];
        $applications = is_array($summary['applications'] ?? null) ? $summary['applications'] : [];
        if (!empty($applications['available']) && (int) ($applications['attention'] ?? 0) > 0) {
            $items[] = ['title' => __('Move event applications forward', 'elev8-os'), 'detail' => __('Reviewing applicants quickly can protect event momentum and customer experience.', 'elev8-os'), 'url' => class_exists('Elev8_OS_Event_Applications_Module') ? Elev8_OS_Event_Applications_Module::admin_url() : '', 'icon' => 'forms'];
        }
        $change = is_array($metrics['booked_value_change'] ?? null) ? $metrics['booked_value_change'] : [];
        if (!empty($change['available']) && is_numeric($change['value'] ?? null) && (float) $change['value'] > 0) {
            $items[] = ['title' => __('Build on current booking momentum', 'elev8-os'), 'detail' => __('Booked value is ahead of last month. Review the sources driving that increase.', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-business-intelligence'), 'icon' => 'chart-line'];
        }
        if (!$items) {
            $items[] = ['title' => __('Opportunity data is still developing', 'elev8-os'), 'detail' => __('Open the Opportunities workspace for verified growth actions already captured by Elev8 OS.', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-ceo-dashboard&view=opportunities'), 'icon' => 'lightbulb'];
        }
        return array_slice($items, 0, 3);
    }

    private static function daypart(): string {
        $hour = (int) current_time('G');
        if ($hour < 12) {
            return __('morning', 'elev8-os');
        }
        if ($hour < 18) {
            return __('afternoon', 'elev8-os');
        }
        return __('evening', 'elev8-os');
    }
}
