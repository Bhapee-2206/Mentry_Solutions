<?php
// includes/helpers.php

if (file_exists(__DIR__ . '/mongo_polyfill.php')) {
    require_once __DIR__ . '/mongo_polyfill.php';
}

function formatINR($amount) {
    if ($amount === null || $amount === '') return '₹0';
    $amount = (float)$amount;
    return '₹' . number_format($amount, 0, '.', ',');
}

function formatDate($date) {
    if (!$date) return 'N/A';
    if (is_array($date)) {
        if (isset($date['$date'])) {
            $date = $date['$date'];
            if (is_array($date) && isset($date['$numberLong'])) {
                $date = $date['$numberLong'];
            }
        }
    }
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        $datetime = $date->toDateTime();
    } elseif (is_numeric($date)) {
        $ts = (float)$date;
        if ($ts > 20000000000) {
            $ts = round($ts / 1000);
        }
        $datetime = new DateTime("@" . (int)$ts);
    } elseif (is_string($date)) {
        if (is_numeric($date)) {
            $ts = (float)$date;
            if ($ts > 20000000000) {
                $ts = round($ts / 1000);
            }
            $datetime = new DateTime("@" . (int)$ts);
        } else {
            try {
                $datetime = new DateTime($date);
            } catch (Exception $e) {
                return $date;
            }
        }
    } else {
        return 'N/A';
    }
    return $datetime->format('M j, Y');
}

function formatRelativeTime($date) {
    if (!$date) return 'recently';
    if (is_array($date)) {
        if (isset($date['$date'])) {
            $date = $date['$date'];
            if (is_array($date) && isset($date['$numberLong'])) {
                $date = $date['$numberLong'];
            }
        }
    }
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        $timestamp = $date->toDateTime()->getTimestamp();
    } elseif (is_numeric($date)) {
        $timestamp = (float)$date;
        if ($timestamp > 20000000000) {
            $timestamp = (int)round($timestamp / 1000);
        } else {
            $timestamp = (int)$timestamp;
        }
    } elseif (is_string($date)) {
        if (is_numeric($date)) {
            $timestamp = (float)$date;
            if ($timestamp > 20000000000) {
                $timestamp = (int)round($timestamp / 1000);
            } else {
                $timestamp = (int)$timestamp;
            }
        } else {
            $timestamp = strtotime($date);
        }
    } else {
        return 'recently';
    }

    if (!$timestamp) return 'recently';
    $diff = time() - $timestamp;
    if ($diff < 0) {
        return date('M j, Y', $timestamp);
    }
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $timestamp);
}

function getStatusBadge($status) {
    $status = strtoupper($status ?? 'PENDING');
    switch ($status) {
        case 'APPROVED':
        case 'SELECTED':
        case 'PUBLISHED':
        case 'ACTIVE':
        case 'COMPLETED':
            return '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        case 'PENDING_APPROVAL':
        case 'PENDING':
        case 'SUBMITTED':
            return '<span class="bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        case 'SHORTLISTED':
            return '<span class="bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        case 'REJECTED':
        case 'SUSPENDED':
        case 'CANCELLED':
            return '<span class="bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        default:
            return '<span class="bg-slate-100 text-slate-700 text-xs font-semibold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
    }
}

function getAvailabilityBadge($availabilityStatus, $availableFromDate = null) {
    $status = strtoupper($availabilityStatus ?? 'AVAILABLE_NOW');
    switch ($status) {
        case 'AVAILABLE_NOW':
            return '<span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Available Now</span>';
        case 'FREE_FROM_DATE':
            $dateText = $availableFromDate ? formatDate($availableFromDate) : 'Upcoming Date';
            return '<span class="inline-flex items-center gap-1 bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="material-symbols-outlined text-[13px] text-amber-600">event</span> Free from ' . htmlspecialchars($dateText) . '</span>';
        case 'BUSY_ON_ASSIGNMENT':
            $busyUntilText = $availableFromDate ? ' (until ' . formatDate($availableFromDate) . ')' : '';
            return '<span class="inline-flex items-center gap-1 bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="material-symbols-outlined text-[13px] text-blue-600">school</span> Delivering Workshop' . htmlspecialchars($busyUntilText) . '</span>';
        case 'UNAVAILABLE':
            return '<span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600 border border-slate-200 text-[11px] font-medium px-2.5 py-0.5 rounded-full"><span class="material-symbols-outlined text-[13px] text-slate-400">block</span> Unavailable</span>';
        default:
            return '<span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Available</span>';
    }
}

/**
 * Generates an ordered sequential Mentry ID (e.g. MEN-TRN-1001, MEN-OPP-1001)
 * Guaranteed to increment strictly in order (+1 each time), never random.
 */
function getNextSequentialMentryId($type) {
    $type = strtoupper(trim($type));
    $prefixMap = [
        'TRAINER' => 'MEN-TRN-',
        'TRN' => 'MEN-TRN-',
        'OPPORTUNITY' => 'MEN-OPP-',
        'OPP' => 'MEN-OPP-',
        'VENDOR' => 'MEN-VND-',
        'VND' => 'MEN-VND-',
        'COLLEGE' => 'MEN-CLG-',
        'CLG' => 'MEN-CLG-',
        'REQUIREMENT' => 'MEN-REQ-',
        'REQ' => 'MEN-REQ-'
    ];
    $counterKeyMap = [
        'TRAINER' => 'TRAINER',
        'TRN' => 'TRAINER',
        'OPPORTUNITY' => 'OPPORTUNITY',
        'OPP' => 'OPPORTUNITY',
        'VENDOR' => 'VENDOR',
        'VND' => 'VENDOR',
        'COLLEGE' => 'COLLEGE',
        'CLG' => 'COLLEGE',
        'REQUIREMENT' => 'REQUIREMENT',
        'REQ' => 'REQUIREMENT'
    ];

    $prefix = $prefixMap[$type] ?? ('MEN-' . $type . '-');
    $counterKey = $counterKeyMap[$type] ?? $type;

    $counterCol = getCollection("Counters");
    $current = $counterCol ? $counterCol->findOne(['_id' => $counterKey]) : null;

    if (!$current || !isset($current['seq'])) {
        // Initialize sequence from highest existing numerical ID in the database
        $maxSeq = 1000;
        if ($counterKey === 'TRAINER') {
            $trainerCol = getCollection("Trainer");
            if ($trainerCol) {
                $trainers = $trainerCol->find()->toArray();
                foreach ($trainers as $t) {
                    $c = $t['trainerCode'] ?? ($t['mentryId'] ?? '');
                    if (preg_match('/MEN-TRN-(\d+)/i', $c, $m)) {
                        $maxSeq = max($maxSeq, (int)$m[1]);
                    }
                }
            }
        } elseif ($counterKey === 'OPPORTUNITY') {
            $oppCol = getCollection("Opportunity");
            if ($oppCol) {
                $opps = $oppCol->find()->toArray();
                foreach ($opps as $o) {
                    $c = $o['jobId'] ?? ($o['mentryId'] ?? '');
                    if (preg_match('/MEN-(?:OPP|[A-Z]{3})-(\d+)/i', $c, $m)) {
                        $maxSeq = max($maxSeq, (int)$m[1]);
                    }
                }
            }
        } elseif ($counterKey === 'REQUIREMENT') {
            $reqCol = getCollection("CollegeRequirement");
            if ($reqCol) {
                $reqs = $reqCol->find()->toArray();
                foreach ($reqs as $r) {
                    $c = $r['requestCode'] ?? ($r['mentryId'] ?? '');
                    if (preg_match('/MEN-REQ-(\d+)/i', $c, $m)) {
                        $maxSeq = max($maxSeq, (int)$m[1]);
                    }
                }
            }
        } elseif ($counterKey === 'VENDOR' || $counterKey === 'COLLEGE') {
            $userCol = getCollection("User");
            if ($userCol) {
                $users = $userCol->find()->toArray();
                foreach ($users as $u) {
                    $c = $u['vendorCode'] ?? ($u['mentryId'] ?? '');
                    if (preg_match('/MEN-(?:VND|CLG)-(\d+)/i', $c, $m)) {
                        $maxSeq = max($maxSeq, (int)$m[1]);
                    }
                }
            }
        }

        $nextSeq = $maxSeq + 1;
        if ($counterCol) {
            $counterCol->updateOne(
                ['_id' => $counterKey],
                ['$set' => ['seq' => $nextSeq]],
                ['upsert' => true]
            );
        }
        return $prefix . $nextSeq;
    }

    $nextSeq = (int)$current['seq'] + 1;
    if ($counterCol) {
        $counterCol->updateOne(
            ['_id' => $counterKey],
            ['$set' => ['seq' => $nextSeq]],
            ['upsert' => true]
        );
    }
    return $prefix . $nextSeq;
}

/**
 * Standardized Mentry Unique ID Formatter
 * Formats official Mentry numbers in order:
 * - Trainers: MEN-TRN-1001, MEN-TRN-1002, ...
 * - Opportunities: MEN-OPP-1001, MEN-OPP-1002, ...
 * - Vendors: MEN-VND-1001, MEN-VND-1002, ...
 * - Requirements: MEN-REQ-1001, MEN-REQ-1002, ...
 */
function getMentryCode($type, $docOrId) {
    if (is_array($docOrId)) {
        if (!empty($docOrId['mentryId'])) return (string)$docOrId['mentryId'];
        if (!empty($docOrId['jobId'])) return (string)$docOrId['jobId'];
        if (!empty($docOrId['trainerCode'])) return (string)$docOrId['trainerCode'];
        if (!empty($docOrId['vendorCode'])) return (string)$docOrId['vendorCode'];
        if (!empty($docOrId['requestCode'])) return (string)$docOrId['requestCode'];
        $idStr = (string)($docOrId['_id'] ?? ($docOrId['id'] ?? ''));
    } else {
        $idStr = (string)$docOrId;
    }

    if (strpos($idStr, 'MEN-') === 0) {
        return $idStr;
    }

    $num = preg_replace('/[^0-9]/', '', substr($idStr, -8));
    if (empty($num)) {
        $num = (abs(crc32($idStr)) % 8999) + 1001;
    } else {
        $num = (int)substr($num, -4);
    }
    if ($num < 1000) $num += 1000;

    switch (strtoupper($type)) {
        case 'TRAINER':
        case 'TRN':
            return 'MEN-TRN-' . $num;
        case 'OPPORTUNITY':
        case 'OPP':
            return 'MEN-OPP-' . $num;
        case 'VENDOR':
        case 'VND':
            return 'MEN-VND-' . $num;
        case 'COLLEGE':
        case 'CLG':
            return 'MEN-CLG-' . $num;
        case 'REQUIREMENT':
        case 'REQ':
            return 'MEN-REQ-' . $num;
        default:
            return 'MEN-' . strtoupper($type) . '-' . $num;
    }
}

/**
 * Resolves a clean, reliable profile photo or high-contrast initials avatar
 */
function getUserAvatar($userOrName, $size = 128) {
    $avatar = null;
    $name = 'Trainer';

    if (is_array($userOrName) || is_object($userOrName)) {
        $arr = (array)$userOrName;
        $avatar = $arr['avatar'] ?? ($arr['logo'] ?? null);
        $name = $arr['name'] ?? ($arr['organizationName'] ?? 'Trainer');
    } elseif (is_string($userOrName)) {
        if (strpos($userOrName, 'http') === 0 || strpos($userOrName, '/public/') === 0 || strpos($userOrName, 'data:') === 0) {
            return $userOrName;
        }
        $name = $userOrName;
    }

    // If a valid avatar URL is present and not an old abstract vercel blob
    if (!empty($avatar) && is_string($avatar) && strpos($avatar, 'avatar.vercel.sh') === false) {
        return $avatar;
    }

    $cleanName = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
    if (empty($cleanName)) $cleanName = 'Trainer';

    // Generates a crisp, elegant initials avatar with rich brand colors
    return "https://ui-avatars.com/api/?name=" . urlencode($cleanName) . "&background=2563EB&color=ffffff&bold=true&size=" . (int)$size . "&font-size=0.42";
}

/**
 * Universal Storage Handler: Supports Supabase Cloud Storage (primary for Serverless/Vercel/Read-Only systems)
 * with graceful fallback to local filesystem (for local XAMPP/Apache).
 */
function uploadFileToCloudOrLocal($tmpFilePath, $desiredFilename, $folder = 'documents', $mimeType = '') {
    if (empty($tmpFilePath) || !file_exists($tmpFilePath) || !is_readable($tmpFilePath)) {
        return ['success' => false, 'error' => 'Temporary uploaded file not found or is unreadable.'];
    }

    $fileContent = @file_get_contents($tmpFilePath);
    if ($fileContent === false) {
        return ['success' => false, 'error' => 'Failed to read uploaded file contents.'];
    }

    // 1. Try Supabase Cloud Storage (Ideal for Vercel/Serverless & persistent storage)
    $supabaseUrl = getenv('SUPABASE_URL') ?: '';
    $supabaseKey = getenv('SUPABASE_KEY') ?: '';

    if (empty($supabaseUrl) || empty($supabaseKey)) {
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $l) {
                    $l = trim($l);
                    if (strpos($l, '=') !== false && !empty($l) && $l[0] !== '#') {
                        list($k, $v) = explode('=', $l, 2);
                        $k = trim($k);
                        $v = trim(trim($v), '"\'');
                        if ($k === 'SUPABASE_URL') $supabaseUrl = $v;
                        if ($k === 'SUPABASE_KEY') $supabaseKey = $v;
                    }
                }
            }
        }
    }

    if (!empty($supabaseUrl) && !empty($supabaseKey)) {
        $bucket = 'documents';
        $objectPath = trim($folder, '/') . '/' . $desiredFilename;
        $uploadEndpoint = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . $objectPath;

        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'x-upsert: true'
        ];
        if (!empty($mimeType)) {
            $headers[] = 'Content-Type: ' . $mimeType;
        }

        $ch = curl_init($uploadEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $publicUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . $bucket . '/' . $objectPath;
            return [
                'success' => true,
                'url' => $publicUrl,
                'storage' => 'supabase',
                'filename' => $desiredFilename
            ];
        }
    }

    // 2. Local Filesystem Fallback (if local directory is writable, e.g. on XAMPP)
    $uploadDir = __DIR__ . '/../public/uploads/' . trim($folder, '/') . '/';
    $isWritable = false;
    if (is_dir($uploadDir)) {
        $isWritable = is_writable($uploadDir);
    } else {
        $isWritable = @mkdir($uploadDir, 0777, true);
    }

    if ($isWritable) {
        $targetPath = $uploadDir . $desiredFilename;
        $moved = @move_uploaded_file($tmpFilePath, $targetPath) || @copy($tmpFilePath, $targetPath);
        if ($moved) {
            return [
                'success' => true,
                'url' => '/public/uploads/' . trim($folder, '/') . '/' . $desiredFilename,
                'storage' => 'local',
                'filename' => $desiredFilename
            ];
        }
    }

    // 3. Serverless temp fallback (when local filesystem is strictly read-only like Vercel)
    $tmpDir = rtrim(sys_get_temp_dir(), '/\\') . '/mentry_uploads/' . trim($folder, '/') . '/';
    @mkdir($tmpDir, 0777, true);
    $tmpTargetPath = $tmpDir . $desiredFilename;
    @copy($tmpFilePath, $tmpTargetPath);

    return [
        'success' => true,
        'url' => '/actions/preview-doc.php?tmp_file=' . urlencode(trim($folder, '/') . '/' . $desiredFilename) . '&ext=' . urlencode(pathinfo($desiredFilename, PATHINFO_EXTENSION)),
        'storage' => 'temp',
        'filename' => $desiredFilename
    ];
}

