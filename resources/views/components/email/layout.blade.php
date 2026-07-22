{{--
    Kerangka semua email keluar.

    Sengaja ditulis dengan tabel dan gaya inline, bukan flexbox/grid dan kelas CSS:
    Outlook merender lewat mesin Word dan Gmail membuang seluruh blok <style>, jadi
    tata letak modern rontok di klien yang justru paling banyak dipakai peserta.

    Slot yang dipakai halaman anak:
      $judul     — judul di kepala email
      $pratinjau — teks yang muncul di daftar kotak masuk, tidak terlihat di badan
      $slot      — isi utama
--}}
@props([
    'judul' => null,
    'pratinjau' => '',
])

@php
    $namaAcara = \App\Models\Setting::ambil('event_name') ?: 'Gong Fun Run 2026';
    $lokasi = \App\Models\Setting::ambil('location') ?: config('funrun.location');
    $tanggal = \Illuminate\Support\Carbon::parse(
        \App\Models\Setting::ambil('event_date') ?: config('funrun.event_date')
    )->translatedFormat('l, d F Y');
@endphp
<div style="margin:0;padding:0;background-color:#F2EFE7;">
    {{-- Baris pratinjau: terbaca di daftar kotak masuk, disembunyikan di badan email. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;height:0;width:0;">
        {{ $pratinjau }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#F2EFE7;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="width:600px;max-width:100%;background-color:#FFFFFF;border:2px solid #17131F;border-radius:14px;overflow:hidden;">

                    {{-- Kepala: pita gradien hijau ke biru, sama dengan lajur samping panel. --}}
                    <tr>
                        <td style="height:6px;line-height:6px;font-size:0;background-color:#0B6E50;
                                   background-image:linear-gradient(90deg,#0B6E50 0%,#0A5A6B 50%,#132878 100%);">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding:26px 32px 0 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-family:'Segoe UI',Arial,sans-serif;font-size:11px;letter-spacing:2px;
                                               text-transform:uppercase;color:#6B6478;font-weight:700;">
                                        GONG / RUN
                                    </td>
                                    <td align="right" style="font-family:'Courier New',monospace;font-size:11px;color:#6B6478;">
                                        {{ $tanggal }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 32px 0 32px;">
                            <h1 style="margin:0;font-family:'Segoe UI',Arial,sans-serif;font-size:26px;line-height:1.2;
                                       font-weight:800;color:#17131F;">
                                {{ $judul ?? $namaAcara }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:6px 32px 0 32px;">
                            <div style="height:2px;background-color:#FF4A1C;width:56px;font-size:0;line-height:2px;">&nbsp;</div>
                        </td>
                    </tr>

                    {{-- Isi --}}
                    <tr>
                        <td style="padding:20px 32px 8px 32px;font-family:'Segoe UI',Arial,sans-serif;
                                   font-size:15px;line-height:1.65;color:#3A3348;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Tanda tangan panitia --}}
                    <tr>
                        <td style="padding:8px 32px 26px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="border-top:2px dashed #E4DFD3;">
                                <tr>
                                    <td style="padding-top:18px;font-family:'Segoe UI',Arial,sans-serif;
                                               font-size:14px;line-height:1.6;color:#3A3348;">
                                        <div style="margin-bottom:2px;">Salam sehat &amp; sampai jumpa di garis start,</div>
                                        <div style="font-weight:800;color:#17131F;font-size:15px;">Panitia {{ $namaAcara }}</div>
                                        <div style="font-size:12px;color:#6B6478;margin-top:2px;">
                                            IKA &mdash; Ikatan Keluarga Alumni SMK Gotong Royong Telaga
                                        </div>
                                        <div style="font-size:12px;color:#6B6478;">{{ $lokasi }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Kaki --}}
                    <tr>
                        <td style="background-color:#17131F;padding:18px 32px;
                                   font-family:'Segoe UI',Arial,sans-serif;font-size:11px;line-height:1.6;color:#A79FB4;">
                            Email ini dikirim otomatis oleh panel {{ $namaAcara }}.
                            Ada pertanyaan? Balas saja email ini &mdash; panitia akan menjawab.
                            <div style="margin-top:6px;color:#6B6478;">
                                &copy; {{ now()->year }} IKA SMK Gotong Royong Telaga, Gorontalo.
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="font-family:'Segoe UI',Arial,sans-serif;font-size:11px;color:#99919F;
                            margin-top:14px;max-width:600px;">
                    Kamu menerima email ini karena terdaftar di {{ $namaAcara }}.
                </div>
            </td>
        </tr>
    </table>
</div>
