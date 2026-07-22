<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    // Link anchor hanya relevan di landing page.
    showAnchors: { type: Boolean, default: false },
});

const open = ref(false);
const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const dashboardUrl = computed(() => (user.value?.is_panitia ? '/panitia' : '/dashboard'));

function logout() {
    open.value = false;
    router.post('/keluar');
}
</script>

<template>
    <nav class="site-nav">
        <div class="wrap nav-in">
            <Link class="brand" href="/">
                <img class="brand-logo" src="/images/logo-ika.jpeg" alt="Logo IKA SMK Gotong Royong Telaga Gorontalo" />
                GONG<span style="color:var(--flame)">/</span>RUN
            </Link>

            <div class="nav-links" :class="{ open }" @click="open = false">
                <template v-if="showAnchors">
                    <a href="#info">Info Acara</a>
                    <a href="#kategori">Kategori</a>
                    <a href="#rute">Rute</a>
                    <Link href="/berita">Berita</Link>
                    <Link href="/hasil">Hasil</Link>
                    <a href="#faq">FAQ</a>
                </template>
                <template v-else>
                    <Link href="/">Beranda</Link>
                    <Link href="/berita">Berita</Link>
                    <Link href="/hasil">Hasil</Link>
                    <Link :href="dashboardUrl">Dashboard</Link>
                    <Link v-if="user && !user.is_panitia" href="/pendaftaran/baru">Daftar Lomba</Link>
                    <Link v-if="user?.is_panitia" href="/panitia/pendaftaran">Data Peserta</Link>
                    <Link v-if="user" href="/profil">Profil</Link>
                </template>

                <!-- Aksi akun versi mobile, ikut jatuh ke dalam menu burger -->
                <template v-if="user">
                    <button
                        type="button"
                        class="nav-mobile-action"
                        @click.stop="logout"
                    >
                        Keluar ({{ user.name }})
                    </button>
                </template>
                <template v-else>
                    <Link class="nav-mobile-action" href="/masuk">Masuk</Link>
                </template>
            </div>

            <div class="nav-actions nav-desktop-only">
                <template v-if="user">
                    <Link class="btn btn--ghost btn--sm" :href="dashboardUrl">
                        <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="9" rx="1" /><rect x="14" y="3" width="7" height="5" rx="1" />
                            <rect x="14" y="12" width="7" height="9" rx="1" /><rect x="3" y="16" width="7" height="5" rx="1" />
                        </svg>
                        Dashboard
                    </Link>
                    <button type="button" class="btn btn--sm" @click="logout">
                        <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                        </svg>
                        Keluar
                    </button>
                </template>
                <template v-else>
                    <Link class="btn btn--ghost btn--sm" href="/masuk">
                        <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" />
                        </svg>
                        Masuk
                    </Link>
                    <Link class="btn btn--sm" href="/daftar-akun">
                        <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
                            <path d="M19 8v6M22 11h-6" />
                        </svg>
                        Daftar Sekarang
                    </Link>
                </template>
            </div>

            <button class="burger" aria-label="Buka menu" @click="open = !open">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>
</template>

<style scoped>
.btn-ic {
    width: 16px;
    height: 16px;
    flex: none;
}

.brand-logo {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--ink);
    background: #fff;
    flex: none;
}

.nav-mobile-action {
    display: none;
    background: none;
    border: none;
    font-family: 'Archivo', sans-serif;
    font-weight: 600;
    font-size: .92rem;
    color: var(--flame);
    cursor: pointer;
    padding: 0;
}

@media (max-width: 640px) {
    .nav-mobile-action { display: block; }
}
</style>
