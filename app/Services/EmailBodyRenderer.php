<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Str;

/**
 * Mengubah isi template yang disunting developer menjadi HTML email yang aman.
 *
 * Isinya TIDAK PERNAH dijalankan sebagai Blade. Blade dikompilasi menjadi PHP,
 * jadi mengompilasi teks dari formulir sama dengan memberi siapa pun yang bisa
 * membuka halaman itu jalan untuk menjalankan kode di server. Yang dilakukan di
 * sini hanya penggantian teks biasa lalu penyaringan tag.
 */
class EmailBodyRenderer
{
    /** Tag yang boleh bertahan. Sisanya dibuang beserta isinya atau dilucuti. */
    private const TAG_AMAN = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'span', 'div',
        'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'small', 'blockquote', 'hr',
    ];

    /** Tag yang dibuang berikut seluruh isinya, bukan sekadar dilucuti tag-nya. */
    private const TAG_BUANG_TOTAL = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'link', 'meta', 'base', 'svg',
    ];

    /**
     * Susun badan email siap kirim.
     *
     * @param  array<string, string>  $data  nilai untuk placeholder {{...}}
     */
    public function render(string $key, array $data): string
    {
        $template = EmailTemplate::ambil($key);

        if ($template === []) {
            return '';
        }

        return $this->susun($template['isi'], $data);
    }

    public function subjek(string $key, array $data): string
    {
        $template = EmailTemplate::ambil($key);

        return $this->gantiPlaceholder($template['subject'] ?? '', $data);
    }

    /** Judul besar di kepala email — dipakai bersama oleh email asli dan pratinjau. */
    public function judul(string $key, array $data): string
    {
        $template = EmailTemplate::ambil($key);

        return $this->gantiPlaceholder($template['heading'] ?? '', $data);
    }

    /** Dipakai juga oleh pratinjau draf, yang isinya belum tersimpan. */
    public function susun(string $isi, array $data): string
    {
        $aman = $this->bersihkan($isi);
        $aman = $this->gantiPlaceholder($aman, $data);

        return $this->gantiBlok($aman, $data);
    }

    /**
     * Placeholder diganti dengan nilai yang SUDAH di-escape, supaya nama peserta
     * berisi tanda kurung siku tidak berubah jadi tag di badan email.
     */
    private function gantiPlaceholder(string $isi, array $data): string
    {
        foreach ($data as $kunci => $nilai) {
            $isi = str_replace('{{'.$kunci.'}}', e((string) $nilai), $isi);
        }

        // Placeholder yang tidak dikenali dikosongkan, bukan dibiarkan tampil
        // mentah sebagai "{{kode}}" di email yang diterima peserta.
        return preg_replace('/\{\{\s*[a-z_]+\s*\}\}/i', '', $isi);
    }

    /**
     * Blok bergaya: [tombol]…[/tombol], [kode], [catatan]…[/catatan].
     *
     * Ditulis sebagai penanda pendek, bukan HTML tabel mentah, karena tombol
     * email yang benar butuh markup tabel berlapis yang mustahil dirawat dengan
     * tangan — dan salah sedikit langsung rusak di Outlook.
     */
    private function gantiBlok(string $isi, array $data): string
    {
        $tautan = $data['tautan'] ?? '#';
        $kode = $data['kode'] ?? '';

        $isi = preg_replace_callback(
            '/\[tombol\](.*?)\[\/tombol\]/is',
            fn ($m) => view('components.email.tombol', [
                'url' => $tautan,
                'warna' => '#FF4A1C',
                'slot' => new \Illuminate\Support\HtmlString(strip_tags($m[1])),
            ])->render(),
            $isi
        );

        $isi = str_replace(
            '[kode]',
            view('components.email.kode', ['kode' => $kode])->render(),
            $isi
        );

        return preg_replace_callback(
            '/\[catatan\](.*?)\[\/catatan\]/is',
            fn ($m) => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
                .' style="margin:18px 0;background-color:#FFF6F3;border-left:3px solid #FF4A1C;border-radius:6px;">'
                .'<tr><td style="padding:14px 16px;font-family:\'Segoe UI\',Arial,sans-serif;font-size:13px;'
                .'line-height:1.6;color:#3A3348;">'.$m[1].'</td></tr></table>',
            $isi
        );
    }

    /**
     * Saring HTML yang diketik developer.
     *
     * Klien email memang membuang skrip sendiri, tapi pratinjaunya dirender di
     * dalam panel — jadi tanpa penyaringan ini, isi template jadi jalan masuk
     * XSS tersimpan ke sesama panitia yang membuka halaman itu.
     */
    public function bersihkan(string $isi): string
    {
        if (trim($isi) === '') {
            return '';
        }

        $dom = new \DOMDocument;

        // Isi yang tidak sempurna adalah hal biasa saat orang sedang mengetik;
        // peringatan parser-nya tidak boleh bocor ke keluaran.
        $sebelumnya = libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="akar">'.$isi.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        $akar = $dom->getElementById('akar');

        if (! $akar) {
            return '';
        }

        $this->saringSimpul($akar);

        $keluar = '';

        foreach (iterator_to_array($akar->childNodes) as $anak) {
            $keluar .= $dom->saveHTML($anak);
        }

        return trim($keluar);
    }

    private function saringSimpul(\DOMNode $induk): void
    {
        // Disalin dulu: menghapus simpul saat menelusuri langsung akan membuat
        // daftar anaknya bergeser dan sebagian simpul terlewat diperiksa.
        foreach (iterator_to_array($induk->childNodes) as $simpul) {
            if (! $simpul instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($simpul->nodeName);

            if (in_array($tag, self::TAG_BUANG_TOTAL, true)) {
                $simpul->parentNode->removeChild($simpul);

                continue;
            }

            if (! in_array($tag, self::TAG_AMAN, true)) {
                // Tag asing dilucuti, tapi teksnya dipertahankan supaya tulisan
                // yang sudah diketik tidak hilang begitu saja.
                $this->saringSimpul($simpul);
                $this->lucuti($simpul);

                continue;
            }

            $this->saringAtribut($simpul, $tag);
            $this->saringSimpul($simpul);
        }
    }

    private function saringAtribut(\DOMElement $simpul, string $tag): void
    {
        foreach (iterator_to_array($simpul->attributes) as $atribut) {
            $nama = strtolower($atribut->nodeName);
            $nilai = trim($atribut->nodeValue);

            // Semua penangan kejadian (onclick, onerror, …) dibuang tanpa kecuali.
            if (str_starts_with($nama, 'on')) {
                $simpul->removeAttribute($atribut->nodeName);

                continue;
            }

            if ($nama === 'style') {
                continue;
            }

            if ($tag === 'a' && $nama === 'href') {
                if (! $this->tautanAman($nilai)) {
                    $simpul->removeAttribute('href');
                }

                continue;
            }

            if (! in_array($nama, ['class', 'title', 'align', 'target', 'rel'], true)) {
                $simpul->removeAttribute($atribut->nodeName);
            }
        }
    }

    /** Hanya skema yang jelas tidak bisa menjalankan apa pun. */
    private function tautanAman(string $url): bool
    {
        $bersih = strtolower(preg_replace('/\s+/', '', $url));

        return Str::startsWith($bersih, ['http://', 'https://', 'mailto:', '#', '/']);
    }

    /** Ganti simpul dengan anak-anaknya. */
    private function lucuti(\DOMElement $simpul): void
    {
        while ($simpul->firstChild) {
            $simpul->parentNode->insertBefore($simpul->firstChild, $simpul);
        }

        $simpul->parentNode->removeChild($simpul);
    }
}
