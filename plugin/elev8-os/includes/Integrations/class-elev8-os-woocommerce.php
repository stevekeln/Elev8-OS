<?php
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_WooCommerce {
    public static function is_available(): bool { return class_exists('WooCommerce'); }
}
