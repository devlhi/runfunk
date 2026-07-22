<script setup>
import { Link } from '@inertiajs/vue3';
import FlashMessage from '../Components/FlashMessage.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <!--
        Kolom tunggal yang dipusatkan. Sebelumnya halaman ini terbagi dua —
        panel merek di kiri, formulir di kanan — yang membuat kotak isian
        terdorong ke tepi layar. Sekarang formulirnya jadi titik fokus di
        tengah, dan konteks acara diringkas ke bawahnya.
    -->
    <div class="auth-wrap">
        <!-- Foto pelari mengisi separuh kanan, sama seperti hero landing page. -->
        <div class="auth-photo" aria-hidden="true"></div>
        <div class="auth-glow" aria-hidden="true"></div>

        <div class="auth-col">
            <div class="auth-card">
                <!-- Logo di dalam kartu, bukan melayang di atasnya: merek dan
                     formulir jadi satu kesatuan, bukan dua benda terpisah. -->
                <Link class="auth-logo" href="/">
                    <img src="/images/logo-ika.jpeg" alt="Logo IKA SMK Gotong Royong Telaga Gorontalo" />
                    <span>GONG<i>/</i>RUN</span>
                </Link>

                <FlashMessage />
                <h1 class="auth-title">{{ title }}</h1>
                <p v-if="subtitle" class="auth-sub">{{ subtitle }}</p>
                <slot />
            </div>

            <ul class="auth-facts">
                <li><b>5K</b> Santai buat keluarga</li>
                <li><b>10K</b> Timing chip &amp; juara</li>
                <li><b>Race pack</b> Jersey &amp; medali</li>
            </ul>

            <p class="auth-meta">
                Sabtu, 31 Oktober 2026 · Flag off 06.00 WITA · Lapangan Tuladenggi, Gorontalo
            </p>

            <Link href="/" class="auth-back">← Kembali ke halaman utama</Link>
        </div>
    </div>
</template>

<style scoped>
.auth-wrap {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 24px 56px;
    background: var(--paper);
    overflow: hidden;
}

/* Tekstur garis miring yang sama dengan hero, tapi versi gelap tipis karena
   latarnya sekarang terang. */
.auth-wrap::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background-image: repeating-linear-gradient(115deg, transparent 0 46px, rgba(23, 19, 31, .035) 46px 48px);
}

.auth-photo {
    position: absolute;
    inset: 0 0 0 auto;
    width: 52%;
    z-index: 0;
    pointer-events: none;
    background-image: url('/images/hero-runners.jpg');
    background-size: auto 150%;
    background-position: 58% 78%;
    /* Di latar terang, foto harus jauh lebih redup daripada di hero yang gelap
       supaya kartu formulir tetap jadi titik fokus. */
    opacity: .3;
    -webkit-mask-image: linear-gradient(to left, #000 0%, rgba(0, 0, 0, .75) 45%, transparent 95%);
    mask-image: linear-gradient(to left, #000 0%, rgba(0, 0, 0, .75) 45%, transparent 95%);
}

.auth-glow {
    position: absolute;
    inset: -20% -10%;
    z-index: 0;
    pointer-events: none;
    background:
        radial-gradient(40% 55% at 20% 30%, rgba(255, 176, 32, .28), transparent 70%),
        radial-gradient(38% 50% at 78% 70%, rgba(255, 74, 28, .16), transparent 72%);
    filter: blur(16px);
    animation: authDrift 26s ease-in-out infinite alternate;
}

@keyframes authDrift {
    from { transform: translate3d(0, 0, 0) scale(1); }
    to   { transform: translate3d(2%, -2%, 0) scale(1.06); }
}

.auth-col {
    position: relative;
    z-index: 3;
    width: min(480px, 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.auth-logo {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding-bottom: 18px;
    margin-bottom: 22px;
    border-bottom: 2px dashed var(--line);
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 1.5rem;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: var(--ink);
}

.auth-logo img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    border: 2.5px solid var(--ink);
}

.auth-logo i { color: var(--flame); font-style: normal; }

/* Kartu dibuat putih bersih supaya tetap menonjol dari latar krem di belakangnya. */
.auth-card {
    width: 100%;
    text-align: left;
    background: #FFFDF8;
    border: 2.5px solid var(--ink);
    border-radius: 22px;
    padding: 34px 32px 32px;
    box-shadow: 9px 9px 0 var(--flame);
}

.auth-title { font-size: clamp(2rem, 5vw, 2.6rem); font-weight: 800; margin-bottom: 8px; }
.auth-sub { color: var(--ink-soft); font-size: .94rem; margin-bottom: 26px; }

.auth-facts {
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px 18px;
    margin: 30px 0 16px;
}

.auth-facts li {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-size: .82rem;
    color: var(--ink-soft);
}

.auth-facts b {
    font-family: 'Space Mono', monospace;
    font-size: .64rem;
    font-weight: 700;
    letter-spacing: .08em;
    background: var(--marigold);
    color: var(--ink);
    border: 1.5px solid var(--ink);
    border-radius: 50px;
    padding: .2rem .6rem;
}

.auth-meta {
    font-family: 'Space Mono', monospace;
    font-size: .68rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--ink-soft);
    max-width: 40ch;
    line-height: 1.7;
    margin-bottom: 20px;
}

.auth-back {
    font-family: 'Space Mono', monospace;
    font-size: .74rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--flame);
}

.auth-back:hover { color: var(--ink); }

@media (max-width: 900px) {
    /* Di layar sempit foto pindah ke pita bawah — kalau tetap di kanan ia
       menindih kartu formulir yang kini memakai lebar penuh. */
    .auth-photo {
        inset: auto 0 0 0;
        width: 100%;
        height: 38%;
        opacity: .22;
        -webkit-mask-image: linear-gradient(to top, #000 0%, rgba(0, 0, 0, .6) 50%, transparent 100%);
        mask-image: linear-gradient(to top, #000 0%, rgba(0, 0, 0, .6) 50%, transparent 100%);
    }
}

@media (max-width: 640px) {
    .auth-wrap { padding: 32px 16px 44px; }
    .auth-card { padding: 26px 20px 24px; border-radius: 18px; box-shadow: 6px 6px 0 var(--flame); }
    .auth-facts { display: none; }
}

@media (prefers-reduced-motion: reduce) {
    .auth-glow { animation: none; }
}
</style>
