<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchThroughClass;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['class_id', 'name', 'code', 'full_marks', 'pass_marks'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToBranchThroughClass, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'full_marks' => 100,
        'pass_marks' => 33,
    ];

    /**
     * Get the class the subject belongs to.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'full_marks' => 'integer',
            'pass_marks' => 'integer',
        ];
    }
}
