<?php

namespace App\Services;

use App\Models\RaceCategory;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class ResultService
{
    /** Batas atas yang masih masuk akal untuk lari 10 km: 12 jam. */
    private const MAKS_DETIK = 12 * 3600;

    /**
     * Mengubah "01:02:03" (jam:menit:detik) atau "62:03" (menit:detik) menjadi
     * jumlah detik. Mengembalikan null kalau formatnya tidak dikenali.
     */
    public function keDetik(?string $waktu): ?int
    {
        if (blank($waktu)) {
            return null;
        }

        $bagian = array_map('trim', explode(':', trim($waktu)));

        if (count($bagian) < 2 || count($bagian) > 3) {
            return null;
        }

        foreach ($bagian as $b) {
            if (! ctype_digit($b)) {
                return null;
            }
        }

        // Dua bagian dibaca sebagai menit:detik, tiga bagian jam:menit:detik.
        [$jam, $menit, $detik] = count($bagian) === 3
            ? $bagian
            : [0, $bagian[0], $bagian[1]];

        if ((int) $detik > 59) {
            return null;
        }

        // Menit dibatasi 59 HANYA pada bentuk tiga bagian, di mana ia memang
        // pecahan dari jam. Pada bentuk "90:00" menitnya adalah durasi utuh —
        // dan 90 menit untuk 10K itu wajar. Aturan lama menolaknya, sehingga
        // panitia tidak bisa mencatat peserta yang finis di atas satu jam.
        if (count($bagian) === 3 && (int) $menit > 59) {
            return null;
        }

        $total = ((int) $jam * 3600) + ((int) $menit * 60) + (int) $detik;

        return $total <= self::MAKS_DETIK ? $total : null;
    }

    public function keWaktu(?int $detik): ?string
    {
        if ($detik === null) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', intdiv($detik, 3600), intdiv($detik % 3600, 60), $detik % 60);
    }

    /**
     * Menghitung ulang peringkat seluruh peserta yang punya catatan waktu.
     *
     * Dihitung ulang dari nol setiap kali, bukan ditambal sebagian: kalau ada
     * satu waktu diperbaiki karena salah ketik, seluruh peringkat di bawahnya
     * ikut bergeser dan harus konsisten.
     */
    public function hitungPeringkat(): void
    {
        DB::transaction(function () {
            foreach (RaceCategory::all() as $kategori) {
                $peserta = Registration::where('race_category_id', $kategori->id)
                    ->where('status', Registration::STATUS_CONFIRMED)
                    ->whereNotNull('finish_seconds')
                    ->orderBy('finish_seconds')
                    ->orderBy('id')      // waktu seri: yang mendaftar lebih dulu di atas
                    ->get();

                $urutanGender = ['L' => 0, 'P' => 0];

                foreach ($peserta as $i => $r) {
                    $gender = $r->gender ?: 'L';
                    $urutanGender[$gender] = ($urutanGender[$gender] ?? 0) + 1;

                    $r->forceFill([
                        'rank_overall' => $i + 1,
                        'rank_gender' => $urutanGender[$gender],
                    ])->saveQuietly();
                }

                // Peserta yang waktunya dihapus tidak boleh menyisakan peringkat lama.
                Registration::where('race_category_id', $kategori->id)
                    ->whereNull('finish_seconds')
                    ->whereNotNull('rank_overall')
                    ->update(['rank_overall' => null, 'rank_gender' => null]);
            }
        });
    }
}
