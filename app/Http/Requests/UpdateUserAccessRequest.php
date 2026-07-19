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

        return $user instanceof User
            && $this->user()?->can('update', $user) === true
            && $this->user()?->can('assignRoles', $user) === true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'roles' => ['present', 'array'],
            'roles.*' => [Rule::exists(Role::class, 'id')],
        ];
    }
}
