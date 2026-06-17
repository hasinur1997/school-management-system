<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Student;
use App\Services\IdCardService;
use Symfony\Component\HttpFoundation\Response;

class IdCardController extends ApiController
{
    public function __construct(private readonly IdCardService $idCards) {}

    /**
     * Stream a single student's ID card PDF (inline). Guarded by
     * idcard.generate; out-of-branch {student} ids 404 via BranchScope binding.
     * 422 when the student has no active enrollment (incl. TC / inactive).
     */
    public function show(Student $student): Response
    {
        return $this->idCards->render($student);
    }
}
