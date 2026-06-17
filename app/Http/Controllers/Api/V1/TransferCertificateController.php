<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Tc\IssueTcRequest;
use App\Http\Requests\Tc\ListTcsRequest;
use App\Http\Resources\TransferCertificateResource;
use App\Models\Student;
use App\Models\TransferCertificate;
use App\Services\TransferCertificateService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TransferCertificateController extends ApiController
{
    public function __construct(private readonly TransferCertificateService $tcs) {}

    /**
     * Issue a transfer certificate for a student (tc.issue). Atomic: TC row +
     * status flips + stored PDF. 409 if the student already holds one;
     * out-of-branch {student} ids 404 via BranchScope binding.
     */
    public function store(IssueTcRequest $request, Student $student): JsonResponse
    {
        $tc = $this->tcs->issue($student, $request->validated());

        $tc->load('student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name');

        return $this->success(
            TransferCertificateResource::make($tc),
            'Transfer certificate issued',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Browse issued TCs in the caller's branch with from/to/search filters.
     */
    public function index(ListTcsRequest $request): JsonResponse
    {
        $tcs = $this->tcs->list(
            $request->only(['from', 'to', 'search']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => TransferCertificateResource::collection($tcs)->resolve($request),
            'meta' => [
                'current_page' => $tcs->currentPage(),
                'per_page' => $tcs->perPage(),
                'total' => $tcs->total(),
                'last_page' => $tcs->lastPage(),
            ],
        ]);
    }

    /**
     * Show one TC. Out-of-branch ids 404 via BranchScope binding.
     */
    public function show(TransferCertificate $tc): JsonResponse
    {
        $tc->load('student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name');

        return $this->success(TransferCertificateResource::make($tc));
    }

    /**
     * Download the stored TC PDF. Missing file → 500 (logged).
     */
    public function pdf(TransferCertificate $tc): Response
    {
        return $this->tcs->downloadPdf($tc);
    }
}
