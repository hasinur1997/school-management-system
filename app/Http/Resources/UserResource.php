<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'branch_id' => $this->whenLoaded('branch', fn () => $this->branch?->public_id),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch === null ? null : [
                'id' => $this->branch->public_id,
                'name' => $this->branch->name,
            ]),
            'student_id' => $this->whenLoaded('student', fn () => $this->student?->public_id),
            'student' => $this->whenLoaded('student', fn () => $this->student === null ? null : [
                'id' => $this->student->public_id,
                'name' => $this->student->name_en ?? $this->student->name_bn,
            ]),
            'is_active' => $this->is_active,
            'photo_url' => $this->photoUrl(),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->effectivePermissions(),
        ];
    }

    /**
     * Effective permission names; super admins bypass checks via Gate::before,
     * so their effective set is every permission.
     *
     * @return Collection<int, string>
     */
    private function effectivePermissions(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::pluck('name')->sort()->values();
        }

        return $this->getAllPermissions()->pluck('name')->sort()->values();
    }
}
