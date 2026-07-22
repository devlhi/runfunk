<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import { rupiah } from '../../lib/format';

const props = defineProps({
    stats: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    pendingQueue: { type: Array, default: () => [] },
    trend: { type: Array, default: () => [] },
    event: { type: Object, default: () => ({}) },
});

function persen(bagian, total) {
    if (!total) return 0;

    return Math.min(100, Math.round((bagian / total) * 100));
}

/** Skala grafik dibuat dari nilai tertinggi, minimal 1 supaya tidak bagi nol. */
const puncakTren = computed(() => Math.max(1, ...props.trend.map((t) => t.jumlah)));
const totalTren = computed(() => props.trend.reduce((a, t) => a + t.jumlah, 0));

const isiKuota = computed(() => persen(props.stats.quota_taken, props.stats.quota_total));

/** Berapa persen pendaftaran yang sudah benar-benar lunas. */
const rasioLunas = computed(() => persen(props.stats.confirmed, props.stats.total));
</script>

<template>
    <Head title="Dashboard Panitia" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Dashboard"
        lede="Ringkas keadaan acara hari ini: siapa yang perlu diverifikasi, seberapa penuh kuotanya, dan berapa dana yang sudah masuk."
    >
        <template #actions>
            <a class="btn btn--ghost btn--sm" href="/panitia/pendaftaran/ekspor?status=confirmed">Ekspor CSV</a>
        </template>

        <!-- Bilah hitung mundur + kemajuan kuota -->
        <section class="hero-strip">
            <div class="hs-count">
                <span class="hs-label">Menuju hari event</span>
                <span class="hs-num">{{ event.days_left ?? '—' }}</span>
                <span class="hs-unit">hari lagi</span>
            </div>

            <div class="hs-bar">
                <div class="hs-bar-top">
                    <span>Kuota terisi</span>
                    <b>{{ stats.quota_taken }} / {{ stats.quota_total }}</b>
                </div>
                <div class="hs-track">
                    <i :style="{ width: isiKuota + '%' }"></i>
                </div>
                <div class="hs-bar-foot">
                    <span>{{ isiKuota }}% terisi</span>
                    <span>{{ stats.quota_total - stats.quota_taken }} slot tersisa</span>
                </div>
            </div>

            <div class="hs-today">
                <span class="hs-label">Masuk hari ini</span>
                <span class="hs-today-num">+{{ stats.today ?? 0 }}</span>
                <span class="hs-unit">pendaftar baru</span>
            </div>
        </section>

        <!-- Kartu yang menuntut tindakan didahulukan -->
        <div class="grid grid-4" style="margin-bottom:20px">
            <Link
                href="/panitia/pendaftaran?status=waiting_verification"
                class="stat-tile stat-link"
                :class="stats.waiting ? 'stat-tile--flame' : ''"
            >
                <div class="k">Menunggu Verifikasi</div>
                <div class="v">{{ stats.waiting }}</div>
                <div class="foot">{{ stats.waiting ? 'Perlu dicek sekarang →' : 'Semua sudah beres' }}</div>
            </Link>

            <div class="stat-tile stat-tile--cobalt">
                <div class="k">Lunas &amp; Terverifikasi</div>
                <div class="v">{{ stats.confirmed }}</div>
                <div class="foot">{{ rasioLunas }}% dari total pendaftaran</div>
            </div>

            <div class="stat-tile stat-tile--ink">
                <div class="k">Total Pendaftaran</div>
                <div class="v">{{ stats.total }}</div>
                <div class="foot">Termasuk yang belum bayar</div>
            </div>

            <div class="stat-tile stat-tile--marigold">
                <div class="k">Dana Terkumpul</div>
                <div class="v" style="font-size:1.9rem">{{ rupiah(stats.revenue) }}</div>
                <div class="foot">Dari pendaftaran lunas</div>
            </div>
        </div>

        <div class="grid grid-side">
            <div>
                <!-- Grafik tren -->
                <div class="panel">
                    <div class="panel-head-row">
                        <div>
                            <h2 class="panel-title">Pendaftaran 14 Hari Terakhir</h2>
                            <p class="panel-sub">Total {{ totalTren }} pendaftaran masuk dalam dua pekan ini.</p>
                        </div>
                        <span class="peak mono">Tertinggi {{ puncakTren }}/hari</span>
                    </div>

                    <div class="chart" role="img" :aria-label="`Grafik pendaftaran harian, total ${totalTren}`">
                        <div v-for="t in trend" :key="t.tanggal" class="bar-col">
                            <span class="bar-val" :class="{ 'is-zero': !t.jumlah }">{{ t.jumlah }}</span>
                            <div class="bar-track">
                                <i
                                    class="bar"
                                    :class="{ 'is-empty': !t.jumlah }"
                                    :style="{ height: Math.max(3, persen(t.jumlah, puncakTren)) + '%' }"
                                ></i>
                            </div>
                            <span class="bar-day">{{ t.hari }}</span>
                            <span class="bar-date mono">{{ t.tanggal }}</span>
                        </div>
                    </div>
                </div>

                <!-- Antrean verifikasi -->
                <div class="panel panel--pop">
                    <div class="panel-head-row">
                        <div>
                            <h2 class="panel-title">Antrean Verifikasi</h2>
                            <p class="panel-sub">Diurutkan dari yang paling lama menunggu.</p>
                        </div>
                        <Link v-if="pendingQueue.length" class="btn btn--ghost btn--sm" href="/panitia/pendaftaran?status=waiting_verification">
                            Lihat semua
                        </Link>
                    </div>

                    <div v-if="pendingQueue.length" class="table-scroll">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Kode</th><th>Peserta</th><th>Kat.</th>
                                    <th>Pengirim</th><th>Nominal</th><th>Menunggu</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in pendingQueue" :key="row.id">
                                    <td class="mono" style="font-size:.8rem">{{ row.code }}</td>
                                    <td class="strong">{{ row.participant_name }}</td>
                                    <td>{{ row.category }}</td>
                                    <td>{{ row.sender_name ?? '—' }}</td>
                                    <td class="mono">{{ rupiah(row.amount) }}</td>
                                    <td class="muted">{{ row.submitted_at }}</td>
                                    <td><Link class="btn btn--sm" :href="`/panitia/pendaftaran/${row.id}`">Periksa</Link></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="empty">
                        <div class="big">Antrean kosong</div>
                        <p>Semua bukti pembayaran yang masuk sudah diverifikasi. Kerja bagus!</p>
                    </div>
                </div>
            </div>

            <aside>
                <!-- Kuota per kategori -->
                <div class="panel">
                    <h2 class="panel-title">Kuota Kategori</h2>
                    <p class="panel-sub">Slot terpakai termasuk yang belum lunas.</p>

                    <div v-for="cat in categories" :key="cat.id" class="quota-row">
                        <div class="quota-top">
                            <span class="quota-name">{{ cat.name }}</span>
                            <span class="mono quota-num">{{ persen(cat.taken, cat.quota) }}%</span>
                        </div>
                        <div class="meter" :class="cat.distance_label === '10K' ? 'meter--cobalt' : ''">
                            <i :style="{ width: persen(cat.taken, cat.quota) + '%' }"></i>
                        </div>
                        <div class="quota-foot">
                            <b>{{ cat.taken }}</b> dari {{ cat.quota }} slot ·
                            {{ cat.confirmed }} lunas · {{ rupiah(cat.price) }}
                        </div>
                    </div>
                </div>

                <!-- Pintasan -->
                <div class="panel">
                    <h2 class="panel-title">Pintasan</h2>
                    <p class="panel-sub">Tugas yang paling sering dikerjakan.</p>

                    <Link href="/panitia/cetak-bib" class="shortcut">
                        <span class="sc-ic">🖨</span>
                        <span><b>Cetak Nomor BIB</b>Untuk peserta yang sudah lunas</span>
                    </Link>
                    <Link href="/panitia/kategori" class="shortcut">
                        <span class="sc-ic">🏷</span>
                        <span><b>Kategori &amp; Kuota</b>Ubah biaya dan jumlah slot</span>
                    </Link>
                    <Link href="/panitia/sponsor" class="shortcut">
                        <span class="sc-ic">⭐</span>
                        <span><b>Data Sponsor</b>Kelola tampilan di landing page</span>
                    </Link>
                </div>

                <!-- Status lain -->
                <div class="panel">
                    <h2 class="panel-title">Status Lain</h2>
                    <div class="pay-row"><span class="k">Belum bayar</span><span class="v">{{ stats.pending_payment }}</span></div>
                    <div class="pay-row"><span class="k">Bukti ditolak</span><span class="v">{{ stats.rejected }}</span></div>
                    <div class="pay-row"><span class="k">Dibatalkan</span><span class="v">{{ stats.cancelled ?? 0 }}</span></div>
                    <div class="pay-row"><span class="k">Bukti masuk hari ini</span><span class="v">{{ stats.proofs_today }}</span></div>
                </div>
            </aside>
        </div>
    </PanelLayout>
</template>

<style scoped>
/* ── Bilah hitung mundur ───────────────────────────────────────────────── */
.hero-strip {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 28px;
    align-items: center;
    padding: 22px 26px;
    margin-bottom: 20px;
    border-radius: 14px;
    color: #EDF3F1;
    background: linear-gradient(115deg, #0A5C43 0%, #084E5C 52%, #0F2266 100%);
    position: relative;
    overflow: hidden;
}

.hero-strip::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: repeating-linear-gradient(115deg, transparent 0 46px, rgba(255, 255, 255, .04) 46px 48px);
}

.hs-label {
    display: block;
    font-family: 'Space Mono', monospace;
    font-size: .6rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, .72);
}

.hs-num {
    display: block;
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 3.4rem;
    line-height: .95;
    color: #fff;
}

.hs-unit { display: block; font-size: .78rem; color: rgba(255, 255, 255, .8); }
.hs-count, .hs-today { flex: none; position: relative; z-index: 2; }
.hs-today { text-align: right; }

.hs-today-num {
    display: block;
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900;
    font-size: 2.4rem;
    line-height: 1;
    color: var(--marigold);
}

.hs-bar { position: relative; z-index: 2; }
.hs-bar-top, .hs-bar-foot { display: flex; justify-content: space-between; gap: 12px; }

.hs-bar-top {
    font-size: .82rem;
    color: rgba(255, 255, 255, .85);
    margin-bottom: 8px;
}

.hs-bar-top b { font-family: 'Space Mono', monospace; font-size: .84rem; color: #fff; }

.hs-track {
    height: 10px;
    border-radius: 20px;
    background: rgba(255, 255, 255, .16);
    overflow: hidden;
}

.hs-track i {
    display: block;
    height: 100%;
    border-radius: 20px;
    background: linear-gradient(90deg, var(--marigold), var(--flame));
    transition: width .5s ease;
}

.hs-bar-foot {
    margin-top: 7px;
    font-family: 'Space Mono', monospace;
    font-size: .64rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, .62);
}

/* ── Ubin statistik yang bisa diklik ───────────────────────────────────── */
.stat-link { display: block; transition: transform .14s ease, box-shadow .14s ease; }
.stat-link:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(23, 19, 31, .14); }

/* ── Grafik batang ─────────────────────────────────────────────────────── */
.panel-head-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.panel-head-row .panel-sub { margin-bottom: 0; }

.peak {
    flex: none;
    font-size: .64rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--txt-soft);
    background: var(--surface-sunk);
    border: 1px solid var(--edge);
    border-radius: 20px;
    padding: .3rem .7rem;
}

.chart { display: flex; align-items: flex-end; gap: 6px; }
.bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px; min-width: 0; }
.bar-val { font-family: 'Space Mono', monospace; font-size: .68rem; font-weight: 700; color: var(--txt); }
.bar-val.is-zero { color: var(--txt-dim); }
.bar-track { width: 100%; height: 108px; display: flex; align-items: flex-end; }

.bar {
    display: block;
    width: 100%;
    border-radius: 5px 5px 2px 2px;
    background: linear-gradient(180deg, var(--flame), #FF7A45);
    animation: barGrow .5s ease-out backwards;
}

.bar.is-empty { background: var(--edge); }

@keyframes barGrow {
    from { height: 0 !important; opacity: .4; }
}

.bar-day { font-size: .64rem; color: var(--txt-soft); text-transform: uppercase; }
.bar-date { font-size: .56rem; color: var(--txt-dim); white-space: nowrap; }

/* ── Kuota ─────────────────────────────────────────────────────────────── */
.quota-row { padding: 14px 0; border-bottom: 1px solid var(--edge); }
.quota-row:last-child { border-bottom: none; }
.quota-top { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.quota-name { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.25rem; text-transform: uppercase; }
.quota-num { font-size: .8rem; font-weight: 700; }
.quota-foot { font-size: .78rem; color: var(--txt-soft); margin-top: 8px; }
.quota-foot b { color: var(--txt); }

/* ── Pintasan ──────────────────────────────────────────────────────────── */
.shortcut {
    display: flex;
    align-items: center;
    gap: .8rem;
    padding: .7rem .8rem;
    border-radius: 10px;
    border: 1px solid var(--edge);
    margin-bottom: 8px;
    transition: background .12s ease, border-color .12s ease, transform .12s ease;
}

.shortcut:last-child { margin-bottom: 0; }
.shortcut:hover { background: var(--surface-sunk); border-color: var(--edge-strong); transform: translateX(3px); }
.sc-ic { flex: none; font-size: 1.2rem; }
.shortcut span span, .shortcut > span:last-child { display: flex; flex-direction: column; font-size: .76rem; color: var(--txt-soft); }
.shortcut b { font-size: .9rem; color: var(--txt); }

@media (max-width: 900px) {
    .hero-strip { grid-template-columns: 1fr; gap: 20px; text-align: left; }
    .hs-today { text-align: left; }
    .bar-date { display: none; }
}

@media (max-width: 640px) {
    .chart { gap: 3px; }
    .bar-val, .bar-day { font-size: .58rem; }
    .bar-track { height: 84px; }
}
</style>
