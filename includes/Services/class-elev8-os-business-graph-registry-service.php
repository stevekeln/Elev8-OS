<?php
/**
 * Canonical Business Graph object, ownership, and relationship registry.
 *
 * This service declares architecture. It does not duplicate authoritative
 * records. Engines register object types, source systems, organization scope,
 * and permitted relationships through filters.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Business_Graph_Registry_Service {
    public static function init(): void {
        add_filter('elev8_os_relationship_kinds', [__CLASS__, 'relationship_labels']);
    }

    /** @return array<string,array<string,mixed>> */
    public static function objects(): array {
        $objects = [
            'person' => self::object_definition('Person', 'Identity', 'wordpress', 'user', true, 'WordPress owns authentication and the user account.'),
            'organization' => self::object_definition('Organization Unit', 'Organization', 'elev8_os', 'organization_unit', true, 'Business, brand, location, department, or team.'),
            'product' => self::object_definition('Product', 'Commerce', 'woocommerce', 'product', true, 'WooCommerce owns authoritative product records.'),
            'order' => self::object_definition('Order', 'Commerce', 'woocommerce', 'order', true, 'WooCommerce owns authoritative order records.'),
            'booking' => self::object_definition('Booking', 'Booking', 'amelia', 'booking', true, 'Amelia currently owns authoritative class booking records.'),
            'class' => self::object_definition('Class', 'Booking', 'amelia', 'service_or_event', true, 'Amelia currently owns authoritative schedules and capacity.'),
            'work' => self::object_definition('Work Item', 'Workflow', 'elev8_os', 'elev8_work_item', true, 'Elev8 OS owns operational work and action state.'),
            'conversation' => self::object_definition('Communication', 'Communication', 'elev8_os', 'elev8_conversation', true, 'Elev8 OS owns contextual internal communication.'),
            'knowledge' => self::object_definition('Knowledge Record', 'Knowledge', 'elev8_os', 'knowledge', true, 'SOPs, guides, policies, and Business Memory.'),
            'event' => self::object_definition('Event', 'Events', 'elev8_os', 'event', true, 'Operational event execution and relationships.'),
            'event_application' => self::object_definition('Event Application', 'Events', 'elev8_os', 'elev8_event_app', true, 'Application intake and operational review.'),
            'reservation' => self::object_definition('Reservation', 'Booking', 'elev8_os', 'reservation', true, 'Operational reservation context around authoritative sources.'),
            'production_job' => self::object_definition('Production Job', 'Operations', 'elev8_os', 'production_job', true, 'Glass and future service production execution.'),
            'repair' => self::object_definition('Repair', 'Operations', 'elev8_os', 'repair', true, 'Customer-item repair workflow.'),
            'memorial_case' => self::object_definition('Memorial Case', 'Operations', 'elev8_os', 'memorial_case', true, 'Memorial production and custody workflow.'),
            'payout' => self::object_definition('Payout', 'Financial', 'elev8_os', 'payout', true, 'Operational payout obligations and approvals.'),
            'asset' => self::object_definition('Asset', 'Assets', 'elev8_os', 'asset', true, 'Physical or digital object lifecycle and custody.'),
            'inventory' => self::object_definition('Inventory Record', 'Inventory', 'authoritative_inventory_provider', 'inventory', true, 'Elev8 OS may orchestrate inventory without replacing the configured authority.'),
            'campaign' => self::object_definition('Campaign', 'Marketing', 'elev8_os', 'campaign', true, 'Marketing execution and attribution context.'),
            'opportunity' => self::object_definition('Opportunity', 'Sales', 'elev8_os', 'opportunity', true, 'Sales opportunity and follow-up execution.'),
            'manager_log' => self::object_definition('Manager Log', 'Operations', 'elev8_os', 'elev8_ops_log', true, 'Structured daily operating record.'),
            'workspace' => self::object_definition('Workspace', 'Workflow', 'virtual', 'workspace', false, 'A view over authoritative objects, never a duplicate source record.'),
        ];
        return (array) apply_filters('elev8_os_business_graph_objects', $objects);
    }

    /** @return array<string,array<string,mixed>> */
    public static function relationships(): array {
        $relationships = [
            'related_to' => self::relationship_definition('Related to', ['*'], ['*'], false, 'General contextual link. Prefer a more specific relationship when available.'),
            'belongs_to' => self::relationship_definition('Belongs to', ['*'], ['*'], true, 'Legacy-compatible structural or operational parent relationship. Engines should register narrower relationship kinds for new workflows.'),
            'assigned_to' => self::relationship_definition('Assigned to', ['work','production_job','repair','memorial_case','booking','event'], ['person','organization'], true, 'Responsibility assignment.'),
            'works_at' => self::relationship_definition('Works at', ['person'], ['organization'], true, 'Person assignment to a business, brand, location, department, or team.'),
            'managed_by' => self::relationship_definition('Managed by', ['organization','production_job','repair','memorial_case','event','class'], ['person','organization'], true, 'Management responsibility.'),
            'created_from' => self::relationship_definition('Created from', ['work','production_job','repair','memorial_case','payout','conversation'], ['order','booking','event_application','reservation','conversation','knowledge'], true, 'Operational record created because an authoritative or upstream record exists.'),
            'fulfills' => self::relationship_definition('Fulfills', ['production_job','work','event','class'], ['order','booking','reservation'], true, 'Operational execution fulfills a customer or commerce commitment.'),
            'produces' => self::relationship_definition('Produces', ['production_job'], ['product','asset','inventory'], true, 'Production output.'),
            'generates' => self::relationship_definition('Generates', ['order','booking','event_application','conversation','production_job'], ['work','payout','conversation'], true, 'Upstream record generates downstream operational work or communication.'),
            'participant_in' => self::relationship_definition('Participant in', ['*'], ['*'], true, 'Legacy-compatible participation relationship. Prefer role-specific relationship kinds when available.'),
            'customer_for' => self::relationship_definition('Customer for', ['person'], ['order','booking','repair','memorial_case','reservation'], true, 'Customer relationship without duplicating the person or source transaction.'),
            'teacher_for' => self::relationship_definition('Teacher for', ['person'], ['class','booking'], true, 'Teaching responsibility.'),
            'supports' => self::relationship_definition('Supports', ['*'], ['*'], false, 'Supporting operational relationship.'),
            'depends_on' => self::relationship_definition('Depends on', ['*'], ['*'], false, 'Dependency relationship.'),
            'blocks' => self::relationship_definition('Blocks', ['*'], ['*'], false, 'Blocking dependency.'),
            'follow_up_for' => self::relationship_definition('Follow-up for', ['work','conversation'], ['*'], false, 'Follow-up relationship.'),
        ];
        return (array) apply_filters('elev8_os_business_graph_relationships', $relationships);
    }

    public static function object(string $type): array {
        $type = self::normalize_object_type($type);
        return self::objects()[$type] ?? [];
    }

    public static function relationship(string $kind): array {
        $kind = sanitize_key($kind);
        return self::relationships()[$kind] ?? [];
    }

    public static function normalize_object_type(string $type): string {
        $type = sanitize_key($type);
        $aliases = [
            'user' => 'person', 'employee' => 'person', 'artist' => 'person', 'customer' => 'person', 'vendor' => 'person',
            'org_unit' => 'organization', 'business' => 'organization', 'brand' => 'organization', 'location' => 'organization', 'department' => 'organization', 'team' => 'organization',
            'elev8_work_item' => 'work', 'elev8_conversation' => 'conversation', 'elev8_ops_log' => 'manager_log', 'elev8_event_app' => 'event_application',
            'memorial' => 'memorial_case', 'communication' => 'conversation',
        ];
        return $aliases[$type] ?? $type;
    }

    public static function is_registered_object(string $type): bool {
        return isset(self::objects()[self::normalize_object_type($type)]);
    }

    public static function authoritative_system(string $type): string {
        $object = self::object($type);
        return (string) ($object['authoritative_system'] ?? 'unregistered');
    }

    public static function owning_engine(string $type): string {
        $object = self::object($type);
        return (string) ($object['engine'] ?? 'Unregistered');
    }

    public static function requires_organization_scope(string $type): bool {
        $object = self::object($type);
        return !empty($object['organization_scoped']);
    }

    public static function can_connect(string $from_type, string $to_type, string $kind): bool {
        $from_type = self::normalize_object_type($from_type);
        $to_type = self::normalize_object_type($to_type);
        $rule = self::relationship($kind);
        if (!$rule || !self::is_registered_object($from_type) || !self::is_registered_object($to_type)) { return false; }
        return self::type_allowed($from_type, (array) ($rule['from'] ?? [])) && self::type_allowed($to_type, (array) ($rule['to'] ?? []));
    }

    public static function validate_connection(string $from_type, string $to_type, string $kind): array {
        $from = self::normalize_object_type($from_type);
        $to = self::normalize_object_type($to_type);
        if (!self::is_registered_object($from)) { return ['valid'=>false,'code'=>'unregistered_from','message'=>__('The source object type is not registered in the Business Graph.', 'elev8-os')]; }
        if (!self::is_registered_object($to)) { return ['valid'=>false,'code'=>'unregistered_to','message'=>__('The target object type is not registered in the Business Graph.', 'elev8-os')]; }
        if (!self::relationship($kind)) { return ['valid'=>false,'code'=>'unregistered_relationship','message'=>__('The relationship type is not registered in the Business Graph.', 'elev8-os')]; }
        if (!self::can_connect($from, $to, $kind)) { return ['valid'=>false,'code'=>'relationship_not_allowed','message'=>__('That relationship is not allowed between these Business Graph object types.', 'elev8-os')]; }
        return ['valid'=>true,'code'=>'valid','message'=>''];
    }

    public static function organization_scope_for(string $type, int $id): int {
        $type = self::normalize_object_type($type);
        $id = absint($id);
        if ($id < 1) { return 0; }
        if ($type === 'organization') { return $id; }
        if ($type === 'person' && class_exists('Elev8_OS_Organization_Service')) {
            $assignments = Elev8_OS_Organization_Service::assignments_for_user($id, true);
            foreach ($assignments as $assignment) { if (!empty($assignment['is_primary'])) { return absint($assignment['unit_id'] ?? 0); } }
            return absint($assignments[0]['unit_id'] ?? 0);
        }
        $keys = ['_elev8_org_unit_id','_elev8_organization_unit_id','_elev8_location_id','_elev8_department_id'];
        foreach ($keys as $key) { $scope = absint(get_post_meta($id, $key, true)); if ($scope) { return $scope; } }
        return (int) apply_filters('elev8_os_business_graph_organization_scope', 0, $type, $id);
    }

    public static function diagnostics(): array {
        $objects = self::objects();
        $relationships = self::relationships();
        $authorities = [];
        $engines = [];
        foreach ($objects as $object) {
            $authorities[(string) $object['authoritative_system']] = ($authorities[(string) $object['authoritative_system']] ?? 0) + 1;
            $engines[(string) $object['engine']] = ($engines[(string) $object['engine']] ?? 0) + 1;
        }
        ksort($authorities); ksort($engines);
        return ['object_count'=>count($objects),'relationship_count'=>count($relationships),'authorities'=>$authorities,'engines'=>$engines];
    }

    public static function relationship_labels(array $labels): array {
        foreach (self::relationships() as $kind => $definition) { $labels[$kind] = $definition['label']; }
        return $labels;
    }

    private static function object_definition(string $label, string $engine, string $authority, string $source_type, bool $organization_scoped, string $notes): array {
        return ['label'=>$label,'engine'=>$engine,'authoritative_system'=>$authority,'source_type'=>$source_type,'organization_scoped'=>$organization_scoped,'notes'=>$notes];
    }

    private static function relationship_definition(string $label, array $from, array $to, bool $directional, string $notes): array {
        return ['label'=>$label,'from'=>$from,'to'=>$to,'directional'=>$directional,'notes'=>$notes];
    }

    private static function type_allowed(string $type, array $allowed): bool {
        return in_array('*', $allowed, true) || in_array($type, $allowed, true);
    }
}
