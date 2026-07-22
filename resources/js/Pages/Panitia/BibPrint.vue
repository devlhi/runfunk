<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import Paginasi from '../../Components/Paginasi.vue';

const props = defineProps({
    registrations: { type: Object, required: true },
    /** Id seluruh peserta yang cocok filter, bukan hanya halaman yang terbuka. */
    semuaId: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const baris = computed(() => props.registrations.data ?? []);

const picked = ref(new Set(props.semuaId));
const category = ref(props.filters.category ?? '');

// Ganti filter -> muat ulang daftar, lalu pilih semua hasil yang baru.
watch(category, (value) => {
    router.get('/panitia/cetak-bib', value ? { category: value } : {}, {
        preserveState: false,
        replace: true,
    });
});

const allPicked = computed(
    () => props.semuaId.length > 0 && picked.value.size === props.semuaId.length
);

/** Peserta yang ditampilkan di pratinjau — bisa dipilih dengan mengklik barisnya. */
const previewId = ref(baris.value[0]?.id ?? null);

const preview = computed(() => {
    const dipilih = baris.value.find((r) => r.id === previewId.value);

    return dipilih ?? baris.value[0] ?? null;
});

const page = usePage();
const eventName = computed(() => page.props.event?.name ?? 'Gong Fun Run 2026');

function toggle(id) {
    const next = new Set(picked.value);
    next.has(id) ? next.delete(id) : next.add(id);
    picked.value = next;
}

/** Klik baris melakukan dua hal: pindahkan pratinjau, lalu ubah pilihannya. */
function pilihBaris(id) {
    previewId.value = id;
    toggle(id);
}

/** Mengenai seluruh peserta yang cocok filter, bukan hanya halaman yang terbuka. */
function toggleAll() {
    picked.value = allPicked.value ? new Set() : new Set(props.semuaId);
}

function print() {
    if (!picked.value.size) return;

    // Lembar cetak dibuka di tab baru supaya panitia tidak kehilangan pilihannya.
    window.open(`/panitia/cetak-bib/lembar?ids=${[...picked.value].join(',')}`, '_blank', 'noopener');
}
</script>

<template>
    <Head title="Cetak Nomor BIB" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Cetak Nomor BIB"
        lede="Pilih peserta yang nomor BIB-nya mau dicetak. Hanya pendaftaran lunas yang sudah punya nomor yang muncul di sini."
    >
        <template #actions>
            <button type="button" class="btn btn--sm" :disabled="!picked.size" @click="print">
                🖨 Cetak {{ picked.size }} BIB
            </button>
        </template>

        <!-- Pratinjau: wujud persis nomor BIB yang akan keluar dari printer. -->
        <div v-if="preview" class="panel" style="margin-bottom:20px">
            <div class="panel-head-row">
                <div>
                    <h2 class="panel-title">Pratinjau Nomor BIB</h2>
                    <p class="panel-sub">
                        Klik baris peserta di bawah untuk mengganti isi pratinjau.
                        Hasil cetak persis seperti ini, dua lembar per halaman A4.
                    </p>
                </div>
                <span class="pv-tag mono">Skala 45%</span>
            </div>

            <div class="pv-wrap">
                <article class="pv-bib">
                    <span class="pv-pin tl"></span><span class="pv-pin tr"></span>
                    <span class="pv-pin bl"></span><span class="pv-pin br"></span>

                    <div class="pv-top">
                        <img class="pv-logo" src="/images/logo-ika.jpeg" alt="" />
                        <span class="pv-ev">{{ eventName }}</span>
                        <span class="pv-cat" :class="preview.category === '10K' ? 'is-ten' : ''">{{ preview.category }}</span>
                    </div>

                    <div class="pv-num">{{ preview.bib_number }}</div>
                    <div class="pv-name">{{ preview.name }}</div>

                    <div class="pv-foot">
                        <span class="mono">{{ preview.code }}</span>
                        <span class="mono">{{ preview.city }}</span>
                        <span class="mono">Jersey {{ preview.jersey_size }}</span>
                        <span v-if="preview.blood_type" class="mono">Gol. {{ preview.blood_type }}</span>
                    </div>

                    <div class="pv-ice">
                        Darurat: {{ preview.emergency_name }} · {{ preview.emergency_phone }}
                    </div>
                </article>

                <ul class="pv-info">
                    <li><b>Ukuran cetak</b>128 mm tinggi, dua BIB per halaman A4</li>
                    <li><b>Yang ikut tercetak</b>Logo IKA, nomor, nama, kode, kota, ukuran jersey</li>
                    <li><b>Kontak darurat</b>Tercetak di kaki BIB — dipakai tim medis kalau terjadi sesuatu di rute</li>
                    <li><b>Bilah tombol</b>Tidak ikut tercetak, begitu juga URL dan nomor halaman browser</li>
                </ul>
            </div>
        </div>

        <div class="panel panel--pop">
            <div class="bib-bar">
                <div class="field" style="margin:0;min-width:180px">
                    <label for="category">Kategori</label>
                    <select id="category" v-model="category" class="select">
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.distance_label }}</option>
                    </select>
                </div>

                <div class="bib-count">
                    <span class="mono">{{ picked.size }}</span> dari {{ semuaId.length }} terpilih
                </div>

                <button
                    v-if="semuaId.length" type="button"
                    class="btn btn--ghost btn--sm" @click="toggleAll"
                >
                    {{ allPicked ? 'Kosongkan pilihan' : `Pilih semua ${semuaId.length}` }}
                </button>
            </div>

            <div v-if="baris.length" class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th style="width:44px"></th>
                            <th>BIB</th>
                            <th>Nama Peserta</th>
                            <th>Kategori</th>
                            <th>Kota</th>
                            <th>Jersey</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in baris" :key="r.id"
                            :class="{ 'is-picked': picked.has(r.id), 'is-preview': previewId === r.id }"
                            @click="pilihBaris(r.id)"
                        >
                            <td>
                                <input
                                    type="checkbox" class="bib-check"
                                    :checked="picked.has(r.id)"
                                    :aria-label="`Pilih ${r.name}`"
                                    @click.stop="toggle(r.id)"
                                />
                            </td>
                            <td><span class="badge badge--bib">{{ r.bib_number }}</span></td>
                            <td class="strong">{{ r.name }}</td>
                            <td>{{ r.category }}</td>
                            <td>{{ r.city }}</td>
                            <td class="mono">{{ r.jersey_size }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="empty">
                <div class="big">Belum ada BIB yang bisa dicetak</div>
                <p>Nomor BIB terbit otomatis setelah panitia menyetujui pembayaran peserta.</p>
            </div>

            <!-- Pilihan disimpan di komponen ini, jadi berpindah halaman tidak
                 boleh membangun ulang halamannya dari nol. -->
            <Paginasi :data="registrations" label="peserta" preserve-state />
        </div>
    </PanelLayout>
</template>

<style scoped>
.bib-bar {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
    padding-bottom: 16px;
    margin-bottom: 4px;
    border-bottom: 1px solid var(--edge);
}

.bib-count { font-size: .88rem; color: var(--txt-soft); margin-right: auto; padding-bottom: .5rem; }
.bib-count .mono { font-weight: 700; color: var(--txt); }
.bib-check { width: 24px; height: 24px; accent-color: var(--flame); cursor: pointer; }
.data tbody tr { cursor: pointer; }
.data tbody tr.is-picked { background: #FFF6F2; }
.data tbody tr.is-picked:hover { background: #FFEFE8; }
.data tbody tr.is-preview td:first-child { box-shadow: inset 3px 0 0 var(--cobalt); }

/* ── Pratinjau BIB ─────────────────────────────────────────────────────── */
.pv-tag {
  flex: none; font-size: .62rem; letter-spacing: .08em; text-transform: uppercase;
  color: var(--txt-soft); background: var(--surface-sunk);
  border: 1px solid var(--edge); border-radius: 20px; padding: .3rem .7rem;
}

.pv-wrap { display: grid; grid-template-columns: 300px 1fr; gap: 26px; align-items: start; }

/* Ukuran ditahan proporsional dengan cetakan aslinya (128 mm tinggi). */
.pv-bib {
  position: relative;
  background: #fff;
  border: 2px solid #17131F;
  border-radius: 12px;
  padding: 16px 18px 12px;
  height: 218px;
  display: flex;
  flex-direction: column;
  text-align: center;
}

.pv-pin { position: absolute; width: 9px; height: 9px; border: 1.5px solid #17131F; border-radius: 50%; background: var(--shell-bg); }
.pv-pin.tl { top: 9px; left: 10px; }
.pv-pin.tr { top: 9px; right: 10px; }
.pv-pin.bl { bottom: 9px; left: 10px; }
.pv-pin.br { bottom: 9px; right: 10px; }

.pv-top { display: flex; align-items: center; justify-content: center; gap: 8px; padding-bottom: 7px; border-bottom: 1.5px dashed #17131F; }
.pv-logo { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; }
.pv-ev { font-family: 'Space Mono'; font-size: .5rem; letter-spacing: .12em; text-transform: uppercase; color: #3A3348; }
.pv-cat { font-family: 'Space Mono'; font-size: .48rem; font-weight: 700; letter-spacing: .08em; padding: .12rem .4rem; border-radius: 20px; background: #0FB07E; color: #fff; }
.pv-cat.is-ten { background: #2C4CFF; }

.pv-num {
  flex: 1; display: flex; align-items: center; justify-content: center;
  font-family: 'Space Mono'; font-weight: 700; font-size: 2.6rem;
  line-height: 1; letter-spacing: .03em; color: #17131F;
}

.pv-name { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.1rem; line-height: 1; text-transform: uppercase; padding-bottom: 7px; color: #17131F; }
.pv-foot { display: flex; justify-content: center; flex-wrap: wrap; gap: 3px 9px; font-size: .5rem; color: #3A3348; padding-top: 7px; border-top: 1.5px dashed #17131F; }
.pv-ice { font-size: .5rem; color: #6B6478; margin-top: 5px; }

.pv-info { list-style: none; display: flex; flex-direction: column; gap: 12px; }
.pv-info li { display: flex; flex-direction: column; font-size: .82rem; color: var(--txt-soft); padding-left: 14px; border-left: 2px solid var(--edge); }
.pv-info b { font-size: .86rem; color: var(--txt); margin-bottom: 2px; }

@media (max-width: 820px) {
  .pv-wrap { grid-template-columns: 1fr; }
}
</style>
