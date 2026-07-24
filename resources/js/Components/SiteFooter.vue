<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    showAnchors: { type: Boolean, default: false },
});
</script>

<template>
    <footer class="site-foot">
        <div class="wrap">
            <div class="foot-grid">
                <div class="foot-brand">
                    <Link class="brand" href="/">
                        <img class="foot-logo" src="/images/logo-ika.jpeg" alt="Logo IKA SMK Gotong Royong Telaga Gorontalo" />
                        GONG<span style="color:var(--flame)">/</span>RUN
                    </Link>
                    <p>
                        Fun run 5K &amp; 10K di Kabupaten Gorontalo. Terbuka untuk umum,
                        semua usia. Lari santai, seru bareng, pulang bawa medali.
                        Sabtu, 31 Oktober 2026.
                    </p>
                    <p class="foot-org">
                        Diselenggarakan oleh <b>IKA — Ikatan Keluarga Alumni SMK Gotong Royong Telaga,
                        Gorontalo.</b>
                    </p>
                </div>

                <div class="foot-col">
                    <h2>Acara</h2>
                    <template v-if="showAnchors">
                        <a href="#info">Info Acara</a>
                        <a href="#kategori">Kategori &amp; Biaya</a>
                        <a href="#rute">Peta Rute</a>
                        <a href="#daftar">Cara Daftar</a>
                    </template>
                    <template v-else>
                        <Link href="/#info">Info Acara</Link>
                        <Link href="/#kategori">Kategori &amp; Biaya</Link>
                        <Link href="/#rute">Peta Rute</Link>
                        <Link href="/#daftar">Cara Daftar</Link>
                    </template>
                </div>

                <div class="foot-col">
                    <h2>Kontak</h2>
                    <a href="mailto:halo@gongfunrun.id">halo@gongfunrun.id</a>
                    <a href="https://wa.me/6281200000000" target="_blank" rel="noopener">WhatsApp Panitia</a>
                    <a href="https://instagram.com/gongfunrun" target="_blank" rel="noopener">Instagram: @gongfunrun</a>
                </div>
            </div>

            <div class="foot-bot">
                <span>© 2026 Gong Funrun · IKA SMK Gotong Royong Telaga, Gorontalo</span>
                <span>Terbuka untuk umum · dijalankan bareng-bareng</span>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.foot-logo {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    flex: none;
}

.foot-org { margin-top: 12px; font-size: .86rem; color: #8C849A; }
.foot-org b { color: var(--marigold); font-weight: 700; }
</style>
