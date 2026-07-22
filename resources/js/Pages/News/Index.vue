<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SiteNav from '../../Components/SiteNav.vue';
import SiteFooter from '../../Components/SiteFooter.vue';

defineProps({
    news: { type: Object, required: true },
});
</script>

<template>
    <Head title="Berita" />

    <SiteNav />

    <header class="news-hero">
        <div class="wrap">
            <div class="sec-tag"><span class="lane">📰</span>Berita &amp; Info</div>
            <h1>Kabar dari Panitia</h1>
            <p>
                Perkembangan persiapan, jadwal, dan info terbaru seputar
                Gong Fun Run 2026.
            </p>
        </div>
    </header>

    <main class="wrap news-main">
        <div v-if="news.data.length" class="news-grid">
            <article v-for="n in news.data" :key="n.slug" class="news-card">
                <Link :href="`/berita/${n.slug}`" class="nc-media">
                    <img v-if="n.cover_url" :src="n.cover_url" :alt="n.title" loading="lazy" />
                    <span v-else class="nc-kosong">📰</span>
                </Link>

                <div class="nc-isi">
                    <div class="nc-meta mono">
                        {{ n.published_at }} · {{ n.author }}
                        <template v-if="n.comments_count"> · {{ n.comments_count }} komentar</template>
                    </div>
                    <h2><Link :href="`/berita/${n.slug}`">{{ n.title }}</Link></h2>
                    <p>{{ n.excerpt }}</p>
                    <Link :href="`/berita/${n.slug}`" class="nc-baca">Baca selengkapnya →</Link>
                </div>
            </article>
        </div>

        <div v-else class="news-kosong">
            <div class="big">Belum ada berita</div>
            <p>Panitia belum menerbitkan berita apa pun. Cek lagi nanti, ya.</p>
            <Link href="/" class="btn" style="margin-top:20px">← Kembali ke Beranda</Link>
        </div>

        <nav v-if="news.links && news.last_page > 1" class="pager">
            <template v-for="link in news.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="page"
                    :class="{ 'is-active': link.active }"
                    v-html="link.label"
                />
                <span v-else class="page is-off" v-html="link.label" />
            </template>
        </nav>
    </main>

    <SiteFooter />
</template>

<style scoped>
.news-hero { background: var(--ink); color: var(--paper); padding: 64px 0 56px; }
.news-hero h1 { font-size: clamp(2.6rem, 6vw, 4rem); font-weight: 900; margin-bottom: 14px; }
.news-hero p { color: #C9C1D4; max-width: 46ch; font-size: 1.02rem; }
.news-hero .sec-tag { color: var(--marigold); }
.news-hero .lane { background: var(--marigold); color: var(--ink); }

.news-main { padding: 56px 0 80px; }
.news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 26px; }

.news-card {
    display: flex;
    flex-direction: column;
    background: var(--paper);
    border: 2.5px solid var(--ink);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 5px 5px 0 var(--ink);
    transition: transform .15s ease, box-shadow .15s ease;
}

.news-card:hover { transform: translate(-3px, -3px); box-shadow: 8px 8px 0 var(--ink); }

.nc-media {
    display: block;
    aspect-ratio: 16 / 9;
    background: #EDE7D8;
    border-bottom: 2.5px solid var(--ink);
    overflow: hidden;
}

.nc-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
.nc-kosong { display: grid; place-items: center; height: 100%; font-size: 2.4rem; opacity: .35; }

.nc-isi { padding: 20px 22px 22px; display: flex; flex-direction: column; flex: 1; }
.nc-meta { font-size: .68rem; letter-spacing: .06em; text-transform: uppercase; color: var(--ink-soft); margin-bottom: 8px; }
.nc-isi h2 { font-size: 1.5rem; font-weight: 800; line-height: 1.05; margin-bottom: 10px; }
.nc-isi h2 a:hover { color: var(--flame); }
.nc-isi p { font-size: .9rem; color: var(--ink-soft); flex: 1; line-height: 1.6; }
.nc-baca { display: inline-block; margin-top: 16px; font-weight: 700; font-size: .88rem; color: var(--flame); }
.nc-baca:hover { color: var(--ink); }

.news-kosong { text-align: center; padding: 80px 24px; }
.news-kosong .big { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 2rem; text-transform: uppercase; margin-bottom: 8px; }
.news-kosong p { color: var(--ink-soft); }
</style>
