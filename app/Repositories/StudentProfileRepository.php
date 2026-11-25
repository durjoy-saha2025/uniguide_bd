<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;

final class StudentProfileRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function findByUserId(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT user_id, ssc_gpa, ssc_group, ssc_passing_year,
                    hsc_gpa, hsc_group, hsc_passing_year, equivalent_qualification,
                    preferred_subject, preferred_location, maximum_budget,
                    scholarship_preference, campus_preference,
                    admission_test_preference, created_at, updated_at
             FROM student_profiles
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $profile = $statement->fetch();
        if ($profile === false) {
            return null;
        }

        if (is_string($profile['equivalent_qualification'])) {
            $profile['equivalent_qualification'] = json_decode(
                $profile['equivalent_qualification'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        if ($profile['scholarship_preference'] !== null) {
            $profile['scholarship_preference'] = (bool) $profile['scholarship_preference'];
        }

        return $profile;
    }

    public function save(int $userId, array $profile): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO student_profiles (
                user_id, ssc_gpa, ssc_group, ssc_passing_year,
                hsc_gpa, hsc_group, hsc_passing_year, equivalent_qualification,
                preferred_subject, preferred_location, maximum_budget,
                scholarship_preference, campus_preference, admission_test_preference
             ) VALUES (
                :user_id, :ssc_gpa, :ssc_group, :ssc_passing_year,
                :hsc_gpa, :hsc_group, :hsc_passing_year, :equivalent_qualification,
                :preferred_subject, :preferred_location, :maximum_budget,
                :scholarship_preference, :campus_preference, :admission_test_preference
             )
             ON DUPLICATE KEY UPDATE
                ssc_gpa = VALUES(ssc_gpa),
                ssc_group = VALUES(ssc_group),
                ssc_passing_year = VALUES(ssc_passing_year),
                hsc_gpa = VALUES(hsc_gpa),
                hsc_group = VALUES(hsc_group),
                hsc_passing_year = VALUES(hsc_passing_year),
                equivalent_qualification = VALUES(equivalent_qualification),
                preferred_subject = VALUES(preferred_subject),
                preferred_location = VALUES(preferred_location),
                maximum_budget = VALUES(maximum_budget),
                scholarship_preference = VALUES(scholarship_preference),
                campus_preference = VALUES(campus_preference),
                admission_test_preference = VALUES(admission_test_preference)'
        );
        $statement->execute(array_merge(
            ['user_id' => $userId],
            $profile
        ));
    }

    public function delete(int $userId): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM student_profiles WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
    }
}
