<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    url: { type: String, required: true },
    title: { type: String, default: '' },
});

const disalin = ref(false);

/**
 * Semua nilai di-encode sebelum masuk ke query string. Tanpa ini, judul yang
 * mengandung & atau # bisa memotong tautan bagikan — dan lebih buruk, bisa
 * dipakai menyisipkan parameter lain.
 */
const u = computed(() => encodeURIComponent(props.url));
const t = computed(() => encodeURIComponent(props.title));

const tautan = computed(() => [
    {
        nama: 'WhatsApp',
        warna: '#25D366',
        href: `https://api.whatsapp.com/send?text=${t.value}%20${u.value}`,
        path: 'M17.5 14.4c-.3-.2-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7 0a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.4 0-.5.1-.7l.5-.6.3-.5v-.5l-1-2.3c-.2-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4A3.6 3.6 0 0 0 5.7 9c0 1.6 1.1 3.1 1.3 3.3a12.4 12.4 0 0 0 4.8 4.2c2.2.9 2.2.6 2.6.6a3.2 3.2 0 0 0 2.2-1.5 2.7 2.7 0 0 0 .2-1.5l-.6-.3M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Z',
    },
    {
        nama: 'Facebook',
        warna: '#1877F2',
        href: `https://www.facebook.com/sharer/sharer.php?u=${u.value}`,
        path: 'M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1 0 2.1.2 2.1.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12Z',
    },
    {
        nama: 'X',
        warna: '#0F1419',
        href: `https://twitter.com/intent/tweet?text=${t.value}&url=${u.value}`,
        path: 'M18.2 2H21l-6.4 7.3L22 22h-5.9l-4.6-6-5.3 6H3.4l6.9-7.8L2.6 2h6l4.2 5.5L18.2 2Zm-1 18.3h1.6L7.9 3.6H6.1l11.1 16.7Z',
    },
    {
        nama: 'Telegram',
        warna: '#2AABEE',
        href: `https://t.me/share/url?url=${u.value}&text=${t.value}`,
        path: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.6 6.8-1.5 7.3c-.1.5-.4.6-.9.4l-2.4-1.8-1.2 1.1c-.1.2-.3.3-.5.3l.2-2.5 4.5-4c.2-.2 0-.3-.3-.1l-5.5 3.5-2.4-.8c-.5-.1-.5-.5.1-.7l9.4-3.6c.4-.2.8.1.7.9Z',
    },
]);

async function salin() {
    try {
        await navigator.clipboard.writeText(props.url);
        disalin.value = true;
        setTimeout(() => (disalin.value = false), 2000);
    } catch {
        // Clipboard API butuh HTTPS. Kalau ditolak, tampilkan tautannya
        // supaya pengguna tetap bisa menyalin manual.
        window.prompt('Salin tautan ini:', props.url);
    }
}
</script>

<template>
    <div class="share">
        <span class="share-label">Bagikan</span>

        <a
            v-for="s in tautan"
            :key="s.nama"
            class="share-btn"
            :style="{ '--warna': s.warna }"
            :href="s.href"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="`Bagikan ke ${s.nama}`"
            :title="`Bagikan ke ${s.nama}`"
        >
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path :d="s.path" /></svg>
        </a>

        <button
            type="button" class="share-btn share-btn--salin"
            :class="{ 'is-done': disalin }"
            aria-label="Salin tautan"
            :title="disalin ? 'Tersalin!' : 'Salin tautan'"
            @click="salin"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <template v-if="disalin"><path d="M20 6 9 17l-5-5" /></template>
                <template v-else>
                    <rect x="9" y="9" width="13" height="13" rx="2" />
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </template>
            </svg>
        </button>

        <span v-if="disalin" class="share-ok">Tautan tersalin</span>
    </div>
</template>

<style scoped>
.share { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.share-label {
    font-family: 'Space Mono', monospace;
    font-size: .66rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--ink-soft);
}

.share-btn {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border: 2px solid var(--ink);
    border-radius: 10px;
    background: var(--paper);
    color: var(--warna, var(--ink));
    cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
}

.share-btn svg { width: 19px; height: 19px; }
.share-btn:hover { transform: translate(-2px, -2px); box-shadow: 3px 3px 0 var(--ink); }
.share-btn--salin { color: var(--ink); }
.share-btn--salin.is-done { background: var(--mint); color: #fff; border-color: var(--mint); }

.share-ok { font-size: .78rem; color: var(--mint); font-weight: 700; }
</style>
