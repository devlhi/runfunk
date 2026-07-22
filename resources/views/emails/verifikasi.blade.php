<x-email.layout
    judul="Satu langkah lagi, {{ $nama }}"
    pratinjau="Kode verifikasi kamu {{ $kode }} — berlaku {{ $berlaku }} menit."
>
    <p style="margin:0 0 14px 0;">
        Terima kasih sudah mendaftar akun. Sebelum bisa memilih kategori dan mengambil
        slot lomba, kami perlu memastikan alamat email ini benar-benar milikmu.
    </p>

    <p style="margin:0 0 6px 0;"><strong style="color:#17131F;">Cara tercepat —</strong> tekan tombolnya:</p>

    <x-email.tombol :url="$tautan">✓ Verifikasi Email Saya</x-email.tombol>

    <p style="margin:0 0 6px 0;">
        <strong style="color:#17131F;">Kalau tombolnya tidak jalan —</strong>
        buka halaman verifikasi dan ketik kode ini:
    </p>

    <x-email.kode :kode="$kode" />

    <p style="margin:0 0 14px 0;font-size:13px;color:#6B6478;">
        Kode berlaku <strong>{{ $berlaku }} menit</strong> dan hanya bisa dipakai sekali.
        Lewat dari itu, minta kode baru dari halaman verifikasi.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:18px 0 0 0;background-color:#FFF6F3;border-left:3px solid #FF4A1C;border-radius:6px;">
        <tr>
            <td style="padding:14px 16px;font-family:'Segoe UI',Arial,sans-serif;font-size:13px;
                       line-height:1.6;color:#3A3348;">
                <strong style="color:#17131F;">Merasa tidak mendaftar?</strong>
                Abaikan email ini dan jangan berikan kodenya ke siapa pun &mdash; termasuk yang
                mengaku panitia. Tanpa kode itu, akun tidak bisa diaktifkan.
            </td>
        </tr>
    </table>

    <p style="margin:18px 0 0 0;font-size:12px;color:#99919F;word-break:break-all;">
        Tombol tidak bisa ditekan? Salin tautan ini ke peramban:<br>
        <span style="color:#6B6478;">{{ $tautan }}</span>
    </p>
</x-email.layout>
