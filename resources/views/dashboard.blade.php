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
            @if(!auth()->user()->signature_path)
                <div class="alert alert-warning alert-dismissible mb-4" role="alert">
                    <div class="d-flex">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" /><path d="M12 16h.01" /></svg>
                        </div>
                        <div class="flex-fill">
                            <h4 class="alert-title">Tanda Tangan Belum Diunggah</h4>
                            <div class="text-secondary">Anda belum mengunggah foto tanda tangan. Tanda tangan diperlukan saat membuat atau menyetujui dokumen di JBLU Office.</div>
                            <div class="mt-2">
                                <a href="{{ route('users.edit', auth()->user()) }}" class="btn btn-warning btn-sm">Lengkapi Profil &amp; Tanda Tangan</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
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
