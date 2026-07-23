<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Services\Audit\AuditLogger;
use App\Services\CompanyProfiles\CompanyLogoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CompanyProfile::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        return view('company-profiles.index', [
            'profiles' => CompanyProfile::query()
                ->withCount('templates')
                ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                    $query->whereLike('company_code', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('legal_name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('display_name', "%{$search}%", caseSensitive: false);
                }))
                ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
                ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('company_code')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', CompanyProfile::class);

        return view('company-profiles.form', ['profile' => new CompanyProfile(['country' => 'ID', 'is_active' => true])]);
    }

    public function store(
        CompanyProfileRequest $request,
        CompanyLogoStorage $logos,
        AuditLogger $audit,
    ): RedirectResponse {
        $profile = DB::transaction(function () use ($request, $logos, $audit): CompanyProfile {
            $data = $request->profileData();
            if ($request->hasFile('logo')) {
                $data += $logos->store($request->file('logo'));
            }
            $profile = CompanyProfile::query()->create($data);
            $audit->record('company_profile.created', $request->user(), $profile, after: $this->auditState($profile), request: $request);

            return $profile;
        });

        return redirect()->route('company-profiles.show', $profile)->with('status', 'Company Profile berhasil dibuat.');
    }

    public function show(CompanyProfile $companyProfile): View
    {
        Gate::authorize('view', $companyProfile);

        return view('company-profiles.show', [
            'profile' => $companyProfile->loadCount('templates'),
            'templates' => $companyProfile->templates()->orderByDesc('version')->get(),
        ]);
    }

    public function edit(CompanyProfile $companyProfile): View
    {
        Gate::authorize('update', $companyProfile);

        return view('company-profiles.form', ['profile' => $companyProfile]);
    }

    public function update(
        CompanyProfileRequest $request,
        CompanyProfile $companyProfile,
        CompanyLogoStorage $logos,
        AuditLogger $audit,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $companyProfile, $logos, $audit): void {
            $locked = CompanyProfile::query()->lockForUpdate()->findOrFail($companyProfile->getKey());
            $before = $this->auditState($locked);
            $data = $request->profileData();
            if ($request->hasFile('logo')) {
                $data += $logos->store($request->file('logo'));
            }
            $locked->update($data);
            $audit->record('company_profile.updated', $request->user(), $locked, before: $before, after: $this->auditState($locked), request: $request);
        });

        return redirect()->route('company-profiles.show', $companyProfile)->with('status', 'Company Profile berhasil diperbarui.');
    }

    public function destroy(Request $request, CompanyProfile $companyProfile, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $companyProfile);
        if ($companyProfile->templates()->exists()) {
            return back()->withErrors('Company Profile yang sudah digunakan template tidak dapat dihapus. Nonaktifkan profile tersebut.');
        }

        $before = $this->auditState($companyProfile);
        $id = $companyProfile->getKey();
        $companyProfile->delete();
        $audit->record('company_profile.deleted', $request->user(), before: $before, context: ['company_profile_id' => $id], request: $request);

        return redirect()->route('company-profiles.index')->with('status', 'Company Profile yang belum digunakan berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function auditState(CompanyProfile $profile): array
    {
        return $profile->only([
            'company_code', 'legal_name', 'display_name', 'address_lines', 'city',
            'postal_code', 'country', 'email', 'phone', 'website', 'tax_id',
            'logo_path', 'logo_sha256', 'primary_color', 'is_active',
        ]);
    }
}
