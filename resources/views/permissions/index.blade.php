@extends('layouts.back.master')
@section('title', 'Permission')
@section('content')
<div class="page-header"><div class="container-xl"><div class="page-pretitle">Administrasi akses</div><h1 class="page-title">Permission</h1><div class="text-secondary mt-1">Katalog permission sistem. Atur permission melalui role kustom.</div></div></div>
<div class="page-body"><div class="container-xl"><div class="row row-cards">@foreach($permissionGroups as $group => $permissions)<div class="col-lg-6"><div class="card"><div class="card-header"><h3 class="card-title text-capitalize">{{ str_replace('-', ' ', $group) }}</h3></div><div class="table-responsive"><table class="table card-table"><thead><tr><th>Permission</th><th>Dipakai role</th></tr></thead><tbody>@foreach($permissions as $permission)<tr><td><code>{{ $permission->slug }}</code><div class="text-secondary">{{ $permission->name }}</div></td><td>{{ $permission->roles->count() }}</td></tr>@endforeach</tbody></table></div></div></div>@endforeach</div></div></div>
@endsection
