@extends('errors.layout')
@section('kode', '419')
@section('judul', 'Sesi kamu kedaluwarsa')
@section('pesan', 'Halaman ini dibiarkan terbuka terlalu lama. Muat ulang halamannya lalu coba lagi — isian yang belum terkirim mungkin perlu diketik ulang.')
@section('aksi-tambahan')
    <a class="btn btn--ghost" href="{{ url('/masuk') }}">Masuk Lagi</a>
@endsection
