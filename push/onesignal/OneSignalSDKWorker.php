<?php
// OneSignal dedicated service worker - served as JS via PHP to guarantee correct content and headers
header("Content-Type: application/javascript; charset=utf-8");
header("Service-Worker-Allowed: /push/onesignal/");
header("Cache-Control: no-cache, no-store, must-revalidate");
echo "importScripts(\"https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js\");\n";
exit;
