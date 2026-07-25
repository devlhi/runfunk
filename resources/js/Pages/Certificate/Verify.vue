<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    sah: { type: Boolean, required: true },
    sertifikat: { type: Object, default: null },
    acara: { type: Object, required: true },
});
</script>

<template>
    <Head :title="sah ? 'Sertifikat Terverifikasi' : 'Sertifikat Tidak Dikenali'" />

    <div class="halaman">
        <div class="kotak" :class="sah ? 'is-sah' : 'is-palsu'">
            <div class="lencana">{{ sah ? '✓' : '!' }}</div>

            <p class="status">{{ sah ? 'Sertifikat Terverifikasi' : 'Tidak Dikenali' }}</p>

            <template v-if="sah">
                <p class="ket">Data di bawah ini tercatat resmi di panitia {{ acara.nama }}.</p>

                <p class="nama">{{ sertifikat.nama }}</p>

                <div class="angka">
                    <div class="item item--utama"><i>Catatan Waktu</i><b>{{ sertifikat.waktu }}</b></div>
                    <div class="item"><i>Kategori</i><b>{{ sertifikat.kategori }}</b></div>
                    <div class="item"><i>Nomor BIB</i><b>{{ sertifikat.bib }}</b></div>
                    <div class="item"><i>Peringkat {{ sertifikat.gender }}</i><b>#{{ sertifikat.peringkat_gender }}</b></div>
                    <div class="item"><i>Peringkat Umum</i><b>#{{ sertifikat.peringkat }}</b></div>
                </div>

                <dl class="rinci">
                    <div><dt>Acara</dt><dd>{{ acara.nama }}</dd></div>
                    <div><dt>Tanggal</dt><dd>{{ acara.tanggal }}</dd></div>
                    <div><dt>Lokasi</dt><dd>{{ acara.lokasi }}</dd></div>
                    <div><dt>Kode</dt><dd class="mono">{{ sertifikat.kode }}</dd></div>
                </dl>
            </template>

            <template v-else>
                <p class="ket">
                    Kode pada sertifikat ini tidak cocok dengan catatan panitia. Bisa jadi kodenya
                    salah ketik, sertifikatnya belum diterbitkan, atau lembarnya bukan terbitan resmi.
                    Hubungi panitia kalau kamu yakin ini keliru.
                </p>
            </template>
        </div>

        <p class="kaki">
            <Link href="/">← {{ acara.nama }}</Link>
            <span>·</span>
            <Link href="/hasil">Papan hasil lomba</Link>
        </p>
    </div>
</template>

<style scoped>
/* Melanjutkan bahasa "Pesisir" — toska laut Gorontalo, Big Shoulders untuk yang
   berteriak, Space Mono untuk yang mencatat. Halaman ini dibuka orang luar yang
   baru menerima sertifikat, jadi jawabannya harus terbaca dalam sekali lihat:
   satu lencana besar, satu kalimat status, baru rinciannya. */
.halaman {
    --toska-tua: #0F766E;
    --toska-lebih-tua: #115E59;
    --toska-terang: #5EEAD4;
    --tinta: #0F172A;
    --kabur: #64748B;
    --garis: #CBD5E1;

    min-height: 100vh;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 20px;
    padding: 40px 18px 48px;
    background:
        radial-gradient(70% 55% at 50% 0%, rgba(15, 118, 110, .12), transparent 70%),
        #EEF4F3;
    color: var(--tinta);
}

.kotak {
    width: min(560px, 100%);
    background: #fff;
    border: 2.5px solid var(--tinta);
    border-radius: 20px;
    padding: 34px 30px 30px;
    text-align: center;
    box-shadow: 9px 9px 0 var(--toska-tua);
}
.kotak.is-palsu { box-shadow: 9px 9px 0 #C2410C; }

.lencana {
    width: 62px; height: 62px; margin: 0 auto 16px;
    display: grid; place-items: center;
    font-size: 2rem; font-weight: 700; line-height: 1;
    border: 2.5px solid var(--tinta); border-radius: 50%;
    background: var(--toska-terang); color: var(--tinta);
}
.is-palsu .lencana { background: #FED7AA; }

.status {
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900; font-size: 2.1rem; line-height: 1.1;
    text-transform: uppercase; letter-spacing: .01em;
    color: var(--toska-lebih-tua);
}
.is-palsu .status { color: #9A3412; }

.ket { margin-top: 10px; font-size: .9rem; line-height: 1.7; color: var(--kabur); }

.nama {
    margin-top: 22px; padding-bottom: 12px;
    font-family: 'Big Shoulders Display', sans-serif;
    font-weight: 900; font-size: clamp(2rem, 8vw, 3.1rem); line-height: 1.1;
    text-transform: uppercase; color: var(--tinta);
    border-bottom: 3px solid var(--toska-terang);
}

.angka { display: flex; flex-wrap: wrap; justify-content: center; gap: 16px 26px; margin-top: 20px; }
.item { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.item i {
    font-style: normal; font-family: 'Space Mono', monospace;
    font-size: .58rem; letter-spacing: .14em; text-transform: uppercase; color: var(--kabur);
}
.item b { font-family: 'Space Mono', monospace; font-weight: 700; font-size: 1.15rem; line-height: 1; }
.item--utama b { font-size: 1.5rem; color: var(--toska-tua); }

.rinci {
    margin-top: 24px; padding-top: 18px;
    border-top: 1.5px dashed var(--garis);
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px 18px;
    text-align: left;
}
.rinci div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.rinci dt {
    font-family: 'Space Mono', monospace; font-size: .58rem;
    letter-spacing: .12em; text-transform: uppercase; color: var(--kabur);
}
.rinci dd { font-size: .86rem; line-height: 1.4; word-break: break-word; }
.rinci .mono { font-family: 'Space Mono', monospace; font-size: .8rem; }

.kaki {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: center;
    font-family: 'Space Mono', monospace; font-size: .72rem;
    letter-spacing: .06em; text-transform: uppercase; color: var(--kabur);
}
.kaki a { color: var(--toska-tua); font-weight: 700; }
.kaki a:hover { color: var(--tinta); }

@media (max-width: 480px) {
    .kotak { padding: 26px 20px 24px; border-radius: 16px; box-shadow: 6px 6px 0 var(--toska-tua); }
    .rinci { grid-template-columns: 1fr; }
}
</style>
