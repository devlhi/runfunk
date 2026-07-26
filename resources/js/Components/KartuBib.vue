<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    /** { bib_number, name, code, category, city, jersey_size, blood_type, emergency_name, emergency_phone, qr } */
    bib: { type: Object, required: true },
    /**
     * Pratinjau di layar memakai kartu yang lebih pendek. Ukuran lembar cetak
     * dikunci ke tinggi milimeter supaya muat dua per halaman A4 — terlalu
     * tinggi kalau dipakai di dalam dialog.
     */
    pratinjau: { type: Boolean, default: false },
});

const page = usePage();
const namaAcara = computed(() => page.props.event?.name ?? 'Gong Funrun 2026');

const sepuluhK = computed(() => props.bib.category === '10K');
</script>

<template>
    <article class="bib" :class="{ 'bib--pratinjau': pratinjau, 'bib--10k': sepuluhK }">
        <!-- Lubang peniti di empat sudut, seperti nomor dada sungguhan. -->
        <span class="bib-pin tl"></span>
        <span class="bib-pin tr"></span>
        <span class="bib-pin bl"></span>
        <span class="bib-pin br"></span>

        <header class="bib-kepala">
            <img class="bib-logo" src="/images/logo-ika.jpeg" alt="" />

            <div class="bib-acara">
                <b>{{ namaAcara }}</b>
                <span>IKA SMK GOTONG ROYONG · GORONTALO</span>
            </div>

            <span class="bib-kat">{{ bib.category }}</span>
        </header>

        <!-- Nomornya yang paling penting: harus terbaca dari jauh oleh fotografer
             dan petugas garis finis, jadi tidak ada apa pun yang menyainginya. -->
        <div class="bib-panggung">
            <div class="bib-nomor">{{ bib.bib_number }}</div>

            <div v-if="bib.qr" class="bib-qr">
                <img :src="bib.qr" alt="" />
                <span>PINDAI PANITIA</span>
            </div>
        </div>

        <div class="bib-nama">{{ bib.name }}</div>

        <footer class="bib-kaki">
            <div class="bib-data">
                <span><i>KODE</i>{{ bib.code }}</span>
                <span><i>KOTA</i>{{ bib.city }}</span>
                <span><i>JERSEY</i>{{ bib.jersey_size }}</span>
                <span v-if="bib.blood_type"><i>GOL. DARAH</i>{{ bib.blood_type }}</span>
            </div>

            <div class="bib-darurat">
                <i>KONTAK DARURAT</i>{{ bib.emergency_name }} · {{ bib.emergency_phone }}
            </div>
        </footer>
    </article>
</template>

<style scoped>
/* ============================================================================
   NOMOR DADA — "PESISIR"

   Gorontalo berdiri di tepi laut, dan toska itu warna airnya. Pita tua di
   kepala kartu jadi garis cakrawala; sisanya dibiarkan putih supaya angkanya
   punya ruang sendiri. Bukan sekadar selera: bidang gelap yang lebar memboroskan
   tinta dan keluar belang di pencetak kantor.
   ============================================================================ */
.bib {
    --toska-tua: #0F766E;
    --toska-lebih-tua: #115E59;
    --toska-terang: #5EEAD4;
    --tinta: #0F172A;
    --kabur: #64748B;
    --garis: #CBD5E1;

    position: relative;
    display: flex;
    flex-direction: column;
    height: 128mm;
    margin-bottom: 6mm;
    overflow: hidden;
    background: #fff;
    border: 0.6mm solid var(--tinta);
    border-radius: 3mm;
    color: var(--tinta);
    page-break-inside: avoid;
    break-inside: avoid;

    /* Tanpa ini peramban membuang semua warna latar saat mencetak, dan pembeda
       5K/10K yang jadi andalan panitia di antrean ikut hilang jadi putih. */
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* 10K memakai ujung yang lebih biru. Dibedakan lewat gelap-terangnya, bukan
   cuma rona, supaya tetap kebaca sekilas dari ujung antrean. */
.bib--10k {
    --toska-tua: #155E75;
    --toska-lebih-tua: #164E63;
    --toska-terang: #A5F3FC;
}

/* ------------------------------------------------------------- Kepala */
.bib-kepala {
    flex: none;
    display: flex;
    align-items: center;
    gap: 4mm;
    padding: 4mm 6mm;
    /* Tekstur garis miring yang sama dengan hero landing page. */
    background:
        repeating-linear-gradient(115deg, transparent 0 7mm, rgba(255, 255, 255, .06) 7mm 7.6mm),
        linear-gradient(100deg, var(--toska-lebih-tua), var(--toska-tua));
    color: #fff;
}

.bib-logo {
    flex: none;
    width: 11mm; height: 11mm;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    border: 0.5mm solid #fff;
}

.bib-acara { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.6mm; }
.bib-acara b {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 800;
    font-size: 6mm;
    line-height: 1;
    letter-spacing: .01em;
    text-transform: uppercase;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.bib-acara span {
    font-family: 'Space Mono', monospace;
    font-size: 2.1mm;
    letter-spacing: .1em;
    color: rgba(255, 255, 255, .82);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Keping kategori: toska cerah dengan teks tinta — 7:1, terbaca dari jauh
   dan tetap tegas walau printernya pucat. */
.bib-kat {
    flex: none;
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 8mm;
    line-height: 1;
    letter-spacing: .02em;
    padding: 1.6mm 4mm 1mm;
    border-radius: 1.5mm;
    background: var(--toska-terang);
    color: var(--tinta);
}

/* ------------------------------------------------------- Panggung angka */
.bib-panggung {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6mm;
    padding: 2mm 6mm 0;
    min-height: 0;
}

/* Big Shoulders memang dirancang untuk papan nama kota — padat, tebal, dan
   karena ramping ia bisa dibuat jauh lebih tinggi pada lebar yang sama.
   Tinggi itulah yang menentukan keterbacaan dari 15 meter. */
.bib-nomor {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 46mm;
    line-height: .82;
    letter-spacing: -.01em;
    font-variant-numeric: tabular-nums;
    color: var(--tinta);
}

.bib-qr {
    flex: none;
    display: flex; flex-direction: column; align-items: center; gap: 1mm;
    padding: 2mm;
    border: 0.4mm solid var(--garis);
    border-radius: 2mm;
}
.bib-qr img { display: block; width: 20mm; height: 20mm; }
.bib-qr span {
    font-family: 'Space Mono', monospace;
    font-size: 1.8mm;
    letter-spacing: .1em;
    color: var(--kabur);
}

/* --------------------------------------------------------------- Nama */
.bib-nama {
    flex: none;
    padding: 0 6mm 3mm;
    text-align: center;
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 700;
    font-size: 9mm;
    /* Sama seperti di kartu panitia: kapital Big Shoulders melampaui kotak
       barisnya, jadi line-height 1 mencukur bagian bawah huruf. */
    line-height: 1.1;
    letter-spacing: .04em;
    text-transform: uppercase;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* --------------------------------------------------------------- Kaki */
.bib-kaki { flex: none; border-top: 0.4mm solid var(--garis); }

.bib-data {
    display: flex;
    justify-content: space-between;
    gap: 3mm;
    padding: 2.5mm 6mm;
}

.bib-data span, .bib-darurat {
    display: flex; flex-direction: column; gap: 0.4mm;
    font-family: 'Space Mono', monospace;
    font-size: 2.6mm;
    line-height: 1.2;
    min-width: 0;
}

/* Label kecil di atas nilainya: satu baris padat lebih sulit dibaca cepat
   daripada pasangan label–nilai. */
.bib-data i, .bib-darurat i {
    font-style: normal;
    font-size: 1.8mm;
    letter-spacing: .12em;
    color: var(--kabur);
}

.bib-darurat {
    padding: 2mm 6mm 2.5mm;
    background: var(--toska-terang);
    border-top: 0.4mm solid var(--garis);
}
.bib-darurat i { color: var(--toska-lebih-tua); }

/* --------------------------------------------------------- Lubang peniti */
.bib-pin {
    position: absolute;
    z-index: 2;
    width: 3.4mm; height: 3.4mm;
    border: 0.5mm solid currentColor;
    border-radius: 50%;
    color: rgba(255, 255, 255, .75);
}
.bib-pin.bl, .bib-pin.br { color: var(--garis); }

.bib-pin.tl { top: 3.4mm; left: 3.4mm; }
.bib-pin.tr { top: 3.4mm; right: 3.4mm; }
.bib-pin.bl { bottom: 3.4mm; left: 3.4mm; }
.bib-pin.br { bottom: 3.4mm; right: 3.4mm; }

/* ----------------------------------------------------- Varian pratinjau */
.bib--pratinjau {
    height: auto;
    margin-bottom: 0;
    box-shadow: 5px 5px 0 var(--toska-tua);
}
.bib--pratinjau .bib-panggung { padding-top: 4mm; padding-bottom: 2mm; }
.bib--pratinjau .bib-nomor { font-size: 30mm; }
.bib--pratinjau .bib-qr img { width: 15mm; height: 15mm; }
.bib--pratinjau .bib-nama { font-size: 7mm; }

@media print {
    .bib { border-radius: 0; }
}
</style>
