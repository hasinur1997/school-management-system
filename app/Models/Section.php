<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchThroughClass;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['class_id', 'name'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use BelongsToBranchThroughClass, HasFactory;

    /**
     * Get the class the section belongs to.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
