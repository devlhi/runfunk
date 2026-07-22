<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import Paginasi from '../../Components/Paginasi.vue';
import PemindaiQr from '../../Components/PemindaiQr.vue';

const props = defineProps({
    peserta: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});

const cari = ref(props.filters.cari ?? '');
const saring = ref(props.filters.saring ?? 'semua');

let debounce = null;

function muat() {
    router.get('/panitia/kehadiran',
        { cari: cari.value, saring: saring.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

watch(cari, () => {
    clearTimeout(debounce);
    debounce = setTimeout(muat, 300);
});

watch(saring, muat);

function toggle(row, jenis) {
    const sudah = jenis === 'racepack' ? row.racepack_at : row.checkin_at;

    router.post(`/panitia/kehadiran/${row.id}`, { jenis, nilai: !sudah }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function persen(bagian) {
    if (!props.stats.lunas) return 0;

    return Math.round((bagian / props.stats.lunas) * 100);
}

/* --------------------------------------------------- Pemindai QR */

const modePindai = ref(false);
const jenisPindai = ref('racepack');
const riwayat = ref([]);
const memproses = ref(false);

/**
 * Riwayat dibuat menumpuk ke atas dan tetap terlihat, bukan sekadar notifikasi
 * sekilas: panitia perlu bisa menoleh balik memastikan orang tadi benar-benar
 * tercatat, tanpa memindai ulang.
 */
function catat(item) {
    riwayat.value = [{ ...item, kunci: Date.now() + Math.random() }, ...riwayat.value].slice(0, 25);
}

async function terbaca(kode) {
    if (memproses.value) return;
    memproses.value = true;

    try {
        const res = await fetch('/panitia/kehadiran/pindai/qr', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    (document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] ?? ''
                ),
            },
            body: JSON.stringify({ kode, jenis: jenisPindai.value }),
        });

        const data = await res.json();

        catat({
            ok: data.ok === true,
            ulangan: data.ulangan === true,
            pesan: data.pesan ?? 'Gagal memproses.',
            peserta: data.peserta ?? null,
        });

        if (data.ok) segarkanAngka();
    } catch {
        // Sinyal putus di lapangan itu wajar; jangan sampai layarnya diam saja.
        catat({ ok: false, ulangan: false, pesan: 'Tidak ada koneksi. Coba lagi sebentar.', peserta: null });
    } finally {
        memproses.value = false;
    }
}

/** Angka ringkasan diambil ulang tanpa mengganggu kamera yang sedang menyala. */
function segarkanAngka() {
    router.reload({ only: ['stats', 'peserta'], preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Race Pack & Kehadiran" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Race Pack & Kehadiran"
        lede="Cari peserta lewat nomor BIB atau nama, lalu tandai. Dipakai saat pembagian race pack H-2 dan registrasi ulang pagi hari-H."
    >
        <div class="grid grid-3" style="margin-bottom:20px">
            <div class="stat-tile stat-tile--ink">
                <div class="k">Peserta Lunas</div>
                <div class="v">{{ stats.lunas }}</div>
                <div class="foot">Berhak ambil race pack</div>
            </div>
            <div class="stat-tile stat-tile--cobalt">
                <div class="k">Race Pack Diambil</div>
                <div class="v">{{ stats.racepack }}</div>
                <div class="foot">{{ persen(stats.racepack) }}% dari peserta lunas</div>
            </div>
            <div class="stat-tile stat-tile--marigold">
                <div class="k">Hadir Hari-H</div>
                <div class="v">{{ stats.hadir }}</div>
                <div class="foot">{{ persen(stats.hadir) }}% sudah registrasi ulang</div>
            </div>
        </div>

        <!-- Pemindai QR: jalur cepat untuk antrean panjang. Pencarian manual di
             bawah tetap ada sebagai cadangan kalau kartunya rusak atau hilang. -->
        <div class="panel panel--pop" style="margin-bottom:20px">
            <div class="panel-head-row">
                <div>
                    <h2 class="panel-title">Pindai QR Peserta</h2>
                    <p class="panel-sub">
                        Arahkan kamera ke kode QR di nomor BIB. Jauh lebih cepat daripada
                        mengetik satu per satu saat antrean panjang.
                    </p>
                </div>
                <button
                    type="button" class="btn btn--sm"
                    :class="modePindai ? 'btn--ghost' : ''"
                    @click="modePindai = !modePindai"
                >
                    {{ modePindai ? 'Tutup Pemindai' : '📷 Buka Pemindai' }}
                </button>
            </div>

            <div v-if="modePindai" class="pindai-wrap">
                <div class="pindai-jenis">
                    <span class="pindai-label">Yang ditandai:</span>
                    <button
                        type="button" :class="{ 'is-on': jenisPindai === 'racepack' }"
                        @click="jenisPindai = 'racepack'"
                    >📦 Race Pack</button>
                    <button
                        type="button" :class="{ 'is-on': jenisPindai === 'checkin' }"
                        @click="jenisPindai = 'checkin'"
                    >✔ Kehadiran</button>
                </div>

                <PemindaiQr @terbaca="terbaca" />

                <ul v-if="riwayat.length" class="riwayat">
                    <li
                        v-for="r in riwayat" :key="r.kunci"
                        :class="{ 'is-gagal': !r.ok, 'is-ulang': r.ulangan }"
                    >
                        <span class="riwayat-ikon">{{ r.ok ? (r.ulangan ? '!' : '✔') : '✕' }}</span>
                        <span class="riwayat-isi">
                            <b v-if="r.peserta">{{ r.peserta.bib }} · {{ r.peserta.nama }}</b>
                            <b v-else>Tidak dikenali</b>
                            <span>
                                {{ r.pesan }}
                                <template v-if="r.peserta"> · Jersey {{ r.peserta.jersey }}</template>
                            </span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="panel panel--pop">
            <div class="cari-row">
                <div class="field" style="margin:0;flex:2;min-width:240px">
                    <label for="cari">Cari Peserta</label>
                    <input
                        id="cari" v-model="cari" type="search" class="input input--besar"
                        placeholder="Ketik nomor BIB, nama, atau kode…" autofocus
                    />
                </div>
                <div class="field" style="margin:0;flex:1;min-width:190px">
                    <label for="saring">Tampilkan</label>
                    <select id="saring" v-model="saring" class="select">
                        <option value="semua">Semua peserta lunas</option>
                        <option value="belum_racepack">Belum ambil race pack</option>
                        <option value="belum_hadir">Belum hadir</option>
                        <option value="sudah_hadir">Sudah hadir</option>
                    </select>
                </div>
            </div>

            <div v-if="peserta.data.length" class="daftar">
                <article v-for="row in peserta.data" :key="row.id" class="baris">
                    <div class="bib-box" :class="row.kategori === '10K' ? 'is-ten' : ''">
                        <span class="bib-num">{{ row.bib }}</span>
                        <span class="bib-cat">{{ row.kategori }}</span>
                    </div>

                    <div class="info">
                        <b>{{ row.nama }}</b>
                        <span class="mono">{{ row.kode }} · Jersey {{ row.jersey }} · {{ row.telepon }}</span>
                    </div>

                    <div class="aksi">
                        <button
                            type="button" class="tandai"
                            :class="{ 'is-on': row.racepack_at }"
                            @click="toggle(row, 'racepack')"
                        >
                            <span class="tk-ic">{{ row.racepack_at ? '✔' : '○' }}</span>
                            <span class="tk-txt">
                                <b>Race Pack</b>
                                <small>{{ row.racepack_at ?? 'Belum diambil' }}</small>
                            </span>
                        </button>

                        <button
                            type="button" class="tandai tandai--hadir"
                            :class="{ 'is-on': row.checkin_at }"
                            @click="toggle(row, 'checkin')"
                        >
                            <span class="tk-ic">{{ row.checkin_at ? '✔' : '○' }}</span>
                            <span class="tk-txt">
                                <b>Hadir</b>
                                <small>{{ row.checkin_at ?? 'Belum hadir' }}</small>
                            </span>
                        </button>
                    </div>
                </article>

                <p class="batas help">
                    Ketik nomor BIB di kotak pencarian untuk langsung menemukan orangnya.
                </p>
            </div>

            <div v-else class="empty">
                <div class="big">Tidak ada yang cocok</div>
                <p v-if="cari">Tidak ada peserta lunas dengan kata kunci “{{ cari }}”.</p>
                <p v-else>Nomor BIB terbit setelah panitia menyetujui pembayaran peserta.</p>
            </div>
        </div>

        <Paginasi :data="peserta" label="peserta" />
    </PanelLayout>
</template>

<style scoped>
/* ------------------------------------------------------ Pemindai QR */
.pindai-wrap { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--edge); }

.pindai-jenis { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
.pindai-label { font-size: .74rem; letter-spacing: .06em; text-transform: uppercase; color: var(--txt-soft); font-weight: 700; }
.pindai-jenis button {
    cursor: pointer; font: inherit; font-size: .84rem; font-weight: 700;
    padding: .45rem .9rem; border-radius: 8px;
    border: 1px solid var(--edge-strong); background: var(--surface); color: var(--txt);
}
.pindai-jenis button.is-on { background: var(--ink); color: #fff; border-color: var(--ink); }

.riwayat { list-style: none; margin: 16px 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.riwayat li {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    background: #E6F6EF; border: 1px solid #B6E4D2;
}
.riwayat li.is-ulang { background: #FFF4DF; border-color: #F0D9A8; }
.riwayat li.is-gagal { background: #FFE9E4; border-color: #F5C4B8; }

.riwayat-ikon {
    flex: none; width: 22px; height: 22px; border-radius: 50%;
    display: grid; place-items: center;
    font-size: .78rem; font-weight: 800; color: #fff; background: #0FB07E;
}
.riwayat li.is-ulang .riwayat-ikon { background: #C68A0A; }
.riwayat li.is-gagal .riwayat-ikon { background: #D7263D; }

.riwayat-isi { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.riwayat-isi b { font-size: .9rem; color: var(--txt); }
.riwayat-isi span { font-size: .78rem; color: var(--txt-soft); }

.cari-row { display: flex; gap: 16px; flex-wrap: wrap; padding-bottom: 18px; margin-bottom: 4px; border-bottom: 1px solid var(--edge); }
/* Kotak cari dibuat besar karena dipakai sambil berdiri di meja registrasi. */
.input--besar { font-size: 1.05rem; padding: .8rem 1rem; }

.daftar { display: flex; flex-direction: column; }
.baris { display: flex; align-items: center; gap: 16px; padding: 14px 0; border-bottom: 1px solid var(--edge); flex-wrap: wrap; }
.baris:last-of-type { border-bottom: none; }

.bib-box {
    flex: none; width: 74px; display: flex; flex-direction: column; align-items: center;
    padding: .45rem; border-radius: 10px; background: #E6F6EF; color: #07634A;
}
.bib-box.is-ten { background: #E4E8FF; color: #2438A8; }
.bib-num { font-family: 'Space Mono'; font-weight: 700; font-size: 1.15rem; line-height: 1; }
.bib-cat { font-family: 'Space Mono'; font-size: .58rem; letter-spacing: .1em; margin-top: 3px; }

.info { flex: 1; min-width: 160px; display: flex; flex-direction: column; gap: 2px; }
.info b { font-size: .98rem; }
.info span { font-size: .74rem; color: var(--txt-soft); }

.aksi { display: flex; gap: 10px; flex-wrap: wrap; }

.tandai {
    display: flex; align-items: center; gap: .6rem; cursor: pointer;
    padding: .5rem .9rem; min-height: 48px;
    border: 1.5px solid var(--edge-strong); border-radius: 10px;
    background: var(--surface); font-family: 'Archivo', sans-serif; text-align: left;
    transition: .13s;
}
.tandai:hover { border-color: var(--txt-soft); }
.tandai.is-on { background: #E6F6EF; border-color: var(--mint); }
.tandai--hadir.is-on { background: #FFF3DC; border-color: var(--marigold); }

.tk-ic { font-size: 1.05rem; color: var(--txt-dim); }
.tandai.is-on .tk-ic { color: var(--mint); }
.tandai--hadir.is-on .tk-ic { color: #B57A00; }
.tk-txt { display: flex; flex-direction: column; }
.tk-txt b { font-size: .82rem; }
.tk-txt small { font-size: .68rem; color: var(--txt-soft); }

.batas { padding-top: 14px; }

@media (max-width: 640px) {
    .aksi { width: 100%; }
    .tandai { flex: 1; }
}
</style>
