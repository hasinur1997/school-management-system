<?php

namespace App\Http\Requests\GradingScale;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a full-replace of the global grading scale: every band well-formed,
 * the ranges covering 0–100 inclusive with no gaps or overlaps, exactly one
 * failing band, and grade points strictly descending as marks fall.
 */
class UpdateGradingScaleRequest extends FormRequest
{
    /**
     * Authorization is enforced by the setting.manage route middleware.
     */
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
            'scale' => ['required', 'array', 'min:1'],
            'scale.*.grade' => ['required', 'string', 'max:5'],
            'scale.*.min_marks' => ['required', 'integer', 'between:0,100'],
            'scale.*.max_marks' => ['required', 'integer', 'between:0,100'],
            'scale.*.grade_point' => ['required', 'numeric', 'between:0,9.99'],
            'scale.*.is_fail' => ['required', 'boolean'],
        ];
    }

    /**
     * Enforce the cross-band invariants: each band's min ≤ max, exactly one
     * failing band, full 0–100 coverage without gaps/overlaps, and strictly
     * descending grade points. The first violation wins so the top-level
     * message is the specific failure.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<array{grade: string, min_marks: int, max_marks: int, grade_point: float, is_fail: bool}> $bands */
            $bands = $this->validated('scale');

            foreach ($bands as $index => $band) {
                if ($band['min_marks'] > $band['max_marks']) {
                    $validator->errors()->add(
                        "scale.{$index}.min_marks",
                        'Band minimum marks cannot exceed its maximum marks.',
                    );

                    return;
                }
            }

            $failCount = count(array_filter($bands, fn (array $band): bool => (bool) $band['is_fail']));

            if ($failCount !== 1) {
                $validator->errors()->add('scale', 'Scale must have exactly one failing grade');

                return;
            }

            // Order ascending by floor so coverage and ordering can be checked
            // in a single sweep.
            usort($bands, fn (array $a, array $b): int => $a['min_marks'] <=> $b['min_marks']);

            if ($bands[0]['min_marks'] !== 0 || $bands[count($bands) - 1]['max_marks'] !== 100) {
                $validator->errors()->add('scale', 'Scale must cover 0–100 without gaps');

                return;
            }

            for ($i = 1, $n = count($bands); $i < $n; $i++) {
                $expected = $bands[$i - 1]['max_marks'] + 1;

                if ($bands[$i]['min_marks'] < $expected) {
                    $validator->errors()->add('scale', 'Scale ranges must not overlap');

                    return;
                }

                if ($bands[$i]['min_marks'] > $expected) {
                    $validator->errors()->add('scale', 'Scale must cover 0–100 without gaps');

                    return;
                }
            }

            // Highest band must hold the highest grade point: walking down the
            // marks, each grade point must be strictly lower than the one above.
            for ($i = $n - 1; $i > 0; $i--) {
                if ((float) $bands[$i - 1]['grade_point'] >= (float) $bands[$i]['grade_point']) {
                    $validator->errors()->add('scale', 'Grade points must descend as marks fall');

                    return;
                }
            }
        }];
    }
}
