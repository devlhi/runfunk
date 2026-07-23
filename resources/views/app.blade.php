@php
    use App\Models\Setting;
    use Illuminate\Support\Carbon;

    // Dibungkus try/catch: view ini juga dirender saat basis datanya belum
    // dimigrasi — misalnya begitu selesai clone dan orang langsung membuka
    // halaman depan. Situs harus tetap tampil, bukan menyodorkan galat 500.
    try {
        $namaAcara = Setting::ambil('event_name') ?: 'Gong Fun Run 2026';
        $lokasi = Setting::ambil('location') ?: config('funrun.location');
        $tanggalMentah = Setting::ambil('event_date') ?: config('funrun.event_date');
        $googleVerif = Setting::ambil('google_verification') ?: '';
    } catch (\Throwable) {
        $namaAcara = 'Gong Fun Run 2026';
        $lokasi = config('funrun.location');
        $tanggalMentah = config('funrun.event_date');
        $googleVerif = '';
    }

    // Developer boleh menempel kode saja atau seluruh tag <meta> dari Google —
    // di sini diambil kodenya saja supaya tetap benar di kedua kasus.
    if ($googleVerif && preg_match('/content=["\']([^"\']+)["\']/', $googleVerif, $m)) {
        $googleVerif = $m[1];
    }

    $tanggal = Carbon::parse($tanggalMentah);

    $ringkasan = "Fun run 5K & 10K di Gorontalo, {$tanggal->translatedFormat('d F Y')}. "
        .'Terbuka untuk umum, semua usia. Diselenggarakan IKA SMK Gotong Royong Telaga.';

    // Foto pelari dipakai sebagai gambar pratinjau saat tautannya dibagikan.
    $gambar = asset('images/hero-runners.jpg');

    // Dibaca dari daftar middleware rutenya sendiri, bukan ditulis ulang di sini —
    // jadi tag <meta> ini tidak akan pernah berbeda pendapat dengan tajuk
    // X-Robots-Tag yang dipasang middleware NoIndex.
    $privat = collect(request()->route()?->gatherMiddleware() ?? [])->contains('jangan-indeks');

    // Disusun di sini, bukan langsung di dalam @json() pada badan halaman:
    // direktif Blade tidak bisa mengurai larik bersarang multi-baris.
    $dataAcara = request()->path() === '/'
        ? json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $namaAcara,
            'description' => $ringkasan,
            'startDate' => $tanggal->toAtomString(),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'image' => $gambar,
            'url' => url('/'),
            'sport' => 'Running',
            'location' => [
                '@type' => 'Place',
                'name' => $lokasi,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Kabupaten Gorontalo',
                    'addressRegion' => 'Gorontalo',
                    'addressCountry' => 'ID',
                ],
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'IKA — Ikatan Keluarga Alumni SMK Gotong Royong Telaga',
                'url' => url('/'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
        : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Gong Fun Run 2026') }}</title>

    @if ($privat)
        {{-- Halaman ini berisi data orang. Selain tajuk X-Robots-Tag, ditandai
             juga di sini karena sebagian perayap hanya membaca salah satunya. --}}
        <meta name="robots" content="noindex, nofollow, noarchive">
    @else
        <meta name="robots" content="index, follow, max-image-preview:large">
        <link rel="canonical" href="{{ url()->current() }}">
    @endif

    @if ($googleVerif)
        <meta name="google-site-verification" content="{{ $googleVerif }}">
    @endif

    {{-- Deskripsi yang muncul di bawah judul pada hasil pencarian. --}}
    <meta name="description" content="{{ $ringkasan }}">
    <meta name="author" content="IKA SMK Gotong Royong Telaga, Gorontalo">
    <meta name="theme-color" content="#0F766E">

    {{-- Open Graph: dipakai WhatsApp, Facebook, dan Telegram saat tautan
         dibagikan. Untuk acara seperti ini, penyebarannya justru lewat sana,
         bukan lewat mesin pencari. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $namaAcara }}">
    <meta property="og:title" content="{{ $namaAcara }} — Fun Run 5K & 10K Gorontalo">
    <meta property="og:description" content="{{ $ringkasan }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $gambar }}">
    <meta property="og:image:alt" content="Peserta fun run sedang berlari">
    <meta property="og:locale" content="id_ID">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $namaAcara }} — Fun Run 5K & 10K Gorontalo">
    <meta name="twitter:description" content="{{ $ringkasan }}">
    <meta name="twitter:image" content="{{ $gambar }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Big+Shoulders+Display:wght@600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia

    {{-- Data terstruktur acara. Google memakainya untuk menampilkan kartu acara
         berisi tanggal dan lokasi langsung di hasil pencarian. Hanya dipasang
         di beranda supaya tidak terbaca sebagai banyak acara berbeda. --}}
    @if ($dataAcara)
        <script type="application/ld+json">{!! $dataAcara !!}</script>
    @endif
</body>
</html>
