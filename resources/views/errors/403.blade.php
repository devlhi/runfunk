@extends('errors.layout')
@section('kode', '403')
@section('judul', 'Halaman ini bukan untukmu')
@section('pesan', 'Halaman yang kamu buka khusus untuk panitia. Kalau kamu peserta, semua urusan pendaftaranmu ada di dashboard.')
@section('aksi-tambahan')
    <a class="btn btn--ghost" href="{{ url('/dashboard') }}">Buka Dashboard</a>
@endsection
