<?php

namespace App\Http\Resources;

use App\Models\ParentProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A parent/guardian profile with its login email (from the users row) and the
 * compact list of linked students. The user and students relations must be
 * eager loaded; students is only serialized when present.
 *
 * @mixin ParentProfile
 */
class ParentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->user?->email,
            'relation' => $this->relation,
            'students' => $this->whenLoaded(
                'students',
                fn () => LinkedStudentResource::collection($this->students)->resolve($request),
            ),
        ];
    }
}
