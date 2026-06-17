<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\AnnualResultController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckinIpController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FeeStructureController;
use App\Http\Controllers\Api\V1\GradingScaleController;
use App\Http\Controllers\Api\V1\IdCardController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MarkController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\PublicAdmissionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResultController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherAssignmentController;
use App\Http\Controllers\Api\V1\TeacherAttendanceController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TransferCertificateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    // Public, unauthenticated surface. Submission is rate-limited to
    // 10 requests/hour/IP; both endpoints handle branches explicitly.
    Route::prefix('public')->name('public.')->group(function () {
        Route::post('admissions', [PublicAdmissionController::class, 'store'])
            ->middleware('throttle:10,60')
            ->name('admissions.store');

        Route::get('admissions/{application_no}/status', [PublicAdmissionController::class, 'status'])
            ->name('admissions.status');

        // Safe subset for the public admission page: school name, logo URL,
        // active branches and their open classes. Never exposes secrets.
        Route::get('settings', [SettingController::class, 'publicSettings'])->name('settings');
    });

    // SSLCommerz callbacks (10.5) — public surface, no auth. The IPN is the
    // server-to-server source of truth (idempotent settlement); the landing
    // routes are browser redirects that only report the payment's status and
    // change no state. Paths must match the callback URLs built in
    // SslCommerzGateway (/api/v1/payments/sslcommerz/*).
    Route::prefix('payments/sslcommerz')->name('payments.sslcommerz.')->group(function () {
        Route::post('ipn', [PaymentController::class, 'ipn'])->name('ipn');

        Route::get('{result}', [PaymentController::class, 'landing'])
            ->whereIn('result', ['success', 'fail', 'cancel'])
            ->name('landing');
    });

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
        });
    });

    Route::middleware(['auth:sanctum', 'permission:session.manage'])->group(function () {
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions', [SessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
        Route::put('sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
        Route::delete('sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
    });

    Route::middleware('auth:sanctum')->group(function () {
        // Reads are open to every authenticated user (dropdown data).
        Route::get('classes', [ClassController::class, 'index'])->name('classes.index');
        Route::get('classes/{class}', [ClassController::class, 'show'])->name('classes.show');
        Route::get('classes/{class}/sections', [SectionController::class, 'index'])->name('classes.sections.index');
        Route::get('sections/{section}', [SectionController::class, 'show'])->name('sections.show');
        Route::get('classes/{class}/subjects', [SubjectController::class, 'index'])->name('classes.subjects.index');
        Route::get('subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');

        Route::middleware('permission:class.manage')->group(function () {
            Route::post('classes', [ClassController::class, 'store'])->name('classes.store');
            Route::put('classes/{class}', [ClassController::class, 'update'])->name('classes.update');
            Route::delete('classes/{class}', [ClassController::class, 'destroy'])->name('classes.destroy');
            Route::post('classes/{class}/sections', [SectionController::class, 'store'])->name('classes.sections.store');
            Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
            Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        });

        Route::middleware('permission:subject.manage')->group(function () {
            Route::post('classes/{class}/subjects', [SubjectController::class, 'store'])->name('classes.subjects.store');
            Route::put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
            Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('teachers', [TeacherController::class, 'index'])
            ->middleware('permission:teacher.view')
            ->name('teachers.index');

        Route::get('teachers/{teacher}', [TeacherController::class, 'show'])
            ->middleware('permission:teacher.view')
            ->name('teachers.show');

        Route::post('teachers', [TeacherController::class, 'store'])
            ->middleware('permission:teacher.create')
            ->name('teachers.store');

        Route::put('teachers/{teacher}', [TeacherController::class, 'update'])
            ->middleware('permission:teacher.update')
            ->name('teachers.update');

        Route::patch('teachers/{teacher}/status', [TeacherController::class, 'updateStatus'])
            ->middleware('permission:teacher.update')
            ->name('teachers.status');

        Route::post('teachers/{teacher}/photo', [TeacherController::class, 'photo'])
            ->middleware('permission:teacher.update')
            ->name('teachers.photo');

        Route::post('teachers/{teacher}/resend-credentials', [TeacherController::class, 'resendCredentials'])
            ->middleware('permission:teacher.create')
            ->name('teachers.resend-credentials');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('students', [StudentController::class, 'index'])
            ->middleware('permission:student.view')
            ->name('students.index');

        // show authorizes via StudentPolicy::view — staff or the student itself.
        Route::get('students/{student}', [StudentController::class, 'show'])
            ->name('students.show');

        // enrollments authorizes via StudentPolicy::view — staff, the student, or a linked parent.
        Route::get('students/{student}/enrollments', [StudentController::class, 'enrollments'])
            ->name('students.enrollments');

        Route::put('students/{student}', [StudentController::class, 'update'])
            ->middleware('permission:student.update')
            ->name('students.update');

        Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])
            ->middleware('permission:student.update')
            ->name('students.status');

        Route::post('students/{student}/photo', [StudentController::class, 'photo'])
            ->middleware('permission:student.update')
            ->name('students.photo');

        // Single ID card PDF (12.1): streamed on demand from live enrollment
        // data — no table. Out-of-branch {student} ids 404 via BranchScope.
        Route::get('students/{student}/id-card', [IdCardController::class, 'show'])
            ->middleware('permission:idcard.generate')
            ->name('students.id-card');

        // Batch ID cards (12.2): queued merged-PDF build + poll + download.
        // Foreign {batch} ids 404 via BranchScope route binding.
        Route::middleware('permission:idcard.generate')->group(function () {
            Route::post('id-cards/batch', [IdCardController::class, 'batch'])
                ->name('id-cards.batch');
            Route::get('id-cards/batch/{batch}', [IdCardController::class, 'batchStatus'])
                ->name('id-cards.batch.status');
            Route::get('id-cards/batch/{batch}/download', [IdCardController::class, 'download'])
                ->name('id-cards.batch.download');
        });

        // Transfer certificates (12.3): issuing retires a student (status → tc)
        // and stores the one persisted legal PDF. Out-of-branch {student}/{tc}
        // ids 404 via BranchScope binding.
        Route::post('students/{student}/tc', [TransferCertificateController::class, 'store'])
            ->middleware('permission:tc.issue')
            ->name('students.tc');

        Route::middleware('permission:tc.view')->group(function () {
            Route::get('tcs', [TransferCertificateController::class, 'index'])->name('tcs.index');
            Route::get('tcs/{tc}', [TransferCertificateController::class, 'show'])->name('tcs.show');
            Route::get('tcs/{tc}/pdf', [TransferCertificateController::class, 'pdf'])->name('tcs.pdf');
        });
    });

    Route::middleware(['auth:sanctum', 'permission:parent.manage'])->group(function () {
        Route::get('parents', [ParentController::class, 'index'])->name('parents.index');
        Route::post('parents', [ParentController::class, 'store'])->name('parents.store');
        Route::post('parents/{parent}/students', [ParentController::class, 'linkStudent'])->name('parents.students.link');
        Route::delete('parents/{parent}/students/{student}', [ParentController::class, 'unlinkStudent'])->name('parents.students.unlink');
    });

    // Parent self-service: linked children. Role-gated in the controller
    // (parents hold no staff permissions).
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me/students', [ParentController::class, 'meStudents'])->name('me.students');
    });

    Route::middleware(['auth:sanctum', 'permission:admission.view'])->group(function () {
        Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
    });

    Route::middleware(['auth:sanctum', 'permission:admission.approve'])->group(function () {
        Route::post('admissions/{admission}/approve', [AdmissionController::class, 'approve'])->name('admissions.approve');
        Route::post('admissions/{admission}/reject', [AdmissionController::class, 'reject'])->name('admissions.reject');
    });

    Route::middleware(['auth:sanctum', 'permission:teacher.update'])->group(function () {
        Route::get('teacher-assignments', [TeacherAssignmentController::class, 'index'])->name('teacher-assignments.index');
        Route::post('teacher-assignments', [TeacherAssignmentController::class, 'store'])->name('teacher-assignments.store');
        Route::get('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'show'])->name('teacher-assignments.show');
        Route::put('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'update'])->name('teacher-assignments.update');
        Route::delete('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy'])->name('teacher-assignments.destroy');
    });

    Route::middleware(['auth:sanctum', 'permission:attendance.create'])->group(function () {
        Route::get('attendance/sheet', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
        Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    });

    Route::middleware(['auth:sanctum', 'permission:attendance.view'])->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    });

    Route::middleware(['auth:sanctum', 'permission:attendance.update'])->group(function () {
        Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
    });

    // Monthly attendance reads. studentMonthly authorizes via
    // StudentPolicy::viewAttendance in the controller (staff/self/linked
    // parent, 404 hiding); meMonthly is role-gated to the student itself.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('students/{student}/attendance', [AttendanceController::class, 'studentMonthly'])->name('students.attendance');
        Route::get('me/attendance', [AttendanceController::class, 'meMonthly'])->name('me.attendance');
    });

    // Teacher self check-in / check-out. Teacher role only; the request IP is
    // matched against the branch whitelist in the service.
    Route::middleware(['auth:sanctum', 'role:teacher'])->group(function () {
        Route::post('teacher-attendance/check-in', [TeacherAttendanceController::class, 'checkIn'])->name('teacher-attendance.check-in');
        Route::post('teacher-attendance/check-out', [TeacherAttendanceController::class, 'checkOut'])->name('teacher-attendance.check-out');
        Route::get('me/teacher-attendance', [TeacherAttendanceController::class, 'me'])->name('me.teacher-attendance');
    });

    // Admin browse + correction. Records are branch-scoped through the teacher,
    // so out-of-branch {teacherAttendance} bindings 404 automatically.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('teacher-attendance', [TeacherAttendanceController::class, 'index'])
            ->middleware('permission:teacher_attendance.view')
            ->name('teacher-attendance.index');

        Route::put('teacher-attendance/{teacherAttendance}', [TeacherAttendanceController::class, 'update'])
            ->middleware('permission:teacher_attendance.manage')
            ->name('teacher-attendance.update');
    });

    // Teacher check-in IP whitelist management. Entries are branch-scoped via
    // BranchScope, so out-of-branch {checkinIp} bindings 404 automatically.
    Route::middleware(['auth:sanctum', 'permission:teacher_attendance.manage'])->group(function () {
        Route::get('checkin-ips', [CheckinIpController::class, 'index'])->name('checkin-ips.index');
        Route::post('checkin-ips', [CheckinIpController::class, 'store'])->name('checkin-ips.store');
        Route::put('checkin-ips/{checkinIp}', [CheckinIpController::class, 'update'])->name('checkin-ips.update');
        Route::delete('checkin-ips/{checkinIp}', [CheckinIpController::class, 'destroy'])->name('checkin-ips.destroy');
    });

    // Grading scale: a single global scale. Reads are open to any authenticated
    // user (cached); the full-replace write requires setting.manage.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('grading-scales', [GradingScaleController::class, 'index'])->name('grading-scales.index');

        Route::put('grading-scales', [GradingScaleController::class, 'update'])
            ->middleware('permission:setting.manage')
            ->name('grading-scales.update');
    });

    // Exams: branch-scoped CRUD. Reads need exam.view, writes exam.manage;
    // out-of-branch {exam} bindings 404 via BranchScope.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('exams', [ExamController::class, 'index'])
            ->middleware('permission:exam.view')
            ->name('exams.index');

        Route::get('exams/{exam}', [ExamController::class, 'show'])
            ->middleware('permission:exam.view')
            ->name('exams.show');

        Route::post('exams', [ExamController::class, 'store'])
            ->middleware('permission:exam.manage')
            ->name('exams.store');

        Route::put('exams/{exam}', [ExamController::class, 'update'])
            ->middleware('permission:exam.manage')
            ->name('exams.update');
    });

    // Marks: per-subject entry sheet + bulk save (marks.entry) and browse
    // (marks.view). Marks are branch-scoped through the enrollment and the
    // {exam} binding 404s out-of-branch via BranchScope.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('exams/{exam}/marks/sheet', [MarkController::class, 'sheet'])
            ->middleware('permission:marks.entry')
            ->name('exams.marks.sheet');

        Route::post('exams/{exam}/marks', [MarkController::class, 'store'])
            ->middleware('permission:marks.entry')
            ->name('exams.marks.store');

        Route::get('exams/{exam}/marks', [MarkController::class, 'index'])
            ->middleware('permission:marks.view')
            ->name('exams.marks.index');
    });

    // Per-exam results: generate/publish (result.generate) and browse
    // (result.view). Results are branch-scoped through the enrollment and the
    // {exam} binding 404s out-of-branch via BranchScope.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('exams/{exam}/results/generate', [ResultController::class, 'generate'])
            ->middleware('permission:result.generate')
            ->name('exams.results.generate');

        Route::post('exams/{exam}/results/publish', [ResultController::class, 'publish'])
            ->middleware('permission:result.generate')
            ->name('exams.results.publish');

        Route::get('exams/{exam}/results', [ResultController::class, 'index'])
            ->middleware('permission:result.view')
            ->name('exams.results.index');
    });

    // Annual results: 25/25/50 weighted generate/publish for a (session, class)
    // tuple (result.generate). Branch isolation comes through the class (the
    // tuple is validated branch-scoped) and the enrollment chain.
    Route::middleware(['auth:sanctum', 'permission:result.generate'])->group(function () {
        Route::post('annual-results/generate', [AnnualResultController::class, 'generate'])
            ->name('annual-results.generate');

        Route::post('annual-results/publish', [AnnualResultController::class, 'publish'])
            ->name('annual-results.publish');
    });

    // Result reads (8.3). Search is staff-only (result.view, staff see
    // unpublished flagged). The enrollment + me reads carry no permission
    // middleware: enrollmentResults authorizes via StudentPolicy::viewResults
    // in the controller (staff/self/linked parent, 404 hiding), and meResults
    // is student/parent self-service — so students/parents, who hold no
    // result.view, can read their own published results.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('results/search', [ResultController::class, 'search'])
            ->middleware('permission:result.view')
            ->name('results.search');

        Route::get('enrollments/{id}/results', [ResultController::class, 'enrollmentResults'])
            ->name('enrollments.results');

        Route::get('me/results', [ResultController::class, 'meResults'])
            ->name('me.results');
    });

    // Promotion preview (9.1): who moves up for a (session, class) and the
    // resolved next class. Branch isolation comes through the class (validated
    // branch-scoped) and the enrollment → student chain.
    Route::middleware(['auth:sanctum', 'permission:promotion.execute'])->group(function () {
        Route::get('promotions/preview', [PromotionController::class, 'preview'])
            ->name('promotions.preview');

        // Bulk promote (9.2): close the class's old enrollments and open the new
        // session's in one transaction. Passed → next class, failed → same class.
        Route::post('promotions/bulk', [PromotionController::class, 'bulk'])
            ->name('promotions.bulk');

        // Individual promote (9.3): move one student; failed/result-less needs
        // promotion.override (checked in the service).
        Route::post('promotions/individual', [PromotionController::class, 'individual'])
            ->name('promotions.individual');
    });

    // Promotion history (9.3): paginated, filterable log under promotion.view.
    Route::middleware(['auth:sanctum', 'permission:promotion.view'])->group(function () {
        Route::get('promotions', [PromotionController::class, 'index'])
            ->name('promotions.index');
    });

    // Fee structures (10.1): the monthly fee per (branch, session, class) that
    // invoices copy at generation time. CRUD guarded by fee.manage; no DELETE
    // (history matters). Out-of-branch {fee_structure} bindings 404 via
    // BranchScope; fee structures carry their own branch_id so list/show are
    // branch-isolated automatically.
    Route::middleware(['auth:sanctum', 'permission:fee.manage'])->group(function () {
        Route::get('fee-structures', [FeeStructureController::class, 'index'])
            ->name('fee-structures.index');

        Route::post('fee-structures', [FeeStructureController::class, 'store'])
            ->name('fee-structures.store');

        Route::put('fee-structures/{feeStructure}', [FeeStructureController::class, 'update'])
            ->name('fee-structures.update');
    });

    // Invoices (10.2): monthly fee invoices generated from the class fee
    // structure. Manual generation is fee.manage; it normally runs from the
    // scheduler on the 1st. The list is staff-only (invoice.view). Show carries
    // no permission middleware — it authorizes via StudentPolicy::viewInvoices
    // (staff/self/linked parent, 404 hiding) so students/parents, who hold no
    // invoice.view, can read their own. /me/invoices is student/parent
    // self-service. Out-of-branch {invoice} ids 404 via BranchScope.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('invoices/generate', [InvoiceController::class, 'generate'])
            ->middleware('permission:fee.manage')
            ->name('invoices.generate');

        Route::get('invoices', [InvoiceController::class, 'index'])
            ->middleware('permission:invoice.view')
            ->name('invoices.index');

        Route::get('me/invoices', [InvoiceController::class, 'me'])
            ->name('me.invoices');

        Route::get('invoices/{id}', [InvoiceController::class, 'show'])
            ->name('invoices.show');
    });

    // Payments (10.3): counter (cash) collection settles an invoice through the
    // PaymentService pipeline (payment → invoice → income → receipt_no), guarded
    // by fee.collect; out-of-branch {invoice} ids 404 via BranchScope binding.
    // The receipt PDF carries no permission middleware — it authorizes via
    // StudentPolicy::viewInvoices (staff/self/linked parent, 404 hiding) and only
    // for a paid payment; out-of-branch {id} 404s via BranchScope.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('invoices/{invoice}/payments/local', [PaymentController::class, 'local'])
            ->middleware('permission:fee.collect')
            ->name('payments.local');

        // Online checkout init (10.4): no permission middleware — students and
        // linked parents initiate too; StudentPolicy::payOnline authorizes
        // (staff fee.collect / self / linked parent, 404 hiding).
        Route::post('invoices/{invoice}/payments/online', [PaymentController::class, 'online'])
            ->name('payments.online');

        Route::get('payments/{id}/receipt', [PaymentController::class, 'receipt'])
            ->name('payments.receipt');
    });

    // Categories (11.1): the shared income/expense category list. CRUD guarded
    // by income.manage OR expense.manage (accountant work). Categories carry
    // their own branch_id, so list/show are branch-isolated automatically and
    // out-of-branch {category} bindings 404 via BranchScope. Deleting a category
    // in use by income/expense rows → 409.
    Route::middleware(['auth:sanctum', 'permission:income.manage|expense.manage'])->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Incomes (11.2): manual income ledger CRUD plus read access to the
    // system-generated fee incomes Task 10.3 posts. Guarded by income.manage.
    // Incomes carry their own branch_id, so list/show are branch-isolated and
    // out-of-branch {income} bindings 404 via BranchScope. System-generated
    // rows (payment_id set, is_system true) are immutable → update/delete 403.
    Route::middleware(['auth:sanctum', 'permission:income.manage'])->group(function () {
        Route::get('incomes', [IncomeController::class, 'index'])->name('incomes.index');
        Route::post('incomes', [IncomeController::class, 'store'])->name('incomes.store');
        Route::put('incomes/{income}', [IncomeController::class, 'update'])->name('incomes.update');
        Route::delete('incomes/{income}', [IncomeController::class, 'destroy'])->name('incomes.destroy');
    });

    // Expenses (11.3): manual expense ledger CRUD mirroring incomes (same
    // filters/sorts). Guarded by expense.manage; out-of-branch {expense}
    // bindings 404 via BranchScope. category_id must be an expense-type
    // category in the caller's branch (validated in the Form Requests).
    Route::middleware(['auth:sanctum', 'permission:expense.manage'])->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // Assets (11.4): asset register CRUD plus an at-a-glance summary
    // (total_value = in_use + damaged; disposed excluded). Guarded by
    // asset.manage; out-of-branch {asset} bindings 404 via BranchScope.
    // Filters status/search; sorts value/purchase_date.
    Route::middleware(['auth:sanctum', 'permission:asset.manage'])->group(function () {
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/summary', [AssetController::class, 'summary'])->name('assets.summary');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    });

    // Finance reports (13.2): income / expense / profit-loss, all SQL-aggregated
    // over the shared report filter (period/from/to + super-admin branch_id,
    // `all` = consolidated). Guarded by report.view. Series granularity switches
    // from daily to monthly at 62 days; consolidated views add a by_branch list.
    Route::middleware(['auth:sanctum', 'permission:report.view'])->group(function () {
        Route::get('reports/income', [ReportController::class, 'income'])->name('reports.income');
        Route::get('reports/expense', [ReportController::class, 'expense'])->name('reports.expense');
        Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');

        // Entity reports (13.3): students / teachers / assets / fees, same
        // filter + SQL-aggregation conventions. Fees figures (invoiced from
        // invoice amounts, collected from paid_amount) reconcile with the
        // invoice/payment fixtures; asset total_value follows the 11.4 rule
        // (in_use + damaged, disposed excluded).
        Route::get('reports/students', [ReportController::class, 'students'])->name('reports.students');
        Route::get('reports/teachers', [ReportController::class, 'teachers'])->name('reports.teachers');
        Route::get('reports/assets', [ReportController::class, 'assets'])->name('reports.assets');
        Route::get('reports/fees', [ReportController::class, 'fees'])->name('reports.fees');

        // Report PDF exports (13.4): any of the seven reports as a streamed PDF
        // over the same filter contract. The {type} constraint rejects unknown
        // types with a 404; data comes from the same 13.2/13.3 services.
        Route::get('reports/{type}/pdf', [ReportController::class, 'pdf'])
            ->where('type', 'income|expense|profit-loss|students|teachers|assets|fees')
            ->name('reports.pdf');
    });

    // Dashboard (14.2): one role-aware summary endpoint — the shape of `data`
    // depends on the caller's role. Authenticated only; no permission gate.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    // Settings (14.1): global + per-branch key/value store. Secrets are
    // write-only (masked on read). Super admins target another branch via
    // branch_id; the cache is invalidated on every write.
    Route::middleware(['auth:sanctum', 'permission:setting.manage'])->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware(['auth:sanctum', 'permission:branch.manage'])->group(function () {
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });
});
