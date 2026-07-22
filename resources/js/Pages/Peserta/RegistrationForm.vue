<script setup>
import { onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import { rupiah } from '../../lib/format';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    selected: { type: String, default: null },
    defaults: { type: Object, default: () => ({}) },
});

const jerseySizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

const form = useForm({
    race_category_id: null,
    participant_name: props.defaults.participant_name ?? '',
    participant_email: props.defaults.participant_email ?? '',
    participant_phone: props.defaults.participant_phone ?? '',
    gender: props.defaults.gender ?? '',
    birth_date: props.defaults.birth_date ?? '',
    city: props.defaults.city ?? '',
    address: props.defaults.address ?? '',
    jersey_size: '',
    blood_type: '',
    community: '',
    emergency_name: '',
    emergency_phone: '',
    agreement: false,
});

onMounted(() => {
    const preselect = props.categories.find((c) => c.slug === props.selected && !c.is_sold_out)
        ?? props.categories.find((c) => !c.is_sold_out);

    if (preselect) form.race_category_id = preselect.id;
});

function chosen() {
    return props.categories.find((c) => c.id === form.race_category_id) ?? null;
}

function submit() {
    form.post('/pendaftaran');
}
</script>

<template>
    <Head title="Formulir Pendaftaran" />

    <PanelLayout
        crumb="Dashboard / Formulir Pendaftaran"
        title="Formulir Pendaftaran"
        lede="Isi data peserta sesuai identitas. Data ini dipakai untuk mencetak BIB, jersey, dan sertifikat — pastikan tidak ada yang keliru."
    >
        <template #actions>
            <Link class="btn btn--ghost btn--sm" href="/dashboard">← Kembali</Link>
        </template>

        <form @submit.prevent="submit">
            <div class="grid grid-side">
                <div>
                    <!-- 1. Kategori -->
                    <div class="panel panel--pop">
                        <h2 class="panel-title">1 · Pilih Kategori</h2>
                        <p class="panel-sub">Harga sudah termasuk race pack lengkap dan medali finisher.</p>

                        <div class="pick-grid">
                            <label
                                v-for="cat in categories"
                                :key="cat.id"
                                class="pick-card"
                                :class="{
                                    'is-picked': form.race_category_id === cat.id,
                                    'is-disabled': cat.is_sold_out,
                                }"
                            >
                                <input
                                    v-model="form.race_category_id"
                                    type="radio"
                                    name="race_category_id"
                                    :value="cat.id"
                                    :disabled="cat.is_sold_out"
                                />
                                <div class="pick-top">
                                    <span class="pick-name">{{ cat.name }}</span>
                                    <span class="pick-price">{{ rupiah(cat.price) }}</span>
                                </div>
                                <p class="pick-desc">{{ cat.tagline }}</p>
                                <p class="pick-desc mono" style="margin-top:8px">
                                    <template v-if="cat.is_sold_out">Kuota habis</template>
                                    <template v-else>Sisa {{ cat.remaining }} slot</template>
                                </p>
                            </label>
                        </div>
                        <p v-if="form.errors.race_category_id" class="error">{{ form.errors.race_category_id }}</p>
                    </div>

                    <!-- 2. Data peserta -->
                    <div class="panel">
                        <h2 class="panel-title">2 · Data Peserta</h2>
                        <p class="panel-sub">
                            Boleh berbeda dari data akun kalau kamu mendaftarkan anggota keluarga.
                        </p>

                        <div class="field">
                            <label for="participant_name">Nama Peserta <span class="req">*</span></label>
                            <input
                                id="participant_name" v-model="form.participant_name" type="text"
                                class="input" :class="{ 'has-error': form.errors.participant_name }"
                                placeholder="Sesuai identitas" required
                            />
                            <p v-if="form.errors.participant_name" class="error">{{ form.errors.participant_name }}</p>
                        </div>

                        <div class="row-2">
                            <div class="field">
                                <label for="participant_email">Email <span class="req">*</span></label>
                                <input
                                    id="participant_email" v-model="form.participant_email" type="email"
                                    class="input" :class="{ 'has-error': form.errors.participant_email }" required
                                />
                                <p v-if="form.errors.participant_email" class="error">{{ form.errors.participant_email }}</p>
                            </div>
                            <div class="field">
                                <label for="participant_phone">Nomor WhatsApp <span class="req">*</span></label>
                                <input
                                    id="participant_phone" v-model="form.participant_phone" type="tel"
                                    class="input" :class="{ 'has-error': form.errors.participant_phone }"
                                    placeholder="0812xxxxxxxx" required
                                />
                                <p v-if="form.errors.participant_phone" class="error">{{ form.errors.participant_phone }}</p>
                            </div>
                        </div>

                        <div class="row-3">
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
                                    id="birth_date" v-model="form.birth_date" type="date"
                                    class="input" :class="{ 'has-error': form.errors.birth_date }" required
                                />
                                <p v-if="form.errors.birth_date" class="error">{{ form.errors.birth_date }}</p>
                            </div>
                            <div class="field">
                                <label for="blood_type">Golongan Darah</label>
                                <select id="blood_type" v-model="form.blood_type" class="select">
                                    <option value="">Tidak tahu</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="AB">AB</option>
                                    <option value="O">O</option>
                                </select>
                            </div>
                        </div>

                        <div class="row-2">
                            <div class="field">
                                <label for="city">Kota / Kabupaten <span class="req">*</span></label>
                                <input
                                    id="city" v-model="form.city" type="text" class="input"
                                    :class="{ 'has-error': form.errors.city }" required
                                />
                                <p v-if="form.errors.city" class="error">{{ form.errors.city }}</p>
                            </div>
                            <div class="field">
                                <label for="jersey_size">Ukuran Jersey <span class="req">*</span></label>
                                <select
                                    id="jersey_size" v-model="form.jersey_size" class="select"
                                    :class="{ 'has-error': form.errors.jersey_size }" required
                                >
                                    <option value="" disabled>Pilih ukuran…</option>
                                    <option v-for="size in jerseySizes" :key="size" :value="size">{{ size }}</option>
                                </select>
                                <p class="help">Ukuran tidak bisa diubah setelah race pack dicetak.</p>
                                <p v-if="form.errors.jersey_size" class="error">{{ form.errors.jersey_size }}</p>
                            </div>
                        </div>

                        <div class="field">
                            <label for="address">Alamat</label>
                            <textarea
                                id="address" v-model="form.address" class="textarea"
                                placeholder="Nama jalan, dusun, desa/kelurahan"
                            ></textarea>
                        </div>

                        <div class="field">
                            <label for="community">Komunitas / Klub Lari</label>
                            <input
                                id="community" v-model="form.community" type="text" class="input"
                                placeholder="Kosongkan kalau daftar sendiri"
                            />
                        </div>
                    </div>

                    <!-- 3. Kontak darurat -->
                    <div class="panel">
                        <h2 class="panel-title">3 · Kontak Darurat</h2>
                        <p class="panel-sub">Dihubungi panitia hanya jika terjadi sesuatu di rute.</p>

                        <div class="row-2">
                            <div class="field">
                                <label for="emergency_name">Nama Kontak Darurat <span class="req">*</span></label>
                                <input
                                    id="emergency_name" v-model="form.emergency_name" type="text"
                                    class="input" :class="{ 'has-error': form.errors.emergency_name }"
                                    placeholder="Orang tua / pasangan / saudara" required
                                />
                                <p v-if="form.errors.emergency_name" class="error">{{ form.errors.emergency_name }}</p>
                            </div>
                            <div class="field">
                                <label for="emergency_phone">Nomor Kontak Darurat <span class="req">*</span></label>
                                <input
                                    id="emergency_phone" v-model="form.emergency_phone" type="tel"
                                    class="input" :class="{ 'has-error': form.errors.emergency_phone }"
                                    placeholder="0812xxxxxxxx" required
                                />
                                <p v-if="form.errors.emergency_phone" class="error">{{ form.errors.emergency_phone }}</p>
                            </div>
                        </div>

                        <label class="check" style="margin-top:8px">
                            <input v-model="form.agreement" type="checkbox" />
                            <span>
                                Saya menyatakan dalam kondisi sehat untuk mengikuti lomba, bersedia mematuhi
                                aturan panitia, dan memahami bahwa biaya pendaftaran tidak dapat dikembalikan.
                            </span>
                        </label>
                        <p v-if="form.errors.agreement" class="error">{{ form.errors.agreement }}</p>
                    </div>
                </div>

                <!-- Ringkasan lengket -->
                <aside class="summary">
                    <div class="panel panel--ink">
                        <h2 class="panel-title">Ringkasan</h2>
                        <p class="panel-sub">Slot terkunci setelah pembayaran diverifikasi panitia.</p>

                        <template v-if="chosen()">
                            <div class="sum-row">
                                <span class="k">Kategori</span>
                                <span class="v">{{ chosen().name }}</span>
                            </div>
                            <div class="sum-row">
                                <span class="k">Jersey</span>
                                <span class="v">{{ form.jersey_size || '—' }}</span>
                            </div>
                            <div class="sum-row">
                                <span class="k">Peserta</span>
                                <span class="v">{{ form.participant_name || '—' }}</span>
                            </div>
                            <div class="sum-total">
                                <span class="k">Total Bayar</span>
                                <span class="amt">{{ rupiah(chosen().price) }}</span>
                            </div>

                            <ul class="sum-feat">
                                <li v-for="f in chosen().features" :key="f">{{ f }}</li>
                            </ul>
                        </template>
                        <p v-else class="panel-sub">Pilih kategori dulu untuk melihat rincian biaya.</p>

                        <button type="submit" class="btn btn--block" :disabled="form.processing || !form.race_category_id">
                            {{ form.processing ? 'Menyimpan…' : 'Lanjut ke Pembayaran →' }}
                        </button>
                        <p class="sum-note">
                            Setelah ini kamu akan diberi instruksi transfer dan diminta mengunggah bukti bayar.
                        </p>
                    </div>
                </aside>
            </div>
        </form>
    </PanelLayout>
</template>

<style scoped>
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }

.summary { position: sticky; top: 96px; }
/* Semua isi kartu ini berdiri di atas gradasi gelap, jadi warnanya dipilih
   dari putih transparan supaya tetap terbaca di ujung hijau maupun biru. */
.sum-row { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid rgba(255, 255, 255, .16); }
.sum-row .k { font-family: 'Space Mono'; font-size: .66rem; letter-spacing: .12em; text-transform: uppercase; color: rgba(255, 255, 255, .72); flex: none; }
.sum-row .v { font-weight: 700; text-align: right; color: #fff; }

.sum-total { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 18px 0 20px; }
/* Label kecil dibuat putih; hanya nominalnya yang kuning, karena teks kuning
   berukuran kecil kontrasnya tidak cukup. */
.sum-total .k { font-family: 'Space Mono'; font-size: .66rem; letter-spacing: .12em; text-transform: uppercase; color: rgba(255, 255, 255, .82); }
.sum-total .amt { font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 2.4rem; color: var(--marigold); line-height: 1; }

.sum-feat { list-style: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 22px; }
.sum-feat li { display: flex; gap: .55rem; align-items: flex-start; font-size: .86rem; color: rgba(255, 255, 255, .85); }
.sum-feat li::before { content: ""; flex: none; margin-top: .45em; width: 7px; height: 7px; border-radius: 50%; background: var(--marigold); }
.sum-note { font-size: .78rem; color: rgba(255, 255, 255, .72); margin-top: 14px; text-align: center; line-height: 1.5; }

@media (max-width: 1020px) {
    .summary { position: static; }
}
@media (max-width: 640px) {
    .row-2, .row-3 { grid-template-columns: 1fr; }
}
</style>
