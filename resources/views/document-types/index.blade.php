@extends('layouts.back.master')

@section('title', 'Tipe Dokumen')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col"><div class="page-pretitle">Administrasi</div><h1 class="page-title">Tipe Dokumen</h1></div>
                @can('create', App\Models\DocumentType::class)
                    <div class="col-auto ms-auto"><a href="{{ route('document-types.create') }}" class="btn btn-primary">Tambah tipe dokumen</a></div>
                @endcan
            </div>
        </div>
    </div>
    <div class="page-body"><div class="container-xl"><div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Kode</th><th>Nama</th><th>Pola</th><th>Approval</th><th>Status</th><th class="w-1"></th></tr></thead>
                <tbody>
                    @forelse($documentTypes as $type)
                        <tr>
                            <td><code>{{ $type->code }}</code></td><td>{{ $type->name }}</td><td><code>{{ $type->number_pattern }}</code></td>
                            <td>{{ $type->approval_mode === 'direct' ? 'Direct' : 'Maker-checker' }}</td>
                            <td><span class="badge {{ $type->is_active ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td><div class="btn-list flex-nowrap">
                                @can('update', $type)<a href="{{ route('document-types.edit', $type) }}" class="btn btn-sm">Ubah</a>
                                    <form method="POST" action="{{ route('document-types.toggle', $type) }}">@csrf @method('PATCH')<button class="btn btn-sm" type="submit">{{ $type->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                                @endcan
                                @can('delete', $type)<form method="POST" action="{{ route('document-types.destroy', $type) }}" onsubmit="return confirm('Hapus tipe dokumen yang belum pernah digunakan?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form>@endcan
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-5">Belum ada tipe dokumen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documentTypes->hasPages())<div class="card-footer">{{ $documentTypes->links() }}</div>@endif
    </div></div></div>
@endsection
