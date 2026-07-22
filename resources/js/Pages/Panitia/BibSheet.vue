<script setup>
import { Head } from '@inertiajs/vue3';
import KartuBib from '../../Components/KartuBib.vue';

defineProps({
    registrations: { type: Array, default: () => [] },
});

function cetak() {
    window.print();
}

function tutup() {
    window.close();
}
</script>

<template>
    <Head title="Lembar Cetak BIB" />

    <div class="sheet-wrap">
        <!-- Bilah ini sengaja tidak ikut tercetak. -->
        <div class="sheet-bar no-print">
            <div>
                <strong>{{ registrations.length }} nomor BIB siap dicetak.</strong>
                <span>Dua BIB per halaman A4. Atur skala 100% dan matikan header/footer browser.</span>
            </div>
            <div class="sheet-bar-actions">
                <button type="button" class="sheet-btn" @click="cetak">🖨 Cetak Sekarang</button>
                <button type="button" class="sheet-btn sheet-btn--ghost" @click="tutup">Tutup</button>
            </div>
        </div>

        <div class="sheet">
            <KartuBib v-for="r in registrations" :key="r.id" :bib="r" />
        </div>
    </div>
</template>

<style scoped>
.sheet-wrap { background: #EFECE4; min-height: 100vh; padding-bottom: 40px; }

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
    background: #17131F;
    color: #F3EFE6;
    font-family: 'Archivo', sans-serif;
    font-size: .88rem;
}

.sheet-bar strong { display: block; font-size: .95rem; }
.sheet-bar span { color: #A79FB4; font-size: .82rem; }
.sheet-bar-actions { display: flex; gap: 10px; }

.sheet-btn {
    font-family: 'Archivo', sans-serif;
    font-weight: 700;
    font-size: .88rem;
    padding: .55rem 1.1rem;
    border: none;
    border-radius: 8px;
    background: #FF4A1C;
    color: #fff;
    cursor: pointer;
}

.sheet-btn--ghost { background: transparent; color: #F3EFE6; border: 1px solid #4A4358; }

.sheet { max-width: 210mm; margin: 0 auto; padding: 16px; }

@media print {
    .no-print { display: none !important; }
    .sheet-wrap { background: #fff; padding: 0; }
    .sheet { max-width: none; padding: 0; margin: 0; }
}

@page { size: A4 portrait; margin: 8mm; }
</style>
