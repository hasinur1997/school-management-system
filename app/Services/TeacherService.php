<?php

namespace App\Services;

use App\Enums\TeacherStatus;
use App\Jobs\SendCredentials;
use App\Models\AcademicSession;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeacherService
{
    /**
     * Create a teacher: a login (random password, teacher role, creator's
     * branch) plus the profile, atomically. The credential job is dispatched
     * only after the transaction commits so it never fires on a rollback.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $branchId): Teacher
    {
        return DB::transaction(function () use ($data, $branchId): Teacher {
            $password = Str::password(10);

            $user = User::create([
                'branch_id' => $branchId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $user->assignRole('teacher');

            // branch_id is guarded, and super admins bypass BranchScope's
            // auto-stamping, so set the resolved branch explicitly (for branch
            // users it matches their own).
            $teacher = new Teacher([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'designation' => $data['designation'],
                'joining_date' => $data['joining_date'] ?? null,
                'status' => TeacherStatus::Active,
            ]);
            $teacher->branch_id = $branchId;
            $teacher->save();

            SendCredentials::dispatch($user, $password, 'Teacher')->afterCommit();

            return $teacher;
        });
    }

    /**
     * Regenerate a teacher's login password, revoke every existing token, and
     * queue fresh credentials. Inactive teachers are rejected (409). The new
     * password is stored only as a hash; the plaintext lives on the queued job.
     */
    public function resendCredentials(Teacher $teacher): void
    {
        if ($teacher->status !== TeacherStatus::Active) {
            abort(409, 'Teacher is inactive.');
        }

        DB::transaction(function () use ($teacher): void {
            $password = Str::password(10);
            $user = $teacher->user;

            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();

            SendCredentials::dispatch($user, $password, 'Teacher')->afterCommit();
        });
    }

    /**
     * List teachers in the caller's branch, filtered by status and a free-text
     * search across name/email/phone/designation, sorted and paginated. Media
     * is eager loaded so photo_url never lazy loads in the Resource.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? ($sort === 'name' ? 'asc' : 'desc');

        return Teacher::query()
            ->with('media')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('designation', 'like', $term));
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    /**
     * Load a teacher for the show endpoint: media plus the assignments for the
     * current academic session, each with class/section/subject names.
     */
    public function loadProfile(Teacher $teacher): Teacher
    {
        $currentSessionId = AcademicSession::where('is_current', true)->value('id');

        return $teacher->load([
            'media',
            'user',
            'assignments' => fn ($query) => $query
                ->where('session_id', $currentSessionId)
                ->with(['schoolClass', 'section', 'subject']),
        ]);
    }

    /**
     * Update mutable profile fields. email is immutable (login identity) and is
     * rejected at validation. The phone is mirrored onto the login so the
     * teacher can still sign in with it.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Teacher $teacher, array $data): Teacher
    {
        return DB::transaction(function () use ($teacher, $data): Teacher {
            $teacher->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'designation' => $data['designation'],
                'joining_date' => $data['joining_date'] ?? null,
            ]);

            $teacher->user->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
            ]);

            return $teacher->load(['media', 'user']);
        });
    }

    /**
     * Flip a teacher's status. Going inactive disables the login and revokes
     * every token so any active session is cut immediately; going active
     * re-enables the login.
     */
    public function setStatus(Teacher $teacher, TeacherStatus $status): Teacher
    {
        return DB::transaction(function () use ($teacher, $status): Teacher {
            $teacher->update(['status' => $status]);

            $isActive = $status === TeacherStatus::Active;
            $teacher->user->update(['is_active' => $isActive]);

            if (! $isActive) {
                $teacher->user->tokens()->delete();
            }

            return $teacher->load(['media', 'user']);
        });
    }

    /**
     * Store/replace the teacher's photo. The collection is single-file, so the
     * previous photo is removed automatically.
     */
    public function setPhoto(Teacher $teacher, UploadedFile $photo): Teacher
    {
        $teacher->addMedia($photo)->toMediaCollection('photo');

        return $teacher->load(['media', 'user']);
    }
}
