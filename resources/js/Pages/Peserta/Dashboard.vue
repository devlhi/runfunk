<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import { rupiah, statusBadgeClass } from '../../lib/format';

const props = defineProps({
    registrations: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    announcements: { type: Array, default: () => [] },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? 'Pelari');

const confirmed = computed(() => props.registrations.filter((r) => r.status === 'confirmed'));
const needsAction = computed(() =>
    props.registrations.filter((r) => ['pending_payment', 'rejected'].includes(r.status))
);

/** Kategori yang masih bisa diambil: belum penuh dan belum punya pendaftaran aktif. */
const availableCategories = computed(() => {
    const activeSlugs = props.registrations
        .filter((r) => ['pending_payment', 'waiting_verification', 'confirmed'].includes(r.status))
        .map((r) => r.category_slug);

    return props.categories.filter((c) => !c.is_sold_out && !activeSlugs.includes(c.slug));
});
</script>

<template>
    <Head title="Dashboard Peserta" />

    <PanelLayout
        crumb="Dashboard Peserta"
        :title="`Halo, ${userName}`"
        lede="Semua pendaftaran, status pembayaran, dan e-tiketmu ada di sini. Nomor BIB terbit otomatis setelah panitia memverifikasi pembayaran."
    >
        <template #actions>
            <Link v-if="availableCategories.length" class="btn btn--sm" href="/pendaftaran/baru">
                + Daftar Lomba
            </Link>
        </template>

        <!-- Pengumuman panitia, ditaruh paling atas supaya tidak terlewat. -->
        <section v-if="announcements.length" class="pengumuman">
            <article
                v-for="a in announcements" :key="a.id"
                class="peng-item" :class="{ 'is-penting': a.level === 'penting' }"
            >
                <span class="peng-ic">{{ a.level === 'penting' ? '!' : 'i' }}</span>
                <div class="peng-isi">
                    <div class="peng-head">
                        <b>{{ a.title }}</b>
                        <span class="mono">{{ a.created_at }}</span>
                    </div>
                    <p>{{ a.body }}</p>
                </div>
            </article>
        </section>

        <!-- Ringkasan -->
        <div class="grid grid-3" style="margin-bottom:28px">
            <div class="stat-tile stat-tile--ink">
                <div class="k">Total Pendaftaran</div>
                <div class="v">{{ registrations.length }}</div>
                <div class="foot">Termasuk yang dibatalkan</div>
            </div>
            <div class="stat-tile" :class="confirmed.length ? 'stat-tile--cobalt' : ''">
                <div class="k">Sudah Lunas</div>
                <div class="v">{{ confirmed.length }}</div>
                <div class="foot">Slot terkunci &amp; BIB terbit</div>
            </div>
            <div class="stat-tile" :class="needsAction.length ? 'stat-tile--flame' : ''">
                <div class="k">Perlu Tindakan</div>
                <div class="v">{{ needsAction.length }}</div>
                <div class="foot">Menunggu pembayaran atau unggah ulang</div>
            </div>
        </div>

        <!-- Daftar pendaftaran -->
        <div class="panel panel--pop">
            <h2 class="panel-title">Pendaftaranku</h2>
            <p class="panel-sub">Klik salah satu untuk melihat detail, instruksi bayar, atau e-tiket.</p>

            <div v-if="registrations.length" class="reg-list">
                <Link
                    v-for="reg in registrations"
                    :key="reg.id"
                    class="reg-card"
                    :href="`/pendaftaran/${reg.id}`"
                >
                    <div class="reg-dist">{{ reg.distance_label }}</div>

                    <div class="reg-body">
                        <div class="reg-top">
                            <span class="mono reg-code">{{ reg.code }}</span>
                            <span :class="statusBadgeClass(reg.status)">{{ reg.status_label }}</span>
                        </div>
                        <div class="reg-name">{{ reg.participant_name }}</div>
                        <div class="reg-meta">
                            {{ reg.category }} · Jersey {{ reg.jersey_size }} · {{ rupiah(reg.amount) }}
                            · Didaftarkan {{ reg.created_at }}
                        </div>
                        <p v-if="reg.status === 'rejected' && reg.panitia_note" class="reg-note">
                            Catatan panitia: {{ reg.panitia_note }}
                        </p>
                    </div>

                    <div class="reg-tail">
                        <template v-if="reg.bib_number">
                            <span class="badge badge--bib">BIB {{ reg.bib_number }}</span>
                        </template>
                        <template v-else-if="reg.can_upload_proof">
                            <span class="reg-action">Bayar sekarang →</span>
                        </template>
                        <template v-else>
                            <span class="reg-action muted-action">Lihat detail →</span>
                        </template>
                    </div>
                </Link>
            </div>

            <div v-else class="empty">
                <div class="big">Belum ada pendaftaran</div>
                <p style="margin-bottom:20px">Pilih kategori 5K atau 10K dan amankan slotmu sekarang.</p>
                <Link class="btn" href="/pendaftaran/baru">Daftar Lomba →</Link>
            </div>
        </div>

        <!-- Kategori yang masih terbuka -->
        <div v-if="availableCategories.length" class="panel" style="margin-top:20px">
            <h2 class="panel-title">Kategori yang masih terbuka</h2>
            <p class="panel-sub">Kamu boleh ikut lebih dari satu kategori selama kuotanya masih ada.</p>

            <div class="grid grid-2">
                <Link
                    v-for="cat in availableCategories"
                    :key="cat.slug"
                    class="pick-card"
                    :href="`/pendaftaran/baru?kategori=${cat.slug}`"
                >
                    <div class="pick-top">
                        <span class="pick-name">{{ cat.name }}</span>
                        <span class="pick-price">{{ rupiah(cat.price) }}</span>
                    </div>
                    <p class="pick-desc">{{ cat.tagline }}</p>
                    <p class="pick-desc mono" style="margin-top:8px">Sisa {{ cat.remaining }} slot</p>
                </Link>
            </div>
        </div>
    </PanelLayout>
</template>

<style scoped>
/* Pengumuman panitia */
.pengumuman { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }

.peng-item {
    display: flex;
    gap: 14px;
    padding: 16px 18px;
    border: 1px solid var(--edge-strong);
    border-left: 4px solid var(--cobalt);
    border-radius: 11px;
    background: var(--surface);
}

.peng-item.is-penting { border-left-color: #C81E1E; background: #FFF6F4; }

.peng-ic {
    flex: none;
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--cobalt);
    color: #fff;
    font-family: 'Space Mono', monospace;
    font-weight: 700;
    font-size: .8rem;
}

.peng-item.is-penting .peng-ic { background: #C81E1E; }
.peng-isi { min-width: 0; }
.peng-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 4px; }
.peng-head b { font-size: .96rem; }
.peng-head span { font-size: .68rem; color: var(--txt-dim); }
.peng-isi p { font-size: .87rem; color: var(--txt-soft); white-space: pre-line; line-height: 1.6; }

.reg-list { display: flex; flex-direction: column; gap: 14px; }

.reg-card {
    display: grid; grid-template-columns: 84px 1fr auto; gap: 20px; align-items: center;
    border: 2.5px solid var(--ink); border-radius: 16px; padding: 18px 20px;
    background: var(--paper); transition: transform .14s ease, box-shadow .14s ease;
}
.reg-card:hover { transform: translate(-3px, -3px); box-shadow: 6px 6px 0 var(--ink); }

.reg-dist {
    font-family: 'Space Mono'; font-weight: 700; font-size: 1.5rem;
    background: var(--ink); color: var(--paper); border-radius: 12px;
    padding: .7rem 0; text-align: center;
}
.reg-top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
.reg-code { font-size: .78rem; letter-spacing: .06em; color: var(--ink-soft); }
.reg-name { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 1.5rem; text-transform: uppercase; }
.reg-meta { font-size: .85rem; color: var(--ink-soft); margin-top: 2px; }
.reg-note {
    margin-top: 10px; font-size: .84rem; font-weight: 600; color: var(--danger);
    border-left: 3px solid var(--danger); padding-left: 10px;
}
.reg-tail { text-align: right; }
.reg-action { font-family: 'Space Mono'; font-size: .76rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--flame); white-space: nowrap; }
.reg-action.muted-action { color: var(--ink-soft); }

@media (max-width: 720px) {
    .reg-card { grid-template-columns: 64px 1fr; }
    .reg-tail { grid-column: 1 / -1; text-align: left; }
}
</style>
