<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import KartuPanitia from '../../Components/KartuPanitia.vue';

const props = defineProps({
    pengelola: { type: Array, default: () => [] },
    acara: { type: Object, required: true },
});

const dipilih = ref(new Set(props.pengelola.map((p) => p.id)));
const pratinjauId = ref(props.pengelola[0]?.id ?? null);
const suntingId = ref(null);

const jabatanForm = useForm({ card_title: '' });

const pratinjau = computed(
    () => props.pengelola.find((p) => p.id === pratinjauId.value) ?? props.pengelola[0] ?? null
);

const semuaDipilih = computed(
    () => props.pengelola.length > 0 && dipilih.value.size === props.pengelola.length
);

function pilih(id) {
    const next = new Set(dipilih.value);
    next.has(id) ? next.delete(id) : next.add(id);
    dipilih.value = next;
}

function pilihSemua() {
    dipilih.value = semuaDipilih.value ? new Set() : new Set(props.pengelola.map((p) => p.id));
}

function cetak() {
    if (!dipilih.value.size) return;

    window.open(`/panitia/kartu-panitia/lembar?ids=${[...dipilih.value].join(',')}`, '_blank', 'noopener');
}

function mulaiSunting(orang) {
    suntingId.value = orang.id;
    jabatanForm.clearErrors();
    jabatanForm.card_title = orang.jabatan ?? '';
}

function simpanJabatan() {
    jabatanForm.patch(`/panitia/kartu-panitia/${suntingId.value}/jabatan`, {
        preserveScroll: true,
        onSuccess: () => { suntingId.value = null; },
    });
}

/* ------------------------------------------------------------ Pas foto */

const fotoUntuk = ref(null);
const fotoForm = useForm({ foto: null });

function pilihFoto(orang, e) {
    const berkas = e.target.files?.[0];
    if (!berkas) return;

    fotoUntuk.value = orang.id;
    fotoForm.foto = berkas;

    fotoForm.post(`/panitia/kartu-panitia/${orang.id}/foto`, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            fotoUntuk.value = null;
            fotoForm.reset();
            e.target.value = ''; // supaya berkas yang sama bisa dipilih lagi
        },
    });
}

function hapusFoto(orang) {
    if (!window.confirm(`Hapus pas foto ${orang.nama}? Kartunya kembali memakai bingkai tempel.`)) return;

    router.delete(`/panitia/kartu-panitia/${orang.id}/foto`, { preserveScroll: true });
}

function terbitkanUlang(orang) {
    if (!window.confirm(
        `Terbitkan ulang kartu ${orang.nama}?\n\n`
        + 'Kartu lamanya akan langsung DITOLAK saat dipindai. Pakai ini kalau kartunya hilang.'
    )) return;

    router.post(`/panitia/kartu-panitia/${orang.id}/terbitkan-ulang`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Kartu Panitia" />

    <PanelLayout
        crumb="Khusus Developer"
        title="Kartu Panitia"
        lede="Cetak kartu tanda panitia berkode QR. Petugas di lapangan bisa memindainya untuk memastikan yang mengaku panitia memang terdaftar."
    >
        <template #actions>
            <Link class="btn btn--ghost btn--sm" href="/panitia/kartu-panitia/validasi">Pindai Kartu</Link>
            <button type="button" class="btn btn--sm" :disabled="!dipilih.size" @click="cetak">
                🖨 Cetak {{ dipilih.size }} Kartu
            </button>
        </template>

        <div class="grid grid-side">
            <div class="panel panel--pop">
                <div class="panel-head-row">
                    <div>
                        <h2 class="panel-title">Daftar Panitia</h2>
                        <p class="panel-sub">Klik baris untuk melihat pratinjau kartunya di samping.</p>
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm" @click="pilihSemua">
                        {{ semuaDipilih ? 'Kosongkan pilihan' : 'Pilih semua' }}
                    </button>
                </div>

                <div class="table-scroll">
                    <table class="data">
                        <thead>
                            <tr>
                                <th style="width:44px"></th>
                                <th style="width:52px">Foto</th>
                                <th>Nama</th>
                                <th>Jabatan di Kartu</th>
                                <th>No. Panitia</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in pengelola" :key="p.id"
                                :class="{ 'is-picked': dipilih.has(p.id), 'is-preview': pratinjauId === p.id }"
                                @click="pratinjauId = p.id"
                            >
                                <td>
                                    <input
                                        type="checkbox" :checked="dipilih.has(p.id)"
                                        :aria-label="`Pilih kartu ${p.nama}`"
                                        @click.stop="pilih(p.id)"
                                    />
                                </td>
                                <td>
                                    <img v-if="p.foto" class="foto-kecil" :src="p.foto" :alt="`Pas foto ${p.nama}`" />
                                    <span v-else class="foto-kosong" title="Belum ada pas foto">3×4</span>
                                </td>
                                <td>
                                    <span class="strong">{{ p.nama }}</span>
                                    <span class="sub">{{ p.peran }}</span>
                                </td>
                                <td>
                                    <span v-if="p.jabatan">{{ p.jabatan }}</span>
                                    <span v-else class="muted">Belum diisi</span>
                                </td>
                                <td class="mono">
                                    {{ p.nomor }}
                                    <span v-if="p.versi > 1" class="badge badge--pending" style="margin-left:6px">
                                        cetak ke-{{ p.versi }}
                                    </span>
                                </td>
                                <td class="row-actions">
                                    <label class="btn btn--ghost btn--sm foto-tombol" @click.stop>
                                        {{ fotoUntuk === p.id ? 'Mengunggah…' : (p.foto ? 'Ganti Foto' : '📷 Pas Foto') }}
                                        <input
                                            type="file" accept="image/jpeg,image/png,image/webp"
                                            @change="pilihFoto(p, $event)"
                                        />
                                    </label>
                                    <button
                                        v-if="p.foto" type="button" class="btn btn--ghost btn--sm"
                                        @click.stop="hapusFoto(p)"
                                    >Hapus Foto</button>
                                    <button type="button" class="btn btn--ghost btn--sm" @click.stop="mulaiSunting(p)">
                                        Jabatan
                                    </button>
                                    <button type="button" class="btn btn--ghost btn--sm" @click.stop="terbitkanUlang(p)">
                                        Terbitkan Ulang
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="fotoForm.errors.foto" class="error" style="margin-top:12px">{{ fotoForm.errors.foto }}</p>

                <p class="help" style="margin-top:14px">
                    <b>Pas foto</b> memakai perbandingan 3×4. Yang belum diunggah tetap bisa dicetak —
                    kartunya keluar dengan bingkai kosong untuk ditempel foto manual.
                </p>
                <p class="help" style="margin-top:6px">
                    <b>Kartu hilang?</b> Tekan “Terbitkan Ulang”. Kartu lamanya langsung ditolak
                    saat dipindai, tanpa perlu menghapus akunnya.
                </p>
            </div>

            <aside>
                <div class="panel">
                    <h2 class="panel-title">Pratinjau Kartu</h2>
                    <p class="panel-sub">Ukuran kartu identitas standar, muat di lanyard.</p>

                    <div v-if="pratinjau" class="pv">
                        <KartuPanitia :kartu="{ ...pratinjau, qr: null }" :acara="acara" pratinjau />
                        <p class="help" style="margin-top:14px">
                            Kode QR-nya muncul di lembar cetak. Tiap kartu punya kode berbeda
                            yang hanya bisa dibuat panel ini.
                        </p>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Modal jabatan -->
        <Teleport to="body">
            <div v-if="suntingId" class="modal-veil" @click.self="suntingId = null">
                <div class="modal" role="dialog" aria-modal="true">
                    <h3>Jabatan di Kartu</h3>
                    <p class="modal-sub">
                        Keterangan tugas di lapangan, bukan peran akun. Contoh: “Koordinator Race Pack”,
                        “Tim Medis”, “Marshal Rute”.
                    </p>

                    <form @submit.prevent="simpanJabatan">
                        <div class="field">
                            <label for="card_title">Jabatan</label>
                            <input
                                id="card_title" v-model="jabatanForm.card_title" type="text" class="input"
                                :class="{ 'has-error': jabatanForm.errors.card_title }"
                                placeholder="Koordinator Race Pack" maxlength="60"
                            />
                            <p v-if="jabatanForm.errors.card_title" class="error">{{ jabatanForm.errors.card_title }}</p>
                            <p v-else class="help">Kosongkan untuk memakai peran akunnya saja.</p>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn--ghost btn--sm" @click="suntingId = null">Batal</button>
                            <button type="submit" class="btn btn--sm" :disabled="jabatanForm.processing">
                                {{ jabatanForm.processing ? 'Menyimpan…' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PanelLayout>
</template>

<style scoped>
.data tbody tr { cursor: pointer; }
.data tbody tr.is-picked { background: #F2FAF7; }
.data tbody tr.is-preview { box-shadow: inset 3px 0 0 var(--flame); }
.sub { display: block; font-size: .76rem; color: var(--txt-soft); }
.muted { color: var(--txt-dim); }

/* Pratinjau pas foto: 3:4, sama dengan bingkai di kartunya. */
.foto-kecil {
    display: block; width: 30px; aspect-ratio: 3 / 4;
    object-fit: cover; border-radius: 4px; border: 1px solid var(--edge-strong);
}
.foto-kosong {
    display: grid; place-items: center;
    width: 30px; aspect-ratio: 3 / 4;
    border: 1px dashed var(--edge-strong); border-radius: 4px;
    font-family: 'Space Mono', monospace; font-size: .58rem; color: var(--txt-dim);
}

/* <label> dipakai sebagai tombol supaya pemilih berkas bawaan peramban tetap
   dipakai — input aslinya disembunyikan, bukan dihilangkan, agar tetap bisa
   dicapai lewat papan ketik. */
.foto-tombol { position: relative; overflow: hidden; cursor: pointer; }
.foto-tombol input {
    position: absolute; inset: 0; opacity: 0; cursor: pointer;
    font-size: 0; width: 100%; height: 100%;
}

.pv { display: flex; flex-direction: column; align-items: center; }
</style>
