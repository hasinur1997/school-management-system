<?php

namespace App\Http\Requests\TeacherAttendance;

use App\Enums\TeacherAttendanceStatus;
use App\Models\TeacherAttendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an admin correction to a teacher attendance record. Every field is
 * optional, but check-out may never precede check-in: the order is checked
 * against the record's current values when only one side is supplied. The
 * record is resolved branch-scoped via route-model binding (out-of-branch →
 * 404), so it always exists here.
 */
class UpdateTeacherAttendanceRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(TeacherAttendanceStatus::class)],
            'check_in_at' => ['sometimes', 'nullable', 'date'],
            'check_out_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Reject a check-out that lands at or before check-in, resolving each side
     * from the request when present and falling back to the stored record.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var TeacherAttendance $record */
            $record = $this->route('teacherAttendance');

            $checkIn = $this->has('check_in_at')
                ? $this->resolveTime('check_in_at')
                : $record->check_in_at;

            $checkOut = $this->has('check_out_at')
                ? $this->resolveTime('check_out_at')
                : $record->check_out_at;

            if ($checkIn !== null && $checkOut !== null && $checkOut->lessThanOrEqualTo($checkIn)) {
                $validator->errors()->add('check_out_at', 'Check-out must be after check-in.');
            }
        }];
    }

    /**
     * Parse a supplied datetime field to a Carbon instance, or null when blank.
     */
    private function resolveTime(string $key): ?Carbon
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : Carbon::parse($value);
    }
}
