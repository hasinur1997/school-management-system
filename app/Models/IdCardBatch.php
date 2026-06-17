<?php

namespace App\Models;

use App\Enums\IdCardBatchStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\IdCardBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A queued request to build one merged PDF of ID cards for a whole class (or a
 * single section). Heavy PDF work is offloaded to BuildIdCardBatch, which fills
 * file_path and flips status; the caller polls until done, then downloads.
 * Branch is stamped automatically via BelongsToBranch.
 */
#[Fillable(['class_id', 'section_id', 'session_id', 'status', 'file_path', 'error', 'requested_by'])]
class IdCardBatch extends Model
{
    /** @use HasFactory<IdCardBatchFactory> */
    use BelongsToBranch, HasFactory, HasUuids;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'processing',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdCardBatchStatus::class,
        ];
    }

    /**
     * Disk-relative path where this batch's merged PDF lives once built.
     */
    public function storagePath(): string
    {
        return "idcards/batches/{$this->id}.pdf";
    }
}
