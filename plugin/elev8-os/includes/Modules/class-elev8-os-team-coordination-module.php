<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Team_Coordination_Module {
    private const SHORTCODE = 'elev8_team_coordination';
    private const ADMIN_SLUG = 'elev8-team-coordination';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 34);
        add_action('admin_post_elev8_save_work_dependencies', [__CLASS__, 'save_dependencies']);
        add_action('admin_post_elev8_request_work_handoff', [__CLASS__, 'request_handoff']);
        add_action('admin_post_elev8_decide_work_handoff', [__CLASS__, 'decide_handoff']);
        add_action('admin_post_elev8_save_capacity_target', [__CLASS__, 'save_capacity_target']);
        add_action('admin_post_elev8_save_coordination_profile', [__CLASS__, 'save_coordination_profile']);
        add_action('admin_post_elev8_save_work_skills', [__CLASS__, 'save_work_skills']);
        add_action('admin_post_elev8_save_availability_calendar', [__CLASS__, 'save_availability_calendar']);
        add_action('admin_post_elev8_verify_coordination_skills', [__CLASS__, 'verify_coordination_skills']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
    }

    public static function register_menu(): void {
        add_submenu_page('elev8-os', __('Team Coordination', 'elev8-os'), __('Team Coordination', 'elev8-os'), 'read', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function url(): string {
        return class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('team_coordination') : admin_url('admin.php?page=' . self::ADMIN_SLUG);
    }

    public static function command(array $commands, WP_User $user): array {
        $commands[] = ['id' => 'team-coordination', 'label' => __('Team Coordination', 'elev8-os'), 'description' => __('See capacity, dependencies, handoff acknowledgements, bottlenecks, and reassignment suggestions.', 'elev8-os'), 'url' => self::url(), 'keywords' => ['team','capacity','dependencies','handoff','waiting','bottleneck']];
        return $commands;
    }

    public static function render_admin(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.', 'elev8-os')); }
        echo '<div class="wrap">' . self::render() . '</div>';
    }

    public static function shortcode(): string {
        return is_user_logged_in() ? self::render() : '<p>' . esc_html__('Please sign in to view Team Coordination.', 'elev8-os') . '</p>';
    }

    private static function render(): string {
        $user = wp_get_current_user();
        $snapshot = Elev8_OS_Team_Coordination_Service::snapshot($user);
        $users = Elev8_OS_Team_Coordination_Service::assignable_users();
        $pending_for_user = [];
        $coordination_profiles = [];
        foreach ($users as $assignable_user) {
            $coordination_profiles[(int) $assignable_user->ID] = [
                'availability' => Elev8_OS_Team_Availability_Skill_Service::availability((int) $assignable_user->ID),
                'skills' => Elev8_OS_Team_Availability_Skill_Service::skills((int) $assignable_user->ID),
                'verified_skills' => Elev8_OS_Team_Availability_Calendar_Service::verified_skills((int) $assignable_user->ID),
                'availability_windows' => Elev8_OS_Team_Availability_Calendar_Service::windows_text((int) $assignable_user->ID),
            ];
        }
        foreach ($snapshot['items'] as $item) {
            $pending = Elev8_OS_Team_Capacity_Service::pending_request((int) $item['id']);
            if ($pending && ((int) ($pending['to_user_id'] ?? 0) === (int) $user->ID || Elev8_OS_Team_Coordination_Service::can_coordinate($user))) {
                $pending['work_id'] = (int) $item['id'];
                $pending['work_title'] = (string) $item['title'];
                $pending_for_user[] = $pending;
            }
        }
        $over_capacity = count(array_filter($snapshot['workloads'], static fn(array $load): bool => ($load['capacity']['state'] ?? '') === 'over_capacity'));
        ob_start();
        ?>
        <main class="elev8-team-coordination" style="max-width:1200px;margin:0 auto;padding:24px">
            <header style="margin-bottom:20px"><p style="text-transform:uppercase;letter-spacing:.08em;color:#666;margin:0"><?php esc_html_e('Operations + Workflow + Organization', 'elev8-os'); ?></p><h1 style="margin:.2em 0"><?php esc_html_e('Team Coordination', 'elev8-os'); ?></h1><p><?php echo esc_html($snapshot['team_view'] ? __('See capacity pressure, waiting relationships, governed handoffs, and where work may need help.', 'elev8-os') : __('See your work dependencies and acknowledge work proposed for handoff to you.', 'elev8-os')); ?></p></header>
            <?php if (isset($_GET['coordination_saved'])): ?><div class="notice notice-success inline"><p><?php esc_html_e('Team coordination evidence saved.', 'elev8-os'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['coordination_error'])): ?><div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash((string) $_GET['coordination_error']))); ?></p></div><?php endif; ?>

            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
                <?php self::metric(__('People with active work', 'elev8-os'), count($snapshot['workloads'])); ?>
                <?php self::metric(__('Over capacity', 'elev8-os'), $over_capacity); ?>
                <?php self::metric(__('Potential bottlenecks', 'elev8-os'), count($snapshot['bottlenecks'])); ?>
                <?php self::metric(__('Handoffs awaiting acknowledgement', 'elev8-os'), count($pending_for_user)); ?>
            </section>

            <?php if ($pending_for_user): ?>
            <section style="background:#fff7e6;border:1px solid #d99a24;border-radius:18px;padding:20px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Handoffs awaiting acknowledgement', 'elev8-os'); ?></h2>
                <?php foreach ($pending_for_user as $request): $from=get_user_by('id',(int)($request['from_user_id']??0));$to=get_user_by('id',(int)($request['to_user_id']??0)); ?>
                <article style="border-top:1px solid #ead3a5;padding:14px 0"><strong><?php echo esc_html($request['work_title']); ?></strong><p><?php echo esc_html(sprintf(__('%1$s proposed a handoff to %2$s. Ownership has not changed.', 'elev8-os'), $from instanceof WP_User?$from->display_name:__('Unassigned','elev8-os'), $to instanceof WP_User?$to->display_name:__('Unknown','elev8-os'))); ?></p><?php if (!empty($request['note'])): ?><p style="color:#555"><?php echo esc_html($request['note']); ?></p><?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_decide_work_handoff"><input type="hidden" name="work_id" value="<?php echo esc_attr((string)$request['work_id']); ?>"><input type="hidden" name="request_id" value="<?php echo esc_attr((string)$request['request_id']); ?>"><?php wp_nonce_field('elev8_decide_work_handoff_'.$request['work_id'].'_'.$request['request_id']); ?><label><?php esc_html_e('Decision note', 'elev8-os'); ?><textarea name="note" rows="2" style="display:block;width:100%;max-width:700px"></textarea></label><p><button class="button button-primary" name="decision" value="accepted" type="submit"><?php esc_html_e('Accept handoff', 'elev8-os'); ?></button> <button class="button" name="decision" value="declined" type="submit"><?php esc_html_e('Decline', 'elev8-os'); ?></button></p></form></article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Capacity visibility', 'elev8-os'); ?></h2><p><?php esc_html_e('Capacity targets are planning guidance only. They do not limit employment, change assignments, or automatically move work.', 'elev8-os'); ?></p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px">
                <?php foreach ($snapshot['workloads'] as $load): $capacity=$load['capacity']; ?>
                    <article style="border:1px solid #ddd;border-radius:14px;padding:15px"><strong><?php echo esc_html($load['name']); ?></strong><p style="margin:.5em 0"><b><?php echo esc_html((string)$capacity['percent']); ?>%</b> · <?php echo esc_html(ucwords(str_replace('_',' ',(string)$capacity['state']))); ?></p><p><?php echo esc_html(sprintf(__('%1$d capacity points / target %2$d', 'elev8-os'), $capacity['points'], $capacity['target'])); ?></p><details><summary><?php esc_html_e('How this was calculated', 'elev8-os'); ?></summary><p><?php echo esc_html($capacity['explanation']); ?></p></details>
                    <?php if (Elev8_OS_Team_Coordination_Service::can_coordinate($user) && !empty($load['user_id'])): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px"><input type="hidden" name="action" value="elev8_save_capacity_target"><input type="hidden" name="user_id" value="<?php echo esc_attr((string)$load['user_id']); ?>"><?php wp_nonce_field('elev8_save_capacity_target_'.$load['user_id']); ?><label><?php esc_html_e('Planning target', 'elev8-os'); ?> <input type="number" min="1" max="100" name="target" value="<?php echo esc_attr((string)$capacity['target']); ?>" style="width:75px"></label> <button class="button" type="submit"><?php esc_html_e('Save', 'elev8-os'); ?></button></form><?php endif; ?></article>
                <?php endforeach; ?>
                <?php if (!$snapshot['workloads']): ?><p><?php esc_html_e('No active work is available in your scope.', 'elev8-os'); ?></p><?php endif; ?>
                </div>
            </section>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Availability calendar and skill verification', 'elev8-os'); ?></h2><p><?php esc_html_e('Recurring windows and verified skills improve handoff suggestions only. They are not attendance, scheduling, leave approval, certification, or permission grants.', 'elev8-os'); ?></p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
                <?php foreach ($users as $assignable): $profile=$coordination_profiles[(int)$assignable->ID]; $can_edit=((int)$assignable->ID===(int)$user->ID)||Elev8_OS_Team_Coordination_Service::can_coordinate($user); $can_verify=Elev8_OS_Team_Coordination_Service::can_coordinate($user); ?>
                    <article style="border:1px solid #ddd;border-radius:14px;padding:15px"><strong><?php echo esc_html($assignable->display_name); ?></strong><p><?php echo esc_html(ucfirst((string)$profile['availability']['state'])); ?><?php if (!empty($profile['availability']['until'])): ?> · <?php echo esc_html(sprintf(__('until %s','elev8-os'),$profile['availability']['until'])); ?><?php endif; ?></p><p><b><?php esc_html_e('Skills:','elev8-os'); ?></b> <?php echo esc_html($profile['skills'] ? implode(', ',$profile['skills']) : __('None recorded.','elev8-os')); ?></p><p><b><?php esc_html_e('Manager-confirmed:','elev8-os'); ?></b> <?php echo esc_html($profile['verified_skills'] ? implode(', ',$profile['verified_skills']) : __('None yet.','elev8-os')); ?></p><p><b><?php esc_html_e('Recurring windows:','elev8-os'); ?></b><br><?php echo $profile['availability_windows'] ? nl2br(esc_html($profile['availability_windows'])) : esc_html__('No recurring calendar recorded.','elev8-os'); ?></p>
                    <?php if ($can_edit): ?><details><summary><?php esc_html_e('Edit availability and declared skills','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_save_coordination_profile"><input type="hidden" name="user_id" value="<?php echo esc_attr((string)$assignable->ID); ?>"><?php wp_nonce_field('elev8_save_coordination_profile_'.$assignable->ID); ?><label><?php esc_html_e('Availability','elev8-os'); ?><select name="availability_state" style="display:block"><option value="available" <?php selected($profile['availability']['state'],'available'); ?>><?php esc_html_e('Available','elev8-os'); ?></option><option value="limited" <?php selected($profile['availability']['state'],'limited'); ?>><?php esc_html_e('Limited','elev8-os'); ?></option><option value="unavailable" <?php selected($profile['availability']['state'],'unavailable'); ?>><?php esc_html_e('Unavailable','elev8-os'); ?></option></select></label><label><?php esc_html_e('Until','elev8-os'); ?><input type="date" name="availability_until" value="<?php echo esc_attr((string)$profile['availability']['until']); ?>" style="display:block"></label><label><?php esc_html_e('Declared skills','elev8-os'); ?><textarea name="skills" rows="2" style="display:block;width:100%" placeholder="inventory, glass repair, networking"><?php echo esc_textarea(implode(', ',$profile['skills'])); ?></textarea></label><label><?php esc_html_e('Availability note','elev8-os'); ?><textarea name="availability_note" rows="2" style="display:block;width:100%"><?php echo esc_textarea((string)$profile['availability']['note']); ?></textarea></label><p><button class="button" type="submit"><?php esc_html_e('Save profile','elev8-os'); ?></button></p></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_save_availability_calendar"><input type="hidden" name="user_id" value="<?php echo esc_attr((string)$assignable->ID); ?>"><?php wp_nonce_field('elev8_save_availability_calendar_'.$assignable->ID); ?><label><?php esc_html_e('Recurring coordination windows','elev8-os'); ?><textarea name="availability_windows" rows="5" style="display:block;width:100%" placeholder="mon 09:00-17:00&#10;tue 09:00-17:00"><?php echo esc_textarea($profile['availability_windows']); ?></textarea></label><p style="color:#666"><?php esc_html_e('Use one line per window, such as “mon 09:00-17:00”.','elev8-os'); ?></p><p><button class="button" type="submit"><?php esc_html_e('Save recurring calendar','elev8-os'); ?></button></p></form></details><?php endif; ?>
                    <?php if ($can_verify && $profile['skills']): ?><details><summary><?php esc_html_e('Verify declared skills','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_verify_coordination_skills"><input type="hidden" name="user_id" value="<?php echo esc_attr((string)$assignable->ID); ?>"><?php wp_nonce_field('elev8_verify_coordination_skills_'.$assignable->ID); ?><?php foreach ($profile['skills'] as $skill): ?><label style="display:block"><input type="checkbox" name="verified_skills[]" value="<?php echo esc_attr($skill); ?>" <?php checked(in_array($skill,$profile['verified_skills'],true)); ?>> <?php echo esc_html($skill); ?></label><?php endforeach; ?><label><?php esc_html_e('Verification note','elev8-os'); ?><textarea name="verification_note" rows="2" style="display:block;width:100%"></textarea></label><p><button class="button" type="submit"><?php esc_html_e('Save verification evidence','elev8-os'); ?></button></p></form></details><?php endif; ?></article>
                <?php endforeach; ?></div>
            </section>

            <?php if ($snapshot['team_view'] && $snapshot['reassignment_suggestions']): ?>
            <section style="background:#eef7ff;border:1px solid #9cc7e8;border-radius:18px;padding:20px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Explainable reassignment suggestions', 'elev8-os'); ?></h2><p><?php esc_html_e('Suggestions never change ownership. Use a governed handoff request when a reassignment makes sense.', 'elev8-os'); ?></p>
                <?php foreach ($snapshot['reassignment_suggestions'] as $suggestion): ?><article style="border-top:1px solid #c8dfef;padding:12px 0"><strong><?php echo esc_html($suggestion['work_title']); ?></strong><p><?php echo esc_html(sprintf(__('%1$s → consider %2$s', 'elev8-os'), $suggestion['from_name'], $suggestion['to_name'])); ?></p><p style="color:#555"><?php echo esc_html($suggestion['reason']); ?></p><p><strong><?php echo esc_html(sprintf(__('Fit score: %d','elev8-os'),(int)($suggestion['match_score']??0))); ?></strong></p></article><?php endforeach; ?>
            </section>
            <?php endif; ?>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Waiting on and bottlenecks', 'elev8-os'); ?></h2>
            <?php foreach ($snapshot['bottlenecks'] as $item): ?><article style="border-top:1px solid #eee;padding:14px 0"><h3 style="margin:0"><?php echo esc_html($item['title']); ?></h3><p><?php echo esc_html(sprintf(__('Owner: %1$s · Bottleneck score: %2$d · Waiting on: %3$d · Blocking: %4$d', 'elev8-os'), $item['owner_name'], $item['bottleneck_score'], count($item['open_dependencies']), count($item['dependent_ids']))); ?></p>
                <?php if (Elev8_OS_Team_Coordination_Service::can_change_work((int)$item['id'],$user)): ?><details><summary><?php esc_html_e('Manage dependencies or request handoff', 'elev8-os'); ?></summary>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0"><input type="hidden" name="action" value="elev8_save_work_dependencies"><input type="hidden" name="work_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_save_work_dependencies_'.$item['id']); ?><label><?php esc_html_e('Waiting on Work Items', 'elev8-os'); ?><select name="dependency_ids[]" multiple size="5" style="display:block;min-width:320px;max-width:100%">
                    <?php foreach ($snapshot['items'] as $candidate): if ((int)$candidate['id']===(int)$item['id']) continue; ?><option value="<?php echo esc_attr((string)$candidate['id']); ?>" <?php selected(in_array((int)$candidate['id'],Elev8_OS_Team_Coordination_Service::dependencies((int)$item['id']),true)); ?>><?php echo esc_html($candidate['title'].' — '.$candidate['owner_name']); ?></option><?php endforeach; ?></select></label><p><button class="button" type="submit"><?php esc_html_e('Save waiting-on relationships', 'elev8-os'); ?></button></p></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0"><input type="hidden" name="action" value="elev8_save_work_skills"><input type="hidden" name="work_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_save_work_skills_'.$item['id']); ?><label><?php esc_html_e('Required skills for handoff fit','elev8-os'); ?><textarea name="required_skills" rows="2" style="display:block;width:100%" placeholder="customer service, inventory, networking"><?php echo esc_textarea(implode(', ',Elev8_OS_Team_Availability_Skill_Service::required_skills((int)$item['id']))); ?></textarea></label><p><button class="button" type="submit"><?php esc_html_e('Save skill requirements','elev8-os'); ?></button></p></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_request_work_handoff"><input type="hidden" name="work_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_request_work_handoff_'.$item['id']); ?><label><?php esc_html_e('Propose handoff to', 'elev8-os'); ?> <select name="to_user_id"><?php foreach ($users as $assignable): ?><option value="<?php echo esc_attr((string)$assignable->ID); ?>"><?php echo esc_html($assignable->display_name); ?></option><?php endforeach; ?></select></label><label style="display:block;margin-top:8px"><?php esc_html_e('Handoff note', 'elev8-os'); ?><textarea name="note" rows="2" style="display:block;width:100%"></textarea></label><p><button class="button button-primary" type="submit"><?php esc_html_e('Request acknowledgement', 'elev8-os'); ?></button></p></form>
                </details><?php endif; ?></article><?php endforeach; ?>
            <?php if (!$snapshot['bottlenecks']): ?><p><?php esc_html_e('No dependency bottlenecks are currently visible in your scope.', 'elev8-os'); ?></p><?php endif; ?></section>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px"><h2 style="margin-top:0"><?php esc_html_e('Completed handoffs', 'elev8-os'); ?></h2><?php foreach ($snapshot['handoffs'] as $handoff): $from=get_user_by('id',(int)$handoff['from_user_id']);$to=get_user_by('id',(int)$handoff['to_user_id']); ?><p><strong><?php echo esc_html($handoff['work_title']); ?></strong> — <?php echo esc_html(sprintf(__('%1$s to %2$s on %3$s', 'elev8-os'), $from instanceof WP_User?$from->display_name:__('Unassigned','elev8-os'), $to instanceof WP_User?$to->display_name:__('Unknown','elev8-os'), $handoff['created_at'])); ?><?php if (!empty($handoff['note'])): ?><br><span style="color:#666"><?php echo esc_html($handoff['note']); ?></span><?php endif; ?></p><?php endforeach; ?><?php if (!$snapshot['handoffs']): ?><p><?php esc_html_e('No accepted handoffs have been recorded yet.', 'elev8-os'); ?></p><?php endif; ?></section>
            <p style="color:#666;margin-top:18px"><?php esc_html_e('Team Coordination extends Universal Work Items. Capacity and suggestions are guidance; assignment changes require acknowledgement.', 'elev8-os'); ?></p>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    public static function save_dependencies(): void {
        $work_id=absint($_POST['work_id']??0); check_admin_referer('elev8_save_work_dependencies_'.$work_id);
        self::redirect(Elev8_OS_Team_Coordination_Service::set_dependencies($work_id,(array)($_POST['dependency_ids']??[])));
    }

    public static function request_handoff(): void {
        $work_id=absint($_POST['work_id']??0); check_admin_referer('elev8_request_work_handoff_'.$work_id);
        self::redirect(Elev8_OS_Team_Capacity_Service::request_handoff($work_id,absint($_POST['to_user_id']??0),sanitize_textarea_field(wp_unslash((string)($_POST['note']??'')))));
    }

    public static function decide_handoff(): void {
        $work_id=absint($_POST['work_id']??0); $request_id=sanitize_text_field(wp_unslash((string)($_POST['request_id']??''))); check_admin_referer('elev8_decide_work_handoff_'.$work_id.'_'.$request_id);
        self::redirect(Elev8_OS_Team_Capacity_Service::decide_handoff($work_id,$request_id,sanitize_key((string)($_POST['decision']??'')),sanitize_textarea_field(wp_unslash((string)($_POST['note']??'')))));
    }

    public static function save_capacity_target(): void {
        $user_id=absint($_POST['user_id']??0); check_admin_referer('elev8_save_capacity_target_'.$user_id);
        self::redirect(Elev8_OS_Team_Capacity_Service::set_target($user_id,absint($_POST['target']??0)));
    }

    public static function save_coordination_profile(): void {
        $user_id=absint($_POST['user_id']??0); check_admin_referer('elev8_save_coordination_profile_'.$user_id);
        self::redirect(Elev8_OS_Team_Availability_Skill_Service::save_profile(
            $user_id,
            sanitize_key((string)($_POST['availability_state']??'available')),
            sanitize_text_field(wp_unslash((string)($_POST['availability_until']??''))),
            sanitize_textarea_field(wp_unslash((string)($_POST['availability_note']??''))),
            [(string)wp_unslash($_POST['skills']??'')]
        ));
    }

    public static function save_work_skills(): void {
        $work_id=absint($_POST['work_id']??0); check_admin_referer('elev8_save_work_skills_'.$work_id);
        self::redirect(Elev8_OS_Team_Availability_Skill_Service::save_required_skills($work_id,[(string)wp_unslash($_POST['required_skills']??'')]));
    }

    public static function save_availability_calendar(): void {
        $user_id=absint($_POST['user_id']??0); check_admin_referer('elev8_save_availability_calendar_'.$user_id);
        self::redirect(Elev8_OS_Team_Availability_Calendar_Service::save_windows($user_id,(string)wp_unslash($_POST['availability_windows']??'')));
    }

    public static function verify_coordination_skills(): void {
        $user_id=absint($_POST['user_id']??0); check_admin_referer('elev8_verify_coordination_skills_'.$user_id);
        self::redirect(Elev8_OS_Team_Availability_Calendar_Service::verify_skills($user_id,(array)($_POST['verified_skills']??[]),sanitize_textarea_field(wp_unslash((string)($_POST['verification_note']??'')))));
    }

    private static function redirect($result): void {
        $args=is_wp_error($result)?['coordination_error'=>$result->get_error_message()]:['coordination_saved'=>'1'];
        wp_safe_redirect(add_query_arg($args,self::url())); exit;
    }

    private static function metric(string $label,int $value): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:16px"><strong style="display:block;font-size:28px">'.esc_html((string)$value).'</strong><span>'.esc_html($label).'</span></article>';
    }
}
