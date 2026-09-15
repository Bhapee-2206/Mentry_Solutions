<?php
header("Content-Type: text/plain");
header("Cache-Control: no-cache");
$base = __DIR__ . "/../";
$paths = ["push/onesignal/OneSignalSDKWorker.js" => $base . "push/onesignal/OneSignalSDKWorker.js", "OneSignalSDKWorker.js (root)" => $base . "OneSignalSDKWorker.js"];
foreach ($paths as $label => $path) { $real = realpath($path); echo "[$label]\n  exists: " . (file_exists($path) ? "YES" : "NO") . "\n  realpath: " . ($real ?: "null") . "\n"; if ($real && is_file($real)) { echo "  size: " . filesize($real) . "\n  content: " . trim(file_get_contents($real)) . "\n"; } echo "\n"; }
$pushDir = $base . "push";
echo "[push/ dir]\n";
if (is_dir($pushDir)) { $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pushDir)); foreach ($iter as $f) { if ($f->isFile()) echo "  " . $f->getPathname() . " (" . $f->getSize() . ")\n"; } } else { echo "  NOT FOUND\n"; }

