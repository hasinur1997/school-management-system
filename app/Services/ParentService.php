<?php

namespace App\Services;

use App\Jobs\SendCredentials;
use App\Models\ParentProfile;
use App\Models\Scopes\BranchScope;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParentService
{
    /**
     * The relations a parent payload reads — eager loaded everywhere a parent
     * Resource is returned so nothing lazy loads under strict mode.
     *
     * @var array<int, string>
     */
    private const WITH = [
        'user',
        'students.media',
        'students.currentEnrollment.schoolClass',
        'students.currentEnrollment.section',
    ];

    /**
     * List parents in the caller's branch (branch scope is automatic),
     * filtered by a free-text search across name/phone, paginated.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return ParentProfile::query()
            ->with(self::WITH)
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create a parent: a login (random password, parent role, creator's branch)
     * plus the profile and the student links, atomically. The credential job is
     * dispatched only after the transaction commits so it never fires on a
     * rollback. student_ids are validated branch-scoped at the request layer.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ParentProfile
    {
        return DB::transaction(function () use ($data): ParentProfile {
            $password = Str::password(10);

            $user = User::create([
                'branch_id' => Auth::user()->branch_id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $user->assignRole('parent');

            // branch_id is stamped from the creator by BelongsToBranch.
            $parent = ParentProfile::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'relation' => $data['relation'],
            ]);

            $parent->students()->attach(collect($data['student_ids'])->unique()->all());

            SendCredentials::dispatch($user, $password, 'Parent')->afterCommit();

            return $parent->load(self::WITH);
        });
    }

    /**
     * Ensure exactly one parent account owns the submitted contact, then link it
     * to the student. A matching parent is reused by email or phone; a contact
     * already held by another account type is rejected before the database
     * unique constraints can surface as a low-level error.
     *
     * @return array{parent: ParentProfile, created: bool}
     */
    public function ensureLinkedAccount(
        int $branchId,
        string $name,
        string $phone,
        ?string $email,
        string $relation,
        Student $student,
    ): array {
        $email = $email !== null && trim($email) !== '' ? $email : null;
        $phone = trim($phone);

        if ($phone === '') {
            abort(422, 'A parent phone number is required.');
        }

        $matchedUsers = User::withTrashed()
            ->where(function (Builder $query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->where('email', $email)
                        ->orWhere('phone', $phone);

                    return;
                }

                $query->where('phone', $phone);
            })
            ->get();

        if ($matchedUsers->count() > 1) {
            abort(422, 'Parent email and phone belong to different accounts.');
        }

        if ($matchedUsers->count() === 1) {
            $user = $matchedUsers->first();
            $parent = ParentProfile::withoutGlobalScope(BranchScope::class)
                ->where('user_id', $user->id)
                ->first();

            if ($user->trashed() || $parent === null || ! $user->hasRole('parent')) {
                abort(422, 'The parent contact is already used by another account.');
            }

            if ((int) $parent->branch_id !== $branchId) {
                abort(422, 'The matched parent belongs to another branch.');
            }

            $parent->students()->syncWithoutDetaching([$student->id]);

            return ['parent' => $parent->load(self::WITH), 'created' => false];
        }

        $password = Str::password(10);

        $user = User::create([
            'branch_id' => $branchId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $user->assignRole('parent');

        $parent = new ParentProfile([
            'user_id' => $user->id,
            'name' => $name,
            'phone' => $phone,
            'relation' => $relation,
        ]);
        $parent->branch_id = $branchId;
        $parent->save();

        $parent->students()->syncWithoutDetaching([$student->id]);

        SendCredentials::dispatch($user, $password, 'Parent')->afterCommit();

        return ['parent' => $parent->load(self::WITH), 'created' => true];
    }

    /**
     * Regenerate the parent's password, revoke existing tokens, and queue fresh
     * login credentials. Credentials are delivered by email, so a parent without
     * an email on file has no delivery channel (422).
     */
    public function resendCredentials(ParentProfile $parent): void
    {
        $user = $parent->user;

        if ($user === null || $user->email === null) {
            abort(422, 'This parent has no email address on file; credentials cannot be sent.');
        }

        DB::transaction(function () use ($user): void {
            $password = Str::password(10);

            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();

            SendCredentials::dispatch($user, $password, 'Parent')->afterCommit();
        });
    }

    /**
     * Link a student to a parent. A duplicate link is a 409 conflict; the
     * student's branch validity is enforced at the request layer.
     */
    public function linkStudent(ParentProfile $parent, int $studentId): ParentProfile
    {
        if ($parent->isLinkedTo($studentId)) {
            abort(409, 'Student is already linked to this parent.');
        }

        $parent->students()->attach($studentId);

        return $parent->load(self::WITH);
    }

    /**
     * Unlink a student from a parent. A student that is not currently linked
     * (including a non-existent or out-of-branch one resolved upstream) is a
     * 404.
     */
    public function unlinkStudent(ParentProfile $parent, Student $student): void
    {
        if (! $parent->isLinkedTo($student->id)) {
            abort(404);
        }

        $parent->students()->detach($student->id);
    }

    /**
     * The students linked to the given parent login, in the compact shape, with
     * the current-session enrollment eager loaded. Returns an empty collection
     * when the user has no parent profile.
     *
     * @return Collection<int, Student>
     */
    public function studentsForUser(User $user): Collection
    {
        $parent = ParentProfile::where('user_id', $user->id)
            ->with([
                'students.media',
                'students.currentEnrollment.schoolClass',
                'students.currentEnrollment.section',
            ])
            ->first();

        return $parent?->students ?? new Collection;
    }
}
