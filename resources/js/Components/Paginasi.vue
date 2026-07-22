<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    /** Objek paginator Laravel: { links, from, to, total, … } */
    data: { type: Object, required: true },
    /** Kata benda untuk baris hitungan, mis. "pendaftaran" atau "peserta". */
    label: { type: String, default: 'baris' },
    /**
     * Halaman yang menyimpan pilihan (mis. centang cetak BIB) harus mempertahankan
     * state komponennya saat berpindah halaman, kalau tidak pilihannya hilang.
     */
    preserveState: { type: Boolean, default: false },
});

// Kurang dari empat tautan berarti hanya ada satu halaman: « Sebelumnya, 1, Berikutnya ».
const banyakHalaman = computed(() => (props.data.links?.length ?? 0) > 3);
</script>

<template>
    <div v-if="data.total">
        <nav v-if="banyakHalaman" class="pager">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, i) in data.links"
                :key="i"
                :href="link.url"
                :preserve-state="preserveState"
                :preserve-scroll="true"
                :class="{ 'is-active': link.active, 'is-off': !link.url }"
                v-html="link.label"
            />
        </nav>

        <p class="tally mono">
            Menampilkan {{ data.from ?? 0 }}–{{ data.to ?? 0 }} dari {{ data.total }} {{ label }}
        </p>
    </div>
</template>

<style scoped>
.tally {
    text-align: center;
    font-size: .76rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--txt-soft, var(--ink-soft));
    margin-top: 16px;
}
</style>
