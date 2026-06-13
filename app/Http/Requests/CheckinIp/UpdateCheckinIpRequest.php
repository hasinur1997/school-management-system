<?php

namespace App\Http\Requests\CheckinIp;

use App\Models\CheckinIpWhitelist;
use App\Rules\IpOrCidr;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckinIpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CheckinIpWhitelist $entry */
        $entry = $this->route('checkinIp');

        return [
            'ip_address' => [
                'sometimes',
                'string',
                'max:45',
                new IpOrCidr,
                Rule::unique('checkin_ip_whitelists', 'ip_address')
                    ->where('branch_id', $entry->branch_id)
                    ->ignore($entry->id),
            ],
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
