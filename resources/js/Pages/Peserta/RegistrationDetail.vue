<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import { rupiah, statusBadgeClass } from '../../lib/format';

const props = defineProps({
    registration: { type: Object, required: true },
    paymentInfo: { type: Object, default: () => ({}) },
});

const copied = ref('');
const showCancel = ref(false);
const showTransfer = ref(false);

const transferForm = useForm({
    participant_name: '',
    participant_email: '',
    participant_phone: '',
    gender: 'L',
    birth_date: '',
    city: '',
    jersey_size: 'M',
    blood_type: '',
    emergency_name: '',
    emergency_phone: '',
    konfirmasi: false,
});

function alihkanSlot() {
    transferForm.post(`/pendaftaran/${props.registration.id}/alihkan`, {
        preserveScroll: true,
        onSuccess: () => {
            showTransfer.value = false;
            transferForm.reset();
        },
    });
}

const form = useForm({
    method: 'transfer',
    sender_name: props.registration.participant_name ?? '',
    sender_bank: '',
    paid_at: new Date().toISOString().slice(0, 10),
    proof: null,
});

const isConfirmed = computed(() => props.registration.status === 'confirmed');
const isWaiting = computed(() => props.registration.status === 'waiting_verification');
const isCancelled = computed(() => props.registration.status === 'cancelled');

function copy(text, key) {
    navigator.clipboard?.writeText(String(text)).then(() => {
        copied.value = key;
        setTimeout(() => (copied.value = ''), 1800);
    });
}

function submitProof() {
    form.post(`/pendaftaran/${props.registration.id}/pembayaran`, {
        forceFormData: true,
        onSuccess: () => form.reset('proof'),
    });
}

/**
 * Kelas penanda dipasang tepat sebelum mencetak lalu dilepas setelahnya,
 * supaya aturan "sembunyikan semua kecuali .print-area" tidak ikut aktif
 * kalau pengguna menekan Ctrl+P dari halaman lain.
 */
function printTicket() {
    document.body.classList.add('printing-area');

    const bersihkan = () => {
        document.body.classList.remove('printing-area');
        window.removeEventListener('afterprint', bersihkan);
    };

    window.addEventListener('afterprint', bersihkan);
    window.print();

    // Jaring pengaman untuk browser yang tidak memicu afterprint.
    setTimeout(bersihkan, 3000);
}

function cancelRegistration() {
    router.post(`/pendaftaran/${props.registration.id}/batal`, {}, {
        onFinish: () => (showCancel.value = false),
    });
}
</script>

<template>
    <Head :title="`Pendaftaran ${registration.code}`" />

    <PanelLayout :crumb="`Dashboard / ${registration.code}`" :title="registration.category">
        <template #actions>
            <Link class="btn btn--ghost btn--sm" href="/dashboard">← Kembali</Link>
        </template>

        <div class="head-badges">
            <span :class="statusBadgeClass(registration.status)">{{ registration.status_label }}</span>
            <span v-if="registration.bib_number" class="badge badge--bib">BIB {{ registration.bib_number }}</span>
            <span class="badge mono">{{ registration.code }}</span>
        </div>

        <!-- Bukti ditolak: tampilkan alasannya paling atas -->
        <div v-if="registration.status === 'rejected'" class="flash flash--error">
            <span aria-hidden="true">⚠</span>
            <span>
                <b>Bukti pembayaran ditolak.</b>
                {{ registration.panitia_note || 'Silakan unggah ulang bukti yang benar.' }}
            </span>
        </div>

        <div class="grid grid-side">
            <div>
                <!-- E-tiket setelah lunas -->
                <div v-if="isConfirmed" class="eticket print-area" style="margin-bottom:20px">
                    <span class="pin tl"></span><span class="pin tr"></span>
                    <div class="arc">Gong Funrun · 2026 · E-Tiket Peserta</div>
                    <div class="bib-no">{{ registration.bib_number }}</div>
                    <div class="bib-cat">{{ registration.distance_label }}</div>
                    <div class="bib-name">{{ registration.participant_name }}</div>
                    <div class="bib-foot">
                        {{ registration.code }} · Jersey {{ registration.jersey_size }} ·
                        Tunjukkan halaman ini saat ambil race pack
                    </div>
                    <div class="etiket-aksi no-print">
                        <button type="button" class="btn btn--ghost btn--sm" @click="printTicket">
                            Cetak / Simpan PDF
                        </button>
                        <a
                            v-if="registration.has_certificate"
                            class="btn btn--sm"
                            :href="`/sertifikat/${registration.id}`"
                        >🏅 Ambil E-Sertifikat</a>
                    </div>

                    <p v-if="registration.finish_time" class="etiket-waktu no-print">
                        Catatan waktumu: <b>{{ registration.finish_time }}</b>
                    </p>
                </div>

                <!-- Instruksi pembayaran -->
                <div v-if="registration.can_upload_proof" class="panel panel--pop">
                    <h2 class="panel-title">Instruksi Pembayaran</h2>
                    <p class="panel-sub">
                        Transfer sesuai nominal persis di bawah ini agar verifikasi lebih cepat.
                    </p>

                    <div class="pay-row">
                        <div>
                            <div class="k">Total yang harus dibayar</div>
                            <div class="v big">{{ rupiah(registration.amount) }}</div>
                        </div>
                        <button type="button" class="copy-btn" @click="copy(registration.amount, 'amount')">
                            {{ copied === 'amount' ? 'Tersalin ✔' : 'Salin' }}
                        </button>
                    </div>

                    <div class="pay-row">
                        <div>
                            <div class="k">{{ paymentInfo.bank_name }} — a.n. {{ paymentInfo.bank_holder }}</div>
                            <div class="v mono">{{ paymentInfo.bank_account }}</div>
                        </div>
                        <button type="button" class="copy-btn" @click="copy(paymentInfo.bank_account, 'rek')">
                            {{ copied === 'rek' ? 'Tersalin ✔' : 'Salin' }}
                        </button>
                    </div>

                    <div class="pay-row">
                        <div>
                            <div class="k">QRIS / E-Wallet</div>
                            <div class="v">{{ paymentInfo.qris_name }}</div>
                        </div>
                        <span class="badge">Scan di lokasi / minta panitia</span>
                    </div>

                    <div class="pay-row">
                        <div>
                            <div class="k">Berita transfer</div>
                            <div class="v mono">{{ registration.code }}</div>
                        </div>
                        <button type="button" class="copy-btn" @click="copy(registration.code, 'code')">
                            {{ copied === 'code' ? 'Tersalin ✔' : 'Salin' }}
                        </button>
                    </div>
                </div>

                <!-- Form unggah bukti -->
                <div v-if="registration.can_upload_proof" class="panel">
                    <h2 class="panel-title">Unggah Bukti Pembayaran</h2>
                    <p class="panel-sub">
                        Format JPG, PNG, WEBP, atau PDF maksimal 4 MB. Panitia memverifikasi maksimal 1×24 jam.
                    </p>

                    <form @submit.prevent="submitProof">
                        <div class="row-2">
                            <div class="field">
                                <label for="method">Metode Pembayaran <span class="req">*</span></label>
                                <select id="method" v-model="form.method" class="select" required>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="qris">QRIS</option>
                                    <option value="ewallet">E-Wallet (Dana/OVO/GoPay)</option>
                                </select>
                                <p v-if="form.errors.method" class="error">{{ form.errors.method }}</p>
                            </div>
                            <div class="field">
                                <label for="paid_at">Tanggal Transfer <span class="req">*</span></label>
                                <input
                                    id="paid_at" v-model="form.paid_at" type="date" class="input"
                                    :class="{ 'has-error': form.errors.paid_at }" required
                                />
                                <p v-if="form.errors.paid_at" class="error">{{ form.errors.paid_at }}</p>
                            </div>
                        </div>

                        <div class="row-2">
                            <div class="field">
                                <label for="sender_name">Nama Pengirim <span class="req">*</span></label>
                                <input
                                    id="sender_name" v-model="form.sender_name" type="text" class="input"
                                    :class="{ 'has-error': form.errors.sender_name }"
                                    placeholder="Nama di rekening pengirim" required
                                />
                                <p v-if="form.errors.sender_name" class="error">{{ form.errors.sender_name }}</p>
                            </div>
                            <div class="field">
                                <label for="sender_bank">Bank / E-Wallet Pengirim</label>
                                <input
                                    id="sender_bank" v-model="form.sender_bank" type="text" class="input"
                                    placeholder="BRI / BCA / Dana …"
                                />
                            </div>
                        </div>

                        <div class="field">
                            <label for="proof">Foto / File Bukti Transfer <span class="req">*</span></label>
                            <input
                                id="proof" type="file" class="input"
                                :class="{ 'has-error': form.errors.proof }"
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                                required
                                @input="form.proof = $event.target.files[0]"
                            />
                            <progress v-if="form.progress" :value="form.progress.percentage" max="100" style="width:100%;margin-top:8px">
                                {{ form.progress.percentage }}%
                            </progress>
                            <p v-if="form.errors.proof" class="error">{{ form.errors.proof }}</p>
                        </div>

                        <button type="submit" class="btn" :disabled="form.processing">
                            {{ form.processing ? 'Mengirim…' : 'Kirim Bukti Pembayaran →' }}
                        </button>
                    </form>
                </div>

                <!-- Status menunggu verifikasi -->
                <div v-if="isWaiting" class="panel panel--pop">
                    <h2 class="panel-title">Bukti Terkirim, Menunggu Verifikasi</h2>
                    <p class="panel-sub">
                        Panitia sedang mencocokkan pembayaranmu dengan mutasi rekening.
                        Nomor BIB terbit otomatis begitu disetujui — biasanya kurang dari 1×24 jam.
                    </p>
                    <div class="waiting-steps">
                        <div class="ws done"><b>1</b> Data peserta lengkap</div>
                        <div class="ws done"><b>2</b> Bukti bayar diunggah</div>
                        <div class="ws now"><b>3</b> Diverifikasi panitia</div>
                        <div class="ws"><b>4</b> BIB &amp; e-tiket terbit</div>
                    </div>
                </div>

                <!-- Riwayat pembayaran -->
                <div v-if="registration.payments?.length" class="panel">
                    <h2 class="panel-title">Riwayat Pembayaran</h2>
                    <p class="panel-sub">Semua bukti yang pernah kamu kirim untuk pendaftaran ini.</p>

                    <div class="table-scroll">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Dikirim</th>
                                    <th>Metode</th>
                                    <th>Pengirim</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                    <th>Bukti</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pay in registration.payments" :key="pay.id">
                                    <td class="muted">{{ pay.created_at }}</td>
                                    <td>{{ pay.method_label }}</td>
                                    <td>
                                        <span class="strong">{{ pay.sender_name }}</span>
                                        <div v-if="pay.sender_bank" class="muted">{{ pay.sender_bank }}</div>
                                    </td>
                                    <td class="mono">{{ rupiah(pay.amount) }}</td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="{
                                                'badge--confirmed': pay.status === 'approved',
                                                'badge--rejected': pay.status === 'rejected',
                                                'badge--pending': pay.status === 'pending',
                                            }"
                                        >
                                            {{ pay.status === 'approved' ? 'Disetujui' : pay.status === 'rejected' ? 'Ditolak' : 'Menunggu' }}
                                        </span>
                                        <div v-if="pay.reject_reason" class="muted">{{ pay.reject_reason }}</div>
                                    </td>
                                    <td>
                                        <a v-if="pay.proof_url" :href="pay.proof_url" target="_blank" rel="noopener" class="link">Lihat</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar: data peserta -->
            <aside>
                <div class="panel">
                    <h2 class="panel-title">Data Peserta</h2>
                    <p class="panel-sub">Ada yang keliru? Hubungi panitia sebelum race pack dicetak.</p>

                    <dl class="dl" style="grid-template-columns:1fr">
                        <div><dt>Nama</dt><dd>{{ registration.participant_name }}</dd></div>
                        <div><dt>Kategori</dt><dd>{{ registration.category }}</dd></div>
                        <div><dt>Ukuran Jersey</dt><dd>{{ registration.jersey_size }}</dd></div>
                        <div><dt>Jenis Kelamin</dt><dd>{{ registration.gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</dd></div>
                        <div><dt>Tanggal Lahir</dt><dd>{{ registration.birth_date }}</dd></div>
                        <div><dt>Kota</dt><dd>{{ registration.city }}</dd></div>
                        <div><dt>WhatsApp</dt><dd>{{ registration.participant_phone }}</dd></div>
                        <div><dt>Email</dt><dd>{{ registration.participant_email }}</dd></div>
                        <div v-if="registration.blood_type"><dt>Golongan Darah</dt><dd>{{ registration.blood_type }}</dd></div>
                        <div v-if="registration.community"><dt>Komunitas</dt><dd>{{ registration.community }}</dd></div>
                        <div>
                            <dt>Kontak Darurat</dt>
                            <dd>{{ registration.emergency_name }} · {{ registration.emergency_phone }}</dd>
                        </div>
                        <div><dt>Total Biaya</dt><dd>{{ rupiah(registration.amount) }}</dd></div>
                    </dl>
                </div>

                <!-- Pengalihan slot ke orang lain, sesuai janji di FAQ. -->
                <div v-if="registration.can_transfer" class="panel no-print">
                    <h2 class="panel-title">Alihkan Slot</h2>
                    <p class="panel-sub">
                        Berhalangan ikut? Slot ini bisa dialihkan ke orang lain sampai H-7.
                        Nomor BIB dan kode pendaftaran tetap sama — hanya data pesertanya yang diganti.
                    </p>

                    <button v-if="!showTransfer" type="button" class="btn btn--ghost btn--sm" @click="showTransfer = true">
                        Alihkan ke Orang Lain
                    </button>

                    <form v-else @submit.prevent="alihkanSlot">
                        <p class="alih-catat">
                            Pembayaran dan e-tiket tetap dikelola dari akunmu. Pastikan datanya
                            benar — <b>pengalihan hanya bisa dilakukan sekali.</b>
                        </p>

                        <div class="field">
                            <label for="t_name">Nama Peserta Pengganti <span class="req">*</span></label>
                            <input id="t_name" v-model="transferForm.participant_name" type="text" class="input"
                                :class="{ 'has-error': transferForm.errors.participant_name }" required />
                            <p v-if="transferForm.errors.participant_name" class="error">{{ transferForm.errors.participant_name }}</p>
                        </div>

                        <div class="field">
                            <label for="t_email">Email <span class="req">*</span></label>
                            <input id="t_email" v-model="transferForm.participant_email" type="email" class="input"
                                :class="{ 'has-error': transferForm.errors.participant_email }" required />
                            <p v-if="transferForm.errors.participant_email" class="error">{{ transferForm.errors.participant_email }}</p>
                        </div>

                        <div class="field">
                            <label for="t_phone">Nomor WhatsApp <span class="req">*</span></label>
                            <input id="t_phone" v-model="transferForm.participant_phone" type="tel" class="input"
                                :class="{ 'has-error': transferForm.errors.participant_phone }" required />
                            <p v-if="transferForm.errors.participant_phone" class="error">{{ transferForm.errors.participant_phone }}</p>
                        </div>

                        <div class="alih-row">
                            <div class="field">
                                <label for="t_gender">Jenis Kelamin <span class="req">*</span></label>
                                <select id="t_gender" v-model="transferForm.gender" class="select">
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="t_birth">Tanggal Lahir <span class="req">*</span></label>
                                <input id="t_birth" v-model="transferForm.birth_date" type="date" class="input"
                                    :class="{ 'has-error': transferForm.errors.birth_date }" required />
                                <p v-if="transferForm.errors.birth_date" class="error">{{ transferForm.errors.birth_date }}</p>
                            </div>
                        </div>

                        <div class="alih-row">
                            <div class="field">
                                <label for="t_city">Kota <span class="req">*</span></label>
                                <input id="t_city" v-model="transferForm.city" type="text" class="input" required />
                            </div>
                            <div class="field">
                                <label for="t_jersey">Ukuran Jersey <span class="req">*</span></label>
                                <select id="t_jersey" v-model="transferForm.jersey_size" class="select">
                                    <option v-for="u in ['S','M','L','XL','XXL']" :key="u" :value="u">{{ u }}</option>
                                </select>
                                <p class="help">Jersey belum dicetak, ukurannya masih bisa berubah.</p>
                            </div>
                        </div>

                        <div class="alih-row">
                            <div class="field">
                                <label for="t_ice">Kontak Darurat <span class="req">*</span></label>
                                <input id="t_ice" v-model="transferForm.emergency_name" type="text" class="input" required />
                            </div>
                            <div class="field">
                                <label for="t_icep">Nomor Darurat <span class="req">*</span></label>
                                <input id="t_icep" v-model="transferForm.emergency_phone" type="tel" class="input" required />
                            </div>
                        </div>

                        <label class="check" style="margin-bottom:16px">
                            <input v-model="transferForm.konfirmasi" type="checkbox" />
                            <span>Saya paham pengalihan ini <b>tidak bisa dibatalkan</b> dan hanya berlaku sekali.</span>
                        </label>
                        <p v-if="transferForm.errors.konfirmasi" class="error" style="margin-bottom:12px">{{ transferForm.errors.konfirmasi }}</p>

                        <div class="alih-aksi">
                            <button type="submit" class="btn btn--sm" :disabled="transferForm.processing">
                                {{ transferForm.processing ? 'Mengalihkan…' : 'Alihkan Slot' }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" @click="showTransfer = false">Batal</button>
                        </div>
                    </form>
                </div>

                <div v-if="registration.transferred_at" class="panel no-print">
                    <h2 class="panel-title">Riwayat Pengalihan</h2>
                    <p class="panel-sub" style="margin-bottom:0">
                        Slot ini dialihkan dari <b>{{ registration.transferred_from }}</b>
                        pada {{ registration.transferred_at }}. Pengalihan hanya berlaku sekali,
                        jadi slot ini tidak bisa dialihkan lagi.
                    </p>
                </div>

                <div v-if="!isConfirmed && !isCancelled" class="panel no-print">
                    <h2 class="panel-title">Batalkan Pendaftaran</h2>
                    <p class="panel-sub">
                        Slot akan dilepas kembali untuk peserta lain. Tindakan ini tidak bisa dibatalkan.
                    </p>
                    <button type="button" class="btn btn--danger btn--sm" @click="showCancel = true">
                        Batalkan Pendaftaran
                    </button>
                </div>
            </aside>
        </div>

        <!-- Konfirmasi pembatalan -->
        <Teleport to="body">
            <div v-if="showCancel" class="modal-veil" @click.self="showCancel = false">
                <div class="modal" role="dialog" aria-modal="true">
                    <h3>Batalkan pendaftaran?</h3>
                    <p class="modal-sub">
                        Pendaftaran <b>{{ registration.code }}</b> atas nama
                        <b>{{ registration.participant_name }}</b> akan dibatalkan dan slotnya dilepas.
                        Kalau nanti mau ikut lagi, kamu harus mendaftar ulang dan kuota bisa saja sudah habis.
                    </p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn--ghost btn--sm" @click="showCancel = false">
                            Tidak jadi
                        </button>
                        <button type="button" class="btn btn--danger btn--sm" @click="cancelRegistration">
                            Ya, batalkan
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </PanelLayout>
</template>

<style scoped>
.etiket-aksi { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 20px; }
.etiket-waktu { margin-top: 12px; font-size: .84rem; color: var(--txt-soft); }
.etiket-waktu b { font-family: 'Space Mono', monospace; color: var(--txt); font-size: .95rem; }

/* Pengalihan slot */
.alih-catat {
    font-size: .82rem;
    color: var(--txt-soft);
    background: var(--surface-sunk);
    border: 1px solid var(--edge);
    border-radius: 9px;
    padding: 11px 13px;
    margin-bottom: 16px;
    line-height: 1.55;
}
.alih-catat b { color: var(--txt); }
.alih-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.alih-aksi { display: flex; gap: 10px; flex-wrap: wrap; }

@media (max-width: 640px) {
    .alih-row { grid-template-columns: 1fr; }
}

.head-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.link { font-weight: 700; color: var(--flame); text-decoration: underline; text-underline-offset: 3px; }

.waiting-steps { display: flex; flex-direction: column; gap: 10px; }
.ws {
    display: flex; align-items: center; gap: .8rem; font-size: .92rem; color: var(--ink-soft);
    border: 2.5px solid var(--line); border-radius: 12px; padding: 12px 16px;
}
.ws b {
    font-family: 'Space Mono'; font-size: .78rem; width: 28px; height: 28px; flex: none;
    display: grid; place-items: center; border-radius: 50%;
    border: 2.5px solid var(--line); background: var(--paper);
}
.ws.done { border-color: var(--ink); color: var(--ink); }
.ws.done b { background: var(--mint); color: var(--paper); border-color: var(--ink); }
.ws.now { border-color: var(--ink); background: var(--marigold); color: var(--ink); font-weight: 700; }
.ws.now b { background: var(--ink); color: var(--paper); border-color: var(--ink); }

@media (max-width: 640px) {
    .row-2 { grid-template-columns: 1fr; }
}
</style>
