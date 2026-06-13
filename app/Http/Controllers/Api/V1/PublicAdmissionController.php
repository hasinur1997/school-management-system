<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admission\CheckAdmissionStatusRequest;
use App\Http\Requests\Admission\StoreAdmissionRequest;
use App\Http\Resources\AdmissionStatusResource;
use App\Http\Resources\AdmissionSubmissionResource;
use App\Services\AdmissionService;
use Illuminate\Http\JsonResponse;

/**
 * The public, unauthenticated admission surface: form submission and a
 * status lookup gated on a matching date_of_birth.
 */
class PublicAdmissionController extends ApiController
{
    public function __construct(private readonly AdmissionService $admissions) {}

    /**
     * Accept a public admission submission.
     */
    public function store(StoreAdmissionRequest $request): JsonResponse
    {
        $application = $this->admissions->submit($request->validated());

        return $this->success(
            AdmissionSubmissionResource::make($application),
            'Application submitted successfully.',
            201,
        );
    }

    /**
     * Return an application's status when the date_of_birth matches.
     */
    public function status(CheckAdmissionStatusRequest $request, string $application_no): JsonResponse
    {
        $application = $this->admissions->findForStatus(
            $application_no,
            $request->validated('date_of_birth'),
        );

        return $this->success(AdmissionStatusResource::make($application));
    }
}
