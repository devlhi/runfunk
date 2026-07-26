<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../Components/FlashMessage.vue';

defineProps({
    title: { type: String, default: '' },
    crumb: { type: String, default: '' },
    lede: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isPanitia = computed(() => Boolean(user.value?.is_panitia));
const isDeveloper = computed(() => Boolean(user.value?.is_developer));

/** Menu yang hanya terlihat developer. */
const devNav = [
    { href: '/panitia/pengguna', label: 'Kelola Pengguna', icon: 'users' },
    { href: '/panitia/pengaturan', label: 'Pengaturan Acara', icon: 'cog' },
    { href: '/panitia/kartu-panitia', label: 'Cetak Kartu Panitia', icon: 'badge' },
    { href: '/panitia/pratinjau-email', label: 'Template Email', icon: 'mail' },
];
const waiting = computed(() => page.props.panitia?.waiting ?? 0);

const path = computed(() => new URL(page.url, 'http://x').pathname);

const initial = computed(() => (user.value?.name ?? '?').trim().charAt(0).toUpperCase());
// Diambil dari backend, bukan ditebak dari is_panitia — developer ikut lolos
// sebagai staf, jadi tebakan lama selalu melabelinya "Panitia".
const roleLabel = computed(() => user.value?.role_label ?? 'Peserta');

const mainNav = computed(() =>
    isPanitia.value
        ? [
            { href: '/panitia', label: 'Dashboard', icon: 'gauge', exact: true },
            { href: '/panitia/pendaftaran', label: 'Data Peserta', icon: 'list', badge: waiting.value },
            { href: '/panitia/kehadiran', label: 'Race Pack & Hadir', icon: 'check' },
            { href: '/panitia/kartu-panitia/validasi', label: 'Pindai Kartu Panitia', icon: 'badge' },
            { href: '/panitia/cetak-bib', label: 'Cetak Nomor BIB', icon: 'printer' },
            { href: '/panitia/hasil', label: 'Hasil Lomba', icon: 'trophy' },
            { href: '/panitia/cetak-sertifikat', label: 'Cetak Sertifikat', icon: 'printer' },
            { href: '/panitia/laporan', label: 'Rekap & Laporan', icon: 'chart' },
            { href: '/panitia/pengumuman', label: 'Pengumuman', icon: 'megaphone' },
            { href: '/panitia/berita', label: 'Kelola Berita', icon: 'news' },
            { href: '/panitia/kategori', label: 'Kategori & Kuota', icon: 'tag' },
            { href: '/panitia/sponsor', label: 'Data Sponsor', icon: 'star' },
        ]
        : [
            { href: '/dashboard', label: 'Dashboard', icon: 'gauge', exact: true },
            { href: '/pendaftaran/baru', label: 'Daftar Lomba', icon: 'plus' },
        ]
);

function isOn(item) {
    return item.exact ? path.value === item.href : path.value.startsWith(item.href);
}

const drawer = ref(false);
const menu = ref(false);
const menuRoot = ref(null);
const bell = ref(false);
const bellRoot = ref(null);

const feed = computed(() => page.props.panitia?.feed ?? []);
const unread = computed(() => page.props.panitia?.unread ?? 0);

/**
 * Membuka lonceng sekaligus menandainya sudah dibaca, supaya angka merahnya
 * hilang setelah panitia benar-benar melihat daftarnya.
 */
function toggleBell() {
    bell.value = !bell.value;
    menu.value = false;

    if (bell.value && unread.value > 0) {
        router.post('/panitia/notifikasi/tandai-dibaca', {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['panitia'],
        });
    }
}

// Pindah halaman menutup drawer maupun kedua menu.
watch(path, () => {
    drawer.value = false;
    menu.value = false;
    bell.value = false;
});

function onDocClick(e) {
    if (menu.value && menuRoot.value && !menuRoot.value.contains(e.target)) {
        menu.value = false;
    }

    if (bell.value && bellRoot.value && !bellRoot.value.contains(e.target)) {
        bell.value = false;
    }
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        menu.value = false;
        bell.value = false;
        drawer.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKeydown);
});

function logout() {
    menu.value = false;
    router.post('/keluar');
}
</script>

<template>
    <div class="panel-shell">
        <button v-if="drawer" class="rail-veil" aria-label="Tutup menu" @click="drawer = false"></button>

        <aside class="rail" :class="{ 'is-open': drawer }">
            <Link class="rail-head" href="/">
                <img class="rail-logo" src="/images/logo-ika.jpeg" alt="Logo IKA SMK Gotong Royong Gorontalo" />
                GONG<span style="color:var(--flame)">/</span>RUN
            </Link>

            <div class="rail-scroll">
                <div class="rail-group">{{ isPanitia ? 'Operasional' : 'Lomba Saya' }}</div>

                <Link
                    v-for="item in mainNav"
                    :key="item.href"
                    :href="item.href"
                    class="rail-link"
                    :class="{ 'is-on': isOn(item) }"
                >
                    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <template v-if="item.icon === 'gauge'">
                            <path d="M12 14a2 2 0 100-4 2 2 0 000 4Z" /><path d="M13.4 10.6 19 5" />
                            <path d="M20.7 17a9 9 0 10-17.4 0" />
                        </template>
                        <template v-else-if="item.icon === 'list'">
                            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                        </template>
                        <template v-else-if="item.icon === 'tag'">
                            <path d="M3 7v5.2a2 2 0 0 0 .6 1.4l7 7a2 2 0 0 0 2.8 0l5.2-5.2a2 2 0 0 0 0-2.8l-7-7A2 2 0 0 0 10.2 5H5a2 2 0 0 0-2 2Z" />
                            <path d="M7.5 9h.01" />
                        </template>
                        <template v-else-if="item.icon === 'printer'">
                            <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" />
                            <path d="M6 14h12v7H6z" />
                        </template>
                        <template v-else-if="item.icon === 'star'">
                            <path d="m12 3 2.9 5.9 6.1.9-4.5 4.3 1.1 6.4L12 17.5 6.4 20.5l1.1-6.4L3 9.8l6.1-.9L12 3Z" />
                        </template>
                        <template v-else-if="item.icon === 'check'">
                            <path d="M9 11l3 3L22 4" />
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                        </template>
                        <template v-else-if="item.icon === 'trophy'">
                            <path d="M6 9a6 6 0 0 0 12 0V3H6v6Z" />
                            <path d="M6 3H4v2a3 3 0 0 0 3 3M18 3h2v2a3 3 0 0 1-3 3M9 21h6M12 15v6" />
                        </template>
                        <template v-else-if="item.icon === 'chart'">
                            <path d="M3 3v18h18" /><path d="M7 15V9M12 15V5M17 15v-3" />
                        </template>
                        <template v-else-if="item.icon === 'megaphone'">
                            <path d="m3 11 18-7v16L3 13v-2Z" /><path d="M7 12v6a2 2 0 0 0 2 2h1" />
                        </template>
                        <template v-else-if="item.icon === 'news'">
                            <path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-4 0V9h4" />
                            <path d="M10 6h8M10 10h8M10 14h4" />
                        </template>
                        <template v-else-if="item.icon === 'badge'">
                            <rect x="3" y="4" width="18" height="16" rx="2" />
                            <circle cx="9" cy="11" r="2.2" />
                            <path d="M5.5 17c.7-1.6 2-2.4 3.5-2.4s2.8.8 3.5 2.4M15 9h4M15 13h4" />
                        </template>
                        <template v-else>
                            <path d="M12 5v14M5 12h14" />
                        </template>
                    </svg>
                    <span class="lbl">{{ item.label }}</span>
                    <span v-if="item.badge" class="rail-tag">{{ item.badge }}</span>
                </Link>

                <!-- Menu ini hanya muncul untuk developer. -->
                <template v-if="isDeveloper">
                    <div class="rail-group rail-group--dev">Khusus Developer</div>

                    <Link
                        v-for="item in devNav"
                        :key="item.href"
                        :href="item.href"
                        class="rail-link"
                        :class="{ 'is-on': path.startsWith(item.href) }"
                    >
                        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <template v-if="item.icon === 'users'">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                            </template>
                            <template v-else-if="item.icon === 'mail'">
                                <rect x="2" y="4" width="20" height="16" rx="2" />
                                <path d="m2 7 10 6 10-6" />
                            </template>
                            <template v-else>
                                <circle cx="12" cy="12" r="3" />
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />
                            </template>
                        </svg>
                        <span class="lbl">{{ item.label }}</span>
                    </Link>
                </template>
            </div>
        </aside>

        <div class="shell-main">
            <header class="topbar">
                <button class="rail-toggle" aria-label="Buka menu" @click="drawer = true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M3 6h18M3 12h18M3 18h18" />
                    </svg>
                </button>

                <div class="topbar-title">
                    <div v-if="crumb" class="crumb-line">{{ crumb }}</div>
                    <h1>{{ title }}</h1>
                </div>

                <div class="topbar-actions">
                    <slot name="actions" />

                    <div v-if="isPanitia" ref="bellRoot" class="bell">
                        <button
                            type="button"
                            class="bell-btn"
                            :class="{ 'is-open': bell }"
                            :aria-expanded="bell"
                            :aria-label="unread ? `${unread} pendaftar baru` : 'Notifikasi'"
                            @click="toggleBell"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.7 21a2 2 0 0 1-3.4 0" />
                            </svg>
                            <span v-if="unread" class="bell-dot">{{ unread > 9 ? '9+' : unread }}</span>
                        </button>

                        <div v-if="bell" class="bell-pop" role="menu">
                            <div class="bell-head">
                                <span class="ttl">Aktivitas Terbaru</span>
                                <span v-if="unread" class="cnt">{{ unread }} baru</span>
                            </div>

                            <div v-if="feed.length" class="bell-list">
                                <Link
                                    v-for="(row, i) in feed"
                                    :key="`${row.jenis}-${i}`"
                                    :href="row.url"
                                    class="bell-row"
                                    role="menuitem"
                                >
                                    <span
                                        class="bell-cat"
                                        :class="{ 'is-ten': row.category === '10K', 'is-kom': row.jenis === 'komentar' }"
                                    >{{ row.category }}</span>
                                    <span class="bell-body">
                                        <span class="nm">{{ row.name }}</span>
                                        <span class="mt">{{ row.status_label }} · {{ row.created_at }}</span>
                                    </span>
                                </Link>
                            </div>

                            <div v-else class="bell-empty">Belum ada yang mendaftar.</div>

                            <Link href="/panitia/pendaftaran" class="bell-foot" role="menuitem">
                                Lihat semua pendaftar →
                            </Link>
                        </div>
                    </div>

                    <div ref="menuRoot" class="user-menu">
                        <button
                            type="button"
                            class="user-btn"
                            :class="{ 'is-open': menu }"
                            :aria-expanded="menu"
                            aria-haspopup="menu"
                            @click="menu = !menu; bell = false"
                        >
                            <span class="user-av" :class="{ 'user-av--staff': isPanitia }">{{ initial }}</span>
                            <span class="user-meta">
                                <span class="nm">{{ user?.name }}</span>
                                <span class="rl">{{ roleLabel }}</span>
                            </span>
                            <svg class="user-caret" viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m1 1.5 5 5 5-5" />
                            </svg>
                        </button>

                        <div v-if="menu" class="user-pop" role="menu">
                            <div class="user-pop-head">
                                <div class="nm">{{ user?.name }}</div>
                                <div class="em">{{ user?.email }}</div>
                            </div>

                            <Link href="/profil" class="user-item" role="menuitem">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
                                </svg>
                                Profil Akun
                            </Link>

                            <a href="/" class="user-item" role="menuitem">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                </svg>
                                Lihat Situs
                            </a>

                            <div class="user-pop-sep"></div>

                            <button type="button" class="user-item is-danger" role="menuitem" @click="logout">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                                </svg>
                                Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="shell-body">
                <div class="shell-inner">
                    <FlashMessage />
                    <p v-if="lede" class="shell-lede">{{ lede }}</p>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
