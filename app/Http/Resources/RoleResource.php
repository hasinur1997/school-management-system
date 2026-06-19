<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array. `is_protected` is true for the
     * super_admin role only (it bypasses checks and cannot be edited).
     * `users_count` comes from withCount('users'); `permissions` are the
     * role's assigned permission names, sorted.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_protected' => $this->name === 'super_admin',
            'users_count' => (int) ($this->users_count ?? 0),
            'permissions' => $this->permissions->pluck('name')->sort()->values()->all(),
        ];
    }
}
