{{--
    Kerangka halaman galat.

    Sengaja berdiri sendiri tanpa Inertia/Vue: halaman ini justru paling sering
    tampil ketika ada yang tidak beres, jadi ia tidak boleh bergantung pada
    berkas JavaScript yang mungkin gagal dimuat. Gayanya ditulis sebaris.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('judul') — Gong Fun Run 2026</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 24px;
            /* Ditulis langsung, bukan lewat token: halaman ini sengaja berdiri
               sendiri tanpa berkas CSS aplikasi. Warna lama: #FBF4E6 (krem). */
            background: #E8F6F2; color: #17131F;
            font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif;
            background-image: repeating-linear-gradient(115deg, transparent 0 46px, rgba(23,19,31,.035) 46px 48px);
        }
        .kotak {
            width: min(520px, 100%); text-align: center;
            background: #FFFDF8; border: 2.5px solid #17131F; border-radius: 22px;
            padding: 44px 32px 36px; box-shadow: 9px 9px 0 #FF4A1C;
        }
        .merek {
            font-size: .72rem; font-weight: 800; letter-spacing: .22em;
            text-transform: uppercase; color: #6B6478; margin-bottom: 22px;
        }
        .merek i { color: #FF4A1C; font-style: normal; }
        .kode {
            font-family: 'Courier New', monospace; font-size: 4.2rem; font-weight: 700;
            line-height: 1; letter-spacing: .06em; color: #17131F;
        }
        .garis { width: 56px; height: 3px; background: #FF4A1C; margin: 18px auto 20px; }
        h1 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; margin-bottom: 10px; }
        p { font-size: .95rem; line-height: 1.65; color: #3A3348; }
        .aksi { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
        .btn {
            display: inline-block; padding: .75rem 1.4rem; border-radius: 10px;
            border: 2.5px solid #17131F; background: #FF4A1C; color: #fff;
            font-weight: 800; font-size: .9rem; text-decoration: none;
        }
        .btn--ghost { background: transparent; color: #17131F; }
        .kaki { margin-top: 26px; font-size: .74rem; color: #6B6478; }
    </style>
</head>
<body>
    <main class="kotak">
        <div class="merek">GONG<i>/</i>RUN</div>

        <div class="kode">@yield('kode')</div>
        <div class="garis"></div>

        <h1>@yield('judul')</h1>
        <p>@yield('pesan')</p>

        <div class="aksi">
            <a class="btn" href="{{ url('/') }}">Kembali ke Beranda</a>
            @yield('aksi-tambahan')
        </div>

        <div class="kaki">
            Panitia Gong Fun Run 2026 &middot; IKA SMK Gotong Royong Telaga, Gorontalo
        </div>
    </main>
</body>
</html>
