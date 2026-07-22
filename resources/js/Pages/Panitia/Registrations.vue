<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import KonfirmasiManual from '../../Components/KonfirmasiManual.vue';
import KartuBib from '../../Components/KartuBib.vue';
import Paginasi from '../../Components/Paginasi.vue';
import { rupiah, statusBadgeClass } from '../../lib/format';

const props = defineProps({
    registrations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const category = ref(props.filters.category ?? '');

let debounce = null;

function apply() {
    router.get(
        '/panitia/pendaftaran',
        { search: search.value, status: status.value, category: category.value },
        { preserveState: true, replace: true }
    );
}

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(apply, 350);
});

watch([status, category], apply);

function reset() {
    search.value = '';
    status.value = '';
    category.value = '';
}

function exportUrl() {
    return status.value
        ? `/panitia/pendaftaran/ekspor?status=${status.value}`
        : '/panitia/pendaftaran/ekspor';
}

/* ------------------------------------------------ Konfirmasi pembayaran */

const konfirmasiManual = ref(null);
const memproses = ref(null);

/**
 * Menyetujui bukti yang sudah diunggah, langsung dari baris tabel. Panitia
 * sudah melihat nominal dan statusnya di sini, jadi untuk kasus yang jelas
 * tidak perlu membuka halaman detail satu per satu.
 */
function setujui(row) {
    if (memproses.value) return;

    if (!window.confirm(
        `Setujui pembayaran ${row.participant_name} (${row.code}) sebesar ${rupiah(row.amount)}?\n\n`
        + 'Nomor BIB akan langsung terbit dan peserta otomatis dikabari.'
    )) return;

    memproses.value = row.id;

    router.post(`/panitia/pembayaran/${row.pending_payment_id}/setujui`, {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (memproses.value = null),
    });
}

/* ----------------------------------------------------------- Pratinjau BIB */

const pratinjauBib = ref(null);

/** Bentuk data yang diharapkan KartuBib — sama persis dengan lembar cetak. */
function bukaPratinjau(row) {
    pratinjauBib.value = {
        bib_number: row.bib_number,
        name: row.participant_name,
        code: row.code,
        category: row.category,
        city: row.city,
        jersey_size: row.jersey_size,
        blood_type: row.blood_type,
        emergency_name: row.emergency_name,
        emergency_phone: row.emergency_phone,
        id: row.id,
        // Diminta saat dibuka, bukan ikut di tiap baris tabel — supaya
        // pratinjaunya tetap sama persis dengan hasil cetak.
        qr: `/panitia/pendaftaran/${row.id}/qr`,
    };
}
</script>

<template>
    <Head title="Data Peserta" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Data Peserta"
        lede="Cari berdasarkan nama, kode pendaftaran, nomor BIB, email, atau nomor WhatsApp."
    >
        <template #actions>
            <a class="btn btn--ghost btn--sm" :href="exportUrl()">Ekspor CSV</a>
        </template>

        <!-- Filter -->
        <div class="panel" style="margin-bottom:20px">
            <div class="filters">
                <div class="field" style="margin:0;flex:2;min-width:220px">
                    <label for="search">Cari</label>
                    <input
                        id="search" v-model="search" type="search" class="input"
                        placeholder="Nama, kode, BIB, email, WhatsApp…"
                    />
                </div>
                <div class="field" style="margin:0;flex:1;min-width:170px">
                    <label for="status">Status</label>
                    <select id="status" v-model="status" class="select">
                        <option value="">Semua status</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="field" style="margin:0;flex:1;min-width:150px">
                    <label for="category">Kategori</label>
                    <select id="category" v-model="category" class="select">
                        <option value="">Semua kategori</option>
                        <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.distance_label }}</option>
                    </select>
                </div>
                <button type="button" class="btn btn--ghost btn--sm" style="align-self:flex-end" @click="reset">
                    Reset
                </button>
            </div>
        </div>

        <!-- Tabel -->
        <div v-if="registrations.data.length" class="table-scroll">
            <table class="data">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>BIB</th>
                        <th>Peserta</th>
                        <th>Kat.</th>
                        <th>Jersey</th>
                        <th>Biaya</th>
                        <th>Status</th>
                        <th>Daftar</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in registrations.data" :key="row.id">
                        <td class="mono" style="font-size:.8rem">{{ row.code }}</td>
                        <td>
                            <span v-if="row.bib_number" class="badge badge--bib">{{ row.bib_number }}</span>
                            <span v-else class="muted">—</span>
                        </td>
                        <td>
                            <div class="strong">{{ row.participant_name }}</div>
                            <div class="muted">{{ row.participant_phone }}</div>
                        </td>
                        <td>{{ row.category }}</td>
                        <td>{{ row.jersey_size }}</td>
                        <td class="mono">{{ rupiah(row.amount) }}</td>
                        <td>
                            <span :class="statusBadgeClass(row.status)">{{ row.status_label }}</span>
                            <div v-if="row.status === 'waiting_verification'" class="muted">Bukti menunggu dicek</div>
                        </td>
                        <td class="muted">{{ row.created_at }}</td>
                        <td>
                            <div class="row-actions">
                                <button
                                    v-if="row.pending_payment_id"
                                    type="button" class="btn btn--mint btn--sm"
                                    :disabled="memproses === row.id"
                                    @click="setujui(row)"
                                >
                                    {{ memproses === row.id ? '…' : '✔ Setujui' }}
                                </button>
                                <button
                                    v-else-if="row.can_confirm_manual"
                                    type="button" class="btn btn--sm"
                                    @click="konfirmasiManual = row"
                                >
                                    ✔ Konfirmasi
                                </button>

                                <button
                                    v-if="row.bib_number"
                                    type="button" class="btn btn--ghost btn--sm"
                                    title="Lihat pratinjau kartu BIB sebelum dicetak"
                                    @click="bukaPratinjau(row)"
                                >
                                    🎫 BIB
                                </button>

                                <Link
                                    class="btn btn--ghost btn--sm"
                                    :href="`/panitia/pendaftaran/${row.id}`"
                                >
                                    {{ row.status === 'waiting_verification' ? 'Periksa' : 'Detail' }}
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="panel">
            <div class="empty">
                <div class="big">Tidak ada data</div>
                <p>Coba ubah kata kunci atau reset filter di atas.</p>
            </div>
        </div>

        <Paginasi :data="registrations" label="pendaftaran" />

        <KonfirmasiManual :target="konfirmasiManual" @close="konfirmasiManual = null" />

        <!-- Pratinjau BIB: kartu yang sama persis dengan yang keluar di lembar cetak. -->
        <Teleport to="body">
            <div v-if="pratinjauBib" class="modal-veil" @click.self="pratinjauBib = null">
                <div class="modal modal--bib" role="dialog" aria-modal="true" aria-labelledby="bib-judul">
                    <h3 id="bib-judul">Pratinjau Nomor BIB</h3>
                    <p class="modal-sub">
                        Tampilan ini sama dengan hasil cetaknya. Periksa ejaan nama dan data darurat
                        sebelum kartunya dicetak.
                    </p>

                    <KartuBib :bib="pratinjauBib" pratinjau />

                    <div class="modal-actions">
                        <button type="button" class="btn btn--ghost btn--sm" @click="pratinjauBib = null">
                            Tutup
                        </button>
                        <a
                            class="btn btn--sm"
                            :href="`/panitia/cetak-bib/lembar?ids=${pratinjauBib.id}`"
                            target="_blank" rel="noopener"
                        >
                            🖨 Cetak BIB Ini
                        </a>
                    </div>
                </div>
            </div>
        </Teleport>
    </PanelLayout>
</template>

<style scoped>
.filters { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
.row-actions { display: flex; gap: 6px; flex-wrap: nowrap; }

/* Sedikit lebih lebar dari modal biasa supaya angka BIB tidak terasa sesak. */
.modal--bib { width: min(560px, 100%); }

/* Tombol pratinjau BIB berdiri sendiri di kolom aksi.
   Versi pertama menjadikan lencana BIB itu sendiri sebagai tombol, dengan ikon
   kaca pembesar yang baru muncul saat kursor menyentuh — praktis tak terlihat,
   dan memang tidak ditemukan. */
</style>
