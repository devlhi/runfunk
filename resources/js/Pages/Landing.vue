<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import SiteNav from '../Components/SiteNav.vue';
import SiteFooter from '../Components/SiteFooter.vue';
import { countdownParts, rupiah } from '../lib/format';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    sponsors: { type: Array, default: () => [] },
    news: { type: Array, default: () => [] },
    ketua: { type: Object, default: () => ({}) },
});

/**
 * Data rute per kategori.
 *
 * Titik air dan titik medis TIDAK selalu berada di tempat yang sama: di 10K
 * water station ada di KM 2, 4, 6, 8 sedangkan pos medis di KM 3, 7, 9 —
 * sengaja diselang-seling supaya sepanjang rute selalu ada petugas terdekat.
 * Di 5K keduanya menyatu di KM 2,5. Karena itu tiap titik punya penanda
 * `air` dan `medis` sendiri, bukan diasumsikan menempel.
 */
const rutes = [
    {
        slug: '5k',
        label: '5K',
        peta: '/images/rute-5k.webp',
        // Dimensi asli berkas — dipasang ke <img> agar browser memesan ruang
        // sebelum petanya turun, jadi tata letak tidak melonjak (CLS).
        w: 1200, h: 874,
        start: 'Lapangan Tuladenggi',
        jalan: 'Jl. Gorontalo Outer Ring Road',
        titik: [
            { km: '2,5', air: true, medis: true },
        ],
    },
    {
        slug: '10k',
        label: '10K',
        peta: '/images/rute-10k.webp',
        w: 1195, h: 872,
        start: 'Lapangan Tuladenggi',
        jalan: 'Jl. Ahmad A. Wahab · Outer Ring Road · Jl. Kota Gorontalo · Jl. Abdul Gandi Pajuhi',
        titik: [
            { km: '2', air: true, medis: false },
            { km: '3', air: false, medis: true },
            { km: '4', air: true, medis: false },
            { km: '6', air: true, medis: false },
            { km: '7', air: false, medis: true },
            { km: '8', air: true, medis: false },
            { km: '9', air: false, medis: true },
        ],
    },
];

/** Nama titik menyesuaikan isinya, jadi tidak ada yang perlu ditulis dua kali. */
function namaTitik(t) {
    if (t.air && t.medis) return 'Water Station + Pos Medis';

    return t.air ? 'Water Station' : 'Pos Medis';
}

function isiTitik(t) {
    if (t.air && t.medis) return 'Air minum · tim medis siaga';

    return t.air ? 'Air minum & isi tenaga' : 'Tim medis siaga';
}

const ringkasTitik = (r) => {
    const air = r.titik.filter((t) => t.air).length;
    const medis = r.titik.filter((t) => t.medis).length;

    return `${air} water station · ${medis} pos medis`;
};

// Ditampilkan bergantian lewat tab supaya halaman tidak memanjang ke bawah.
const ruteAktif = ref('5k');
const rute = computed(() => rutes.find((r) => r.slug === ruteAktif.value) ?? rutes[0]);

// Kalau berkas petanya hilang, tampilkan keterangan — bukan ikon gambar rusak.
const petaGagal = ref({});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const eventDate = computed(() => page.props.event?.date_iso ?? '2026-10-31T06:00:00+08:00');
const event = computed(() => page.props.event ?? {});

const clock = ref(countdownParts(eventDate.value));
let timer = null;
let observer = null;

/** Peserta yang belum masuk diarahkan membuat akun dulu, sesuai alur pendaftaran. */
function ctaFor(slug) {
    if (!user.value) return `/daftar-akun?kategori=${slug}`;
    if (user.value.is_panitia) return '/panitia';

    return `/pendaftaran/baru?kategori=${slug}`;
}

const primaryCta = computed(() => {
    if (!user.value) return { href: '/daftar-akun', label: 'Daftar Sekarang →' };
    if (user.value.is_panitia) return { href: '/panitia', label: 'Buka Panel Panitia →' };

    return { href: '/pendaftaran/baru', label: 'Lanjut Daftar Lomba →' };
});

const longestDistance = computed(() => {
    const numbers = props.categories
        .map((c) => parseInt(String(c.distance_label), 10))
        .filter((n) => !Number.isNaN(n));

    return numbers.length ? Math.max(...numbers) : 10;
});

onMounted(() => {
    timer = setInterval(() => {
        clock.value = countdownParts(eventDate.value);
    }, 1000);

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15 }
    );

    document.querySelectorAll('.reveal').forEach((node) => observer.observe(node));
});

onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
    if (observer) observer.disconnect();
});
</script>

<template>
    <Head title="Fun Run Gorontalo · Lari 5K & 10K" />

    <SiteNav show-anchors />

    <main>
    <header class="hero" id="top">
        <!-- Latar hero: foto pelari di separuh kanan + gradient yang bergerak pelan. -->
        <div class="hero-photo" aria-hidden="true"></div>
        <div class="hero-glow" aria-hidden="true"></div>

        <div class="wrap hero-in">
            <div>
                <div class="hero-org">
                    <img src="/images/logo-ika.jpeg" width="640" height="640" alt="Logo Ikatan Keluarga Alumni SMK Gotong Royong Telaga Gorontalo" />
                    <span>
                        <b>Diselenggarakan oleh IKA</b>
                        Ikatan Keluarga Alumni SMK Gotong Royong Telaga, Gorontalo
                    </span>
                </div>

                <!-- Tanggal dan lokasi dipecah dua span: di layar sempit keduanya
                     turun baris pada titik yang disengaja, bukan patah di tengah
                     nama tempat ("LAPANGAN / TULADENGGI"). -->
                <div class="eyebrow">
                    <b></b>
                    <span class="eyebrow-txt">
                        <span>Sabtu, 31 Oktober 2026</span>
                        <span class="eyebrow-loc">Lapangan Tuladenggi, Gorontalo</span>
                    </span>
                </div>
                <h1>
                    <span class="l1">Gong</span><br />
                    <span class="l2">Fun Run</span><br />
                    <span class="l3">2026</span>
                </h1>
                <p class="hero-sub">
                    Fun run 5K &amp; 10K di Gorontalo. <b>Terbuka untuk umum</b>, semua usia —
                    dari yang baru mulai lari sampai yang mengejar catatan waktu.
                </p>
                <div class="hero-cta">
                    <Link :href="primaryCta.href" class="btn">{{ primaryCta.label }}</Link>
                    <a href="#kategori" class="btn btn--ghost">Lihat Kategori</a>
                </div>
                <div class="meta-strip">
                    <div class="m"><span class="k">Flag Off</span><span class="v">06.00 WITA</span></div>
                    <div class="m"><span class="k">Kategori</span><span class="v">5K · 10K</span></div>
                    <div class="m"><span class="k">Start / Finis</span><span class="v">Lap. Tuladenggi</span></div>
                    <div class="m"><span class="k">Peserta</span><span class="v">Terbuka Umum</span></div>
                </div>
            </div>

            <div class="clock" role="timer" aria-label="Hitung mundur menuju acara">
                <div class="clock-h">
                    <span class="t">Menuju Hari Event</span>
                    <span class="live"><i></i>Live</span>
                </div>
                <div class="cd">
                    <div><div class="n">{{ clock.d }}</div><div class="u">Hari</div></div>
                    <div><div class="n">{{ clock.h }}</div><div class="u">Jam</div></div>
                    <div><div class="n">{{ clock.m }}</div><div class="u">Menit</div></div>
                    <div><div class="n">{{ clock.s }}</div><div class="u">Detik</div></div>
                </div>
                <div class="clock-foot">
                    <template v-if="clock.done">🏁 Event sudah dimulai — selamat berlari!</template>
                    <template v-else>⏱ Kuota terbatas — amankan slotmu lebih awal.</template>
                </div>
            </div>
        </div>
    </header>

    <div class="stats">
        <div class="wrap stats-grid">
            <div class="stat reveal"><div class="n">{{ stats.total_quota ?? 3000 }}</div><div class="l">Slot Peserta</div></div>
            <div class="stat reveal"><div class="n">{{ categories.length }}</div><div class="l">Kategori Lari</div></div>
            <div class="stat reveal"><div class="n">{{ longestDistance }}</div><div class="l">KM Jarak Terjauh</div></div>
            <!-- Jumlah pendaftar sengaja tidak ditampilkan ke publik. -->
            <div class="stat reveal"><div class="n">06.00</div><div class="l">Flag Off WITA</div></div>
        </div>
    </div>

    <section id="info">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <div class="sec-tag"><span class="lane">1</span>Info Acara</div>
                    <h2>Satu pagi, satu garis start, semua senang</h2>
                </div>
                <p>
                    Bukan soal siapa tercepat. Gong Fun Run terbuka untuk siapa saja — warga Gorontalo
                    maupun pelari dari luar daerah — buat kamu yang mau gerak bareng keluarga dan teman,
                    sambil bawa pulang medali.
                </p>
            </div>
            <div class="fac-grid">
                <div class="fac reveal">
                    <div class="fac-ic">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                    </div>
                    <h3>Kapan</h3>
                    <p>Sabtu, 31 Oktober 2026. Flag off 06.00 WITA. Datang lebih awal buat pemanasan &amp; foto bareng.</p>
                </div>
                <div class="fac reveal">
                    <div class="fac-ic">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0116 0Z" /><circle cx="12" cy="10" r="3" /></svg>
                    </div>
                    <h3>Di mana</h3>
                    <p>Start &amp; finis di Lapangan Tuladenggi, Kabupaten Gorontalo. Rute menyusuri Gorontalo Outer Ring Road dan jalan utama sekitarnya.</p>
                </div>
                <div class="fac reveal">
                    <div class="fac-ic">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9a6 6 0 0012 0V3H6v6Z" /><path d="M6 3H4v2a3 3 0 003 3M18 3h2v2a3 3 0 01-3 3M9 21h6M12 15v6" /></svg>
                    </div>
                    <h3>Untuk siapa</h3>
                    <p>Semua umur. Kategori 5K santai buat keluarga, dan 10K buat yang mau tantangan. Bisa lari, bisa jalan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Sambutan Ketua IKA. Fotonya sengaja ditaruh sebelum kategori & biaya:
         yang mengajak lari di sini orangnya, bukan angkanya. -->
    <section id="sambutan" class="sambutan">
        <div class="wrap">
            <div class="sam-grid">
                <figure class="sam-foto reveal">
                    <img
                        src="/images/ketua-ika.webp"
                        :alt="`${ketua.jabatan} berlari mengenakan jersey ${event.name}`"
                        width="600" height="1312" loading="lazy"
                    />
                    <figcaption>
                        <b v-if="ketua.nama">{{ ketua.nama }}</b>
                        <span>{{ ketua.jabatan }}</span>
                    </figcaption>
                </figure>

                <div class="sam-teks reveal">
                    <div class="sec-tag"><span class="lane">★</span>Sambutan</div>
                    <h2>Ayo lari bareng, dari alumni untuk semua</h2>

                    <blockquote class="sam-kutip">
                        <p v-for="(paragraf, i) in ketua.pesan" :key="i">{{ paragraf }}</p>
                    </blockquote>

                    <div class="sam-ttd">
                        <span class="sam-nama">{{ ketua.nama || ketua.jabatan }}</span>
                        <span v-if="ketua.nama" class="sam-jabatan">{{ ketua.jabatan }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="kategori" style="background:linear-gradient(180deg,var(--paper),var(--paper-2))">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <div class="sec-tag"><span class="lane">2</span>Kategori &amp; Biaya</div>
                    <h2>Pilih nomor bib-mu</h2>
                </div>
                <p>Harga sudah termasuk race pack lengkap. Kuota terbatas per kategori — daftar mepet, kehabisan.</p>
            </div>

            <div class="bibs">
                <div
                    v-for="category in categories"
                    :key="category.slug"
                    class="bib reveal"
                    :class="{ feature: category.is_featured }"
                >
                    <span v-if="category.is_featured" class="flag">Paling Diminati</span>
                    <span class="pin tl"></span><span class="pin tr"></span>

                    <div class="arc">Gong Funrun · 2026</div>
                    <div class="num">
                        {{ category.distance_label.replace(/K$/i, '') }}<small>K</small>
                    </div>
                    <div class="cat">{{ category.name }}</div>
                    <p class="desc">{{ category.tagline }}</p>

                    <ul class="feat">
                        <li v-for="feature in category.features" :key="feature">{{ feature }}</li>
                    </ul>

                    <!-- Biaya hanya tampil untuk yang sudah masuk; untuk tamu
                         bagian ini tidak dirender sama sekali. -->
                    <div v-if="category.price !== null" class="price">
                        <div class="rp">Rp</div>
                        <div class="amt">{{ rupiah(category.price, false) }}</div>
                    </div>

                    <Link v-if="!category.is_sold_out" class="pick" :href="ctaFor(category.slug)">
                        Pilih {{ category.distance_label }}
                    </Link>
                    <span v-else class="pick is-off">Kuota Habis</span>
                </div>
            </div>
        </div>
    </section>

    <section id="rute">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <div class="sec-tag"><span class="lane">3</span>Peta Rute</div>
                    <h2>Rute jalan raya, aman &amp; terukur</h2>
                </div>
                <p>Start dan finis di titik yang sama. Setiap water station dijaga tim medis, dan di garis finis sudah menunggu air minum serta buah segar.</p>
            </div>

            <!-- Dipisah tab supaya halaman tidak memanjang ke bawah. -->
            <div class="route-tabs" role="tablist" aria-label="Pilih kategori rute">
                <button
                    v-for="r in rutes" :key="r.slug" type="button" role="tab"
                    class="route-tab" :class="{ 'is-on': ruteAktif === r.slug }"
                    :aria-selected="ruteAktif === r.slug"
                    @click="ruteAktif = r.slug"
                >
                    <span class="route-chip" :class="`route-chip--${r.slug}`">{{ r.label }}</span>
                    <span class="route-tab-txt">
                        <b>Rute {{ r.label }}</b>
                        <small>{{ ringkasTitik(r) }}</small>
                    </span>
                </button>
            </div>

            <article :key="rute.slug" class="route-block">
                <div class="route-wrap">
                    <ul class="route-list">
                        <li>
                            <span class="km start">START</span>
                            <span class="place">
                                <b>{{ rute.start }}</b>
                                <span>Registrasi ulang · panggung · pos medis</span>
                            </span>
                        </li>
                        <li v-for="t in rute.titik" :key="t.km">
                            <span class="km" :class="{ 'is-medis': t.medis && !t.air }">KM {{ t.km }}</span>
                            <span class="place">
                                <b>
                                    {{ namaTitik(t) }}
                                    <i v-if="t.air" class="tanda tanda--air" title="Water station">💧</i>
                                    <i v-if="t.medis" class="tanda tanda--medis" title="Pos medis">✚</i>
                                </b>
                                <span>{{ isiTitik(t) }}</span>
                            </span>
                        </li>
                        <li>
                            <span class="km finish">FINIS</span>
                            <span class="place">
                                <b>Garis Finis</b>
                                <span>Air minum, buah segar, medali, pos medis, dan foto bareng</span>
                            </span>
                        </li>
                    </ul>

                    <div class="map-card">
                        <div v-if="!petaGagal[rute.slug]" class="map-frame">
                            <img
                                :src="rute.peta"
                                :width="rute.w" :height="rute.h"
                                :alt="`Peta rute ${rute.label} Gong Fun Run 2026 dengan titik start, finis, dan water station`"
                                loading="lazy"
                                @error="petaGagal[rute.slug] = true"
                            />
                            <!-- Menempati sudut yang dikosongkan dari panel logo poster. -->
                            <span class="map-badge">Rute<b>{{ rute.label }}</b></span>
                        </div>
                        <div v-else class="map-kosong">
                            <span>🗺</span>
                            Peta rute {{ rute.label }} sedang disiapkan panitia.
                        </div>
                        <div class="map-cap">{{ rute.jalan }}</div>
                    </div>
                </div>
            </article>

            <div class="route-note">
                <span class="rn-ic" aria-hidden="true">✚</span>
                <p>
                    <b>Pos medis dan water station sengaja diselang-seling</b> di jalur 10K —
                    air di KM 2, 4, 6, 8 dan tim medis di KM 3, 7, 9 — supaya sepanjang rute
                    selalu ada petugas dalam jarak dekat. Di jalur 5K keduanya menyatu di KM 2,5.
                    Tim medis juga berjaga di garis start/finis. Kalau merasa tidak enak badan
                    di tengah rute, berhenti dan lapor ke petugas terdekat — jangan dipaksakan.
                </p>
            </div>
        </div>
    </section>

    <section id="fasilitas" style="background:linear-gradient(180deg,var(--paper),var(--paper-2))">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <div class="sec-tag"><span class="lane">4</span>Race Pack</div>
                    <h2>Yang kamu bawa pulang</h2>
                </div>
                <p>Setiap pendaftar dapat race pack lengkap. Diambil H-2 di lokasi (detail lewat WhatsApp).</p>
            </div>

            <!-- Wujud asli jersey & medali, biar peserta tahu persis yang dibawa pulang. -->
            <div class="merch">
                <figure class="merch-card reveal">
                    <span class="merch-tag">Jersey Resmi</span>
                    <img src="/images/jersey.jpeg" width="1376" height="768" alt="Jersey Gong Fun Run 2026 tampak depan dan belakang, warna hijau dengan tulisan emas" loading="lazy" />
                    <figcaption>
                        <b>Jersey Gong Fun Run 2026</b>
                        Bahan adem dan ringan, sablon emas depan-belakang. Ukuran S sampai XXL,
                        dipilih saat mendaftar.
                    </figcaption>
                </figure>

                <figure class="merch-card reveal">
                    <span class="merch-tag merch-tag--gold">Medali Finisher</span>
                    <img src="/images/medali.jpeg" width="1408" height="768" alt="Medali finisher Gong Fun Run 2026 berwarna emas dengan pita hijau" loading="lazy" />
                    <figcaption>
                        <b>Medali Finisher</b>
                        Medali emas berpita hijau bergambar pelari dan panorama Gorontalo.
                        Untuk semua yang menyentuh garis finis, 5K maupun 10K.
                    </figcaption>
                </figure>
            </div>

            <div class="fac-grid">
                <div class="fac reveal">
                    <div class="fac-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l4 2v14H6V8L4 7Zm16 0l-4 2v14h2V8l2-1Z" /><path d="M8 6h8v3H8z" /></svg></div>
                    <h3>Jersey Lari</h3><p>Kaos edisi Gong Funrun berbahan adem dan ringan.</p>
                </div>
                <div class="fac reveal">
                    <div class="fac-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="15" r="6" /><path d="M9 9L7 3h10l-2 6" /></svg></div>
                    <h3>Medali Finisher</h3><p>Buat semua yang berhasil menyentuh garis finis.</p>
                </div>
                <div class="fac reveal">
                    <div class="fac-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="6" width="16" height="12" rx="2" /><path d="M8 10h8M8 14h4" /></svg></div>
                    <h3>Bib &amp; Timing Chip</h3><p>Nomor peserta plus chip untuk catatan waktu otomatis (10K).</p>
                </div>
                <div class="fac reveal">
                    <div class="fac-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12l-1 8H7L6 2Z" /><path d="M8 10c0 4 2 6 4 6s4-2 4-6M12 16v4M8 22h8" /></svg></div>
                    <h3>Goodie Bag</h3><p>Snack, minuman, dan produk dari sponsor pilihan.</p>
                </div>
                <div class="fac reveal">
                    <div class="fac-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="14" rx="2" /><circle cx="12" cy="13" r="3.5" /><path d="M8 6l1.5-2h5L16 6" /></svg></div>
                    <h3>Foto &amp; Sertifikat</h3><p>Foto aksi lari dan e-sertifikat digital dikirim setelah acara.</p>
                </div>
                <div class="fac reveal">
                    <div class="fac-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V4s-1 1-4 1-5-2-8-2-4 1-4 1Z" /><path d="M4 22v-7" /></svg></div>
                    <h3>Hiburan Finis</h3><p>Musik, doorprize, dan jajanan di area finis. Ajak keluarga!</p>
                </div>
            </div>
        </div>
    </section>

    <section id="daftar">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <div class="sec-tag"><span class="lane">5</span>Cara Daftar</div>
                    <h2>Tiga langkah, langsung lari</h2>
                </div>
                <p>Prosesnya cepat dan online sepenuhnya. Bayar pakai QRIS, e-wallet, atau transfer bank.</p>
            </div>
            <div class="steps">
                <div class="step reveal">
                    <div class="no">1</div>
                    <h3>Buat Akun &amp; Isi Data</h3>
                    <p>Daftar akun peserta, pilih 5K atau 10K, lalu lengkapi data diri dan ukuran jersey.</p>
                </div>
                <div class="step reveal">
                    <div class="no">2</div>
                    <h3>Bayar &amp; Unggah Bukti</h3>
                    <p>Transfer sesuai nominal, unggah bukti di dashboard. Panitia verifikasi maksimal 1x24 jam.</p>
                </div>
                <div class="step reveal">
                    <div class="no">3</div>
                    <h3>Ambil Race Pack &amp; Lari</h3>
                    <p>Nomor BIB terbit otomatis setelah lunas. Tunjukkan e-tiket saat ambil race pack H-2.</p>
                </div>
            </div>
            <div class="steps-cta">
                <Link :href="primaryCta.href" class="btn">{{ primaryCta.label }}</Link>
            </div>
        </div>
    </section>

    <!-- Berita terbaru. Bagian ini hilang sendiri kalau panitia belum
         menerbitkan apa pun, jadi tidak ada kotak kosong di landing page. -->
    <section v-if="news.length" id="berita" style="background:linear-gradient(180deg,var(--paper),var(--paper-2))">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <div class="sec-tag"><span class="lane">6</span>Berita Terbaru</div>
                    <h2>Kabar dari panitia</h2>
                </div>
                <p>Perkembangan persiapan, jadwal, dan info terbaru menjelang hari event.</p>
            </div>

            <div class="berita-grid">
                <Link
                    v-for="n in news" :key="n.slug"
                    :href="`/berita/${n.slug}`"
                    class="berita-card reveal"
                >
                    <span class="bc-media">
                        <img v-if="n.cover_url" :src="n.cover_url" :alt="n.title" loading="lazy" />
                        <span v-else class="bc-kosong">📰</span>
                    </span>
                    <span class="bc-isi">
                        <span class="bc-tgl mono">{{ n.published_at }}</span>
                        <b>{{ n.title }}</b>
                        <span class="bc-ring">{{ n.excerpt }}</span>
                        <span class="bc-baca">Baca selengkapnya →</span>
                    </span>
                </Link>
            </div>

            <div class="steps-cta">
                <Link href="/berita" class="btn btn--ghost">Lihat Semua Berita →</Link>
            </div>
        </div>
    </section>

    <section class="sponsors" style="padding-bottom:56px">
        <div class="wrap">
            <div class="sec-tag" style="justify-content:center">Didukung Oleh</div>
            <div class="spon-row">
                <component
                    v-for="s in sponsors"
                    :key="s.name"
                    :is="s.website_url ? 'a' : 'span'"
                    :href="s.website_url || undefined"
                    :target="s.website_url ? '_blank' : undefined"
                    :rel="s.website_url ? 'noopener noreferrer' : undefined"
                    class="spon"
                    :class="`spon--${s.tier}`"
                >{{ s.name }}</component>
            </div>
        </div>
    </section>

    <section id="faq" style="background:linear-gradient(180deg,var(--paper-2),var(--paper));padding-top:56px">
        <div class="wrap">
            <div class="sec-head" style="justify-content:center;text-align:center;flex-direction:column;align-items:center;margin-bottom:40px">
                <div class="sec-tag" style="justify-content:center">Pertanyaan Umum</div>
                <h2 style="max-width:20ch">Masih ragu? Ini jawabannya</h2>
            </div>
            <div class="faq-list">
                <details open>
                    <summary>Boleh jalan kaki, nggak harus lari?<span class="chev">+</span></summary>
                    <div class="a">Boleh banget. Ini fun run — santai aja. Kategori 5K memang dirancang buat lari maupun jalan cepat bareng keluarga.</div>
                </details>
                <details>
                    <summary>Bagaimana cara mendaftarnya?<span class="chev">+</span></summary>
                    <div class="a">Buat akun peserta dulu lewat tombol Daftar Sekarang, pilih kategori, isi data, lalu selesaikan pembayaran dan unggah buktinya. Status pendaftaran bisa kamu pantau kapan saja di dashboard.</div>
                </details>
                <details>
                    <summary>Ada batas waktu (cut-off)?<span class="chev">+</span></summary>
                    <div class="a">Ada cut-off longgar: 5K sekitar 90 menit, 10K sekitar 120 menit. Cukup nyaman buat sebagian besar peserta.</div>
                </details>
                <details>
                    <summary>Race pack diambil di mana?<span class="chev">+</span></summary>
                    <div class="a">Di Lapangan Tuladenggi, H-2 sebelum hari-H. Bawa e-tiket dari dashboard dan identitas. Jam &amp; titik pengambilan dikirim lewat WhatsApp.</div>
                </details>
                <details>
                    <summary>Peserta dari luar Gorontalo bisa ikut?<span class="chev">+</span></summary>
                    <div class="a">Bisa. Pendaftaran terbuka untuk umum. Race pack bisa diambil di lokasi H-2 atau saat registrasi ulang pagi hari-H.</div>
                </details>
                <details>
                    <summary>Kalau berhalangan, bisa refund?<span class="chev">+</span></summary>
                    <div class="a">Slot bisa dialihkan ke orang lain sampai H-7. Refund biaya pendaftaran tidak tersedia, tapi race pack tetap bisa diambil atau dititipkan.</div>
                </details>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="wrap">
            <h2>Siap lari?</h2>
            <p>Slot terbatas dan cepat habis. Amankan nomor bib-mu sekarang sebelum kehabisan.</p>
            <Link :href="primaryCta.href" class="btn">Daftar Gong Funrun 2026 →</Link>
        </div>
    </section>
    </main>

    <SiteFooter show-anchors />
</template>

<style scoped>
/* ---------------------------------------------------------------- Hero */
.hero { position: relative; background: var(--ink); color: var(--paper); overflow: hidden; padding: 74px 0 150px; }
.hero::before {
  content: ""; position: absolute; inset: 0; pointer-events: none;
  background-image: repeating-linear-gradient(115deg, transparent 0 46px, rgba(255, 255, 255, .035) 46px 48px);
}
/* ── Lapisan latar hero ────────────────────────────────────────────────────
   Dua lapis, dari bawah ke atas:
   1. .hero-photo — foto pelari sungguhan di separuh kanan
   2. .hero-glow  — gradient jingga-emas yang bergerak perlahan */

/* Foto hanya mengisi separuh kanan hero, lalu memudar ke kiri. Sisi kiri
   dibiarkan gelap polos supaya judul besar dan paragrafnya tetap tajam
   terbaca — kalau foto dipasang penuh, teks putih di atasnya jadi ramai.

   Fotonya pelari berjersey resmi acara (potret 688x1384) — menggantikan foto
   kerumunan maraton luar yang dua kali bermasalah: crop close-up kaki yang
   dikeluhkan terlalu terbuka, dan nomor dada bersponsor acara lain di bagian
   atasnya. Posisi 50% 18% menjaga kepala tetap di dalam bingkai. */
.hero-photo {
  position: absolute; inset: 0 0 0 auto; width: 56%; z-index: 0; pointer-events: none;
  background-image: url('/images/hero-runners.jpg');
  background-size: cover;
  background-position: 50% 18%;
  background-repeat: no-repeat;
  opacity: .58;
  filter: saturate(.85) contrast(1.05);
  -webkit-mask-image: linear-gradient(to left, #000 0%, rgba(0, 0, 0, .82) 42%, transparent 92%);
  mask-image: linear-gradient(to left, #000 0%, rgba(0, 0, 0, .82) 42%, transparent 92%);
}

/* Rona gelap tipis di atas foto supaya kartu hitung mundur tetap menonjol. */
.hero-photo::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(to bottom, rgba(23, 19, 31, .45) 0%, transparent 38%, rgba(23, 19, 31, .6) 100%);
}

.hero-glow {
  position: absolute; inset: -20% -10%; z-index: 0; pointer-events: none;
  background:
    radial-gradient(42% 55% at 18% 22%, rgba(255, 74, 28, .42), transparent 68%),
    radial-gradient(38% 48% at 82% 30%, rgba(255, 176, 32, .30), transparent 70%),
    radial-gradient(46% 52% at 55% 88%, rgba(15, 176, 126, .26), transparent 72%);
  filter: blur(14px);
  animation: heroDrift 24s ease-in-out infinite alternate;
}

@keyframes heroDrift {
  0%   { transform: translate3d(0, 0, 0) scale(1); }
  50%  { transform: translate3d(-3%, 2%, 0) scale(1.08); }
  100% { transform: translate3d(3%, -2%, 0) scale(1.04); }
}

/* ── Blok penyelenggara ── */
.hero-org {
  display: inline-flex; align-items: center; gap: .8rem; margin-bottom: 22px;
  padding: .5rem .95rem .5rem .5rem;
  border: 1.5px solid rgba(255, 255, 255, .2); border-radius: 50px;
  background: rgba(255, 255, 255, .06); backdrop-filter: blur(4px);
}
.hero-org img { width: 42px; height: 42px; border-radius: 50%; flex: none; background: #fff; object-fit: cover; }
.hero-org span { display: flex; flex-direction: column; line-height: 1.25; font-size: .78rem; color: #CFC7DA; max-width: 32ch; }
.hero-org b { font-family: 'Space Mono'; font-size: .68rem; letter-spacing: .12em; text-transform: uppercase; color: var(--marigold); }
.hero-in { position: relative; z-index: 2; display: grid; grid-template-columns: 1.15fr .85fr; gap: 52px; align-items: center; }
.eyebrow { display: inline-flex; align-items: baseline; gap: .6rem; font-family: 'Space Mono'; font-size: .78rem; letter-spacing: .14em; text-transform: uppercase; color: var(--marigold); margin-bottom: 22px; }
.eyebrow b { width: 8px; height: 8px; border-radius: 50%; background: var(--marigold); display: inline-block; flex: none; }
.eyebrow-txt { display: flex; flex-wrap: wrap; column-gap: .55rem; row-gap: .4rem; }
/* Titik pemisah hanya saat keduanya masih satu baris. */
.eyebrow-loc::before { content: '·'; margin-right: .55rem; }
.hero h1 { font-size: clamp(3.6rem, 10vw, 7.2rem); font-weight: 900; }
.hero h1 .l2 { color: var(--flame); }
.hero h1 .l3 { -webkit-text-stroke: 2px var(--paper); color: transparent; }
.hero-sub { margin: 24px 0 30px; font-size: 1.15rem; max-width: 34ch; color: #E9E1D2; }
.hero-sub b { color: var(--marigold); font-weight: 700; }
.hero-cta { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 34px; }
.meta-strip { display: flex; flex-wrap: wrap; gap: 28px; padding-top: 26px; border-top: 1.5px solid rgba(255, 255, 255, .16); }
.meta-strip .m { display: flex; flex-direction: column; gap: 2px; }
.meta-strip .k { font-family: 'Space Mono'; font-size: .7rem; letter-spacing: .12em; text-transform: uppercase; color: #A79FB4; }
.meta-strip .v { font-family: 'Big Shoulders Display'; font-weight: 700; font-size: 1.35rem; }

/* ------------------------------------------------------------- Countdown */
.clock { background: var(--paper); color: var(--ink); border: 2.5px solid var(--ink); border-radius: 20px; padding: 26px 24px; box-shadow: 8px 8px 0 var(--flame); }
.clock-h { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.clock-h .t { font-family: 'Space Mono'; font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--ink-soft); }
.live { display: inline-flex; align-items: center; gap: .4rem; font-family: 'Space Mono'; font-size: .68rem; letter-spacing: .1em; color: var(--flame); }
.live i { width: 8px; height: 8px; background: var(--flame); border-radius: 50%; display: inline-block; animation: blink 1.4s steps(2) infinite; }
@keyframes blink { 50% { opacity: .25; } }
.cd { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; text-align: center; }
.cd .n { font-family: 'Space Mono'; font-weight: 700; font-size: clamp(1.9rem, 4.5vw, 2.9rem); background: var(--ink); color: var(--paper); border-radius: 12px; padding: .35em 0 .2em; letter-spacing: .02em; }
.cd .u { font-family: 'Space Mono'; font-size: .64rem; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-soft); margin-top: 8px; }
.clock-foot { margin-top: 20px; font-size: .86rem; color: var(--ink-soft); display: flex; align-items: center; gap: .5rem; }

/* ---------------------------------------------------------------- Stats */
/* Pita angka: gradien hijau ke toska, memakai warna yang sama dengan lajur
   samping panel panitia — supaya halaman depan dan panel terasa satu keluarga.
   Warna lama: var(--cobalt) biru polos (#2C4CFF).
   Tekstur garis miringnya kutipan dari hero, biar bidang selebar ini tidak
   terbaca sebagai blok warna datar. */
.stats {
  position: relative;
  background:
    repeating-linear-gradient(115deg, transparent 0 46px, rgba(255, 255, 255, .05) 46px 48px),
    linear-gradient(100deg, #0B6E50 0%, #0A6A63 52%, #0E7490 100%);
  color: var(--paper);
  border-top: 2.5px solid var(--ink);
  border-bottom: 2.5px solid var(--ink);
}
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; }
.stat { padding: 46px 22px; text-align: center; border-right: 1.5px solid rgba(255, 255, 255, .2); }
.stat:last-child { border-right: none; }
.stat .n { font-family: 'Big Shoulders Display'; font-weight: 900; font-size: clamp(2.6rem, 6vw, 4rem); line-height: 1; }
/* Label ikut bergeser ke rona toska muda; #DDE2FF yang lama kebiruan dan
   terlihat asing di atas latar hijau. Dicerahkan sampai #DCF2EB karena label
   ini huruf kecil — pada rona yang lebih tua ia cuma 4,3:1 di ujung terbiru
   gradien, tepat di bawah ambang keterbacaan. */
.stat .l { font-family: 'Space Mono'; font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; margin-top: 8px; color: #DCF2EB; }

/* ----------------------------------------------------------- Facilities */
.fac-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.fac { background: var(--paper); border: 2.5px solid var(--ink); border-radius: 16px; padding: 26px 24px; transition: .15s; }
.fac:hover { background: var(--ink); color: var(--paper); }
.fac:hover .fac-ic { background: var(--marigold); }
.fac-ic { width: 52px; height: 52px; border-radius: 12px; background: var(--cobalt); border: 2.5px solid var(--ink); display: grid; place-items: center; margin-bottom: 16px; color: var(--paper); }
.fac h3 { font-size: 1.35rem; font-weight: 700; margin-bottom: 6px; }
.fac p { font-size: .9rem; color: inherit; opacity: .85; }

/* --------------------------------------------------------------- Bibs */
.bibs { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; max-width: 860px; margin: 0 auto; }
.bib { position: relative; background: var(--paper); border: 2.5px solid var(--ink); border-radius: 20px; padding: 36px 34px 30px; box-shadow: 6px 6px 0 var(--ink); transition: transform .15s ease, box-shadow .15s ease; }
.bib:hover { transform: translate(-3px, -3px); box-shadow: 9px 9px 0 var(--ink); }
.bib.feature { background: var(--marigold); }
.bib .pin { position: absolute; width: 12px; height: 12px; border-radius: 50%; background: var(--paper); border: 2.5px solid var(--ink); }
.bib .pin.tl { top: 14px; left: 16px; } .bib .pin.tr { top: 14px; right: 16px; }
.bib .arc { font-family: 'Space Mono'; font-size: .7rem; letter-spacing: .18em; text-transform: uppercase; text-align: center; color: var(--ink-soft); border-bottom: 2px dashed var(--ink); padding-bottom: 14px; margin: 8px 0 18px; }
.bib .num { font-family: 'Space Mono'; font-weight: 700; font-size: 4.2rem; text-align: center; line-height: 1; letter-spacing: .03em; }
.bib .num small { font-size: 1.8rem; }
.bib .cat { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 2.2rem; text-align: center; margin-top: 8px; }
.bib .desc { text-align: center; font-size: .92rem; color: var(--ink-soft); margin: 10px auto 20px; max-width: 26ch; }
.bib.feature .desc { color: #5A4410; }
.bib .feat { list-style: none; display: flex; flex-direction: column; gap: 9px; max-width: 260px; margin: 0 auto 22px; text-align: left; }
.bib .feat li { display: flex; gap: .55rem; align-items: flex-start; font-size: .9rem; }
.bib .feat li::before { content: ""; flex: none; margin-top: .4em; width: 8px; height: 8px; border-radius: 50%; background: var(--flame); }
.bib.feature .feat li::before { background: var(--ink); }
.bib .price { text-align: center; border-top: 2px dashed var(--ink); padding-top: 18px; margin-bottom: 18px; }
.bib .price .rp { font-family: 'Space Mono'; font-size: .82rem; color: var(--ink-soft); }
.bib .price .amt { font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 2.4rem; letter-spacing: .01em; }
/* Tamu tidak melihat blok harga sama sekali, jadi daftar fitur perlu jarak
   bawah sendiri supaya tidak menempel ke tombol. */
.bib .feat:last-of-type { margin-bottom: 24px; }
.bib.feature .price .slot { color: #5A4410; }
.bib .pick { display: block; width: 100%; text-align: center; font-weight: 700; font-size: .95rem; padding: .85rem; border: 2.5px solid var(--ink); border-radius: 50px; background: var(--ink); color: var(--paper); cursor: pointer; transition: .12s; }
.bib .pick:hover { background: var(--flame); }
.bib.feature .pick:hover { background: var(--paper); color: var(--ink); }
.bib .pick.is-off { background: #CFC7BA; color: var(--ink-soft); cursor: not-allowed; }
.flag { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: var(--flame); color: var(--paper); font-family: 'Space Mono'; font-size: .66rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; padding: .3rem .85rem; border: 2.5px solid var(--ink); border-radius: 50px; white-space: nowrap; }

/* --------------------------------------------------------------- Route */
.route-wrap { display: grid; grid-template-columns: .9fr 1.1fr; gap: 46px; align-items: center; }
.route-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }
.route-list li { display: flex; gap: 16px; align-items: flex-start; padding: 16px 0; border-bottom: 1.5px solid var(--line); }
.route-list li:last-child { border-bottom: none; }
.route-list .km { font-family: 'Space Mono'; font-weight: 700; font-size: .88rem; background: var(--ink); color: var(--paper); border-radius: 8px; padding: .4rem .55rem; flex: none; min-width: 64px; text-align: center; }
.route-list .km.finish { background: var(--flame); }
.route-list .km.start { background: var(--mint); }
/* Titik yang hanya berisi tim medis dibedakan warnanya dari titik air. */
.route-list .km.is-medis { background: #C81E1E; }
.route-list .place b { display: flex; align-items: center; gap: .4rem; }
.tanda { font-style: normal; font-size: .78rem; line-height: 1; }
.tanda--medis { color: #C81E1E; }
.route-list .place b { display: block; font-family: 'Big Shoulders Display'; font-weight: 700; font-size: 1.15rem; letter-spacing: .01em; }
.route-list .place span { font-size: .86rem; color: var(--ink-soft); }
.map-card { background: var(--ink); border: 2.5px solid var(--ink); border-radius: 20px; padding: 14px; box-shadow: 8px 8px 0 var(--cobalt); }
.map-frame { position: relative; container-type: inline-size; }
.map-card img { display: block; width: 100%; height: auto; border-radius: 12px; }
/* Label ini duduk di sudut peta yang dikosongkan dari panel logo poster,
   supaya area transparannya terlihat memang disengaja. */
/* Label "Rute 5K/10K" duduk di panel putih yang sudah tercetak di sudut kiri
   atas kedua peta. Dulu ukurannya rem tetap + min-width 74px: di layar sempit
   panel putihnya ikut mengecil tapi tulisannya tidak, jadi "5K" tumpah ke atas
   citra satelit dan terbaca berantakan. Sekarang satuannya cqw — ikut lebar
   peta (.map-frame jadi container) — jadi selalu pas di dalam panel di semua
   ukuran layar. Warnanya juga digelapkan: marigold nyaris hilang di atas putih,
   diganti flame untuk angka dan tinta lembut untuk kata "Rute". */
.map-badge {
  position: absolute; top: 0; left: 0; z-index: 2;
  width: 10.4%; aspect-ratio: 1 / 2.1;
  display: flex; flex-direction: column; align-items: flex-start; justify-content: center;
  padding: 0 0 0 4%;
  font-family: 'Space Mono'; font-size: 1.55cqw; letter-spacing: .12em;
  text-transform: uppercase; color: var(--ink-soft); line-height: 1.2;
}
.map-badge b {
  font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 5cqw;
  letter-spacing: .01em; color: var(--flame);
}
.map-cap { font-family: 'Space Mono'; font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; color: #A79FB4; margin-top: 14px; text-align: center; line-height: 1.5; padding: 0 6px 4px; }
.map-kosong {
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;
  aspect-ratio: 4 / 3; border-radius: 12px; background: #221C2D;
  border: 2px dashed #4A4358; color: #A79FB4; font-size: .92rem; text-align: center; padding: 24px;
}
.map-kosong span { font-size: 2rem; }

/* Tab kategori rute */
.route-tabs { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 34px; }
.route-tab {
  display: flex; align-items: center; gap: 14px; cursor: pointer; text-align: left;
  padding: .7rem 1.3rem .7rem .7rem;
  border: 2.5px solid var(--ink); border-radius: 16px;
  background: var(--paper); color: var(--ink); transition: .14s;
}
.route-tab:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 var(--ink); }
.route-tab.is-on { background: var(--ink); color: var(--paper); box-shadow: 5px 5px 0 var(--flame); }
.route-tab-txt { display: flex; flex-direction: column; gap: 3px; }
.route-tab-txt b { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.5rem; text-transform: uppercase; line-height: 1; }
.route-tab-txt small { font-family: 'Space Mono'; font-size: .62rem; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-soft); }
.route-tab.is-on .route-tab-txt small { color: var(--marigold); }

.route-chip {
  flex: none; display: grid; place-items: center; min-width: 64px; padding: .45rem .8rem;
  font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 1.75rem; line-height: 1;
  border: 2.5px solid var(--ink); border-radius: 12px;
  background: var(--marigold); color: var(--ink);
}
.route-chip--10k { background: var(--cobalt); color: var(--paper); }

/* Catatan tim medis */
.route-note {
  display: flex; gap: 16px; align-items: flex-start; margin-top: 38px;
  padding: 20px 24px; border: 2.5px solid var(--ink); border-radius: 16px;
  background: var(--paper);
}
.route-note .rn-ic {
  flex: none; width: 38px; height: 38px; display: grid; place-items: center;
  border-radius: 50%; background: var(--flame); color: var(--paper);
  font-size: 1.2rem; font-weight: 700;
}
.route-note p { font-size: .94rem; color: var(--ink-soft); max-width: 76ch; }
.route-note b { color: var(--ink); }

/* --------------------------------------------------------------- Steps */
.steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; border: 2.5px solid var(--ink); border-radius: 20px; overflow: hidden; background: var(--paper); }
.step { padding: 36px 28px; border-right: 2.5px solid var(--ink); position: relative; }
.step:last-child { border-right: none; }
.step .no { font-family: 'Space Mono'; font-weight: 700; font-size: .8rem; color: var(--paper); background: var(--flame); width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; border: 2.5px solid var(--ink); margin-bottom: 18px; }
.step h3 { font-size: 1.45rem; font-weight: 700; margin-bottom: 8px; }
.step p { font-size: .9rem; color: var(--ink-soft); }
.steps-cta { text-align: center; margin-top: 42px; }

/* ------------------------------------------------- Sambutan ketua IKA */
.sambutan { background: linear-gradient(180deg, var(--paper-2), var(--paper)); }

.sam-grid { display: grid; grid-template-columns: minmax(0, 380px) minmax(0, 1fr); gap: 40px; align-items: center; }

.sam-foto {
  position: relative; margin: 0;
  border: 2.5px solid var(--ink); border-radius: 20px; overflow: hidden;
  background: var(--paper-2); box-shadow: 8px 8px 0 var(--mint);
}

/* Potret ponsel terlalu jangkung untuk dipasang utuh — dipotong ke 4:5 dan
   ditarik ke atas supaya wajahnya tetap di dalam bingkai.

   `height: auto` wajib ada: atribut height="1312" di HTML berlaku sebagai
   petunjuk tinggi tetap, dan selama tinggi itu pasti, aspect-ratio diabaikan
   sepenuhnya — fotonya terentang setinggi 1312 px. Atributnya sendiri tetap
   dipertahankan supaya browser memesan ruang sebelum gambarnya turun. */
.sam-foto img {
  display: block; width: 100%; height: auto;
  aspect-ratio: 4 / 5; object-fit: cover; object-position: 50% 18%;
}

.sam-foto figcaption {
  padding: 16px 20px; border-top: 2.5px solid var(--ink); background: var(--paper);
}
.sam-foto figcaption b {
  display: block; font-family: 'Big Shoulders Display'; font-weight: 800;
  font-size: 1.45rem; line-height: 1.1; text-transform: uppercase; color: var(--ink);
}
.sam-foto figcaption span {
  display: block; margin-top: 3px;
  font-family: 'Space Mono', monospace; font-size: .68rem; letter-spacing: .08em;
  text-transform: uppercase; color: var(--ink-soft);
}

.sam-teks h2 { margin-bottom: 20px; }

.sam-kutip { position: relative; margin: 0 0 24px; padding-left: 22px; border-left: 3px solid var(--flame); }
.sam-kutip p { font-size: 1rem; line-height: 1.75; color: var(--ink-soft); }
.sam-kutip p + p { margin-top: 14px; }

.sam-ttd { display: flex; flex-direction: column; gap: 2px; }
.sam-nama { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.35rem; text-transform: uppercase; color: var(--ink); }
.sam-jabatan { font-family: 'Space Mono', monospace; font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-soft); }

@media (max-width: 860px) {
  .sam-grid { grid-template-columns: 1fr; gap: 28px; }
  .sam-foto { max-width: 340px; margin-inline: auto; }
}

/* -------------------------------------------------- Jersey & medali */
.merch { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-bottom: 24px; }
.merch-card {
  position: relative; margin: 0; overflow: hidden;
  background: var(--paper); border: 2.5px solid var(--ink); border-radius: 18px;
  box-shadow: 6px 6px 0 var(--ink);
  transition: transform .15s ease, box-shadow .15s ease;
}
.merch-card:hover { transform: translate(-3px, -3px); box-shadow: 9px 9px 0 var(--ink); }
.merch-card img {
  display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: contain;
  background: #fff; border-bottom: 2.5px solid var(--ink);
}
.merch-tag {
  position: absolute; top: 14px; left: 14px; z-index: 2;
  font-family: 'Space Mono'; font-size: .64rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase;
  padding: .3rem .8rem; border: 2px solid var(--ink); border-radius: 50px;
  background: var(--flame); color: var(--paper);
}
.merch-tag--gold { background: var(--marigold); color: var(--ink); }
.merch-card figcaption { padding: 20px 22px 22px; font-size: .9rem; color: var(--ink-soft); line-height: 1.55; }
.merch-card figcaption b {
  display: block; font-family: 'Big Shoulders Display'; font-weight: 800;
  font-size: 1.5rem; text-transform: uppercase; color: var(--ink); margin-bottom: 6px;
}

/* -------------------------------------------------------------- Berita */
/* Lima kartu di grid tiga kolom akan menyisakan baris timpang (3 + 2), jadi
   berita terbaru dibuat melebar dua kolom: baris pertama 2+1, baris kedua
   1+1+1. Sekaligus memberi penekanan pada yang paling baru. */
.berita-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 8px; }

.berita-card:first-child { grid-column: span 2; flex-direction: row; }
.berita-card:first-child .bc-media {
  flex: 0 0 46%; aspect-ratio: auto;
  border-bottom: none; border-right: 2.5px solid var(--ink);
}
.berita-card:first-child .bc-isi { justify-content: center; padding: 24px 26px; }
.berita-card:first-child .bc-isi b { font-size: 1.75rem; }
.berita-card:first-child .bc-ring { flex: 0; font-size: .92rem; }

.berita-card {
  display: flex; flex-direction: column; overflow: hidden;
  background: var(--paper); border: 2.5px solid var(--ink); border-radius: 18px;
  box-shadow: 5px 5px 0 var(--ink);
  transition: transform .15s ease, box-shadow .15s ease;
}
.berita-card:hover { transform: translate(-3px, -3px); box-shadow: 8px 8px 0 var(--ink); }

.bc-media { display: block; aspect-ratio: 16 / 9; background: #EDE7D8; border-bottom: 2.5px solid var(--ink); overflow: hidden; }
.bc-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
.bc-kosong { display: grid; place-items: center; height: 100%; font-size: 2.2rem; opacity: .32; }

.bc-isi { display: flex; flex-direction: column; flex: 1; padding: 18px 20px 20px; }
.bc-tgl { font-size: .66rem; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-soft); margin-bottom: 8px; }
.bc-isi b { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.35rem; line-height: 1.05; text-transform: uppercase; margin-bottom: 8px; }
.bc-ring { font-size: .86rem; color: var(--ink-soft); line-height: 1.55; flex: 1; }
.bc-baca { margin-top: 14px; font-weight: 700; font-size: .84rem; color: var(--flame); }
.berita-card:hover .bc-baca { color: var(--ink); }

/* ------------------------------------------------------------ Sponsors */
.sponsors { text-align: center; }
.spon-row { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 16px; margin-top: 8px; }
.spon { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.4rem; letter-spacing: .02em; color: var(--ink-soft); border: 2.5px solid var(--line); border-radius: 12px; padding: .7rem 1.5rem; background: var(--paper); transition: .14s; }
/* Sponsor utama tampil lebih besar dan berbingkai tegas. */
.spon--utama { font-size: 1.75rem; color: var(--ink); border-color: var(--ink); padding: .8rem 1.8rem; }
.spon--media { font-size: 1.2rem; }
a.spon:hover { border-color: var(--flame); color: var(--flame); transform: translateY(-2px); }

/* ----------------------------------------------------------------- FAQ */
.faq-list { max-width: 820px; margin: 0 auto; }
details { border: 2.5px solid var(--ink); border-radius: 14px; background: var(--paper); margin-bottom: 14px; overflow: hidden; }
summary { list-style: none; cursor: pointer; padding: 20px 24px; font-family: 'Big Shoulders Display'; font-weight: 700; font-size: 1.3rem; letter-spacing: .01em; display: flex; justify-content: space-between; align-items: center; gap: 16px; text-transform: uppercase; }
summary::-webkit-details-marker { display: none; }
summary .chev { flex: none; width: 28px; height: 28px; border: 2.5px solid var(--ink); border-radius: 50%; display: grid; place-items: center; font-family: 'Space Mono'; font-weight: 700; transition: .2s; }
details[open] summary .chev { background: var(--flame); color: var(--paper); transform: rotate(45deg); }
details .a { padding: 0 24px 22px; color: var(--ink-soft); font-size: .98rem; }

/* ------------------------------------------------------------ CTA band */
.cta-band { background: var(--flame); color: var(--paper); border-top: 2.5px solid var(--ink); border-bottom: 2.5px solid var(--ink); text-align: center; }
.cta-band h2 { font-size: clamp(2.6rem, 7vw, 5rem); font-weight: 900; margin-bottom: 12px; }
.cta-band p { max-width: 34ch; margin: 0 auto 30px; font-size: 1.1rem; }
.cta-band .btn { background: var(--ink); color: var(--paper); box-shadow: 5px 5px 0 rgba(0, 0, 0, .35); }
.cta-band .btn:hover { background: var(--paper); color: var(--ink); box-shadow: 7px 7px 0 rgba(0, 0, 0, .35); }

/* ---------------------------------------------------------- Responsive */
@media (max-width: 920px) {
  .hero-in { grid-template-columns: 1fr; gap: 38px; }
  /* Peta didahulukan di layar sempit — lebih mudah dipahami daripada daftar. */
  .route-wrap { grid-template-columns: 1fr; gap: 28px; }
  .route-wrap .map-card { order: -1; }
  .steps { grid-template-columns: 1fr; }
  .step { border-right: none; border-bottom: 2.5px solid var(--ink); }
  .step:last-child { border-bottom: none; }
  .fac-grid { grid-template-columns: 1fr 1fr; }
  .merch { grid-template-columns: 1fr; }
  /* Dua kolom: kartu utama melebar penuh, empat sisanya berpasangan. */
  .berita-grid { grid-template-columns: 1fr 1fr; }
  .berita-card:first-child { grid-column: span 2; }

  /* Di layar sempit foto memenuhi SELURUH hero, bukan pita bawah 46% —
     versi pita jatuh di luar layar sehingga pelari baru terlihat setelah
     menggulir. Dibuat redup dan diberi selubung gelap supaya judul besar
     tetap tajam terbaca di atasnya. Fotonya potret, jadi posisi atas (25%)
     menjaga sosok pelarinya utuh di bingkai. */
  .hero-photo {
    inset: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: 50% 25%;
    opacity: .45;
    -webkit-mask-image: none;
    mask-image: none;
  }
  .hero-photo::after {
    background: linear-gradient(to bottom, rgba(23, 19, 31, .62) 0%, rgba(23, 19, 31, .3) 45%, rgba(23, 19, 31, .68) 100%);
  }
}
@media (max-width: 640px) {
  .bibs { grid-template-columns: 1fr; }
  .hero-org { padding: .45rem .8rem .45rem .45rem; }
  .hero-org img { width: 36px; height: 36px; }
  .hero-org span { font-size: .72rem; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .stat:nth-child(2) { border-right: none; }
  .stat:nth-child(1), .stat:nth-child(2) { border-bottom: 1.5px solid rgba(255, 255, 255, .2); }
  .fac-grid { grid-template-columns: 1fr; }
  .meta-strip { gap: 18px; }

  /* Tanggal dan lokasi bertumpuk rapi, bulatannya sejajar baris pertama. */
  .eyebrow { align-items: flex-start; }
  .eyebrow b { margin-top: 3px; }
  .eyebrow-txt { flex-direction: column; }
  .eyebrow-loc::before { content: none; margin-right: 0; }

  /* Dua tab rute berdampingan sama lebar — bukan dua kartu lebar penuh yang
     bertumpuk memakan setengah layar. Efek angkat saat hover ikut dimatikan:
     di layar sentuh ia hanya membuat tombol tampak melompat saat ditekan. */
  .route-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 26px; }
  .route-tab { padding: .55rem .7rem; gap: 10px; border-radius: 12px; }
  .route-tab:hover { transform: none; box-shadow: none; }
  .route-tab.is-on { box-shadow: 4px 4px 0 var(--flame); }
  .route-chip { min-width: 46px; font-size: 1.3rem; padding: .35rem .5rem; }
  .route-tab-txt { gap: 2px; min-width: 0; }
  .route-tab-txt b { font-size: 1.05rem; }
  .route-tab-txt small { font-size: .54rem; line-height: 1.45; }

  /* Satu kolom: semua kartu kembali bertumpuk vertikal, termasuk yang utama. */
  .berita-grid { grid-template-columns: 1fr; }
  .berita-card:first-child { grid-column: auto; flex-direction: column; }
  .berita-card:first-child .bc-media {
    flex: none; aspect-ratio: 16 / 9;
    border-right: none; border-bottom: 2.5px solid var(--ink);
  }
  .berita-card:first-child .bc-isi { padding: 18px 20px 20px; }
  .berita-card:first-child .bc-isi b { font-size: 1.35rem; }
}
</style>
