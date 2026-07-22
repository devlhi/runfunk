<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

defineProps({
    news: { type: Object, required: true },
});

const editing = ref(null);
const confirmDelete = ref(null);
const previewSampul = ref(null);

const blank = {
    title: '',
    excerpt: '',
    body: '',
    is_published: false,
    published_at: '',
    cover: null,
};

const form = useForm({ ...blank });

function startCreate() {
    editing.value = null;
    previewSampul.value = null;
    form.clearErrors();
    form.defaults({ ...blank });
    form.reset();
}

function startEdit(n) {
    editing.value = n.id;
    previewSampul.value = n.cover_url;
    form.clearErrors();
    form.title = n.title;
    form.excerpt = n.excerpt ?? '';
    form.body = n.body;
    form.is_published = n.is_published;
    form.published_at = n.published_at ?? '';
    form.cover = null;
}

function pilihSampul(e) {
    const berkas = e.target.files?.[0] ?? null;
    form.cover = berkas;
    previewSampul.value = berkas ? URL.createObjectURL(berkas) : null;
}

function submit() {
    const opsi = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => startCreate(),
    };

    if (editing.value) {
        // Unggahan berkas tidak terkirim lewat PATCH di banyak browser, jadi
        // dikirim sebagai POST dengan penanda _method.
        form.transform((d) => ({ ...d, _method: 'post' })).post(`/panitia/berita/${editing.value}`, opsi);
    } else {
        form.post('/panitia/berita', opsi);
    }
}

function destroy(id) {
    router.delete(`/panitia/berita/${id}`, {
        preserveScroll: true,
        onSuccess: () => (confirmDelete.value = null),
    });
}
</script>

<template>
    <Head title="Kelola Berita" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Kelola Berita"
        lede="Tulis berita yang tampil di halaman publik /berita. Peserta yang sudah masuk bisa ikut berkomentar."
    >
        <template #actions>
            <a class="btn btn--ghost btn--sm" href="/berita" target="_blank" rel="noopener">Lihat Halaman Berita</a>
        </template>

        <div class="grid grid-side">
            <div class="panel panel--pop">
                <h2 class="panel-title">Daftar Berita</h2>
                <p class="panel-sub">
                    {{ news.total }} berita tersimpan · menampilkan
                    {{ news.from ?? 0 }}–{{ news.to ?? 0 }}.
                </p>

                <div v-if="news.data.length" class="list">
                    <article v-for="n in news.data" :key="n.id" class="item" :class="{ 'is-draft': !n.is_published }">
                        <div class="item-media">
                            <img v-if="n.cover_url" :src="n.cover_url" :alt="n.title" />
                            <span v-else>📰</span>
                        </div>

                        <div class="item-isi">
                            <div class="item-head">
                                <span class="badge" :class="n.is_published ? 'badge--confirmed' : 'badge--cancelled'">
                                    {{ n.is_published ? 'Terbit' : 'Draf' }}
                                </span>
                                <span class="waktu mono">
                                    {{ n.published_label ?? 'Belum dijadwalkan' }} · {{ n.author }}
                                </span>
                            </div>

                            <h3>{{ n.title }}</h3>
                            <p class="ringkas">{{ n.excerpt || n.body.slice(0, 120) }}</p>

                            <div class="item-stat mono">
                                {{ n.views }} dibaca · {{ n.comments_count }} komentar
                            </div>

                            <div class="item-aksi">
                                <button type="button" class="btn btn--ghost btn--sm" @click="startEdit(n)">Ubah</button>
                                <a
                                    v-if="n.is_published" class="btn btn--ghost btn--sm"
                                    :href="`/berita/${n.slug}`" target="_blank" rel="noopener"
                                >Buka</a>
                                <button
                                    v-if="confirmDelete !== n.id" type="button"
                                    class="btn btn--ghost btn--sm" @click="confirmDelete = n.id"
                                >Hapus</button>
                                <template v-else>
                                    <button type="button" class="btn btn--danger btn--sm" @click="destroy(n.id)">
                                        Hapus berita &amp; {{ n.comments_count }} komentarnya
                                    </button>
                                    <button type="button" class="btn btn--ghost btn--sm" @click="confirmDelete = null">Batal</button>
                                </template>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="empty">
                    <div class="big">Belum ada berita</div>
                    <p>Tulis berita pertama lewat formulir di samping.</p>
                </div>

                <nav v-if="news.last_page > 1" class="pager">
                    <template v-for="link in news.links" :key="link.label">
                        <Link
                            v-if="link.url" :href="link.url" class="page"
                            :class="{ 'is-active': link.active }"
                            preserve-scroll v-html="link.label"
                        />
                        <span v-else class="page is-off" v-html="link.label" />
                    </template>
                </nav>
            </div>

            <aside>
                <div class="panel">
                    <h2 class="panel-title">{{ editing ? 'Ubah Berita' : 'Tulis Berita' }}</h2>
                    <p class="panel-sub">
                        {{ editing ? 'Menyimpan langsung memperbarui halaman publiknya.' : 'Simpan sebagai draf dulu kalau belum siap terbit.' }}
                    </p>

                    <form @submit.prevent="submit">
                        <div class="field">
                            <label for="title">Judul <span class="req">*</span></label>
                            <input
                                id="title" v-model="form.title" type="text" class="input"
                                :class="{ 'has-error': form.errors.title }"
                                placeholder="Contoh: Rute 10K resmi diumumkan" required
                            />
                            <p v-if="form.errors.title" class="error">{{ form.errors.title }}</p>
                        </div>

                        <div class="field">
                            <label for="cover">Gambar Sampul</label>
                            <input
                                id="cover" type="file" class="input"
                                accept="image/jpeg,image/png,image/webp"
                                @change="pilihSampul"
                            />
                            <p v-if="form.errors.cover" class="error">{{ form.errors.cover }}</p>
                            <p v-else class="help">JPG, PNG, atau WEBP. Maksimal 3 MB.</p>

                            <div v-if="previewSampul" class="sampul-pv">
                                <img :src="previewSampul" alt="Pratinjau sampul" />
                            </div>
                        </div>

                        <div class="field">
                            <label for="excerpt">Ringkasan</label>
                            <textarea
                                id="excerpt" v-model="form.excerpt" class="textarea" rows="2"
                                :class="{ 'has-error': form.errors.excerpt }"
                                maxlength="300" placeholder="Satu-dua kalimat untuk kartu di daftar berita."
                            ></textarea>
                            <p v-if="form.errors.excerpt" class="error">{{ form.errors.excerpt }}</p>
                            <p v-else class="help">Boleh dikosongkan — akan diambil otomatis dari awal isi berita.</p>
                        </div>

                        <div class="field">
                            <label for="body">Isi Berita <span class="req">*</span></label>
                            <textarea
                                id="body" v-model="form.body" class="textarea" rows="10"
                                :class="{ 'has-error': form.errors.body }"
                                placeholder="Tulis biasa saja. Baris kosong jadi paragraf baru." required
                            ></textarea>
                            <p v-if="form.errors.body" class="error">{{ form.errors.body }}</p>
                            <p v-else class="help">Teks biasa. Kode HTML tidak akan dijalankan, demi keamanan.</p>
                        </div>

                        <div class="field">
                            <label for="published_at">Jadwal Terbit</label>
                            <input
                                id="published_at" v-model="form.published_at" type="datetime-local" class="input"
                                :class="{ 'has-error': form.errors.published_at }"
                            />
                            <p v-if="form.errors.published_at" class="error">{{ form.errors.published_at }}</p>
                            <p v-else class="help">Kosongkan untuk terbit sekarang. Waktu di masa depan berarti tayang otomatis nanti.</p>
                        </div>

                        <label class="check" style="margin-bottom:20px">
                            <input v-model="form.is_published" type="checkbox" />
                            <span>Terbitkan ke halaman publik</span>
                        </label>

                        <button type="submit" class="btn btn--block" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan…' : (editing ? 'Simpan Perubahan' : 'Simpan Berita') }}
                        </button>

                        <button
                            v-if="editing" type="button" class="btn btn--ghost btn--block"
                            style="margin-top:10px" @click="startCreate"
                        >Batal, tulis baru saja</button>
                    </form>
                </div>
            </aside>
        </div>
    </PanelLayout>
</template>

<style scoped>
.list { display: flex; flex-direction: column; gap: 16px; }
.item { display: flex; gap: 16px; padding: 16px; border: 1px solid var(--edge); border-radius: 11px; background: var(--surface-sunk); }
.item.is-draft { border-style: dashed; opacity: .82; }

.item-media {
    flex: none; width: 120px; aspect-ratio: 16/10;
    border-radius: 8px; overflow: hidden; background: var(--edge);
    display: grid; place-items: center; font-size: 1.6rem;
}
.item-media img { width: 100%; height: 100%; object-fit: cover; }

.item-isi { min-width: 0; flex: 1; }
.item-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.waktu { margin-left: auto; font-size: .66rem; color: var(--txt-dim); }
.item h3 { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.25rem; text-transform: uppercase; margin-bottom: 5px; }
.ringkas { font-size: .84rem; color: var(--txt-soft); line-height: 1.55; }
.item-stat { font-size: .66rem; color: var(--txt-dim); margin-top: 8px; }
.item-aksi { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }

.sampul-pv { margin-top: 12px; border: 1px solid var(--edge-strong); border-radius: 9px; overflow: hidden; }
.sampul-pv img { display: block; width: 100%; aspect-ratio: 16/9; object-fit: cover; }

@media (max-width: 640px) {
  .item { flex-direction: column; }
  .item-media { width: 100%; }
}
</style>
