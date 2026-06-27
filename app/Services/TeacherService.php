<?php

namespace App\Services;

use App\Enums\TeacherStatus;
use App\Jobs\SendCredentials;
use App\Models\AcademicSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
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

            return $teacher->load(['media', 'user']);
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
     * search across name/user email/phone/designation, sorted and paginated.
     * Media and user are eager loaded so the Resource never lazy loads them.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? ($sort === 'name' ? 'asc' : 'desc');

        return $this->filtered($filters)
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    /**
     * List soft-deleted teachers in the caller's branch, most-recently-trashed
     * first, with the same search/status filters as the live list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listTrashed(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
    }

    /**
     * Base teacher query: branch-scoped, eager-loading media + user, with the
     * shared status and free-text (name/email/phone/designation) filters
     * applied. Shared by the live list and the trash list.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Teacher>
     */
    private function filtered(array $filters): Builder
    {
        return Teacher::query()
            ->with(['media', 'user'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $term)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $term))
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('designation', 'like', $term));
            });
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
     * Update mutable profile fields. Email and phone belong to the login user;
     * email is mirrored into the legacy teacher column until that schema debt is
     * removed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Teacher $teacher, array $data): Teacher
    {
        return DB::transaction(function () use ($teacher, $data): Teacher {
            $teacher->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'designation' => $data['designation'],
                'joining_date' => $data['joining_date'] ?? null,
            ]);

            $teacher->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
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

    /**
     * Soft-delete a teacher (move to trash) and disable the linked login,
     * revoking its tokens so any active session is cut immediately. The teacher
     * can be restored later.
     */
    public function delete(Teacher $teacher): void
    {
        DB::transaction(function () use ($teacher): void {
            $teacher->delete();

            $user = $teacher->user()->withTrashed()->first();

            if ($user !== null) {
                $user->update(['is_active' => false]);
                $user->tokens()->delete();
            }
        });
    }

    /**
     * Soft-delete many teachers by public id. Ids are resolved branch-scoped,
     * so foreign ids are silently skipped.
     *
     * @param  list<string>  $publicIds
     */
    public function bulkDelete(array $publicIds): int
    {
        $teachers = Teacher::query()
            ->whereIn('public_id', $publicIds)
            ->get();

        foreach ($teachers as $teacher) {
            $this->delete($teacher);
        }

        return $teachers->count();
    }

    /**
     * Restore a trashed teacher and re-enable the login when the teacher's
     * status is active (an inactive teacher stays disabled on restore).
     */
    public function restore(Teacher $teacher): Teacher
    {
        return DB::transaction(function () use ($teacher): Teacher {
            $teacher->restore();

            $user = $teacher->user()->withTrashed()->first();

            if ($user !== null) {
                $user->update(['is_active' => $teacher->status === TeacherStatus::Active]);
            }

            return $teacher;
        });
    }

    /**
     * Restore many trashed teachers by public id (branch-scoped resolution).
     *
     * @param  list<string>  $publicIds
     */
    public function bulkRestore(array $publicIds): int
    {
        $teachers = Teacher::onlyTrashed()
            ->whereIn('public_id', $publicIds)
            ->get();

        foreach ($teachers as $teacher) {
            $this->restore($teacher);
        }

        return $teachers->count();
    }

    /**
     * Permanently delete a trashed teacher: remove the session assignments,
     * then the teacher (sections' class_teacher_id nulls out via the FK), then
     * the linked login. Blocked when dependent attendance history exists.
     */
    public function forceDelete(Teacher $teacher): void
    {
        $this->assertForceDeletable($teacher);

        DB::transaction(function () use ($teacher): void {
            $user = $teacher->user()->withTrashed()->first();

            $teacher->assignments()->delete();
            $teacher->forceDelete();

            if ($user !== null) {
                $user->tokens()->delete();
                $user->syncRoles([]);
                $user->forceDelete();
            }
        });
    }

    /**
     * Permanently delete many trashed teachers by public id. Every target is
     * checked deletable first so the batch fails before any row is removed.
     *
     * @param  list<string>  $publicIds
     */
    public function bulkForceDelete(array $publicIds): int
    {
        $teachers = Teacher::onlyTrashed()
            ->whereIn('public_id', $publicIds)
            ->get();

        foreach ($teachers as $teacher) {
            $this->assertForceDeletable($teacher);
        }

        foreach ($teachers as $teacher) {
            $this->forceDelete($teacher);
        }

        return $teachers->count();
    }

    /**
     * Guard permanent deletion: the teacher must already be trashed and must
     * carry no dependent attendance history (those FKs restrict on delete).
     */
    private function assertForceDeletable(Teacher $teacher): void
    {
        abort_unless($teacher->trashed(), 409, 'Teacher must be in trash before permanent deletion.');

        $hasHistory = TeacherAttendance::withoutGlobalScopes()
            ->where('teacher_id', $teacher->id)
            ->exists();

        abort_if(
            $hasHistory,
            409,
            'Teacher has dependent records and cannot be permanently deleted.',
        );
    }
}
