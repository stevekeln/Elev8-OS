<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shared configuration and state contract for the Elev8 OS install helper.
 *
 * Dashboards render the same module; this service supplies role-neutral URLs,
 * storage keys, labels, and layout selectors without duplicating install logic.
 */
final class Elev8_OS_Install_Helper_Service {
    public static function config(): array {
        $home = class_exists('Elev8_OS_Workspace_Resolver_Service')
            ? Elev8_OS_Workspace_Resolver_Service::destination(null, true)
            : (class_exists('Elev8_OS_Mobile_Home_Module') ? Elev8_OS_Mobile_Home_Module::get_url() : home_url('/elev8-app/'));

        return (array) apply_filters('elev8_os_install_helper_config', [
            'homeUrl' => $home,
            'storageKey' => 'elev8_os_install_dismissed_v2',
            'installedKey' => 'elev8_os_installed_v2',
            'protectedSelectors' => [
                '.elev8-experience-dock',
                '.elev8-shortcut-fab',
                '[data-elev8-protected-floating]',
            ],
            'labels' => [
                'install' => __('Install App', 'elev8-os'),
                'installed' => __('Open App', 'elev8-os'),
                'open' => __('Open App', 'elev8-os'),
            ],
        ]);
    }
}
