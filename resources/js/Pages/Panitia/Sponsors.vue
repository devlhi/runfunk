<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import Paginasi from '../../Components/Paginasi.vue';

const props = defineProps({
    sponsors: { type: Object, required: true },
    aktifCount: { type: Number, default: 0 },
    tiers: { type: Array, default: () => [] },
});

const editing = ref(null);
const confirmDelete = ref(null);

const blank = {
    name: '',
    tier: 'pendukung',
    website_url: '',
    note: '',
    is_active: true,
    sort_order: 0,
};

const form = useForm({ ...blank });

function startCreate() {
    editing.value = null;
    form.clearErrors();
    form.defaults({ ...blank });
    form.reset();
}

function startEdit(sponsor) {
    editing.value = sponsor.id;
    form.clearErrors();
    form.name = sponsor.name;
    form.tier = sponsor.tier;
    form.website_url = sponsor.website_url ?? '';
    form.note = sponsor.note ?? '';
    form.is_active = sponsor.is_active;
    form.sort_order = sponsor.sort_order;
}

function submit() {
    const done = {
        preserveScroll: true,
        onSuccess: () => startCreate(),
    };

    if (editing.value) {
        form.transform((d) => ({ ...d, website_url: d.website_url || null, note: d.note || null }))
            .patch(`/panitia/sponsor/${editing.value}`, done);
    } else {
        form.transform((d) => ({ ...d, website_url: d.website_url || null, note: d.note || null }))
            .post('/panitia/sponsor', done);
    }
}

function destroy(id) {
    router.delete(`/panitia/sponsor/${id}`, {
        preserveScroll: true,
        onSuccess: () => { confirmDelete.value = null; },
    });
}

function tierClass(tier) {
    return {
        utama: 'badge badge--confirmed',
        pendukung: 'badge badge--waiting',
        media: 'badge badge--pending',
    }[tier] ?? 'badge';
}
</script>

<template>
    <Head title="Data Sponsor" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Data Sponsor"
        lede="Kelola sponsor dan media partner. Yang berstatus aktif langsung tampil di bagian “Didukung Oleh” pada landing page."
    >
        <div class="grid grid-side">
            <div class="panel panel--pop">
                <h2 class="panel-title">Daftar Sponsor</h2>
                <p class="panel-sub">
                    {{ sponsors.total }} sponsor terdaftar ·
                    {{ aktifCount }} aktif tampil di situs.
                </p>

                <div v-if="sponsors.data.length" class="table-scroll">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Tingkat</th>
                                <th>Urutan</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in sponsors.data" :key="s.id">
                                <td>
                                    <span class="strong">{{ s.name }}</span>
                                    <a
                                        v-if="s.website_url" class="spon-url" :href="s.website_url"
                                        target="_blank" rel="noopener noreferrer"
                                    >{{ s.website_url }}</a>
                                    <span v-if="s.note" class="spon-note">{{ s.note }}</span>
                                </td>
                                <td><span :class="tierClass(s.tier)">{{ s.tier_label }}</span></td>
                                <td class="mono">{{ s.sort_order }}</td>
                                <td>
                                    <span class="badge" :class="s.is_active ? 'badge--confirmed' : 'badge--cancelled'">
                                        {{ s.is_active ? 'Tampil' : 'Disembunyikan' }}
                                    </span>
                                </td>
                                <td class="row-actions">
                                    <button type="button" class="btn btn--ghost btn--sm" @click="startEdit(s)">Ubah</button>
                                    <button
                                        v-if="confirmDelete !== s.id" type="button"
                                        class="btn btn--ghost btn--sm" @click="confirmDelete = s.id"
                                    >Hapus</button>
                                    <template v-else>
                                        <button type="button" class="btn btn--danger btn--sm" @click="destroy(s.id)">Yakin hapus</button>
                                        <button type="button" class="btn btn--ghost btn--sm" @click="confirmDelete = null">Batal</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="empty">
                    <div class="big">Belum ada sponsor</div>
                    <p>Tambahkan lewat formulir di samping. Bagian sponsor di landing page akan menyesuaikan sendiri.</p>
                </div>

                <Paginasi :data="sponsors" label="sponsor" />
            </div>

            <aside>
                <div class="panel">
                    <h2 class="panel-title">{{ editing ? 'Ubah Sponsor' : 'Tambah Sponsor' }}</h2>
                    <p class="panel-sub">
                        {{ editing ? 'Menyimpan akan langsung memperbarui tampilan di landing page.' : 'Sponsor baru muncul di landing page begitu disimpan dalam keadaan aktif.' }}
                    </p>

                    <form @submit.prevent="submit">
                        <div class="field">
                            <label for="name">Nama <span class="req">*</span></label>
                            <input
                                id="name" v-model="form.name" type="text" class="input"
                                :class="{ 'has-error': form.errors.name }"
                                placeholder="Contoh: Pemdes Tuladenggi" required
                            />
                            <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                        </div>

                        <div class="field">
                            <label for="tier">Tingkat <span class="req">*</span></label>
                            <select id="tier" v-model="form.tier" class="select">
                                <option v-for="t in tiers" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <p class="help">Sponsor utama tampil paling depan dan paling besar.</p>
                        </div>

                        <div class="field">
                            <label for="website_url">Alamat Situs</label>
                            <input
                                id="website_url" v-model="form.website_url" type="url" class="input"
                                :class="{ 'has-error': form.errors.website_url }"
                                placeholder="https://contoh.id"
                            />
                            <p v-if="form.errors.website_url" class="error">{{ form.errors.website_url }}</p>
                            <p v-else class="help">Opsional. Nama sponsor jadi tautan kalau diisi.</p>
                        </div>

                        <div class="field">
                            <label for="note">Catatan Internal</label>
                            <input
                                id="note" v-model="form.note" type="text" class="input"
                                placeholder="Contoh: kontribusi 20 kaos"
                            />
                            <p class="help">Hanya terlihat panitia, tidak tampil di situs.</p>
                        </div>

                        <div class="field">
                            <label for="sort_order">Urutan</label>
                            <input id="sort_order" v-model.number="form.sort_order" type="number" min="0" max="999" class="input" />
                            <p class="help">Angka kecil tampil lebih dulu di tingkat yang sama.</p>
                        </div>

                        <label class="check" style="margin-bottom:20px">
                            <input v-model="form.is_active" type="checkbox" />
                            <span>Tampilkan di landing page</span>
                        </label>

                        <button type="submit" class="btn btn--block" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan…' : (editing ? 'Simpan Perubahan' : 'Tambah Sponsor') }}
                        </button>

                        <button
                            v-if="editing" type="button" class="btn btn--ghost btn--block"
                            style="margin-top:10px" @click="startCreate"
                        >
                            Batal, tambah baru saja
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </PanelLayout>
</template>

<style scoped>
.spon-url, .spon-note { display: block; font-size: .78rem; color: var(--txt-soft); }
.spon-url { color: var(--cobalt); word-break: break-all; }
.row-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
</style>
