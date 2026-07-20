<?php
if (!defined('ABSPATH')) { exit; }

/** Rule-based foundation for the future AI Artist Coach. */
final class Elev8_OS_Recommendation_Engine {
    /** @param array<string,mixed> $snapshot @return array<int,array<string,string>> */
    public static function recommend(array $snapshot): array {
        $r=[]; $assets=(array)($snapshot['assets']??[]); $classes=(array)($snapshot['classes']??[]);
        if (($assets['total']??0) === 0) $r[] = self::item('Add your first artwork', 'Your storefront is empty. Add one strong piece so customers have something to discover and buy.', 'artwork', 'high');
        elseif (($assets['available']??0) < 3) $r[] = self::item('Build your available inventory', 'Artists with more available work give customers more chances to find the right piece.', 'artwork', 'medium');
        if (($assets['low_inventory']??0) > 0) $r[] = self::item('Review low inventory', sprintf(_n('%d item needs attention before it becomes unavailable.', '%d items need attention before they become unavailable.', (int)$assets['low_inventory'], 'elev8-os'), (int)$assets['low_inventory']), 'artwork', 'high');
        if (($assets['views']??0) > 0 && ($snapshot['sales']['count_month']??0) === 0) $r[] = self::item('Turn views into a sale', 'People are viewing your artwork. Feature your strongest piece and share its direct link or QR code.', 'website', 'medium');
        if (($assets['qr_scans']??0) === 0 && ($assets['public']??0) > 0) $r[] = self::item('Put your QR codes to work', 'Print or share an artwork QR code so in-person visitors can open the story and purchase page.', 'artwork', 'low');
        if (($classes['upcoming_count']??0) === 0) $r[] = self::item('Create your next class', 'No upcoming class is currently verified. A new date gives students something to book.', 'classes', 'high');
        elseif (($classes['seats_available']??0) !== null && ($classes['seats_available']??0) <= 2) $r[] = self::item('Your classes are filling', 'Review demand and consider adding another date while interest is high.', 'classes', 'high');
        if (empty($snapshot['profile']['complete'])) $r[] = self::item('Finish your public profile', 'A complete artist story and contact path builds confidence before a customer books or buys.', 'website', 'medium');
        return array_slice($r, 0, 5);
    }
    private static function item(string $title,string $message,string $action,string $priority): array { return compact('title','message','action','priority'); }
}
