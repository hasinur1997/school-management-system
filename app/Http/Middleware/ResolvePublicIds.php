<?php

namespace App\Http\Middleware;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicIds
{
    /**
     * @var array<string, class-string<Model>>
     */
    private array $models = [
        'branch_id' => Branch::class,
        'category_id' => Category::class,
        'class_id' => SchoolClass::class,
        'desired_class_id' => SchoolClass::class,
        'enrollment_id' => Enrollment::class,
        'from_class_id' => SchoolClass::class,
        'from_enrollment_id' => Enrollment::class,
        'from_session_id' => AcademicSession::class,
        'invoice_id' => Invoice::class,
        'parent_id' => ParentProfile::class,
        'payment_id' => Payment::class,
        'role_id' => Role::class,
        'section_id' => Section::class,
        'session_id' => AcademicSession::class,
        'student_id' => Student::class,
        'subject_id' => Subject::class,
        'teacher_id' => Teacher::class,
        'to_class_id' => SchoolClass::class,
        'to_enrollment_id' => Enrollment::class,
        'to_section_id' => Section::class,
        'to_session_id' => AcademicSession::class,
        'user_id' => User::class,
    ];

    /**
     * Plural keys whose value is a list of public ids — each element is
     * resolved to its internal id (e.g. `class_ids => [hash, hash]`).
     *
     * @var array<string, class-string<Model>>
     */
    private array $arrayModels = [
        'class_ids' => SchoolClass::class,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace($this->resolve($request->query->all()));
        $request->request->replace($this->resolve($request->request->all()));

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolve(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                // A plural key (`class_ids`) carries a list of public ids; map
                // each element to its internal id. Otherwise recurse so nested
                // payloads (e.g. `marks[]`) still resolve their scalar keys.
                $payload[$key] = isset($this->arrayModels[$key])
                    ? array_map(fn ($item) => $this->resolveOne($this->arrayModels[$key], $item), $value)
                    : $this->resolve($value);

                continue;
            }

            if (! isset($this->models[$key])) {
                continue;
            }

            $payload[$key] = $this->resolveOne($this->models[$key], $value);
        }

        return $payload;
    }

    /**
     * Translate a single public id to its internal id, leaving non-string,
     * already-numeric, or unresolvable values untouched.
     *
     * @param  class-string<Model>  $model
     */
    private function resolveOne(string $model, mixed $value): mixed
    {
        if (! is_string($value) || ctype_digit($value)) {
            return $value;
        }

        $id = $model::query()->where('public_id', $value)->value('id');

        return $id ?? $value;
    }
}
