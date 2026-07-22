<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Menggambar kode QR sebagai data URI.
 *
 * Dikembalikan sebagai data URI, bukan berkas yang disimpan: kodenya diturunkan
 * dari data yang sudah ada, jadi menyimpannya hanya menambah berkas yang harus
 * ikut dicadangkan dan dibersihkan. Ditulis SVG supaya tetap tajam saat kartunya
 * dicetak, berapa pun ukuran kertasnya.
 */
class QrImage
{
    /**
     * @param  int  $ukuran  sisi gambar dalam piksel (SVG tetap skalabel)
     */
    public function dataUri(string $isi, int $ukuran = 320): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($isi, $ukuran));
    }

    /** SVG mentah, untuk disajikan langsung sebagai berkas gambar. */
    public function svg(string $isi, int $ukuran = 320): string
    {
        $renderer = new ImageRenderer(
            // Margin 1 modul. QR butuh area kosong di sekelilingnya supaya
            // pemindai bisa menemukan batasnya; tanpa itu, kode yang menempel
            // ke garis tepi kartu sering gagal terbaca.
            new RendererStyle($ukuran, 1),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($isi);
    }
}
