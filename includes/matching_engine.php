<?php
// includes/matching_engine.php - Intelligent Trainer Matching & Recommendation Engine
require_once __DIR__ . '/db.php';

class MatchingEngine {

    /**
     * Determine whether two skill strings match semantically or by tokens
     * Handles ordering differences ('PyTorch & TensorFlow' vs 'TensorFlow & PyTorch'),
     * sub-skills ('PyTorch' vs 'PyTorch & TensorFlow'), and symbols/delimiters.
     */
    public static function isSkillMatch($skillA, $skillB) {
        $a = strtolower(trim((string)$skillA));
        $b = strtolower(trim((string)$skillB));

        if (empty($a) || empty($b)) return false;
        if ($a === $b) return true;

        // Direct substring check
        if (strpos($a, $b) !== false || strpos($b, $a) !== false) return true;

        // Tokenize by removing special punctuation and stopwords
        $stopWords = ['and', 'or', 'the', 'with', 'in', 'of', 'for', 'to', '&', '/', '+', ',', '-', '_', '(', ')'];
        
        $tokensA = array_filter(preg_split('/[\s\&\/\,\+\-\_\(\)]+/', $a), function($t) use ($stopWords) {
            return strlen($t) >= 2 && !in_array($t, $stopWords);
        });
        $tokensB = array_filter(preg_split('/[\s\&\/\,\+\-\_\(\)]+/', $b), function($t) use ($stopWords) {
            return strlen($t) >= 2 && !in_array($t, $stopWords);
        });

        if (empty($tokensA) || empty($tokensB)) return false;

        // Check if sorted unique tokens match exactly (e.g. 'pytorch', 'tensorflow' in any order)
        $uniqueA = array_values(array_unique($tokensA));
        $uniqueB = array_values(array_unique($tokensB));
        sort($uniqueA);
        sort($uniqueB);
        if ($uniqueA === $uniqueB) return true;

        // Check if all tokens of one are inside the other
        // e.g. User requested 'PyTorch' -> tokens ['pytorch'], candidate has ['pytorch', 'tensorflow']
        $intersect = array_intersect($uniqueA, $uniqueB);
        if (count($intersect) === count($uniqueA) || count($intersect) === count($uniqueB)) {
            return true;
        }

        // Substring matches between individual tokens (e.g. 'react' matches 'reactjs' or 'react.js')
        foreach ($uniqueA as $ta) {
            foreach ($uniqueB as $tb) {
                if ($ta === $tb || (strlen($ta) >= 3 && strlen($tb) >= 3 && (strpos($ta, $tb) !== false || strpos($tb, $ta) !== false))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Compute match breakdown and score between an Opportunity and a Trainer
     */
    public static function evaluateMatch($opp, $trainer, $trainerSkills = []) {
        $score = 0;
        $details = [
            'domainScore' => 0,
            'skillsScore' => 0,
            'experienceScore' => 0,
            'logisticsScore' => 0,
            'matchedSkills' => [],
            'missingSkills' => [],
            'isDomainMatch' => false
        ];

        $oppDomain = strtolower(trim($opp['domain'] ?? ''));
        $trainerDomain = strtolower(trim($trainer['primaryDomain'] ?? ''));

        // 1. Domain Match (35 pts max)
        if (!empty($oppDomain) && !empty($trainerDomain)) {
            if ($oppDomain === $trainerDomain || strpos($oppDomain, $trainerDomain) !== false || strpos($trainerDomain, $oppDomain) !== false) {
                $details['domainScore'] = 35;
                $details['isDomainMatch'] = true;
            } elseif (
                (strpos($oppDomain, 'program') !== false && strpos($trainerDomain, 'program') !== false) ||
                ((strpos($oppDomain, 'data') !== false || strpos($oppDomain, 'ai') !== false || strpos($oppDomain, 'deep') !== false || strpos($oppDomain, 'machine') !== false || strpos($oppDomain, 'ml') !== false) &&
                 (strpos($trainerDomain, 'data') !== false || strpos($trainerDomain, 'ai') !== false || strpos($trainerDomain, 'deep') !== false || strpos($trainerDomain, 'machine') !== false || strpos($trainerDomain, 'ml') !== false)) ||
                ((strpos($oppDomain, 'cloud') !== false || strpos($oppDomain, 'devops') !== false) &&
                 (strpos($trainerDomain, 'cloud') !== false || strpos($trainerDomain, 'devops') !== false)) ||
                ((strpos($oppDomain, 'web') !== false || strpos($oppDomain, 'full') !== false) &&
                 (strpos($trainerDomain, 'web') !== false || strpos($trainerDomain, 'full') !== false)) ||
                (strpos($oppDomain, 'vlsi') !== false || strpos($oppDomain, 'embed') !== false)
            ) {
                $details['domainScore'] = 30;
                $details['isDomainMatch'] = true;
            } else {
                $details['domainScore'] = 8;
            }
        } else {
            $details['domainScore'] = 20;
        }

        // 2. Skills Match (40 pts max)
        $oppSkills = [];
        if (!empty($opp['skillsRequired'])) {
            if (is_array($opp['skillsRequired'])) {
                $oppSkills = $opp['skillsRequired'];
            } elseif (is_string($opp['skillsRequired'])) {
                $decoded = json_decode($opp['skillsRequired'], true);
                $oppSkills = is_array($decoded) ? $decoded : explode(',', $opp['skillsRequired']);
            }
        }
        $oppSkills = array_filter(array_map('trim', $oppSkills));

        // Gather all trainer skills (from Skill collection, trainer.skills, and extractedSkills array)
        $allTrainerSkills = [];
        if (is_array($trainerSkills)) {
            foreach ($trainerSkills as $ts) {
                $val = is_string($ts) ? $ts : (is_array($ts) ? ($ts['name'] ?? '') : ($ts->name ?? ''));
                if (!empty($val)) $allTrainerSkills[] = $val;
            }
        }
        if (!empty($trainer['skills']) && is_array($trainer['skills'])) {
            foreach ($trainer['skills'] as $s) {
                $val = is_string($s) ? $s : (is_array($s) ? ($s['name'] ?? '') : ($s->name ?? ''));
                if (!empty($val)) $allTrainerSkills[] = $val;
            }
        }
        if (!empty($trainer['extractedSkills']) && is_array($trainer['extractedSkills'])) {
            foreach ($trainer['extractedSkills'] as $es) {
                if (is_string($es) && !empty($es)) $allTrainerSkills[] = $es;
            }
        }
        $allTrainerSkills = array_values(array_unique(array_filter($allTrainerSkills)));

        if (empty($oppSkills)) {
            // Default skills score if opportunity didn't specify
            $details['skillsScore'] = $details['isDomainMatch'] ? 30 : 15;
        } else {
            $matchedCount = 0;
            foreach ($oppSkills as $reqSkill) {
                $matched = false;
                $matchedTrainerSkillName = '';
                foreach ($allTrainerSkills as $ts) {
                    if (self::isSkillMatch($reqSkill, $ts)) {
                        $matched = true;
                        $matchedTrainerSkillName = $ts;
                        break;
                    }
                }

                if ($matched) {
                    $matchedCount++;
                    $details['matchedSkills'][] = !empty($matchedTrainerSkillName) ? $matchedTrainerSkillName : $reqSkill;
                } else {
                    $details['missingSkills'][] = $reqSkill;
                }
            }

            $details['matchedSkills'] = array_values(array_unique($details['matchedSkills']));
            $details['missingSkills'] = array_values(array_unique($details['missingSkills']));

            $skillRatio = $matchedCount / count($oppSkills);
            $details['skillsScore'] = round($skillRatio * 40);
        }

        // 3. Experience Match (15 pts max)
        $minExp = (int)($opp['minExperienceYears'] ?? 3);
        $trainerExp = (int)($trainer['totalExperienceYears'] ?? 0);

        if ($trainerExp >= $minExp) {
            $details['experienceScore'] = 15;
        } elseif ($trainerExp >= ($minExp - 1)) {
            $details['experienceScore'] = 10;
        } else {
            $details['experienceScore'] = 5;
        }

        // 4. Logistics & Availability Match (10 pts max)
        $availStatus = $trainer['availabilityStatus'] ?? 'AVAILABLE_NOW';
        if ($availStatus === 'AVAILABLE_NOW') {
            $details['logisticsScore'] += 6;
        } elseif ($availStatus === 'FREE_FROM_DATE') {
            $details['logisticsScore'] += 4;
        }

        $oppCity = strtolower(trim($opp['city'] ?? ''));
        $trainerCity = strtolower(trim($trainer['currentCity'] ?? ''));
        $travelPref = $trainer['travelPreference'] ?? 'PAN_INDIA';
        $oppMode = $opp['mode'] ?? 'OFFLINE';

        if ($oppMode === 'ONLINE' || $travelPref === 'PAN_INDIA' || (!empty($oppCity) && $oppCity === $trainerCity)) {
            $details['logisticsScore'] += 4;
        } else {
            $details['logisticsScore'] += 1;
        }

        $totalScore = $details['domainScore'] + $details['skillsScore'] + $details['experienceScore'] + $details['logisticsScore'];
        $details['score'] = min(98, max(20, $totalScore));

        return $details;
    }

    /**
     * Find top ranked candidates for a given opportunity
     */
    public static function getRankedCandidatesForOpportunity($opp, $limit = 10) {
        $trainerCol = getCollection("Trainer");
        $userCol = getCollection("User");
        $skillCol = getCollection("Skill");

        if (!$trainerCol || !$userCol) return [];

        $trainers = $trainerCol->find(['status' => 'APPROVED'])->toArray();
        $ranked = [];

        foreach ($trainers as $tr) {
            $trainerId = (string)$tr['_id'];
            $userId = (string)($tr['userId'] ?? '');

            // Get trainer user info
            $u = null;
            if (!empty($userId)) {
                try {
                    $u = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
                } catch (Exception $e) {}
            }

            // Get trainer skills
            $skills = $skillCol ? $skillCol->find(['trainerId' => $trainerId])->toArray() : [];

            $match = self::evaluateMatch($opp, $tr, $skills);

            $ranked[] = [
                'trainer' => $tr,
                'user' => $u,
                'match' => $match,
                'score' => $match['score']
            ];
        }

        // Sort descending by score
        usort($ranked, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($ranked, 0, $limit);
    }
}
