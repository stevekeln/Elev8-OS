<?php
/** Build-time route registry validator. */
$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Route validation failed: plugin source root is missing.\n");
    exit(1);
}
$service = $root . '/includes/Services/class-elev8-os-route-registry-service.php';
if (!is_file($service)) {
    fwrite(STDERR, "Route validation failed: route registry service is missing.\n");
    exit(1);
}
$text = file_get_contents($service);
preg_match_all("/self::register\(\s*'([^']+)'/", $text, $m);
$registered = $m[1] ?? [];
$duplicates = array_diff_assoc($registered, array_unique($registered));
$errors = [];
if ($duplicates) { $errors[] = 'Duplicate route IDs: ' . implode(', ', array_unique($duplicates)); }

$used = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') { continue; }
    $source = file_get_contents($file->getPathname());
    preg_match_all("/Elev8_OS_Route_Registry_Service::url\(\s*'([^']+)'/", $source, $calls);
    foreach (($calls[1] ?? []) as $id) { $used[$id][] = $file->getPathname(); }
}
foreach ($used as $id => $files) {
    if (!in_array($id, $registered, true)) {
        $errors[] = "Unregistered route '$id' referenced by " . implode(', ', $files);
    }
}
if ($errors) {
    fwrite(STDERR, "Elev8 OS route validation failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}
echo 'Route registry validation passed: ' . count($registered) . ' routes registered, ' . count($used) . " route IDs referenced.\n";
