<?php
/**
 * Role-aware, rule-based coaching recommendations.
 *
 * Recommendations are deterministic, explainable, and grounded in verified
 * Elev8 OS services. This service does not call an external AI provider and
 * never performs an action on a user's behalf.
 *
 * @package Elev8OS
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Coaching_Service {

    /** @return array<string,mixed> */
    public static function build(?WP_User $user = null, int $limit = 4): array {
        $user = $user ?: wp_get_current_user();
        $role = self::role($user);
        $items = [];

        if ($role === 'ceo') {
            $items = self::ceo_recommendations($user);
        } elseif ($role === 'manager') {
            $items = self::manager_recommendations($user);
        } elseif ($role === 'event_host') {
            $items = self::event_host_recommendations($user);
        }

        /**
         * Filter role-aware coaching recommendations.
         *
         * @param array<int,array<string,mixed>> $items
         * @param string                         $role
         * @param WP_User                        $user
         */
        $items = apply_filters('elev8_os_coaching_recommendations', $items, $role, $user);
        if (!is_array($items)) { $items = []; }

        $normalized = [];
        foreach ($items as $item) {
            $item = self::normalize($item);
            if ($item !== null) { $normalized[$item['id']] = $item; }
        }
        $normalized = array_values($normalized);
        usort($normalized, static function(array $a, array $b): int {
            $rank = ['critical'=>4, 'high'=>3, 'medium'=>2, 'low'=>1];
            return ($rank[$b['priority']] ?? 0) <=> ($rank[$a['priority']] ?? 0);
        });

        return [
            'available' => true,
            'role' => $role,
            'generated_at' => current_time('mysql'),
            'items' => array_slice($normalized, 0, max(1, min(10, $limit))),
            'method' => __('Rule-based and explainable', 'elev8-os'),
        ];
    }

    /** Render the shared recommendation panel. */
    public static function render(?WP_User $user = null, string $heading = ''): void {
        $result = self::build($user);
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];
        if (!$items) { return; }
        $heading = $heading !== '' ? $heading : __('Recommended Next Actions', 'elev8-os');
        ?>
        <section class="elev8-coaching-panel" aria-labelledby="elev8-coaching-heading">
            <div class="elev8-coaching-heading">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Elev8 Coach', 'elev8-os'); ?></p>
                    <h2 id="elev8-coaching-heading"><?php echo esc_html($heading); ?></h2>
                    <p><?php esc_html_e('Suggestions based only on verified Elev8 OS activity. You remain in control of every action.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-coaching-method"><?php echo esc_html((string)$result['method']); ?></span>
            </div>
            <div class="elev8-coaching-grid">
                <?php foreach ($items as $item) : ?>
                    <article class="elev8-coaching-card is-<?php echo esc_attr((string)$item['priority']); ?>">
                        <div class="elev8-coaching-card__top">
                            <span class="elev8-coaching-type"><?php esc_html_e('Recommendation', 'elev8-os'); ?></span>
                            <span class="elev8-coaching-confidence"><?php echo esc_html(sprintf(__('%d%% confidence', 'elev8-os'), (int)$item['confidence'])); ?></span>
                        </div>
                        <h3><?php echo esc_html((string)$item['title']); ?></h3>
                        <p><?php echo esc_html((string)$item['reason']); ?></p>
                        <details class="elev8-coaching-why">
                            <summary><?php esc_html_e('Why am I seeing this?', 'elev8-os'); ?></summary>
                            <p><?php echo esc_html((string)$item['why']); ?></p>
                        </details>
                        <?php if ((string)$item['url'] !== '') : ?>
                            <a class="elev8-coaching-action" href="<?php echo esc_url((string)$item['url']); ?>"><?php echo esc_html((string)$item['action']); ?> <span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /** @return array<int,array<string,mixed>> */
    private static function ceo_recommendations(WP_User $user): array {
        $summary = class_exists('Elev8_OS_Dashboard_Service') ? Elev8_OS_Dashboard_Service::summary($user) : [];
        $attention = is_array($summary['attention'] ?? null) ? $summary['attention'] : [];
        $team = is_array($summary['team_work'] ?? null) ? $summary['team_work'] : [];
        $apps = is_array($summary['applications'] ?? null) ? $summary['applications'] : [];
        $reservations = is_array($summary['reservations'] ?? null) ? $summary['reservations'] : [];
        $items = [];

        if ((int)($attention['critical'] ?? 0) > 0 || (int)($team['overdue'] ?? 0) > 0) {
            $count = max((int)($attention['critical'] ?? 0), (int)($team['overdue'] ?? 0));
            $items[] = self::item('ceo.overdue', __('Resolve the highest-risk operating item first', 'elev8-os'), sprintf(_n('%d critical or overdue item is waiting.', '%d critical or overdue items are waiting.', $count, 'elev8-os'), $count), 'critical', 98, __('Elev8 OS found verified critical attention or overdue team work. This recommendation moves risk ahead of lower-priority growth work.', 'elev8-os'), class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::team_url() : '', __('Review Work', 'elev8-os'));
        }
        if ((int)($apps['attention'] ?? 0) > 0) {
            $count = (int)$apps['attention'];
            $items[] = self::item('ceo.applications', __('Move waiting event applications forward', 'elev8-os'), sprintf(_n('%d event application needs review.', '%d event applications need review.', $count, 'elev8-os'), $count), 'high', 96, __('The Event Applications service reports applications in a review state. Acting now can protect applicant experience and event planning time.', 'elev8-os'), class_exists('Elev8_OS_Event_Applications_Module') ? Elev8_OS_Event_Applications_Module::admin_url() : '', __('Review Applications', 'elev8-os'));
        }
        if ((int)($reservations['attention'] ?? 0) > 0) {
            $count = (int)$reservations['attention'];
            $items[] = self::item('ceo.reservations', __('Clear guest reservations that need attention', 'elev8-os'), sprintf(_n('%d reservation is waiting for review.', '%d reservations are waiting for review.', $count, 'elev8-os'), $count), 'high', 94, __('The Reservations service reports a verified follow-up state. Timely review improves the customer experience.', 'elev8-os'), class_exists('Elev8_OS_Bingo_Reservations_Module') ? Elev8_OS_Bingo_Reservations_Module::admin_url() : '', __('Review Reservations', 'elev8-os'));
        }
        if (!$items) {
            $items[] = self::item('ceo.focus', __('Use today to move the strongest opportunity forward', 'elev8-os'), __('No verified critical operating item is currently blocking the business.', 'elev8-os'), 'low', 84, __('The shared Attention and Work services report no critical or overdue item. Elev8 OS therefore recommends proactive growth work rather than inventing a problem.', 'elev8-os'), admin_url('admin.php?page=elev8-ceo-dashboard&view=opportunities'), __('Open Opportunities', 'elev8-os'));
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private static function manager_recommendations(WP_User $user): array {
        $s = class_exists('Elev8_OS_Manager_Dashboard_Service') ? Elev8_OS_Manager_Dashboard_Service::snapshot($user) : [];
        $ops = is_array($s['operations'] ?? null) ? $s['operations'] : [];
        $my = is_array($s['my_work'] ?? null) ? $s['my_work'] : [];
        $team = is_array($s['team_work'] ?? null) ? $s['team_work'] : [];
        $items = [];
        $log_url = class_exists('Elev8_OS_Daily_Operations_Module') ? admin_url('admin.php?page=elev8-daily-operations') : '';

        if ((int)($ops['submitted_today'] ?? 0) === 0) {
            $items[] = self::item('manager.log', __('Complete today’s manager operating log', 'elev8-os'), __('No verified manager log has been submitted by you today.', 'elev8-os'), 'high', 99, __('Elev8 OS checked today’s Daily Operations records for your user account and found no manager-template submission.', 'elev8-os'), $log_url, __('Complete Manager Log', 'elev8-os'));
        }
        if ((int)($my['overdue'] ?? 0) > 0) {
            $count = (int)$my['overdue'];
            $items[] = self::item('manager.overdue', __('Clear your overdue work before adding new commitments', 'elev8-os'), sprintf(_n('%d assigned item is overdue.', '%d assigned items are overdue.', $count, 'elev8-os'), $count), 'critical', 98, __('The Work service found verified overdue items assigned to your account.', 'elev8-os'), class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::my_url() : '', __('Open My Work', 'elev8-os'));
        }
        if ((int)($team['unassigned'] ?? 0) > 0) {
            $count = (int)$team['unassigned'];
            $items[] = self::item('manager.assign', __('Assign team work that has no owner', 'elev8-os'), sprintf(_n('%d team item is unassigned.', '%d team items are unassigned.', $count, 'elev8-os'), $count), 'high', 96, __('The Team Work summary found verified active items without an assignee. Clear ownership reduces dropped follow-up.', 'elev8-os'), class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::team_url() : '', __('Assign Team Work', 'elev8-os'));
        }
        if (!$items) {
            $items[] = self::item('manager.coach', __('Recognize one team win and document the next priority', 'elev8-os'), __('No verified overdue or unassigned work is currently blocking the team.', 'elev8-os'), 'low', 82, __('Current Work and Daily Operations summaries show no urgent routing issue. This recommendation supports coaching and continuity without inventing a problem.', 'elev8-os'), $log_url, __('Open Daily Operations', 'elev8-os'));
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private static function event_host_recommendations(WP_User $user): array {
        $s = class_exists('Elev8_OS_Event_Host_Dashboard_Service') ? Elev8_OS_Event_Host_Dashboard_Service::snapshot($user) : [];
        $open = is_array($s['open_mic'] ?? null) ? $s['open_mic'] : [];
        $bingo = is_array($s['bingo'] ?? null) ? $s['bingo'] : [];
        $profile = is_array($s['public_profile'] ?? null) ? $s['public_profile'] : [];
        $items = [];

        if ((int)($open['new'] ?? 0) > 0) {
            $count = (int)$open['new'];
            $items[] = self::item('host.open_mic', __('Review new Open Mic entries before the event', 'elev8-os'), sprintf(_n('%d new entry is waiting.', '%d new entries are waiting.', $count, 'elev8-os'), $count), 'high', 98, __('The verified Open Mic intake count includes entries that have not yet been reviewed by the event team.', 'elev8-os'), (string)($s['open_mic_form_url'] ?? ''), __('Review Open Mic', 'elev8-os'));
        }
        if ((int)($bingo['attention'] ?? 0) > 0) {
            $count = (int)$bingo['attention'];
            $items[] = self::item('host.bingo', __('Resolve Bingo reservation questions', 'elev8-os'), sprintf(_n('%d reservation needs attention.', '%d reservations need attention.', $count, 'elev8-os'), $count), 'high', 95, __('The Bingo Reservations service found assigned groups with a verified attention state.', 'elev8-os'), (string)($s['reservations_url'] ?? ''), __('Open Reservations', 'elev8-os'));
        }
        if (empty($profile['published'])) {
            $items[] = self::item('host.profile', __('Create your public event-host profile', 'elev8-os'), __('Guests cannot currently view a published host profile for you.', 'elev8-os'), 'medium', 100, __('The Event Host Dashboard service reports that no published public host profile is connected to your account.', 'elev8-os'), class_exists('Elev8_OS_Public_Profile_Service') ? Elev8_OS_Public_Profile_Service::editor_url() : '', __('Create Public Profile', 'elev8-os'));
        }
        if (!$items) {
            $items[] = self::item('host.closeout', __('Keep the event log ready for tonight’s closeout', 'elev8-os'), __('No verified Open Mic or Bingo item currently requires review.', 'elev8-os'), 'low', 88, __('The event intake and reservation summaries report no waiting attention item, so the next best action is preserving event outcomes in Business Memory.', 'elev8-os'), (string)($s['event_log_url'] ?? ''), __('Open Event Log', 'elev8-os'));
        }
        return $items;
    }

    private static function role(WP_User $user): string {
        if ($user->has_cap('manage_options') || (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::is_owner($user))) { return 'ceo'; }
        if (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::is_manager($user)) { return 'manager'; }
        if (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::uses_event_host_home($user)) { return 'event_host'; }
        return 'member';
    }

    /** @return array<string,mixed> */
    private static function item(string $id, string $title, string $reason, string $priority, int $confidence, string $why, string $url, string $action): array {
        return compact('id','title','reason','priority','confidence','why','url','action');
    }

    /** @return array<string,mixed>|null */
    private static function normalize($item): ?array {
        if (!is_array($item)) { return null; }
        $id = sanitize_key((string)($item['id'] ?? ''));
        $title = sanitize_text_field((string)($item['title'] ?? ''));
        if ($id === '' || $title === '') { return null; }
        $priority = sanitize_key((string)($item['priority'] ?? 'medium'));
        if (!in_array($priority, ['critical','high','medium','low'], true)) { $priority = 'medium'; }
        return [
            'id'=>$id,
            'title'=>$title,
            'reason'=>sanitize_text_field((string)($item['reason'] ?? '')),
            'priority'=>$priority,
            'confidence'=>max(0, min(100, absint($item['confidence'] ?? 0))),
            'why'=>sanitize_text_field((string)($item['why'] ?? '')),
            'url'=>esc_url_raw((string)($item['url'] ?? '')),
            'action'=>sanitize_text_field((string)($item['action'] ?? __('Open', 'elev8-os'))),
        ];
    }
}
