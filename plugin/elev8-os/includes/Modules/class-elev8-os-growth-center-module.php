<?php
if (!defined('ABSPATH')) { exit; }

/** Owner-level view of artist business health using the same verified snapshot as artists. */
final class Elev8_OS_Growth_Center_Module {
    private const SLUG = 'elev8-growth-center';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 24);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function register_menu(): void {
        add_submenu_page('elev8-os', __('Artist Growth Center', 'elev8-os'), __('Artist Growth Center', 'elev8-os'), 'manage_options', self::SLUG, [__CLASS__, 'render']);
    }

    public static function assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::SLUG) { return; }
        wp_enqueue_style('dashicons');
        wp_enqueue_style('elev8-os-artist-dashboard', ELEV8_OS_URL . 'assets/css/artist-dashboard.css', [], ELEV8_OS_VERSION);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os')); }
        $users = self::artist_users();
        ?>
        <div class="wrap elev8-growth-owner">
            <header class="elev8-dashboard-header elev8-dashboard-hero">
                <div><p class="elev8-eyebrow"><?php esc_html_e('Owner View', 'elev8-os'); ?></p><h1><?php esc_html_e('Artist Growth Center', 'elev8-os'); ?></h1><p><?php esc_html_e('See who is growing, who needs help, and the highest-value next action supported by verified data.', 'elev8-os'); ?></p></div>
                <span class="elev8-dashboard-badge"><?php echo esc_html(sprintf(_n('%d artist', '%d artists', count($users), 'elev8-os'), count($users))); ?></span>
            </header>
            <section class="elev8-dashboard-panel elev8-owner-growth-table">
                <?php if (!$users): ?>
                    <div class="elev8-dashboard-empty"><span class="dashicons dashicons-groups"></span><h3><?php esc_html_e('No linked artist accounts found', 'elev8-os'); ?></h3><p><?php esc_html_e('Link WordPress users to Amelia Member Artists in Employee Mapping. Scores will appear here automatically.', 'elev8-os'); ?></p></div>
                <?php else: ?>
                    <div class="elev8-table-wrap"><table><thead><tr><th><?php esc_html_e('Artist', 'elev8-os'); ?></th><th><?php esc_html_e('Score', 'elev8-os'); ?></th><th><?php esc_html_e('Classes', 'elev8-os'); ?></th><th><?php esc_html_e('Students', 'elev8-os'); ?></th><th><?php esc_html_e('Artwork', 'elev8-os'); ?></th><th><?php esc_html_e('Needs attention', 'elev8-os'); ?></th></tr></thead><tbody>
                    <?php foreach ($users as $user): $snapshot = Elev8_OS_Artist_Business_Service::get_snapshot($user); $score=(array)($snapshot['score']??[]); $rec=(array)($snapshot['recommendations'][0]??[]); ?>
                        <tr><td><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_email); ?></small></td><td><span class="elev8-score-pill score-<?php echo esc_attr(self::score_band((int)($score['score']??0))); ?>"><?php echo esc_html((string)($score['score']??0)); ?></span> <?php echo esc_html((string)($score['label']??'')); ?></td><td><?php echo esc_html(number_format_i18n((int)($snapshot['classes']['upcoming_count']??0))); ?></td><td><?php echo esc_html(number_format_i18n((int)($snapshot['classes']['student_count']??0))); ?></td><td><?php echo esc_html(number_format_i18n((int)($snapshot['assets']['total']??0))); ?></td><td><?php echo $rec ? esc_html((string)($rec['title']??'')) : esc_html__('No verified issue', 'elev8-os'); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        </div><?php
    }

    /** @return WP_User[] */
    private static function artist_users(): array {
        $found = get_users(['meta_key'=>'elev8_os_amelia_employee_id','meta_compare'=>'EXISTS','orderby'=>'display_name','order'=>'ASC']);
        $ids = array_map(static fn($u)=>(int)$u->ID, $found);
        foreach (get_users(['role__in'=>['amelia_employee'],'orderby'=>'display_name','order'=>'ASC']) as $user) {
            if (!in_array((int)$user->ID,$ids,true)) { $found[]=$user; $ids[]=(int)$user->ID; }
        }
        return $found;
    }
    private static function score_band(int $score): string { return $score>=85?'excellent':($score>=70?'strong':($score>=50?'building':'attention')); }
}
