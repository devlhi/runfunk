<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const props = defineProps({
    intendedCategory: { type: String, default: null },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    gender: '',
    birth_date: '',
    city: '',
    password: '',
    password_confirmation: '',
    kategori: props.intendedCategory,
});

function submit() {
    form.post('/daftar-akun', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

const loginUrl = props.intendedCategory
    ? `/masuk?kategori=${props.intendedCategory}`
    : '/masuk';
</script>

<template>
    <Head title="Daftar Akun" />

    <AuthLayout
        title="Buat Akun Peserta"
        subtitle="Satu akun untuk mendaftar lomba, bayar, dan menyimpan e-tiket."
    >
        <div v-if="intendedCategory" class="picked-note">
            Kategori pilihanmu: <b>{{ intendedCategory.toUpperCase() }}</b> — lanjut isi formulir lomba
            setelah akun jadi.
        </div>

        <form @submit.prevent="submit">
            <div class="field">
                <label for="name">Nama Lengkap <span class="req">*</span></label>
                <input
                    id="name" v-model="form.name" type="text" class="input"
                    :class="{ 'has-error': form.errors.name }"
                    placeholder="Sesuai KTP" required autofocus
                />
                <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
            </div>

            <div class="field">
                <label for="email">Email <span class="req">*</span></label>
                <input
                    id="email" v-model="form.email" type="email" class="input"
                    :class="{ 'has-error': form.errors.email }"
                    placeholder="nama@email.com" autocomplete="email" required
                />
                <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
            </div>

            <div class="field">
                <label for="phone">Nomor WhatsApp <span class="req">*</span></label>
                <input
                    id="phone" v-model="form.phone" type="tel" class="input"
                    :class="{ 'has-error': form.errors.phone }"
                    placeholder="0812xxxxxxxx" required
                />
                <p class="help">Dipakai panitia untuk info race pack dan pengumuman hari-H.</p>
                <p v-if="form.errors.phone" class="error">{{ form.errors.phone }}</p>
            </div>

            <div class="row-2">
                <div class="field">
                    <label for="gender">Jenis Kelamin <span class="req">*</span></label>
                    <select
                        id="gender" v-model="form.gender" class="select"
                        :class="{ 'has-error': form.errors.gender }" required
                    >
                        <option value="" disabled>Pilih…</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                    <p v-if="form.errors.gender" class="error">{{ form.errors.gender }}</p>
                </div>

                <div class="field">
                    <label for="birth_date">Tanggal Lahir <span class="req">*</span></label>
                    <input
                        id="birth_date" v-model="form.birth_date" type="date" class="input"
                        :class="{ 'has-error': form.errors.birth_date }" required
                    />
                    <p v-if="form.errors.birth_date" class="error">{{ form.errors.birth_date }}</p>
                </div>
            </div>

            <div class="field">
                <label for="city">Kota / Kabupaten <span class="req">*</span></label>
                <input
                    id="city" v-model="form.city" type="text" class="input"
                    :class="{ 'has-error': form.errors.city }"
                    placeholder="Kabupaten Gorontalo" required
                />
                <p v-if="form.errors.city" class="error">{{ form.errors.city }}</p>
            </div>

            <div class="row-2">
                <div class="field">
                    <label for="password">Kata Sandi <span class="req">*</span></label>
                    <input
                        id="password" v-model="form.password" type="password" class="input"
                        :class="{ 'has-error': form.errors.password }"
                        autocomplete="new-password" placeholder="Min. 8 karakter" required
                    />
                    <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
                </div>

                <div class="field">
                    <label for="password_confirmation">Ulangi Kata Sandi <span class="req">*</span></label>
                    <input
                        id="password_confirmation" v-model="form.password_confirmation"
                        type="password" class="input" autocomplete="new-password"
                        placeholder="Ketik ulang" required
                    />
                </div>
            </div>

            <button type="submit" class="btn btn--block" :disabled="form.processing">
                <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
                    <path d="M19 8v6M22 11h-6" />
                </svg>
                {{ form.processing ? 'Membuat akun…' : 'Buat Akun' }}
            </button>
        </form>

        <p class="switch">
            Sudah punya akun?
            <Link :href="loginUrl">Masuk di sini</Link>
        </p>
    </AuthLayout>
</template>

<style scoped>
.btn-ic { width: 17px; height: 17px; flex: none; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.picked-note {
    border: 2.5px solid var(--ink); border-radius: 12px; background: var(--marigold);
    padding: 12px 16px; font-size: .88rem; margin-bottom: 24px;
}
.switch { margin-top: 24px; font-size: .93rem; color: var(--ink-soft); text-align: center; }
.switch a { font-weight: 700; color: var(--flame); text-decoration: underline; text-underline-offset: 3px; }

@media (max-width: 560px) {
    .row-2 { grid-template-columns: 1fr; }
}
</style>
