<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatus;
use App\Enums\AssetStatus;
use App\Enums\AttendanceStatus;
use App\Enums\CategoryType;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Enums\TeacherStatus;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CheckinIpWhitelist;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\AnnualResultService;
use App\Services\InvoiceService;
use App\Services\MarkService;
use App\Services\PaymentService;
use App\Services\PromotionService;
use App\Services\ResultService;
use App\Services\TransferCertificateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Populates ONE branch end-to-end so `migrate:fresh --seed` yields a fully
 * explorable system: real teachers/students/parents, a published 2025 exam
 * cycle promoted into 2026, current-session enrollments, a month of
 * attendance, two months of invoices with mixed payments + incomes, expenses,
 * assets, a transfer certificate, and a check-in whitelist.
 *
 * Guardrails:
 *  - never runs in production;
 *  - idempotent — a second run is a no-op once students exist, so re-seeding
 *    on top of a populated database can never violate a unique constraint.
 *
 * The whole run is performed as a logged-in branch admin so BelongsToBranch
 * stamps branch_id automatically and the real services (admission approval,
 * marks, results, promotion, payments, TC) resolve the actor through Auth.
 */
class DemoSeeder extends Seeder
{
    /** Students created in the 2025 promotion cohort (exam + promote pipeline). */
    private const PROMOTION_COHORT = 28;

    /** Students enrolled directly into the current (2026) session per class. */
    private const CURRENT_PER_CLASS = 18;

    /** Cohort-C roll numbers start here so they never collide with promoted rolls. */
    private const CURRENT_ROLL_BASE = 101;

    /** Monotonic source of globally-unique user phone numbers. */
    private int $phoneSeq = 100_000_000;

    /** The demo password ("password"), hashed once and reused for every login. */
    private ?string $passwordHash = null;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoSeeder skipped: refusing to seed demo data in production.');

            return;
        }

        // Idempotency guard: once the demo population exists, re-running is a
        // no-op rather than a second pile of randomly-keyed rows.
        if (Student::query()->withoutGlobalScopes()->exists()) {
            $this->command?->info('DemoSeeder skipped: students already exist.');

            return;
        }

        // DatabaseSeeder mutes model events (WithoutModelEvents). The real
        // services this seeder drives — admission approval, TC issuance, branch
        // stamping via BelongsToBranch — depend on those events, so re-enable
        // them for the demo run (it is the final seeder, so nothing else is
        // affected).
        Model::setEventDispatcher(app('events'));

        $branch = Branch::query()->orderBy('id')->firstOrFail();
        $admin = $this->staff($branch);

        Auth::login($admin);

        try {
            [$session2025, $session2026] = $this->sessions();
            $classes = $this->classes($branch);
            $subjects = $this->subjects($classes);
            $this->teachers($branch, $session2026, $classes, $subjects);

            $this->promotionCohort($branch, $session2025, $session2026, $classes, $subjects, $admin);
            $this->currentCohort($branch, $session2026, $classes);
            $this->attendance($session2026, $classes[3], $admin);
            $this->feesAndPayments($branch, $session2026, $classes, $admin);
            $this->finance($branch, $admin);
            $this->transferCertificate($session2026, $classes[8]);
            $this->whitelist($branch);
        } finally {
            Auth::logout();
        }

        $this->command?->info('DemoSeeder complete: branch "'.$branch->name.'" fully populated.');
    }

    /**
     * The branch admin (the actor for the whole run) plus an accountant, both
     * with the password "password" for demo logins.
     */
    private function staff(Branch $branch): User
    {
        $admin = $this->user($branch, 'Demo Admin', 'admin@'.strtolower($branch->code).'.demo');
        $admin->assignRole('admin');

        $accountant = $this->user($branch, 'Demo Accountant', 'accountant@'.strtolower($branch->code).'.demo');
        $accountant->assignRole('accountant');

        return $admin;
    }

    /**
     * Ensure both sessions exist: 2026 (current, seeded already) and the past
     * 2025 the promotion cohort sits in.
     *
     * @return array{0: AcademicSession, 1: AcademicSession}
     */
    private function sessions(): array
    {
        $session2026 = AcademicSession::query()->where('name', '2026')->firstOrFail();

        $session2025 = AcademicSession::query()->updateOrCreate(
            ['name' => '2025'],
            ['start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_current' => false],
        );

        return [$session2025, $session2026];
    }

    /**
     * Classes 1–10 of the branch (seeded by SchoolClassSeeder), keyed by level,
     * each with its section A eager loaded.
     *
     * @return array<int, SchoolClass>
     */
    private function classes(Branch $branch): array
    {
        return SchoolClass::query()
            ->where('branch_id', $branch->id)
            ->with('sections')
            ->orderBy('numeric_level')
            ->get()
            ->keyBy('numeric_level')
            ->all();
    }

    /**
     * Five core subjects per class, with globally-unique codes.
     *
     * @param  array<int, SchoolClass>  $classes
     * @return array<int, Collection<int, Subject>> keyed by class level
     */
    private function subjects(array $classes): array
    {
        $defs = ['Bangla' => 'BAN', 'English' => 'ENG', 'Mathematics' => 'MAT', 'Science' => 'SCI', 'Religion' => 'REL'];

        $byClass = [];

        foreach ($classes as $level => $class) {
            $byClass[$level] = collect($defs)->map(fn (string $code, string $name): Subject => Subject::create([
                'class_id' => $class->id,
                'name' => $name,
                'code' => $code.$level,
                'full_marks' => 100,
                'pass_marks' => 33,
            ]))->values();
        }

        return $byClass;
    }

    /**
     * Ten teachers, each assigned to subjects across the classes (round-robin)
     * for the current session, plus a class-teacher (section) assignment.
     *
     * @param  array<int, SchoolClass>  $classes
     * @param  array<int, Collection<int, Subject>>  $subjects
     * @return list<Teacher>
     */
    private function teachers(Branch $branch, AcademicSession $session, array $classes, array $subjects): array
    {
        $designations = ['Assistant Teacher', 'Senior Teacher', 'Head Teacher'];

        $teachers = [];
        for ($i = 0; $i < 10; $i++) {
            $user = $this->user($branch, fake()->name('male'), 'teacher'.($i + 1).'@'.strtolower($branch->code).'.demo');
            $user->assignRole('teacher');

            $teachers[] = Teacher::create([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'designation' => $designations[$i % count($designations)],
                'joining_date' => fake()->dateTimeBetween('-6 years', '-1 year')->format('Y-m-d'),
                'status' => TeacherStatus::Active,
            ]);
        }

        $cursor = 0;
        foreach ($classes as $level => $class) {
            foreach ($subjects[$level] as $subject) {
                TeacherAssignment::create([
                    'teacher_id' => $teachers[$cursor % 10]->id,
                    'session_id' => $session->id,
                    'class_id' => $class->id,
                    'section_id' => null,
                    'subject_id' => $subject->id,
                ]);
                $cursor++;
            }

            TeacherAssignment::create([
                'teacher_id' => $teachers[($level - 1) % 10]->id,
                'session_id' => $session->id,
                'class_id' => $class->id,
                'section_id' => $this->sectionA($class)->id,
                'subject_id' => null,
            ]);
        }

        return $teachers;
    }

    /**
     * The 2025 promotion cohort: real students admitted through the admission
     * approval service into Class 1, a full three-exam cycle with marks,
     * published per-exam + annual results, then a bulk promotion into Class 2
     * of the 2026 session.
     *
     * @param  array<int, SchoolClass>  $classes
     * @param  array<int, Collection<int, Subject>>  $subjects
     */
    private function promotionCohort(
        Branch $branch,
        AcademicSession $session2025,
        AcademicSession $session2026,
        array $classes,
        array $subjects,
        User $admin,
    ): void {
        $class1 = $classes[1];
        $sectionA = $this->sectionA($class1);
        $admissions = app(AdmissionService::class);

        for ($i = 1; $i <= self::PROMOTION_COHORT; $i++) {
            $application = AdmissionApplication::factory()->create([
                'branch_id' => $branch->id,
                'desired_class_id' => $class1->id,
                'father_mobile' => sprintf('0172%07d', $i),
                'mother_mobile' => sprintf('0182%07d', $i),
                'status' => AdmissionStatus::Pending,
            ]);

            $admissions->approve($application, [
                'session_id' => $session2025->id,
                'class_id' => $class1->id,
                'section_id' => $sectionA->id,
                'roll_no' => $i,
                'create_parent_account' => true,
                'parent_relation' => fake()->randomElement(['father', 'mother']),
                'admission_no' => null,
            ]);
        }

        // Three exams, full marks for every subject, then results + annual.
        $exams = [];
        foreach (ExamType::cases() as $type) {
            $exams[] = Exam::create([
                'branch_id' => $branch->id,
                'session_id' => $session2025->id,
                'class_id' => $class1->id,
                'type' => $type,
                'name' => $this->examName($type).' 2025',
                'start_date' => '2025-'.sprintf('%02d', count($exams) * 4 + 4).'-01',
                'end_date' => '2025-'.sprintf('%02d', count($exams) * 4 + 4).'-10',
                'status' => ExamStatus::Completed,
            ]);
        }

        $enrollments = Enrollment::query()
            ->where('session_id', $session2025->id)
            ->where('class_id', $class1->id)
            ->where('status', EnrollmentStatus::Active)
            ->get();

        $marks = app(MarkService::class);
        foreach ($exams as $exam) {
            foreach ($subjects[1] as $subject) {
                $rows = $enrollments->map(fn (Enrollment $e): array => [
                    'enrollment_id' => $e->id,
                    'obtained_marks' => fake()->numberBetween(40, 98),
                ])->all();

                $marks->saveBulk($exam, $subject->id, $rows, $admin);
            }
        }

        $results = app(ResultService::class);
        foreach ($exams as $exam) {
            $results->generateExamResults($exam);
        }
        foreach ($exams as $exam) {
            $results->publishExamResults($exam);
        }

        $annual = app(AnnualResultService::class);
        $annual->generate($session2025->id, $class1->id);
        $annual->publish($session2025->id, $class1->id);

        app(PromotionService::class)->bulk([
            'from_session_id' => $session2025->id,
            'from_class_id' => $class1->id,
            'to_session_id' => $session2026->id,
            'to_section_id' => $this->sectionA($classes[2])->id,
            'roll_strategy' => 'keep',
        ], $admin);
    }

    /**
     * The current-session population: fresh students enrolled directly into the
     * 2026 session across every class, roughly 60% with a linked parent login.
     *
     * @param  array<int, SchoolClass>  $classes
     */
    private function currentCohort(Branch $branch, AcademicSession $session, array $classes): void
    {
        foreach ($classes as $level => $class) {
            $sectionA = $this->sectionA($class);

            for ($n = 0; $n < self::CURRENT_PER_CLASS; $n++) {
                $student = $this->currentStudent($branch);

                Enrollment::create([
                    'student_id' => $student->id,
                    'session_id' => $session->id,
                    'class_id' => $class->id,
                    'section_id' => $sectionA->id,
                    'roll_no' => self::CURRENT_ROLL_BASE + $n,
                    'status' => EnrollmentStatus::Active,
                ]);

                if (fake()->boolean(60)) {
                    $this->linkParent($branch, $student);
                }
            }
        }
    }

    /**
     * Create one active student with its login.
     */
    private function currentStudent(Branch $branch): Student
    {
        $user = $this->user($branch, fake()->name(), null);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'status' => StudentStatus::Active,
        ]);
    }

    /**
     * Create a parent login + profile and link it to the student.
     */
    private function linkParent(Branch $branch, Student $student): void
    {
        $user = $this->user($branch, $student->father_name_en, null);
        $user->assignRole('parent');

        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'name' => $student->father_name_en,
            'phone' => $user->phone,
            'relation' => 'father',
        ]);

        $parent->students()->attach($student->id);
    }

    /**
     * A month of attendance for one section's current enrollments — weekdays
     * (Friday/Saturday excluded), mostly present. Bulk-inserted per the
     * performance rules.
     */
    private function attendance(AcademicSession $session, SchoolClass $class, User $admin): void
    {
        $enrollmentIds = Enrollment::query()
            ->where('session_id', $session->id)
            ->where('class_id', $class->id)
            ->where('status', EnrollmentStatus::Active)
            ->pluck('id');

        if ($enrollmentIds->isEmpty()) {
            return;
        }

        $dates = [];
        $day = Carbon::create(2026, 5, 1);
        $end = Carbon::create(2026, 5, 31);
        for (; $day->lte($end); $day->addDay()) {
            // Friday (5) and Saturday (6) are the weekend.
            if (! in_array($day->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $dates[] = $day->toDateString();
            }
        }

        $now = now();
        $rows = [];
        foreach ($enrollmentIds as $enrollmentId) {
            foreach ($dates as $date) {
                $rows[] = [
                    'enrollment_id' => $enrollmentId,
                    'date' => $date,
                    'status' => fake()->randomElement([
                        AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Present,
                        AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Absent,
                        AttendanceStatus::Leave,
                    ])->value,
                    'recorded_by' => $admin->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StudentAttendance::query()->insert($chunk);
        }
    }

    /**
     * Fee structures for every class, two months of generated invoices, and
     * mixed payments (cash + fake-gateway) — each settled payment posting one
     * income row through the real settlement pipeline.
     *
     * @param  array<int, SchoolClass>  $classes
     */
    private function feesAndPayments(Branch $branch, AcademicSession $session, array $classes, User $admin): void
    {
        foreach ($classes as $level => $class) {
            FeeStructure::create([
                'branch_id' => $branch->id,
                'session_id' => $session->id,
                'class_id' => $class->id,
                'monthly_fee' => sprintf('%d.00', 800 + $level * 100),
            ]);
        }

        $invoiceService = app(InvoiceService::class);
        $invoiceService->generate(1, 2026);
        $invoiceService->generate(2, 2026);

        $payments = app(PaymentService::class);

        Invoice::query()
            ->where('status', InvoiceStatus::Unpaid)
            ->orderBy('id')
            ->get()
            ->each(function (Invoice $invoice, int $index) use ($payments, $admin): void {
                // A realistic ageing mix: the prior month (1) is mostly
                // collected, the current month (2) is left outstanding.
                if ($invoice->month !== 1 || ! fake()->boolean(70)) {
                    return;
                }

                if ($index % 2 === 0) {
                    $payments->collectLocal($invoice, $invoice->amount, $admin);

                    return;
                }

                // Fake-gateway settlement: a pending SSLCommerz payment driven
                // straight through the shared settle() pipeline.
                $payment = Payment::create([
                    'branch_id' => $invoice->branch_id,
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount,
                    'method' => PaymentMethod::Sslcommerz,
                    'status' => PaymentStatus::Pending,
                    'transaction_id' => 'TXN-'.Str::uuid(),
                    'collected_by' => $admin->id,
                ]);
                $payments->settle($payment);
            });
    }

    /**
     * Manual finance: donations (income), operating expenses, and assets in a
     * mix of statuses.
     */
    private function finance(Branch $branch, User $admin): void
    {
        $incomeCategories = Category::query()
            ->where('branch_id', $branch->id)
            ->where('type', CategoryType::Income)
            ->pluck('id');

        $expenseCategories = Category::query()
            ->where('branch_id', $branch->id)
            ->where('type', CategoryType::Expense)
            ->pluck('id');

        for ($i = 0; $i < 6; $i++) {
            Income::create([
                'branch_id' => $branch->id,
                'category_id' => $incomeCategories->random(),
                'payment_id' => null,
                'title' => fake()->randomElement(['Annual donation', 'Alumni contribution', 'Charity fund', 'Event sponsorship']),
                'amount' => (string) fake()->numberBetween(5_000, 50_000).'.00',
                'date' => fake()->dateTimeBetween('2026-01-01', '2026-02-28')->format('Y-m-d'),
                'description' => fake()->optional()->sentence(),
                'created_by' => $admin->id,
            ]);
        }

        for ($i = 0; $i < 15; $i++) {
            Expense::create([
                'branch_id' => $branch->id,
                'category_id' => $expenseCategories->random(),
                'item_name' => fake()->randomElement(['Electricity bill', 'Staff salary', 'Classroom repair', 'Stationery purchase', 'Water bill', 'Internet bill']),
                'amount' => (string) fake()->numberBetween(2_000, 40_000).'.00',
                'date' => fake()->dateTimeBetween('2026-01-01', '2026-02-28')->format('Y-m-d'),
                'description' => fake()->optional()->sentence(),
                'created_by' => $admin->id,
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            Asset::create([
                'branch_id' => $branch->id,
                'name' => fake()->randomElement(['Desktop Computer', 'Projector', 'Classroom Bench', 'Whiteboard', 'Printer', 'Office Chair']),
                'description' => fake()->optional()->sentence(),
                'value' => (string) fake()->numberBetween(3_000, 80_000).'.00',
                'purchase_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
                'status' => fake()->randomElement([AssetStatus::InUse, AssetStatus::InUse, AssetStatus::Damaged, AssetStatus::Disposed]),
                'created_by' => $admin->id,
            ]);
        }
    }

    /**
     * Issue one transfer certificate (the only stored PDF) for an active
     * student in a class outside the exam/attendance demos.
     */
    private function transferCertificate(AcademicSession $session, SchoolClass $class): void
    {
        $student = Student::query()
            ->where('status', StudentStatus::Active)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('session_id', $session->id)
                ->where('class_id', $class->id)
                ->where('status', EnrollmentStatus::Active))
            ->first();

        if ($student === null) {
            return;
        }

        app(TransferCertificateService::class)->issue($student, [
            'reason' => 'Family relocating to another district',
            'issue_date' => '2026-03-15',
        ]);
    }

    /**
     * Whitelist localhost for teacher check-in on this branch.
     */
    private function whitelist(Branch $branch): void
    {
        CheckinIpWhitelist::create([
            'branch_id' => $branch->id,
            'ip_address' => '127.0.0.1',
            'label' => 'School Office',
            'is_active' => true,
        ]);
    }

    /**
     * Create a user with the demo password and a globally-unique phone.
     */
    private function user(Branch $branch, string $name, ?string $email): User
    {
        return User::create([
            'branch_id' => $branch->id,
            'name' => $name,
            'email' => $email,
            'phone' => sprintf('01%09d', $this->phoneSeq++),
            'password' => $this->passwordHash ??= bcrypt('password'),
            'is_active' => true,
        ]);
    }

    /**
     * The "A" section of a class (seeded by SchoolClassSeeder).
     */
    private function sectionA(SchoolClass $class): Section
    {
        return $class->sections->firstWhere('name', 'A') ?? $class->sections->first();
    }

    /**
     * The display name for an exam type.
     */
    private function examName(ExamType $type): string
    {
        return match ($type) {
            ExamType::FirstSemester => 'First Semester',
            ExamType::SecondSemester => 'Second Semester',
            ExamType::Final => 'Final Exam',
        };
    }
}
