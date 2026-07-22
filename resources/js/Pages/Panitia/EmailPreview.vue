<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    pengirim: { type: Object, default: () => ({}) },
});

const dipilih = ref(props.templates[0]?.key ?? null);
const lebar = ref('desktop');
const mode = ref('pratinjau');

const aktif = computed(() => props.templates.find((t) => t.key === dipilih.value) ?? null);

const form = useForm({ subject: aktif.value?.subject ?? '', isi: aktif.value?.isi ?? '' });

// Berganti template harus memuat isinya, bukan menyisakan draf template sebelumnya.
watch(aktif, (t) => {
    if (!t) return;
    form.clearErrors();
    form.subject = t.subject;
    form.isi = t.isi;
    draf.value = null;
});

/* ------------------------------------------------- Pratinjau langsung */

const draf = ref(null);
let tunda = null;

/**
 * Draf dikirim ke server untuk dirender, bukan ditempel apa adanya ke iframe:
 * penyaringan tag dan penggantian placeholder hanya ada di sisi server, jadi
 * pratinjau yang dirakit di peramban akan berbohong soal hasil akhirnya.
 */
async function segarkanDraf() {
    const res = await fetch(`/panitia/pratinjau-email/${dipilih.value}/draf`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(
                (document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] ?? ''
            ),
        },
        body: JSON.stringify({ isi: form.isi }),
    });

    if (res.ok) draf.value = await res.text();
}

watch(() => form.isi, () => {
    clearTimeout(tunda);
    tunda = setTimeout(segarkanDraf, 400);
});

onBeforeUnmount(() => clearTimeout(tunda));

const adaPerubahan = computed(
    () => aktif.value && (form.subject !== aktif.value.subject || form.isi !== aktif.value.isi)
);

function simpan() {
    form.patch(`/panitia/pratinjau-email/${dipilih.value}`, {
        preserveScroll: true,
        onSuccess: () => { draf.value = null; },
    });
}

function pulihkan() {
    if (!window.confirm('Kembalikan template ini ke isi bawaan? Suntingan yang sekarang akan hilang.')) return;

    router.delete(`/panitia/pratinjau-email/${dipilih.value}`, {
        preserveScroll: true,
        onSuccess: () => { draf.value = null; },
    });
}

function sisip(tanda) {
    form.isi += `\n${tanda}`;
}

const lebarPx = computed(() => (lebar.value === 'mobile' ? 380 : 640));
</script>

<template>
    <Head title="Template Email" />

    <PanelLayout
        crumb="Khusus Developer"
        title="Template Email"
        lede="Sunting isi email dan lihat hasilnya persis seperti yang diterima peserta. Halaman ini tidak mengirim apa pun."
    >
        <template #actions>
            <Link class="btn btn--ghost btn--sm" href="/panitia/pengaturan">← Pengaturan</Link>
        </template>

        <!-- Pemilih template: satu baris di atas, supaya kolom di bawahnya
             punya lebar penuh untuk editor dan pratinjau berdampingan. -->
        <div class="panel" style="margin-bottom:20px">
            <div class="pilih">
                <button
                    v-for="t in templates" :key="t.key"
                    type="button" class="tab" :class="{ 'is-on': t.key === dipilih }"
                    @click="dipilih = t.key"
                >
                    <span class="tab-judul">{{ t.judul }}</span>
                    <span class="tab-kapan">{{ t.kapan }}</span>
                    <span v-if="t.diubah" class="badge badge--waiting tab-tanda">Disunting</span>
                </button>
            </div>
        </div>

        <div class="dua-kolom">
            <!-- Editor -->
            <div class="panel">
                <div class="panel-head-row">
                    <div>
                        <h2 class="panel-title">Isi Email</h2>
                        <p class="panel-sub">
                            HTML sederhana. Tag berbahaya otomatis dibuang saat disimpan.
                        </p>
                    </div>
                    <button
                        v-if="aktif?.diubah" type="button"
                        class="btn btn--ghost btn--sm" @click="pulihkan"
                    >↺ Bawaan</button>
                </div>

                <form @submit.prevent="simpan">
                    <div class="field">
                        <label for="subject">Judul Email <span class="req">*</span></label>
                        <input
                            id="subject" v-model="form.subject" type="text" class="input"
                            :class="{ 'has-error': form.errors.subject }" required
                        />
                        <p v-if="form.errors.subject" class="error">{{ form.errors.subject }}</p>
                    </div>

                    <div class="field">
                        <label for="isi">Badan Email <span class="req">*</span></label>
                        <textarea
                            id="isi" v-model="form.isi" class="textarea kode-edit"
                            :class="{ 'has-error': form.errors.isi }"
                            rows="16" spellcheck="false" required
                        ></textarea>
                        <p v-if="form.errors.isi" class="error">{{ form.errors.isi }}</p>
                    </div>

                    <div class="sisip">
                        <span class="sisip-label">Sisipkan:</span>
                        <button type="button" @click="sisip('<p>Tulis di sini.</p>')">Paragraf</button>
                        <button type="button" @click="sisip('[tombol]Teks Tombol[/tombol]')">Tombol</button>
                        <button type="button" @click="sisip('[kode]')">Kotak kode</button>
                        <button type="button" @click="sisip('[catatan]Catatan penting.[/catatan]')">Kotak catatan</button>
                    </div>

                    <div class="kolom-daftar">
                        <span class="sisip-label">Data yang bisa dipakai:</span>
                        <button
                            v-for="(ket, tanda) in aktif?.kolom ?? {}" :key="tanda"
                            type="button" class="kolom-chip" :title="ket" @click="sisip(tanda)"
                        >{{ tanda }}</button>
                    </div>

                    <div class="simpan-bar">
                        <span v-if="aktif?.diubah_pada" class="jejak">
                            Terakhir disunting {{ aktif.diubah_pada }}
                            <template v-if="aktif.diubah_oleh">oleh {{ aktif.diubah_oleh }}</template>
                        </span>
                        <span v-else class="jejak">Masih memakai isi bawaan.</span>

                        <button type="submit" class="btn btn--sm" :disabled="form.processing || !adaPerubahan">
                            {{ form.processing ? 'Menyimpan…' : 'Simpan Template' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Pratinjau -->
            <div class="panel" style="padding:0;overflow:hidden">
                <div class="bar">
                    <div class="bar-kiri">
                        <span class="judul">Pratinjau</span>
                        <span class="kapan">
                            {{ adaPerubahan ? 'Menampilkan draf yang belum disimpan' : 'Menampilkan isi tersimpan' }}
                        </span>
                    </div>
                    <div class="lebar-pilih">
                        <button type="button" :class="{ 'is-on': lebar === 'desktop' }" @click="lebar = 'desktop'">🖥</button>
                        <button type="button" :class="{ 'is-on': lebar === 'mobile' }" @click="lebar = 'mobile'">📱</button>
                    </div>
                </div>

                <div class="panggung">
                    <!-- sandbox="" mematikan skrip di dalam bingkai: isi ini datang
                         dari kolom sunting, jadi diperlakukan sebagai tidak tepercaya. -->
                    <iframe
                        v-if="draf !== null"
                        :key="'draf-' + lebar"
                        :srcdoc="draf"
                        :style="{ width: lebarPx + 'px' }"
                        title="Pratinjau draf email"
                        sandbox=""
                    ></iframe>
                    <iframe
                        v-else-if="aktif"
                        :key="aktif.key + lebar"
                        :src="`/panitia/pratinjau-email/${aktif.key}`"
                        :style="{ width: lebarPx + 'px' }"
                        title="Pratinjau email"
                        sandbox=""
                    ></iframe>
                </div>
            </div>
        </div>

        <div class="panel" style="margin-top:20px">
            <h2 class="panel-title">Dikirim Sebagai</h2>
            <dl class="dl">
                <div><dt>Nama</dt><dd>{{ pengirim.nama || '—' }}</dd></div>
                <div><dt>Alamat</dt><dd>{{ pengirim.alamat || '—' }}</dd></div>
                <div>
                    <dt>Gateway</dt>
                    <dd>
                        <span :class="pengirim.aktif ? 'badge badge--confirmed' : 'badge badge--pending'">
                            {{ pengirim.aktif ? 'Aktif' : 'Belum aktif' }}
                        </span>
                    </dd>
                </div>
            </dl>
            <p v-if="!pengirim.aktif" class="help" style="margin-top:12px">
                Selama gateway belum aktif, email hanya ditulis ke log server — desainnya tetap seperti pratinjau ini.
            </p>
        </div>
    </PanelLayout>
</template>

<style scoped>
/* Editor dan pratinjau berdampingan di layar lebar, bertumpuk di layar sempit. */
.dua-kolom { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; align-items: start; }
@media (max-width: 1100px) { .dua-kolom { grid-template-columns: minmax(0, 1fr); } }

.pilih { display: flex; gap: 10px; flex-wrap: wrap; }
.tab {
    flex: 1; min-width: 220px; text-align: left; cursor: pointer; font: inherit;
    display: flex; flex-direction: column; gap: 2px;
    padding: 12px 14px; border: 1px solid var(--edge); border-radius: 10px; background: var(--surface);
}
.tab:hover { border-color: var(--edge-strong); }
.tab.is-on { border-color: var(--ink); box-shadow: inset 3px 0 0 var(--flame); }
.tab-judul { font-weight: 800; font-size: .88rem; color: var(--txt); }
.tab-kapan { font-size: .76rem; color: var(--txt-soft); line-height: 1.45; }
.tab-tanda { align-self: flex-start; margin-top: 4px; }

.kode-edit { font-family: 'Space Mono', monospace; font-size: .8rem; line-height: 1.65; white-space: pre; overflow-x: auto; }

.sisip, .kolom-daftar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.sisip-label { font-size: .74rem; letter-spacing: .06em; text-transform: uppercase; color: var(--txt-soft); font-weight: 700; }
.sisip button, .kolom-chip {
    cursor: pointer; font: inherit; font-size: .76rem; font-weight: 700;
    padding: .3rem .6rem; border: 1px solid var(--edge-strong); border-radius: 7px;
    background: var(--surface-sunk); color: var(--txt);
}
.sisip button:hover, .kolom-chip:hover { border-color: var(--ink); }
.kolom-chip { font-family: 'Space Mono', monospace; }

.simpan-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid var(--edge); }
.jejak { font-size: .78rem; color: var(--txt-soft); }

.bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--edge); background: var(--surface-sunk); }
.bar-kiri { display: flex; flex-direction: column; }
.judul { font-weight: 800; font-size: .95rem; }
.kapan { font-size: .78rem; color: var(--txt-soft); }

.lebar-pilih { display: flex; gap: 4px; background: var(--surface); border: 1px solid var(--edge-strong); border-radius: 9px; padding: 3px; }
.lebar-pilih button { border: none; background: none; cursor: pointer; font-size: .9rem; padding: .25rem .55rem; border-radius: 6px; }
.lebar-pilih button.is-on { background: var(--ink); }

.panggung { background: #E7E3DA; padding: 18px; display: flex; justify-content: center; overflow-x: auto; }
.panggung iframe { max-width: 100%; height: 720px; border: 1px solid var(--edge-strong); border-radius: 10px; background: #fff; flex: none; }
</style>
