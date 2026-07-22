<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'subject', 'body_html', 'updated_by'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Template yang boleh disunting, beserta isi bawaannya.
     *
     * Daftar ini sekaligus jadi daftar putih: kunci yang tidak ada di sini
     * ditolak, jadi nama template dari URL tidak pernah dipakai untuk memilih
     * berkas view maupun membuat baris baru sembarangan.
     *
     * @return array<string, array{judul: string, kapan: string, subject: string, isi: string, kolom: array<string,string>}>
     */
    public static function definisi(): array
    {
        return [
            'verifikasi' => [
                'judul' => 'Verifikasi Email Pendaftar',
                'kapan' => 'Dikirim otomatis begitu peserta selesai membuat akun.',
                'subject' => 'Kode verifikasi kamu: {{kode}}',
                // Judul besar di kepala email. Dipakai bersama oleh email
                // sungguhan dan pratinjau, supaya pratinjaunya tidak berbohong.
                'heading' => 'Satu langkah lagi, {{nama}}',
                'kolom' => [
                    '{{nama}}' => 'Nama pendaftar',
                    '{{kode}}' => 'Kode 6 angka',
                    '{{tautan}}' => 'URL tombol verifikasi',
                    '{{berlaku}}' => 'Masa berlaku kode (menit)',
                ],
                'isi' => <<<'HTML'
                    <p>Terima kasih sudah mendaftar akun. Sebelum bisa memilih kategori dan
                    mengambil slot lomba, kami perlu memastikan alamat email ini benar-benar milikmu.</p>

                    <p><strong>Cara tercepat —</strong> tekan tombolnya:</p>

                    <p>[tombol]✓ Verifikasi Email Saya[/tombol]</p>

                    <p><strong>Kalau tombolnya tidak jalan —</strong> buka halaman verifikasi dan ketik kode ini:</p>

                    <p>[kode]</p>

                    <p>Kode berlaku <strong>{{berlaku}} menit</strong> dan hanya bisa dipakai sekali.
                    Lewat dari itu, minta kode baru dari halaman verifikasi.</p>

                    <p>[catatan]<strong>Merasa tidak mendaftar?</strong> Abaikan email ini dan jangan berikan
                    kodenya ke siapa pun &mdash; termasuk yang mengaku panitia. Tanpa kode itu, akun tidak
                    bisa diaktifkan.[/catatan]</p>
                    HTML,
            ],
            'uji-coba' => [
                'judul' => 'Uji Coba Pengiriman',
                'kapan' => 'Dikirim saat developer menekan tombol Uji Email di Pengaturan Acara.',
                'subject' => 'Uji Coba Email — Gong Fun Run 2026',
                'heading' => 'Uji coba pengiriman email',
                'kolom' => [
                    '{{host}}' => 'Host & port SMTP',
                    '{{pengirim}}' => 'Alamat email pengirim',
                    '{{waktu}}' => 'Waktu pengiriman',
                ],
                'isi' => <<<'HTML'
                    <p>Halo,</p>

                    <p>Ini email percobaan dari panel panitia. Kalau pesan ini sampai di kotak masuk,
                    pengaturan SMTP sudah benar dan kabar pembayaran serta pengumuman ke peserta
                    bisa dikirim lewat email.</p>

                    <p>[catatan]Host: {{host}}<br>Pengirim: {{pengirim}}<br>Waktu: {{waktu}} WITA[/catatan]</p>

                    <p>Tidak perlu membalas email ini.</p>
                    HTML,
            ],
        ];
    }

    public static function adaKunci(string $key): bool
    {
        return array_key_exists($key, self::definisi());
    }

    /** Isi tersimpan kalau ada, kalau tidak isi bawaan. */
    public static function ambil(string $key): array
    {
        $bawaan = self::definisi()[$key] ?? null;

        if (! $bawaan) {
            return [];
        }

        $tersimpan = self::where('key', $key)->first();

        return [
            'key' => $key,
            'judul' => $bawaan['judul'],
            'kapan' => $bawaan['kapan'],
            'kolom' => $bawaan['kolom'],
            'subject' => $tersimpan->subject ?? $bawaan['subject'],
            'heading' => $bawaan['heading'],
            'isi' => $tersimpan->body_html ?? trim($bawaan['isi']),
            'diubah' => $tersimpan !== null,
            'diubah_oleh' => $tersimpan?->editor?->name,
            'diubah_pada' => $tersimpan?->updated_at?->translatedFormat('d M Y, H:i'),
        ];
    }
}
