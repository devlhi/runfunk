<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import Paginasi from '../../Components/Paginasi.vue';

defineProps({
    announcements: { type: Object, required: true },
    levels: { type: Array, default: () => [] },
    waAktif: { type: Boolean, default: false },
    jumlahPenerima: { type: Number, default: 0 },
});

/* --- Kirim ke peserta --- */
const kirimUntuk = ref(null);
const ujiForm = useForm({ nomor: '' });
const kirimForm = useForm({ email: true, whatsapp: false });

function bukaKirim(a) {
    kirimUntuk.value = kirimUntuk.value === a.id ? null : a.id;
    ujiForm.clearErrors();
    kirimForm.clearErrors();
}

function ujiKirim(id) {
    ujiForm.post(`/panitia/pengumuman/${id}/uji`, { preserveScroll: true });
}

function broadcast(id) {
    kirimForm.post(`/panitia/pengumuman/${id}/kirim`, {
        preserveScroll: true,
        onSuccess: () => { kirimUntuk.value = null; },
    });
}

const editing = ref(null);
const confirmDelete = ref(null);

const blank = { title: '', body: '', level: 'info', is_published: true };
const form = useForm({ ...blank });

function startCreate() {
    editing.value = null;
    form.clearErrors();
    form.defaults({ ...blank });
    form.reset();
}

function startEdit(a) {
    editing.value = a.id;
    form.clearErrors();
    form.title = a.title;
    form.body = a.body;
    form.level = a.level;
    form.is_published = a.is_published;
}

function submit() {
    const opsi = { preserveScroll: true, onSuccess: () => startCreate() };

    if (editing.value) {
        form.patch(`/panitia/pengumuman/${editing.value}`, opsi);
    } else {
        form.post('/panitia/pengumuman', opsi);
    }
}

function destroy(id) {
    router.delete(`/panitia/pengumuman/${id}`, {
        preserveScroll: true,
        onSuccess: () => { confirmDelete.value = null; },
    });
}
</script>

<template>
    <Head title="Pengumuman" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Pengumuman"
        lede="Tulis pengumuman yang langsung muncul di dashboard semua peserta — tanpa perlu email atau WhatsApp."
    >
        <div class="grid grid-side">
            <div class="panel panel--pop">
                <h2 class="panel-title">Daftar Pengumuman</h2>
                <p class="panel-sub">Yang terbaru tampil paling atas di dashboard peserta.</p>

                <div v-if="announcements.data.length" class="list">
                    <article v-for="a in announcements.data" :key="a.id" class="item" :class="{ 'is-draft': !a.is_published }">
                        <div class="item-head">
                            <span class="badge" :class="a.level === 'penting' ? 'badge--rejected' : 'badge--waiting'">
                                {{ a.level_label }}
                            </span>
                            <span v-if="!a.is_published" class="badge badge--cancelled">Draf</span>
                            <span class="waktu mono">{{ a.created_at }} · {{ a.author }}</span>
                        </div>

                        <h3>{{ a.title }}</h3>
                        <p class="isi">{{ a.body }}</p>

                        <div class="item-aksi">
                            <button type="button" class="btn btn--ghost btn--sm" @click="startEdit(a)">Ubah</button>
                            <button
                                v-if="a.is_published" type="button" class="btn btn--sm"
                                @click="bukaKirim(a)"
                            >
                                {{ kirimUntuk === a.id ? 'Tutup' : '✉ Kirim ke Peserta' }}
                            </button>
                            <button
                                v-if="confirmDelete !== a.id" type="button"
                                class="btn btn--ghost btn--sm" @click="confirmDelete = a.id"
                            >Hapus</button>
                            <template v-else>
                                <button type="button" class="btn btn--danger btn--sm" @click="destroy(a.id)">Yakin hapus</button>
                                <button type="button" class="btn btn--ghost btn--sm" @click="confirmDelete = null">Batal</button>
                            </template>
                        </div>

                        <!-- Panel kirim: uji dulu, baru sebar. -->
                        <div v-if="kirimUntuk === a.id" class="kirim">
                            <p v-if="a.broadcast_at" class="kirim-peringatan">
                                ⚠ Pengumuman ini <b>sudah pernah dikirim</b> pada {{ a.broadcast_at }}.
                                Mengirim lagi berarti peserta menerima pesan yang sama dua kali.
                            </p>

                            <div class="kirim-uji">
                                <div class="field" style="margin:0;flex:1;min-width:190px">
                                    <label :for="`uji-${a.id}`">Uji kirim dulu ke satu nomor</label>
                                    <input
                                        :id="`uji-${a.id}`" v-model="ujiForm.nomor" type="tel" class="input"
                                        placeholder="08123456789" :disabled="!waAktif"
                                    />
                                    <p v-if="ujiForm.errors.nomor" class="error">{{ ujiForm.errors.nomor }}</p>
                                </div>
                                <button
                                    type="button" class="btn btn--ghost btn--sm"
                                    :disabled="!waAktif || ujiForm.processing || !ujiForm.nomor"
                                    @click="ujiKirim(a.id)"
                                >
                                    {{ ujiForm.processing ? 'Mengirim…' : 'Uji Kirim' }}
                                </button>
                            </div>

                            <p v-if="!waAktif" class="help kirim-mati">
                                Gateway WhatsApp belum aktif. Developer bisa mengaturnya di
                                <b>Pengaturan Acara</b>. Tanpa itu, pengiriman hanya lewat email.
                            </p>

                            <div class="kirim-saluran">
                                <label class="check">
                                    <input v-model="kirimForm.email" type="checkbox" />
                                    <span>Kirim lewat <b>email</b></span>
                                </label>
                                <label class="check" :class="{ 'is-off': !waAktif }">
                                    <input v-model="kirimForm.whatsapp" type="checkbox" :disabled="!waAktif" />
                                    <span>Kirim lewat <b>WhatsApp</b></span>
                                </label>
                            </div>

                            <div class="kirim-aksi">
                                <span class="kirim-info">
                                    Akan dikirim ke <b>{{ jumlahPenerima }} peserta</b>.
                                    Pesan yang sudah terkirim tidak bisa ditarik kembali.
                                </span>
                                <button
                                    type="button" class="btn btn--danger btn--sm"
                                    :disabled="kirimForm.processing || (!kirimForm.email && !kirimForm.whatsapp)"
                                    @click="broadcast(a.id)"
                                >
                                    {{ kirimForm.processing ? 'Memulai…' : `Kirim ke ${jumlahPenerima} Peserta` }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="empty">
                    <div class="big">Belum ada pengumuman</div>
                    <p>Contoh yang berguna: jadwal pengambilan race pack, perubahan rute, atau imbauan datang lebih awal.</p>
                </div>

                <Paginasi :data="announcements" label="pengumuman" />
            </div>

            <aside>
                <div class="panel">
                    <h2 class="panel-title">{{ editing ? 'Ubah Pengumuman' : 'Tulis Pengumuman' }}</h2>
                    <p class="panel-sub">
                        {{ editing ? 'Perubahan langsung terlihat peserta.' : 'Begitu disimpan dan berstatus tayang, semua peserta melihatnya.' }}
                    </p>

                    <form @submit.prevent="submit">
                        <div class="field">
                            <label for="title">Judul <span class="req">*</span></label>
                            <input
                                id="title" v-model="form.title" type="text" class="input"
                                :class="{ 'has-error': form.errors.title }"
                                placeholder="Contoh: Pengambilan race pack H-2" required
                            />
                            <p v-if="form.errors.title" class="error">{{ form.errors.title }}</p>
                        </div>

                        <div class="field">
                            <label for="body">Isi <span class="req">*</span></label>
                            <textarea
                                id="body" v-model="form.body" class="textarea" rows="6"
                                :class="{ 'has-error': form.errors.body }"
                                placeholder="Tulis lengkap dengan tanggal, jam, dan lokasi." required
                            ></textarea>
                            <p v-if="form.errors.body" class="error">{{ form.errors.body }}</p>
                        </div>

                        <div class="field">
                            <label for="level">Tingkat</label>
                            <select id="level" v-model="form.level" class="select">
                                <option v-for="l in levels" :key="l.value" :value="l.value">{{ l.label }}</option>
                            </select>
                            <p class="help">“Penting” ditandai merah supaya tidak terlewat.</p>
                        </div>

                        <label class="check" style="margin-bottom:20px">
                            <input v-model="form.is_published" type="checkbox" />
                            <span>Tayangkan ke peserta</span>
                        </label>

                        <button type="submit" class="btn btn--block" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan…' : (editing ? 'Simpan Perubahan' : 'Terbitkan') }}
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
.list { display: flex; flex-direction: column; gap: 14px; }
.item { border: 1px solid var(--edge); border-radius: 11px; padding: 16px 18px; background: var(--surface-sunk); }
.item.is-draft { opacity: .7; border-style: dashed; }
.item-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.waktu { margin-left: auto; font-size: .68rem; color: var(--txt-dim); }
.item h3 { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.3rem; text-transform: uppercase; margin-bottom: 6px; }
.isi { font-size: .88rem; color: var(--txt-soft); white-space: pre-line; line-height: 1.6; }
.item-aksi { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }

/* Panel kirim sengaja dibingkai mencolok — ini aksi yang tidak bisa dibatalkan. */
.kirim {
    margin-top: 14px;
    padding: 16px;
    border: 1.5px solid var(--edge-strong);
    border-radius: 11px;
    background: var(--surface);
}

.kirim-peringatan {
    font-size: .82rem;
    color: #A32209;
    background: #FFE7DF;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 14px;
}

.kirim-uji { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.kirim-mati { margin-top: 10px; }
.kirim-saluran { display: flex; gap: 22px; flex-wrap: wrap; margin: 16px 0 14px; padding-top: 14px; border-top: 1px solid var(--edge); }
.kirim-saluran .check.is-off { opacity: .5; }

.kirim-aksi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.kirim-info { font-size: .8rem; color: var(--txt-soft); max-width: 42ch; }
.kirim-info b { color: var(--txt); }
</style>
