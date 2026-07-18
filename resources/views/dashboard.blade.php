@extends('layouts.back.master')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Aplikasi Office</div>
                    <h1 class="page-title">Selamat datang, {{ auth()->user()->name }}</h1>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                @if($activeUsers !== null)
                    <div class="col-sm-6 col-lg-4"><div class="card"><div class="card-body"><div class="text-secondary">User aktif</div><div class="h1 mb-0">{{ $activeUsers }}</div></div></div></div>
                @endif
                @if($roles !== null)
                    <div class="col-sm-6 col-lg-4"><div class="card"><div class="card-body"><div class="text-secondary">Role tersedia</div><div class="h1 mb-0">{{ $roles }}</div></div></div></div>
                @endif
                @if($pendingJobs !== null)
                    <div class="col-sm-6 col-lg-4"><div class="card"><div class="card-body"><div class="text-secondary">Job menunggu</div><div class="h1 mb-0">{{ $pendingJobs }}</div></div></div></div>
                @endif
                @if($activeUsers === null && $roles === null && $pendingJobs === null)
                    <div class="col-12"><div class="card"><div class="card-body"><h2 class="card-title">Akun siap digunakan</h2><p class="text-secondary mb-0">Administrator belum memberikan akses modul Office kepada akun ini.</p></div></div></div>
                @endif
                @can('audit-logs.read')
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><h2 class="card-title">Aktivitas terbaru</h2></div>
                            <div class="table-responsive">
                                <table class="table card-table table-vcenter">
                                    <thead><tr><th>Waktu</th><th>Aktor</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        @forelse($recentAudits as $audit)
                                            <tr><td>{{ $audit->occurred_at->timezone(config('office.business_timezone'))->format('d M Y H:i') }}</td><td>{{ $audit->actor?->name ?? 'Sistem' }}</td><td><code>{{ $audit->action }}</code></td></tr>
                                        @empty
                                            <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada aktivitas audit.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endcan
            </div>
        </div>
    </div>
@endsection
