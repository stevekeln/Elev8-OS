<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Loader {
    public static function boot(): void {
        require_once ELEV8_OS_DIR . 'includes/Support/class-elev8-os-logger.php';
        require_once ELEV8_OS_DIR . 'includes/Integrations/class-elev8-os-amelia.php';
        require_once ELEV8_OS_DIR . 'includes/Integrations/class-elev8-os-woocommerce.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-artist-portal-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-waitlist-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-crm-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-dashboard-module.php';
        require_once ELEV8_OS_DIR . 'includes/class-elev8-os.php';

        Elev8_OS::init();
    }
}
