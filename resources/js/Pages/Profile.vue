<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PanelLayout from '../Layouts/PanelLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const profileForm = useForm({ ...props.profile, current_password: '' });

/**
 * Mengganti email memerlukan kata sandi: alamat baru bisa langsung dipakai
 * meminta tautan atur ulang, jadi tanpa ini sesi yang dicuri cukup untuk
 * mengambil alih akun. Kolomnya baru muncul saat alamatnya benar-benar diubah,
 * supaya menyunting nama saja tetap ringkas.
 */
const emailBerubah = computed(
    () => (profileForm.email ?? '').trim().toLowerCase() !== (props.profile.email ?? '').toLowerCase()
);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function saveProfile() {
    profileForm.patch('/profil', {
        preserveScroll: true,
        // Jangan tinggalkan kata sandi tersimpan di kolom setelah selesai.
        onFinish: () => profileForm.reset('current_password'),
    });
}

function savePassword() {
    passwordForm.put('/profil/kata-sandi', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="Profil" />

    <PanelLayout
        crumb="Akun"
        title="Profil Akun"
        lede="Data ini dipakai sebagai isian awal saat kamu mendaftar lomba. Mengubahnya tidak mengubah pendaftaran yang sudah berjalan."
    >

        <div class="grid grid-side">
            <div class="panel panel--pop">
                <h2 class="panel-title">Data Diri</h2>
                <p class="panel-sub">Pastikan nomor WhatsApp aktif — panitia mengirim info race pack ke sana.</p>

                <form @submit.prevent="saveProfile">
                    <div class="field">
                        <label for="name">Nama Lengkap</label>
                        <input
                            id="name" v-model="profileForm.name" type="text" class="input"
                            :class="{ 'has-error': profileForm.errors.name }" required
                        />
                        <p v-if="profileForm.errors.name" class="error">{{ profileForm.errors.name }}</p>
                    </div>

                    <div class="row-2">
                        <div class="field">
                            <label for="email">Email</label>
                            <input
                                id="email" v-model="profileForm.email" type="email" class="input"
                                :class="{ 'has-error': profileForm.errors.email }" required
                            />
                            <p v-if="profileForm.errors.email" class="error">{{ profileForm.errors.email }}</p>
                        </div>
                        <div class="field">
                            <label for="phone">Nomor WhatsApp</label>
                            <input
                                id="phone" v-model="profileForm.phone" type="tel" class="input"
                                :class="{ 'has-error': profileForm.errors.phone }" required
                            />
                            <p v-if="profileForm.errors.phone" class="error">{{ profileForm.errors.phone }}</p>
                        </div>
                    </div>

                    <!-- Muncul hanya saat alamat email diubah. -->
                    <div v-if="emailBerubah" class="field field-sandi">
                        <label for="current_password">Kata sandi saat ini</label>
                        <input
                            id="current_password" v-model="profileForm.current_password"
                            type="password" class="input" autocomplete="current-password"
                            :class="{ 'has-error': profileForm.errors.current_password }"
                            placeholder="Wajib diisi untuk mengganti email"
                        />
                        <p v-if="profileForm.errors.current_password" class="error">
                            {{ profileForm.errors.current_password }}
                        </p>
                        <p v-else class="hint">
                            Email yang baru bisa dipakai meminta tautan atur ulang kata sandi,
                            jadi perubahannya kami pastikan dulu memang darimu.
                        </p>
                    </div>

                    <div class="row-2">
                        <div class="field">
                            <label for="gender">Jenis Kelamin</label>
                            <select id="gender" v-model="profileForm.gender" class="select">
                                <option :value="null">Belum diisi</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="birth_date">Tanggal Lahir</label>
                            <input id="birth_date" v-model="profileForm.birth_date" type="date" class="input" />
                        </div>
                    </div>

                    <div class="field">
                        <label for="city">Kota / Kabupaten</label>
                        <input id="city" v-model="profileForm.city" type="text" class="input" />
                    </div>

                    <div class="field">
                        <label for="address">Alamat</label>
                        <textarea id="address" v-model="profileForm.address" class="textarea"></textarea>
                    </div>

                    <button type="submit" class="btn btn--sm" :disabled="profileForm.processing">
                        {{ profileForm.processing ? 'Menyimpan…' : 'Simpan Profil' }}
                    </button>
                </form>
            </div>

            <aside>
                <div class="panel">
                    <h2 class="panel-title">Ubah Kata Sandi</h2>
                    <p class="panel-sub">Minimal 8 karakter. Gunakan kombinasi yang tidak mudah ditebak.</p>

                    <form @submit.prevent="savePassword">
                        <div class="field">
                            <label for="current_password">Kata Sandi Saat Ini</label>
                            <input
                                id="current_password" v-model="passwordForm.current_password" type="password"
                                class="input" :class="{ 'has-error': passwordForm.errors.current_password }"
                                autocomplete="current-password" required
                            />
                            <p v-if="passwordForm.errors.current_password" class="error">
                                {{ passwordForm.errors.current_password }}
                            </p>
                        </div>

                        <div class="field">
                            <label for="new_password">Kata Sandi Baru</label>
                            <input
                                id="new_password" v-model="passwordForm.password" type="password"
                                class="input" :class="{ 'has-error': passwordForm.errors.password }"
                                autocomplete="new-password" required
                            />
                            <p v-if="passwordForm.errors.password" class="error">{{ passwordForm.errors.password }}</p>
                        </div>

                        <div class="field">
                            <label for="password_confirmation">Ulangi Kata Sandi Baru</label>
                            <input
                                id="password_confirmation" v-model="passwordForm.password_confirmation"
                                type="password" class="input" autocomplete="new-password" required
                            />
                        </div>

                        <button type="submit" class="btn btn--sm btn--ink" :disabled="passwordForm.processing">
                            {{ passwordForm.processing ? 'Menyimpan…' : 'Ubah Kata Sandi' }}
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </PanelLayout>
</template>

<style scoped>
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Ditandai garis oranye supaya jelas ini langkah pengaman, bukan isian biasa. */
.field-sandi {
    padding: 14px 16px;
    margin-bottom: 4px;
    border-left: 3px solid var(--flame);
    border-radius: 0 10px 10px 0;
    background: rgba(255, 74, 28, .05);
}

.hint { margin-top: 6px; font-size: .8rem; line-height: 1.5; color: var(--ink-soft); }

@media (max-width: 640px) {
    .row-2 { grid-template-columns: 1fr; }
}
</style>
