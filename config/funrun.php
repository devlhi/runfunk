<?php

return [
    /*
    | Tanggal & waktu flag off (WITA / UTC+8) — dipakai countdown di landing page.
    */
    'event_date' => env('FUNRUN_EVENT_DATE', '2026-10-31T06:00:00+08:00'),
    'location' => env('FUNRUN_LOCATION', 'Lapangan Tuladenggi, Kab. Gorontalo'),

    /*
    | Rekening tujuan pembayaran yang ditampilkan di halaman instruksi bayar.
    */
    'payment' => [
        'bank_name' => env('FUNRUN_BANK_NAME', 'Bank BRI'),
        'bank_account' => env('FUNRUN_BANK_ACCOUNT', '0123-01-004567-53-9'),
        'bank_holder' => env('FUNRUN_BANK_HOLDER', 'Panitia Gong Funrun 2026'),
        'qris_name' => env('FUNRUN_QRIS_NAME', 'GONG FUNRUN 2026'),
        'whatsapp' => env('FUNRUN_WHATSAPP', '0812-0000-0000'),
    ],

    /*
    | Batas waktu penyelesaian pembayaran (jam) sebelum slot dilepas kembali.
    */
    'payment_deadline_hours' => env('FUNRUN_PAYMENT_DEADLINE_HOURS', 24),
];
