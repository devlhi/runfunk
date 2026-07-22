<script setup>
import { reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import { rupiah } from '../../lib/format';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

/** Satu form per kategori supaya tiap kartu bisa disimpan sendiri-sendiri. */
const forms = reactive(
    Object.fromEntries(
        props.categories.map((cat) => [
            cat.id,
            useForm({
                name: cat.name,
                tagline: cat.tagline ?? '',
                price: cat.price,
                quota: cat.quota,
                is_active: cat.is_active,
            }),
        ])
    )
);

function save(cat) {
    forms[cat.id].patch(`/panitia/kategori/${cat.slug}`, { preserveScroll: true });
}

function fillPercent(taken, quota) {
    if (!quota) return 0;

    return Math.min(100, Math.round((taken / quota) * 100));
}
</script>

<template>
    <Head title="Kategori & Kuota" />

    <PanelLayout
        crumb="Panel Panitia"
        title="Kategori &amp; Kuota"
        lede="Ubah biaya pendaftaran, kuota, dan status buka/tutup tiap kategori. Perubahan langsung terlihat di landing page."
    >

        <div class="grid grid-2">
            <div v-for="cat in categories" :key="cat.id" class="panel panel--pop">
                <div class="cat-head">
                    <div>
                        <h2 class="panel-title">{{ cat.name }}</h2>
                        <p class="panel-sub" style="margin-bottom:0">
                            {{ cat.confirmed }} peserta lunas · {{ cat.taken }} slot terpakai ·
                            sisa {{ cat.remaining }}
                        </p>
                    </div>
                    <span class="badge" :class="cat.is_active ? 'badge--confirmed' : 'badge--cancelled'">
                        {{ cat.is_active ? 'Dibuka' : 'Ditutup' }}
                    </span>
                </div>

                <div class="meter" :class="cat.distance_label === '10K' ? 'meter--cobalt' : ''" style="margin:16px 0 22px">
                    <i :style="{ width: fillPercent(cat.taken, cat.quota) + '%' }"></i>
                </div>

                <form @submit.prevent="save(cat)">
                    <div class="field">
                        <label :for="`name-${cat.id}`">Nama Kategori</label>
                        <input
                            :id="`name-${cat.id}`" v-model="forms[cat.id].name" type="text" class="input"
                            :class="{ 'has-error': forms[cat.id].errors.name }" required
                        />
                        <p v-if="forms[cat.id].errors.name" class="error">{{ forms[cat.id].errors.name }}</p>
                    </div>

                    <div class="field">
                        <label :for="`tagline-${cat.id}`">Tagline</label>
                        <input
                            :id="`tagline-${cat.id}`" v-model="forms[cat.id].tagline" type="text" class="input"
                            placeholder="Kalimat singkat di kartu bib"
                        />
                    </div>

                    <div class="row-2">
                        <div class="field">
                            <label :for="`price-${cat.id}`">Biaya (Rp)</label>
                            <input
                                :id="`price-${cat.id}`" v-model.number="forms[cat.id].price" type="number"
                                class="input" :class="{ 'has-error': forms[cat.id].errors.price }"
                                min="0" step="1000" required
                            />
                            <p class="help">Saat ini {{ rupiah(cat.price) }}</p>
                            <p v-if="forms[cat.id].errors.price" class="error">{{ forms[cat.id].errors.price }}</p>
                        </div>

                        <div class="field">
                            <label :for="`quota-${cat.id}`">Kuota</label>
                            <input
                                :id="`quota-${cat.id}`" v-model.number="forms[cat.id].quota" type="number"
                                class="input" :class="{ 'has-error': forms[cat.id].errors.quota }"
                                :min="cat.taken" required
                            />
                            <p class="help">Minimal {{ cat.taken }} (slot terpakai)</p>
                            <p v-if="forms[cat.id].errors.quota" class="error">{{ forms[cat.id].errors.quota }}</p>
                        </div>
                    </div>

                    <label class="check" style="margin-bottom:20px">
                        <input v-model="forms[cat.id].is_active" type="checkbox" />
                        <span>Buka pendaftaran untuk kategori ini</span>
                    </label>

                    <button type="submit" class="btn btn--sm" :disabled="forms[cat.id].processing">
                        {{ forms[cat.id].processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                    </button>
                </form>
            </div>
        </div>
    </PanelLayout>
</template>

<style scoped>
.cat-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

@media (max-width: 640px) {
    .row-2 { grid-template-columns: 1fr; }
}
</style>
