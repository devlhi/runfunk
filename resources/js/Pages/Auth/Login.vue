<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const props = defineProps({
    intendedCategory: { type: String, default: null },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
    kategori: props.intendedCategory,
});

function submit() {
    form.post('/masuk', {
        onFinish: () => form.reset('password'),
    });
}

const registerUrl = props.intendedCategory
    ? `/daftar-akun?kategori=${props.intendedCategory}`
    : '/daftar-akun';
</script>

<template>
    <Head title="Masuk" />

    <AuthLayout title="Masuk" subtitle="Kelola pendaftaran dan pembayaranmu di satu tempat.">
        <form @submit.prevent="submit">
            <div class="field">
                <label for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="input"
                    :class="{ 'has-error': form.errors.email }"
                    autocomplete="email"
                    placeholder="nama@email.com"
                    required
                    autofocus
                />
                <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
            </div>

            <div class="field">
                <label for="password">Kata Sandi</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="input"
                    :class="{ 'has-error': form.errors.password }"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    required
                />
                <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
            </div>

            <div class="row-between">
                <label class="check">
                    <input v-model="form.remember" type="checkbox" />
                    <span>Ingat saya</span>
                </label>
                <Link href="/lupa-kata-sandi" class="forgot">Lupa kata sandi?</Link>
            </div>

            <button type="submit" class="btn btn--block" :disabled="form.processing">
                <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" />
                </svg>
                {{ form.processing ? 'Memproses…' : 'Masuk' }}
            </button>
        </form>

        <p class="switch">
            Belum punya akun?
            <Link :href="registerUrl">Daftar sekarang</Link>
        </p>
    </AuthLayout>
</template>

<style scoped>
.btn-ic { width: 17px; height: 17px; flex: none; }
.row-between { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.forgot { font-size: .88rem; font-weight: 700; color: var(--flame); text-decoration: underline; text-underline-offset: 3px; }
.switch { margin-top: 24px; font-size: .93rem; color: var(--ink-soft); text-align: center; }
.switch a { font-weight: 700; color: var(--flame); text-decoration: underline; text-underline-offset: 3px; }
</style>
