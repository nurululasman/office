<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        if (! ($user instanceof User)) {
            return false;
        }

        $actor = $this->user();
        if (! $actor) {
            return false;
        }

        if ($actor->is($user) && ($this->has('roles') || $this->has('is_active'))) {
            return false;
        }

        if ($actor->is($user)) {
            return true;
        }

        if ($this->has('roles') && ! $actor->can('assignRoles', $user)) {
            return false;
        }

        return $actor->can('update', $user);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => [Rule::exists(Role::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }
}
