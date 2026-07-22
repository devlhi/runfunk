<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\User;

/**
 * Menerbitkan dan memeriksa isi kode QR.
 *
 * Isinya TIDAK boleh sekadar nomor BIB. Kalau begitu, siapa pun yang tahu
 * nomornya bisa mencetak kartu palsu dan mengambil race pack orang lain —
 * dan nomor BIB memang tercetak besar-besar untuk dilihat semua orang.
 *
 * Karena itu tiap kode membawa tanda tangan HMAC yang hanya bisa dibuat server.
 * Formatnya sengaja pendek supaya QR-nya tidak terlalu rapat, agar tetap
 * terbaca dari kartu tercetak di bawah cahaya pagi yang seadanya.
 *
 *   P.<id>.<tanda tangan>    peserta
 *   K.<id>.<versi>.<tanda tangan>    kartu panitia
 */
class QrToken
{
    private const TIPE_PESERTA = 'P';

    private const TIPE_PANITIA = 'K';

    /**
     * 12 karakter heksadesimal = 48 bit. Untuk memalsukan, penyerang harus
     * menebak tanda tangan yang benar bagi satu id tertentu — mustahil dilakukan
     * dengan mencoba-coba, apalagi lajunya dibatasi di sisi rute.
     */
    private const PANJANG_TANDA = 12;

    public function untukPeserta(Registration $registration): string
    {
        $inti = self::TIPE_PESERTA.'.'.$registration->id;

        return $inti.'.'.$this->tandaTangan($inti);
    }

    /**
     * Kartu panitia ikut membawa nomor versi. Menaikkan versinya lewat "cetak
     * ulang" membuat kartu lama langsung tidak berlaku — itu satu-satunya cara
     * menonaktifkan kartu yang hilang tanpa menghapus akunnya.
     */
    public function untukPanitia(User $user): string
    {
        $inti = self::TIPE_PANITIA.'.'.$user->id.'.'.$user->card_version;

        return $inti.'.'.$this->tandaTangan($inti);
    }

    /**
     * Membaca kode hasil pindaian.
     *
     * @return array{tipe: string, id: int, versi: int|null}|null null kalau tidak sah
     */
    public function baca(?string $kode): ?array
    {
        if (blank($kode)) {
            return null;
        }

        $bagian = explode('.', trim($kode));

        // Peserta punya 3 bagian, kartu panitia 4 (ada nomor versinya).
        if (count($bagian) === 3 && $bagian[0] === self::TIPE_PESERTA) {
            [$tipe, $id, $tanda] = $bagian;
            $versi = null;
        } elseif (count($bagian) === 4 && $bagian[0] === self::TIPE_PANITIA) {
            [$tipe, $id, $versi, $tanda] = $bagian;
        } else {
            return null;
        }

        if (! ctype_digit($id) || ($versi !== null && ! ctype_digit($versi))) {
            return null;
        }

        $inti = $versi === null ? "{$tipe}.{$id}" : "{$tipe}.{$id}.{$versi}";

        // hash_equals: pembandingan biasa membocorkan berapa karakter awal yang
        // sudah benar lewat selisih waktu, dan itu cukup untuk menyusun tanda
        // tangan sedikit demi sedikit.
        if (! hash_equals($this->tandaTangan($inti), $tanda)) {
            return null;
        }

        return ['tipe' => $tipe, 'id' => (int) $id, 'versi' => $versi === null ? null : (int) $versi];
    }

    public function bacaPeserta(?string $kode): ?int
    {
        $isi = $this->baca($kode);

        return $isi && $isi['tipe'] === self::TIPE_PESERTA ? $isi['id'] : null;
    }

    /** @return array{id: int, versi: int}|null */
    public function bacaPanitia(?string $kode): ?array
    {
        $isi = $this->baca($kode);

        return $isi && $isi['tipe'] === self::TIPE_PANITIA
            ? ['id' => $isi['id'], 'versi' => $isi['versi']]
            : null;
    }

    private function tandaTangan(string $inti): string
    {
        return substr(hash_hmac('sha256', $inti, config('app.key')), 0, self::PANJANG_TANDA);
    }
}
