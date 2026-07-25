<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    sertifikat: { type: Object, required: true },
    acara: { type: Object, required: true },
});

/**
 * Sertifikat dicetak lewat dialog browser, bukan dibuat di server. Tidak perlu
 * pustaka PDF, dan peserta bebas memilih "Simpan sebagai PDF" atau cetak langsung.
 */
function cetak() {
    window.print();
}
</script>

<template>
    <Head :title="`E-Sertifikat — ${sertifikat.nama}`" />

    <div class="halaman">
        <div class="bilah no-print">
            <Link href="/dashboard" class="kembali">← Kembali ke dashboard</Link>
            <div class="bilah-aksi">
                <span class="petunjuk">Pilih “Simpan sebagai PDF” di dialog cetak untuk mengunduh.</span>
                <button type="button" class="btn btn--sm" @click="cetak">🖨 Cetak / Simpan PDF</button>
            </div>
        </div>

        <article class="sertifikat">
            <span class="sudut tl"></span><span class="sudut tr"></span>
            <span class="sudut bl"></span><span class="sudut br"></span>

            <header class="s-head">
                <img class="s-logo" src="/images/logo-ika.jpeg" alt="Logo IKA SMK Gotong Royong Telaga Gorontalo" />
                <div class="s-org">
                    <b>IKA SMK Gotong Royong Telaga</b>
                    <span>Ikatan Keluarga Alumni · Gorontalo</span>
                </div>
            </header>

            <p class="s-label">Sertifikat Penghargaan</p>
            <h1 class="s-acara">{{ acara.nama }}</h1>

            <p class="s-untuk">Diberikan kepada</p>
            <p class="s-nama">{{ sertifikat.nama }}</p>

            <p class="s-teks">
                yang telah menyelesaikan kategori <b>{{ sertifikat.kategori }}</b>
                pada {{ acara.nama }} yang diselenggarakan
                {{ acara.tanggal }} di {{ acara.lokasi }}.
            </p>

            <div class="s-angka">
                <div class="s-item">
                    <span class="k">Catatan Waktu</span>
                    <span class="v">{{ sertifikat.waktu }}</span>
                </div>
                <div class="s-item">
                    <span class="k">Nomor BIB</span>
                    <span class="v">{{ sertifikat.bib }}</span>
                </div>
                <div class="s-item">
                    <span class="k">Peringkat {{ sertifikat.gender }}</span>
                    <span class="v">#{{ sertifikat.peringkat_gender }}</span>
                </div>
                <div class="s-item">
                    <span class="k">Peringkat Umum</span>
                    <span class="v">#{{ sertifikat.peringkat }}</span>
                </div>
            </div>

            <footer class="s-foot">
                <div class="s-ttd">
                    <span class="garis"></span>
                    <b>Panitia {{ acara.nama }}</b>
                    <span>IKA SMK Gotong Royong Telaga, Gorontalo</span>
                </div>
                <p class="s-kode mono">
                    Kode verifikasi: {{ sertifikat.kode }}
                </p>
            </footer>
        </article>
    </div>
</template>

<style scoped>
.halaman { min-height: 100vh; background: #EFECE4; padding: 28px 20px 60px; }

.bilah {
    max-width: 900px; margin: 0 auto 24px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; flex-wrap: wrap;
}
.kembali { font-family: 'Space Mono'; font-size: .74rem; letter-spacing: .1em; text-transform: uppercase; color: var(--flame); font-weight: 700; }
.kembali:hover { color: var(--ink); }
.bilah-aksi { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.petunjuk { font-size: .78rem; color: var(--ink-soft); }

/* Rasio A4 lanskap supaya hasil cetaknya pas satu halaman. */
.sertifikat {
    position: relative;
    max-width: 900px;
    margin: 0 auto;
    aspect-ratio: 297 / 210;
    background: #FFFDF8;
    border: 3px solid var(--ink);
    border-radius: 6px;
    padding: 38px 56px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    box-shadow: 10px 10px 0 var(--ink);
}

.sudut { position: absolute; width: 26px; height: 26px; border: 3px solid var(--flame); }
.sudut.tl { top: 14px; left: 14px; border-right: none; border-bottom: none; }
.sudut.tr { top: 14px; right: 14px; border-left: none; border-bottom: none; }
.sudut.bl { bottom: 14px; left: 14px; border-right: none; border-top: none; }
.sudut.br { bottom: 14px; right: 14px; border-left: none; border-top: none; }

.s-head { display: flex; align-items: center; gap: .7rem; margin-bottom: 14px; }
.s-logo { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
.s-org { display: flex; flex-direction: column; text-align: left; }
.s-org b { font-size: .82rem; }
.s-org span { font-size: .64rem; color: var(--ink-soft); }

.s-label { font-family: 'Space Mono'; font-size: .68rem; letter-spacing: .3em; text-transform: uppercase; color: var(--ink-soft); }
.s-acara { font-family: 'Big Shoulders Display'; font-weight: 900; font-size: clamp(1.8rem, 4vw, 2.8rem); text-transform: uppercase; line-height: 1; margin: 4px 0 18px; }

.s-untuk { font-size: .82rem; color: var(--ink-soft); }
.s-nama {
    font-family: 'Big Shoulders Display'; font-weight: 900;
    font-size: clamp(2.2rem, 5.5vw, 3.6rem); line-height: 1;
    color: var(--flame); margin: 6px 0 14px;
    border-bottom: 3px solid var(--ink); padding-bottom: 10px;
}

.s-teks { font-size: .9rem; color: var(--ink-soft); max-width: 56ch; line-height: 1.6; }
.s-teks b { color: var(--ink); }

.s-angka { display: flex; gap: 34px; flex-wrap: wrap; justify-content: center; margin: 22px 0 auto; }
.s-item { display: flex; flex-direction: column; gap: 3px; }
.s-item .k { font-family: 'Space Mono'; font-size: .6rem; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-soft); }
.s-item .v { font-family: 'Space Mono'; font-weight: 700; font-size: 1.3rem; }

.s-foot { width: 100%; display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; }
.s-ttd { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.s-ttd .garis { width: 180px; border-top: 1.5px solid var(--ink); margin-bottom: 6px; }
.s-ttd b { font-size: .82rem; }
.s-ttd span { font-size: .66rem; color: var(--ink-soft); }
.s-kode { font-size: .6rem; color: var(--ink-soft); letter-spacing: .06em; }

@media print {
    .no-print { display: none !important; }
    .halaman { background: #fff; padding: 0; }
    .sertifikat { max-width: none; border-radius: 0; box-shadow: none; border-width: 2px; margin: 0; }
    @page { size: A4 landscape; margin: 8mm; }

    /* Tanpa ini browser boleh membuang warna demi menghemat tinta (nilai bawaan
       "economy"): nama peserta yang oranye tercetak kelabu, empat sudut oranye
       hilang, dan latar krem jadi putih polos — sertifikatnya kehilangan seluruh
       identitasnya justru saat dicetak. Aturan yang sama sudah dipakai di kartu
       BIB dan kartu panitia. */
    .sertifikat,
    .sertifikat * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

@media (max-width: 720px) {
    .sertifikat { aspect-ratio: auto; padding: 30px 24px; }
    .s-angka { gap: 20px; }
    .s-foot { flex-direction: column; align-items: center; gap: 14px; margin-top: 24px; }
}
</style>
