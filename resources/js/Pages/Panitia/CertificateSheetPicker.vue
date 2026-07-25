<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    jumlah: { type: Number, default: 0 },
    belumAdaWaktu: { type: Number, default: 0 },
    contoh: { type: Array, default: () => [] },
});

const kategori = ref(props.filters.kategori ?? '');

watch(kategori, (value) => {
    router.get('/panitia/cetak-sertifikat', value ? { kategori: value } : {}, {
        preserveState: true,
        replace: true,
    });
});

/**
 * Lembar cetaknya dibuka di tab baru: dialog cetak peramban mengunci tab yang
 * memanggilnya, dan panitia biasanya masih perlu kembali ke daftar ini.
 */
function cetak() {
    const q = kategori.value ? `?kategori=${kategori.value}` : '';
    window.open(`/panitia/cetak-sertifikat/lembar${q}`, '_blank', 'noopener');
}
</script>

<template>
    <Head title="Cetak Sertifikat" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Cetak Sertifikat"
        lede="Cetak sertifikat finisher sekaligus — satu halaman A4 lanskap per peserta. Hanya yang lunas dan sudah punya catatan waktu yang muncul di sini."
    >
        <template #actions>
            <button type="button" class="btn btn--sm" :disabled="!jumlah" @click="cetak">
                🖨 Cetak {{ jumlah }} Sertifikat
            </button>
        </template>

        <div class="panel">
            <div class="panel-head-row">
                <div>
                    <h2 class="panel-title">Pilih yang mau dicetak</h2>
                    <p class="panel-sub">
                        Kosongkan kategori untuk mencetak seluruh finisher.
                    </p>
                </div>
                <select v-model="kategori" class="select" style="max-width:200px">
                    <option value="">Semua kategori</option>
                    <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.distance_label }}</option>
                </select>
            </div>

            <div class="ringkas">
                <div class="rk">
                    <span class="rk-n">{{ jumlah }}</span>
                    <span class="rk-l">Siap dicetak</span>
                </div>
                <div class="rk" :class="{ 'rk--ingat': belumAdaWaktu > 0 }">
                    <span class="rk-n">{{ belumAdaWaktu }}</span>
                    <span class="rk-l">Belum ada waktu finis</span>
                </div>
            </div>

            <!-- Diberitahu di depan supaya panitia tidak terlanjur mencetak
                 sebagian lalu harus mengulang seluruh tumpukan. -->
            <p v-if="belumAdaWaktu > 0" class="ingat">
                <b>{{ belumAdaWaktu }} peserta lunas belum punya catatan waktu</b>, jadi sertifikatnya
                belum bisa terbit. Isi dulu di halaman <b>Hasil Lomba</b> kalau mereka memang finis.
            </p>

            <div v-if="contoh.length" class="cuplik">
                <p class="cuplik-judul mono">Contoh yang akan ikut tercetak</p>
                <ul>
                    <li v-for="c in contoh" :key="c.bib">
                        <span class="cb mono">{{ c.bib }}</span>
                        <b>{{ c.nama }}</b>
                        <span class="ck mono">{{ c.kategori }} · {{ c.waktu }}</span>
                    </li>
                </ul>
                <p v-if="jumlah > contoh.length" class="help">
                    …dan {{ jumlah - contoh.length }} lainnya.
                </p>
            </div>

            <p v-else class="help kosong">
                Belum ada sertifikat yang bisa dicetak untuk pilihan ini.
            </p>

            <p class="help batas">
                Saat dialog cetak terbuka: pilih <b>A4 lanskap</b>, skala <b>100%</b>, matikan
                header/footer peramban, dan nyalakan <b>“Background graphics”</b> supaya warnanya
                ikut tercetak. Sekali cetak dibatasi 300 sertifikat.
            </p>
        </div>
    </PanelLayout>
</template>

<style scoped>
.ringkas { display: flex; gap: 14px; flex-wrap: wrap; margin: 18px 0 4px; }

.rk {
    flex: 1; min-width: 150px;
    display: flex; flex-direction: column; gap: 3px;
    padding: 14px 16px;
    border: 1.5px solid var(--edge);
    border-radius: 12px;
    background: #F0FDFA;
}
.rk--ingat { background: #FFF7ED; }
.rk-n { font-family: 'Space Mono', monospace; font-weight: 700; font-size: 1.7rem; line-height: 1; }
.rk-l { font-family: 'Space Mono', monospace; font-size: .62rem; letter-spacing: .1em; text-transform: uppercase; color: var(--txt-soft); }

.ingat {
    margin-top: 14px; padding: 12px 14px;
    border-left: 3px solid #EA580C; border-radius: 0 10px 10px 0;
    background: #FFF7ED;
    font-size: .84rem; line-height: 1.6; color: #7C2D12;
}

.cuplik { margin-top: 20px; }
.cuplik-judul { font-size: .62rem; letter-spacing: .12em; text-transform: uppercase; color: var(--txt-soft); margin-bottom: 8px; }
.cuplik ul { list-style: none; display: flex; flex-direction: column; }
.cuplik li {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 9px 0; border-bottom: 1px solid var(--edge);
    font-size: .88rem;
}
.cuplik li:last-child { border-bottom: none; }
.cb {
    flex: none; min-width: 52px; text-align: center;
    padding: .22rem .5rem; border-radius: 7px;
    background: #CCFBF1; color: #115E59;
    font-weight: 700; font-size: .78rem;
}
.cuplik li b { flex: 1; min-width: 120px; font-weight: 600; }
.ck { font-size: .72rem; color: var(--txt-soft); }

.kosong { margin-top: 18px; }
.batas { margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--edge); }
</style>
