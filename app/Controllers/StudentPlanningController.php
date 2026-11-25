<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Request;
use UniGuide\App\Middleware\AuthMiddleware;
use UniGuide\App\Repositories\StudentProfileRepository;

final class StudentPlanningController
{
    private PDO $database;
    private StudentProfileRepository $profiles;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->profiles = new StudentProfileRepository($database);
    }

    public function handle(): void
    {
        $userId = AuthMiddleware::requireRole(['student'], $this->database);
        $action = $_GET['action'] ?? '';
        if (!is_string($action)) {
            ApiResponse::error('invalid_action', 'The planning action is invalid.', 422);
        }
        if ($action === 'eligibility') {
            Request::requireMethod('GET');
            $this->eligibility($userId, $this->requiredProgramId());
        }
        if ($action === 'budget') {
            Request::requireMethod('GET');
            $this->budget($userId, $this->requiredProgramId());
        }
        if ($action === 'recommendations') {
            Request::requireMethod('GET');
            $this->recommendations($userId);
        }

        ApiResponse::error('invalid_action', 'Choose eligibility, budget, or recommendations.', 422);
    }

    private function eligibility(int $userId, int $programId): void
    {
        $program = $this->publicProgram($programId);
        if ($program === null) {
            ApiResponse::error('not_found', 'The verified program was not found.', 404);
        }
        $profile = $this->profiles->findByUserId($userId);
        if ($profile === null) {
            ApiResponse::success([
                'program' => $program,
                'status' => 'profile_incomplete',
                'reasons' => ['Add your SSC/HSC results and groups to check available criteria.'],
                'admission_test_required' => null,
            ]);
        }
        $requirements = $this->verifiedRequirements($programId);
        if ($requirements === []) {
            ApiResponse::success([
                'program' => $program,
                'status' => 'criteria_unavailable',
                'reasons' => ['No verified admission requirements are currently available.'],
                'admission_test_required' => null,
            ]);
        }

        $possible = false;
        $unknown = false;
        $reasons = [];
        $testRequired = false;
        foreach ($requirements as $requirement) {
            $failures = [];
            $missing = [];
            $testRequired = $testRequired || (bool) $requirement['admission_test_required'];
            $this->checkGpa($requirement, $profile, 'minimum_ssc_gpa', 'ssc_gpa', 'SSC GPA', $failures, $missing);
            $this->checkGpa($requirement, $profile, 'minimum_hsc_gpa', 'hsc_gpa', 'HSC GPA', $failures, $missing);
            $minimumCombined = $requirement['minimum_combined_gpa'];
            if ($minimumCombined === null) {
                $missing[] = 'combined GPA threshold';
            } elseif ($profile['ssc_gpa'] === null || $profile['hsc_gpa'] === null) {
                $missing[] = 'both SSC and HSC GPA';
            } elseif ((float) $profile['ssc_gpa'] + (float) $profile['hsc_gpa'] < (float) $minimumCombined) {
                $failures[] = 'combined GPA below the published threshold';
            }
            $this->checkGroup($requirement['accepted_ssc_groups'], $profile['ssc_group'], 'SSC group', $failures, $missing);
            $this->checkGroup($requirement['accepted_hsc_groups'], $profile['hsc_group'], 'HSC group', $failures, $missing);
            if (!empty($requirement['subject_prerequisites'])) {
                $missing[] = 'manual review of subject prerequisites';
            }
            if ($requirement['additional_requirements'] !== null && trim($requirement['additional_requirements']) !== '') {
                $missing[] = 'manual review of additional requirements';
            }

            if ($failures === [] && $missing === []) {
                $possible = true;
                $reasons[] = 'Your profile matches one verified set of published criteria; confirm details with the official admissions office.';
                break;
            }
            if ($failures === []) {
                $unknown = true;
                $reasons = array_merge($reasons, $missing);
            } else {
                $reasons = array_merge($reasons, $failures);
            }
        }
        $status = $possible ? 'potentially_eligible' : ($unknown ? 'cannot_determine' : 'not_eligible');
        ApiResponse::success([
            'program' => $program,
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
            'admission_test_required' => $testRequired,
            'notice' => 'This is a screening aid, not an admission decision. Always verify current requirements with the university.',
        ]);
    }

    private function budget(int $userId, int $programId): void
    {
        $program = $this->publicProgram($programId);
        if ($program === null) {
            ApiResponse::error('not_found', 'The verified program was not found.', 404);
        }
        $statement = $this->database->prepare(
            'SELECT f.id, f.fee_type, f.label, f.amount, f.currency, f.effective_from, f.effective_until
             FROM program_fees f
             INNER JOIN sources s ON s.id = f.source_id AND s.verification_status = :source_status
             WHERE f.program_id = :program_id AND f.verification_status = :status
                   AND (f.effective_from IS NULL OR f.effective_from <= CURRENT_DATE())
                   AND (f.effective_until IS NULL OR f.effective_until >= CURRENT_DATE())
             ORDER BY f.fee_type ASC, f.id ASC'
        );
        $statement->execute([
            'source_status' => 'verified',
            'program_id' => $programId,
            'status' => 'verified',
        ]);
        $fees = $statement->fetchAll();
        $profile = $this->profiles->findByUserId($userId);
        $currency = $fees === [] ? null : $fees[0]['currency'];
        $sameCurrency = $currency !== null;
        $total = 0.0;
        foreach ($fees as $fee) {
            if ($fee['currency'] !== $currency) {
                $sameCurrency = false;
            }
            $total += (float) $fee['amount'];
        }
        $knownTotal = $fees !== [] && $sameCurrency;
        ApiResponse::success([
            'program' => $program,
            'components' => $fees,
            'reported_fee_total' => $knownTotal ? number_format($total, 2, '.', '') : null,
            'currency' => $knownTotal ? $currency : null,
            'maximum_budget' => $profile['maximum_budget'] ?? null,
            'reported_fee_components_within_budget' => $knownTotal && $profile !== null && $profile['maximum_budget'] !== null
                ? $total <= (float) $profile['maximum_budget']
                : null,
            'is_complete_program_cost' => false,
            'notice' => 'This is the sum of currently effective verified fee entries only. Missing fees, living costs, and waivers may change the actual cost.',
        ]);
    }

    private function recommendations(int $userId): void
    {
        $profile = $this->profiles->findByUserId($userId);
        if ($profile === null || ($profile['preferred_subject'] === null && $profile['preferred_location'] === null)) {
            ApiResponse::success([
                'items' => [],
                'notice' => 'Set a preferred subject or location in your profile to receive matching program suggestions.',
            ]);
        }
        $statement = $this->database->query(
            "SELECT p.id, p.university_id, p.name, p.field_of_study, p.degree_level,
                    u.slug AS university_slug, u.name AS university_name, u.city, u.campus_type,
                    ps.title AS program_source_title, ps.url AS program_source_url,
                    us.title AS university_source_title, us.url AS university_source_url
             FROM programs p
             INNER JOIN sources ps ON ps.id = p.source_id AND ps.verification_status = 'verified'
             INNER JOIN universities u ON u.id = p.university_id
                 AND u.status = 'published' AND u.verification_status = 'verified'
             INNER JOIN sources us ON us.id = u.source_id AND us.verification_status = 'verified'
             WHERE p.status = 'published' AND p.verification_status = 'verified'
             ORDER BY p.name ASC, p.id ASC
             LIMIT 100"
        );
        $items = [];
        foreach ($statement->fetchAll() as $program) {
            $reasons = [];
            if (
                $profile['preferred_subject'] !== null
                && stripos((string) $program['field_of_study'] . ' ' . $program['name'], $profile['preferred_subject']) !== false
            ) {
                $reasons[] = 'Matches your preferred subject';
            }
            if (
                $profile['preferred_location'] !== null
                && strcasecmp((string) $program['city'], $profile['preferred_location']) === 0
            ) {
                $reasons[] = 'Matches your preferred location';
            }
            if ($reasons !== []) {
                $program['match_reasons'] = $reasons;
                $items[] = $program;
            }
        }
        ApiResponse::success([
            'items' => $items,
            'notice' => 'Suggestions are matched to your subject and location preferences. They do not imply eligibility or affordability.',
        ]);
    }

    private function publicProgram(int $programId): ?array
    {
        $statement = $this->database->prepare(
            "SELECT p.id, p.name, p.degree_level, p.field_of_study,
                    u.id AS university_id, u.name AS university_name, u.city,
                    ps.title AS program_source_title, ps.url AS program_source_url,
                    us.title AS university_source_title, us.url AS university_source_url
             FROM programs p
             INNER JOIN sources ps ON ps.id = p.source_id AND ps.verification_status = 'verified'
             INNER JOIN universities u ON u.id = p.university_id
                 AND u.status = 'published' AND u.verification_status = 'verified'
             INNER JOIN sources us ON us.id = u.source_id AND us.verification_status = 'verified'
             WHERE p.id = :id AND p.status = 'published' AND p.verification_status = 'verified'
             LIMIT 1"
        );
        $statement->execute(['id' => $programId]);
        $program = $statement->fetch();

        return $program === false ? null : $program;
    }

    private function verifiedRequirements(int $programId): array
    {
        $statement = $this->database->prepare(
            "SELECT r.minimum_ssc_gpa, r.minimum_hsc_gpa, r.minimum_combined_gpa,
                    r.accepted_ssc_groups, r.accepted_hsc_groups, r.subject_prerequisites,
                    r.admission_test_required, r.additional_requirements
             FROM admission_requirements r
             INNER JOIN sources s ON s.id = r.source_id AND s.verification_status = 'verified'
             WHERE r.program_id = :program_id AND r.verification_status = 'verified'"
        );
        $statement->execute(['program_id' => $programId]);
        $requirements = $statement->fetchAll();
        foreach ($requirements as &$requirement) {
            foreach (['accepted_ssc_groups', 'accepted_hsc_groups', 'subject_prerequisites'] as $field) {
                if (is_string($requirement[$field])) {
                    $requirement[$field] = json_decode($requirement[$field], true, 512, JSON_THROW_ON_ERROR);
                }
            }
        }
        unset($requirement);

        return $requirements;
    }

    private function checkGpa(
        array $requirement,
        array $profile,
        string $criterion,
        string $studentField,
        string $label,
        array &$failures,
        array &$missing
    ): void {
        if ($requirement[$criterion] === null) {
            $missing[] = $label . ' threshold';
        } elseif ($profile[$studentField] === null) {
            $missing[] = 'your ' . $label;
        } elseif ((float) $profile[$studentField] < (float) $requirement[$criterion]) {
            $failures[] = $label . ' below the published threshold';
        }
    }

    private function checkGroup($accepted, ?string $studentGroup, string $label, array &$failures, array &$missing): void
    {
        if (!is_array($accepted) || $accepted === []) {
            $missing[] = $label . ' criteria';
        } elseif ($studentGroup === null) {
            $missing[] = 'your ' . $label;
        } elseif (!in_array($studentGroup, $accepted, true)) {
            $failures[] = $label . ' is not in the accepted groups';
        }
    }

    private function requiredProgramId(): int
    {
        $value = $_GET['program_id'] ?? null;
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            ApiResponse::error('invalid_parameter', 'A valid program_id is required.', 422);
        }

        return (int) $value;
    }
}
