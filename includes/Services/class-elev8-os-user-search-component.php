<?php
/**
 * Reusable searchable WordPress user selector for Elev8 OS admin workflows.
 *
 * Keeps WordPress authentication data in WordPress while presenting Elev8 OS
 * identity and assignment context to administrators.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_User_Search_Component {
    private const EMPLOYEE_META = 'elev8_os_amelia_employee_id';
    private const PROFILE_OPTION = 'elev8_os_artist_profiles';

    /**
     * Render an accessible searchable WordPress-account selector.
     *
     * @param array<string,mixed> $args
     */
    public static function render(array $args = []): void {
        $defaults = [
            'name' => 'wp_user_id',
            'id' => 'elev8-connected-account',
            'selected' => 0,
            'artist_id' => 0,
            'label' => __('Connected Account', 'elev8-os'),
            'description' => __('Search by artist name, email address, or WordPress username.', 'elev8-os'),
            'none_label' => __('Not connected', 'elev8-os'),
        ];
        $args = wp_parse_args($args, $defaults);
        $artist_id = absint($args['artist_id']);
        $selected = absint($args['selected']);
        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
        ]);
        $assignments = self::assignment_index();
        $search_id = sanitize_html_class((string) $args['id']) . '-search';
        ?>
        <div class="elev8-identity-field">
            <label for="<?php echo esc_attr($search_id); ?>"><strong><?php echo esc_html((string) $args['label']); ?></strong></label>
            <p class="description"><?php echo esc_html((string) $args['description']); ?></p>
            <div class="elev8-user-account-picker" data-elev8-user-picker>
                <input type="search" id="<?php echo esc_attr($search_id); ?>" class="elev8-user-account-search" placeholder="<?php esc_attr_e('Search name, email, or username…', 'elev8-os'); ?>" autocomplete="off" data-elev8-user-search aria-controls="<?php echo esc_attr((string) $args['id']); ?>">
                <select name="<?php echo esc_attr((string) $args['name']); ?>" id="<?php echo esc_attr((string) $args['id']); ?>" data-elev8-user-select>
                    <option value="0"><?php echo esc_html((string) $args['none_label']); ?></option>
                    <?php foreach ($users as $user):
                        $user_id = absint($user->ID);
                        $assignment = $assignments[$user_id] ?? ['artist_id' => 0, 'artist_name' => '', 'source' => ''];
                        $assigned_artist_id = absint($assignment['artist_id'] ?? 0);
                        $is_current = $artist_id > 0 && $assigned_artist_id === $artist_id;
                        $is_conflict = $assigned_artist_id > 0 && !$is_current;
                        $status = $is_current ? __('Connected', 'elev8-os') : ($is_conflict ? sprintf(__('Already mapped to %s', 'elev8-os'), (string) $assignment['artist_name']) : __('Available', 'elev8-os'));
                        $label = sprintf('%s — %s — %s — %s', $user->display_name, $user->user_email, $user->user_login, $status);
                        ?>
                        <option value="<?php echo esc_attr((string) $user_id); ?>"
                            data-search="<?php echo esc_attr(strtolower($user->display_name . ' ' . $user->user_email . ' ' . $user->user_login)); ?>"
                            data-status="<?php echo esc_attr($is_current ? 'connected' : ($is_conflict ? 'conflict' : 'available')); ?>"
                            data-status-label="<?php echo esc_attr($status); ?>"
                            <?php selected($selected, $user_id); ?>
                            <?php disabled($is_conflict && $selected !== $user_id); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="elev8-user-search-status" data-elev8-user-search-status aria-live="polite"></p>
                <div class="elev8-selected-identity" data-elev8-selected-identity aria-live="polite"></div>
            </div>
            <p class="description elev8-identity-authority"><strong><?php esc_html_e('Elev8 Identity:', 'elev8-os'); ?></strong> <?php esc_html_e('Approved Artist Mapping remains the source of truth. Accounts assigned to another artist cannot be selected here.', 'elev8-os'); ?></p>
        </div>
        <?php
    }

    /** @return array<int,array{artist_id:int,artist_name:string,source:string}> */
    private static function assignment_index(): array {
        $index = [];
        $profiles = get_option(self::PROFILE_OPTION, []);
        if (is_array($profiles)) {
            foreach ($profiles as $artist_id => $profile) {
                if (!is_array($profile)) { continue; }
                $user_id = absint($profile['wp_user_id'] ?? 0);
                if ($user_id <= 0) { continue; }
                $index[$user_id] = [
                    'artist_id' => absint($artist_id),
                    'artist_name' => self::artist_name(absint($artist_id)),
                    'source' => 'legacy_profile',
                ];
            }
        }
        $mapped_users = get_users([
            'meta_key' => self::EMPLOYEE_META,
            'meta_compare' => 'EXISTS',
            'fields' => 'ids',
        ]);
        foreach ((array) $mapped_users as $user_id) {
            $artist_id = absint(get_user_meta(absint($user_id), self::EMPLOYEE_META, true));
            if ($artist_id <= 0) { continue; }
            $index[absint($user_id)] = [
                'artist_id' => $artist_id,
                'artist_name' => self::artist_name($artist_id),
                'source' => 'approved_mapping',
            ];
        }
        return $index;
    }

    private static function artist_name(int $artist_id): string {
        global $wpdb;
        if ($artist_id <= 0) { return __('another artist', 'elev8-os'); }
        $table = $wpdb->prefix . 'amelia_users';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) { return sprintf(__('artist #%d', 'elev8-os'), $artist_id); }
        $row = $wpdb->get_row($wpdb->prepare("SELECT firstName, lastName, email FROM `{$table}` WHERE id = %d LIMIT 1", $artist_id), ARRAY_A);
        if (!is_array($row)) { return sprintf(__('artist #%d', 'elev8-os'), $artist_id); }
        $name = trim((string) ($row['firstName'] ?? '') . ' ' . (string) ($row['lastName'] ?? ''));
        return $name !== '' ? $name : ((string) ($row['email'] ?? '') ?: sprintf(__('artist #%d', 'elev8-os'), $artist_id));
    }
}
