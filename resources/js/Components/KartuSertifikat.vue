<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** { nama, bib, kategori, waktu, peringkat, peringkat_gender, gender, kode, qr, verifikasi_url } */
    sertifikat: { type: Object, required: true },
    /** { nama, tanggal, lokasi } */
    acara: { type: Object, required: true },
    /**
     * Di layar kartunya mengikuti lebar wadah lewat aspect-ratio; untuk lembar
     * cetak massal ukurannya dikunci ke milimeter A4 lanskap supaya tepat satu
     * halaman per sertifikat.
     */
    pratinjau: { type: Boolean, default: false },
});

const sepuluhK = computed(() => props.sertifikat.kategori === '10K');

/**
 * Nama panjang khas Indonesia ("Andi Muhammad Fadhlurrahman Al-Ghifari") akan
 * membungkus jadi dua baris dan mendorong isi lain keluar halaman. Ukurannya
 * diturunkan bertahap supaya tetap satu-dua baris tanpa perlu memotong nama —
 * nama orang tidak boleh dipotong di sertifikatnya sendiri.
 */
const ukuranNama = computed(() => {
    const n = (props.sertifikat.nama ?? '').length;

    if (n > 42) return 'nama--xs';
    if (n > 30) return 'nama--s';
    if (n > 22) return 'nama--m';

    return '';
});
</script>

<template>
    <article class="srt" :class="{ 'srt--pratinjau': pratinjau, 'srt--10k': sepuluhK }">
        <!-- Pita cakrawala: garis laut yang sama dengan kartu BIB & kartu panitia. -->
        <header class="srt-pita">
            <img class="srt-logo" src="/images/logo-ika.jpeg" alt="" />
            <div class="srt-org">
                <b>{{ acara.nama }}</b>
                <span>IKA SMK GOTONG ROYONG TELAGA · GORONTALO</span>
            </div>
            <span class="srt-kat">{{ sertifikat.kategori }}</span>
        </header>

        <div class="srt-badan">
            <p class="srt-label">Sertifikat Penghargaan</p>

            <p class="srt-untuk">Diberikan kepada</p>
            <p class="srt-nama" :class="ukuranNama">{{ sertifikat.nama }}</p>

            <p class="srt-teks">
                yang telah menyelesaikan kategori <b>{{ sertifikat.kategori }}</b>
                pada {{ acara.nama }}, {{ acara.tanggal }}, di {{ acara.lokasi }}.
            </p>

            <div class="srt-angka">
                <div class="srt-item srt-item--utama">
                    <i>Catatan Waktu</i><b>{{ sertifikat.waktu }}</b>
                </div>
                <div class="srt-item"><i>Nomor BIB</i><b>{{ sertifikat.bib }}</b></div>
                <div class="srt-item"><i>Peringkat {{ sertifikat.gender }}</i><b>#{{ sertifikat.peringkat_gender }}</b></div>
                <div class="srt-item"><i>Peringkat Umum</i><b>#{{ sertifikat.peringkat }}</b></div>
            </div>
        </div>

        <footer class="srt-kaki">
            <div class="srt-ttd">
                <span class="garis"></span>
                <b>Panitia {{ acara.nama }}</b>
                <span class="srt-ttd-sub">IKA SMK Gotong Royong Telaga, Gorontalo</span>
            </div>

            <!-- QR menuju halaman pemeriksaan keaslian. Kode teksnya tetap
                 dicantumkan supaya sertifikat yang difotokopi buram pun masih
                 bisa dicocokkan secara manual. -->
            <div v-if="sertifikat.qr" class="srt-qr">
                <img :src="sertifikat.qr" alt="" />
                <span>Pindai untuk<br />memeriksa keaslian</span>
            </div>

            <div class="srt-kode">
                <i>Kode Verifikasi</i>
                <b>{{ sertifikat.kode }}</b>
            </div>
        </footer>
    </article>
</template>

<style scoped>
/* ============================================================================
   SERTIFIKAT FINISHER — "PESISIR"

   Melanjutkan bahasa yang sudah dipakai nomor dada dan kartu panitia: toska
   sebagai warna laut Gorontalo, pita tua di kepala sebagai garis cakrawala, dan
   badan putih yang lapang. Bedanya cuma niatnya — nomor dada dibuat terbaca dari
   15 meter, sertifikat dibuat untuk dipandang dari jarak satu meja dan disimpan
   bertahun-tahun. Karena itu di sini ruang kosongnya dibiarkan lebih lega dan
   satu-satunya yang boleh berteriak adalah nama pemiliknya.
   ============================================================================ */
.srt {
    --toska-tua: #0F766E;
    --toska-lebih-tua: #115E59;
    --toska-terang: #5EEAD4;
    --tinta: #0F172A;
    --kabur: #64748B;
    --garis: #CBD5E1;

    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
    border: 0.6mm solid var(--tinta);
    color: var(--tinta);
    page-break-inside: avoid;
    break-inside: avoid;

    /* Tanpa ini peramban membuang warna latar demi hemat tinta, dan sertifikat
       kehilangan seluruh identitasnya justru saat dicetak. */
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* 10K memakai ujung yang lebih biru, sama seperti nomor dadanya. */
.srt--10k {
    --toska-tua: #155E75;
    --toska-lebih-tua: #164E63;
    --toska-terang: #A5F3FC;
}

/* Sudut kertas: dua garis tipis toska, bukan bingkai penuh — bingkai penuh
   membuat lembar sertifikat terlihat seperti sertifikat kursus daring. */
.srt::before,
.srt::after {
    content: "";
    position: absolute;
    width: 14mm;
    height: 14mm;
    border: 0.5mm solid var(--toska-terang);
    pointer-events: none;
}
.srt::before { top: 5mm; left: 5mm; border-right: none; border-bottom: none; }
.srt::after { bottom: 5mm; right: 5mm; border-left: none; border-top: none; }

/* ----------------------------------------------------------- Pita kepala */
.srt-pita {
    flex: none;
    display: flex;
    align-items: center;
    gap: 4mm;
    padding: 4mm 8mm;
    background:
        repeating-linear-gradient(115deg, transparent 0 7mm, rgba(255, 255, 255, .06) 7mm 7.6mm),
        linear-gradient(100deg, var(--toska-lebih-tua), var(--toska-tua));
    color: #fff;
}

.srt-logo {
    flex: none;
    width: 11mm; height: 11mm;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    border: 0.5mm solid #fff;
}

.srt-org { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.6mm; }
.srt-org b {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 800;
    font-size: 6mm;
    line-height: 1.1;
    letter-spacing: .01em;
    text-transform: uppercase;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.srt-org span {
    font-family: 'Space Mono', monospace;
    font-size: 2.1mm;
    letter-spacing: .1em;
    color: rgba(255, 255, 255, .82);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.srt-kat {
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

/* ----------------------------------------------------------------- Badan */
.srt-badan {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 6mm 16mm 0;
}

.srt-label {
    font-family: 'Space Mono', monospace;
    font-size: 2.6mm;
    letter-spacing: .42em;
    text-transform: uppercase;
    color: var(--kabur);
    /* Huruf terakhir ikut menerima jarak kanan; digeser balik supaya barisnya
       benar-benar terlihat terpusat. */
    text-indent: .42em;
}

.srt-untuk {
    margin-top: 5mm;
    font-family: 'Space Mono', monospace;
    font-size: 2.9mm;
    letter-spacing: .06em;
    color: var(--kabur);
}

/* Satu-satunya yang boleh berteriak. Garis toska di bawahnya menegaskan bahwa
   ini nama pemilik, bukan judul. */
.srt-nama {
    margin-top: 1mm;
    padding-bottom: 3mm;
    max-width: 100%;
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 19mm;
    /* Kapital Big Shoulders melampaui kotak barisnya; 1.1 mencegah bagian bawah
       huruf tercukur. */
    line-height: 1.1;
    letter-spacing: .01em;
    text-transform: uppercase;
    color: var(--toska-lebih-tua);
    border-bottom: 0.8mm solid var(--toska-terang);
}
.srt-nama.nama--m { font-size: 15mm; }
.srt-nama.nama--s { font-size: 12mm; }
.srt-nama.nama--xs { font-size: 9.5mm; }

.srt-teks {
    margin-top: 4mm;
    max-width: 155mm;
    font-family: 'Space Mono', monospace;
    font-size: 2.9mm;
    line-height: 1.8;
    color: var(--kabur);
}
.srt-teks b { color: var(--tinta); }

/* --------------------------------------------------------------- Angka */
.srt-angka {
    display: flex;
    justify-content: center;
    gap: 14mm;
    margin-top: 7mm;
}

.srt-item { display: flex; flex-direction: column; align-items: center; gap: 1mm; }
.srt-item i {
    font-style: normal;
    font-family: 'Space Mono', monospace;
    font-size: 2mm;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--kabur);
}
.srt-item b {
    font-family: 'Space Mono', monospace;
    font-weight: 700;
    font-size: 5mm;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

/* Catatan waktu itu alasan sertifikat ini ada — dibedakan warnanya dari angka
   pendamping supaya mata jatuh ke sana lebih dulu. */
.srt-item--utama b { color: var(--toska-tua); font-size: 6.5mm; }

/* ----------------------------------------------------------------- Kaki */
.srt-kaki {
    flex: none;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 8mm;
    padding: 0 12mm 7mm;
}

.srt-ttd { display: flex; flex-direction: column; align-items: center; gap: 0.8mm; flex: 1; }
.srt-ttd .garis { width: 52mm; border-top: 0.4mm solid var(--tinta); margin-bottom: 1.5mm; }
.srt-ttd b {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 700;
    font-size: 4.4mm;
    line-height: 1.1;
    text-transform: uppercase;
    letter-spacing: .02em;
}
.srt-ttd-sub { font-family: 'Space Mono', monospace; font-size: 2.1mm; color: var(--kabur); }

.srt-qr {
    flex: none;
    order: -1;
    display: flex;
    align-items: center;
    gap: 2.5mm;
    padding: 2mm;
    border: 0.4mm solid var(--garis);
    border-radius: 2mm;
}
.srt-qr img { display: block; width: 17mm; height: 17mm; }
.srt-qr span {
    font-family: 'Space Mono', monospace;
    font-size: 1.9mm;
    line-height: 1.5;
    letter-spacing: .06em;
    color: var(--kabur);
    text-align: left;
}

.srt-kode { flex: none; display: flex; flex-direction: column; align-items: flex-end; gap: 0.6mm; }
.srt-kode i {
    font-style: normal;
    font-family: 'Space Mono', monospace;
    font-size: 1.9mm;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--kabur);
}
.srt-kode b { font-family: 'Space Mono', monospace; font-weight: 700; font-size: 3mm; }

/* ------------------------------------------------- Ukuran layar vs cetak */
/* Di layar: ikut lebar wadah, rasio A4 lanskap supaya pratinjau jujur. */
.srt--pratinjau { aspect-ratio: 297 / 210; border-radius: 2mm; }

/* Lembar cetak: dikunci ke milimeter, tepat satu halaman per sertifikat. */
.srt:not(.srt--pratinjau) { height: 194mm; }

@media (max-width: 720px) {
    /* Rasio A4 dilepas di layar sempit — dipaksakan, tulisannya jadi terlalu
       kecil untuk dibaca di HP. Peserta paling sering membuka dari tautan
       WhatsApp, jadi tampilan HP-nya yang harus nyaman, bukan miniatur A4. */
    .srt--pratinjau { aspect-ratio: auto; }
    .srt-badan { padding: 8mm 6mm 0; }
    .srt-nama, .srt-nama.nama--m, .srt-nama.nama--s, .srt-nama.nama--xs { font-size: 11mm; }
    .srt-angka { flex-wrap: wrap; gap: 7mm 10mm; }
    .srt-kaki { flex-direction: column; align-items: center; gap: 5mm; padding-bottom: 8mm; }
    .srt-qr { order: 0; }
    .srt-kode { align-items: center; }
}

@media print {
    .srt { border-radius: 0; }
}
</style>
