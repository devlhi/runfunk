<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { rupiah } from '../lib/format';

const props = defineProps({
    /** Null saat tertutup; berisi { id, code, participant_name, amount } saat terbuka. */
    target: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({ metode: 'tunai', catatan: '' });

// Dialognya dipakai ulang untuk banyak peserta di halaman daftar. Tanpa ini,
// catatan milik peserta sebelumnya masih tertinggal saat dibuka untuk peserta lain.
watch(
    () => props.target?.id,
    (id) => {
        if (id) {
            form.reset();
            form.clearErrors();
        }
    }
);

function submit() {
    form.post(`/panitia/pendaftaran/${props.target.id}/konfirmasi-manual`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="target" class="modal-veil" @click.self="emit('close')">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="km-judul">
                <h3 id="km-judul">Konfirmasi pembayaran tanpa bukti</h3>
                <p class="modal-sub">
                    Untuk peserta yang membayar tunai ke panitia, atau yang dananya sudah terlihat di
                    mutasi rekening tanpa pernah mengunggah bukti. Nomor BIB langsung terbit dan
                    laporannya dikirim ke WhatsApp admin.
                </p>

                <div class="km-target">
                    <div>
                        <div class="km-nama">{{ target.participant_name }}</div>
                        <div class="km-kode mono">{{ target.code }}</div>
                    </div>
                    <span class="km-nominal">{{ rupiah(target.amount) }}</span>
                </div>

                <form @submit.prevent="submit">
                    <div class="field">
                        <label for="km-metode">Cara Pembayaran <span class="req">*</span></label>
                        <select id="km-metode" v-model="form.metode" class="select">
                            <option value="tunai">Tunai langsung ke panitia</option>
                            <option value="manual">Sudah masuk rekening, dicek panitia</option>
                        </select>
                        <p v-if="form.errors.metode" class="error">{{ form.errors.metode }}</p>
                    </div>

                    <div class="field">
                        <label for="km-catatan">Catatan Verifikasi <span class="req">*</span></label>
                        <textarea
                            id="km-catatan" v-model="form.catatan" class="textarea"
                            :class="{ 'has-error': form.errors.catatan }"
                            placeholder="Contoh: bayar tunai di sekretariat IKA, diterima bendahara, kuitansi no. 014."
                            required
                        ></textarea>
                        <p v-if="form.errors.catatan" class="error">{{ form.errors.catatan }}</p>
                        <p v-else class="help">
                            Wajib diisi. Tidak ada bukti digital yang bisa dicek ulang, jadi catatan ini
                            satu-satunya jejak untuk panitia lain.
                        </p>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn--ghost btn--sm" @click="emit('close')">
                            Batal
                        </button>
                        <button type="submit" class="btn btn--mint btn--sm" :disabled="form.processing">
                            {{ form.processing ? 'Memproses…' : '✔ Konfirmasi & Terbitkan BIB' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.km-target {
    display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
    border: 2.5px solid var(--ink); border-radius: 14px; background: var(--paper-2);
    padding: 14px 16px; margin-bottom: 20px;
}
.km-nama { font-weight: 800; }
.km-kode { font-size: .76rem; color: var(--ink-soft); }
.km-nominal { font-family: 'Big Shoulders Display'; font-weight: 900; font-size: 1.6rem; }
</style>
