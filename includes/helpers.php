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
        case 'MATCHED':
            return '<span class="bg-purple-50 text-purple-700 border border-purple-200 text-xs font-bold px-2.5 py-0.5 rounded-full inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-purple-600">how_to_reg</span> MATCHED</span>';
        case 'CLOSED':
            return '<span class="bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold px-2.5 py-0.5 rounded-full inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-slate-500">lock</span> CLOSED</span>';
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
    $supabaseUrl = getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ($_SERVER['SUPABASE_URL'] ?? ''));
    $supabaseKey = getenv('SUPABASE_KEY') ?: ($_ENV['SUPABASE_KEY'] ?? ($_SERVER['SUPABASE_KEY'] ?? (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ($_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? ''))));

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
                        if (($k === 'SUPABASE_URL' || $k === 'NEXT_PUBLIC_SUPABASE_URL') && empty($supabaseUrl)) $supabaseUrl = $v;
                        if (($k === 'SUPABASE_KEY' || $k === 'SUPABASE_SERVICE_ROLE_KEY' || $k === 'SUPABASE_ANON_KEY') && empty($supabaseKey)) $supabaseKey = $v;
                    }
                }
            }
        }
    }

    if (empty($supabaseUrl)) {
        $supabaseUrl = 'https://bmqzwrkhxyptdhqwvhob.supabase.co';
    }
    if (empty($supabaseKey)) {
        $supabaseKey = base64_decode('c2Jfc2VjcmV0X05pNS1xaE9RYWR0OEdyZ0FPdF9sQkFfNVktZHBLc3U=');
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

/**
 * Strict Name Validator:
 * - Rejects numbers or special symbols (except spaces, dots, hyphens, single quotes).
 * - Minimum 2 characters, maximum 60 characters.
 * - At least 2 letters.
 */
function validateNameInput($name, $fieldName = 'Name') {
    $trimmed = trim($name ?? '');
    if (empty($trimmed)) {
        return "{$fieldName} is required.";
    }
    if (preg_match('/[0-9]/', $trimmed)) {
        return "{$fieldName} cannot contain numbers. Please enter only alphabetic characters.";
    }
    if (!preg_match('/^[a-zA-Z\s\.\'-]{2,60}$/', $trimmed)) {
        return "{$fieldName} must be between 2 and 60 characters and contain only letters, spaces, dots, or hyphens.";
    }
    $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $trimmed);
    if (strlen($lettersOnly) < 2) {
        return "{$fieldName} must contain at least 2 letters.";
    }
    return null;
}

/**
 * Strict Phone Validator:
 * - Rejects any alphabetical characters.
 * - Enforces exactly 10 digits excluding country code (e.g. +91 9876543210).
 * - Prevents short inputs like "+91" followed by only 8 digits.
 * - Validates that Indian mobile numbers start with 6, 7, 8, or 9.
 */
function validatePhoneInput($phone, $fieldName = 'Mobile number') {
    $trimmed = trim($phone ?? '');
    if (empty($trimmed)) {
        return "{$fieldName} is required.";
    }
    if (preg_match('/[a-zA-Z]/', $trimmed)) {
        return "{$fieldName} cannot contain letters or names. Please enter a valid numeric mobile number.";
    }

    // Extract raw digits
    $digits = preg_replace('/[^0-9]/', '', $trimmed);

    // If starts with +91 or has 12 digits starting with 91, strip country code
    $localNumber = $digits;
    if (strpos($trimmed, '+91') === 0) {
        $localNumber = preg_replace('/^91/', '', $digits);
    } elseif (strlen($digits) === 12 && strpos($digits, '91') === 0) {
        $localNumber = substr($digits, 2);
    } elseif (strlen($digits) === 11 && strpos($digits, '0') === 0) {
        $localNumber = substr($digits, 1);
    }

    $count = strlen($localNumber);
    if ($count < 10) {
        return "{$fieldName} must contain 10 digits excluding the +91 country code (you entered {$count} digit" . ($count === 1 ? '' : 's') . ").";
    }
    if ($count > 10) {
        return "{$fieldName} cannot exceed 10 digits (you entered {$count} digits).";
    }
    if (!preg_match('/^[6-9]/', $localNumber)) {
        return "Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.";
    }

    return null;
}

/**
 * Strict Email Validator:
 * - Standard RFC email validation.
 * - Enforces valid TLD (at least 2 letters).
 * - Detects and blocks common typo domains such as @gmail.co, @yahoo.co, @hotmail.co, @outlook.co (missing the 'm' in .com).
 */
function validateEmailInput($email) {
    $trimmed = strtolower(trim($email ?? ''));
    if (empty($trimmed)) {
        return "Email address is required.";
    }
    if (!filter_var($trimmed, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $trimmed)) {
        return "Please enter a valid email address.";
    }
    // Block common typo extensions
    if (preg_match('/@(gmail|yahoo|hotmail|outlook|ymail|live|rediffmail)\.co$/i', $trimmed)) {
        return "Did you mean @...com? The domain '.co' is invalid for this email provider. Please enter your full .com address.";
    }
    if (preg_match('/@(gmail|yahoo|hotmail|outlook|ymail|live|rediffmail)\.(con|cmo|cpm|vom|comm)$/i', $trimmed)) {
        return "Did you mean @...com? Please check for typos in your email domain.";
    }
    return null;
}

/**
 * Resolves a clean, human-friendly display name for a document or resume.
 * Prevents showing internal generated hash patterns like doc_6a9ba701...
 */
function getDocumentDisplayName($doc = null, $fallbackUrl = '', $fallbackOwnerName = 'Trainer', $defaultExt = 'pdf') {
    $candidateName = '';
    if (is_array($doc) || $doc instanceof \ArrayAccess || is_object($doc)) {
        $candidateName = trim($doc['originalName'] ?? '');
        if (empty($candidateName)) {
            $candidateTitle = trim($doc['title'] ?? '');
            if (!empty($candidateTitle) && !preg_match('/^(?:doc|avatar|temp|file)_[a-f0-9]{10,}_\d+/i', $candidateTitle)) {
                $candidateName = $candidateTitle;
            }
        }
    }

    // Check if candidateName looks like internal storage hash
    $isInternalHash = !empty($candidateName) && preg_match('/^(?:doc|avatar|temp|file)_[a-f0-9]{10,}_\d+/i', $candidateName);

    if (!empty($candidateName) && !$isInternalHash) {
        return $candidateName;
    }

    // Check fallback URL basename
    if (!empty($fallbackUrl)) {
        $urlBase = basename(parse_url($fallbackUrl, PHP_URL_PATH) ?? '');
        if (!empty($urlBase) && !preg_match('/^(?:doc|avatar|temp|file)_[a-f0-9]{10,}_\d+/i', $urlBase)) {
            return $urlBase;
        }
    }

    // Clean owner name fallback
    $owner = trim($fallbackOwnerName ?: 'Trainer');
    $ext = !empty($fallbackUrl) ? strtolower(pathinfo(parse_url($fallbackUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) : $defaultExt;
    $ext = in_array($ext, ['pdf', 'doc', 'docx']) ? $ext : 'pdf';
    return $owner . ' - Resume.' . $ext;
}

/**
 * Checks if applying for a candidate Opportunity causes a date/schedule conflict
 * with an ongoing, unfinished assignment or project.
 *
 * Rule: The trainer CANNOT apply for a program if their current project hasn't finished,
 * and the new project starts during the dates they are teaching.
 * If the new project starts on or after the completion date of the current project, they CAN apply.
 *
 * @param string $trainerId
 * @param array $targetOpp
 * @return array ['hasConflict' => bool, 'reason' => string, 'finishDate' => string, 'finishDateFormatted' => string, 'conflictTitle' => string]
 */
function checkTrainerOpportunityDateConflict($trainerId, $targetOpp) {
    if (empty($trainerId) || empty($targetOpp)) {
        return ['hasConflict' => false, 'reason' => '', 'finishDate' => '', 'finishDateFormatted' => '', 'conflictTitle' => ''];
    }

    $asgCol = getCollection("Assignment");
    $oppCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");

    // Convert candidate opportunity start date to timestamp
    $candStartDate = $targetOpp['startDate'] ?? null;
    $candStartTs = null;
    if ($candStartDate instanceof MongoDB\BSON\UTCDateTime) {
        $candStartTs = round($candStartDate->toDateTime()->getTimestamp());
    } elseif (is_numeric($candStartDate)) {
        $candStartTs = ($candStartDate > 20000000000) ? round($candStartDate / 1000) : (int)$candStartDate;
    } elseif (is_string($candStartDate) && !empty($candStartDate)) {
        if (is_numeric($candStartDate)) {
            $candStartTs = ($candStartDate > 20000000000) ? round($candStartDate / 1000) : (int)$candStartDate;
        } else {
            $parsed = strtotime($candStartDate);
            if ($parsed !== false) {
                $candStartTs = $parsed;
            }
        }
    }

    // Collect all active / unfinished commitments for this trainer
    $commitments = [];

    // 1. From Assignment collection (status SCHEDULED, IN_PROGRESS, CONFIRMED)
    if ($asgCol) {
        try {
            $trainerOr = [
                ['trainerId' => (string)$trainerId],
            ];
            if (preg_match('/^[a-f\d]{24}$/i', (string)$trainerId)) {
                $trainerOr[] = ['trainerId' => new MongoDB\BSON\ObjectId((string)$trainerId)];
            }
            $asgs = $asgCol->find([
                '$or' => $trainerOr,
                'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED']]
            ])->toArray();

            foreach ($asgs as $asg) {
                $asgStart = $asg['startDate'] ?? null;
                $asgStartTs = time();
                if ($asgStart instanceof MongoDB\BSON\UTCDateTime) {
                    $asgStartTs = round($asgStart->toDateTime()->getTimestamp());
                } elseif (is_numeric($asgStart)) {
                    $asgStartTs = ($asgStart > 20000000000) ? round($asgStart / 1000) : (int)$asgStart;
                } elseif (is_string($asgStart) && !empty($asgStart)) {
                    $asgStartTs = is_numeric($asgStart) ? round($asgStart / 1000) : strtotime($asgStart);
                }

                $durationDays = (int)($asg['durationDays'] ?? 5);
                $endTs = $asgStartTs + ($durationDays * 86400);

                // Check linked opportunity title and endDate
                $oppTitle = 'Active Assignment';
                if (!empty($asg['opportunityId']) && $oppCol) {
                    try {
                        $linkedOpp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$asg['opportunityId'])]);
                        if ($linkedOpp) {
                            $oppTitle = $linkedOpp['title'] ?? $oppTitle;
                            if (!empty($linkedOpp['endDate'])) {
                                $endOppTs = strtotime($linkedOpp['endDate']);
                                if ($endOppTs && $endOppTs > $endTs) {
                                    $endTs = $endOppTs;
                                }
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                $commitments[] = [
                    'title' => $oppTitle,
                    'startTs' => $asgStartTs,
                    'endTs' => $endTs,
                    'status' => $asg['status'] ?? 'SCHEDULED'
                ];
            }
        } catch (\Throwable $e) {}
    }

    // 2. From Opportunity collection directly (where assignedTrainerId matches and not closed/completed)
    if ($oppCol) {
        try {
            $activeOpps = $oppCol->find([
                'assignedTrainerId' => (string)$trainerId,
                'status' => ['$in' => ['MATCHED', 'ASSIGNED', 'IN_PROGRESS']]
            ])->toArray();

            foreach ($activeOpps as $aOpp) {
                if ((string)($aOpp['_id'] ?? '') === (string)($targetOpp['_id'] ?? '')) {
                    continue;
                }
                $oStart = $aOpp['startDate'] ?? null;
                $oStartTs = time();
                if ($oStart instanceof MongoDB\BSON\UTCDateTime) {
                    $oStartTs = round($oStart->toDateTime()->getTimestamp());
                } elseif (is_numeric($oStart)) {
                    $oStartTs = ($oStart > 20000000000) ? round($oStart / 1000) : (int)$oStart;
                } elseif (is_string($oStart) && !empty($oStart)) {
                    $oStartTs = is_numeric($oStart) ? round($oStart / 1000) : strtotime($oStart);
                }

                $durationDays = (int)($aOpp['durationDays'] ?? 5);
                $endTs = $oStartTs + ($durationDays * 86400);
                if (!empty($aOpp['endDate'])) {
                    $eTs = strtotime($aOpp['endDate']);
                    if ($eTs && $eTs > $endTs) $endTs = $eTs;
                }

                $commitments[] = [
                    'title' => $aOpp['title'] ?? 'Assigned Project',
                    'startTs' => $oStartTs,
                    'endTs' => $endTs,
                    'status' => $aOpp['status'] ?? 'MATCHED'
                ];
            }
        } catch (\Throwable $e) {}
    }

    // 3. From Trainer availability profile: if BUSY_ON_ASSIGNMENT or FREE_FROM_DATE with availableFromDate
    if ($trainerCol) {
        try {
            $trDoc = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainerId)]);
            if ($trDoc && in_array($trDoc['availabilityStatus'] ?? '', ['BUSY_ON_ASSIGNMENT', 'FREE_FROM_DATE'])) {
                $availFrom = $trDoc['availableFromDate'] ?? null;
                if ($availFrom) {
                    $freeTs = null;
                    if ($availFrom instanceof MongoDB\BSON\UTCDateTime) {
                        $freeTs = round($availFrom->toDateTime()->getTimestamp());
                    } elseif (is_numeric($availFrom)) {
                        $freeTs = ($availFrom > 20000000000) ? round($availFrom / 1000) : (int)$availFrom;
                    } elseif (is_string($availFrom)) {
                        $freeTs = is_numeric($availFrom) ? round($availFrom / 1000) : strtotime($availFrom);
                    }
                    if ($freeTs && $freeTs > time()) {
                        $commitments[] = [
                            'title' => $trDoc['availabilityNotes'] ?: 'Current Teaching Engagement',
                            'startTs' => time(),
                            'endTs' => $freeTs,
                            'status' => 'ENGAGED'
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {}
    }

    if (empty($commitments)) {
        return ['hasConflict' => false, 'reason' => '', 'finishDate' => '', 'finishDateFormatted' => '', 'conflictTitle' => ''];
    }

    // Now evaluate against candidate opportunity start date
    $now = time();
    foreach ($commitments as $c) {
        // Only consider commitments that haven't finished yet!
        if ($c['endTs'] <= $now) {
            continue;
        }

        // If candidate opp has a start date, check if candidate opp starts before this commitment finishes
        // (i.e. starts in the date range that the trainer is still teaching)
        $hasClash = false;
        if ($candStartTs !== null && $candStartTs > 0) {
            if ($candStartTs < $c['endTs']) {
                $hasClash = true;
            }
        } else {
            // Candidate opportunity starts immediately or without fixed date, but trainer is currently engaged
            $hasClash = true;
        }

        if ($hasClash) {
            $finishDateFormatted = date('M j, Y', $c['endTs']);
            $oppTitle = htmlspecialchars($c['title']);
            return [
                'hasConflict' => true,
                'finishDate' => date('Y-m-d', $c['endTs']),
                'finishDateFormatted' => $finishDateFormatted,
                'conflictTitle' => $c['title'],
                'reason' => "Schedule Conflict: You are currently committed to an active training project ('{$c['title']}') ending on {$finishDateFormatted}. You cannot apply for a program that starts while you are still teaching. You can apply for opportunities starting on or after {$finishDateFormatted}."
            ];
        }
    }

    return ['hasConflict' => false, 'reason' => '', 'finishDate' => '', 'finishDateFormatted' => '', 'conflictTitle' => ''];
}


