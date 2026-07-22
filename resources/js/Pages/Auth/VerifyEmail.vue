<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const props = defineProps({
    email: { type: String, required: true },
    adaKode: { type: Boolean, default: false },
    kedaluwarsa: { type: String, default: null },
    berlakuMenit: { type: Number, default: 60 },
});

const form = useForm({ kode: '' });
const kirimUlang = useForm({});

function submit() {
    form.post('/verifikasi-email', { onFinish: () => form.reset('kode') });
}

function minta() {
    kirimUlang.post('/verifikasi-email/kirim-ulang', { preserveScroll: true });
}

function keluar() {
    router.post('/keluar');
}

/**
 * Hanya menerima angka dan otomatis mengirim saat 6 digit terisi — kode ini
 * biasanya disalin dari ponsel, jadi menekan tombol lagi terasa mengulang.
 */
const input = ref(null);

function ketik(e) {
    const bersih = e.target.value.replace(/\D/g, '').slice(0, 6);
    form.kode = bersih;
    e.target.value = bersih;

    if (bersih.length === 6 && !form.processing) submit();
}

const sisaMenit = computed(() => {
    if (!props.kedaluwarsa) return null;
    const menit = Math.ceil((new Date(props.kedaluwarsa) - new Date()) / 60000);

    return menit > 0 ? menit : 0;
});
</script>

<template>
    <Head title="Verifikasi Email" />

    <AuthLayout
        title="Verifikasi Email"
        subtitle="Satu langkah lagi sebelum bisa mengambil slot lomba."
    >
        <p class="ke">
            Kode 6 angka sudah dikirim ke
            <strong>{{ email }}</strong>. Cek juga folder spam kalau belum terlihat.
        </p>

        <form @submit.prevent="submit">
            <div class="field">
                <label for="kode">Kode Verifikasi</label>
                <input
                    id="kode" ref="input" type="text" class="input kode-input"
                    :class="{ 'has-error': form.errors.kode }"
                    inputmode="numeric" autocomplete="one-time-code"
                    placeholder="······" maxlength="6" required autofocus
                    @input="ketik"
                />
                <p v-if="form.errors.kode" class="error">{{ form.errors.kode }}</p>
                <p v-else-if="sisaMenit !== null" class="help">
                    {{ sisaMenit > 0
                        ? `Kode ini berlaku ${sisaMenit} menit lagi.`
                        : 'Kode ini sudah kedaluwarsa — minta kode baru.' }}
                </p>
                <p v-else class="help">Kode berlaku {{ berlakuMenit }} menit sejak dikirim.</p>
            </div>

            <button type="submit" class="btn btn--block" :disabled="form.processing">
                {{ form.processing ? 'Memeriksa…' : '✓ Verifikasi Sekarang' }}
            </button>
        </form>

        <div class="pisah"><span>atau</span></div>

        <p class="ke ke--kecil">
            Tekan tombol <strong>Verifikasi Email Saya</strong> di dalam emailnya — hasilnya sama,
            tanpa perlu mengetik kode.
        </p>

        <div class="bar">
            <button type="button" class="tautan" :disabled="kirimUlang.processing" @click="minta">
                {{ kirimUlang.processing ? 'Mengirim…' : 'Kirim ulang kode' }}
            </button>
            <button type="button" class="tautan tautan--samar" @click="keluar">
                Keluar
            </button>
        </div>

        <p class="ke ke--kecil" style="margin-top:18px">
            Salah menulis alamat email?
            <button type="button" class="tautan" @click="keluar">Keluar</button>
            lalu daftar ulang dengan email yang benar.
        </p>
    </AuthLayout>
</template>

<style scoped>
.ke { font-size: .92rem; color: var(--ink-soft); margin-bottom: 20px; line-height: 1.6; }
.ke--kecil { font-size: .84rem; margin-bottom: 0; }

.kode-input {
    font-family: 'Space Mono', monospace;
    font-size: 1.7rem;
    font-weight: 700;
    letter-spacing: .5em;
    text-align: center;
    padding-left: .5em;
}

.pisah { display: flex; align-items: center; gap: 12px; margin: 22px 0 16px; }
.pisah::before, .pisah::after { content: ''; flex: 1; height: 2px; background: var(--line); }
.pisah span { font-size: .74rem; letter-spacing: .12em; text-transform: uppercase; color: var(--ink-soft); }

.bar { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 20px; }

.tautan {
    background: none; border: none; padding: 0; cursor: pointer;
    font: inherit; font-size: .86rem; font-weight: 700;
    color: var(--flame); text-decoration: underline; text-underline-offset: 3px;
}

.tautan:disabled { opacity: .55; cursor: default; }
.tautan--samar { color: var(--ink-soft); font-weight: 600; }
</style>
