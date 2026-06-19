<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * One module group of the permission registry: the module key (the prefix
 * before the first dot) and the permissions it contains, each with a
 * human-readable label. The resource is constructed from a [module,
 * permissions] pair, not a single model.
 *
 * @property string $module
 * @property Collection<int, Permission> $permissions
 */
class PermissionGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'module' => $this->resource['module'],
            'permissions' => collect($this->resource['permissions'])
                ->map(fn (Permission $permission): array => [
                    'name' => $permission->name,
                    'label' => Str::headline(str_replace('.', ' ', $permission->name)),
                ])
                ->values()
                ->all(),
        ];
    }
}
