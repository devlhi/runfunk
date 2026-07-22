Uji coba pengiriman email — {{ \App\Models\Setting::ambil('event_name') ?: 'Gong Fun Run 2026' }}

Ini email percobaan dari panel panitia. Kalau pesan ini sampai di kotak masuk,
pengaturan SMTP sudah benar dan kabar pembayaran serta pengumuman ke peserta
bisa dikirim lewat email.

Host     : {{ $host }}
Pengirim : {{ $pengirim }}
Waktu    : {{ $waktu }} WITA

--
Panitia {{ \App\Models\Setting::ambil('event_name') ?: 'Gong Fun Run 2026' }}
IKA — Ikatan Keluarga Alumni SMK Gotong Royong Telaga, Gorontalo
