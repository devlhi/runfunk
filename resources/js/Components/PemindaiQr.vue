<script setup>
import { nextTick, onBeforeUnmount, ref, shallowRef } from 'vue';
import jsQR from 'jsqr';

const emit = defineEmits(['terbaca']);

const props = defineProps({
    /**
     * Jeda sebelum kode yang sama boleh terbaca lagi (ms). Kamera menangkap
     * puluhan bingkai per detik, jadi tanpa jeda satu kartu terkirim berkali-kali.
     */
    jedaUlang: { type: Number, default: 2500 },
});

const aktif = ref(false);
const galat = ref('');
const video = ref(null);
const kanvas = ref(null);

const aliran = shallowRef(null);
let rafId = null;
let terakhir = { kode: null, waktu: 0 };

const menyiapkan = ref(false);

async function mulai() {
    // Ketukan kedua saat kamera masih dibuka akan membuka aliran kedua yang
    // tidak pernah ditutup — lampu kamera tetap menyala walau pemindainya sudah
    // ditutup.
    if (menyiapkan.value || aktif.value) return;

    galat.value = '';

    if (!navigator.mediaDevices?.getUserMedia) {
        // Penyebab tersering BUKAN peramban usang, melainkan situs diakses lewat
        // http:// biasa. Di luar localhost, peramban menyembunyikan seluruh API
        // kamera pada koneksi tak terenkripsi — dan pesan "peramban tidak
        // mendukung" akan membuat panitia mengejar masalah yang salah.
        galat.value = window.isSecureContext === false
            ? 'Kamera diblokir karena situs ini dibuka lewat koneksi tidak aman (http://). '
              + 'Buka lewat alamat https://, atau tandai peserta manual lewat pencarian di bawah.'
            : 'Peramban ini tidak mendukung kamera. Pakai Chrome di Android, atau cari manual lewat nomor BIB.';

        return;
    }

    menyiapkan.value = true;

    try {
        aliran.value = await navigator.mediaDevices.getUserMedia({
            // facingMode belakang: panitia mengarahkan HP ke kartu peserta.
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
    } catch (e) {
        galat.value = {
            NotAllowedError: 'Izin kamera ditolak. Aktifkan izin kamera untuk situs ini di pengaturan peramban.',
            NotFoundError: 'Tidak ada kamera di perangkat ini.',
            NotReadableError: 'Kamera sedang dipakai aplikasi lain. Tutup dulu aplikasi itu.',
        }[e.name] ?? `Kamera tidak bisa dibuka: ${e.name}`;

        menyiapkan.value = false;

        return;
    }

    aktif.value = true;
    await nextTick(); // tunggu <video> benar-benar ada di DOM

    // Komponennya bisa saja sudah ditutup selama menunggu izin kamera.
    if (!video.value) {
        berhenti();
        menyiapkan.value = false;

        return;
    }

    video.value.srcObject = aliran.value;
    video.value.setAttribute('playsinline', true); // iOS: jangan buka pemutar layar penuh
    await video.value.play();

    menyiapkan.value = false;
    pindai();
}

function pindai() {
    rafId = requestAnimationFrame(pindai);

    const v = video.value;
    if (!v || v.readyState !== v.HAVE_ENOUGH_DATA) return;

    const k = kanvas.value;
    const ctx = k.getContext('2d', { willReadFrequently: true });

    // Diperkecil sebelum dibaca. Membaca bingkai penuh 1080p tiap kali membuat
    // HP kelas menengah tersendat, dan QR sebesar itu tetap terbaca di 480px.
    const skala = 480 / Math.max(v.videoWidth, v.videoHeight);
    k.width = Math.round(v.videoWidth * skala);
    k.height = Math.round(v.videoHeight * skala);

    ctx.drawImage(v, 0, 0, k.width, k.height);

    const hasil = jsQR(
        ctx.getImageData(0, 0, k.width, k.height).data,
        k.width,
        k.height,
        { inversionAttempts: 'dontInvert' }
    );

    if (!hasil?.data) return;

    const sekarang = Date.now();
    const sama = hasil.data === terakhir.kode;

    if (sama && sekarang - terakhir.waktu < props.jedaUlang) return;

    terakhir = { kode: hasil.data, waktu: sekarang };

    // Getar pendek: panitia sering memindai sambil menatap antrean, bukan layar.
    navigator.vibrate?.(60);

    emit('terbaca', hasil.data);
}

function berhenti() {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = null;

    aliran.value?.getTracks().forEach((t) => t.stop());
    aliran.value = null;
    aktif.value = false;
}

onBeforeUnmount(berhenti);

defineExpose({ berhenti });
</script>

<template>
    <div class="pindai">
        <div v-if="!aktif" class="pindai-mati">
            <button type="button" class="btn" @click="mulai">📷 Nyalakan Kamera</button>
            <p v-if="galat" class="error">{{ galat }}</p>
            <p v-else class="help">
                Arahkan kamera ke kode QR pada nomor BIB peserta. Kartu tetap bisa
                dicari manual lewat kotak pencarian di bawah.
            </p>
        </div>

        <div v-else class="pindai-hidup">
            <video ref="video" class="pindai-video" muted playsinline></video>
            <div class="pindai-bingkai" aria-hidden="true"></div>

            <button type="button" class="pindai-tutup" @click="berhenti">✕ Tutup Kamera</button>
        </div>

        <!-- Hanya alat baca; tidak pernah ditampilkan. -->
        <canvas ref="kanvas" class="pindai-kanvas"></canvas>
    </div>
</template>

<style scoped>
.pindai-mati { text-align: center; padding: 20px 0; }
.pindai-mati .help, .pindai-mati .error { margin-top: 12px; max-width: 46ch; margin-inline: auto; }

.pindai-hidup { position: relative; border-radius: 14px; overflow: hidden; background: #17131F; }

.pindai-video { display: block; width: 100%; max-height: 420px; object-fit: cover; }

/* Kotak bidik di tengah — memberi tahu panitia harus mengarahkan ke mana. */
.pindai-bingkai {
    position: absolute; inset: 50% auto auto 50%; transform: translate(-50%, -50%);
    width: min(58%, 220px); aspect-ratio: 1;
    border: 3px solid rgba(255, 255, 255, .9);
    border-radius: 16px;
    box-shadow: 0 0 0 100vmax rgba(23, 19, 31, .45);
    pointer-events: none;
}

.pindai-tutup {
    position: absolute; top: 12px; right: 12px;
    border: none; border-radius: 8px; cursor: pointer;
    padding: .45rem .8rem;
    background: rgba(23, 19, 31, .78); color: #fff;
    font: inherit; font-size: .8rem; font-weight: 700;
}

.pindai-kanvas { display: none; }
</style>
