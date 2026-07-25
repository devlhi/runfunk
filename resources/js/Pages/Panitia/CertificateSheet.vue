<script setup>
import { Head } from '@inertiajs/vue3';
import KartuSertifikat from '../../Components/KartuSertifikat.vue';

defineProps({
    daftar: { type: Array, default: () => [] },
    acara: { type: Object, required: true },
});

function cetak() {
    window.print();
}

function tutup() {
    window.close();
}
</script>

<template>
    <Head title="Lembar Cetak Sertifikat" />

    <div class="sheet-wrap">
        <!-- Bilah ini sengaja tidak ikut tercetak. -->
        <div class="sheet-bar no-print">
            <div>
                <strong>{{ daftar.length }} sertifikat siap dicetak.</strong>
                <span>
                    Satu halaman A4 <b>lanskap</b> per sertifikat. Atur skala 100%,
                    matikan header/footer peramban, dan nyalakan “Background graphics”
                    supaya warnanya ikut tercetak.
                </span>
            </div>
            <div class="sheet-bar-actions">
                <button type="button" class="sheet-btn" @click="cetak">🖨 Cetak Sekarang</button>
                <button type="button" class="sheet-btn sheet-btn--ghost" @click="tutup">Tutup</button>
            </div>
        </div>

        <div v-if="daftar.length" class="sheet">
            <!-- Tiap sertifikat dipaksa mulai di halaman baru; tanpa ini dua
                 lembar bisa terpotong di tengah satu halaman. -->
            <div v-for="s in daftar" :key="s.id" class="lembar">
                <KartuSertifikat :sertifikat="s" :acara="acara" />
            </div>
        </div>

        <p v-else class="kosong no-print">
            Belum ada peserta yang bisa disertifikatkan. Sertifikat baru terbit setelah
            catatan waktu finisnya diisi di halaman Hasil Lomba.
        </p>
    </div>
</template>

<style scoped>
.sheet-wrap { background: #EEF4F3; min-height: 100vh; padding-bottom: 40px; }

.sheet-bar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    padding: 14px 22px;
    background: #0F172A;
    color: #F1F5F9;
    font-family: 'Archivo', sans-serif;
    font-size: .88rem;
}

.sheet-bar strong { display: block; font-size: .95rem; }
.sheet-bar span { color: #94A3B8; font-size: .82rem; max-width: 78ch; display: block; }
.sheet-bar span b { color: #5EEAD4; }
.sheet-bar-actions { display: flex; gap: 10px; }

.sheet-btn {
    font-family: 'Archivo', sans-serif;
    font-weight: 700;
    font-size: .88rem;
    padding: .55rem 1.1rem;
    border: none;
    border-radius: 8px;
    background: #0F766E;
    color: #fff;
    cursor: pointer;
}
.sheet-btn:hover { background: #115E59; }
.sheet-btn--ghost { background: transparent; color: #F1F5F9; border: 1px solid #334155; }

/* Lebar A4 lanskap supaya pratinjau di layar sama dengan hasil cetak. */
.sheet { max-width: 297mm; margin: 0 auto; padding: 16px; }

.lembar { page-break-after: always; break-after: page; margin-bottom: 16px; }
.lembar:last-child { page-break-after: auto; break-after: auto; margin-bottom: 0; }

.kosong {
    max-width: 60ch;
    margin: 60px auto;
    text-align: center;
    line-height: 1.7;
    color: #475569;
}

@media print {
    .no-print { display: none !important; }
    .sheet-wrap { background: #fff; padding: 0; }
    .sheet { max-width: none; padding: 0; margin: 0; }
    .lembar { margin-bottom: 0; }
}

@page { size: A4 landscape; margin: 8mm; }
</style>
