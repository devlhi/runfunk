{{--
    Badan email yang isinya disunting developer.

    $isi sudah melewati EmailBodyRenderer: tag berbahaya dibuang, placeholder
    diganti dengan nilai yang sudah di-escape. Karena itu ia dicetak tanpa
    escape di sini — dan HANYA boleh diisi dari renderer tersebut.
--}}
<x-email.layout :judul="$judul" :pratinjau="$pratinjau">
    {!! $isi !!}
</x-email.layout>
