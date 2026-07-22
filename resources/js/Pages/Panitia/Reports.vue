<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import { rupiah } from '../../lib/format';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    cakupanOptions: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    jersey: { type: Array, default: () => [] },
    gender: { type: Array, default: () => [] },
    usia: { type: Array, default: () => [] },
    kota: { type: Array, default: () => [] },
    komunitas: { type: Array, default: () => [] },
    golDarah: { type: Array, default: () => [] },
    pemasukan: { type: Array, default: () => [] },
});

const cakupan = ref(props.filters.cakupan ?? 'confirmed');
const kategori = ref(props.filters.kategori ?? '');

watch([cakupan, kategori], () => {
    router.get('/panitia/laporan',
        { cakupan: cakupan.value, kategori: kategori.value || undefined },
        { preserveState: true, replace: true });
});

/** Bar proporsional terhadap nilai terbesar di daftar yang sama. */
function lebar(jumlah, daftar) {
    const puncak = Math.max(1, ...daftar.map((d) => d.jumlah));

    return Math.max(2, Math.round((jumlah / puncak) * 100));
}

function persen(jumlah) {
    if (!props.total) return '0%';

    return Math.round((jumlah / props.total) * 100) + '%';
}

const totalPemasukan = () => props.pemasukan.reduce((a, p) => a + p.total, 0);
</script>

<template>
    <Head title="Rekap & Laporan" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Rekap & Laporan"
        lede="Rangkuman data peserta untuk keperluan pemesanan jersey, penentuan kategori juara, dan laporan ke sponsor."
    >
        <template #actions>
            <a class="btn btn--ghost btn--sm" :href="`/panitia/pendaftaran/ekspor${cakupan === 'confirmed' ? '?status=confirmed' : ''}`">
                Ekspor CSV
            </a>
        </template>

        <div class="panel" style="margin-bottom:20px">
            <div class="filter-row">
                <div class="field" style="margin:0;min-width:220px">
                    <label for="cakupan">Cakupan Data</label>
                    <select id="cakupan" v-model="cakupan" class="select">
                        <option v-for="o in cakupanOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="field" style="margin:0;min-width:170px">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" v-model="kategori" class="select">
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.distance_label }}</option>
                    </select>
                </div>
                <div class="filter-total">
                    <span class="ft-num">{{ total }}</span>
                    <span class="ft-lbl">peserta masuk hitungan</span>
                </div>
            </div>
            <p class="help" style="margin-top:12px">
                Untuk memesan jersey, pakai <b>“Hanya yang lunas”</b> — peserta yang belum
                membayar bisa batal, dan jersey yang terlanjur dipesan tidak bisa dikembalikan.
            </p>
        </div>

        <!-- Jersey: yang paling sering dipakai, jadi ditaruh paling atas -->
        <div class="panel panel--pop" style="margin-bottom:20px">
            <h2 class="panel-title">Ukuran Jersey</h2>
            <p class="panel-sub">
                Dipisah per kategori karena jersey 5K dan 10K dipesan terpisah.
            </p>

            <div v-if="jersey.length" class="jersey-grid">
                <div v-for="j in jersey" :key="j.kategori" class="jersey-card">
                    <div class="jc-head">
                        <span class="jc-cat">{{ j.kategori }}</span>
                        <span class="jc-total mono">{{ j.total }} pcs</span>
                    </div>
                    <div v-for="u in j.ukuran" :key="u.label" class="size-row">
                        <span class="size-lbl">{{ u.label }}</span>
                        <div class="size-track">
                            <i :style="{ width: lebar(u.jumlah, j.ukuran) + '%' }"></i>
                        </div>
                        <span class="size-num mono">{{ u.jumlah }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="empty">
                <div class="big">Belum ada data</div>
                <p>Belum ada peserta yang masuk cakupan ini.</p>
            </div>
        </div>

        <div class="grid grid-2" style="margin-bottom:20px">
            <div class="panel">
                <h2 class="panel-title">Jenis Kelamin</h2>
                <p class="panel-sub">Untuk menyiapkan kategori juara putra & putri.</p>
                <div v-for="g in gender" :key="g.label" class="stat-row">
                    <span class="sr-lbl">{{ g.label }}</span>
                    <div class="sr-track"><i :style="{ width: lebar(g.jumlah, gender) + '%' }"></i></div>
                    <span class="sr-num mono">{{ g.jumlah }} · {{ persen(g.jumlah) }}</span>
                </div>
                <p v-if="!gender.length" class="help">Belum ada data.</p>
            </div>

            <div class="panel">
                <h2 class="panel-title">Kelompok Usia</h2>
                <p class="panel-sub">Rentang yang biasa dipakai untuk kategori juara.</p>
                <div v-for="u in usia" :key="u.label" class="stat-row">
                    <span class="sr-lbl">{{ u.label }}</span>
                    <div class="sr-track"><i class="i-cobalt" :style="{ width: lebar(u.jumlah, usia) + '%' }"></i></div>
                    <span class="sr-num mono">{{ u.jumlah }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-3" style="margin-bottom:20px">
            <div class="panel">
                <h2 class="panel-title">Asal Kota</h2>
                <p class="panel-sub">10 terbanyak.</p>
                <ol class="rank">
                    <li v-for="(k, i) in kota" :key="k.label">
                        <span class="rk-no">{{ i + 1 }}</span>
                        <span class="rk-lbl">{{ k.label }}</span>
                        <span class="rk-num mono">{{ k.jumlah }}</span>
                    </li>
                </ol>
                <p v-if="!kota.length" class="help">Belum ada data.</p>
            </div>

            <div class="panel">
                <h2 class="panel-title">Komunitas / Klub</h2>
                <p class="panel-sub">Berguna untuk undangan & sponsor.</p>
                <ol class="rank">
                    <li v-for="(k, i) in komunitas" :key="k.label">
                        <span class="rk-no">{{ i + 1 }}</span>
                        <span class="rk-lbl">{{ k.label }}</span>
                        <span class="rk-num mono">{{ k.jumlah }}</span>
                    </li>
                </ol>
                <p v-if="!komunitas.length" class="help">Belum ada yang mengisi komunitas.</p>
            </div>

            <div class="panel">
                <h2 class="panel-title">Golongan Darah</h2>
                <p class="panel-sub">Diteruskan ke tim medis sebelum hari-H.</p>
                <div v-for="d in golDarah" :key="d.label" class="stat-row">
                    <span class="sr-lbl">{{ d.label }}</span>
                    <div class="sr-track"><i class="i-danger" :style="{ width: lebar(d.jumlah, golDarah) + '%' }"></i></div>
                    <span class="sr-num mono">{{ d.jumlah }}</span>
                </div>
                <p v-if="!golDarah.length" class="help">Belum ada yang mengisi golongan darah.</p>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">Pemasukan per Kategori</h2>
            <p class="panel-sub">Dihitung dari biaya pendaftaran peserta dalam cakupan terpilih.</p>

            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr><th>Kategori</th><th>Peserta</th><th>Pemasukan</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in pemasukan" :key="p.label">
                            <td class="strong">{{ p.label }}</td>
                            <td class="mono">{{ p.jumlah }}</td>
                            <td class="mono strong">{{ rupiah(p.total) }}</td>
                        </tr>
                        <tr v-if="pemasukan.length" class="total-row">
                            <td class="strong">Total</td>
                            <td class="mono strong">{{ total }}</td>
                            <td class="mono strong">{{ rupiah(totalPemasukan()) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </PanelLayout>
</template>

<style scoped>
.filter-row { display: flex; align-items: flex-end; gap: 18px; flex-wrap: wrap; }
.filter-total { margin-left: auto; text-align: right; padding-bottom: .3rem; }
.ft-num { display: block; font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 2rem; line-height: 1; }
.ft-lbl { font-family: 'Space Mono'; font-size: .62rem; letter-spacing: .1em; text-transform: uppercase; color: var(--txt-soft); }

.jersey-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
.jersey-card { border: 1px solid var(--edge); border-radius: 11px; padding: 16px 18px; background: var(--surface-sunk); }
.jc-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px; }
.jc-cat { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.4rem; text-transform: uppercase; }
.jc-total { font-size: .78rem; font-weight: 700; color: var(--flame); }

.size-row { display: flex; align-items: center; gap: 10px; margin-bottom: 7px; }
.size-lbl { font-family: 'Space Mono'; font-size: .72rem; font-weight: 700; width: 30px; flex: none; }
.size-track { flex: 1; height: 8px; border-radius: 20px; background: var(--edge); overflow: hidden; }
.size-track i { display: block; height: 100%; border-radius: 20px; background: var(--flame); }
.size-num { font-size: .78rem; font-weight: 700; width: 34px; text-align: right; flex: none; }

.stat-row { display: flex; align-items: center; gap: 10px; margin-bottom: 9px; }
.sr-lbl { font-size: .84rem; width: 92px; flex: none; }
.sr-track { flex: 1; height: 8px; border-radius: 20px; background: var(--edge); overflow: hidden; }
.sr-track i { display: block; height: 100%; border-radius: 20px; background: var(--mint); }
.sr-track .i-cobalt { background: var(--cobalt); }
.sr-track .i-danger { background: #C81E1E; }
.sr-num { font-size: .76rem; color: var(--txt-soft); flex: none; }

.rank { list-style: none; counter-reset: r; }
.rank li { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--edge); }
.rank li:last-child { border-bottom: none; }
.rk-no {
  flex: none; width: 22px; height: 22px; display: grid; place-items: center;
  border-radius: 6px; background: var(--surface-sunk); border: 1px solid var(--edge);
  font-family: 'Space Mono'; font-size: .64rem; font-weight: 700; color: var(--txt-soft);
}
.rk-lbl { flex: 1; font-size: .86rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rk-num { font-size: .8rem; font-weight: 700; }

.total-row td { border-top: 2px solid var(--edge-strong); background: var(--surface-sunk); }
</style>
