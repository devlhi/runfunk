<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import Paginasi from '../../Components/Paginasi.vue';

defineProps({
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    pesertaCount: { type: Number, default: 0 },
});

const editing = ref(null);
const confirmDelete = ref(null);

const blank = { name: '', email: '', phone: '', role: 'panitia', password: '', password_confirmation: '' };
const form = useForm({ ...blank });

function startCreate() {
    editing.value = null;
    form.clearErrors();
    form.defaults({ ...blank });
    form.reset();
}

function startEdit(user) {
    editing.value = user.id;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.phone = user.phone ?? '';
    form.role = user.role;
    form.password = '';
    form.password_confirmation = '';
}

function submit() {
    const opsi = { preserveScroll: true, onSuccess: () => startCreate() };

    if (editing.value) {
        form.patch(`/panitia/pengguna/${editing.value}`, opsi);
    } else {
        form.post('/panitia/pengguna', opsi);
    }
}

function destroy(id) {
    router.delete(`/panitia/pengguna/${id}`, {
        preserveScroll: true,
        onSuccess: () => { confirmDelete.value = null; },
    });
}
</script>

<template>
    <Head title="Kelola Pengguna" />

    <PanelLayout
        crumb="Khusus Developer"
        title="Kelola Pengguna"
        lede="Buat dan atur akun panitia maupun developer. Akun peserta tidak ditampilkan di sini — mereka mendaftar sendiri lewat situs."
    >
        <div class="grid grid-side">
            <div class="panel panel--pop">
                <div class="panel-head-row">
                    <div>
                        <h2 class="panel-title">Akun Pengelola</h2>
                        <p class="panel-sub">
                            {{ users.total }} akun pengelola · {{ pesertaCount }} akun peserta terdaftar.
                        </p>
                    </div>
                </div>

                <div class="table-scroll">
                    <table class="data">
                        <thead>
                            <tr><th>Nama</th><th>Peran</th><th>Dibuat</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users.data" :key="u.id">
                                <td>
                                    <span class="strong">{{ u.name }}</span>
                                    <span class="sub">{{ u.email }}</span>
                                    <span v-if="u.phone" class="sub">{{ u.phone }}</span>
                                </td>
                                <td>
                                    <span class="badge" :class="u.role === 'developer' ? 'badge--rejected' : 'badge--waiting'">
                                        {{ u.role_label }}
                                    </span>
                                    <span v-if="u.is_self" class="badge badge--cancelled" style="margin-left:6px">Kamu</span>
                                </td>
                                <td class="mono">{{ u.created_at }}</td>
                                <td class="row-actions">
                                    <button type="button" class="btn btn--ghost btn--sm" @click="startEdit(u)">Ubah</button>
                                    <template v-if="!u.is_self">
                                        <button
                                            v-if="confirmDelete !== u.id" type="button"
                                            class="btn btn--ghost btn--sm" @click="confirmDelete = u.id"
                                        >Hapus</button>
                                        <template v-else>
                                            <button type="button" class="btn btn--danger btn--sm" @click="destroy(u.id)">Yakin hapus</button>
                                            <button type="button" class="btn btn--ghost btn--sm" @click="confirmDelete = null">Batal</button>
                                        </template>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="form.errors.user" class="error" style="margin-top:12px">{{ form.errors.user }}</p>

                <Paginasi :data="users" label="akun" />
            </div>

            <aside>
                <div class="panel">
                    <h2 class="panel-title">{{ editing ? 'Ubah Akun' : 'Tambah Akun' }}</h2>
                    <p class="panel-sub">
                        {{ editing ? 'Kosongkan kata sandi kalau tidak ingin menggantinya.' : 'Akun baru bisa langsung dipakai masuk.' }}
                    </p>

                    <form @submit.prevent="submit">
                        <div class="field">
                            <label for="name">Nama <span class="req">*</span></label>
                            <input id="name" v-model="form.name" type="text" class="input" :class="{ 'has-error': form.errors.name }" required />
                            <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                        </div>

                        <div class="field">
                            <label for="email">Email <span class="req">*</span></label>
                            <input id="email" v-model="form.email" type="email" class="input" :class="{ 'has-error': form.errors.email }" required />
                            <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
                        </div>

                        <div class="field">
                            <label for="phone">Nomor WhatsApp</label>
                            <input id="phone" v-model="form.phone" type="tel" class="input" placeholder="0812…" />
                        </div>

                        <div class="field">
                            <label for="role">Peran <span class="req">*</span></label>
                            <select id="role" v-model="form.role" class="select" :class="{ 'has-error': form.errors.role }">
                                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                            <p v-if="form.errors.role" class="error">{{ form.errors.role }}</p>
                            <p v-else class="help">
                                Developer bisa mengerjakan semua tugas panitia, plus kelola akun dan pengaturan acara.
                            </p>
                        </div>

                        <div class="field">
                            <label for="password">
                                Kata Sandi <span v-if="!editing" class="req">*</span>
                            </label>
                            <input
                                id="password" v-model="form.password" type="password" class="input"
                                :class="{ 'has-error': form.errors.password }"
                                autocomplete="new-password" :required="!editing" placeholder="Min. 8 karakter"
                            />
                            <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
                        </div>

                        <div class="field">
                            <label for="password_confirmation">Ulangi Kata Sandi</label>
                            <input
                                id="password_confirmation" v-model="form.password_confirmation" type="password"
                                class="input" autocomplete="new-password" :required="!editing && !!form.password"
                            />
                        </div>

                        <button type="submit" class="btn btn--block" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan…' : (editing ? 'Simpan Perubahan' : 'Buat Akun') }}
                        </button>

                        <button
                            v-if="editing" type="button" class="btn btn--ghost btn--block"
                            style="margin-top:10px" @click="startCreate"
                        >Batal, tambah baru saja</button>
                    </form>
                </div>
            </aside>
        </div>
    </PanelLayout>
</template>

<style scoped>
.sub { display: block; font-size: .78rem; color: var(--txt-soft); }
.row-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
</style>
