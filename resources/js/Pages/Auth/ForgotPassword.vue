<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const form = useForm({ email: '' });

function submit() {
    form.post('/lupa-kata-sandi');
}
</script>

<template>
    <Head title="Lupa Kata Sandi" />

    <AuthLayout
        title="Lupa Kata Sandi"
        subtitle="Masukkan email akunmu. Kami kirimkan tautan untuk membuat kata sandi baru."
    >
        <form @submit.prevent="submit">
            <div class="field">
                <label for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="input"
                    :class="{ 'has-error': form.errors.email }"
                    placeholder="nama@email.com"
                    autocomplete="email"
                    required
                    autofocus
                />
                <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
            </div>

            <button type="submit" class="btn btn--block" :disabled="form.processing">
                {{ form.processing ? 'Mengirim…' : 'Kirim Tautan Atur Ulang →' }}
            </button>
        </form>

        <p class="switch">
            Ingat kata sandimu?
            <Link href="/masuk">Kembali ke halaman masuk</Link>
        </p>
    </AuthLayout>
</template>

<style scoped>
.switch { margin-top: 24px; font-size: .93rem; color: var(--ink-soft); text-align: center; }
.switch a { font-weight: 700; color: var(--flame); text-decoration: underline; text-underline-offset: 3px; }
</style>
