<?php
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Class_Approval_Module {
    /** Stable route contract for integrations and workspace links. */
    public static function url(array $args = []): string {
        $base = class_exists('Elev8_OS_Glass_Manager_Suite_Module')
            ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool' => 'approvals'])
            : admin_url('admin.php?page=elev8-glass-operations');
        return $args ? add_query_arg($args, $base) : $base;
    }
    public static function init(): void {
        add_action('admin_post_elev8_os_class_decision', [__CLASS__, 'handle_decision']);
        add_action('wp_ajax_elev8_os_pending_class_count', [__CLASS__, 'ajax_count']);
    }
    public static function render(): void {
        $rows=Elev8_OS_Class_Approval_Service::pending_for_current_user();
        $urgent=count(array_filter($rows,static fn($r)=>!empty($r['urgent'])));
        ?>
        <section class="elev8-class-approvals" data-elev8-class-approvals data-count="<?php echo esc_attr((string)count($rows)); ?>">
          <header><div><p class="eyebrow">Class decisions</p><h1>Class Approval Center</h1><p>Approve, move, or cancel pending Amelia bookings without entering WordPress admin.</p></div><div class="approval-count"><strong><?php echo esc_html((string)count($rows)); ?></strong><span>pending</span><?php if($urgent):?><b><?php echo esc_html((string)$urgent); ?> urgent</b><?php endif;?></div></header>
          <div class="notification-setup"><div><strong>Phone alerts</strong><p>Enable browser notifications so Elev8 OS can alert this device while the app is installed or open.</p></div><button type="button" class="button button-primary" data-enable-class-alerts>Enable Phone Notifications</button><button type="button" class="button" data-test-class-alert>Test Alert</button><span data-alert-status></span></div>
          <?php if(!$rows):?><div class="empty"><h2>No pending class decisions</h2><p>Everything currently verified in Amelia has been handled.</p></div><?php endif;?>
          <div class="approval-list">
          <?php foreach($rows as$row): $customer=(array)$row['customer']; ?>
            <article class="approval-card <?php echo !empty($row['urgent'])?'is-urgent':''; ?>">
              <div class="approval-card__top"><span><?php echo !empty($row['urgent'])?'URGENT':'PENDING'; ?></span><time><?php echo esc_html(wp_date('l, F j, Y · '.get_option('time_format'),strtotime((string)$row['booking_start']))); ?></time></div>
              <h2><?php echo esc_html((string)$row['service']); ?></h2>
              <dl><div><dt>Customer</dt><dd><?php echo esc_html((string)($customer['name']?:'Customer')); ?></dd></div><div><dt>Guests</dt><dd><?php echo esc_html((string)max(1,(int)$row['persons'])); ?></dd></div><div><dt>Teacher</dt><dd><?php echo esc_html((string)$row['teacher']); ?></dd></div><div><dt>Location</dt><dd><?php echo esc_html((string)$row['location']); ?></dd></div></dl>
              <?php if(!empty($customer['phone'])||!empty($customer['email'])):?><p class="contact"><?php if(!empty($customer['phone'])):?><a href="tel:<?php echo esc_attr((string)$customer['phone']); ?>"><?php echo esc_html((string)$customer['phone']); ?></a><?php endif;?> <?php if(!empty($customer['email'])):?><a href="mailto:<?php echo esc_attr((string)$customer['email']); ?>"><?php echo esc_html((string)$customer['email']); ?></a><?php endif;?></p><?php endif;?>
              <div class="approval-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_class_decision"><input type="hidden" name="decision" value="approve"><input type="hidden" name="booking_id" value="<?php echo esc_attr((string)$row['booking_id']); ?>"><?php wp_nonce_field('elev8_os_class_decision_'.$row['booking_id']); ?><button class="button button-primary">Approve</button></form>
                <details><summary class="button">Move Date</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_class_decision"><input type="hidden" name="decision" value="move"><input type="hidden" name="booking_id" value="<?php echo esc_attr((string)$row['booking_id']); ?>"><?php wp_nonce_field('elev8_os_class_decision_'.$row['booking_id']); ?><label>New date and time<input type="datetime-local" name="new_start" required></label><button class="button button-primary">Move Booking</button></form></details>
                <details><summary class="button">Cancel</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_class_decision"><input type="hidden" name="decision" value="cancel"><input type="hidden" name="booking_id" value="<?php echo esc_attr((string)$row['booking_id']); ?>"><?php wp_nonce_field('elev8_os_class_decision_'.$row['booking_id']); ?><label>Reason<textarea name="reason" required></textarea></label><button class="button">Cancel Booking</button></form></details>
              </div>
            </article>
          <?php endforeach;?>
          </div>
        </section>
        <?php
    }
    public static function handle_decision(): void {
        $id=absint($_POST['booking_id']??0); check_admin_referer('elev8_os_class_decision_'.$id);
        if(!Elev8_OS_Access_Service::user_can('view_glass_dashboard'))wp_die('Not permitted.');
        $decision=sanitize_key($_POST['decision']??''); $ok=false;
        if($decision==='approve')$ok=Elev8_OS_Class_Approval_Service::approve($id);
        elseif($decision==='move')$ok=Elev8_OS_Class_Approval_Service::move($id,sanitize_text_field($_POST['new_start']??''));
        elseif($decision==='cancel')$ok=Elev8_OS_Class_Approval_Service::cancel($id,sanitize_textarea_field($_POST['reason']??''));
        $url=Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'approvals','elev8_notice'=>$ok?'saved':'error']); wp_safe_redirect($url); exit;
    }
    public static function ajax_count(): void { check_ajax_referer('elev8_class_alerts','nonce'); wp_send_json_success(['count'=>count(Elev8_OS_Class_Approval_Service::pending_for_current_user()),'url'=>Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'approvals'])]); }
}
