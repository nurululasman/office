<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'activeUsers' => $user->can('users.read') ? User::query()->where('is_active', true)->count() : null,
            'roles' => $user->can('roles.read') ? Role::query()->count() : null,
            'pendingJobs' => $user->can('roles.read') ? DB::table('jobs')->count() : null,
            'recentAudits' => $user->can('audit-logs.read')
                ? AuditLog::query()->with('actor')->latest('occurred_at')->limit(8)->get()
                : collect(),
        ]);
    }
}
