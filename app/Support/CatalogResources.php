<?php
declare(strict_types=1);

namespace UniGuide\App\Support;

final class CatalogResources
{
    private const SHARED_STATUS = ['draft', 'published', 'archived'];
    private const VERIFICATION = ['pending', 'verified', 'rejected'];

    public static function all(): array
    {
        return [
            'programs' => [
                'table' => 'programs',
                'search' => ['name', 'slug', 'field_of_study'],
                'public_fields' => ['id', 'university_id', 'slug', 'name', 'degree_level', 'field_of_study', 'duration_months', 'description', 'verification_status', 'verified_at'],
                'public_filters' => ['university_id' => 'integer'],
                'public_order' => 'r.name ASC, r.id ASC',
                'filters' => ['university_id' => 'integer', 'status' => self::SHARED_STATUS, 'verification_status' => ['draft', 'pending', 'verified', 'rejected']],
                'rules' => [
                    'university_id' => ['type' => 'integer', 'required' => true, 'min' => 1],
                    'slug' => ['type' => 'string', 'required' => true, 'max' => 140],
                    'name' => ['type' => 'string', 'required' => true, 'max' => 200],
                    'degree_level' => ['type' => 'string', 'max' => 100],
                    'field_of_study' => ['type' => 'string', 'max' => 120],
                    'duration_months' => ['type' => 'integer', 'min' => 1, 'max' => 600],
                    'description' => ['type' => 'string', 'max' => 10000],
                    'source_id' => ['type' => 'integer', 'min' => 1],
                    'verification_status' => ['type' => 'enum', 'values' => ['draft', 'pending', 'verified', 'rejected'], 'default' => 'draft'],
                    'status' => ['type' => 'enum', 'values' => self::SHARED_STATUS, 'default' => 'draft'],
                ],
                'timestamps' => ['created_at', 'updated_at'],
            ],
            'program-fees' => [
                'table' => 'program_fees',
                'search' => ['label', 'fee_type'],
                'public_fields' => ['id', 'program_id', 'fee_type', 'label', 'amount', 'currency', 'effective_from', 'effective_until', 'verified_at'],
                'public_filters' => ['program_id' => 'integer'],
                'public_order' => 'r.program_id ASC, r.id ASC',
                'filters' => ['program_id' => 'integer', 'verification_status' => self::VERIFICATION],
                'rules' => [
                    'program_id' => ['type' => 'integer', 'required' => true, 'min' => 1],
                    'source_id' => ['type' => 'integer', 'min' => 1],
                    'fee_type' => ['type' => 'enum', 'required' => true, 'values' => ['tuition', 'admission', 'semester', 'lab', 'other']],
                    'label' => ['type' => 'string', 'required' => true, 'max' => 180],
                    'amount' => ['type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 9999999999.99],
                    'currency' => ['type' => 'currency', 'default' => 'BDT'],
                    'effective_from' => ['type' => 'date'],
                    'effective_until' => ['type' => 'date'],
                    'verification_status' => ['type' => 'enum', 'values' => self::VERIFICATION, 'default' => 'pending'],
                ],
                'timestamps' => ['created_at'],
            ],
            'admission-requirements' => [
                'table' => 'admission_requirements',
                'search' => ['additional_requirements'],
                'public_fields' => ['id', 'program_id', 'minimum_ssc_gpa', 'minimum_hsc_gpa', 'minimum_combined_gpa', 'accepted_ssc_groups', 'accepted_hsc_groups', 'subject_prerequisites', 'admission_test_required', 'additional_requirements', 'verified_at'],
                'public_filters' => ['program_id' => 'integer'],
                'public_order' => 'r.program_id ASC, r.id ASC',
                'filters' => ['program_id' => 'integer', 'verification_status' => self::VERIFICATION],
                'rules' => [
                    'program_id' => ['type' => 'integer', 'required' => true, 'min' => 1],
                    'source_id' => ['type' => 'integer', 'min' => 1],
                    'minimum_ssc_gpa' => ['type' => 'decimal', 'min' => 0, 'max' => 5],
                    'minimum_hsc_gpa' => ['type' => 'decimal', 'min' => 0, 'max' => 5],
                    'minimum_combined_gpa' => ['type' => 'decimal', 'min' => 0, 'max' => 10],
                    'accepted_ssc_groups' => ['type' => 'json'],
                    'accepted_hsc_groups' => ['type' => 'json'],
                    'subject_prerequisites' => ['type' => 'json'],
                    'admission_test_required' => ['type' => 'boolean', 'default' => false],
                    'additional_requirements' => ['type' => 'string', 'max' => 10000],
                    'verification_status' => ['type' => 'enum', 'values' => self::VERIFICATION, 'default' => 'pending'],
                ],
                'timestamps' => ['created_at', 'updated_at'],
            ],
            'scholarships' => [
                'table' => 'scholarships',
                'search' => ['title', 'description'],
                'public_fields' => ['id', 'university_id', 'program_id', 'title', 'description', 'award_type', 'award_value', 'eligibility_rules', 'conditions', 'verified_at'],
                'public_filters' => ['university_id' => 'integer', 'program_id' => 'integer'],
                'public_order' => 'r.title ASC, r.id ASC',
                'filters' => ['university_id' => 'integer', 'program_id' => 'integer', 'status' => self::SHARED_STATUS, 'verification_status' => self::VERIFICATION],
                'rules' => [
                    'university_id' => ['type' => 'integer', 'required' => true, 'min' => 1],
                    'program_id' => ['type' => 'integer', 'min' => 1],
                    'source_id' => ['type' => 'integer', 'min' => 1],
                    'title' => ['type' => 'string', 'required' => true, 'max' => 200],
                    'description' => ['type' => 'string', 'max' => 10000],
                    'award_type' => ['type' => 'enum', 'required' => true, 'values' => ['percentage', 'fixed_amount', 'other']],
                    'award_value' => ['type' => 'decimal', 'min' => 0, 'max' => 9999999999.99],
                    'eligibility_rules' => ['type' => 'json'],
                    'conditions' => ['type' => 'string', 'max' => 10000],
                    'verification_status' => ['type' => 'enum', 'values' => self::VERIFICATION, 'default' => 'pending'],
                    'status' => ['type' => 'enum', 'values' => self::SHARED_STATUS, 'default' => 'draft'],
                ],
                'timestamps' => ['created_at', 'updated_at'],
            ],
            'rankings' => [
                'table' => 'rankings',
                'search' => ['publisher', 'edition', 'rank_label'],
                'public_fields' => ['id', 'university_id', 'publisher', 'edition', 'publication_date', 'rank_label', 'verified_at'],
                'public_filters' => ['university_id' => 'integer', 'publisher' => 'string', 'edition' => 'string'],
                'public_order' => 'r.publisher ASC, r.edition DESC, r.id DESC',
                'filters' => ['university_id' => 'integer', 'publisher' => 'string', 'edition' => 'string', 'verification_status' => self::VERIFICATION],
                'rules' => [
                    'university_id' => ['type' => 'integer', 'required' => true, 'min' => 1],
                    'source_id' => ['type' => 'integer', 'min' => 1],
                    'publisher' => ['type' => 'string', 'required' => true, 'max' => 180],
                    'edition' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'publication_date' => ['type' => 'date'],
                    'rank_label' => ['type' => 'string', 'max' => 80],
                    'verification_status' => ['type' => 'enum', 'values' => self::VERIFICATION, 'default' => 'pending'],
                ],
                'timestamps' => ['created_at', 'updated_at'],
            ],
            'news' => [
                'table' => 'news_updates',
                'search' => ['title', 'summary', 'category'],
                'public_fields' => ['id', 'university_id', 'slug', 'title', 'category', 'summary', 'body', 'source_published_at', 'verified_at'],
                'public_filters' => ['university_id' => 'integer', 'category' => 'string', 'slug' => 'string'],
                'public_order' => 'r.source_published_at DESC, r.id DESC',
                'filters' => ['university_id' => 'integer', 'category' => 'string', 'workflow_status' => ['draft', 'review', 'approved', 'published', 'archived'], 'verification_status' => self::VERIFICATION],
                'rules' => [
                    'university_id' => ['type' => 'integer', 'min' => 1],
                    'source_id' => ['type' => 'integer', 'min' => 1],
                    'slug' => ['type' => 'slug', 'required' => true, 'max' => 180],
                    'title' => ['type' => 'string', 'required' => true, 'max' => 240],
                    'category' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'summary' => ['type' => 'string', 'max' => 600],
                    'body' => ['type' => 'string', 'max' => 60000],
                    'source_published_at' => ['type' => 'datetime'],
                    'workflow_status' => ['type' => 'enum', 'values' => ['draft', 'review', 'approved', 'published', 'archived'], 'default' => 'draft'],
                    'verification_status' => ['type' => 'enum', 'values' => self::VERIFICATION, 'default' => 'pending'],
                ],
                'timestamps' => ['created_at', 'updated_at'],
                'created_by' => true,
            ],
            'guides' => [
                'table' => 'student_guides',
                'search' => ['title', 'summary', 'category'],
                'public_fields' => ['id', 'slug', 'title', 'category', 'summary', 'body', 'seo_title', 'seo_description', 'published_at'],
                'public_filters' => ['category' => 'string', 'slug' => 'string'],
                'public_order' => 'r.published_at DESC, r.id DESC',
                'filters' => ['category' => 'string', 'status' => self::SHARED_STATUS],
                'rules' => [
                    'slug' => ['type' => 'slug', 'required' => true, 'max' => 180],
                    'title' => ['type' => 'string', 'required' => true, 'max' => 240],
                    'category' => ['type' => 'string', 'required' => true, 'max' => 100],
                    'summary' => ['type' => 'string', 'max' => 600],
                    'body' => ['type' => 'string', 'required' => true, 'max' => 60000],
                    'seo_title' => ['type' => 'string', 'max' => 240],
                    'seo_description' => ['type' => 'string', 'max' => 500],
                    'status' => ['type' => 'enum', 'values' => self::SHARED_STATUS, 'default' => 'draft'],
                ],
                'timestamps' => ['created_at', 'updated_at'],
                'created_by' => true,
            ],
        ];
    }

    public static function find(string $name): ?array
    {
        $resources = self::all();

        if (!isset($resources[$name])) {
            return null;
        }
        $definition = $resources[$name];
        $columns = array_merge(['id'], array_keys($definition['rules']), $definition['timestamps']);
        if (array_key_exists('verification_status', $definition['rules'])) {
            $columns[] = 'verified_at';
        }
        if ($name === 'guides') {
            $columns[] = 'published_at';
        }
        if ($definition['created_by'] ?? false) {
            $columns[] = 'created_by';
        }
        $definition['columns'] = array_values(array_unique($columns));

        return $definition;
    }
}
