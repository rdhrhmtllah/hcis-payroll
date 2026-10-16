<template>
    <HcisDialog
        :model-value="modelValue"
        :title="detail?.nama || 'Detail Hasil Evaluasi Anggota'"
        kicker="HASIL ANGGOTA TIM"
        :description="detail ? `${detail.kode_karyawan || 'Kode belum tersedia'} · ${detail.nama_jabatan || 'Jabatan belum tersedia'}` : ''"
        icon="person-vcard-fill"
        sentiment="teal"
        variant="glass"
        size="full"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <PaLeaderMemberDetailView
            :detail="detail"
            :loading="loading"
            :error="error"
            :error-status="errorStatus"
            :error-code="errorCode"
            :request-id="requestId"
            :team-members="teamMembers"
            :show-back-action="false"
            @select-member="$emit('select-member', $event)"
            @retry="$emit('retry')"
        />

        <template #footer>
            <HcisButton
                variant="secondary"
                label="Tutup"
                size="sm"
                @click="$emit('update:modelValue', false)"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { HcisButton, HcisDialog } from '@/components/ui';
import PaLeaderMemberDetailView from './PaLeaderMemberDetailView.vue';

defineOptions({ name: 'PaLeaderMemberDetailDialog' });

defineProps({
    modelValue: { type: Boolean, default: false },
    detail: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    teamMembers: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue', 'select-member', 'retry']);
</script>
