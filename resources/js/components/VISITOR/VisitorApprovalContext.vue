<template>
    <HcisContextBanner
        :title="title"
        :subtitle="subtitle"
        :kicker="config.approval_label || `Alur approval ${entityLabel}`"
        :code="config.feature_code || ''"
        :icon="isReady ? 'person-check-fill' : 'exclamation-triangle-fill'"
        :sentiment="isReady ? 'success' : 'warning'"
        :scope-label="modeLabel"
        :scope-variant="isReady ? 'success' : 'warning'"
        :capabilities="capabilities"
        pattern="dots"
    >
        <template v-if="approvalPolicy.needs_approval && approverFlows.length" #actions>
            <div class="visitor-approval-context__approvers" aria-label="Daftar approver">
                <HcisBadge
                    v-for="(approver, index) in approverFlows.slice(0, 3)"
                    :key="`${approver.Kode_Karyawan || approver.Nama || 'approver'}-${index}`"
                    :label="approver.Nama || approver.Jabatan || `Approver ${index + 1}`"
                    variant="success"
                    appearance="glass"
                    size="sm"
                    icon="person-check"
                />
                <HcisBadge
                    v-if="approverFlows.length > 3"
                    :label="`+${approverFlows.length - 3} lainnya`"
                    variant="neutral"
                    size="sm"
                />
            </div>
        </template>
    </HcisContextBanner>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge, HcisContextBanner } from '@/components/ui';

defineOptions({ name: 'VisitorApprovalContext' });

const props = defineProps({
    entityLabel: { type: String, required: true },
    config: { type: Object, default: () => ({}) },
    approvalPolicy: { type: Object, default: () => ({}) },
    approverFlows: { type: Array, default: () => [] },
});

const isReady = computed(() => !props.approvalPolicy.needs_approval || props.approverFlows.length > 0);

const modeLabel = computed(() =>
    props.approvalPolicy.needs_approval ? 'Memerlukan approval' : 'Persetujuan otomatis',
);

const title = computed(() => {
    if (!props.approvalPolicy.needs_approval) {
        return `${props.entityLabel} langsung aktif setelah dikirim`;
    }
    if (isReady.value) {
        return `Alur approval ${props.entityLabel} siap digunakan`;
    }
    return `Approver ${props.entityLabel} belum tersedia`;
});

const subtitle = computed(() => {
    if (!props.approvalPolicy.needs_approval) {
        return 'Sistem akan menyelesaikan persetujuan secara otomatis sesuai konfigurasi aktif.';
    }
    if (isReady.value) {
        return `${props.approverFlows.length} approver tersedia dan pengajuan dapat dikirim.`;
    }
    return 'Pengajuan dinonaktifkan sementara sampai konfigurasi approver tersedia.';
});

const capabilities = computed(() => {
    const items = [
        {
            label: `${props.config.approver_limit || props.approverFlows.length || 0} approver`,
            icon: 'people',
        },
    ];

    if (Object.prototype.hasOwnProperty.call(props.config, 'checklist_required')) {
        items.push({
            label: props.config.checklist_required ? 'Checklist wajib' : 'Checklist opsional',
            icon: 'list-check',
        });
    }

    return items;
});
</script>

<style scoped>
.visitor-approval-context__approvers {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: var(--hcis-space-2, 0.5rem);
}

@media (max-width: 768px) {
    .visitor-approval-context__approvers {
        justify-content: flex-start;
    }
}
</style>
