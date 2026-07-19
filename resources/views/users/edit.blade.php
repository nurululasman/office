@extends('layouts.back.master')
@section('title', 'Atur Akses User')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="page-pretitle">Administrasi akses</div><h1 class="page-title">Atur akses {{ $managedUser->name }}</h1></div></div>
<div class="page-body"><div class="container-xl"><div class="row row-cards"><div class="col-lg-8"><form class="card" method="POST" action="{{ route('users.update', $managedUser) }}">@csrf @method('PUT')
<div class="card-body"><p class="text-secondary">{{ $managedUser->email }}</p>
@if(auth()->user()->is($managedUser))<div class="alert alert-warning">Akses akun Anda sendiri tidak dapat diubah untuk mencegah administrator terkunci.</div>@endif
<label class="form-check form-switch mb-4"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser->is_active)) @disabled(auth()->user()->is($managedUser))><span class="form-check-label">User aktif dan boleh login</span></label>
<h3 class="card-title">Role</h3><div class="row">@foreach($roles as $role)<div class="col-md-6"><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $managedUser->roles->pluck('id')->all()))) @disabled(auth()->user()->is($managedUser))><span class="form-check-label"><strong>{{ $role->name }}</strong><span class="d-block text-secondary">{{ $role->description }}</span></span></label></div>@endforeach</div>
</div><div class="card-footer text-end"><a href="{{ route('users.index') }}" class="btn">Batal</a>@if(!auth()->user()->is($managedUser))<button class="btn btn-primary" type="submit">Simpan akses</button>@endif</div></form></div></div></div></div>
@endsection
