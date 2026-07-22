<x-email.layout
    judul="Uji coba pengiriman email"
    pratinjau="Kalau email ini sampai, pengaturan SMTP sudah benar."
>
    <p style="margin:0 0 14px 0;">Halo,</p>

    <p style="margin:0 0 14px 0;">
        Ini email percobaan dari panel panitia. Kalau pesan ini sampai di kotak masuk,
        pengaturan SMTP sudah benar dan kabar pembayaran serta pengumuman ke peserta
        bisa dikirim lewat email.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:20px 0;background-color:#FAF8F3;border:1px solid #E4DFD3;border-radius:10px;">
        <tr>
            <td style="padding:16px 18px;font-family:'Courier New',monospace;font-size:13px;color:#3A3348;line-height:1.8;">
                <strong style="color:#17131F;">Host</strong> &nbsp; {{ $host }}<br>
                <strong style="color:#17131F;">Pengirim</strong> &nbsp; {{ $pengirim }}<br>
                <strong style="color:#17131F;">Waktu</strong> &nbsp; {{ $waktu }} WITA
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#6B6478;">
        Tidak perlu membalas email ini. Kalau kamu menerimanya tanpa pernah meminta,
        abaikan saja &mdash; tidak ada tindakan yang terjadi.
    </p>
</x-email.layout>
