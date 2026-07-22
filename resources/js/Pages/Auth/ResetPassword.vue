<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/atur-ulang-kata-sandi', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Atur Ulang Kata Sandi" />

    <AuthLayout title="Kata Sandi Baru" subtitle="Buat kata sandi baru untuk akun peserta kamu.">
        <form @submit.prevent="submit">
            <div class="field">
                <label for="email">Email</label>
                <input
                    id="email" v-model="form.email" type="email" class="input"
                    :class="{ 'has-error': form.errors.email }" autocomplete="email" required
                />
                <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
            </div>

            <div class="field">
                <label for="password">Kata Sandi Baru</label>
                <input
                    id="password" v-model="form.password" type="password" class="input"
                    :class="{ 'has-error': form.errors.password }"
                    autocomplete="new-password" placeholder="Min. 8 karakter" required autofocus
                />
                <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
            </div>

            <div class="field">
                <label for="password_confirmation">Ulangi Kata Sandi Baru</label>
                <input
                    id="password_confirmation" v-model="form.password_confirmation"
                    type="password" class="input" autocomplete="new-password" required
                />
            </div>

            <button type="submit" class="btn btn--block" :disabled="form.processing">
                {{ form.processing ? 'Menyimpan…' : 'Simpan Kata Sandi Baru →' }}
            </button>
        </form>
    </AuthLayout>
</template>
