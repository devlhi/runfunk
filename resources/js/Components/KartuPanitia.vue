<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** { nama, jabatan, peran, telepon, nomor, qr, is_developer } */
    kartu: { type: Object, required: true },
    acara: { type: Object, required: true },
    /** Pratinjau di layar memakai bayangan; hasil cetak tidak. */
    pratinjau: { type: Boolean, default: false },
});

/**
 * Ukuran nama menyesuaikan panjangnya.
 *
 * Kotaknya dibatasi dua baris supaya kartu tidak melar. Pada ukuran tetap,
 * nama tiga kata seperti "Muhammad Abdurrahman Hasanuddin" kehilangan kata
 * terakhirnya — dan pada kartu identitas, nama yang terpotong itu cacat, bukan
 * sekadar kurang rapi.
 */
const ukuranNama = computed(() => {
    const n = (props.kartu.nama ?? '').length;

    return n > 28 ? 'kp-nama--kecil' : (n > 18 ? 'kp-nama--sedang' : '');
});
</script>

<template>
    <article class="kp" :class="{ 'kp--pratinjau': pratinjau, 'kp--dev': kartu.is_developer }">
        <!-- Tulang punggung tegak. Dari jarak lanyard, kata "PANITIA" inilah
             yang pertama terbaca — sebelum nama, sebelum apa pun. -->
        <aside class="kp-tulang">
            <span class="kp-tulang-teks">PANITIA</span>
        </aside>

        <div class="kp-badan">
            <header class="kp-kepala">
                <img class="kp-logo" src="/images/logo-ika.jpeg" alt="" />
                <div class="kp-acara">
                    <b>{{ acara.nama }}</b>
                    <span>{{ acara.tanggal }}</span>
                </div>
            </header>

            <div class="kp-isi">
                <!-- Pas foto 3×4. Yang belum mengunggah fotonya tetap bisa
                     dicetak — bingkainya dibiarkan kosong untuk ditempel manual. -->
                <div class="kp-foto" :class="{ 'kp-foto--kosong': !kartu.foto }">
                    <img v-if="kartu.foto" :src="kartu.foto" alt="" />
                    <span v-else>TEMPEL<br />PAS FOTO<br /><i>3 × 4</i></span>
                </div>

                <div class="kp-orang">
                    <div class="kp-nama" :class="ukuranNama">{{ kartu.nama }}</div>
                    <div class="kp-jabatan">{{ kartu.jabatan || kartu.peran }}</div>
                    <div v-if="kartu.telepon" class="kp-telepon">{{ kartu.telepon }}</div>
                </div>
            </div>

            <footer class="kp-kaki">
                <div class="kp-id">
                    <i>NO. PANITIA</i>
                    <b>{{ kartu.nomor }}</b>
                </div>

                <span class="kp-catatan">WAJIB DIPAKAI SAAT BERTUGAS</span>

                <div class="kp-qr">
                    <img v-if="kartu.qr" :src="kartu.qr" alt="" />
                    <div v-else class="kp-qr-kosong"></div>
                </div>
            </footer>
        </div>
    </article>
</template>

<style scoped>
/* ============================================================================
   KARTU PANITIA — "PESISIR"

   Sepupu dari nomor dada, tapi tugasnya lain: bukan dibaca dari 15 meter,
   melainkan dari satu meter di dada orang yang sedang berdiri. Karena itu
   susunannya mendatar dengan tulang punggung tegak — kata "PANITIA" terbaca
   lebih dulu daripada apa pun, bahkan saat kartunya sedikit terlipat.
   ============================================================================ */
.kp {
    --toska-tua: #0F766E;
    --toska-lebih-tua: #115E59;
    --toska-terang: #5EEAD4;
    --tinta: #0F172A;
    --kabur: #64748B;
    --garis: #CBD5E1;

    position: relative;
    display: flex;
    width: 85.6mm;
    height: 54mm;
    overflow: hidden;
    background: #fff;
    border: 0.5mm solid var(--tinta);
    border-radius: 2.5mm;
    color: var(--tinta);
    page-break-inside: avoid;
    break-inside: avoid;

    /* Tanpa ini peramban membuang warna latar saat mencetak — tulang punggung
       toska yang jadi penanda utama kartu ini akan keluar putih polos. */
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* Developer memakai ujung yang lebih biru, sejalan dengan pembeda 5K/10K
   di nomor dada. */
.kp--dev {
    --toska-tua: #155E75;
    --toska-lebih-tua: #164E63;
    --toska-terang: #A5F3FC;
}

/* ------------------------------------------------------ Tulang punggung */
.kp-tulang {
    flex: none;
    width: 11mm;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        repeating-linear-gradient(115deg, transparent 0 5mm, rgba(255, 255, 255, .07) 5mm 5.5mm),
        linear-gradient(170deg, var(--toska-tua), var(--toska-lebih-tua));
}

.kp-tulang-teks {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 6.4mm;
    line-height: 1;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: #fff;
    /* Dibaca dari bawah ke atas: arah yang lazim untuk punggung buku dan
       tanda pengenal, jadi kepala tak perlu dimiringkan untuk membacanya. */
    writing-mode: vertical-rl;
    transform: rotate(180deg);
}

/* --------------------------------------------------------------- Badan */
.kp-badan { flex: 1; display: flex; flex-direction: column; min-width: 0; }

.kp-kepala {
    flex: none;
    display: flex; align-items: center; gap: 2.5mm;
    padding: 2.5mm 3.5mm 2mm;
    border-bottom: 0.4mm solid var(--garis);
}

.kp-logo {
    flex: none;
    width: 7.5mm; height: 7.5mm;
    border-radius: 50%;
    object-fit: cover;
    border: 0.35mm solid var(--tinta);
}

.kp-acara { min-width: 0; display: flex; flex-direction: column; gap: 0.3mm; }
.kp-acara b {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 800;
    font-size: 4mm;
    line-height: 1;
    letter-spacing: .01em;
    text-transform: uppercase;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.kp-acara span {
    font-family: 'Space Mono', monospace;
    font-size: 2mm;
    letter-spacing: .06em;
    color: var(--kabur);
}

.kp-isi {
    flex: 1;
    display: flex; align-items: center; gap: 3mm;
    padding: 1.5mm 3.5mm;
    min-height: 0;
}

/* Pas foto memakai perbandingan 3:4 seperti pas foto cetak pada umumnya, jadi
   yang ditempel manual pas di bingkainya tanpa perlu digunting ulang. */
.kp-foto {
    flex: none;
    width: 17mm;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    border: 0.4mm solid var(--tinta);
    border-radius: 1mm;
    background: #fff;
}

.kp-foto img { display: block; width: 100%; height: 100%; object-fit: cover; }

.kp-foto--kosong {
    display: grid;
    place-items: center;
    border-style: dashed;
    border-color: var(--garis);
    background: #F8FAFC;
    text-align: center;
    font-family: 'Space Mono', monospace;
    font-size: 1.9mm;
    line-height: 1.5;
    letter-spacing: .06em;
    color: var(--kabur);
}
.kp-foto--kosong i { font-style: normal; font-size: 2.4mm; color: var(--toska-tua); }

.kp-orang { flex: 1; min-width: 0; }

.kp-nama {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 7.6mm;
    /* Huruf kapital Big Shoulders lebih tinggi dari kotak barisnya. Pada .95
       bagian bawah nama tercukur 4px, dan pada 1 masih tersisa 2px — 1.1 baru
       benar-benar melingkupi glifnya. */
    line-height: 1.1;
    letter-spacing: .01em;
    text-transform: uppercase;
    /* Nama panjang turun ke baris kedua, bukan meluber keluar kartu. */
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Nama panjang diberi baris ketiga, bukan dikecilkan terus-menerus.
   Terukur: pada 5,2mm "MUHAMMAD ABDURRAHMAN" butuh 206px di kotak 174px, jadi
   tiap kata memang turun sendiri. Mengecilkan huruf sampai dua kata muat akan
   menyisakan nama setinggi 4mm — sedangkan ruang tegaknya masih sisa 34px. */
.kp-nama--sedang { font-size: 6.4mm; -webkit-line-clamp: 3; }
.kp-nama--kecil { font-size: 5.2mm; -webkit-line-clamp: 3; }

/* Jabatan diberi latar toska: inilah yang dicari orang saat mencari
   "siapa yang mengurus race pack", jadi ia tidak boleh tenggelam. */
.kp-jabatan {
    display: inline-block;
    margin-top: 1.2mm;
    padding: 0.8mm 2mm;
    border-radius: 1mm;
    background: var(--toska-terang);
    font-size: 2.7mm;
    font-weight: 700;
    line-height: 1.15;
    max-width: 100%;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.kp-telepon {
    font-family: 'Space Mono', monospace;
    font-size: 2.4mm;
    color: var(--kabur);
    margin-top: 1.2mm;
}

/* --------------------------------------------------------------- Kaki */
.kp-kaki {
    flex: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2mm;
    padding: 1.5mm 3.5mm 1.8mm;
    border-top: 0.4mm solid var(--garis);
}

/* Nomor panitia dibuat menonjol: inilah yang dibacakan lewat radio saat ada
   pos tanpa sinyal yang perlu memastikan seseorang. */
.kp-id { display: flex; flex-direction: column; gap: 0.2mm; min-width: 0; }
.kp-id i {
    font-style: normal;
    font-family: 'Space Mono', monospace;
    font-size: 1.7mm;
    letter-spacing: .12em;
    color: var(--kabur);
}
.kp-id b {
    font-family: 'Space Mono', monospace;
    font-size: 3mm;
    font-weight: 700;
    letter-spacing: .02em;
    color: var(--tinta);
}

.kp-catatan {
    flex: 1;
    text-align: center;
    font-family: 'Space Mono', monospace;
    font-size: 1.7mm;
    /* Renggangnya ditahan di .02em: pada .06em teksnya melebar ~11px melewati
       ruang tersisa dan berakhir terpotong titik-titik. */
    letter-spacing: .02em;
    color: var(--kabur);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.kp-qr { flex: none; }
.kp-qr img, .kp-qr-kosong { display: block; width: 13mm; height: 13mm; }

/* Di pratinjau layar, QR belum diterbitkan — ruangnya tetap dipesan supaya
   tata letaknya sama persis dengan hasil cetak. */
.kp-qr-kosong {
    border: 0.4mm dashed var(--garis);
    border-radius: 1mm;
}

.kp--pratinjau { box-shadow: 5px 5px 0 var(--toska-tua); }

@media print {
    .kp { border-radius: 0; }
}
</style>
