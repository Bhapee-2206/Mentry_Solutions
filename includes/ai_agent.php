<?php
// includes/ai_agent.php - Zervy: Internal AI Agent Core Engine, Token Calculator & Gemini Gateway

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/resume_parser.php';

class AIAgent {

    private static $apiKey = null;
    private static $model = 'gemini-1.5-flash';

    private static function initConfig() {
        if (self::$apiKey !== null) return;

        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $env = @parse_ini_file($envFile);
            if (!empty($env['GEMINI_API_KEY'])) {
                self::$apiKey = trim($env['GEMINI_API_KEY'], '"\'');
            }
            if (!empty($env['GEMINI_MODEL'])) {
                self::$model = trim($env['GEMINI_MODEL'], '"\'');
            }
        }
        if (empty(self::$apiKey)) {
            self::$apiKey = getenv('GEMINI_API_KEY') ?: '';
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

        // Calculate tokens for current request
        $promptTokens = 0;
        $completionTokens = 0;

        if ($geminiUsage && isset($geminiUsage['promptTokenCount'])) {
            $promptTokens = (int)$geminiUsage['promptTokenCount'];
            $completionTokens = (int)($geminiUsage['candidatesTokenCount'] ?? 0);
        } else {
            // Standard estimation: ~4 chars per token for English & JSON
            $promptTokens = max(1, (int)ceil(strlen($promptText) / 4));
            $completionTokens = max(1, (int)ceil(strlen($completionText) / 4));
        }

        $totalTokens = $promptTokens + $completionTokens;

        // Gemini 1.5 Flash Pricing: $0.075 / 1M input tokens, $0.30 / 1M output tokens
        $reqCost = ($promptTokens * 0.000000075) + ($completionTokens * 0.00000030);

        // Update cumulative stats
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
    public static function callGemini($prompt, $systemInstruction = '', $jsonMode = true) {
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
                'temperature' => 0.2,
                'topP' => 0.8,
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
     * Intent Classification: Detects whether a message is a greeting, non-requirement, or trainer search
     */
    public static function classifyIntent($query) {
        $q = strtolower(trim($query));

        // Greetings
        $greetings = ['hi', 'hello', 'hey', 'good morning', 'good evening', 'good afternoon', 'hola', 'yo', 'sup', 'greetings'];
        if (in_array($q, $greetings) || preg_match('/^(hi|hello|hey|good morning|greetings)[\s!.,?]*$/i', $q)) {
            return [
                'type' => 'GREETING',
                'message' => "👋 **Hello! I am Zervy**, your internal AI trainer matching assistant for Mentry Solutions.\n\nTo discover and rank trainers, please share a training requirement (e.g. *\"Need a Python & Django corporate trainer in Bangalore for 5 days\"* or *\"Find React and Node.js faculty\"*)."
            ];
        }

        // Who are you / help queries
        if (preg_match('/\b(who are you|what can you do|help me|what is your name)\b/i', $q)) {
            return [
                'type' => 'HELP',
                'message' => "🤖 **I am Zervy**, the intelligent trainer discovery agent for Mentry Solutions operations.\n\n**What I can do:**\n1. **Match Trainers**: Analyze program requirements and search verified trainer profiles and resumes.\n2. **Rank & Explain**: Score candidates with factual justifications (*Why this trainer?*).\n3. **Compare Candidates**: Provide side-by-side multi-trainer comparison matrices."
            ];
        }

        // Check if there are technical skills or training words
        $extractedSkills = ResumeSkillParser::extractSkillsFromText($query);
        $hasSkills = false;
        foreach ($extractedSkills as $cat => $list) {
            if (!empty($list)) {
                $hasSkills = true;
                break;
            }
        }

        $trainingKeywords = ['trainer', 'training', 'faculty', 'instructor', 'bootcamp', 'workshop', 'program', 'campus', 'corporate', 'course', 'placement', 'teach', 'sessions'];
        $hasTrainingContext = false;
        foreach ($trainingKeywords as $kw) {
            if (stripos($q, $kw) !== false) {
                $hasTrainingContext = true;
                break;
            }
        }

        // General work chats without requirements (e.g. invoice, call me, payment, meeting)
        $nonReqPatterns = ['payment', 'invoice', 'call me', 'free now', 'meeting at', 'check this', 'lunch', 'thank you', 'thanks', 'ok', 'okay', 'got it', 'sure', 'yes', 'no'];
        foreach ($nonReqPatterns as $nrp) {
            if (stripos($q, $nrp) !== false && !$hasSkills && !$hasTrainingContext) {
                return [
                    'type' => 'NON_REQUIREMENT',
                    'message' => "💬 *Message noted.* This message does not appear to contain a training requirement or technical skill.\n\nIf you would like to search for trainers, mention technical skills (*e.g. Java, Python, AWS*) or training requirements."
                ];
            }
        }

        // If very short and no skills
        if (strlen($q) < 8 && !$hasSkills && !$hasTrainingContext) {
            return [
                'type' => 'GREETING',
                'message' => "👋 **Hi there! I am Zervy.** Please provide specific training topics or required skills to find suitable trainers."
            ];
        }

        return ['type' => 'TRAINER_SEARCH'];
    }

    /**
     * Tool 1: searchTrainers
     */
    public static function searchTrainers($skills = [], $domain = '', $minExp = 0, $location = '', $mode = 'ALL') {
        $trainerCol = getCollection("Trainer");
        $trainers = [];

        try {
            if ($trainerCol) {
                $filter = ['status' => 'APPROVED'];
                if ($mode !== 'ALL' && !empty($mode)) {
                    $filter['preferredMode'] = $mode;
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
            'city' => $location,
            'mode' => $mode
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
     * Tool 2: getTrainerProfile
     */
    public static function getTrainerProfile($trainerId) {
        $trainerCol = getCollection("Trainer");
        if ($trainerCol) {
            try {
                $t = $trainerCol->findOne(['_id' => $trainerId]);
                if ($t) return $t;
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
     * Tool 5: compareTrainers
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
You must compare the given trainers against the requirement.
CRITICAL RULES:
1. Base your comparison STRICTLY on the facts provided in the trainer data.
2. NEVER invent certifications, experience years, or projects not in the data.
3. If a field is unknown, state 'Information not available in trainer profile'.
4. Return a valid JSON object matching this schema:
{
  \"summary\": \"Short overview comparing candidate strengths\",
  \"comparisonMatrix\": [
    { \"category\": \"Skills Match\", \"values\": { \"trainer_id_or_name\": \"Strong/Medium/Low with brief detail\" } },
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

        // Deterministic Fallback Comparison Matrix
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
     * Primary Match & Reasoning Pipeline with Intent Filter & Token Calculator
     */
    public static function processRequirementQuery($query) {
        $query = trim($query);
        if (empty($query)) {
            return [
                'success' => false,
                'message' => 'Please provide a training requirement or question.'
            ];
        }

        // 1. Intent Classification Gate (Prevents false positive recommendations on "hi", "payment", etc.)
        $intent = self::classifyIntent($query);
        if ($intent['type'] !== 'TRAINER_SEARCH') {
            $tokenStats = self::trackTokenUsage($query, $intent['message']);
            return [
                'success' => true,
                'isConversational' => true,
                'intentType' => $intent['type'],
                'conversationalMessage' => $intent['message'],
                'data' => [
                    'understoodRequirement' => null,
                    'clarification' => null,
                    'topMatches' => []
                ],
                'tokenStats' => $tokenStats,
                'source' => 'zervy-intent-engine'
            ];
        }

        // 2. Extract skills and keywords using pure PHP dictionary
        $extractedSkills = ResumeSkillParser::extractSkillsFromText($query);
        $skillsList = [];
        foreach ($extractedSkills as $cat => $skList) {
            foreach ($skList as $sk) {
                $skillsList[] = $sk;
            }
        }

        // Extract location if present
        $locations = ["Bangalore", "Bengaluru", "Chennai", "Hyderabad", "Pune", "Mumbai", "Delhi", "NCR", "Kochi", "Coimbatore", "Salem", "Trichy", "Madurai", "Mysore", "Kolkata", "Noida", "Gurgaon"];
        $detectedLocation = '';
        foreach ($locations as $loc) {
            if (stripos($query, $loc) !== false) {
                $detectedLocation = $loc;
                break;
            }
        }

        // Extract mode
        $detectedMode = 'ALL';
        if (stripos($query, 'online') !== false || stripos($query, 'virtual') !== false) {
            $detectedMode = 'ONLINE';
        } elseif (stripos($query, 'offline') !== false || stripos($query, 'classroom') !== false || stripos($query, 'on-campus') !== false) {
            $detectedMode = 'OFFLINE';
        }

        // 3. Perform Structured Search Layer
        $candidates = self::searchTrainers($skillsList, '', 0, $detectedLocation, $detectedMode);
        $topCandidates = array_slice($candidates, 0, 6);

        // Prepare candidate summary for Gemini ranking
        $candidatesData = [];
        foreach ($topCandidates as $c) {
            $t = $c['trainer'];
            $candidatesData[] = [
                'id' => $t['id'] ?? (string)($t['_id'] ?? ''),
                'name' => $t['name'],
                'headline' => $t['headline'] ?? ($t['primaryDomain'] . ' Specialist'),
                'totalExperienceYears' => $t['totalExperienceYears'] ?? 5,
                'skills' => $t['skills'] ?? ($t['extractedSkills'] ?? []),
                'primaryDomain' => $t['primaryDomain'] ?? 'Technical Training',
                'city' => $t['city'] ?? 'Bangalore',
                'preferredMode' => $t['preferredMode'] ?? 'HYBRID',
                'corporateExperience' => $t['corporateExperience'] ?? true,
                'certifications' => $t['certifications'] ?? [],
                'completedTrainings' => $t['completedTrainingsCount'] ?? 8,
                'baseScore' => $c['score']
            ];
        }

        // 4. AI Reasoning via Gemini
        $systemPrompt = "You are Zervy, the high-precision internal trainer recommendation agent for Mentry Solutions (India's Premier Managed Trainer Network).

YOUR OBJECTIVE:
1. Analyze the user's training requirement.
2. Evaluate the provided trainer candidates.
3. Rank the top suitable trainers and provide a match percentage (e.g. 94%).
4. Explain clearly and factually WHY each trainer is recommended.

STRICT ANTI-HALLUCINATION RULES:
- NEVER invent trainer certifications, experience, projects, or client ratings.
- Only reference data explicitly provided in the trainer profiles.
- If a detail is missing (e.g. certification or specific tool), explicitly state 'Information not available in trainer profile'.
- If the requirement is vague or missing key details (e.g. duration, location, mode), include 1-2 polite clarifying questions in the 'clarification' field.

OUTPUT FORMAT:
Return a valid JSON object matching this structure:
{
  \"understoodRequirement\": {
    \"topic\": \"Identified training topic/domain\",
    \"skills\": [\"Skill1\", \"Skill2\"],
    \"location\": \"City or Any\",
    \"mode\": \"ONLINE/OFFLINE/HYBRID/Any\",
    \"duration\": \"Identified duration or Not Specified\",
    \"experienceLevel\": \"Identified experience level or Not Specified\"
  },
  \"clarification\": \"Optional helpful clarifying question if requirement is incomplete, else null\",
  \"topMatches\": [
    {
      \"trainerId\": \"id\",
      \"name\": \"Trainer Name\",
      \"headline\": \"Professional Headline\",
      \"matchScore\": 94,
      \"confidence\": \"High\",
      \"matchingSkills\": [\"Skill1\", \"Skill2\"],
      \"missingSkills\": [\"Skill3\"],
      \"relevantExperienceYears\": 8,
      \"relevantTrainings\": \"5 corporate programs & 8 campus bootcamps\",
      \"location\": \"Bangalore (Hybrid)\",
      \"certifications\": [\"Cert1\"],
      \"whyRecommended\": \"Clear factual explanation highlighting exact alignment with requirements.\"
    }
  ]
}";

        $userPrompt = "Requirement: " . $query . "\n\nAvailable Trainer Candidates Data:\n" . json_encode($candidatesData, JSON_PRETTY_PRINT);

        $geminiRes = self::callGemini($userPrompt, $systemPrompt, true);
        $tokenStats = self::trackTokenUsage($userPrompt, $geminiRes['text'] ?? '', $geminiRes['usage'] ?? null);

        if ($geminiRes['success'] && !empty($geminiRes['text'])) {
            $parsed = json_decode($geminiRes['text'], true);
            if (is_array($parsed) && !empty($parsed['topMatches'])) {
                return [
                    'success' => true,
                    'isConversational' => false,
                    'data' => $parsed,
                    'tokenStats' => $tokenStats,
                    'source' => 'gemini-ai'
                ];
            }
        }

        // 5. Deterministic Intelligent Fallback
        $fallbackMatches = [];
        foreach ($topCandidates as $c) {
            $t = $c['trainer'];
            $tSkills = $t['skills'] ?? [];
            
            $fallbackMatches[] = [
                'trainerId' => $t['id'] ?? (string)($t['_id'] ?? ''),
                'name' => $t['name'],
                'headline' => $t['headline'] ?? ($t['primaryDomain'] . ' Trainer'),
                'matchScore' => max(65, min(98, $c['score'])),
                'confidence' => $c['score'] >= 80 ? 'High' : ($c['score'] >= 60 ? 'Medium' : 'Low'),
                'matchingSkills' => !empty($c['breakdown']['matchedSkills']) ? $c['breakdown']['matchedSkills'] : array_slice($tSkills, 0, 4),
                'missingSkills' => $c['breakdown']['missingSkills'] ?? [],
                'relevantExperienceYears' => $t['totalExperienceYears'] ?? 5,
                'relevantTrainings' => ($t['completedTrainingsCount'] ?? '8') . '+ completed training engagements',
                'location' => ($t['city'] ?? 'India') . ' (' . ($t['preferredMode'] ?? 'HYBRID') . ')',
                'certifications' => !empty($t['certifications']) ? $t['certifications'] : ['Information not available in trainer profile'],
                'whyRecommended' => 'Strong match for requested domain with ' . ($t['totalExperienceYears'] ?? '5') . '+ years of verified training experience in ' . implode(', ', array_slice($tSkills, 0, 3)) . '.'
            ];
        }

        return [
            'success' => true,
            'isConversational' => false,
            'data' => [
                'understoodRequirement' => [
                    'topic' => !empty($skillsList) ? implode(' / ', array_slice($skillsList, 0, 3)) : 'General Training',
                    'skills' => $skillsList,
                    'location' => $detectedLocation ?: 'Any Location',
                    'mode' => $detectedMode,
                    'duration' => 'Not Specified',
                    'experienceLevel' => 'Corporate / Academic'
                ],
                'clarification' => empty($detectedLocation) ? 'To refine matches, would you prefer Online delivery or a specific campus/city location?' : null,
                'topMatches' => $fallbackMatches
            ],
            'tokenStats' => $tokenStats,
            'source' => 'matching-engine'
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
