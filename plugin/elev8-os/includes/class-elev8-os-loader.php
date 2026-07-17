<?php
if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Loader {

    public static function boot(): void {
        require_once ELEV8_OS_DIR . 'includes/Support/class-elev8-os-logger.php';
        require_once ELEV8_OS_DIR . 'includes/Integrations/class-elev8-os-amelia.php';
        require_once ELEV8_OS_DIR . 'includes/Integrations/class-elev8-os-woocommerce.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-asset-sale-notification-service.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-user-search-component.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-print-service.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-gallery-operations-service.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-student-relationship-service.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-marketing-service.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-artist-portal-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-waitlist-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-crm-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-dashboard-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-artist-print-center-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-gallery-operations-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-my-classes-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-my-artwork-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-students-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-marketing-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-system-inspector-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-employee-mapping-module.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-business-intelligence.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-recommendation-service.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-recommendation-state-service.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-class-discovery.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-portal-page-manager.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-opportunity-service.php';
        require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-opportunity-activity-service.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-class-demand-manager-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-business-intelligence-dashboard-module.php';
        require_once ELEV8_OS_DIR . 'includes/Modules/class-elev8-os-ceo-dashboard-module.php';
        require_once ELEV8_OS_DIR . 'includes/class-elev8-os.php';

        Elev8_OS::init();
        Elev8_OS_WooCommerce::init();
        Elev8_OS_Asset_Sale_Notification_Service::init();
        Elev8_OS_Portal_Page_Manager::init();
        Elev8_OS_Recommendation_State_Service::init();
        Elev8_OS_Gallery_Operations_Service::init();
        Elev8_OS_Student_Relationship_Service::init();
        Elev8_OS_Marketing_Service::init();
        Elev8_OS_Artist_Portal_Module::init();
        Elev8_OS_Dashboard_Module::init();
        Elev8_OS_Artist_Print_Center_Module::init();
        Elev8_OS_Gallery_Operations_Module::init();
        Elev8_OS_My_Classes_Module::init();
        Elev8_OS_My_Artwork_Module::init();
        Elev8_OS_Students_Module::init();
        Elev8_OS_Marketing_Module::init();
        Elev8_OS_Waitlist_Module::init();
        Elev8_OS_Class_Demand_Manager_Module::init();
        Elev8_OS_System_Inspector_Module::init();
        Elev8_OS_Employee_Mapping_Module::init();
        Elev8_OS_Business_Intelligence_Dashboard_Module::init();
        Elev8_OS_CEO_Dashboard_Module::init();
    }
}
