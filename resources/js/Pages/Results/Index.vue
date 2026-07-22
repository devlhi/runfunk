<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SiteNav from '../../Components/SiteNav.vue';
import SiteFooter from '../../Components/SiteFooter.vue';

const props = defineProps({
    hasil: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    juara: { type: Object, default: () => ({}) },
    sudahAda: { type: Boolean, default: false },
    namaAcara: { type: String, default: 'Gong Fun Run 2026' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const cari = ref(props.filters.cari ?? '');
const kategori = ref(props.filters.kategori ?? '');

let debounce = null;

function muat() {
    router.get('/hasil', { cari: cari.value, kategori: kategori.value },
        { preserveState: true, preserveScroll: true, replace: true });
}

watch(cari, () => { clearTimeout(debounce); debounce = setTimeout(muat, 350); });
watch(kategori, muat);

const medali = ['🥇', '🥈', '🥉'];
</script>

<template>
    <!-- Nama acara tidak ditulis di sini; templat akar sudah menambahkannya. -->
    <Head title="Hasil Lomba" />

    <SiteNav />

    <header class="hasil-hero">
        <div class="wrap">
            <div class="sec-tag"><span class="lane">🏁</span>Hasil Lomba</div>
            <h1>Papan Hasil</h1>
            <p>Catatan waktu resmi {{ namaAcara }}. Cari namamu atau nomor BIB untuk melihat hasilmu.</p>
        </div>
    </header>

    <main class="wrap hasil-main">
        <template v-if="sudahAda">
            <!-- Juara per kategori -->
            <section v-if="juara.putra?.length || juara.putri?.length" class="juara">
                <div v-for="(daftar, jenis) in juara" :key="jenis" class="juara-kolom">
                    <h2>Juara {{ jenis === 'putra' ? 'Putra' : 'Putri' }}</h2>
                    <ol v-if="daftar.length" class="podium">
                        <li v-for="(j, i) in daftar" :key="j.bib" :class="`pos-${i + 1}`">
                            <span class="medali">{{ medali[i] }}</span>
                            <span class="j-isi">
                                <b>{{ j.nama }}</b>
                                <small class="mono">BIB {{ j.bib }}</small>
                            </span>
                            <span class="j-waktu mono">{{ j.waktu }}</span>
                        </li>
                    </ol>
                    <p v-else class="kosong-kecil">Belum ada hasil.</p>
                </div>
            </section>

            <div class="saring">
                <div class="field" style="margin:0;flex:1;min-width:220px">
                    <label for="cari">Cari Peserta</label>
                    <input id="cari" v-model="cari" type="search" class="input" placeholder="Nama atau nomor BIB…" />
                </div>
                <div class="field" style="margin:0;min-width:160px">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" v-model="kategori" class="select">
                        <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.label }}</option>
                    </select>
                </div>
            </div>

            <div v-if="hasil.data.length" class="tabel-bungkus">
                <table class="hasil-tabel">
                    <thead>
                        <tr><th>#</th><th>BIB</th><th>Nama</th><th>Kota</th><th>Waktu</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="h in hasil.data" :key="h.bib" :class="{ 'is-top': h.peringkat <= 3 }">
                            <td class="mono kol-rank">{{ h.peringkat }}</td>
                            <td class="mono">{{ h.bib }}</td>
                            <td class="kol-nama">
                                {{ h.nama }}
                                <small>{{ h.gender === 'P' ? 'Putri' : 'Putra' }} · peringkat {{ h.peringkat_gender }}</small>
                            </td>
                            <td>{{ h.kota }}</td>
                            <td class="mono kol-waktu">{{ h.waktu }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="kosong-kecil" style="padding:40px 0">
                Tidak ada hasil yang cocok dengan pencarianmu.
            </p>

            <nav v-if="hasil.last_page > 1" class="pager">
                <template v-for="link in hasil.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" class="page" :class="{ 'is-active': link.active }" v-html="link.label" />
                    <span v-else class="page is-off" v-html="link.label" />
                </template>
            </nav>

            <p class="catatan">
                Sudah menyelesaikan lomba?
                <template v-if="user">
                    Buka <Link href="/dashboard">dashboard</Link> untuk mengunduh e-sertifikatmu.
                </template>
                <template v-else>
                    <Link href="/masuk">Masuk</Link> untuk mengunduh e-sertifikatmu.
                </template>
            </p>
        </template>

        <div v-else class="belum">
            <div class="big">Hasil belum tersedia</div>
            <p>
                Catatan waktu akan diumumkan di sini setelah lomba selesai dan panitia
                selesai memverifikasi hasilnya.
            </p>
            <Link href="/" class="btn" style="margin-top:20px">← Kembali ke Beranda</Link>
        </div>
    </main>

    <SiteFooter />
</template>

<style scoped>
.hasil-hero { background: var(--ink); color: var(--paper); padding: 60px 0 52px; }
.hasil-hero h1 { font-size: clamp(2.6rem, 6vw, 4rem); font-weight: 900; margin-bottom: 14px; }
.hasil-hero p { color: #C9C1D4; max-width: 48ch; }
.hasil-hero .sec-tag { color: var(--marigold); }
.hasil-hero .lane { background: var(--marigold); }

.hasil-main { padding: 48px 0 80px; }

.juara { display: grid; grid-template-columns: 1fr 1fr; gap: 26px; margin-bottom: 40px; }
.juara-kolom h2 { font-size: 1.5rem; font-weight: 800; text-transform: uppercase; margin-bottom: 14px; }
.podium { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.podium li {
  display: flex; align-items: center; gap: 14px; padding: 14px 18px;
  border: 2.5px solid var(--ink); border-radius: 14px; background: var(--paper);
}
.podium .pos-1 { background: var(--marigold); box-shadow: 5px 5px 0 var(--ink); }
.medali { font-size: 1.5rem; flex: none; }
.j-isi { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.j-isi b { font-size: 1rem; }
.j-isi small { font-size: .68rem; color: var(--ink-soft); }
.j-waktu { font-weight: 700; font-size: 1rem; }

.saring { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }

.tabel-bungkus { overflow-x: auto; border: 2.5px solid var(--ink); border-radius: 16px; background: var(--paper); }
.hasil-tabel { width: 100%; border-collapse: collapse; min-width: 620px; }
.hasil-tabel th {
  font-family: 'Space Mono'; font-size: .66rem; letter-spacing: .1em; text-transform: uppercase;
  text-align: left; padding: 14px 16px; background: var(--ink); color: var(--paper); white-space: nowrap;
}
.hasil-tabel td { padding: 13px 16px; border-bottom: 1.5px solid var(--line); font-size: .92rem; }
.hasil-tabel tbody tr:last-child td { border-bottom: none; }
.hasil-tabel tr.is-top { background: #FFF8E8; }
.kol-rank { font-weight: 700; font-size: 1rem; }
.kol-nama { font-weight: 700; }
.kol-nama small { display: block; font-weight: 400; font-size: .72rem; color: var(--ink-soft); }
.kol-waktu { font-weight: 700; }

.catatan { margin-top: 28px; font-size: .92rem; color: var(--ink-soft); text-align: center; }
.catatan a { color: var(--flame); font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }

.kosong-kecil { font-size: .9rem; color: var(--ink-soft); }
.belum { text-align: center; padding: 80px 24px; }
.belum .big { font-family: 'Big Shoulders Display'; font-weight: 800; font-size: 2rem; text-transform: uppercase; margin-bottom: 10px; }
.belum p { color: var(--ink-soft); max-width: 44ch; margin: 0 auto; }

@media (max-width: 720px) {
  .juara { grid-template-columns: 1fr; }
}
</style>
