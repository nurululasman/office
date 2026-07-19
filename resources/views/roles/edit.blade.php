@extends('layouts.back.master')
@section('title', $role->is_system ? 'Detail Role' : 'Ubah Role')
@section('content')
<div class="page-header"><div class="container-xl"><div class="page-pretitle">Administrasi akses</div><h1 class="page-title">{{ $role->is_system ? 'Detail' : 'Ubah' }} role</h1></div></div><div class="page-body"><div class="container-xl"><div class="row"><div class="col-lg-8"><form class="card" method="POST" action="{{ route('roles.update', $role) }}">@csrf @method('PUT') @include('roles._form')</form>@can('delete', $role)<form class="mt-3" method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Hapus role</button></form>@endcan</div></div></div></div>
@endsection
