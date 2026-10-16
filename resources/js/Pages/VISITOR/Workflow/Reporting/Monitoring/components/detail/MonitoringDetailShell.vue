<template>
    <HcisDialog
        :model-value="show"
        :title="judul"
        :description="keterangan"
        :sentiment="sentiment"
        size="lg"
        variant="glass"
        @update:model-value="(v) => !v && $emit('close')"
        @close="$emit('close')"
    >
        <div v-if="memuat" class="md-muat">
            <HcisSkeleton :lines="6" type="text" />
        </div>

        <HcisAlert
            v-else-if="galat"
            variant="danger"
            appearance="glass"
            title="Detail tidak dapat dimuat"
            :description="galat"
            icon="exclamation-octagon-fill"
        />

        <div v-else class="md-isi">
            <slot />
        </div>

        <template #footer>
            <slot name="footer" />
        </template>
    </HcisDialog>
</template>

<script>
import { HcisAlert, HcisDialog, HcisSkeleton } from '@/components/ui';

/**
 * Kerangka dialog detail monitoring.
 *
 * Memuat / galat / isi adalah tiga keadaan yang SELALU sama di kedua ranah,
 * dan menuliskannya dua kali berarti salah satunya cepat atau lambat kehilangan
 * salah satu keadaan — biasanya keadaan galat, yang paling jarang terlihat saat
 * membangun dan paling membingungkan saat terjadi.
 */
export default {
    name: 'MonitoringDetailShell',
    components: { HcisAlert, HcisDialog, HcisSkeleton },
    props: {
        show: { type: Boolean, default: false },
        judul: { type: String, required: true },
        keterangan: { type: String, default: '' },
        sentiment: { type: String, default: 'brand' },
        memuat: { type: Boolean, default: false },
        galat: { type: String, default: '' },
    },
    emits: ['close'],
};
</script>

<style scoped>
.md-muat {
    padding: var(--hcis-space-3, 0.75rem) 0;
}

.md-isi {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}
</style>
