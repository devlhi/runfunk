@props(['kode'])

{{--
    Kotak kode verifikasi. Angkanya direnggangkan dan diberi jarak antar-karakter
    supaya mudah disalin manual dari layar ponsel ke layar lain.
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">
    <tr>
        <td align="center" style="background-color:#FAF8F3;border:2px dashed #17131F;border-radius:12px;padding:22px 16px;">
            <div style="font-family:'Segoe UI',Arial,sans-serif;font-size:11px;letter-spacing:2px;
                        text-transform:uppercase;color:#6B6478;font-weight:700;margin-bottom:10px;">
                Kode Verifikasi
            </div>
            <div style="font-family:'Courier New',monospace;font-size:38px;font-weight:700;
                        letter-spacing:10px;color:#17131F;line-height:1;">
                {{ $kode }}
            </div>
        </td>
    </tr>
</table>
