<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('viewAny', Permission::class);

        return view('permissions.index', ['permissionGroups' => Permission::query()->with('roles')->orderBy('group')->orderBy('slug')->get()->groupBy('group')]);
    }
}
