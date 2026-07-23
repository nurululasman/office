<?php

namespace App\Http\Requests;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('company_profile');

        return $profile instanceof CompanyProfile
            ? $this->user()?->can('update', $profile) === true
            : $this->user()?->can('create', CompanyProfile::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $profile = $this->route('company_profile');

        return [
            'company_code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('company_profiles', 'company_code')->ignore($profile?->getKey()),
            ],
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'address_lines_text' => ['required', 'string', 'max:5000'],
            'city' => ['required', 'string', 'max:150'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->addressLines() === []) {
                    $validator->errors()->add(
                        'address_lines_text',
                        'The address must contain at least one non-empty line.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_code' => strtoupper(trim((string) $this->input('company_code'))),
            'country' => strtoupper(trim((string) $this->input('country', 'ID'))),
            'primary_color' => strtoupper(trim((string) $this->input('primary_color'))) ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function profileData(): array
    {
        $data = $this->safe()->except(['address_lines_text', 'logo']);
        $data['address_lines'] = $this->addressLines();

        return $data;
    }

    /** @return array<int, string> */
    private function addressLines(): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $this->string('address_lines_text')->toString()) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
