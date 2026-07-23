<?php
/**
 * Artist sale notifications for Asset Engine purchases.
 *
 * WooCommerce owns customer receipts and payment communication. Elev8 OS owns
 * the artist-facing business notification because it understands asset
 * ownership and the Artist Portal identity mapping.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Asset_Sale_Notification_Service {

    private const NOTIFIED_META = '_elev8_os_artist_sale_notified_at';
    private const NOTIFIED_EMAIL_META = '_elev8_os_artist_sale_notified_email';

    public static function init(): void {
        add_action('elev8_os_asset_sold', [__CLASS__, 'notify_artist'], 10, 4);
    }

    /**
     * @param array<string,mixed> $asset
     * @param WC_Order_Item_Product $item
     * @param WC_Order $order
     */
    public static function notify_artist(array $asset, int $order_id, $item, $order): void {
        if (!$order instanceof WC_Order || !$item instanceof WC_Order_Item_Product) {
            return;
        }

        // WooCommerce may transition through Processing and Completed. The
        // order-item marker makes this notification safe to call repeatedly.
        if ((string) $item->get_meta(self::NOTIFIED_META, true) !== '') {
            return;
        }

        $owner_user_id = absint($asset['owner_user_id'] ?? 0);
        $artist = $owner_user_id > 0 ? get_userdata($owner_user_id) : false;
        $artist_email = $artist instanceof WP_User ? sanitize_email((string) $artist->user_email) : '';

        if ($artist_email === '' || !is_email($artist_email)) {
            self::notify_admin_of_missing_artist_email($asset, $order);
            $order->add_order_note(
                sprintf(
                    /* translators: %s: asset title. */
                    __('Elev8 OS could not send the artist sale notification for “%s” because no valid artist email was available.', 'elev8-os'),
                    (string) ($asset['title'] ?? __('Artwork', 'elev8-os'))
                )
            );
            return;
        }

        $subject = sprintf(
            /* translators: %s: artwork title. */
            __('You sold “%s” through Elev8 Arts', 'elev8-os'),
            (string) ($asset['title'] ?? __('Artwork', 'elev8-os'))
        );

        $message = self::build_message($asset, $order, $item, $artist);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = Elev8_OS_Notification_Service::send_email($artist_email, $subject, $message, $headers);

        if (!$sent) {
            Elev8_OS_Logger::error('Artist asset sale notification failed.', [
                'order_id' => $order_id,
                'asset_id' => absint($asset['id'] ?? 0),
                'owner_user_id' => $owner_user_id,
            ]);
            $order->add_order_note(
                sprintf(
                    /* translators: %s: artist email address. */
                    __('Elev8 OS attempted to send the artist sale notification to %s, but WordPress reported that the email was not sent.', 'elev8-os'),
                    $artist_email
                )
            );
            return;
        }

        $notified_at = current_time('mysql');
        $item->update_meta_data(self::NOTIFIED_META, $notified_at);
        $item->update_meta_data(self::NOTIFIED_EMAIL_META, $artist_email);
        $item->save();

        $order->add_order_note(
            sprintf(
                /* translators: 1: artwork title, 2: artist email. */
                __('Elev8 OS sent an artist sale notification for “%1$s” to %2$s.', 'elev8-os'),
                (string) ($asset['title'] ?? __('Artwork', 'elev8-os')),
                $artist_email
            )
        );

        do_action('elev8_os_asset_sale_notification_sent', $asset, $order, $item, $artist_email);
    }

    /**
     * @param array<string,mixed> $asset
     * @param WC_Order $order
     * @param WC_Order_Item_Product $item
     */
    private static function build_message(array $asset, WC_Order $order, WC_Order_Item_Product $item, WP_User $artist): string {
        $title = (string) ($asset['title'] ?? __('Artwork', 'elev8-os'));
        $asset_number = trim((string) ($asset['asset_number'] ?? ''));
        $sale_total = (float) $item->get_total() + (float) $item->get_total_tax();
        $currency = (string) $order->get_currency();
        $sale_price = function_exists('wc_price')
            ? wc_price($sale_total, ['currency' => $currency])
            : esc_html(number_format_i18n($sale_total, 2) . ' ' . $currency);
        $sold_at = $order->get_date_paid();
        if (!$sold_at) {
            $sold_at = $order->get_date_created();
        }
        $sold_date = $sold_at ? wc_format_datetime($sold_at) : current_time(get_option('date_format') . ' ' . get_option('time_format'));
        $portal_url = home_url('/artist-artwork/');
        $image_url = '';
        $image_id = absint($asset['image_attachment_id'] ?? 0);
        if ($image_id > 0) {
            $image_url = (string) wp_get_attachment_image_url($image_id, 'medium');
        }
        $artist_name = trim((string) $artist->display_name);
        if ($artist_name === '') {
            $artist_name = __('Artist', 'elev8-os');
        }

        ob_start();
        ?>
        <!doctype html>
        <html>
        <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;color:#222;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f4;padding:24px 12px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fff;border-radius:10px;overflow:hidden;">
                            <tr>
                                <td style="padding:28px 30px 12px;">
                                    <h1 style="margin:0 0 12px;font-size:26px;line-height:1.25;">Great news — you sold an item!</h1>
                                    <p style="margin:0 0 18px;font-size:16px;line-height:1.6;">Hi <?php echo esc_html($artist_name); ?>, your artwork sold through Elev8 Arts. We marked it as sold and will remove it from display and prepare it for the customer.</p>
                                </td>
                            </tr>
                            <?php if ($image_url !== '') : ?>
                                <tr>
                                    <td style="padding:0 30px 20px;">
                                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" style="display:block;max-width:100%;height:auto;border-radius:8px;">
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding:0 30px 22px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse;font-size:15px;">
                                        <tr><td style="border-bottom:1px solid #eee;"><strong>Artwork</strong></td><td style="border-bottom:1px solid #eee;"><?php echo esc_html($title); ?></td></tr>
                                        <tr><td style="border-bottom:1px solid #eee;"><strong>Asset number</strong></td><td style="border-bottom:1px solid #eee;"><?php echo esc_html($asset_number !== '' ? $asset_number : __('Unavailable', 'elev8-os')); ?></td></tr>
                                        <tr><td style="border-bottom:1px solid #eee;"><strong>Sale price</strong></td><td style="border-bottom:1px solid #eee;"><?php echo wp_kses_post($sale_price); ?></td></tr>
                                        <tr><td style="border-bottom:1px solid #eee;"><strong>Order</strong></td><td style="border-bottom:1px solid #eee;">#<?php echo esc_html($order->get_order_number()); ?></td></tr>
                                        <tr><td style="border-bottom:1px solid #eee;"><strong>Sold</strong></td><td style="border-bottom:1px solid #eee;"><?php echo esc_html($sold_date); ?></td></tr>
                                        <tr><td><strong>Status</strong></td><td><?php esc_html_e('Sold', 'elev8-os'); ?></td></tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 30px 30px;">
                                    <a href="<?php echo esc_url($portal_url); ?>" style="display:inline-block;background:#2271b1;color:#fff;text-decoration:none;font-weight:bold;padding:12px 18px;border-radius:6px;">View My Artwork</a>
                                    <p style="margin:18px 0 0;font-size:13px;line-height:1.5;color:#666;">Customer payment details and private customer information are intentionally not included in this email.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $asset */
    private static function notify_admin_of_missing_artist_email(array $asset, WC_Order $order): void {
        $admin_email = sanitize_email((string) get_option('admin_email'));
        if ($admin_email === '' || !is_email($admin_email)) {
            return;
        }

        $subject = __('Elev8 OS: artist sale email unavailable', 'elev8-os');
        $message = sprintf(
            "An artwork sold, but Elev8 OS could not find a valid email for the owning artist.\n\nArtwork: %s\nAsset number: %s\nOrder: #%s\nOwner WordPress user ID: %d",
            (string) ($asset['title'] ?? __('Artwork', 'elev8-os')),
            (string) ($asset['asset_number'] ?? __('Unavailable', 'elev8-os')),
            $order->get_order_number(),
            absint($asset['owner_user_id'] ?? 0)
        );
        Elev8_OS_Notification_Service::send_email($admin_email, $subject, $message);
    }
}
