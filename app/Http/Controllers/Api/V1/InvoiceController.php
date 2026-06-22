<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Invoice\GenerateInvoicesRequest;
use App\Http\Requests\Invoice\ListInvoicesRequest;
use App\Http\Requests\Invoice\MeInvoicesRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\InvoiceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends ApiController
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * Manually trigger monthly generation for a (month, year). Idempotent — a
     * re-run reports the existing invoices as skipped. Guarded by fee.manage.
     */
    public function generate(GenerateInvoicesRequest $request): JsonResponse
    {
        $result = $this->invoices->generate(
            $request->integer('month'),
            $request->integer('year'),
        );

        return $this->success($result, 'Invoices generated');
    }

    /**
     * Browse invoices in the caller's branch, filtered and paginated. Staff
     * only (invoice.view).
     */
    public function index(ListInvoicesRequest $request): JsonResponse
    {
        $invoices = $this->invoices->list(
            $request->only(['student_id', 'class_id', 'status', 'month', 'year']),
            $request->integer('per_page', 15),
        );

        return $this->paginated($request, $invoices);
    }

    /**
     * Show one invoice with its payments. Authorized by
     * StudentPolicy::viewInvoices — staff (invoice.view), the student itself, or
     * a linked parent; a denial hides existence (404).
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->loadDetail($invoice);

        if ($request->user()->cannot('viewInvoices', $invoice->student)) {
            abort(404);
        }

        return $this->success(InvoiceResource::make($invoice));
    }

    /**
     * Return the caller's own invoices (student) or a linked child's invoices
     * (parent, via student_id) for a year. Students always get their own — any
     * student_id is ignored; a parent must pass a linked student_id (an unlinked
     * or missing one → 404).
     */
    public function me(MeInvoicesRequest $request): JsonResponse
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if ($student === null) {
            $parent = ParentProfile::where('user_id', $user->id)->first();
            abort_if($parent === null, 403);

            $studentId = $request->integer('student_id');
            abort_unless($studentId !== 0 && $parent->isLinkedTo($studentId), 404);

            $student = Student::findOrFail($studentId);
        }

        $invoices = $this->invoices->forStudent(
            $student,
            $request->filled('year') ? $request->integer('year') : null,
            $request->integer('per_page', 15),
        );

        return $this->paginated($request, $invoices);
    }

    /**
     * Wrap a paginated invoice listing in the standard envelope with meta.
     *
     * @param  LengthAwarePaginator<int, Invoice>  $invoices
     */
    private function paginated(Request $request, LengthAwarePaginator $invoices): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => InvoiceResource::collection($invoices)->resolve($request),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }
}
