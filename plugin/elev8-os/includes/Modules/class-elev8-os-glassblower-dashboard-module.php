<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Glassblower_Dashboard_Module {
    public static function render(WP_User $user): void {
        $jobs = Elev8_OS_Glass_Operations_Service::jobs(['assigned_user_id' => (int)$user->ID, 'limit' => 100]);
        $pay = Elev8_OS_Glass_Operations_Service::blower_pay_summary((int)$user->ID);
        $profile = class_exists('Elev8_OS_Production_Catalog_Service') ? Elev8_OS_Production_Catalog_Service::compensation_profile((int)$user->ID) : null;
        $active = array_values(array_filter($jobs, static fn(array $j): bool => !in_array($j['status'], ['completed','cancelled'], true)));
        $qc = array_values(array_filter($jobs, static fn(array $j): bool => $j['status'] === 'quality_control'));
        ?>
        <div class="elev8-artist-dashboard elev8-dashboard-v2 elev8-glassblower-home">
            <header class="elev8-dashboard-header elev8-dashboard-hero">
                <div><p class="elev8-eyebrow">Elev8 Premier · Glassblower Operational Home</p><h1><?php echo esc_html(sprintf('Welcome back, %s!', $user->display_name)); ?></h1><p><?php echo esc_html(wp_date('l, F j')); ?> · Here is your production work and tracked pay.</p></div>
                <span class="elev8-dashboard-badge">Glassblower</span>
            </header>
            <section class="elev8-dashboard-stat-grid">
                <article><span>Assigned jobs</span><strong><?php echo count($active); ?></strong><small>Open production assigned to you</small></article>
                <article><span>Waiting for QC</span><strong><?php echo count($qc); ?></strong><small>Jobs ready for manager review</small></article>
                <article><span>Pending pay</span><strong>$<?php echo number_format_i18n((float)$pay['pending'], 2); ?></strong><small>Waiting for manager approval</small></article>
                <article><span>Approved pay</span><strong>$<?php echo number_format_i18n((float)$pay['approved'], 2); ?></strong><small>Approved tracked production pay</small></article>
            </section>
            <section class="elev8-dashboard-panel"><div class="elev8-dashboard-section-heading"><div><p class="elev8-eyebrow">My Production</p><h2>Assigned Jobs</h2></div></div>
                <?php if (!$active) : ?><p>No active production jobs are assigned to you.</p><?php else : ?><div class="elev8-dashboard-list"><?php foreach ($active as $job) : ?><article><div><strong><?php echo esc_html($job['product_name']); ?></strong><p><?php echo esc_html(ucwords(str_replace('_',' ',$job['source']))); ?> · Due <?php echo esc_html($job['due_date'] ?: 'Unavailable'); ?> · <?php echo esc_html(ucwords(str_replace('_',' ',$job['status']))); ?></p></div></article><?php endforeach; ?></div><?php endif; ?>
            </section>
            <section class="elev8-dashboard-panel"><div class="elev8-dashboard-section-heading"><div><p class="elev8-eyebrow">My Pay</p><h2>Current Production Pay</h2></div></div>
                <div class="elev8-dashboard-stat-grid"><article><span>Hourly</span><strong>$<?php echo number_format_i18n((float)$pay['hourly'],2); ?></strong><small><?php echo $profile ? '$' . number_format_i18n((float)$profile['hourly_rate'],2) . '/hour profile' : 'Hourly profile unavailable'; ?></small></article><article><span>Piecework</span><strong>$<?php echo number_format_i18n((float)$pay['piecework'],2); ?></strong><small>Catalog-controlled piece rates</small></article></div>
                <?php if (!empty($pay['entries'])) : ?><div class="elev8-dashboard-list"><?php foreach (array_slice($pay['entries'],0,12) as $entry) : ?><article><div><strong><?php echo esc_html($entry['item_name']); ?></strong><p><?php echo esc_html($entry['work_date']); ?> · <?php echo esc_html(ucfirst($entry['approval_status'])); ?></p></div><strong>$<?php echo number_format_i18n((float)$entry['total'],2); ?></strong></article><?php endforeach; ?></div><?php endif; ?>
            </section>
            <section class="elev8-dashboard-panel"><div class="elev8-dashboard-section-heading"><div><p class="elev8-eyebrow">Questions & Issues</p><h2>Contact the Glass Manager</h2></div></div><p>Use Conversations to report missing materials, unclear instructions, rework, quantity disagreements or pay questions. Keep the message tied to the production job whenever possible.</p><p><a class="elev8-dashboard-button" href="<?php echo esc_url(home_url('/elev8-conversations/')); ?>">Open Conversations</a></p></section>
        </div>
        <?php
    }
}
