<?php

namespace App\Models;

use Database\Factories\AdmissionPreviousEducationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single previous-schooling row from admission form item 13. Branch
 * isolation is inherited through the parent application; rows cascade-delete
 * with it.
 */
#[Fillable([
    'application_id', 'exam_name', 'institution_name', 'gpa', 'passing_year', 'board_roll', 'board_reg_no',
])]
class AdmissionPreviousEducation extends Model
{
    /** @use HasFactory<AdmissionPreviousEducationFactory> */
    use HasFactory;

    /**
     * "education" is uncountable, so the inferred plural is wrong.
     *
     * @var string
     */
    protected $table = 'admission_previous_educations';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gpa' => 'decimal:2',
            'passing_year' => 'integer',
        ];
    }

    /**
     * Get the application this education row belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'application_id');
    }
}
