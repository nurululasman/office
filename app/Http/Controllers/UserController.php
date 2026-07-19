<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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

    public function update(UpdateUserAccessRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $before = ['is_active' => $user->is_active, 'roles' => $user->roles()->pluck('slug')->sort()->values()->all()];

        DB::transaction(function () use ($request, $user, $validated, $before, $audit): void {
            $user->update(['is_active' => $validated['is_active']]);
            $user->roles()->sync($validated['roles']);
            $after = ['is_active' => $user->is_active, 'roles' => $user->roles()->pluck('slug')->sort()->values()->all()];
            $audit->record('authorization.user_access.updated', actor: $request->user(), subject: $user, before: $before, after: $after, request: $request);
        });

        return redirect()->route('users.index')->with('status', 'Akses user berhasil diperbarui.');
    }
}
