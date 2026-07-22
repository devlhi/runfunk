@props([
    'url',
    'warna' => '#FF4A1C',
])

{{--
    Tombol email. Dibungkus tabel, bukan <a> yang di-styling langsung: Outlook
    mengabaikan padding pada tautan, sehingga tombolnya menciut jadi teks biasa.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">
    <tr>
        <td align="center" style="border-radius:10px;background-color:{{ $warna }};border:2px solid #17131F;">
            <a href="{{ $url }}"
               style="display:inline-block;padding:13px 30px;font-family:'Segoe UI',Arial,sans-serif;
                      font-size:15px;font-weight:800;color:#FFFFFF;text-decoration:none;border-radius:10px;">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>
