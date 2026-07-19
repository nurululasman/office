@extends('layouts.back.master')
@section('title', 'Tambah Role')
@section('content')
<div class="page-header"><div class="container-xl"><div class="page-pretitle">Administrasi akses</div><h1 class="page-title">Tambah role</h1></div></div><div class="page-body"><div class="container-xl"><div class="row"><div class="col-lg-8"><form class="card" method="POST" action="{{ route('roles.store') }}">@csrf @include('roles._form')</form></div></div></div></div>
@endsection
