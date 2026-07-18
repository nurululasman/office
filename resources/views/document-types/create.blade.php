@extends('layouts.back.master')
@section('title', 'Tambah Tipe Dokumen')
@section('content')
    <div class="page-header"><div class="container-xl"><div class="page-pretitle">Tipe Dokumen</div><h1 class="page-title">Tambah tipe dokumen</h1></div></div>
    <div class="page-body"><div class="container-xl"><form method="POST" action="{{ route('document-types.store') }}" class="card"><div class="card-body">@csrf @include('document-types._form')</div></form></div></div>
@endsection
