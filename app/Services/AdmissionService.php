<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Owns the public admission write/read paths. The submission persists the
 * application, its previous-education rows, and its media in a single
 * transaction so a media failure rolls the whole thing back — no orphaned
 * application without its photo.
 */
class AdmissionService
{
    public function __construct(private readonly ApplicationNoGenerator $applicationNos) {}

    /**
     * Persist a public admission submission atomically: the application row
     * (branch stamped explicitly — the public context has no auth user, so
     * BelongsToBranch does not stamp it), its previous-education children, and
     * the uploaded photo + documents.
     *
     * The application number is generated inside this transaction so the
     * branch row lock the generator takes spans the insert, serializing
     * concurrent same-branch submissions.
     *
     * @param  array<string, mixed>  $data  Validated request data.
     */
    public function submit(array $data): AdmissionApplication
    {
        $photo = $data['photo'];
        $documents = $data['documents'] ?? [];
        $previousEducations = $data['previous_educations'] ?? [];

        $attributes = Arr::except($data, ['photo', 'documents', 'previous_educations']);

        // The column is NOT NULL (schema item 7) while the contract marks the
        // field optional; absence persists as an empty string.
        $attributes['mother_mobile'] ??= '';

        return DB::transaction(function () use ($attributes, $previousEducations, $photo, $documents): AdmissionApplication {
            $branchId = (int) $attributes['branch_id'];

            $application = new AdmissionApplication($attributes);
            $application->branch_id = $branchId;
            $application->application_no = $this->applicationNos->generate($branchId);
            $application->save();

            if ($previousEducations !== []) {
                $application->previousEducations()->createMany($previousEducations);
            }

            $application->addMedia($photo)->toMediaCollection('photo');

            foreach ($documents as $document) {
                $application->addMedia($document)->toMediaCollection('documents');
            }

            return $application;
        });
    }

    /**
     * Resolve an application for a public status check. Both the application
     * number and date_of_birth must match; a miss throws model-not-found
     * (rendered 404) so existence is never revealed.
     */
    public function findForStatus(string $applicationNo, string $dateOfBirth): AdmissionApplication
    {
        return AdmissionApplication::query()
            ->where('application_no', $applicationNo)
            ->whereDate('date_of_birth', $dateOfBirth)
            ->firstOrFail();
    }
}
