<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
     * List admission applications in the caller's branch (branch isolation is
     * automatic via BranchScope). Defaults to pending; supports filtering by
     * desired class and a created-date range, plus a free-text search across
     * the applicant/father identifiers. The desired class is eager loaded for
     * the compact rows so it never lazy loads in the Resource.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $status = $filters['status'] ?? AdmissionStatus::Pending->value;

        return AdmissionApplication::query()
            ->with('desiredClass')
            ->where('status', $status)
            ->when(isset($filters['desired_class_id']), fn (Builder $query) => $query->where('desired_class_id', $filters['desired_class_id']))
            ->when(isset($filters['from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name_en', 'like', $term)
                    ->orWhere('name_bn', 'like', $term)
                    ->orWhere('application_no', 'like', $term)
                    ->orWhere('father_mobile', 'like', $term)
                    ->orWhere('birth_reg_no', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Eager load everything the detail Resource touches: the desired class, the
     * previous-education child rows, the reviewer, and the media (photo +
     * documents).
     */
    public function loadDetail(AdmissionApplication $application): AdmissionApplication
    {
        return $application->load(['desiredClass', 'previousEducations', 'reviewer', 'media']);
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
