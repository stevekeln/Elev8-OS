<?php
/**
 * Elev8 OS build-time static contract validator.
 *
 * Finds direct Elev8_OS_Class::method() calls and confirms the target class
 * and method are declared somewhere in the plugin source. This prevents
 * activation-time fatals caused by undefined static route/service contracts.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(2); }
$pluginRoot = $argv[1] ?? '';
if ($pluginRoot === '' || !is_dir($pluginRoot)) { fwrite(STDERR, "Plugin source folder not found.\n"); exit(2); }
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php' && strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
        $files[] = $file->getPathname();
    }
}
$classes = [];
$calls = [];
foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) { continue; }
    if (preg_match_all('/\bclass\s+(Elev8_OS_[A-Za-z0-9_]+)/', $source, $matches)) {
        foreach ($matches[1] as $className) {
            $classes[$className] = $classes[$className] ?? [];
            if (preg_match_all('/\bfunction\s+([A-Za-z0-9_]+)\s*\(/', $source, $methodMatches)) {
                foreach ($methodMatches[1] as $methodName) { $classes[$className][$methodName] = true; }
            }
        }
    }
    if (preg_match_all('/\b(Elev8_OS_[A-Za-z0-9_]+)::([A-Za-z0-9_]+)\s*\(/', $source, $callMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($callMatches as $match) {
            $before = substr($source, 0, $match[0][1]);
            $line = substr_count($before, "\n") + 1;
            $calls[] = [$match[1][0], $match[2][0], $file, $line];
        }
    }
}
$failures = [];
foreach ($calls as [$className, $methodName, $file, $line]) {
    if (!isset($classes[$className])) {
        $failures[] = "Missing class {$className} referenced at {$file}:{$line}";
        continue;
    }
    if (!isset($classes[$className][$methodName])) {
        $failures[] = "Undefined method {$className}::{$methodName}() referenced at {$file}:{$line}";
    }
}
$failures = array_values(array_unique($failures));
if ($failures) {
    fwrite(STDERR, "Static contract validation failed:\n");
    foreach ($failures as $failure) { fwrite(STDERR, " - {$failure}\n"); }
    exit(1);
}
echo "Static contract validation passed: " . count($calls) . " Elev8 OS calls checked.\n";
