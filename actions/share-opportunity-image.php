<?php
// actions/share-opportunity-image.php - Dynamic Open Graph Share Preview Image Generator
// Generates a 1200x630 professional branded social preview card for Mentry Solutions opportunities.
// Optimized for WhatsApp, Telegram, LinkedIn, Twitter/X, and Facebook scrapers.
// Completely public-safe: Never exposes internal IDs, vendor data, admin notes, or private fields.

ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$id = trim($_GET['id'] ?? ($_GET['jobId'] ?? ''));
$opp = findOpportunityById($id);

if (!$opp) {
    // Fallback default opportunity data if ID is missing or invalid
    $opp = [
        'title' => 'Technical Training Opportunities',
        'city' => 'Pan-India',
        'state' => 'Universities & Colleges',
        'startDate' => date('Y-m-d', strtotime('+3 days')),
        'durationDays' => 5,
        'dailyRateMin' => 6000,
        'dailyRateMax' => 8500,
        'jobId' => !empty($id) ? $id : 'OPPORTUNITY',
        'mode' => 'OFFLINE',
        'skillsRequired' => ['Technical Training', 'Hands-on Workshops'],
        'updatedAt' => 'default'
    ];
}

$jobId = (string)($opp['jobId'] ?? ($opp['mentryId'] ?? 'OPPORTUNITY'));
$updatedAt = (string)($opp['updatedAt'] ?? '1');
$cacheKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $jobId) . '_' . substr(md5($updatedAt . ($opp['title'] ?? '')), 0, 10);
$cacheDir = sys_get_temp_dir();
$cacheFile = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mentry_og_' . $cacheKey . '.png';

// 1. Serve from disk cache if exists
if (file_exists($cacheFile) && filesize($cacheFile) > 1000) {
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
    header('ETag: "' . md5_file($cacheFile) . '"');
    readfile($cacheFile);
    exit();
}

// 2. Prepare Public-Safe Fields
$title = trim((string)($opp['title'] ?? 'Training Opportunity'));
$city = trim((string)($opp['city'] ?? ''));
$state = trim((string)($opp['state'] ?? 'India'));
$location = trim($city . (!empty($city) && !empty($state) ? ', ' : '') . $state);
if (empty($location)) $location = 'Pan-India';

$startDate = !empty($opp['startDate']) ? formatDate($opp['startDate']) : '';
$endDate = !empty($opp['endDate']) ? formatDate($opp['endDate']) : '';
$dates = $startDate . (!empty($endDate) && $endDate !== $startDate ? ' – ' . $endDate : '');
if (!empty($opp['durationDays'])) {
    $dates .= ' • ' . (int)$opp['durationDays'] . ' Days';
}

$minRate = (float)($opp['dailyRateMin'] ?? 0);
$maxRate = (float)($opp['dailyRateMax'] ?? 0);
$rate = 'Guaranteed Remuneration';
if ($minRate > 0 && $maxRate > 0 && $minRate !== $maxRate) {
    $rate = formatINR($minRate) . ' – ' . formatINR($maxRate) . ' / day';
} elseif ($minRate > 0) {
    $rate = formatINR($minRate) . ' / day';
} elseif ($maxRate > 0) {
    $rate = formatINR($maxRate) . ' / day';
}

$mode = strtoupper($opp['mode'] ?? 'OFFLINE');
$modeLabel = $mode === 'ONLINE' ? 'Virtual / Online' : ($mode === 'HYBRID' ? 'Hybrid' : 'Offline (On-Campus)');

$rawSkills = $opp['skillsRequired'] ?? [];
$skillsList = [];
if (is_string($rawSkills)) {
    $dec = json_decode($rawSkills, true);
    $skillsList = is_array($dec) ? $dec : explode(',', $rawSkills);
} elseif (is_array($rawSkills)) {
    $skillsList = $rawSkills;
}
$skillsList = array_values(array_filter(array_map('trim', $skillsList)));
if (empty($skillsList)) {
    $skillsList = ['Technical Training', 'Hands-on Labs'];
}
$topSkills = array_slice($skillsList, 0, 4);

// 3. Create 1200x630 High-Resolution Canvas
if (!function_exists('imagecreatetruecolor')) {
    $fallbackImage = __DIR__ . '/../public/mentry.png';
    if (file_exists($fallbackImage)) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($fallbackImage));
        header('Cache-Control: public, max-age=86400');
        readfile($fallbackImage);
        exit();
    }
}

$width = 1200;
$height = 630;
$im = imagecreatetruecolor($width, $height);
imagealphablending($im, true);
imagesavealpha($im, true);

// Color Palette
$bgCanvas = imagecolorallocate($im, 248, 250, 252);     // #F8FAFC
$bgCard = imagecolorallocate($im, 255, 255, 255);       // #FFFFFF
$borderCard = imagecolorallocate($im, 226, 232, 240);   // #E2E8F0
$borderPill = imagecolorallocate($im, 203, 213, 225);   // #CBD5E1
$accentTeal = imagecolorallocate($im, 13, 148, 136);    // #0D9488 (Mint/Teal Brand)
$accentTealDark = imagecolorallocate($im, 15, 118, 110);// #0F766E
$accentOrange = imagecolorallocate($im, 254, 94, 4);    // #FE5E04 (Mentry Accent)
$badgeBg = imagecolorallocate($im, 236, 253, 245);      // #ECFDF5 (Mint-50)
$badgeBorder = imagecolorallocate($im, 167, 243, 208);  // #A7F3D0 (Mint-200)
$badgeText = imagecolorallocate($im, 6, 95, 70);        // #065F46 (Mint-800)
$textDark = imagecolorallocate($im, 15, 23, 42);        // #0F172A (Slate-900)
$textMuted = imagecolorallocate($im, 100, 116, 139);    // #64748B (Slate-500)
$textLabel = imagecolorallocate($im, 148, 163, 184);    // #94A3B8 (Slate-400)
$statBg = imagecolorallocate($im, 248, 250, 252);       // #F8FAFC
$statBorder = imagecolorallocate($im, 226, 232, 240);   // #E2E8F0
$emeraldGreen = imagecolorallocate($im, 5, 150, 105);   // #059669
$tagBg = imagecolorallocate($im, 241, 245, 249);        // #F1F5F9
$tagText = imagecolorallocate($im, 51, 65, 85);         // #334155

// Fill Outer Background
imagefilledrectangle($im, 0, 0, $width, $height, $bgCanvas);

// Subtle Background Geometric Ornaments
imagefilledrectangle($im, 0, 0, $width, 8, $accentTeal);

// Inner Card Coordinates
$cardX1 = 44;
$cardY1 = 34;
$cardX2 = 1156;
$cardY2 = 596;

// Draw Card (white background + clean border)
imagefilledrectangle($im, $cardX1, $cardY1, $cardX2, $cardY2, $bgCard);
imagerectangle($im, $cardX1, $cardY1, $cardX2, $cardY2, $borderCard);
// Inner accent top stripe on the card
imagefilledrectangle($im, $cardX1, $cardY1, $cardX2, $cardY1 + 5, $accentOrange);

// Determine TTF Font Availability
$fontBold = __DIR__ . '/../assets/fonts/font-bold.ttf';
$fontRegular = __DIR__ . '/../assets/fonts/font-regular.ttf';
$hasTtf = function_exists('imagettftext') && file_exists($fontBold) && file_exists($fontRegular);

// Helper for rendering text cleanly
$renderText = function($image, $size, $x, $y, $color, $text, $bold = false) use ($hasTtf, $fontBold, $fontRegular) {
    if ($hasTtf) {
        $font = $bold ? $fontBold : $fontRegular;
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    } else {
        $fontNum = $size > 14 ? 5 : ($size > 11 ? 4 : 3);
        imagestring($image, $fontNum, $x, $y - 12, $text, $color);
    }
};

// 4. Header Section: Brand Emblem + Identity
$emblemPath = __DIR__ . '/../public/mentry-emblem.png';
$logoDrawn = false;
if (file_exists($emblemPath) && function_exists('imagecreatefrompng')) {
    $emblem = @imagecreatefrompng($emblemPath);
    if ($emblem) {
        $srcW = imagesx($emblem);
        $srcH = imagesy($emblem);
        imagecopyresampled($im, $emblem, 80, 68, 0, 0, 48, 48, $srcW, $srcH);
        imagedestroy($emblem);
        $logoDrawn = true;
    }
}
if (!$logoDrawn) {
    // Fallback vector brand square
    imagefilledrectangle($im, 80, 68, 128, 116, $accentOrange);
    $renderText($im, 20, 92, 102, $bgCard, 'M', true);
}

// Brand Text
$renderText($im, 17, 142, 92, $textDark, 'MENTRY SOLUTIONS', true);
$renderText($im, 10, 144, 112, $textMuted, 'Managed Trainer Network  |  Campus & Corporate Hiring', false);

// Top Right: Badge & Opportunity ID
// Badge Pill: "TRAINING OPPORTUNITY"
$badgeX = 850;
$badgeY = 72;
$badgeW = 226;
$badgeH = 34;
imagefilledrectangle($im, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, $badgeBg);
imagerectangle($im, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, $badgeBorder);
$renderText($im, 10, $badgeX + 18, $badgeY + 22, $badgeText, 'TRAINING OPPORTUNITY', true);

// Header Divider Line
imageline($im, 80, 140, 1120, 140, $borderCard);

// 5. Opportunity Title (Wrapped cleanly)
$titleY = 190;
$words = explode(' ', $title);
$line1 = '';
$line2 = '';

foreach ($words as $w) {
    $test = trim($line1 . ' ' . $w);
    if (strlen($test) <= 44) {
        $line1 = $test;
    } else {
        $line2 = trim($line2 . ' ' . $w);
    }
}

if (strlen($line2) > 52) {
    $line2 = substr($line2, 0, 49) . '...';
}

$renderText($im, 24, 80, $titleY, $textDark, $line1, true);
if (!empty($line2)) {
    $renderText($im, 24, 80, $titleY + 44, $textDark, $line2, true);
    $tagsY = $titleY + 86;
} else {
    $tagsY = $titleY + 46;
}

// 6. Technology & Category Tags
$tagX = 80;
$tagItems = array_merge([$modeLabel], $topSkills);
foreach ($tagItems as $tag) {
    $tag = trim($tag);
    if (empty($tag)) continue;
    $tagWidth = strlen($tag) * 9 + 24;
    if ($tagX + $tagWidth > 1120) break;
    imagefilledrectangle($im, $tagX, $tagsY, $tagX + $tagWidth, $tagsY + 30, $tagBg);
    imagerectangle($im, $tagX, $tagsY, $tagX + $tagWidth, $tagsY + 30, $borderPill);
    $renderText($im, 10, $tagX + 12, $tagsY + 20, $tagText, $tag, true);
    $tagX += $tagWidth + 10;
}

// 7. Key Information Cards (3 Columns)
$infoCardY = 320;
$infoCardH = 136;
$colW = 324;
$gap = 34;

// Column 1: Location
$col1X = 80;
imagefilledrectangle($im, $col1X, $infoCardY, $col1X + $colW, $infoCardY + $infoCardH, $statBg);
imagerectangle($im, $col1X, $infoCardY, $col1X + $colW, $infoCardY + $infoCardH, $statBorder);
imagefilledrectangle($im, $col1X, $infoCardY, $col1X + 4, $infoCardY + $infoCardH, $accentTeal);

$renderText($im, 9, $col1X + 20, $infoCardY + 32, $textLabel, 'LOCATION', true);
$locDisplay = mb_strlen($location, 'UTF-8') > 28 ? mb_substr($location, 0, 26, 'UTF-8') . '...' : $location;
$renderText($im, 15, $col1X + 20, $infoCardY + 68, $textDark, $locDisplay, true);
$subLoc = (!empty($state) ? $state . ', ' : '') . 'India';
$renderText($im, 10, $col1X + 20, $infoCardY + 98, $textMuted, $subLoc, false);

// Column 2: Dates & Duration
$col2X = $col1X + $colW + $gap;
imagefilledrectangle($im, $col2X, $infoCardY, $col2X + $colW, $infoCardY + $infoCardH, $statBg);
imagerectangle($im, $col2X, $infoCardY, $col2X + $colW, $infoCardY + $infoCardH, $statBorder);
imagefilledrectangle($im, $col2X, $infoCardY, $col2X + 4, $infoCardY + $infoCardH, $accentOrange);

$renderText($im, 9, $col2X + 20, $infoCardY + 32, $textLabel, 'DATES & DURATION', true);
$datesDisplay = mb_strlen($dates, 'UTF-8') > 30 ? mb_substr($dates, 0, 28, 'UTF-8') . '...' : $dates;
$renderText($im, 15, $col2X + 20, $infoCardY + 68, $textDark, $datesDisplay, true);
$renderText($im, 10, $col2X + 20, $infoCardY + 98, $textMuted, 'Guaranteed Project Schedule', false);

// Column 3: Daily Remuneration
$col3X = $col2X + $colW + $gap;
imagefilledrectangle($im, $col3X, $infoCardY, $col3X + $colW, $infoCardY + $infoCardH, $statBg);
imagerectangle($im, $col3X, $infoCardY, $col3X + $colW, $infoCardY + $infoCardH, $statBorder);
imagefilledrectangle($im, $col3X, $infoCardY, $col3X + 4, $infoCardY + $infoCardH, $emeraldGreen);

$renderText($im, 9, $col3X + 20, $infoCardY + 32, $textLabel, 'DAILY REMUNERATION', true);
$rateDisplay = mb_strlen($rate, 'UTF-8') > 30 ? mb_substr($rate, 0, 28, 'UTF-8') . '...' : $rate;
$renderText($im, 15, $col3X + 20, $infoCardY + 68, $emeraldGreen, $rateDisplay, true);
$renderText($im, 10, $col3X + 20, $infoCardY + 98, $textMuted, 'Guaranteed Payout Disbursed', false);

// 8. Footer Section: Identity and Canonical Domain
$footerY = 500;
imageline($im, 80, $footerY, 1120, $footerY, $borderCard);

$renderText($im, 11, 80, $footerY + 44, $textMuted, 'New Training Opportunity | Mentry Solutions', false);
$renderText($im, 11, 80, $footerY + 64, $textLabel, 'ID: ' . $jobId . '  •  Verified Institutional Opening', false);

$renderText($im, 13, 850, $footerY + 48, $accentTealDark, 'mentry-solutions.vercel.app', true);
$renderText($im, 10, 850, $footerY + 68, $textMuted, 'Official Application Portal', false);

// 9. Cache to disk and Output PNG
@imagepng($im, $cacheFile, 6);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
header('ETag: "' . md5_file($cacheFile) . '"');
imagepng($im, null, 6);
imagedestroy($im);
exit();
