<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\TransferCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A transfer certificate issued to a student. Issuing one retires the student
 * from active operations (status → tc) while preserving every record, and is
 * the project's one stored document — the rendered PDF is persisted via
 * medialibrary as a legal record. Branch is stamped via BelongsToBranch.
 */
#[Fillable(['student_id', 'tc_no', 'reason', 'issue_date', 'issued_by'])]
class TransferCertificate extends Model implements HasMedia
{
    /** @use HasFactory<TransferCertificateFactory> */
    use BelongsToBranch, HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    /**
     * The stored TC PDF: a single file, the legal record.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('certificate')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    /**
     * Get the student this certificate was issued to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who issued this certificate.
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
