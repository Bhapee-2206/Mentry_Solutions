<?php
// includes/resume_parser.php - High-Accuracy Pure PHP Resume Parser & Skill Extraction Engine
require_once __DIR__ . '/db.php';

class ResumeSkillParser {

    /**
     * Precision-engineered skill dictionary with strict contextual regex patterns.
     * Standalone single letters and common English words (c, r, go, ai) are explicitly prohibited
     * to eliminate false positive detections.
     */
    private static $skillDictionary = [
        'Programming' => [
            'Python' => ['\bpython\d*(?:\.\d+)?\b', '\bcore\s+python\b', '\bpythonic\b'],
            'Java' => ['\bjava\b(?!\s*script)', '\bj2ee\b', '\bjdk\b', '\bcore\s+java\b', '\badvanced\s+java\b'],
            'C++' => ['\bc\+\+\b', '\bcpp\b', '\bgcc\b'],
            'C Programming' => [
                '\b(?:c\s*programming|c\s*language|ansi\s*c|embedded\s*c|c\s*\/\s*c\+\+|turbo\s*c)\b',
                '\bprogramming\s+in\s+c\b'
            ],
            'C#' => ['\bc#\b', '\bcsharp\b', '\bc#\s*\.net\b'],
            'JavaScript' => ['\bjavascript\b', '\bes6\b', '\becmascript\b', '\bmodern\s*javascript\b'],
            'TypeScript' => ['\btypescript\b'],
            'PHP' => ['\bphp\d*\b', '\blamp\s*stack\b'],
            'Go / Golang' => [
                '\bgolang\b',
                '\bgo\s*(?:language|lang|programming|developer|backend)\b'
            ],
            'Rust' => ['\brust\s*(?:language|lang|programming)?\b'],
            'Ruby' => ['\bruby(?:\s+on\s+rails)?\b', '\brails\b'],
            'Kotlin' => ['\bkotlin\b'],
            'Swift' => ['\bswift\s*(?:language|programming|ios)?\b'],
            'R Programming' => [
                '\b(?:r\s*programming|r\s*language|r\s*studio|r-project|cran)\b'
            ],
            'SQL' => ['\bsql\b', '\bt-sql\b', '\bpl\/sql\b', '\bstructured\s+query\s+language\b'],
            'HTML & CSS' => ['\bhtml5?\b', '\bcss3?\b'],
            'Data Structures & Algorithms' => [
                '\bdata\s+structures\b', '\balgorithms\b', '\bdsa\b', '\bleetcode\b',
                '\bcompetitive\s+programming\b'
            ]
        ],
        'Frameworks' => [
            'React.js' => ['\breact(?:\.js|js)?\b(?!\s*native)', '\breact\s+developer\b'],
            'Angular' => ['\bangular(?:\.js|js|2\+)?\b'],
            'Vue.js' => ['\bvue(?:\.js|js)?\b'],
            'Next.js' => ['\bnext(?:\.js|js)\b'],
            'Node.js' => ['\bnode(?:\.js|js)\b', '\bnodejs\b'],
            'Express.js' => ['\bexpress(?:\.js|js)\b'],
            'Django' => ['\bdjango\b', '\bdjango\s+rest\s+framework\b', '\bdrf\b'],
            'Flask' => ['\bflask\b'],
            'FastAPI' => ['\bfastapi\b'],
            'Spring Boot' => ['\bspring\s*boot\b', '\bspring\s+framework\b', '\bspring\s+mvc\b'],
            'Laravel' => ['\blaravel\b'],
            'ASP.NET' => ['\basp\.net\b', '\b\.net\s+core\b'],
            'Flutter' => ['\bflutter\b', '\bdart\s*(?:language|programming|sdk)?\b'],
            'React Native' => ['\breact\s+native\b'],
            'Tailwind CSS' => ['\btailwind(?:\s*css)?\b'],
            'Bootstrap' => ['\bbootstrap\d*\b']
        ],
        'Databases' => [
            'MySQL' => ['\bmysql\b'],
            'PostgreSQL' => ['\bpostgres(?:ql)?\b'],
            'MongoDB' => ['\bmongodb\b', '\bno[- ]?sql\b', '\bmongoose\b'],
            'Redis' => ['\bredis\b'],
            'Oracle DB' => ['\boracle(?:\s+database|\s+db|\s+11g|\s+12c|\s+19c|\s+pl\/sql)?\b'],
            'Microsoft SQL Server' => ['\bms\s*sql\b', '\bmssql\b', '\bsql\s+server\b'],
            'Firebase' => ['\bfirebase\b', '\bfirestore\b'],
            'Big Data & Spark' => ['\bapache\s+spark\b', '\bpyspark\b', '\bhadoop\b', '\bbig\s*data\b']
        ],
        'Cloud & DevOps' => [
            'AWS' => ['\baws\b', '\bamazon\s+web\s+services\b', '\bec2\b', '\bs3\b', '\baws\s+lambda\b', '\bcloudformation\b'],
            'Microsoft Azure' => ['\bazure\b', '\bmicrosoft\s+azure\b', '\bazure\s+devops\b'],
            'Google Cloud (GCP)' => ['\bgcp\b', '\bgoogle\s+cloud(?:\s+platform)?\b'],
            'Docker' => ['\bdocker\b', '\bcontainer(?:s|ization)?\b'],
            'Kubernetes' => ['\bkubernetes\b', '\bk8s\b'],
            'Terraform' => ['\bterraform\b', '\biac\b'],
            'Ansible' => ['\bansible\b'],
            'CI/CD Pipelines' => ['\bci\/cd\b', '\bcicd\b', '\bjenkins\b', '\bgithub\s+actions\b', '\bgitlab\s+ci\b'],
            'Linux / Shell' => ['\blinux\b', '\bubuntu\b', '\bcentos\b', '\bredhat\b', '\bbash\b', '\bshell\s+scripting\b'],
            'Git & GitHub' => ['\bgit\b(?!\s*hub|\s*lab)', '\bgithub\b', '\bgitlab\b', '\bversion\s+control\b']
        ],
        'Data Science & AI' => [
            'Machine Learning' => ['\bmachine\s+learning\b', '\bsupervised\s+learning\b', '\bunsupervised\s+learning\b'],
            'Deep Learning' => ['\bdeep\s+learning\b', '\bneural\s+networks?\b', '\bcnn\b', '\brnn\b', '\blstm\b'],
            'Artificial Intelligence' => [
                '\bartificial\s+intelligence\b',
                '\bai\s*(?:\/|\&)\s*ml\b',
                '\bgenai\b',
                '\bgenerative\s+ai\b'
            ],
            'Natural Language Processing (NLP)' => [
                '\bnatural\s+language\s+processing\b', '\bnlp\b', '\btransformers\b',
                '\bbert\b', '\bllms?\b', '\blarge\s+language\s+models?\b', '\blangchain\b'
            ],
            'Computer Vision' => ['\bcomputer\s+vision\b', '\bopencv\b', '\byolo\b', '\bimage\s+processing\b'],
            'Pandas & NumPy' => ['\bpandas\b', '\bnumpy\b', '\bscipy\b'],
            'PyTorch & TensorFlow' => ['\bpytorch\b', '\btensorflow\b', '\bkeras\b'],
            'Scikit-learn' => ['\bscikit[- ]?learn\b', '\bsklearn\b'],
            'Power BI' => ['\bpower\s*bi\b', '\bpowerbi\b'],
            'Tableau' => ['\btableau\b']
        ],
        'Testing & QA' => [
            'Manual Testing' => [
                '\bmanual\s+testing\b', '\btest\s+cases?\b', '\bsdlc\b', '\bstlc\b',
                '\bqa\s+testing\b', '\bbug\s+tracking\b', '\bblack\s*box\s*testing\b'
            ],
            'Automation Testing' => [
                '\bautomation\s+testing\b', '\bselenium\b', '\bcypress\b', '\bplaywright\b',
                '\btestng\b', '\bjunit\b', '\bcucumber\b'
            ]
        ],
        'VLSI & Embedded' => [
            'VLSI & Verilog' => ['\bvlsi\b', '\basic\b', '\bfpga\b', '\brtl\s*design\b', '\bverilog\b', '\bvhdl\b', '\bsystemverilog\b'],
            'Embedded Systems' => ['\bembedded\s+systems?\b', '\bembedded\s+c\b', '\bfirmware\b'],
            'Microcontrollers & IoT' => [
                '\bmicrocontrollers?\b', '\barm\s+cortex\b', '\b8051\b', '\bpic\b', '\bstm32\b',
                '\barduino\b', '\braspberry\s+pi\b', '\biot\b', '\binternet\s+of\s+things\b',
                '\besp32\b', '\besp8266\b'
            ],
            'Robotics' => ['\brobotics\b', '\bros\b', '\brobot\s+operating\s+system\b']
        ],
        'Cybersecurity' => [
            'Cybersecurity' => ['\bcybersecurity\b', '\bcyber\s+security\b', '\binformation\s+security\b', '\binfosec\b'],
            'Ethical Hacking & VAPT' => ['\bethical\s+hacking\b', '\bpenetration\s+testing\b', '\bpentesting\b', '\bvapt\b', '\bmetasploit\b', '\bkali\s+linux\b'],
            'Network Security' => ['\bnetwork\s+security\b', '\bfirewalls?\b', '\bwireshark\b'],
            'Web App Security' => ['\bweb\s+application\s+security\b', '\bowasp\b', '\bxss\b', '\bsql\s+injection\b']
        ],
        'Aptitude & Soft Skills' => [
            'Quantitative Aptitude' => ['\bquantitative\s+aptitude\b', '\bquant\b', '\barithmetic\b', '\bnumber\s+systems\b'],
            'Logical Reasoning' => ['\blogical\s+reasoning\b', '\banalytical\s+reasoning\b', '\bpuzzles\b'],
            'Verbal Ability' => ['\bverbal\s+ability\b', '\breading\s+comprehension\b', '\benglish\s+grammar\b'],
            'Soft Skills & Communication' => ['\bsoft\s+skills\b', '\bcommunication\s+skills\b', '\bpublic\s+speaking\b', '\bpersonality\s+development\b'],
            'Campus Placement Training' => ['\bcampus\s+placement(?:\s+training)?\b', '\bplacement\s+training\b', '\bgroup\s+discussion\b', '\bgd\b', '\binterview\s+skills\b']
        ]
    ];

    /**
     * Extract plain text from PDF, DOCX, DOC, TXT, or RTF
     */
    public static function extractTextFromFile($filePath) {
        if (!file_exists($filePath)) {
            return '';
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'pdf':
                return self::extractTextFromPDF($filePath);
            case 'docx':
                return self::extractTextFromDOCX($filePath);
            case 'doc':
                return self::extractTextFromDOC($filePath);
            case 'txt':
                return @file_get_contents($filePath) ?: '';
            case 'rtf':
                return self::extractTextFromRTF($filePath);
            default:
                // Try reading as plain string
                $content = @file_get_contents($filePath);
                return $content ? strip_tags($content) : '';
        }
    }

    /**
     * Pure-PHP DOCX text extractor
     * Leverages native ZipArchive when available, plus a bulletproof pure-PHP PKZip
     * decompressor using gzinflate() so DOCX parsing works on any environment.
     */
    public static function extractTextFromDOCX($filePath) {
        $xmlParts = [];

        // Target XML entries inside DOCX
        $targetEntries = [
            'word/document.xml',
            'word/header1.xml',
            'word/header2.xml',
            'word/header3.xml',
            'word/footer1.xml',
            'word/footer2.xml',
            'word/footer3.xml'
        ];

        // 1. Try PHP ZipArchive if available
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                foreach ($targetEntries as $entry) {
                    $xmlIndex = $zip->locateName($entry);
                    if ($xmlIndex !== false) {
                        $content = $zip->getFromIndex($xmlIndex);
                        if (!empty($content)) {
                            $xmlParts[] = $content;
                        }
                    }
                }
                $zip->close();
            }
        }

        // 2. If ZipArchive not present or extracted nothing, use pure-PHP PKZip Reader
        if (empty($xmlParts)) {
            foreach ($targetEntries as $entry) {
                $content = self::readEntryFromZip($filePath, $entry);
                if (!empty($content)) {
                    $xmlParts[] = $content;
                }
            }
        }

        // 3. Fallback: Search for any compressed XML stream in file
        if (empty($xmlParts)) {
            $raw = @file_get_contents($filePath);
            if ($raw) {
                $pos = strpos($raw, '<w:body>');
                if ($pos !== false) {
                    $endPos = strpos($raw, '</w:body>', $pos);
                    if ($endPos !== false) {
                        $xmlParts[] = substr($raw, $pos, $endPos - $pos + 9);
                    }
                }
            }
        }

        if (empty($xmlParts)) {
            return '';
        }

        // Clean and convert all XML parts to structured plain text
        $fullText = '';
        foreach ($xmlParts as $xml) {
            $fullText .= ' ' . self::wordXmlToPlainText($xml);
        }

        return trim(preg_replace('/\s+/', ' ', $fullText));
    }

    /**
     * Pure-PHP PKZip decompressor: reads any specified file inside a ZIP without external dependencies
     */
    private static function readEntryFromZip($filePath, $entryName) {
        $data = @file_get_contents($filePath);
        if (!$data || strlen($data) < 30) {
            return null;
        }

        // Strategy A: Parse Central Directory from End of Central Directory (EOCD)
        $eocdPos = strrpos($data, "\x50\x4B\x05\x06");
        if ($eocdPos !== false && strlen($data) >= $eocdPos + 22) {
            $cdEntries = unpack('v', substr($data, $eocdPos + 10, 2))[1];
            $cdOffset = unpack('V', substr($data, $eocdPos + 16, 4))[1];

            $offset = $cdOffset;
            for ($i = 0; $i < $cdEntries; $i++) {
                if ($offset + 46 > strlen($data) || substr($data, $offset, 4) !== "\x50\x4B\x01\x02") {
                    break;
                }

                $method = unpack('v', substr($data, $offset + 10, 2))[1];
                $compSize = unpack('V', substr($data, $offset + 20, 4))[1];
                $fnLen = unpack('v', substr($data, $offset + 28, 2))[1];
                $extraLen = unpack('v', substr($data, $offset + 30, 2))[1];
                $commentLen = unpack('v', substr($data, $offset + 32, 2))[1];
                $localOffset = unpack('V', substr($data, $offset + 42, 4))[1];

                $currentFilename = substr($data, $offset + 46, $fnLen);

                if (strcasecmp($currentFilename, $entryName) === 0) {
                    if ($localOffset + 30 <= strlen($data) && substr($data, $localOffset, 4) === "\x50\x4B\x03\x04") {
                        $localFnLen = unpack('v', substr($data, $localOffset + 26, 2))[1];
                        $localExtraLen = unpack('v', substr($data, $localOffset + 28, 2))[1];
                        $dataOffset = $localOffset + 30 + $localFnLen + $localExtraLen;

                        $compData = substr($data, $dataOffset, $compSize);
                        if ($method === 0) {
                            return $compData;
                        } elseif ($method === 8) {
                            $uncompressed = @gzinflate($compData);
                            if ($uncompressed === false) {
                                $uncompressed = @gzuncompress($compData);
                            }
                            return $uncompressed ?: null;
                        }
                    }
                }

                $offset += 46 + $fnLen + $extraLen + $commentLen;
            }
        }

        // Strategy B: Sequential local file header scanning
        $pos = 0;
        $fileLen = strlen($data);
        while ($pos < $fileLen - 30) {
            $sigPos = strpos($data, "\x50\x4B\x03\x04", $pos);
            if ($sigPos === false) break;

            $method = unpack('v', substr($data, $sigPos + 8, 2))[1];
            $compSize = unpack('V', substr($data, $sigPos + 18, 4))[1];
            $fnLen = unpack('v', substr($data, $sigPos + 26, 2))[1];
            $extraLen = unpack('v', substr($data, $sigPos + 28, 2))[1];

            if ($sigPos + 30 + $fnLen > $fileLen) break;
            $currentFilename = substr($data, $sigPos + 30, $fnLen);

            if (strcasecmp($currentFilename, $entryName) === 0) {
                $dataStart = $sigPos + 30 + $fnLen + $extraLen;
                if ($compSize > 0 && $dataStart + $compSize <= $fileLen) {
                    $compData = substr($data, $dataStart, $compSize);
                    if ($method === 0) {
                        return $compData;
                    } elseif ($method === 8) {
                        $uncompressed = @gzinflate($compData);
                        if ($uncompressed === false) {
                            $uncompressed = @gzuncompress($compData);
                        }
                        if ($uncompressed !== false) return $uncompressed;
                    }
                } else {
                    $chunk = substr($data, $dataStart);
                    $uncompressed = @gzinflate($chunk);
                    if ($uncompressed !== false) return $uncompressed;
                }
            }

            $pos = $sigPos + 4;
        }

        return null;
    }

    /**
     * Converts WordprocessingML XML to clean, readable plain text
     */
    private static function wordXmlToPlainText($xml) {
        // Replace paragraph and break tags with spaces / newlines
        $xml = preg_replace('/<\/w:p>/i', ' ', $xml);
        $xml = preg_replace('/<w:br[^>]*>/i', ' ', $xml);
        $xml = preg_replace('/<w:cr[^>]*>/i', ' ', $xml);
        $xml = preg_replace('/<w:tab[^>]*>/i', ' ', $xml);

        // Extract all <w:t> element texts
        if (preg_match_all('/<w:t(?:\s+[^>]*)?>(.*?)<\/w:t>/is', $xml, $matches)) {
            $text = implode(' ', $matches[1]);
        } else {
            $text = strip_tags($xml);
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Pure-PHP PDF text extractor
     */
    private static function extractTextFromPDF($filePath) {
        $content = @file_get_contents($filePath);
        if (!$content) return '';

        $text = '';

        // Extract streams
        preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/is', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $stream) {
                $uncompressed = @gzuncompress($stream);
                if ($uncompressed === false) {
                    $uncompressed = @gzinflate($stream);
                }
                if ($uncompressed === false) {
                    $uncompressed = $stream;
                }

                $text .= " " . self::parsePDFStreamTokens($uncompressed);
            }
        }

        // Also parse literal text strings outside stream if present
        $text .= " " . self::parsePDFStreamTokens($content);

        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function parsePDFStreamTokens($stream) {
        $result = '';

        // Extract TJ array syntax: [ (Text) -20 (More) ] TJ
        if (preg_match_all('/\[(.*?)\]\s*TJ/is', $stream, $tjMatches)) {
            foreach ($tjMatches[1] as $tj) {
                if (preg_match_all('/\((.*?)\)/s', $tj, $strMatches)) {
                    foreach ($strMatches[1] as $s) {
                        $result .= stripcslashes($s) . ' ';
                    }
                }
            }
        }

        // Extract Tj string syntax: (Text) Tj
        if (preg_match_all('/\((.*?)\)\s*Tj/is', $stream, $tMatches)) {
            foreach ($tMatches[1] as $m) {
                $result .= stripcslashes($m) . ' ';
            }
        }

        // Extract ' syntax
        if (preg_match_all('/\((.*?)\)\s*\'/is', $stream, $quoteMatches)) {
            foreach ($quoteMatches[1] as $m) {
                $result .= stripcslashes($m) . ' ';
            }
        }

        return $result;
    }

    /**
     * Legacy Word .DOC (Word 97-2003 binary format) plain text extractor
     */
    private static function extractTextFromDOC($filePath) {
        $content = @file_get_contents($filePath);
        if (!$content) return '';

        $text = '';
        // Extract UTF-16LE text runs
        if (preg_match_all('/(?:[\x20-\x7E]\x00){3,}/', $content, $utfMatches)) {
            foreach ($utfMatches[0] as $match) {
                $converted = @mb_convert_encoding($match, 'UTF-8', 'UTF-16LE');
                if (!empty($converted)) {
                    $text .= ' ' . $converted;
                }
            }
        }

        // Extract printable ASCII runs
        if (preg_match_all('/[a-zA-Z0-9\s.,+@#\-_:\/]{4,}/', $content, $asciiMatches)) {
            foreach ($asciiMatches[0] as $match) {
                $text .= ' ' . $match;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * RTF plain text stripper
     */
    private static function extractTextFromRTF($filePath) {
        $text = @file_get_contents($filePath);
        if (!$text) return '';
        $text = preg_replace('/\{\\\\.*?\}|\\\\[a-z]+-?[0-9]* ?/i', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Extract skills dictionary matches from raw string / text
     */
    public static function extractSkillsFromText($rawText) {
        $normalizedText = ' ' . strtolower($rawText) . ' ';
        $extracted = [];

        foreach (self::$skillDictionary as $domain => $skills) {
            foreach ($skills as $skillName => $patterns) {
                foreach ($patterns as $pattern) {
                    $cleanPattern = preg_quote($pattern, '/');
                    if (strpos($pattern, '\b') !== false || strpos($pattern, '(') !== false) {
                        $regex = '/' . $pattern . '/i';
                    } else {
                        $regex = '/(?<=[\s,;.()\/\-]|^)' . $cleanPattern . '(?=[\s,;.()\/\-]|$)/i';
                    }

                    if (@preg_match($regex, $normalizedText)) {
                        $extracted[$domain][] = $skillName;
                        break;
                    }
                }
            }
        }
        return $extracted;
    }

    /**
     * Extract matching skills, estimate experience, and determine primary domain
     */
    public static function analyzeResume($filePath) {
        $rawText = self::extractTextFromFile($filePath);
        $normalizedText = ' ' . strtolower($rawText) . ' ';

        $extractedSkills = [];
        $categoryScores = [];

        foreach (self::$skillDictionary as $domain => $skills) {
            if (!isset($categoryScores[$domain])) {
                $categoryScores[$domain] = 0;
            }

            foreach ($skills as $skillName => $patterns) {
                $found = false;
                foreach ($patterns as $pattern) {
                    $cleanPattern = preg_quote($pattern, '/');
                    if (strpos($pattern, '\b') !== false || strpos($pattern, '(') !== false) {
                        $regex = '/' . $pattern . '/i';
                    } else {
                        $regex = '/(?<=[\s,;.()\/\-]|^)' . $cleanPattern . '(?=[\s,;.()\/\-]|$)/i';
                    }

                    if (@preg_match($regex, $normalizedText)) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $extractedSkills[] = [
                        'name' => $skillName,
                        'category' => $domain,
                        'proficiency' => 'ADVANCED'
                    ];
                    $categoryScores[$domain] += 1;
                }
            }
        }

        // Determine Primary Domain by highest score
        arsort($categoryScores);
        $topDomain = key($categoryScores);
        if (empty($topDomain) || $categoryScores[$topDomain] === 0) {
            $topDomain = 'Programming';
        }

        // Estimate Years of Experience from patterns like "5+ years", "6 years of experience", etc.
        $detectedYears = 3; // Default baseline
        if (preg_match('/(\d{1,2})\+?\s*(?:years?|yrs?)(?:\s*(?:of)?\s*(?:industry|professional|work|hands-on|teaching|training)?\s*experience)?/i', $rawText, $expMatch)) {
            $val = (int)$expMatch[1];
            if ($val >= 1 && $val <= 30) {
                $detectedYears = $val;
            }
        }

        return [
            'success' => true,
            'textLength' => strlen($rawText),
            'skills' => $extractedSkills,
            'skillCount' => count($extractedSkills),
            'recommendedDomain' => $topDomain,
            'estimatedExperienceYears' => $detectedYears,
            'categoryBreakdown' => $categoryScores
        ];
    }

    /**
     * Process an uploaded resume for a trainer and synchronize skills into MongoDB
     */
    public static function processAndSaveTrainerResume($trainerId, $filePath) {
        $analysis = self::analyzeResume($filePath);
        $trainerCol = getCollection("Trainer");
        $skillCol = getCollection("Skill");

        if (!$trainerCol || empty($trainerId)) {
            return $analysis;
        }

        try {
            $trainerObjId = new MongoDB\BSON\ObjectId($trainerId);
            $trainer = $trainerCol->findOne(['_id' => $trainerObjId]);
            if (!$trainer) return $analysis;

            // 1. Save extracted skills list on the Trainer document
            $skillNames = array_column($analysis['skills'], 'name');
            $trainerCol->updateOne(
                ['_id' => $trainerObjId],
                ['$set' => [
                    'extractedSkills' => $skillNames,
                    'extractedSkillsCount' => count($skillNames),
                    'resumeParsedAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // If trainer had no primary domain or generic default, update with detected
            $updateFields = [];
            if (empty($trainer['primaryDomain']) || $trainer['primaryDomain'] === 'Programming') {
                if (!empty($analysis['recommendedDomain'])) {
                    $updateFields['primaryDomain'] = $analysis['recommendedDomain'];
                }
            }
            if (empty($trainer['totalExperienceYears']) || $trainer['totalExperienceYears'] < $analysis['estimatedExperienceYears']) {
                $updateFields['totalExperienceYears'] = $analysis['estimatedExperienceYears'];
            }
            if (!empty($updateFields)) {
                $trainerCol->updateOne(['_id' => $trainerObjId], ['$set' => $updateFields]);
            }

            // 2. Insert into Skill collection without duplicating existing
            if ($skillCol && !empty($analysis['skills'])) {
                $existingSkills = $skillCol->find(['trainerId' => (string)$trainerId])->toArray();
                $existingNames = array_map(function($s) { return strtolower($s['name']); }, $existingSkills);

                foreach ($analysis['skills'] as $s) {
                    if (!in_array(strtolower($s['name']), $existingNames)) {
                        $skillCol->insertOne([
                            'trainerId' => (string)$trainerId,
                            'name' => $s['name'],
                            'category' => $s['category'],
                            'proficiencyLevel' => $s['proficiency'],
                            'yearsOfExperience' => $analysis['estimatedExperienceYears'],
                            'verified' => true,
                            'source' => 'RESUME_EXTRACTED',
                            'createdAt' => new MongoDB\BSON\UTCDateTime(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error saving parsed resume skills: " . $e->getMessage());
        }

        return $analysis;
    }
}
