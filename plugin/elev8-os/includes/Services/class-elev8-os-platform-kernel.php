<?php
/**
 * Elev8 OS Platform Kernel.
 *
 * The kernel is the governed bootstrap and extension boundary for platform
 * services. It does not own business logic; it registers and boots reusable
 * platform capabilities in a predictable order.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Platform_Kernel {
    private static array $components = [];
    private static array $booted = [];
    private static array $failures = [];
    private static bool $ready = false;

    public static function init(): void {
        self::$ready = true;
        do_action('elev8_os_kernel_ready', __CLASS__);
    }

    public static function register(string $id, string $class, string $method = 'init', array $meta = []): void {
        $id = sanitize_key($id);
        if ($id === '' || $class === '' || $method === '') { return; }
        self::$components[$id] = [
            'id' => $id,
            'class' => $class,
            'method' => $method,
            'type' => sanitize_key((string) ($meta['type'] ?? 'service')),
            'critical' => !empty($meta['critical']),
            'status' => 'registered',
        ];
    }

    public static function boot(string $id): bool {
        $id = sanitize_key($id);
        if (!isset(self::$components[$id])) { return false; }
        if (isset(self::$booted[$id])) { return true; }

        $component = self::$components[$id];
        $class = $component['class'];
        $method = $component['method'];
        if (!class_exists($class) || !is_callable([$class, $method])) {
            self::$failures[$id] = sprintf('%s::%s is unavailable.', $class, $method);
            self::$components[$id]['status'] = 'failed';
            return false;
        }

        try {
            call_user_func([$class, $method]);
            self::$booted[$id] = true;
            self::$components[$id]['status'] = 'booted';
            do_action('elev8_os_kernel_component_booted', $id, self::$components[$id]);
            return true;
        } catch (Throwable $error) {
            self::$failures[$id] = $error->getMessage();
            self::$components[$id]['status'] = 'failed';
            if (class_exists('Elev8_OS_Logger')) {
                Elev8_OS_Logger::error('Platform kernel boot failure', [
                    'component' => $id,
                    'class' => $class,
                    'method' => $method,
                    'message' => $error->getMessage(),
                ]);
            }
            return false;
        }
    }

    public static function boot_many(array $ids): void {
        foreach ($ids as $id) { self::boot((string) $id); }
    }

    public static function all(): array { return self::$components; }
    public static function failures(): array { return self::$failures; }
    public static function is_ready(): bool { return self::$ready; }
    public static function is_healthy(): bool { return self::$ready && self::$failures === []; }

    public static function snapshot(): array {
        return [
            'ready' => self::$ready,
            'healthy' => self::is_healthy(),
            'registered' => count(self::$components),
            'booted' => count(self::$booted),
            'failed' => count(self::$failures),
            'components' => self::$components,
            'failures' => self::$failures,
        ];
    }
}
