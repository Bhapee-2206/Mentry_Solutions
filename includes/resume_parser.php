<?php
// includes/resume_parser.php - Pure PHP Resume Parser & Skill Extraction Engine
require_once __DIR__ . '/db.php';

class ResumeSkillParser {

    private static $skillDictionary = [
        'Programming' => [
            'Python' => ['python', 'python3', 'python2'],
            'Java' => ['java', 'core java', 'advanced java', 'j2ee', 'jdk'],
            'C++' => ['c++', 'cpp'],
            'C Programming' => ['\bc\b', 'c language', 'ansi c'],
            'C#' => ['c#', 'csharp', '.net'],
            'JavaScript' => ['javascript', 'js', 'es6', 'ecmascript'],
            'TypeScript' => ['typescript', 'ts'],
            'PHP' => ['php', 'php7', 'php8'],
            'Go / Golang' => ['golang', '\bgo\b'],
            'Rust' => ['rust'],
            'Ruby' => ['ruby', 'ruby on rails'],
            'Kotlin' => ['kotlin'],
            'Swift' => ['swift'],
            'R Programming' => ['\br\b', 'r programming', 'r language'],
            'SQL' => ['sql', 't-sql', 'pl/sql'],
            'HTML/CSS' => ['html', 'html5', 'css', 'css3'],
            'Data Structures & Algorithms' => ['data structures', 'dsa', 'algorithms', 'problem solving']
        ],
        'Frameworks' => [
            'React.js' => ['react', 'reactjs', 'react.js'],
            'Angular' => ['angular', 'angularjs', 'angular 2+'],
            'Vue.js' => ['vue', 'vuejs', 'vue.js'],
            'Next.js' => ['nextjs', 'next.js'],
            'Node.js' => ['nodejs', 'node.js', 'node'],
            'Express.js' => ['express', 'expressjs', 'express.js'],
            'Django' => ['django', 'django rest framework', 'drf'],
            'Flask' => ['flask'],
            'FastAPI' => ['fastapi'],
            'Spring Boot' => ['spring', 'spring boot', 'springboot'],
            'Laravel' => ['laravel'],
            'ASP.NET' => ['asp.net', 'asp.net core', '.net core'],
            'Flutter' => ['flutter', 'dart'],
            'React Native' => ['react native'],
            'Tailwind CSS' => ['tailwind', 'tailwindcss']
        ],
        'Cloud & DevOps' => [
            'AWS' => ['aws', 'amazon web services', 'ec2', 's3', 'lambda', 'cloudformation'],
            'Microsoft Azure' => ['azure', 'microsoft azure', 'azure devops'],
            'Google Cloud (GCP)' => ['gcp', 'google cloud', 'google cloud platform'],
            'Docker' => ['docker', 'containerization', 'containers'],
            'Kubernetes' => ['kubernetes', 'k8s'],
            'Terraform' => ['terraform', 'iac'],
            'Ansible' => ['ansible'],
            'CI/CD Pipelines' => ['ci/cd', 'cicd', 'jenkins', 'github actions', 'gitlab ci'],
            'Linux / Shell' => ['linux', 'ubuntu', 'centos', 'redhat', 'bash', 'shell scripting'],
            'Git & GitHub' => ['git', 'github', 'gitlab', 'version control']
        ],
        'Databases' => [
            'MySQL' => ['mysql'],
            'PostgreSQL' => ['postgresql', 'postgres'],
            'MongoDB' => ['mongodb', 'nosql', 'mongoose'],
            'Redis' => ['redis'],
            'Oracle DB' => ['oracle database', 'oracle db', 'plsql'],
            'Microsoft SQL Server' => ['ms sql', 'mssql', 'sql server'],
            'Firebase' => ['firebase', 'firestore'],
            'Elasticsearch' => ['elasticsearch', 'elk stack']
        ],
        'Data Science & AI' => [
            'Machine Learning' => ['machine learning', 'ml', 'supervised learning', 'unsupervised learning'],
            'Deep Learning' => ['deep learning', 'neural networks', 'ann', 'cnn', 'rnn', 'lstm'],
            'Artificial Intelligence' => ['artificial intelligence', '\bai\b', 'ai/ml'],
            'Natural Language Processing (NLP)' => ['nlp', 'natural language processing', 'transformers', 'bert', 'gpt'],
            'Generative AI & LLMs' => ['generative ai', 'genai', 'large language models', 'llm', 'langchain', 'openai'],
            'Computer Vision' => ['computer vision', 'opencv', 'image processing', 'yolo'],
            'Data Analysis & Visualization' => ['data analytics', 'data analysis', 'eda', 'statistics'],
            'Pandas & NumPy' => ['pandas', 'numpy', 'scipy'],
            'TensorFlow & PyTorch' => ['tensorflow', 'pytorch', 'keras'],
            'Scikit-learn' => ['scikit-learn', 'sklearn'],
            'Power BI' => ['power bi', 'powerbi'],
            'Tableau' => ['tableau'],
            'Big Data & Spark' => ['big data', 'apache spark', 'pyspark', 'hadoop']
        ],
        'VLSI & Embedded' => [
            'VLSI Design' => ['vlsi', 'asic', 'fpga', 'rtl design', 'soc'],
            'Verilog / VHDL' => ['verilog', 'systemverilog', 'vhdl'],
            'Embedded Systems' => ['embedded systems', 'embedded c', 'firmware'],
            'Microcontrollers' => ['microcontrollers', 'arm', 'cortex', '8051', 'pic', 'stm32', 'arduino', 'raspberry pi'],
            'IoT' => ['iot', 'internet of things', 'sensors', 'mqtt', 'esp32', 'esp8266'],
            'Robotics' => ['robotics', 'ros', 'robot operating system'],
            'PLC & SCADA' => ['plc', 'scada', 'industrial automation']
        ],
        'Cybersecurity' => [
            'Cybersecurity' => ['cybersecurity', 'cyber security', 'information security', 'infosec'],
            'Ethical Hacking & VAPT' => ['ethical hacking', 'penetration testing', 'pentesting', 'vapt', 'metasploit'],
            'Network Security' => ['network security', 'firewalls', 'ids/ips', 'wireshark', 'kali linux'],
            'Web App Security' => ['web application security', 'owasp', 'xss', 'sql injection']
        ],
        'Aptitude & Soft Skills' => [
            'Quantitative Aptitude' => ['quantitative aptitude', 'quant', 'arithmetic', 'number systems'],
            'Logical Reasoning' => ['logical reasoning', 'analytical reasoning', 'puzzles', 'critical thinking'],
            'Verbal Ability' => ['verbal ability', 'english grammar', 'reading comprehension', 'vocabulary'],
            'Communication Skills' => ['communication skills', 'spoken english', 'business communication', 'public speaking'],
            'Campus Placement Training' => ['campus placement training', 'placement training', 'interview skills', 'group discussion', 'gd'],
            'Soft Skills & Corporate Etiquette' => ['soft skills', 'corporate etiquette', 'body language', 'personality development']
        ]
    ];

    /**
     * Extract plain text from PDF, DOCX, TXT, or RTF
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
     * Pure-PHP PDF text extractor
     */
    private static function extractTextFromPDF($filePath) {
        $content = @file_get_contents($filePath);
        if (!$content) return '';

        $text = '';

        // 1. Check for decompressed or uncompressed streams
        preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/is', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $stream) {
                // Try decompressing with gzuncompress
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

        // 2. Also parse literal text strings outside stream if any
        $text .= " " . self::parsePDFStreamTokens($content);

        // Normalize text
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
     * Pure-PHP DOCX text extractor (using ZipArchive or ZIP parsing)
     */
    private static function extractTextFromDOCX($filePath) {
        $text = '';

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $xmlIndex = $zip->locateName('word/document.xml');
                if ($xmlIndex !== false) {
                    $xml = $zip->getFromIndex($xmlIndex);
                    // Replace paragraph ends with spaces
                    $xml = str_replace(['</w:p>', '</w:r>', '<w:tab/>', '<w:br/>'], ' ', $xml);
                    $text = strip_tags($xml);
                }
                $zip->close();
                return trim(preg_replace('/\s+/', ' ', $text));
            }
        }

        // Fallback: search for XML tokens directly in file content
        $raw = @file_get_contents($filePath);
        if ($raw) {
            $pos = strpos($raw, '<w:body>');
            if ($pos !== false) {
                $endPos = strpos($raw, '</w:body>', $pos);
                if ($endPos !== false) {
                    $xml = substr($raw, $pos, $endPos - $pos + 9);
                    return trim(strip_tags($xml));
                }
            }
        }

        return '';
    }

    /**
     * RTF plain text stripper
     */
    private static function extractTextFromRTF($filePath) {
        $text = @file_get_contents($filePath);
        if (!$text) return '';
        // Strip RTF control words
        $text = preg_replace('/\{\\\\.*?\}|\\\\[a-z]+-?[0-9]* ?/i', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
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
                    // Check exact phrase or word boundary
                    $cleanPattern = preg_quote($pattern, '/');
                    if (strpos($pattern, '\b') !== false) {
                        $regex = '/' . str_replace('\b', '\b', $pattern) . '/i';
                    } else {
                        $regex = '/(?<=[\s,;.()\/\-]|^)' . $cleanPattern . '(?=[\s,;.()\/\-]|$)/i';
                    }

                    if (preg_match($regex, $normalizedText)) {
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
        $detectedYears = 3; // Default sensible baseline
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

            // If trainer had no primary domain or low experience, update with detected
            $updateFields = [];
            if (empty($trainer['primaryDomain']) || $trainer['primaryDomain'] === 'Programming') {
                $updateFields['primaryDomain'] = $analysis['recommendedDomain'];
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
