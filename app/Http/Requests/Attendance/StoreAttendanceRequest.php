<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a bulk attendance save: a scope in the caller's branch — a section,
 * or a class for a whole-class ("all sections") save — a date that is not in
 * the future, and a list of (enrollment, status) records. Each enrollment must
 * be active and belong to the scope — TC/inactive/foreign enrollments report
 * keyed at errors.records.N.enrollment_id. The teacher assignment check (403)
 * lives in AttendanceService, not here.
 */
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required_without:section_id', 'nullable', 'integer', Rule::exists('school_classes', 'id')],
            'section_id' => ['required_without:class_id', 'nullable', 'integer', Rule::exists('sections', 'id')],
            'date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
        ];
    }

    /**
     * Branch-scope the section/class, reject future dates, and verify every
     * enrollment is an active member of the scope. All lookups carry the
     * branch scope, so foreign rows are indistinguishable from invalid ones.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $section = null;
            if ($this->filled('section_id')) {
                $section = Section::find($this->integer('section_id'));

                if ($section === null) {
                    $validator->errors()->add('section_id', 'The selected section is invalid.');

                    return;
                }
            }

            if ($section === null && SchoolClass::find($this->integer('class_id')) === null) {
                $validator->errors()->add('class_id', 'The selected class is invalid.');

                return;
            }

            if ($this->filled('class_id') && $section !== null && $section->class_id !== $this->integer('class_id')) {
                $validator->errors()->add('section_id', 'The selected section is not of this class.');

                return;
            }

            if (Carbon::parse($this->validated('date'))->toDateString() > Carbon::today()->toDateString()) {
                $validator->errors()->add('date', 'Attendance cannot be taken for a future date.');

                return;
            }

            $activeEnrollmentIds = Enrollment::query()
                ->when(
                    $section !== null,
                    fn ($query) => $query->where('section_id', $section->id),
                    fn ($query) => $query->where('class_id', $this->integer('class_id')),
                )
                ->where('status', EnrollmentStatus::Active)
                ->pluck('id')
                ->all();

            foreach ($this->validated('records') as $index => $record) {
                if (! in_array($record['enrollment_id'], $activeEnrollmentIds, true)) {
                    $validator->errors()->add(
                        "records.{$index}.enrollment_id",
                        $section !== null
                            ? 'The selected enrollment is not an active member of this section.'
                            : 'The selected enrollment is not an active member of this class.',
                    );
                }
            }
        }];
    }
}
