<?php
// actions/preview-doc.php - In-Browser Document Preview Handler (PDF, DOCX, Images, Text)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    header("Location: /login.php");
    exit();
}

$url = $_GET['url'] ?? '';
$title = trim($_GET['title'] ?? 'Document Preview');

if (empty($url)) {
    http_response_code(400);
    die("Document URL is required.");
}

$isRemote = preg_match('/^https?:\/\//i', $url);
$fullPath = null;
$ext = '';

if ($isRemote) {
    $urlPath = parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
    
    // For images & PDFs, we can download to temp or redirect
    $tempFile = tempnam(sys_get_temp_dir(), 'prev_');
    $fetched = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300 && $data !== false) {
            file_put_contents($tempFile, $data);
            $fetched = true;
        }
    }

    if (!$fetched) {
        $data = @file_get_contents($url);
        if ($data !== false) {
            file_put_contents($tempFile, $data);
            $fetched = true;
        }
    }

    if ($fetched) {
        $fullPath = $tempFile;
    } else {
        // Fallback: direct redirect to cloud URL
        header("Location: " . $url);
        exit();
    }
} else {
    // Clean relative URL and prevent path traversal
    $urlPath = parse_url($url, PHP_URL_PATH);
    $urlPath = ltrim($urlPath, '/\\');

    $baseDir = realpath(__DIR__ . '/../');
    $fullPath = realpath($baseDir . '/' . $urlPath);

    if (!$fullPath || !file_exists($fullPath) || strpos($fullPath, $baseDir) !== 0) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-50 flex items-center justify-center min-h-screen p-6 text-center">
            <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm max-w-md">
                <h3 class="font-bold text-slate-800 text-base">Document File Not Found</h3>
                <p class="text-xs text-slate-500 mt-2">The requested document file could not be located on the server.</p>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
}

// 1. PDF Documents: Stream directly with inline disposition so browser PDF viewer handles it
$downloadName = !empty($urlPath) ? basename($urlPath) : basename($fullPath);

if ($ext === 'pdf') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($fullPath);
    if ($isRemote && file_exists($fullPath)) {
        @unlink($fullPath);
    }
    exit();
}

// 2. Images: Stream inline
if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
    while (ob_get_level()) { ob_end_clean(); }
    $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/' . $ext;
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    if ($isRemote && file_exists($fullPath)) {
        @unlink($fullPath);
    }
    exit();
}

// 3. Plain text
if ($ext === 'txt' || $ext === 'log') {
    $content = @file_get_contents($fullPath);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-100 p-6 min-h-screen">
        <div class="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-sm border border-slate-200 font-mono text-xs whitespace-pre-wrap text-slate-800 leading-relaxed">
            <?= htmlspecialchars($content) ?>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 4. DOCX Documents: Pure PHP Zip Extractor + Mammoth.js Hybrid In-Browser Renderer
$extractedHtml = '';

// Backend DOCX XML parse as instant fallback
if ($ext === 'docx') {
    try {
        $xmlContent = null;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($fullPath) === true) {
                $xmlContent = $zip->getFromName('word/document.xml');
                $zip->close();
            }
        }
        
        if (!$xmlContent) {
            // Fallback raw zip stream parser if ZipArchive not enabled
            $rawZip = @file_get_contents($fullPath);
            if ($rawZip) {
                $pos = 0;
                while (($sigPos = strpos($rawZip, "PK\x03\x04", $pos)) !== false) {
                    $header = substr($rawZip, $sigPos, 30);
                    if (strlen($header) < 30) break;
                    $fnLen = unpack('v', substr($header, 26, 2))[1];
                    $extraLen = unpack('v', substr($header, 28, 2))[1];
                    $fn = substr($rawZip, $sigPos + 30, $fnLen);
                    $dataStart = $sigPos + 30 + $fnLen + $extraLen;
                    if ($fn === 'word/document.xml') {
                        $compSize = unpack('V', substr($header, 18, 4))[1];
                        $method = unpack('v', substr($header, 8, 2))[1];
                        if ($compSize > 0) {
                            $compData = substr($rawZip, $dataStart, $compSize);
                            $xmlContent = ($method === 8) ? @gzinflate($compData) : $compData;
                            if ($xmlContent === false) $xmlContent = @gzuncompress($compData);
                        }
                        break;
                    }
                    $pos = $sigPos + 4;
                }
            }
        }

        if ($xmlContent) {
            // Convert paragraphs and headings to styled HTML
            $paragraphs = [];
            if (preg_match_all('/<w:p(?:\s+[^>]*)?>(.*?)<\/w:p>/is', $xmlContent, $pMatches)) {
                foreach ($pMatches[1] as $pXml) {
                    $isHeading = (bool)preg_match('/<w:pStyle\s+[^>]*w:val="Heading(\d)"/i', $pXml, $hMatch);
                    $hLevel = $isHeading ? (int)$hMatch[1] : 0;
                    
                    // Extract text runs with bold/italic
                    $runsText = '';
                    if (preg_match_all('/<w:r(?:\s+[^>]*)?>(.*?)<\/w:r>/is', $pXml, $rMatches)) {
                        foreach ($rMatches[1] as $rXml) {
                            $isBold = (bool)preg_match('/<w:b(?:\s|\/|>)/i', $rXml);
                            $isItalic = (bool)preg_match('/<w:i(?:\s|\/|>)/i', $rXml);
                            if (preg_match_all('/<w:t(?:\s+[^>]*)?>(.*?)<\/w:t>/is', $rXml, $tMatches)) {
                                $t = implode('', $tMatches[1]);
                                $t = html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
                                $t = htmlspecialchars($t);
                                if ($isBold) $t = '<strong>' . $t . '</strong>';
                                if ($isItalic) $t = '<em>' . $t . '</em>';
                                $runsText .= $t;
                            }
                        }
                    } else {
                        $runsText = htmlspecialchars(strip_tags($pXml));
                    }
                    
                    $runsText = trim($runsText);
                    if (!empty($runsText)) {
                        if ($hLevel === 1) {
                            $paragraphs[] = '<h1 class="text-xl font-black text-slate-900 mt-4 mb-2 pb-1 border-b border-slate-200">' . $runsText . '</h1>';
                        } elseif ($hLevel === 2) {
                            $paragraphs[] = '<h2 class="text-base font-bold text-blue-800 mt-3 mb-1">' . $runsText . '</h2>';
                        } elseif ($hLevel >= 3) {
                            $paragraphs[] = '<h3 class="text-sm font-bold text-slate-800 mt-2 mb-1">' . $runsText . '</h3>';
                        } else {
                            $paragraphs[] = '<p class="text-xs text-slate-700 leading-relaxed my-1.5">' . $runsText . '</p>';
                        }
                    }
                }
            }
            $extractedHtml = implode("\n", $paragraphs);
        }
    } catch (\Throwable $e) {
        $extractedHtml = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .doc-page {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04);
            min-height: 100vh;
        }
        /* Style Mammoth-generated HTML */
        #docxRenderContainer h1 { font-size: 1.5rem; font-weight: 900; color: #0f172a; margin-top: 1.5rem; margin-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.25rem; }
        #docxRenderContainer h2 { font-size: 1.25rem; font-weight: 800; color: #1e3a8a; margin-top: 1.25rem; margin-bottom: 0.5rem; }
        #docxRenderContainer h3 { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-top: 1rem; margin-bottom: 0.25rem; }
        #docxRenderContainer p { font-size: 0.85rem; line-height: 1.6; color: #334155; margin-top: 0.4rem; margin-bottom: 0.4rem; }
        #docxRenderContainer ul { list-style-type: disc; margin-left: 1.5rem; margin-top: 0.4rem; margin-bottom: 0.4rem; font-size: 0.85rem; color: #334155; }
        #docxRenderContainer ol { list-style-type: decimal; margin-left: 1.5rem; margin-top: 0.4rem; margin-bottom: 0.4rem; font-size: 0.85rem; color: #334155; }
        #docxRenderContainer li { margin-bottom: 0.25rem; line-height: 1.5; }
        #docxRenderContainer table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; margin-bottom: 0.75rem; font-size: 0.8rem; }
        #docxRenderContainer th, #docxRenderContainer td { border: 1px solid #cbd5e1; padding: 0.5rem; text-align: left; }
        #docxRenderContainer th { background-color: #f8fafc; font-weight: 700; }
        #docxRenderContainer strong { font-weight: 700; color: #0f172a; }
    </style>
</head>
<body class="bg-slate-200/80 p-4 sm:p-8 min-h-screen">

    <!-- Document Wrapper (Paper Layout) -->
    <div class="doc-page rounded-2xl border border-slate-300/80 p-8 sm:p-14 transition-all">
        <!-- Document Top Bar inside page -->
        <div class="border-b border-slate-100 pb-4 mb-6 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[#FE5E04] text-2xl">description</span>
                <div>
                    <h1 class="text-sm font-black text-slate-900 leading-tight"><?= htmlspecialchars($title) ?></h1>
                    <p class="text-[11px] text-slate-400">Microsoft Word Document (.docx) &bull; Verified in-browser rendering</p>
                </div>
            </div>
            <span class="bg-blue-50 text-blue-700 text-[10px] font-extrabold px-2.5 py-1 rounded-full border border-blue-200 uppercase tracking-wider">
                Word Document View
            </span>
        </div>

        <!-- Render Target for Mammoth.js -->
        <div id="docxRenderContainer">
            <?php if (!empty($extractedHtml)): ?>
                <?= $extractedHtml ?>
            <?php else: ?>
                <div id="loadingPlaceholder" class="flex flex-col items-center justify-center py-16 space-y-3 text-slate-400">
                    <span class="material-symbols-outlined text-4xl animate-spin text-[#FE5E04]">progress_activity</span>
                    <p class="text-xs font-semibold">Parsing Word Document Structure...</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Client-side Mammoth.js Enhancement -->
    <script>
    (function() {
        const docUrl = '<?= addslashes($url) ?>';
        const container = document.getElementById('docxRenderContainer');

        if (window.mammoth && docUrl) {
            fetch(docUrl)
                .then(response => {
                    if (!response.ok) throw new Error("HTTP error " + response.status);
                    return response.arrayBuffer();
                })
                .then(arrayBuffer => {
                    return mammoth.convertToHtml({ arrayBuffer: arrayBuffer });
                })
                .then(result => {
                    if (result && result.value && result.value.trim().length > 0) {
                        container.innerHTML = result.value;
                    }
                })
                .catch(err => {
                    console.log("Mammoth client-side render fallback:", err);
                    // If backend extracted HTML is already present, leave it as fallback
                });
        }
    })();
    </script>
</body>
</html>
