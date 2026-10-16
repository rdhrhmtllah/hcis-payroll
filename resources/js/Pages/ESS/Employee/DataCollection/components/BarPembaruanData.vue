<template>
    <HcisAlert
        variant="info"
        appearance="soft"
        size="sm"
        icon="bi bi-megaphone-fill"
        title="Data karyawanmu belum diperbarui"
        class="pd-bar"
    >
        <template #actions>
            <HcisButton
                variant="primary"
                size="sm"
                :label="sedangMembuka ? 'Membuka…' : 'Isi sekarang'"
                :trailing-icon="sedangMembuka ? undefined : 'bi bi-arrow-right'"
                :loading="sedangMembuka"
                :disabled="sedangMembuka"
                data-uji="bar-isi"
                @click="handleIsi"
            />
        </template>
    </HcisAlert>
</template>

<script setup>
import { ref } from 'vue';
import { HcisAlert, HcisButton } from '@/components/ui';

/**
 * Pengingat menetap di atas halaman, untuk orang yang masih bisa menunda.
 */
defineProps({
    status: { type: Object, required: true },
});

const emit = defineEmits(['isi']);

const sedangMembuka = ref(false);

const handleIsi = () => {
    sedangMembuka.value = true;
    emit('isi');
};
</script>

<style scoped>
.pd-bar {
    margin-bottom: var(--hcis-space-5);
}
</style>
