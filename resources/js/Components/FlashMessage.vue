<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const dismissed = ref(null);

const flash = computed(() => {
    const data = page.props.flash ?? {};

    if (data.success) return { type: 'success', text: data.success };
    if (data.error) return { type: 'error', text: data.error };

    return null;
});

const visible = computed(() => flash.value && dismissed.value !== flash.value.text);

watch(flash, () => {
    dismissed.value = null;
});
</script>

<template>
    <div v-if="visible" :class="['flash', `flash--${flash.type}`]" role="status">
        <span aria-hidden="true">{{ flash.type === 'success' ? '✔' : '⚠' }}</span>
        <span style="flex:1">{{ flash.text }}</span>
        <button
            type="button"
            aria-label="Tutup pesan"
            style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.1rem;line-height:1"
            @click="dismissed = flash.text"
        >
            ×
        </button>
    </div>
</template>
