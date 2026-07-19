<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('roles.index', ['roles' => Role::query()->withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('roles.create', $this->formData());
    }

    public function store(RoleRequest $request, AuditLogger $audit): RedirectResponse
    {
        $role = DB::transaction(function () use ($request, $audit): Role {
            $role = Role::query()->create($request->safe()->except('permissions') + ['is_system' => false]);
            $role->permissions()->sync($request->validated('permissions'));
            $audit->record('authorization.role.created', actor: $request->user(), subject: $role, after: $this->snapshot($role), request: $request);

            return $role;
        });

        return redirect()->route('roles.edit', $role)->with('status', 'Role berhasil dibuat.');
    }

    public function edit(Role $role): View
    {
        Gate::authorize('view', $role);

        return view('roles.edit', $this->formData($role) + ['role' => $role->load('permissions')]);
    }

    public function update(RoleRequest $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        $before = $this->snapshot($role);
        DB::transaction(function () use ($request, $role, $before, $audit): void {
            $role->update($request->safe()->except('permissions'));
            $role->permissions()->sync($request->validated('permissions'));
            $audit->record('authorization.role.updated', actor: $request->user(), subject: $role, before: $before, after: $this->snapshot($role), request: $request);
        });

        return back()->with('status', 'Role berhasil diperbarui.');
    }

    public function destroy(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $role);
        if ($role->users()->exists()) {
            return back()->withErrors('Role yang masih digunakan tidak dapat dihapus. Lepaskan role dari seluruh user terlebih dahulu.');
        }
        $before = $this->snapshot($role);
        DB::transaction(function () use ($request, $role, $before, $audit): void {
            $id = $role->getKey();
            $role->permissions()->detach();
            $role->delete();
            $audit->record('authorization.role.deleted', actor: $request->user(), before: $before, context: ['role_id' => $id], request: $request);
        });

        return redirect()->route('roles.index')->with('status', 'Role berhasil dihapus.');
    }

    private function formData(?Role $role = null): array
    {
        return ['permissionGroups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group')];
    }

    private function snapshot(Role $role): array
    {
        return ['name' => $role->name, 'slug' => $role->slug, 'description' => $role->description, 'permissions' => $role->permissions()->pluck('slug')->sort()->values()->all()];
    }
}
