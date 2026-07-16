<?php
/** Shared activity boundary delegating to the established opportunity timeline. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Activity_Service {
    public static function record_opportunity(int $opportunity_id, string $type, string $label, string $details = '', int $interest_id = 0): bool {
        if ($opportunity_id <= 0 || !class_exists('Elev8_OS_Opportunity_Activity_Service')) { return false; }
        Elev8_OS_Opportunity_Activity_Service::record($opportunity_id, sanitize_key($type), $label, $details, $interest_id);
        return true;
    }
}
