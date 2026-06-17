<?php

namespace App\Http\Requests\Setting;

use App\Settings\SettingRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a bulk settings upsert. Every key must be known (else 422 under
 * `settings.<key>`) and its value must match the key's declared type. Branch
 * scoping resolves from the caller (super admins may target another branch via
 * branch_id); a branch-scoped key with no resolvable branch is rejected.
 */
class UpdateSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $settings = $this->input('settings');

                if (! is_array($settings)) {
                    return;
                }

                $branchId = $this->resolvedBranchId();

                foreach ($settings as $key => $value) {
                    if (! is_string($key) || ! SettingRegistry::has($key)) {
                        $validator->errors()->add("settings.{$key}", 'Unknown setting');

                        continue;
                    }

                    if ($error = SettingRegistry::validate($key, $value)) {
                        $validator->errors()->add("settings.{$key}", $error);

                        continue;
                    }

                    if (! SettingRegistry::isGlobal($key) && $branchId === null) {
                        $validator->errors()->add("settings.{$key}", 'A branch is required for this setting');
                    }
                }
            },
        ];
    }

    /**
     * The branch a per-branch setting targets: the caller's branch, or for a
     * super admin the explicit branch_id (null when none is given).
     */
    public function resolvedBranchId(): ?int
    {
        $user = $this->user();

        if ($user->isSuperAdmin()) {
            return $this->integer('branch_id') ?: null;
        }

        return $user->branch_id;
    }
}
