@extends('layouts.back.master')
@section('title', 'Ubah Tipe Dokumen')
@section('content')
    <div class="page-header"><div class="container-xl"><div class="page-pretitle">Tipe Dokumen</div><h1 class="page-title">Ubah {{ $documentType->name }}</h1></div></div>
    <div class="page-body"><div class="container-xl"><form method="POST" action="{{ route('document-types.update', $documentType) }}" class="card"><div class="card-body">@csrf @method('PUT') @include('document-types._form')</div></form></div></div>
@endsection
