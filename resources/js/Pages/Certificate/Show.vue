<script setup>
import { Head, Link } from '@inertiajs/vue3';
import KartuSertifikat from '../../Components/KartuSertifikat.vue';

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
                <button type="button" class="cetak-btn" @click="cetak">🖨 Cetak / Simpan PDF</button>
            </div>
        </div>

        <div class="panggung">
            <KartuSertifikat :sertifikat="sertifikat" :acara="acara" pratinjau />
        </div>

        <p class="catatan no-print">
            Keaslian sertifikat ini bisa diperiksa siapa pun dengan memindai kode QR di atas,
            atau membuka <span class="mono">{{ sertifikat.verifikasi_url }}</span>
        </p>
    </div>
</template>

<style scoped>
.halaman {
    min-height: 100vh;
    /* Latar toska sangat muda supaya lembar putih sertifikatnya "terangkat",
       bukan menyatu dengan halaman. */
    background:
        radial-gradient(60% 50% at 50% 0%, rgba(15, 118, 110, .10), transparent 70%),
        #EEF4F3;
    padding: 28px 20px 60px;
}

.bilah {
    max-width: 980px; margin: 0 auto 22px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; flex-wrap: wrap;
}
.kembali {
    font-family: 'Space Mono', monospace; font-size: .74rem;
    letter-spacing: .1em; text-transform: uppercase; font-weight: 700;
    color: #0F766E;
}
.kembali:hover { color: #0F172A; }

.bilah-aksi { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.petunjuk { font-size: .78rem; color: #64748B; }

.cetak-btn {
    font-weight: 700; font-size: .88rem;
    padding: .6rem 1.15rem;
    border: 2px solid #0F172A; border-radius: 50px;
    background: #0F766E; color: #fff; cursor: pointer;
    box-shadow: 3px 3px 0 #0F172A;
    transition: .14s;
}
.cetak-btn:hover { background: #115E59; transform: translate(-1px, -1px); box-shadow: 4px 4px 0 #0F172A; }

/* Bayangan pekat khas panel: lembarnya terasa benar-benar sehelai kertas. */
.panggung {
    max-width: 980px; margin: 0 auto;
    box-shadow: 10px 10px 0 rgba(15, 118, 110, .22);
}

.catatan {
    max-width: 980px; margin: 22px auto 0;
    font-size: .78rem; line-height: 1.7; color: #64748B; text-align: center;
}
.catatan .mono { font-family: 'Space Mono', monospace; word-break: break-all; color: #0F766E; }

@media print {
    .no-print { display: none !important; }
    .halaman { background: #fff; padding: 0; }
    .panggung { max-width: none; margin: 0; box-shadow: none; }
    @page { size: A4 landscape; margin: 8mm; }
}

@media (max-width: 720px) {
    .halaman { padding: 18px 12px 40px; }
    .bilah { justify-content: center; text-align: center; }
    .panggung { box-shadow: 6px 6px 0 rgba(15, 118, 110, .22); }
}
</style>
