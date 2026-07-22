<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import SiteNav from '../../Components/SiteNav.vue';
import SiteFooter from '../../Components/SiteFooter.vue';
import ShareButtons from '../../Components/ShareButtons.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import Paginasi from '../../Components/Paginasi.vue';

const props = defineProps({
    news: { type: Object, required: true },
    comments: { type: Object, required: true },
    lainnya: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const shareUrl = computed(() =>
    typeof window !== 'undefined' ? window.location.href : `/berita/${props.news.slug}`
);

const form = useForm({ body: '' });
const konfirmasiHapus = ref(null);

const sisaKarakter = computed(() => 1000 - form.body.length);

function kirim() {
    form.post(`/berita/${props.news.slug}/komentar`, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}

function hapus(id) {
    router.delete(`/komentar/${id}`, {
        preserveScroll: true,
        onSuccess: () => (konfirmasiHapus.value = null),
    });
}
</script>

<template>
    <Head>
        <title>{{ news.title }}</title>
        <meta name="description" :content="news.excerpt" />
        <!-- Tag ini yang dibaca WhatsApp & Facebook saat tautannya dibagikan. -->
        <meta property="og:type" content="article" />
        <meta property="og:title" :content="news.title" />
        <meta property="og:description" :content="news.excerpt" />
        <meta v-if="news.cover_url" property="og:image" :content="news.cover_url" />
        <meta name="twitter:card" :content="news.cover_url ? 'summary_large_image' : 'summary'" />
    </Head>

    <SiteNav />

    <article class="artikel">
        <div class="wrap wrap-narrow">
            <FlashMessage />

            <div v-if="!news.is_published" class="draf-tanda">
                Berita ini <b>belum terbit</b>. Hanya panitia yang bisa melihatnya.
            </div>

            <Link href="/berita" class="balik mono">← Semua berita</Link>

            <header class="art-head">
                <div class="art-meta mono">{{ news.published_at }} · {{ news.author }} · {{ news.views }} kali dibaca</div>
                <h1>{{ news.title }}</h1>
            </header>

            <img v-if="news.cover_url" class="art-cover" :src="news.cover_url" :alt="news.title" />

            <!-- Isi ditampilkan sebagai teks biasa; HTML apa pun ikut ter-escape. -->
            <div class="art-body">{{ news.body }}</div>

            <div class="art-share">
                <ShareButtons :url="shareUrl" :title="news.title" />
            </div>

            <!-- ---------------------------------------------- Komentar --- -->
            <section class="komentar" id="komentar">
                <h2 class="kom-judul">Komentar <span>({{ comments.total }})</span></h2>

                <form v-if="user" class="kom-form" @submit.prevent="kirim">
                    <div class="field" style="margin-bottom:10px">
                        <label for="body" class="sr-only">Tulis komentar</label>
                        <textarea
                            id="body" v-model="form.body" class="textarea" rows="3"
                            :class="{ 'has-error': form.errors.body }"
                            maxlength="1000"
                            :placeholder="`Tulis komentar sebagai ${user.name}…`"
                        ></textarea>
                        <p v-if="form.errors.body" class="error">{{ form.errors.body }}</p>
                    </div>
                    <div class="kom-form-bawah">
                        <span class="mono sisa" :class="{ 'is-low': sisaKarakter < 100 }">{{ sisaKarakter }} karakter tersisa</span>
                        <button type="submit" class="btn btn--sm" :disabled="form.processing || !form.body.trim()">
                            {{ form.processing ? 'Mengirim…' : 'Kirim Komentar' }}
                        </button>
                    </div>
                </form>

                <div v-else class="kom-masuk">
                    <p>Masuk dulu untuk ikut berkomentar.</p>
                    <Link href="/masuk" class="btn btn--sm">Masuk</Link>
                </div>

                <ul v-if="comments.data.length" class="kom-list">
                    <li v-for="k in comments.data" :key="k.id" class="kom-item">
                        <span class="kom-av">{{ k.author.charAt(0).toUpperCase() }}</span>
                        <div class="kom-isi">
                            <div class="kom-head">
                                <b>{{ k.author }}</b>
                                <span v-if="k.is_staff" class="kom-badge">Panitia</span>
                                <span class="kom-waktu mono">{{ k.created_at }}</span>
                            </div>
                            <p>{{ k.body }}</p>

                            <div v-if="k.can_delete" class="kom-aksi">
                                <button v-if="konfirmasiHapus !== k.id" type="button" @click="konfirmasiHapus = k.id">Hapus</button>
                                <template v-else>
                                    <button type="button" class="is-danger" @click="hapus(k.id)">Yakin hapus</button>
                                    <button type="button" @click="konfirmasiHapus = null">Batal</button>
                                </template>
                            </div>
                        </div>
                    </li>
                </ul>

                <p v-else class="kom-kosong">Belum ada komentar. Jadilah yang pertama!</p>

                <Paginasi :data="comments" label="komentar" />
            </section>

            <!-- ------------------------------------------ Berita lain --- -->
            <section v-if="lainnya.length" class="lainnya">
                <h2 class="kom-judul">Berita Lainnya</h2>
                <div class="lain-grid">
                    <Link v-for="n in lainnya" :key="n.slug" :href="`/berita/${n.slug}`" class="lain-card">
                        <img v-if="n.cover_url" :src="n.cover_url" :alt="n.title" loading="lazy" />
                        <span v-else class="lain-kosong">📰</span>
                        <span class="lain-isi">
                            <b>{{ n.title }}</b>
                            <small class="mono">{{ n.published_at }}</small>
                        </span>
                    </Link>
                </div>
            </section>
        </div>
    </article>

    <SiteFooter />
</template>

<style scoped>
.wrap-narrow { max-width: 780px; }
.artikel { padding: 44px 0 80px; }

.draf-tanda {
    padding: 12px 16px; margin-bottom: 20px;
    border: 2px dashed var(--flame); border-radius: 10px;
    background: #FFF1EC; color: #A32209; font-size: .88rem;
}

.balik { display: inline-block; font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; color: var(--flame); margin-bottom: 22px; }
.balik:hover { color: var(--ink); }

.art-meta { font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-soft); margin-bottom: 12px; }
.art-head h1 { font-size: clamp(2.1rem, 5.5vw, 3.2rem); font-weight: 900; line-height: 1; margin-bottom: 26px; }

.art-cover {
    width: 100%; height: auto; display: block; margin-bottom: 30px;
    border: 2.5px solid var(--ink); border-radius: 16px; box-shadow: 6px 6px 0 var(--ink);
}

/* white-space menjaga paragraf yang diketik panitia tanpa perlu HTML. */
.art-body { font-size: 1.04rem; line-height: 1.75; color: var(--ink-soft); white-space: pre-line; }

.art-share { margin: 34px 0; padding: 20px 0; border-top: 2px dashed var(--line); border-bottom: 2px dashed var(--line); }

.kom-judul { font-size: 1.7rem; font-weight: 800; text-transform: uppercase; margin-bottom: 18px; }
.kom-judul span { color: var(--ink-soft); font-weight: 700; }

.kom-form { margin-bottom: 26px; }
.kom-form-bawah { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
.sisa { font-size: .7rem; color: var(--ink-soft); }
.sisa.is-low { color: var(--flame); font-weight: 700; }

.kom-masuk {
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    padding: 18px 20px; margin-bottom: 26px;
    border: 2.5px solid var(--ink); border-radius: 14px; background: var(--paper);
}
.kom-masuk p { font-size: .92rem; color: var(--ink-soft); }

.kom-list { list-style: none; display: flex; flex-direction: column; gap: 16px; }
.kom-item { display: flex; gap: 14px; padding-bottom: 16px; border-bottom: 1.5px solid var(--line); }
.kom-item:last-child { border-bottom: none; }

.kom-av {
    flex: none; width: 38px; height: 38px; display: grid; place-items: center;
    border-radius: 50%; background: var(--cobalt); color: #fff;
    font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 1.1rem;
}

.kom-isi { min-width: 0; flex: 1; }
.kom-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 5px; }
.kom-head b { font-size: .94rem; }

.kom-badge {
    font-family: 'Space Mono'; font-size: .58rem; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
    background: var(--marigold); color: var(--ink);
    border-radius: 20px; padding: .12rem .5rem;
}

.kom-waktu { margin-left: auto; font-size: .68rem; color: var(--ink-soft); }
.kom-isi p { font-size: .92rem; color: var(--ink-soft); line-height: 1.65; white-space: pre-line; word-break: break-word; }

.kom-aksi { display: flex; gap: 12px; margin-top: 8px; }
.kom-aksi button {
    background: none; border: none; padding: 0; cursor: pointer;
    font-family: 'Archivo', sans-serif; font-size: .76rem; font-weight: 700;
    color: var(--ink-soft); text-decoration: underline; text-underline-offset: 3px;
}
.kom-aksi button:hover { color: var(--ink); }
.kom-aksi button.is-danger { color: var(--danger); }

.kom-kosong { font-size: .92rem; color: var(--ink-soft); padding: 20px 0; }

.lainnya { margin-top: 48px; padding-top: 34px; border-top: 2.5px dashed var(--line); }
.lain-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; }
.lain-card { display: flex; flex-direction: column; border: 2px solid var(--ink); border-radius: 12px; overflow: hidden; background: var(--paper); transition: .14s; }
.lain-card:hover { transform: translateY(-3px); box-shadow: 4px 4px 0 var(--ink); }
.lain-card img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; border-bottom: 2px solid var(--ink); }
.lain-kosong { display: grid; place-items: center; aspect-ratio: 16/9; font-size: 1.8rem; opacity: .3; background: #EDE7D8; border-bottom: 2px solid var(--ink); }
.lain-isi { padding: 12px 14px; display: flex; flex-direction: column; gap: 4px; }
.lain-isi b { font-size: .9rem; line-height: 1.3; }
.lain-isi small { font-size: .66rem; color: var(--ink-soft); }

.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
