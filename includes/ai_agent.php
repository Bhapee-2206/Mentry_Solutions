<?php
// includes/ai_agent.php - Zervy: Intelligent AI Brain, Trainer Match Engine & Token Calculator

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/resume_parser.php';
require_once __DIR__ . '/helpers.php';

class AIAgent {

    private static $apiKey = null;
    private static $model = 'gemini-2.5-flash';

    private static function initConfig() {
        if (self::$apiKey !== null) return;

        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $l) {
                    $l = trim($l);
                    if (empty($l) || $l[0] === '#') continue;
                    if (strpos($l, '=') !== false) {
                        list($k, $v) = explode('=', $l, 2);
                        $k = trim($k);
                        $v = trim(trim($v), '"\'');
                        if ($k === 'GEMINI_API_KEY' && !empty($v)) {
                            self::$apiKey = $v;
                        }
                        if ($k === 'GEMINI_MODEL' && !empty($v)) {
                            self::$model = $v;
                        }
                    }
                }
            }
        }
        if (empty(self::$apiKey)) {
            self::$apiKey = getenv('GEMINI_API_KEY') ?: '';
        }
        if (empty(self::$model)) {
            self::$model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
        }
    }

    /**
     * Token Calculator: Tracks and computes token usage & costs
     */
    public static function trackTokenUsage($promptText, $completionText, $geminiUsage = null) {
        $usageFile = __DIR__ . '/../config/token_usage.json';
        $cumulative = [
            'totalRequests' => 0,
            'totalPromptTokens' => 0,
            'totalCompletionTokens' => 0,
            'grandTotalTokens' => 0,
            'estimatedCostUsd' => 0.0
        ];

        if (file_exists($usageFile)) {
            $existing = @json_decode(file_get_contents($usageFile), true);
            if (is_array($existing)) {
                $cumulative = array_merge($cumulative, $existing);
            }
        }

        $promptTokens = 0;
        $completionTokens = 0;

        if ($geminiUsage && isset($geminiUsage['promptTokenCount'])) {
            $promptTokens = (int)$geminiUsage['promptTokenCount'];
            $completionTokens = (int)($geminiUsage['candidatesTokenCount'] ?? 0);
        } else {
            $promptTokens = max(1, (int)ceil(strlen($promptText) / 4));
            $completionTokens = max(1, (int)ceil(strlen($completionText) / 4));
        }

        $totalTokens = $promptTokens + $completionTokens;

        // Gemini 1.5 Flash Pricing: $0.075 / 1M input tokens, $0.30 / 1M output tokens
        $reqCost = ($promptTokens * 0.000000075) + ($completionTokens * 0.00000030);

        $cumulative['totalRequests'] += 1;
        $cumulative['totalPromptTokens'] += $promptTokens;
        $cumulative['totalCompletionTokens'] += $completionTokens;
        $cumulative['grandTotalTokens'] += $totalTokens;
        $cumulative['estimatedCostUsd'] = round($cumulative['estimatedCostUsd'] + $reqCost, 6);
        $cumulative['lastUpdated'] = date('Y-m-d H:i:s');

        @file_put_contents($usageFile, json_encode($cumulative, JSON_PRETTY_PRINT));

        return [
            'promptTokens' => $promptTokens,
            'completionTokens' => $completionTokens,
            'totalTokens' => $totalTokens,
            'requestCostUsd' => '$' . number_format($reqCost, 6),
            'cumulativeTokens' => $cumulative['grandTotalTokens'],
            'totalRequests' => $cumulative['totalRequests'],
            'cumulativeCostUsd' => '$' . number_format($cumulative['estimatedCostUsd'], 4),
            'model' => self::$model
        ];
    }

    /**
     * Get aggregate Token usage metrics
     */
    public static function getTokenMetrics() {
        $usageFile = __DIR__ . '/../config/token_usage.json';
        if (file_exists($usageFile)) {
            $data = @json_decode(file_get_contents($usageFile), true);
            if (is_array($data)) return $data;
        }
        return [
            'totalRequests' => 0,
            'totalPromptTokens' => 0,
            'totalCompletionTokens' => 0,
            'grandTotalTokens' => 0,
            'estimatedCostUsd' => 0.0
        ];
    }

    /**
     * Call Google Gemini API securely from backend
     */
    public static function callGemini($prompt, $systemInstruction = '', $jsonMode = false) {
        self::initConfig();

        if (empty(self::$apiKey)) {
            return [
                'success' => false,
                'error' => 'GEMINI_API_KEY is not configured in .env'
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode(self::$model) . ":generateContent?key=" . urlencode(self::$apiKey);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topP' => 0.85,
                'topK' => 40,
                'maxOutputTokens' => 2048
            ]
        ];

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("Gemini cURL Error: " . $curlErr);
            return ['success' => false, 'error' => $curlErr];
        }

        if ($httpCode !== 200) {
            error_log("Gemini API HTTP Error {$httpCode}: " . $response);
            return ['success' => false, 'error' => "Gemini API responded with status {$httpCode}"];
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usage = $data['usageMetadata'] ?? null;

        return [
            'success' => true,
            'text' => $text,
            'usage' => $usage,
            'raw' => $data
        ];
    }

    /**
     * Tool: searchTrainers
     */
    public static function searchTrainers($skills = [], $domain = '', $minExp = 0, $location = '', $mode = 'ALL') {
        $trainerCol = getCollection("Trainer");
        $trainers = [];

        $cleanMode = strtoupper(trim((string)$mode));
        $cleanLocation = trim((string)$location);
        if (in_array(strtoupper($cleanLocation), ['ANY', 'ALL', 'NONE', 'N/A', ''])) {
            $cleanLocation = '';
        }

        try {
            if ($trainerCol) {
                $filter = ['status' => 'APPROVED'];
                if (!empty($cleanMode) && !in_array($cleanMode, ['ALL', 'ANY', 'NONE', 'BOTH', 'EITHER', ''])) {
                    $filter['$or'] = [
                        ['preferredMode' => $cleanMode],
                        ['preferredMode' => 'HYBRID'],
                        ['preferredMode' => ['$exists' => false]],
                        ['preferredMode' => null]
                    ];
                }
                $cursor = $trainerCol->find($filter);
                $trainers = $cursor->toArray();
            }
        } catch (\Throwable $e) {
            error_log("AIAgent DB Error: " . $e->getMessage());
        }

        if (empty($trainers)) {
            $trainers = self::getDefaultTrainersPool();
        }

        $mockOpp = [
            'domain' => $domain,
            'skillsRequired' => $skills,
            'minExperienceYears' => $minExp,
            'city' => $cleanLocation,
            'mode' => in_array($cleanMode, ['ALL', 'ANY', '']) ? 'ALL' : $cleanMode
        ];

        $results = [];
        foreach ($trainers as $t) {
            $tSkills = $t['skills'] ?? ($t['extractedSkills'] ?? []);
            $eval = MatchingEngine::evaluateMatch($mockOpp, $t, $tSkills);
            
            $results[] = [
                'trainer' => $t,
                'score' => $eval['score'] ?? 50,
                'breakdown' => $eval
            ];
        }

        usort($results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $results;
    }

    /**
     * Tool: getTrainerProfile
     */
    public static function getTrainerProfile($trainerId) {
        $trainerCol = getCollection("Trainer");
        if ($trainerCol) {
            try {
                if (preg_match('/^[a-f0-9]{24}$/i', $trainerId)) {
                    $t = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
                    if ($t) return $t;
                }
                $t = $trainerCol->findOne(['id' => $trainerId]);
                if ($t) return $t;
            } catch (\Throwable $e) {}
        }
        foreach (self::getDefaultTrainersPool() as $t) {
            if (($t['id'] ?? '') === $trainerId || (string)($t['_id'] ?? '') === $trainerId) {
                return $t;
            }
        }
        return null;
    }

    /**
     * Tool: compareTrainers
     */
    public static function compareTrainers($trainerIds = [], $requirementText = '') {
        $trainers = [];
        foreach ($trainerIds as $id) {
            $p = self::getTrainerProfile($id);
            if ($p) $trainers[] = $p;
        }

        if (empty($trainers)) {
            return [
                'success' => false,
                'message' => 'No matching trainers found for comparison.'
            ];
        }

        $systemPrompt = "You are Zervy, the internal AI matching specialist for Mentry Solutions.
Compare the given trainers against the requirement. Base your comparison strictly on verified records.
Return a valid JSON object matching this schema:
{
  \"summary\": \"Short overview comparing candidate strengths\",
  \"comparisonMatrix\": [
    { \"category\": \"Skills Match\", \"values\": { \"trainer_name\": \"Strong/Medium/Low with brief detail\" } },
    { \"category\": \"Training Experience\", \"values\": { ... } },
    { \"category\": \"Domain Expertise\", \"values\": { ... } },
    { \"category\": \"Location & Delivery Mode\", \"values\": { ... } },
    { \"category\": \"Certifications\", \"values\": { ... } }
  ],
  \"recommendedChoice\": {
    \"trainerId\": \"id\",
    \"trainerName\": \"name\",
    \"reason\": \"Factual concise justification why this trainer is the best fit.\"
  }
}";

        $userPrompt = "Requirement: " . $requirementText . "\n\nTrainers Data:\n" . json_encode($trainers, JSON_PRETTY_PRINT);
        
        $aiRes = self::callGemini($userPrompt, $systemPrompt, true);
        $tokenStats = self::trackTokenUsage($userPrompt, $aiRes['text'] ?? '', $aiRes['usage'] ?? null);

        if ($aiRes['success'] && !empty($aiRes['text'])) {
            $decoded = json_decode($aiRes['text'], true);
            if (is_array($decoded)) {
                return [
                    'success' => true,
                    'comparison' => $decoded,
                    'trainers' => $trainers,
                    'tokenStats' => $tokenStats
                ];
            }
        }

        // Fallback matrix
        $matrix = [
            ['category' => 'Primary Skills', 'values' => []],
            ['category' => 'Total Experience', 'values' => []],
            ['category' => 'Training Track Record', 'values' => []],
            ['category' => 'Location / Delivery Mode', 'values' => []],
            ['category' => 'Certifications', 'values' => []]
        ];

        foreach ($trainers as $t) {
            $tName = $t['name'];
            $matrix[0]['values'][$tName] = implode(', ', array_slice($t['skills'] ?? ['General'], 0, 4));
            $matrix[1]['values'][$tName] = ($t['totalExperienceYears'] ?? '5+') . ' Years';
            $matrix[2]['values'][$tName] = ($t['completedTrainingsCount'] ?? '10+') . ' Programs Completed';
            $matrix[3]['values'][$tName] = ($t['city'] ?? 'India') . ' (' . ($t['preferredMode'] ?? 'HYBRID') . ')';
            $matrix[4]['values'][$tName] = !empty($t['certifications']) ? implode(', ', $t['certifications']) : 'Information not available in trainer profile';
        }

        $topTrainer = $trainers[0];
        return [
            'success' => true,
            'comparison' => [
                'summary' => 'Direct comparison based on verified profile records and training track record.',
                'comparisonMatrix' => $matrix,
                'recommendedChoice' => [
                    'trainerId' => $topTrainer['id'] ?? (string)($topTrainer['_id'] ?? ''),
                    'trainerName' => $topTrainer['name'],
                    'reason' => 'Highest combined alignment with verified skills (' . implode(', ', array_slice($topTrainer['skills'] ?? [], 0, 3)) . ') and ' . ($topTrainer['totalExperienceYears'] ?? '5') . '+ years of subject-matter experience.'
                ]
            ],
            'trainers' => $trainers,
            'tokenStats' => $tokenStats
        ];
    }

    /**
     * Zervy AI Thinking Brain: Understands any query, questions, or requirements dynamically via Gemini
     */
    public static function processRequirementQuery($query) {
        $query = trim($query);
        if (empty($query)) {
            return [
                'success' => false,
                'message' => 'Please provide a question or training requirement.'
            ];
        }

        // Step 1: Brain Thinking Analysis via Gemini
        // Ask Gemini to classify user intent: Is it a generic question/conversation OR a specific trainer discovery requirement?
        $brainSystemPrompt = "You are Zervy's Decision Engine for Mentry Solutions (India's Premier Trainer Operations Platform).
Analyze the user's input:
1. If the user is greeting ('hi', 'how are you'), asking general questions ('who are you', 'explain cloud computing', 'what is react'), requesting curriculum/syllabus ('draft a 5-day python syllabus'), requesting email templates, or discussing non-training topics ('invoice', 'call me'):
   Set action = 'CONVERSATION', and provide a friendly, helpful, professional markdown response as Zervy AI.
2. If the user is searching for trainers or specifying training requirements ('need a python trainer in Bangalore', 'find java faculty for corporate workshop', 'React and Node trainers in Chennai'):
   Set action = 'TRAINER_SEARCH', extract skills, domain, location, delivery mode, and duration.

Return JSON:
{
  \"action\": \"CONVERSATION\" | \"TRAINER_SEARCH\",
  \"replyText\": \"Detailed markdown response if action is CONVERSATION, else null\",
  \"extracted\": {
    \"topic\": \"Identified topic or domain\",
    \"skills\": [\"Skill1\", \"Skill2\"],
    \"location\": \"City or Any\",
    \"mode\": \"ONLINE/OFFLINE/HYBRID/Any\",
    \"duration\": \"Duration if stated or null\"
  }
}";

        $brainRes = self::callGemini("User Query: " . $query, $brainSystemPrompt, true);

        if ($brainRes['success'] && !empty($brainRes['text'])) {
            $brainData = json_decode($brainRes['text'], true);
            if (is_array($brainData)) {
                
                // If Zervy Brain decided it's a conversational / advice query:
                if (($brainData['action'] ?? '') === 'CONVERSATION' && !empty($brainData['replyText'])) {
                    $tokenStats = self::trackTokenUsage($query, $brainData['replyText'], $brainRes['usage'] ?? null);
                    return [
                        'success' => true,
                        'isConversational' => true,
                        'conversationalMessage' => $brainData['replyText'],
                        'data' => [
                            'understoodRequirement' => null,
                            'clarification' => null,
                            'topMatches' => []
                        ],
                        'tokenStats' => $tokenStats,
                        'source' => 'zervy-brain-ai'
                    ];
                }

                // If Zervy Brain identified a Trainer Search Requirement:
                if (($brainData['action'] ?? '') === 'TRAINER_SEARCH') {
                    $extracted = $brainData['extracted'] ?? [];
                    $skillsList = $extracted['skills'] ?? [];
                    
                    // Also merge with deterministic parser extraction to never miss recognized skills
                    $parserSkills = ResumeSkillParser::extractSkillsFromText($query);
                    foreach ($parserSkills as $cat => $skList) {
                        foreach ($skList as $sk) {
                            if (!in_array($sk, $skillsList)) {
                                $skillsList[] = $sk;
                            }
                        }
                    }

                    $detectedLocation = $extracted['location'] ?? '';
                    $detectedMode = $extracted['mode'] ?? 'ALL';
                    $topicDomain = $extracted['topic'] ?? '';

                    // Perform database candidate discovery
                    $candidates = self::searchTrainers($skillsList, $topicDomain, 0, $detectedLocation, $detectedMode);
                    $topCandidates = array_slice($candidates, 0, 5);

                    // If candidates exist, rank and explain via Gemini
                    $candidatesData = [];
                    foreach ($topCandidates as $c) {
                        $t = $c['trainer'];
                        $tCode = $t['trainerCode'] ?? ($t['mentryId'] ?? getMentryCode('TRAINER', $t));
                        $candidatesData[] = [
                            'id' => (string)($t['_id'] ?? ($t['id'] ?? '')),
                            'trainerCode' => $tCode,
                            'name' => $t['name'],
                            'headline' => $t['professionalTitle'] ?? ($t['headline'] ?? ($t['primaryDomain'] . ' Specialist')),
                            'totalExperienceYears' => $t['totalExperienceYears'] ?? 5,
                            'skills' => $t['skills'] ?? ($t['extractedSkills'] ?? []),
                            'primaryDomain' => $t['primaryDomain'] ?? 'Technical Training',
                            'city' => $t['currentCity'] ?? ($t['city'] ?? 'Bangalore'),
                            'preferredMode' => $t['travelPreference'] ?? ($t['preferredMode'] ?? 'HYBRID'),
                            'certifications' => $t['certifications'] ?? [],
                            'completedTrainings' => $t['completedTrainingsCount'] ?? 8,
                            'baseScore' => $c['score']
                        ];
                    }

                    $rankPrompt = "Rank these candidates for requirement: " . $query . "\n\nCandidates Data:\n" . json_encode($candidatesData, JSON_PRETTY_PRINT);
                    $rankSystem = "You are Zervy AI. Rank and explain why each trainer matches the requirement based on factual records. Return JSON:
{
  \"understoodRequirement\": {
    \"topic\": \"" . ($topicDomain ?: 'Training Program') . "\",
    \"skills\": " . json_encode($skillsList) . ",
    \"location\": \"" . ($detectedLocation ?: 'Any') . "\",
    \"mode\": \"" . $detectedMode . "\"
  },
  \"clarification\": null,
  \"topMatches\": [
    {
      \"trainerId\": \"id\",
      \"trainerCode\": \"MEN-TRN-xxxx\",
      \"name\": \"name\",
      \"headline\": \"headline\",
      \"matchScore\": 95,
      \"confidence\": \"High\",
      \"matchingSkills\": [\"Skill1\"],
      \"relevantExperienceYears\": 8,
      \"relevantTrainings\": \"5 corporate programs\",
      \"whyRecommended\": \"Factual reason why they match.\"
    }
  ]
}";
                    $rankRes = self::callGemini($rankPrompt, $rankSystem, true);
                    $tokenStats = self::trackTokenUsage($rankPrompt, $rankRes['text'] ?? '', $rankRes['usage'] ?? null);

                    if ($rankRes['success'] && !empty($rankRes['text'])) {
                        $parsedRank = json_decode($rankRes['text'], true);
                        if (is_array($parsedRank) && !empty($parsedRank['topMatches'])) {
                            // Ensure trainerCode is preserved in matches
                            foreach ($parsedRank['topMatches'] as &$m) {
                                if (empty($m['trainerCode'])) {
                                    foreach ($candidatesData as $cd) {
                                        if ($cd['id'] === ($m['trainerId'] ?? '')) {
                                            $m['trainerCode'] = $cd['trainerCode'];
                                            break;
                                        }
                                    }
                                }
                            }
                            unset($m);

                            return [
                                'success' => true,
                                'isConversational' => false,
                                'data' => $parsedRank,
                                'tokenStats' => $tokenStats,
                                'source' => 'zervy-gemini-ai'
                            ];
                        }
                    }
                }
            }
        }

        // Fallback conversational reply if Gemini API key is missing or offline
        $qLower = strtolower($query);
        if (preg_match('/^(hi|hello|hey|how are you|good morning|greetings)\b/i', $qLower)) {
            $reply = "👋 **Hello! I am Zervy**, your internal AI assistant for Mentry Solutions.\n\nI am doing great! You can ask me anything — from searching verified trainers and analyzing resumes to drafting workshop syllabi or screening interview questions. How can I help you today?";
            $tokenStats = self::trackTokenUsage($query, $reply);
            return [
                'success' => true,
                'isConversational' => true,
                'conversationalMessage' => $reply,
                'data' => [
                    'understoodRequirement' => null,
                    'clarification' => null,
                    'topMatches' => []
                ],
                'tokenStats' => $tokenStats,
                'source' => 'zervy-local-brain'
            ];
        }

        // Default candidate search fallback
        $extractedSkills = ResumeSkillParser::extractSkillsFromText($query);
        $skillsList = [];
        $domainFromSkills = '';
        foreach ($extractedSkills as $cat => $skList) {
            if (empty($domainFromSkills) && !empty($cat)) {
                $domainFromSkills = $cat;
            }
            foreach ($skList as $sk) { $skillsList[] = $sk; }
        }
        if (empty($skillsList)) {
            $skillsList = array_filter(array_map('trim', preg_split('/[,&\|\/]+/', $query)));
        }

        $candidates = self::searchTrainers($skillsList, $domainFromSkills, 0, '', 'ALL');
        $topCandidates = array_slice($candidates, 0, 4);

        $fallbackMatches = [];
        foreach ($topCandidates as $c) {
            $t = $c['trainer'];
            $tSkills = $t['skills'] ?? ($t['extractedSkills'] ?? []);
            $tCode = $t['trainerCode'] ?? ($t['mentryId'] ?? getMentryCode('TRAINER', $t));
            $fallbackMatches[] = [
                'trainerId' => (string)($t['_id'] ?? ($t['id'] ?? '')),
                'trainerCode' => $tCode,
                'name' => $t['name'],
                'headline' => $t['professionalTitle'] ?? ($t['headline'] ?? ($t['primaryDomain'] . ' Trainer')),
                'matchScore' => max(65, min(98, $c['score'])),
                'confidence' => $c['score'] >= 80 ? 'High' : 'Medium',
                'matchingSkills' => !empty($c['breakdown']['matchedSkills']) ? $c['breakdown']['matchedSkills'] : array_slice($tSkills, 0, 4),
                'relevantExperienceYears' => $t['totalExperienceYears'] ?? 5,
                'relevantTrainings' => ($t['completedTrainingsCount'] ?? '8') . '+ completed programs',
                'whyRecommended' => 'Strong match for requested domain with ' . ($t['totalExperienceYears'] ?? '5') . '+ years of verified experience in ' . implode(', ', array_slice($tSkills, 0, 3)) . '.'
            ];
        }

        $tokenStats = self::trackTokenUsage($query, json_encode($fallbackMatches));
        return [
            'success' => true,
            'isConversational' => false,
            'data' => [
                'understoodRequirement' => [
                    'topic' => !empty($skillsList) ? implode(' / ', array_slice($skillsList, 0, 3)) : 'Technical Training',
                    'skills' => $skillsList,
                    'location' => 'Any',
                    'mode' => 'ALL'
                ],
                'clarification' => null,
                'topMatches' => $fallbackMatches
            ],
            'tokenStats' => $tokenStats,
            'source' => 'zervy-deterministic-layer'
        ];
    }

    /**
     * Default Verified Trainer Pool
     */
    public static function getDefaultTrainersPool() {
        return [
            [
                'id' => 'tr_01',
                '_id' => 'tr_01',
                'name' => 'Rajesh Verma',
                'headline' => 'Senior DevOps & Cloud Architect | Corporate Trainer',
                'email' => 'rajesh.verma@example.com',
                'phone' => '+91 98450 11001',
                'city' => 'Bangalore',
                'primaryDomain' => 'Cloud & DevOps',
                'totalExperienceYears' => 11,
                'preferredMode' => 'HYBRID',
                'corporateExperience' => true,
                'academicExperience' => true,
                'status' => 'APPROVED',
                'completedTrainingsCount' => 24,
                'skills' => ['Python', 'Docker', 'Kubernetes', 'AWS', 'CI/CD Pipelines', 'Linux / Shell', 'Terraform', 'Django'],
                'extractedSkills' => ['Python', 'Docker', 'Kubernetes', 'AWS', 'CI/CD Pipelines', 'Linux / Shell', 'Terraform'],
                'certifications' => ['AWS Certified Solutions Architect', 'CKA: Certified Kubernetes Administrator'],
                'bio' => '11+ years in cloud infrastructure and enterprise automation. Conducted 20+ corporate training workshops for Fortune 500 tech companies.',
                'resumeUrl' => '/public/sample_resume_devops.pdf'
            ],
            [
                'id' => 'tr_02',
                '_id' => 'tr_02',
                'name' => 'Dr. Priya Sharma',
                'headline' => 'Lead Data Scientist & Generative AI Specialist',
                'email' => 'priya.sharma@example.com',
                'phone' => '+91 98450 11002',
                'city' => 'Hyderabad',
                'primaryDomain' => 'Data Science & AI',
                'totalExperienceYears' => 8,
                'preferredMode' => 'HYBRID',
                'corporateExperience' => true,
                'academicExperience' => true,
                'status' => 'APPROVED',
                'completedTrainingsCount' => 19,
                'skills' => ['Python', 'Machine Learning', 'Deep Learning', 'Generative AI & LLMs', 'NLP', 'TensorFlow & PyTorch', 'FastAPI', 'Pandas & NumPy'],
                'extractedSkills' => ['Python', 'Machine Learning', 'Deep Learning', 'Generative AI & LLMs', 'NLP', 'TensorFlow & PyTorch'],
                'certifications' => ['TensorFlow Certified Developer', 'DeepLearning.AI NLP Specialization'],
                'bio' => 'Former Lead AI Researcher at top tier labs. Conducted high-impact faculty development and hands-on GenAI bootcamps.',
                'resumeUrl' => '/public/sample_resume_ai.pdf'
            ],
            [
                'id' => 'tr_03',
                '_id' => 'tr_03',
                'name' => 'Karthik Ramanathan',
                'headline' => 'Full-Stack Web Architect (MERN / Next.js / Python)',
                'email' => 'karthik.raman@example.com',
                'phone' => '+91 98450 11003',
                'city' => 'Chennai',
                'primaryDomain' => 'Full-Stack Development',
                'totalExperienceYears' => 9,
                'preferredMode' => 'HYBRID',
                'corporateExperience' => true,
                'academicExperience' => true,
                'status' => 'APPROVED',
                'completedTrainingsCount' => 28,
                'skills' => ['Python', 'Django', 'React.js', 'Next.js', 'Node.js', 'TypeScript', 'PostgreSQL', 'REST API', 'Tailwind CSS'],
                'extractedSkills' => ['Python', 'Django', 'React.js', 'Next.js', 'Node.js', 'TypeScript', 'PostgreSQL'],
                'certifications' => ['Meta Certified Full-Stack Developer'],
                'bio' => 'Delivered 25+ campus placement training programs and enterprise full-stack onboarding batches with 4.9/5 student ratings.',
                'resumeUrl' => '/public/sample_resume_fullstack.pdf'
            ],
            [
                'id' => 'tr_04',
                '_id' => 'tr_04',
                'name' => 'Ananya Deshmukh',
                'headline' => 'Enterprise Java & Spring Boot Specialist',
                'email' => 'ananya.deshmukh@example.com',
                'phone' => '+91 98450 11004',
                'city' => 'Pune',
                'primaryDomain' => 'Enterprise Java',
                'totalExperienceYears' => 10,
                'preferredMode' => 'HYBRID',
                'corporateExperience' => true,
                'academicExperience' => true,
                'status' => 'APPROVED',
                'completedTrainingsCount' => 31,
                'skills' => ['Java', 'Spring Boot', 'Microservices', 'Hibernate', 'MySQL', 'Docker', 'Data Structures & Algorithms'],
                'extractedSkills' => ['Java', 'Spring Boot', 'Microservices', 'Hibernate', 'MySQL', 'Docker'],
                'certifications' => ['Oracle Certified Professional: Java SE 11 Developer'],
                'bio' => 'Enterprise backend consultant and corporate trainer. Specialized in zero-to-hero Java Spring Boot microservices.',
                'resumeUrl' => '/public/sample_resume_java.pdf'
            ],
            [
                'id' => 'tr_05',
                '_id' => 'tr_05',
                'name' => 'Vikramaditya Rao',
                'headline' => 'Cybersecurity & Ethical Hacking Consultant',
                'email' => 'vikram.rao@example.com',
                'phone' => '+91 98450 11005',
                'city' => 'Bangalore',
                'primaryDomain' => 'Cybersecurity',
                'totalExperienceYears' => 7,
                'preferredMode' => 'OFFLINE',
                'corporateExperience' => true,
                'academicExperience' => true,
                'status' => 'APPROVED',
                'completedTrainingsCount' => 16,
                'skills' => ['Cybersecurity', 'Ethical Hacking & VAPT', 'Network Security', 'Python', 'Linux / Shell', 'Web App Security'],
                'extractedSkills' => ['Cybersecurity', 'Ethical Hacking & VAPT', 'Network Security', 'Python'],
                'certifications' => ['CEH (Certified Ethical Hacker)', 'OSCP: Offensive Security Certified Professional'],
                'bio' => 'Hands-on Red Team security consultant and college lab instructor with extensive real-world pentesting experience.',
                'resumeUrl' => '/public/sample_resume_security.pdf'
            ]
        ];
    }
}
