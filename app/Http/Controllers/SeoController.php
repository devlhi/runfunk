<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Response;

/**
 * robots.txt dan sitemap.xml.
 *
 * Dilayani lewat rute, bukan berkas statis di public/, supaya alamat di
 * dalamnya selalu mengikuti APP_URL. Berkas statis harus disunting manual tiap
 * kali domainnya berbeda — dan itu langkah yang paling mudah terlupakan saat
 * situs dipindahkan dari komputer sendiri ke server.
 */
class SeoController extends Controller
{
    /**
     * Alamat yang tidak boleh dirayapi.
     *
     * Semuanya berisi data orang, atau halaman yang memang tidak ada gunanya
     * di hasil pencarian.
     */
    private const TERLARANG = [
        '/panitia',
        '/dashboard',
        '/pendaftaran',
        '/sertifikat',
        '/bukti-bayar',
        '/profil',
        '/masuk',
        '/keluar',
        '/daftar-akun',
        '/verifikasi-email',
        '/lupa-kata-sandi',
        '/atur-ulang-kata-sandi',
    ];

    public function robots(): Response
    {
        $baris = [
            '# Gong Fun Run 2026',
            '#',
            '# Yang boleh dirayapi hanya halaman umum: beranda, berita, dan papan hasil.',
            '# Sisanya berisi data orang — e-tiket, nomor BIB, bukti pembayaran,',
            '# sertifikat, dan seluruh panel panitia.',
            '#',
            '# Ini lapisan pertama saja. Halaman-halaman itu juga menjawab dengan tajuk',
            '# X-Robots-Tag: noindex, karena robots.txt hanya mencegah perayapan —',
            '# alamat yang pernah dibagikan orang tetap bisa muncul di hasil pencarian',
            '# tanpa tajuk itu.',
            '',
            'User-agent: *',
            '',
        ];

        foreach (self::TERLARANG as $jalur) {
            $baris[] = "Disallow: {$jalur}";
        }

        $baris = array_merge($baris, [
            '',
            'Allow: /$',
            'Allow: /berita',
            'Allow: /hasil',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response(implode("\n", $baris), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public function sitemap(): Response
    {
        $halaman = [
            ['loc' => url('/'), 'prioritas' => '1.0', 'ubah' => 'daily'],
            ['loc' => url('/berita'), 'prioritas' => '0.8', 'ubah' => 'daily'],
            ['loc' => url('/hasil'), 'prioritas' => '0.8', 'ubah' => 'weekly'],
        ];

        // Tiap berita yang sudah tayang punya alamatnya sendiri; inilah yang
        // paling mungkin dicari orang lewat mesin pencari.
        foreach (News::tayang()->orderByDesc('published_at')->get() as $berita) {
            $halaman[] = [
                'loc' => url('/berita/'.$berita->slug),
                'ubah' => 'monthly',
                'prioritas' => '0.6',
                'diubah' => ($berita->updated_at ?? $berita->created_at)->toAtomString(),
            ];
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($halaman as $h) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.e($h['loc']).'</loc>';

            if (isset($h['diubah'])) {
                $xml[] = '    <lastmod>'.$h['diubah'].'</lastmod>';
            }

            $xml[] = '    <changefreq>'.$h['ubah'].'</changefreq>';
            $xml[] = '    <priority>'.$h['prioritas'].'</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
