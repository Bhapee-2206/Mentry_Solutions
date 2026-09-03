<?php
// includes/matching_engine.php - Intelligent Trainer Matching & Recommendation Engine
require_once __DIR__ . '/db.php';

class MatchingEngine {

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
            if ($oppDomain === $trainerDomain) {
                $details['domainScore'] = 35;
                $details['isDomainMatch'] = true;
            } elseif (
                (strpos($oppDomain, 'program') !== false && strpos($trainerDomain, 'program') !== false) ||
                (strpos($oppDomain, 'data') !== false && strpos($trainerDomain, 'ai') !== false) ||
                (strpos($oppDomain, 'cloud') !== false && strpos($trainerDomain, 'devops') !== false) ||
                (strpos($oppDomain, 'vlsi') !== false && strpos($trainerDomain, 'embed') !== false)
            ) {
                $details['domainScore'] = 28;
                $details['isDomainMatch'] = true;
            } else {
                $details['domainScore'] = 8;
            }
        } else {
            $details['domainScore'] = 15;
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

        // Gather all trainer skills (from Skill collection + extractedSkills array)
        $allTrainerSkills = [];
        foreach ($trainerSkills as $ts) {
            $name = is_array($ts) ? ($ts['name'] ?? '') : ($ts->name ?? '');
            if (!empty($name)) $allTrainerSkills[] = strtolower(trim($name));
        }
        if (!empty($trainer['extractedSkills']) && is_array($trainer['extractedSkills'])) {
            foreach ($trainer['extractedSkills'] as $es) {
                $allTrainerSkills[] = strtolower(trim($es));
            }
        }
        $allTrainerSkills = array_unique($allTrainerSkills);

        if (empty($oppSkills)) {
            // Default skills score if opportunity didn't specify
            $details['skillsScore'] = $details['isDomainMatch'] ? 30 : 15;
        } else {
            $matchedCount = 0;
            foreach ($oppSkills as $reqSkill) {
                $reqLower = strtolower($reqSkill);
                $matched = false;
                foreach ($allTrainerSkills as $tsLower) {
                    if ($reqLower === $tsLower || strpos($tsLower, $reqLower) !== false || strpos($reqLower, $tsLower) !== false) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    $matchedCount++;
                    $details['matchedSkills'][] = $reqSkill;
                } else {
                    $details['missingSkills'][] = $reqSkill;
                }
            }

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
