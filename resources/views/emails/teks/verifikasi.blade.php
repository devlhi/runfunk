Halo {{ $nama }},

Terima kasih sudah mendaftar akun di {{ \App\Models\Setting::ambil('event_name') ?: 'Gong Fun Run 2026' }}.
Sebelum bisa mengambil slot lomba, kami perlu memastikan alamat email ini milikmu.

KODE VERIFIKASI: {{ $kode }}

Kode berlaku {{ $berlaku }} menit dan hanya bisa dipakai sekali.

Atau buka tautan ini untuk verifikasi sekali klik:
{{ $tautan }}

Merasa tidak mendaftar? Abaikan email ini dan jangan berikan kodenya ke siapa pun,
termasuk yang mengaku panitia. Tanpa kode itu, akun tidak bisa diaktifkan.

--
Panitia {{ \App\Models\Setting::ambil('event_name') ?: 'Gong Fun Run 2026' }}
IKA — Ikatan Keluarga Alumni SMK Gotong Royong, Gorontalo
