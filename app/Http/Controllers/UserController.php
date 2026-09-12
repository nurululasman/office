<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\UserSignatureStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('users.index', ['users' => User::query()->with('roles')->orderBy('name')->paginate(20)]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('view', $user);

        return view('users.edit', [
            'managedUser' => $user->load('roles'),
            'roles' => Role::query()->orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }

    public function update(
        UpdateUserAccessRequest $request,
        User $user,
        UserSignatureStorage $signatures,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $request->user();

        DB::transaction(function () use ($request, $user, $validated, $actor, $signatures, $audit): void {
            $before = [
                'name' => $user->name,
                'signature_path' => $user->signature_path,
                'is_active' => $user->is_active,
                'roles' => $user->roles()->pluck('slug')->sort()->values()->all(),
            ];

            $data = [];
            if (isset($validated['name']) && $validated['name'] !== '') {
                $data['name'] = $validated['name'];
            }
            if ($request->hasFile('signature')) {
                $data += $signatures->store($request->file('signature'));
            }
            if (isset($validated['is_active']) && ! $actor->is($user)) {
                $data['is_active'] = $validated['is_active'];
            }

            if (! empty($data)) {
                $user->update($data);
            }

            if (isset($validated['roles']) && ! $actor->is($user)) {
                $user->roles()->sync($validated['roles']);
            }

            $after = [
                'name' => $user->name,
                'signature_path' => $user->signature_path,
                'is_active' => $user->is_active,
                'roles' => $user->roles()->pluck('slug')->sort()->values()->all(),
            ];

            $audit->record('authorization.user_access.updated', actor: $actor, subject: $user, before: $before, after: $after, request: $request);
        });

        return redirect()->route('users.index')->with('status', 'Data user berhasil diperbarui.');
    }
}
