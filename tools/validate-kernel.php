<?php
$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) { fwrite(STDERR, "Plugin root is required.\n"); exit(1); }
$loader = $root . '/includes/class-elev8-os-loader.php';
$kernel = $root . '/includes/Services/class-elev8-os-platform-kernel.php';
$errors = [];
if (!is_file($kernel)) { $errors[] = 'Platform Kernel class file is missing.'; }
if (!is_file($loader)) { $errors[] = 'Loader file is missing.'; }
if (!$errors) {
    $text = file_get_contents($loader);
    $required = ['core','access','workspace-resolver','route-registry','ui-framework','widget-registry','workspace-registry'];
    foreach ($required as $id) {
        if (strpos($text, "register('{$id}'") === false) { $errors[] = "Kernel component '{$id}' is not registered."; }
    }
    if (strpos($text, 'Elev8_OS_Platform_Kernel::boot_many') === false) { $errors[] = 'Kernel boot sequence is missing.'; }
}
if ($errors) { foreach ($errors as $error) { fwrite(STDERR, "[FAIL] {$error}\n"); } exit(1); }
echo "Platform Kernel validation passed (7 foundational components).\n";
