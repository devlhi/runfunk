<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import Paginasi from '../../Components/Paginasi.vue';

const props = defineProps({
    peserta: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const cari = ref(props.filters.cari ?? '');
const kategori = ref(props.filters.kategori ?? '');
const saring = ref(props.filters.saring ?? 'semua');

let debounce = null;

function muat() {
    router.get('/panitia/hasil',
        { cari: cari.value, kategori: kategori.value, saring: saring.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

watch(cari, () => { clearTimeout(debounce); debounce = setTimeout(muat, 300); });
watch([kategori, saring], muat);

/** Satu form per baris, supaya kesalahan di satu peserta tidak mengunci yang lain. */
const forms = ref({});
const galat = ref({});

function nilai(row) {
    return forms.value[row.id] ?? row.waktu ?? '';
}

function simpan(row) {
    const waktu = forms.value[row.id] ?? row.waktu ?? '';

    router.post(`/panitia/hasil/${row.id}`, { waktu }, {
        preserveScroll: true,
        preserveState: true,
        onError: (e) => { galat.value = { ...galat.value, [row.id]: e.waktu }; },
        onSuccess: () => {
            const sisa = { ...galat.value };
            delete sisa[row.id];
            galat.value = sisa;
        },
    });
}

function persen() {
    if (!props.stats.lunas) return 0;

    return Math.round((props.stats.sudah / props.stats.lunas) * 100);
}
</script>

<template>
    <Head title="Hasil Lomba" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Input Hasil Lomba"
        lede="Masukkan waktu finis tiap peserta. Peringkat dan e-sertifikat dihitung otomatis setelah waktunya tersimpan."
    >
        <template #actions>
            <a class="btn btn--ghost btn--sm" href="/hasil" target="_blank" rel="noopener">Lihat Papan Hasil</a>
        </template>

        <div class="grid grid-3" style="margin-bottom:20px">
            <div class="stat-tile stat-tile--ink">
                <div class="k">Peserta Lunas</div>
                <div class="v">{{ stats.lunas }}</div>
                <div class="foot">Berhak dicatat waktunya</div>
            </div>
            <div class="stat-tile stat-tile--cobalt">
                <div class="k">Sudah Ada Waktu</div>
                <div class="v">{{ stats.sudah }}</div>
                <div class="foot">{{ persen() }}% dari peserta lunas</div>
            </div>
            <div class="stat-tile" :class="stats.lunas - stats.sudah ? 'stat-tile--flame' : ''">
                <div class="k">Belum Dicatat</div>
                <div class="v">{{ stats.lunas - stats.sudah }}</div>
                <div class="foot">{{ stats.lunas - stats.sudah ? 'Perlu diinput' : 'Semua sudah lengkap' }}</div>
            </div>
        </div>

        <div class="panel panel--pop">
            <div class="cari-row">
                <div class="field" style="margin:0;flex:2;min-width:230px">
                    <label for="cari">Cari Peserta</label>
                    <input id="cari" v-model="cari" type="search" class="input input--besar"
                        placeholder="Ketik nomor BIB atau nama…" autofocus />
                </div>
                <div class="field" style="margin:0;flex:1;min-width:150px">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" v-model="kategori" class="select">
                        <option value="">Semua</option>
                        <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.distance_label }}</option>
                    </select>
                </div>
                <div class="field" style="margin:0;flex:1;min-width:150px">
                    <label for="saring">Tampilkan</label>
                    <select id="saring" v-model="saring" class="select">
                        <option value="semua">Semua peserta</option>
                        <option value="belum">Belum ada waktu</option>
                        <option value="sudah">Sudah ada waktu</option>
                    </select>
                </div>
            </div>

            <p class="help" style="margin-bottom:16px">
                Format waktu: <b>jam:menit:detik</b> (contoh <span class="mono">01:02:03</span>)
                atau <b>menit:detik</b> (contoh <span class="mono">90:00</span> untuk 90 menit).
                Kosongkan lalu simpan untuk menghapus catatan yang keliru.
            </p>

            <div v-if="peserta.data.length" class="daftar">
                <article v-for="row in peserta.data" :key="row.id" class="baris">
                    <div class="bib-box" :class="row.kategori === '10K' ? 'is-ten' : ''">
                        <span class="bib-num">{{ row.bib }}</span>
                        <span class="bib-cat">{{ row.kategori }}</span>
                    </div>

                    <div class="info">
                        <b>{{ row.nama }}</b>
                        <span>{{ row.gender === 'P' ? 'Putri' : 'Putra' }}</span>
                    </div>

                    <div class="waktu-box">
                        <input
                            :value="nilai(row)"
                            type="text" class="input waktu-input"
                            :class="{ 'has-error': galat[row.id] }"
                            placeholder="00:00:00" inputmode="numeric"
                            @input="forms[row.id] = $event.target.value"
                            @keyup.enter="simpan(row)"
                        />
                        <button type="button" class="btn btn--sm" @click="simpan(row)">Simpan</button>
                    </div>

                    <span v-if="row.peringkat" class="peringkat mono">#{{ row.peringkat }}</span>
                    <span v-else class="peringkat mono is-off">—</span>

                    <!-- Sertifikat baru terbit setelah waktu finisnya tersimpan,
                         jadi tautannya ikut muncul belakangan. Dibuka di tab baru
                         supaya daftar yang sedang diisi tidak ikut berpindah. -->
                    <a
                        v-if="row.waktu"
                        :href="`/sertifikat/${row.id}`"
                        target="_blank" rel="noopener"
                        class="lihat-sertifikat"
                        :title="`Lihat e-sertifikat ${row.nama}`"
                    >🏅 Sertifikat</a>
                    <span v-else class="lihat-sertifikat is-off" title="Isi waktu finis dulu">🏅 Sertifikat</span>

                    <p v-if="galat[row.id]" class="error baris-galat">{{ galat[row.id] }}</p>
                </article>

                <p class="help batas">Ketik nomor BIB di kotak pencarian untuk langsung menemukan satu peserta.</p>
            </div>

            <div v-else class="empty">
                <div class="big">Tidak ada yang cocok</div>
                <p>Nomor BIB terbit setelah panitia menyetujui pembayaran peserta.</p>
            </div>
        </div>

        <Paginasi :data="peserta" label="peserta" />
    </PanelLayout>
</template>

<style scoped>
.cari-row { display: flex; gap: 16px; flex-wrap: wrap; padding-bottom: 18px; margin-bottom: 16px; border-bottom: 1px solid var(--edge); }
.input--besar { font-size: 1.05rem; padding: .8rem 1rem; }

.daftar { display: flex; flex-direction: column; }
.baris { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--edge); flex-wrap: wrap; }
.baris:last-of-type { border-bottom: none; }

.bib-box { flex: none; width: 70px; display: flex; flex-direction: column; align-items: center; padding: .4rem; border-radius: 9px; background: #E6F6EF; color: #07634A; }
.bib-box.is-ten { background: #E4E8FF; color: #2438A8; }
.bib-num { font-family: 'Space Mono'; font-weight: 700; font-size: 1.05rem; line-height: 1; }
.bib-cat { font-family: 'Space Mono'; font-size: .55rem; letter-spacing: .1em; margin-top: 3px; }

.info { flex: 1; min-width: 130px; display: flex; flex-direction: column; }
.info b { font-size: .94rem; }
.info span { font-size: .74rem; color: var(--txt-soft); }

.waktu-box { display: flex; gap: 8px; align-items: center; }
.waktu-input { width: 130px; font-family: 'Space Mono', monospace; text-align: center; }

.peringkat { flex: none; width: 46px; text-align: right; font-weight: 700; font-size: .9rem; color: var(--flame); }
.peringkat.is-off { color: var(--txt-dim); font-weight: 400; }

.lihat-sertifikat {
    flex: none;
    font-family: 'Space Mono', monospace;
    font-size: .66rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    white-space: nowrap;
    padding: .38rem .7rem;
    border: 1.5px solid var(--edge);
    border-radius: 50px;
    color: var(--txt-soft);
    transition: .14s;
}
a.lihat-sertifikat:hover { border-color: var(--flame); color: var(--flame); }
/* Belum ada waktu finis: tampil redup dan tidak bisa diklik. */
.lihat-sertifikat.is-off { opacity: .38; cursor: not-allowed; }

.baris-galat { flex-basis: 100%; margin: 0; }
.batas { padding-top: 14px; }

@media (max-width: 640px) {
    .waktu-box { width: 100%; }
    .waktu-input { flex: 1; width: auto; }
    .peringkat { text-align: left; }
}
</style>
