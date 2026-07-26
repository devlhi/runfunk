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

    // Deskripsi yang muncul di hasil pencarian. Ditulis mengalir tapi memuat
    // frasa yang paling dicari orang: "Fun Run Gorontalo", "5K/10K",
    // "alumni SMK Gotong Royong Telaga". Bukan tumpukan kata kunci — Google
    // menghukum itu — hanya kalimat wajar yang kebetulan memuat istilahnya.
    $ringkasan = "Fun Run Gorontalo 2026: lomba lari 5K & 10K di Kabupaten Gorontalo, "
        ."{$tanggal->translatedFormat('d F Y')}. Terbuka umum, semua usia — "
        .'diselenggarakan IKA (alumni) SMK Gotong Royong Telaga.';

    // Meta keywords: Google mengabaikannya, tapi ditambahkan sesuai permintaan
    // dan masih dibaca sebagian mesin pencari/alat lain. Dijaga ringkas dan
    // relevan — daftar yang terlalu panjang justru terbaca spam.
    $kataKunci = 'Fun Run Gorontalo, Fun Run 5K Gorontalo, Fun Run 10K Gorontalo, '
        .'lomba lari Gorontalo 2026, Gong Fun Run 2026, '
        .'Fun Run alumni SMK Gotong Royong Telaga, IKA SMK Gotong Royong Telaga, '
        .'lari santai Gorontalo, pendaftaran fun run Gorontalo';

    // Foto pelari dipakai sebagai gambar pratinjau saat tautannya dibagikan.
    $gambar = asset('images/hero-ketua.jpg');

    // Meta halaman = acara secara bawaan. Untuk artikel berita, ditimpa dengan
    // judul, ringkasan, dan sampul artikelnya sendiri. Ini WAJIB di sisi server:
    // perayap yang tak menjalankan JavaScript (WhatsApp, Facebook, dan ambilan
    // awal Google) hanya membaca HTML awal ini — bukan tag yang dipasang Inertia
    // di sisi klien. Modelnya sudah di-resolve route binding, jadi tanpa kueri
    // tambahan.
    $judulHalaman = config('app.name', 'Gong Fun Run 2026');
    $ogJudul = "{$namaAcara} — Fun Run 5K & 10K Gorontalo";
    $deskripsi = $ringkasan;
    $ogGambar = $gambar;
    $ogTipe = 'website';

    try {
        $artikel = request()->routeIs('news.show') ? request()->route('news') : null;
        if ($artikel instanceof \App\Models\News && $artikel->is_published) {
            $judulHalaman = $artikel->title;
            $ogJudul = $artikel->title;
            $deskripsi = $artikel->ringkasan();
            $ogTipe = 'article';
            if ($sampul = $artikel->coverUrl()) {
                $ogGambar = $sampul;
            }
        }
    } catch (\Throwable) {
        // Biarkan memakai meta acara bawaan.
    }

    // Dibaca dari daftar middleware rutenya sendiri, bukan ditulis ulang di sini —
    // jadi tag <meta> ini tidak akan pernah berbeda pendapat dengan tajuk
    // X-Robots-Tag yang dipasang middleware NoIndex.
    $privat = collect(request()->route()?->gatherMiddleware() ?? [])->contains('jangan-indeks');

    // Disusun di sini, bukan langsung di dalam @json() pada badan halaman:
    // direktif Blade tidak bisa mengurai larik bersarang multi-baris.
    $dataAcara = null;
    if (request()->path() === '/') {
        // CATATAN: properti "offers" SENGAJA tidak dipasang, walau Search Console
        // menandainya hilang (peringatannya "non-kritis" — tidak menghalangi
        // hasil kaya). Isinya wajib memuat harga, sedangkan situs ini memang
        // menyembunyikan biaya dari pengunjung yang belum masuk — aturan yang
        // dijaga MenuSmokeTest ("harganya juga tidak boleh terselip di sumber
        // halaman"). Data terstruktur ikut terbaca siapa pun, jadi memasangnya
        // sama saja membocorkan harga lewat pintu belakang.
        $acara = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $namaAcara,
            'description' => $ringkasan,
            'startDate' => $tanggal->toAtomString(),
            // Search Console menandai "endDate" hilang. Lari + acara di garis
            // finis (musik, doorprize) wajar rampung sekitar tengah hari.
            'endDate' => $tanggal->copy()->addHours(6)->toAtomString(),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'image' => $gambar,
            'url' => url('/'),
            'sport' => 'Running',
            // Nama lain yang dipakai orang mencari acara ini.
            'alternateName' => [
                'Fun Run Gorontalo 2026',
                'Gong Fun Run Gorontalo',
                'Fun Run Alumni SMK Gotong Royong Telaga',
            ],
            'keywords' => 'fun run Gorontalo, lomba lari 5K & 10K Gorontalo, lari santai, '
                .'IKA SMK Gotong Royong Telaga, Gong Fun Run 2026',
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
            // Search Console menandai "performer" hilang. Pada lomba massal,
            // yang "tampil" adalah para pelarinya sendiri.
            'performer' => [
                '@type' => 'PerformingGroup',
                'name' => 'Pelari '.$namaAcara,
            ],
        ];

        $dataAcara = json_encode($acara, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Prioritaskan foto hero: itulah elemen LCP di beranda. Sebagai
         background-image CSS, ia baru ditemukan browser SETELAH CSS diunduh &
         diurai — itu yang membuat LCP lambat. Preload + fetchpriority=high
         membuatnya ditemukan langsung di HTML awal dan diunduh paling dulu. --}}
    @if (request()->path() === '/')
        <link rel="preload" as="image" href="{{ asset('images/hero-ketua.jpg') }}" fetchpriority="high">
    @endif

    <title inertia>{{ $judulHalaman }}</title>

    {{-- Favicon: logo IKA sebagai favicon.ico (memuat 16/32/48/64 px). ?v=2
         memaksa peramban memuat ulang, melewati ikon lama yang ter-cache. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">

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

    {{-- Deskripsi yang muncul di bawah judul pada hasil pencarian. Untuk artikel
         berita, ini ringkasan artikelnya; selebihnya ringkasan acara. --}}
    <meta name="description" content="{{ $deskripsi }}">
    @unless ($privat)
        <meta name="keywords" content="{{ $kataKunci }}">
    @endunless
    <meta name="author" content="IKA SMK Gotong Royong Telaga, Gorontalo">
    <meta name="theme-color" content="#0F766E">

    {{-- Open Graph: dipakai WhatsApp, Facebook, dan Telegram saat tautan
         dibagikan. Nilainya per-halaman (lihat blok @php di atas) supaya tiap
         artikel berita punya kartu sendiri, bukan kartu acara yang sama. --}}
    <meta property="og:type" content="{{ $ogTipe }}">
    <meta property="og:site_name" content="{{ $namaAcara }}">
    <meta property="og:title" content="{{ $ogJudul }}">
    <meta property="og:description" content="{{ $deskripsi }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $ogGambar }}">
    <meta property="og:image:alt" content="{{ $ogTipe === 'article' ? $ogJudul : 'Peserta fun run sedang berlari' }}">
    <meta property="og:locale" content="id_ID">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogJudul }}">
    <meta name="twitter:description" content="{{ $deskripsi }}">
    <meta name="twitter:image" content="{{ $ogGambar }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Font dimuat TANPA memblokir render: dipasang sebagai media=print (browser
         mengunduhnya di jalur non-kritis) lalu dialihkan ke "all" oleh app.js.
         CATATAN: pengalihan TIDAK boleh lewat atribut onload sebaris — CSP situs
         (script-src 'self', lihat SecurityHeaders) memblokir handler sebaris,
         sehingga font tidak akan pernah tampil. app.js dilayani dari origin
         sendiri, jadi diizinkan CSP. <noscript> menjaga font tetap termuat bila
         JavaScript dimatikan. --}}
    <link rel="stylesheet" media="print" data-font
          href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Big+Shoulders+Display:wght@600;700;800;900&family=Space+Mono:wght@400;700&display=swap">
    <noscript>
        <link rel="stylesheet"
              href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Big+Shoulders+Display:wght@600;700;800;900&family=Space+Mono:wght@400;700&display=swap">
    </noscript>

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
