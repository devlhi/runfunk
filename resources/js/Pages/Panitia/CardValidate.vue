<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PemindaiQr from '../../Components/PemindaiQr.vue';

const hasil = ref(null);
const memproses = ref(false);
const riwayat = ref([]);

async function terbaca(kode) {
    if (memproses.value) return;
    memproses.value = true;

    try {
        const res = await fetch('/panitia/kartu-panitia/validasi', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    (document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] ?? ''
                ),
            },
            body: JSON.stringify({ kode }),
        });

        const data = await res.json();

        hasil.value = data;
        riwayat.value = [
            { ...data, kunci: Date.now() + Math.random(), jam: new Date().toLocaleTimeString('id-ID') },
            ...riwayat.value,
        ].slice(0, 15);
    } catch {
        hasil.value = { sah: false, pesan: 'Tidak ada koneksi. Kartu tidak bisa diperiksa sekarang.' };
    } finally {
        memproses.value = false;
    }
}
</script>

<template>
    <Head title="Pindai Kartu Panitia" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Pindai Kartu Panitia"
        lede="Periksa keaslian kartu panitia di pintu masuk, meja race pack, atau area start."
    >
        <template #actions>
            <Link class="btn btn--ghost btn--sm" href="/panitia">← Panel</Link>
        </template>

        <!-- Hasil ditaruh di atas kamera: petugas menatap layar sambil
             mengarahkan HP, jadi jawabannya harus muncul di garis pandangnya. -->
        <div
            v-if="hasil"
            class="hasil"
            :class="hasil.sah ? 'hasil--sah' : 'hasil--tolak'"
        >
            <div class="hasil-ikon">{{ hasil.sah ? '✔' : '✕' }}</div>
            <div class="hasil-isi">
                <div class="hasil-vonis">{{ hasil.sah ? 'KARTU SAH' : 'DITOLAK' }}</div>

                <template v-if="hasil.orang">
                    <div class="hasil-nama">{{ hasil.orang.nama }}</div>
                    <div v-if="hasil.orang.jabatan" class="hasil-jabatan">{{ hasil.orang.jabatan }}</div>
                    <div v-if="hasil.orang.nomor" class="hasil-nomor mono">
                        {{ hasil.orang.nomor }}
                        <template v-if="hasil.orang.telepon"> · {{ hasil.orang.telepon }}</template>
                    </div>
                </template>

                <p class="hasil-pesan">{{ hasil.pesan }}</p>
            </div>
        </div>

        <div class="panel panel--pop">
            <h2 class="panel-title">Kamera</h2>
            <p class="panel-sub">Arahkan ke kode QR pada kartu panitia.</p>

            <PemindaiQr @terbaca="terbaca" />
        </div>

        <div v-if="riwayat.length" class="panel">
            <h2 class="panel-title">Riwayat Pemeriksaan</h2>
            <p class="panel-sub">Hanya di layar ini, tidak tersimpan.</p>

            <ul class="riwayat">
                <li v-for="r in riwayat" :key="r.kunci" :class="{ 'is-tolak': !r.sah }">
                    <span class="r-ikon">{{ r.sah ? '✔' : '✕' }}</span>
                    <span class="r-isi">
                        <b>{{ r.orang?.nama ?? 'Tidak dikenali' }}</b>
                        <span>{{ r.pesan }}</span>
                    </span>
                    <span class="r-jam mono">{{ r.jam }}</span>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>

<style scoped>
.hasil {
    display: flex; align-items: flex-start; gap: 16px;
    padding: 22px 24px; margin-bottom: 20px;
    border-radius: 14px; border: 2px solid;
}
.hasil--sah { background: #E6F6EF; border-color: #0FB07E; }
.hasil--tolak { background: #FFE9E4; border-color: #D7263D; }

.hasil-ikon {
    flex: none; width: 46px; height: 46px; border-radius: 50%;
    display: grid; place-items: center;
    font-size: 1.4rem; font-weight: 800; color: #fff;
}
.hasil--sah .hasil-ikon { background: #0FB07E; }
.hasil--tolak .hasil-ikon { background: #D7263D; }

.hasil-isi { min-width: 0; }
.hasil-vonis {
    font-family: 'Space Mono', monospace; font-size: .72rem; font-weight: 700;
    letter-spacing: .16em; margin-bottom: 4px;
}
.hasil--sah .hasil-vonis { color: #07634A; }
.hasil--tolak .hasil-vonis { color: #A32209; }

.hasil-nama {
    font-family: 'Big Shoulders Display', sans-serif; font-weight: 900;
    font-size: 2rem; line-height: 1.05; text-transform: uppercase; color: var(--txt);
}
.hasil-jabatan { font-size: .95rem; font-weight: 600; color: var(--txt); margin-top: 2px; }
.hasil-nomor { font-size: .78rem; color: var(--txt-soft); margin-top: 4px; }
.hasil-pesan { font-size: .86rem; color: var(--txt-soft); margin-top: 8px; }

.riwayat { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.riwayat li {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    background: #E6F6EF; border: 1px solid #B6E4D2;
}
.riwayat li.is-tolak { background: #FFE9E4; border-color: #F5C4B8; }

.r-ikon {
    flex: none; width: 20px; height: 20px; border-radius: 50%;
    display: grid; place-items: center;
    font-size: .72rem; font-weight: 800; color: #fff; background: #0FB07E;
}
.riwayat li.is-tolak .r-ikon { background: #D7263D; }

.r-isi { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.r-isi b { font-size: .88rem; }
.r-isi span { font-size: .76rem; color: var(--txt-soft); }
.r-jam { flex: none; font-size: .72rem; color: var(--txt-dim); }
</style>
