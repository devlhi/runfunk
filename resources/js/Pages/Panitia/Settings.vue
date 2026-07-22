<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    fields: { type: Array, default: () => [] },
});

const form = useForm({
    ...props.settings,
    payment_deadline_hours: Number(props.settings.payment_deadline_hours),
    mail_port: Number(props.settings.mail_port),
    registration_open: props.settings.registration_open === '1',
    wa_enabled: props.settings.wa_enabled === '1',
    mail_enabled: props.settings.mail_enabled === '1',
});

const grupWa = ['wa_api_url', 'wa_sender'];
const grupMail = ['mail_host', 'mail_port', 'mail_username', 'mail_from_address', 'mail_from_name'];

function submit() {
    form.patch('/panitia/pengaturan', { preserveScroll: true });
}

/* Uji kirim dipisah dari formulir utama: yang diuji adalah pengaturan yang sudah
   tersimpan, jadi tombolnya tidak boleh ikut menyimpan perubahan yang belum jadi. */
const ujiForm = useForm({ email: '' });

function ujiKirim() {
    ujiForm.post('/panitia/pengaturan/uji-email', { preserveScroll: true });
}

const ujiWaForm = useForm({ nomor: '' });

function ujiKirimWa() {
    ujiWaForm.post('/panitia/pengaturan/uji-wa', { preserveScroll: true });
}

/** Kelompokkan supaya formulirnya tidak jadi satu tumpukan panjang. */
const grupAcara = ['event_name', 'event_date', 'location'];
const grupBayar = ['payment_bank', 'payment_account', 'payment_holder', 'payment_whatsapp', 'payment_deadline_hours'];

function meta(key) {
    return props.fields.find((f) => f.key === key) ?? { label: key, type: 'text' };
}

function tipeInput(key) {
    const t = meta(key).type;

    return t === 'datetime' ? 'datetime-local' : (t === 'number' ? 'number' : 'text');
}

/** Hitungan kasar untuk umpan balik langsung; keabsahan tiap nomor tetap diperiksa server. */
const jumlahNomorWa = computed(
    () => (form.payment_whatsapp ?? '').split(/[,;\n]+/).map((n) => n.trim()).filter(Boolean).length
);

const contohMail = {
    mail_host: 'smtp.gmail.com',
    mail_port: '587',
    mail_username: 'panitia@gongfunrun.id',
    mail_from_address: 'panitia@gongfunrun.id',
    mail_from_name: 'Panitia Gong Fun Run',
};
</script>

<template>
    <Head title="Pengaturan Acara" />

    <PanelLayout
        crumb="Khusus Developer"
        title="Pengaturan Acara"
        lede="Data yang dulu hanya bisa diubah lewat berkas .env. Perubahan di sini langsung berlaku di seluruh situs tanpa perlu restart."
    >
        <form @submit.prevent="submit">
            <div class="grid grid-2">
                <div class="panel">
                    <h2 class="panel-title">Identitas Acara</h2>
                    <p class="panel-sub">Dipakai di landing page, hitung mundur, dan e-tiket peserta.</p>

                    <div v-for="key in grupAcara" :key="key" class="field">
                        <label :for="key">{{ meta(key).label }} <span class="req">*</span></label>
                        <input
                            :id="key" v-model="form[key]" :type="tipeInput(key)" class="input"
                            :class="{ 'has-error': form.errors[key] }" required
                        />
                        <p v-if="form.errors[key]" class="error">{{ form.errors[key] }}</p>
                    </div>

                    <div class="toggle-box" :class="{ 'is-off': !form.registration_open }">
                        <label class="check">
                            <input v-model="form.registration_open" type="checkbox" />
                            <span>
                                <b>Pendaftaran dibuka</b>
                                Kalau dimatikan, tombol daftar di landing page ikut nonaktif.
                            </span>
                        </label>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Rekening Pembayaran</h2>
                    <p class="panel-sub">Ditampilkan ke peserta pada halaman instruksi transfer.</p>

                    <div v-for="key in grupBayar" :key="key" class="field">
                        <label :for="key">{{ meta(key).label }} <span class="req">*</span></label>

                        <!-- Boleh diisi beberapa nomor, jadi butuh ruang lebih dari satu baris. -->
                        <textarea
                            v-if="key === 'payment_whatsapp'"
                            :id="key" v-model="form[key]" class="textarea wa-list"
                            :class="{ 'has-error': form.errors[key] }"
                            rows="3" placeholder="081234567890, 081298765432" required
                        ></textarea>
                        <input
                            v-else
                            :id="key" v-model="form[key]" :type="tipeInput(key)" class="input"
                            :class="{ 'has-error': form.errors[key] }"
                            :min="tipeInput(key) === 'number' ? 1 : undefined" required
                        />

                        <p v-if="form.errors[key]" class="error">{{ form.errors[key] }}</p>
                        <p v-else-if="key === 'payment_deadline_hours'" class="help">
                            Setelah lewat batas ini, slot yang belum dibayar dilepas kembali.
                        </p>
                        <p v-else-if="key === 'payment_whatsapp'" class="help">
                            Boleh lebih dari satu nomor — pisahkan dengan koma atau baris baru.
                            Laporan konfirmasi pembayaran dikirim ke semuanya.
                            <b v-if="jumlahNomorWa">{{ jumlahNomorWa }} nomor terbaca.</b>
                        </p>
                    </div>
                </div>
            </div>

            <div class="panel" style="margin-top:20px">
                <h2 class="panel-title">Sambutan Ketua IKA</h2>
                <p class="panel-sub">
                    Tampil di landing page bersama fotonya. Nama dikosongkan berarti
                    hanya jabatannya yang ditampilkan.
                </p>

                <div class="grid grid-2">
                    <div class="field">
                        <label for="chairman_name">Nama Ketua IKA</label>
                        <input
                            id="chairman_name" v-model="form.chairman_name" type="text" class="input"
                            :class="{ 'has-error': form.errors.chairman_name }"
                            placeholder="Belum diisi"
                        />
                        <p v-if="form.errors.chairman_name" class="error">{{ form.errors.chairman_name }}</p>
                    </div>

                    <div class="field">
                        <label for="chairman_title">Jabatan</label>
                        <input
                            id="chairman_title" v-model="form.chairman_title" type="text" class="input"
                            :class="{ 'has-error': form.errors.chairman_title }"
                        />
                        <p v-if="form.errors.chairman_title" class="error">{{ form.errors.chairman_title }}</p>
                    </div>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label for="chairman_message">Isi Sambutan</label>
                    <textarea
                        id="chairman_message" v-model="form.chairman_message" class="textarea"
                        :class="{ 'has-error': form.errors.chairman_message }" rows="7"
                    ></textarea>
                    <p v-if="form.errors.chairman_message" class="error">{{ form.errors.chairman_message }}</p>
                    <p v-else class="help">Pisahkan paragraf dengan satu baris kosong.</p>
                </div>
            </div>

            <div class="panel" style="margin-top:20px">
                <h2 class="panel-title">Gateway WhatsApp (mpedia)</h2>
                <p class="panel-sub">
                    Dipakai untuk mengirim pengumuman ke nomor WhatsApp peserta.
                    Kosongkan atau matikan sakelarnya kalau belum berlangganan.
                </p>

                <div class="toggle-box" :class="{ 'is-off': !form.wa_enabled }" style="margin-bottom:18px">
                    <label class="check">
                        <input v-model="form.wa_enabled" type="checkbox" />
                        <span>
                            <b>Aktifkan kirim WhatsApp</b>
                            Kalau mati, pengumuman hanya bisa dikirim lewat email.
                        </span>
                    </label>
                </div>

                <div class="grid grid-2">
                    <div v-for="key in grupWa" :key="key" class="field">
                        <label :for="key">{{ meta(key).label }}</label>
                        <input
                            :id="key" v-model="form[key]" type="text" class="input"
                            :class="{ 'has-error': form.errors[key] }"
                            :placeholder="key === 'wa_api_url' ? 'https://mpedia.example.id/send_message' : '628123456789'"
                        />
                        <p v-if="form.errors[key]" class="error">{{ form.errors[key] }}</p>
                    </div>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label for="wa_api_key">API Key</label>
                    <input
                        id="wa_api_key" v-model="form.wa_api_key" type="password" class="input"
                        :class="{ 'has-error': form.errors.wa_api_key }"
                        autocomplete="new-password"
                        :placeholder="settings.wa_api_key_terisi ? '•••••••• (sudah tersimpan)' : 'Tempel API key dari mpedia'"
                    />
                    <p v-if="form.errors.wa_api_key" class="error">{{ form.errors.wa_api_key }}</p>
                    <p v-else class="help">
                        Demi keamanan, kunci yang tersimpan tidak pernah ditampilkan kembali.
                        Biarkan kosong kalau tidak ingin menggantinya.
                    </p>
                </div>
            </div>

            <div class="panel" style="margin-top:20px">
                <h2 class="panel-title">Gateway Email (SMTP)</h2>
                <p class="panel-sub">
                    Akun pengirim untuk kabar pembayaran, pengumuman, dan atur ulang kata sandi.
                    Selama sakelarnya mati, email hanya ditulis ke log server.
                </p>

                <div class="toggle-box" :class="{ 'is-off': !form.mail_enabled }" style="margin-bottom:18px">
                    <label class="check">
                        <input v-model="form.mail_enabled" type="checkbox" />
                        <span>
                            <b>Aktifkan kirim email</b>
                            Butuh host SMTP dan email pengirim terisi lebih dulu.
                        </span>
                    </label>
                </div>

                <div class="grid grid-2">
                    <div v-for="key in grupMail" :key="key" class="field">
                        <label :for="key">{{ meta(key).label }}</label>
                        <input
                            :id="key" v-model="form[key]" :type="tipeInput(key)" class="input"
                            :class="{ 'has-error': form.errors[key] }"
                            :min="tipeInput(key) === 'number' ? 1 : undefined"
                            :placeholder="contohMail[key]"
                        />
                        <p v-if="form.errors[key]" class="error">{{ form.errors[key] }}</p>
                    </div>

                    <div class="field">
                        <label for="mail_scheme">Keamanan Koneksi</label>
                        <select id="mail_scheme" v-model="form.mail_scheme" class="select">
                            <option value="smtp">STARTTLS — port 587 (paling umum)</option>
                            <option value="smtps">SSL/TLS langsung — port 465</option>
                        </select>
                        <p v-if="form.errors.mail_scheme" class="error">{{ form.errors.mail_scheme }}</p>
                    </div>

                    <div class="field">
                        <label for="mail_password">Kata Sandi</label>
                        <input
                            id="mail_password" v-model="form.mail_password" type="password" class="input"
                            :class="{ 'has-error': form.errors.mail_password }"
                            autocomplete="new-password"
                            :placeholder="settings.mail_password_terisi ? '•••••••• (sudah tersimpan)' : 'Kata sandi akun SMTP'"
                        />
                        <p v-if="form.errors.mail_password" class="error">{{ form.errors.mail_password }}</p>
                        <p v-else class="help">
                            Untuk Gmail, pakai App Password — bukan kata sandi akun.
                        </p>
                    </div>
                </div>
            </div>

            <div class="save-bar">
                <span>Perubahan berlaku langsung untuk semua pengunjung.</span>
                <button type="submit" class="btn" :disabled="form.processing">
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Pengaturan' }}
                </button>
            </div>
        </form>

        <!-- Di luar formulir utama supaya tidak ikut mengirim/menyimpan pengaturan. -->
        <div class="panel" style="margin-top:20px">
            <h2 class="panel-title">Uji Coba Pengiriman</h2>
            <p class="panel-sub">
                Mengirim satu pesan percobaan memakai pengaturan yang <b>sudah disimpan</b>.
                Simpan dulu kalau baru saja mengubah isian di atas.
            </p>

            <div class="grid grid-2">
                <form class="uji" @submit.prevent="ujiKirim">
                    <div class="field" style="margin:0;flex:1;min-width:220px">
                        <label for="uji_email">Email tujuan</label>
                        <input
                            id="uji_email" v-model="ujiForm.email" type="email" class="input"
                            :class="{ 'has-error': ujiForm.errors.email }"
                            placeholder="nama@email.com" required
                        />
                        <p v-if="ujiForm.errors.email" class="error">{{ ujiForm.errors.email }}</p>
                    </div>
                    <button type="submit" class="btn btn--sm" :disabled="ujiForm.processing">
                        {{ ujiForm.processing ? 'Mengirim…' : '✉ Uji Email' }}
                    </button>
                </form>

                <form class="uji" @submit.prevent="ujiKirimWa">
                    <div class="field" style="margin:0;flex:1;min-width:220px">
                        <label for="uji_nomor">Nomor WhatsApp tujuan</label>
                        <input
                            id="uji_nomor" v-model="ujiWaForm.nomor" type="tel" class="input"
                            :class="{ 'has-error': ujiWaForm.errors.nomor }"
                            placeholder="081234567890" required
                        />
                        <p v-if="ujiWaForm.errors.nomor" class="error">{{ ujiWaForm.errors.nomor }}</p>
                    </div>
                    <button type="submit" class="btn btn--sm btn--mint" :disabled="ujiWaForm.processing">
                        {{ ujiWaForm.processing ? 'Mengirim…' : '💬 Uji WhatsApp' }}
                    </button>
                </form>
            </div>

            <p class="help" style="margin-top:12px">
                Hasilnya muncul sebagai pesan di atas halaman. Kalau gagal, pesan galat asli dari
                server SMTP atau gateway ikut ditampilkan supaya penyebabnya kelihatan.
                Nomor boleh ditulis <span class="mono">08…</span> atau <span class="mono">628…</span>.
            </p>

            <p class="help" style="margin-top:10px">
                <a href="/panitia/pratinjau-email">
                    Sunting &amp; lihat pratinjau template email →
                </a>
            </p>
        </div>
    </PanelLayout>
</template>

<style scoped>
.toggle-box {
    margin-top: 4px;
    padding: 14px 16px;
    border: 1px solid var(--edge-strong);
    border-radius: 11px;
    background: var(--surface-sunk);
    transition: border-color .14s ease;
}

.toggle-box.is-off { border-color: var(--danger); background: #FFF4F1; }
.toggle-box .check span { display: flex; flex-direction: column; font-size: .8rem; color: var(--txt-soft); }
.toggle-box b { font-size: .92rem; color: var(--txt); }

.uji { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }

.save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 20px;
    padding: 16px 20px;
    border: 1px solid var(--edge);
    border-radius: 12px;
    background: var(--surface);
    font-size: .86rem;
    color: var(--txt-soft);
}
</style>
