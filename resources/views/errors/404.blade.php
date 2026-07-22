@extends('errors.layout')
@section('kode', '404')
@section('judul', 'Halaman tidak ditemukan')
@section('pesan', 'Alamatnya mungkin salah ketik, atau halamannya sudah dipindahkan. Coba mulai lagi dari beranda.')
@section('aksi-tambahan')
    <a class="btn btn--ghost" href="{{ url('/berita') }}">Lihat Berita</a>
@endsection
