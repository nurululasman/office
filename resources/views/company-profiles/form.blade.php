@extends('layouts.back.master')

@section('title', $profile->exists ? 'Edit Company Profile' : 'Tambah Company Profile')

@section('content')
@php($editing = $profile->exists)
<div class="page-header d-print-none"><div class="container-xl"><div class="page-pretitle">Administration</div><h1 class="page-title">{{ $editing ? 'Edit Company Profile' : 'Tambah Company Profile' }}</h1></div></div>
<div class="page-body"><div class="container-xl"><form class="card" method="POST" enctype="multipart/form-data" action="{{ $editing ? route('company-profiles.update', $profile) : route('company-profiles.store') }}">@csrf @if($editing) @method('PUT') @endif
<div class="card-body"><div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="company_code">Kode perusahaan</label><input class="form-control" id="company_code" name="company_code" maxlength="50" value="{{ old('company_code', $profile->company_code) }}" required></div>
    <div class="col-md-4"><label class="form-label" for="legal_name">Nama legal</label><input class="form-control" id="legal_name" name="legal_name" value="{{ old('legal_name', $profile->legal_name) }}" required></div>
    <div class="col-md-4"><label class="form-label" for="display_name">Nama tampilan</label><input class="form-control" id="display_name" name="display_name" value="{{ old('display_name', $profile->display_name) }}" required></div>
    <div class="col-12"><label class="form-label" for="address_lines_text">Alamat</label><textarea class="form-control" id="address_lines_text" name="address_lines_text" rows="3" required>{{ old('address_lines_text', implode("\n", $profile->address_lines ?? [])) }}</textarea><div class="form-hint">Satu baris alamat per baris.</div></div>
    <div class="col-md-5"><label class="form-label" for="city">Kota</label><input class="form-control" id="city" name="city" value="{{ old('city', $profile->city) }}" required></div>
    <div class="col-md-3"><label class="form-label" for="postal_code">Kode pos</label><input class="form-control" id="postal_code" name="postal_code" value="{{ old('postal_code', $profile->postal_code) }}" required></div>
    <div class="col-md-2"><label class="form-label" for="country">Negara</label><input class="form-control" id="country" name="country" maxlength="2" value="{{ old('country', $profile->country ?: 'ID') }}" required></div>
    <div class="col-md-2"><label class="form-label" for="primary_color">Warna utama</label><input class="form-control form-control-color" type="color" id="primary_color" name="primary_color" value="{{ old('primary_color', $profile->primary_color ?: '#087EAE') }}"></div>
    <div class="col-md-4"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email', $profile->email) }}"></div>
    <div class="col-md-4"><label class="form-label" for="phone">Telepon</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $profile->phone) }}"></div>
    <div class="col-md-4"><label class="form-label" for="website">Website</label><input class="form-control" type="url" id="website" name="website" value="{{ old('website', $profile->website) }}" placeholder="https://"></div>
    <div class="col-md-6"><label class="form-label" for="tax_id">NPWP / Tax ID</label><input class="form-control" id="tax_id" name="tax_id" value="{{ old('tax_id', $profile->tax_id) }}"></div>
    <div class="col-md-6"><label class="form-label" for="logo">Logo PNG/JPEG</label><input class="form-control" type="file" id="logo" name="logo" accept="image/png,image/jpeg"><div class="form-hint">Maksimum 2 MB. Upload baru tidak menghapus file lama agar snapshot tetap dapat direproduksi.</div></div>
    <div class="col-12"><input type="hidden" name="is_active" value="0"><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $profile->is_active ?? true))><span class="form-check-label">Profile aktif dan dapat dipilih untuk template baru</span></label></div>
</div></div>
<div class="card-footer text-end"><a class="btn me-2" href="{{ $editing ? route('company-profiles.show', $profile) : route('company-profiles.index') }}">Batal</a><button class="btn btn-primary">Simpan</button></div>
</form></div></div>
@endsection
