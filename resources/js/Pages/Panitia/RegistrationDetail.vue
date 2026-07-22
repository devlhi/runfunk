<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import KonfirmasiManual from '../../Components/KonfirmasiManual.vue';
import { rupiah, statusBadgeClass } from '../../lib/format';

const props = defineProps({
    registration: { type: Object, required: true },
});

const rejecting = ref(null);
const konfirmasiManual = ref(null);

/** Selama belum lunas atau batal, panitia masih boleh mengakui pembayaran tanpa bukti. */
const bisaKonfirmasiManual = computed(
    () => ['pending_payment', 'rejected'].includes(props.registration.status)
);

function bukaKonfirmasiManual() {
    konfirmasiManual.value = {
        id: props.registration.id,
        code: props.registration.code,
        participant_name: props.registration.participant_name,
        amount: props.registration.amount,
    };
}

const rejectForm = useForm({ reason: '' });
const noteForm = useForm({ panitia_note: props.registration.panitia_note ?? '' });

/** Bukti terbaru yang masih menunggu keputusan panitia. */
const pendingPayment = computed(
    () => props.registration.payments?.find((p) => p.status === 'pending') ?? null
);

function approve(paymentId) {
    router.post(`/panitia/pembayaran/${paymentId}/setujui`, {}, { preserveScroll: true });
}

function submitReject() {
    rejectForm.post(`/panitia/pembayaran/${rejecting.value}/tolak`, {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            rejectForm.reset();
        },
    });
}

function saveNote() {
    noteForm.patch(`/panitia/pendaftaran/${props.registration.id}/catatan`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Peserta ${registration.code}`" />

    <PanelLayout :crumb="`Data Peserta / ${registration.code}`" :title="registration.participant_name">
        <template #actions>
            <Link class="btn btn--ghost btn--sm" href="/panitia/pendaftaran">← Kembali</Link>
        </template>

        <div class="head-badges">
            <span :class="statusBadgeClass(registration.status)">{{ registration.status_label }}</span>
            <span v-if="registration.bib_number" class="badge badge--bib">BIB {{ registration.bib_number }}</span>
            <span class="badge">{{ registration.distance_label }}</span>
            <span class="badge mono">{{ registration.code }}</span>
        </div>

        <div class="grid grid-side">
            <div>
                <!-- Verifikasi pembayaran -->
                <div class="panel panel--pop">
                    <h2 class="panel-title">Verifikasi Pembayaran</h2>
                    <p class="panel-sub">
                        Cocokkan nominal dan nama pengirim dengan mutasi rekening sebelum menyetujui.
                        Nomor BIB terbit otomatis saat disetujui.
                    </p>

                    <div v-if="registration.payments?.length" class="proof-list">
                        <article v-for="pay in registration.payments" :key="pay.id" class="proof">
                            <div class="proof-head">
                                <div>
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
                                    <span class="proof-time mono">Dikirim {{ pay.created_at }}</span>
                                </div>
                                <span class="proof-amount">{{ rupiah(pay.amount) }}</span>
                            </div>

                            <dl class="dl proof-dl">
                                <div><dt>Metode</dt><dd>{{ pay.method_label }}</dd></div>
                                <div><dt>Nama Pengirim</dt><dd>{{ pay.sender_name }}</dd></div>
                                <div><dt>Bank / E-Wallet</dt><dd>{{ pay.sender_bank || '—' }}</dd></div>
                                <div><dt>Tanggal Transfer</dt><dd>{{ pay.paid_at || '—' }}</dd></div>
                            </dl>

                            <p v-if="pay.confirm_note" class="proof-note">
                                Dikonfirmasi panitia tanpa bukti unggahan — {{ pay.confirm_note }}
                            </p>

                            <div v-if="pay.proof_url" class="proof-file">
                                <a v-if="!pay.is_pdf" :href="pay.proof_url" target="_blank" rel="noopener">
                                    <img :src="pay.proof_url" alt="Bukti pembayaran" />
                                </a>
                                <a v-else class="btn btn--ghost btn--sm" :href="pay.proof_url" target="_blank" rel="noopener">
                                    Buka berkas PDF bukti bayar
                                </a>
                            </div>

                            <p v-if="pay.reject_reason" class="proof-reason">
                                Alasan penolakan: {{ pay.reject_reason }}
                            </p>
                            <p v-if="pay.reviewer" class="proof-time mono">Diperiksa oleh {{ pay.reviewer }}</p>

                            <div v-if="pay.status === 'pending'" class="proof-actions">
                                <button type="button" class="btn btn--mint btn--sm" @click="approve(pay.id)">
                                    ✔ Setujui &amp; Terbitkan BIB
                                </button>
                                <button type="button" class="btn btn--danger btn--sm" @click="rejecting = pay.id">
                                    ✕ Tolak Bukti
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="empty">
                        <div class="big">Belum ada bukti bayar</div>
                        <p>Peserta ini belum mengunggah bukti pembayaran apa pun.</p>
                    </div>

                    <!--
                        Jalur untuk pembayaran yang tidak lewat unggahan: tunai ke panitia,
                        atau dana yang sudah terlihat di mutasi rekening.
                    -->
                    <div v-if="bisaKonfirmasiManual" class="manual-box">
                        <div>
                            <div class="manual-title">Peserta bayar tanpa unggah bukti?</div>
                            <p class="manual-sub">
                                Tunai ke panitia atau dananya sudah masuk rekening. BIB langsung terbit
                                dan laporannya dikirim ke WhatsApp admin.
                            </p>
                        </div>
                        <button type="button" class="btn btn--sm" @click="bukaKonfirmasiManual">
                            ✔ Konfirmasi Manual
                        </button>
                    </div>
                </div>

                <!-- Catatan panitia -->
                <div class="panel">
                    <h2 class="panel-title">Catatan Panitia</h2>
                    <p class="panel-sub">Terlihat oleh peserta di halaman detail pendaftarannya.</p>

                    <form @submit.prevent="saveNote">
                        <div class="field">
                            <label for="panitia_note">Catatan</label>
                            <textarea
                                id="panitia_note" v-model="noteForm.panitia_note" class="textarea"
                                placeholder="Contoh: race pack sudah diambil pada 29 Okt."
                            ></textarea>
                            <p v-if="noteForm.errors.panitia_note" class="error">{{ noteForm.errors.panitia_note }}</p>
                        </div>
                        <button type="submit" class="btn btn--sm" :disabled="noteForm.processing">
                            {{ noteForm.processing ? 'Menyimpan…' : 'Simpan Catatan' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Data peserta -->
            <aside>
                <div class="panel">
                    <h2 class="panel-title">Data Peserta</h2>
                    <dl class="dl" style="grid-template-columns:1fr">
                        <div><dt>Nama</dt><dd>{{ registration.participant_name }}</dd></div>
                        <div><dt>Kategori</dt><dd>{{ registration.category }}</dd></div>
                        <div><dt>Jersey</dt><dd>{{ registration.jersey_size }}</dd></div>
                        <div><dt>Jenis Kelamin</dt><dd>{{ registration.gender }}</dd></div>
                        <div><dt>Tanggal Lahir</dt><dd>{{ registration.birth_date }}</dd></div>
                        <div><dt>Golongan Darah</dt><dd>{{ registration.blood_type || '—' }}</dd></div>
                        <div><dt>WhatsApp</dt><dd>{{ registration.participant_phone }}</dd></div>
                        <div><dt>Email</dt><dd>{{ registration.participant_email }}</dd></div>
                        <div><dt>Kota</dt><dd>{{ registration.city }}</dd></div>
                        <div><dt>Alamat</dt><dd>{{ registration.address || '—' }}</dd></div>
                        <div><dt>Komunitas</dt><dd>{{ registration.community || '—' }}</dd></div>
                        <div>
                            <dt>Kontak Darurat</dt>
                            <dd>{{ registration.emergency_name }} · {{ registration.emergency_phone }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Jejak Akun</h2>
                    <dl class="dl" style="grid-template-columns:1fr">
                        <div><dt>Akun Pendaftar</dt><dd>{{ registration.account_name }}</dd></div>
                        <div><dt>Email Akun</dt><dd>{{ registration.account_email }}</dd></div>
                        <div><dt>Didaftarkan</dt><dd>{{ registration.created_at }}</dd></div>
                        <div v-if="registration.verified_by">
                            <dt>Diverifikasi</dt>
                            <dd>{{ registration.verified_by }} · {{ registration.verified_at }}</dd>
                        </div>
                        <div><dt>Total Biaya</dt><dd>{{ rupiah(registration.amount) }}</dd></div>
                    </dl>
                </div>
            </aside>
        </div>

        <KonfirmasiManual :target="konfirmasiManual" @close="konfirmasiManual = null" />

        <!-- Modal tolak -->
        <Teleport to="body">
            <div v-if="rejecting" class="modal-veil" @click.self="rejecting = null">
                <div class="modal" role="dialog" aria-modal="true">
                    <h3>Tolak bukti pembayaran</h3>
                    <p class="modal-sub">
                        Alasan ini ditampilkan ke peserta supaya mereka tahu apa yang perlu diperbaiki,
                        lalu mereka bisa mengunggah bukti baru.
                    </p>

                    <form @submit.prevent="submitReject">
                        <div class="field">
                            <label for="reason">Alasan Penolakan <span class="req">*</span></label>
                            <textarea
                                id="reason" v-model="rejectForm.reason" class="textarea"
                                :class="{ 'has-error': rejectForm.errors.reason }"
                                placeholder="Contoh: nominal transfer kurang Rp 50.000 dari total tagihan."
                                required
                            ></textarea>
                            <p v-if="rejectForm.errors.reason" class="error">{{ rejectForm.errors.reason }}</p>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn--ghost btn--sm" @click="rejecting = null">
                                Batal
                            </button>
                            <button type="submit" class="btn btn--danger btn--sm" :disabled="rejectForm.processing">
                                {{ rejectForm.processing ? 'Mengirim…' : 'Tolak Bukti' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PanelLayout>
</template>

<style scoped>
.head-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }

.proof-list { display: flex; flex-direction: column; gap: 18px; }
.proof { border: 2.5px solid var(--ink); border-radius: 16px; padding: 20px; background: var(--paper); }
.proof-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
.proof-time { font-size: .72rem; color: var(--ink-soft); margin-left: .6rem; }
.proof-amount { font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 1.8rem; }
.proof-dl { margin-bottom: 16px; }
.proof-file img { display: block; width: 100%; max-height: 420px; object-fit: contain; border: 2.5px solid var(--ink); border-radius: 12px; background: var(--paper-2); }
.proof-reason { margin-top: 12px; font-size: .86rem; font-weight: 600; color: var(--danger); border-left: 3px solid var(--danger); padding-left: 10px; }
.proof-note { margin-bottom: 12px; font-size: .86rem; font-weight: 600; color: var(--ink-soft); border-left: 3px solid var(--marigold); padding-left: 10px; }

.manual-box { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-top: 20px; padding-top: 20px; border-top: 2px dashed var(--ink); }
.manual-title { font-weight: 800; }
.manual-sub { font-size: .84rem; color: var(--ink-soft); margin-top: 2px; max-width: 46ch; }
.proof-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; padding-top: 18px; border-top: 2px dashed var(--ink); }
</style>
