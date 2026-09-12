@extends('layouts.back.master')
@section('title', 'Edit User & Atur Akses')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="page-pretitle">Administrasi user</div>
        <h1 class="page-title">Edit User: {{ $managedUser->name }}</h1>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        <div class="row row-cards">
            <div class="col-lg-8">
                <form class="card" method="POST" action="{{ route('users.update', $managedUser) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-header">
                        <h3 class="card-title">Profil & Tanda Tangan</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label required" for="name">Nama</label>
                                <input class="form-control" id="name" name="name" value="{{ old('name', $managedUser->name) }}" required>
                                <div class="form-hint">Nama dapat diedit dan tidak terikat pada SSO.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="username">Username SSO</label>
                                <input class="form-control" id="username" value="{{ $managedUser->username ?: '—' }}" disabled readonly>
                                <div class="form-hint">Username berasal dari SSO dan tidak dapat diubah.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" id="email" value="{{ $managedUser->email }}" disabled readonly>
                                <div class="form-hint">Email dikelola melalui penyedia SSO.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="signature">Foto Tanda Tangan (PNG/JPEG)</label>
                                <input class="form-control" type="file" id="signature" name="signature" accept="image/png,image/jpeg">
                                <div class="form-hint">Maksimum 2 MB. Disarankan format PNG dengan latar belakang transparan.</div>
                                @if($managedUser->signature_path)
                                    <div class="mt-3 p-3 bg-light border rounded">
                                        <div class="form-label small text-secondary mb-2">Tanda tangan saat ini:</div>
                                        <img src="{{ $managedUser->signature_path }}" alt="Tanda tangan {{ $managedUser->name }}" style="max-height: 100px; max-width: 250px; object-fit: contain;">
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card-header">
                        <h3 class="card-title">Akses & Role</h3>
                    </div>
                    <div class="card-body">
                        @if(auth()->user()->is($managedUser))
                            <div class="alert alert-warning">Akses akun Anda sendiri tidak dapat diubah untuk mencegah administrator terkunci. Anda tetap dapat memperbarui nama dan tanda tangan.</div>
                        @endif
                        <label class="form-check form-switch mb-4">
                            @if(!auth()->user()->is($managedUser))
                                <input type="hidden" name="is_active" value="0">
                            @endif
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser->is_active)) @disabled(auth()->user()->is($managedUser))>
                            <span class="form-check-label">User aktif dan boleh login</span>
                        </label>

                        <h4 class="card-title">Role</h4>
                        <div class="row">
                            @foreach($roles as $role)
                                <div class="col-md-6">
                                    <label class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $managedUser->roles->pluck('id')->all()))) @disabled(auth()->user()->is($managedUser))>
                                        <span class="form-check-label">
                                            <strong>{{ $role->name }}</strong>
                                            <span class="d-block text-secondary">{{ $role->description }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card-footer text-end">
                        <a href="{{ route('users.index') }}" class="btn me-2">Batal</a>
                        <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
