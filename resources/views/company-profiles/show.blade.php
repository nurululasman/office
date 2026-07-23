@extends('layouts.back.master')

@section('title', $profile->display_name)

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">{{ $profile->company_code }}</div>
                <h1 class="page-title">{{ $profile->display_name }}</h1>
            </div>
            @can('update', $profile)
                <div class="col-auto">
                    <a class="btn btn-primary" href="{{ route('company-profiles.edit', $profile) }}">Edit</a>
                </div>
            @endcan
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nama legal</dt>
                            <dd class="col-sm-8">{{ $profile->legal_name }}</dd>

                            <dt class="col-sm-4">Alamat</dt>
                            <dd class="col-sm-8">
                                @foreach($profile->address_lines as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                                <div>{{ $profile->city }} {{ $profile->postal_code }}, {{ $profile->country }}</div>
                            </dd>

                            <dt class="col-sm-4">Kontak</dt>
                            <dd class="col-sm-8">
                                {{ $profile->email ?: '—' }}<br>
                                {{ $profile->phone ?: '—' }}<br>
                                @if($profile->website)
                                    <a href="{{ $profile->website }}" rel="noopener noreferrer">{{ $profile->website }}</a>
                                @else
                                    —
                                @endif
                            </dd>

                            <dt class="col-sm-4">Tax ID</dt>
                            <dd class="col-sm-8">{{ $profile->tax_id ?: '—' }}</dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">{{ $profile->is_active ? 'Aktif' : 'Nonaktif' }}</dd>

                            <dt class="col-sm-4">Template</dt>
                            <dd class="col-sm-8">{{ $profile->templates_count }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        @if($profile->logo_path)
                            <img src="{{ $profile->logo_path }}" alt="Logo {{ $profile->display_name }}" style="max-width: 100%; max-height: 160px">
                        @else
                            <div class="text-secondary py-5">Logo belum tersedia</div>
                        @endif

                        @if($profile->primary_color)
                            <div class="mt-3">
                                <span class="badge" style="background: {{ $profile->primary_color }}">{{ $profile->primary_color }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Template terkait</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table card-table">
                            <thead>
                                <tr><th>Nama</th><th>Versi</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $template)
                                    <tr>
                                        <td><a href="{{ route('quotation-templates.show', $template) }}">{{ $template->name }}</a></td>
                                        <td>{{ $template->version }}</td>
                                        <td>{{ $template->status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-secondary">Belum digunakan template.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @can('delete', $profile)
                <div class="col-12">
                    <form method="POST" action="{{ route('company-profiles.destroy', $profile) }}" onsubmit="return confirm('Hapus Company Profile yang belum digunakan?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger">Hapus profile</button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</div>
@endsection
