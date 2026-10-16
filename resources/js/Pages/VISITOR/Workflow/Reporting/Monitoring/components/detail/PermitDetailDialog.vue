<template>
    <PermitReviewModal
        :show="show"
        :permit-id="idPermit"
        detail-endpoint="/laporanvisitor-detail-permit"
        read-only
        @close="$emit('close')"
        revoke-tersedia
        cetak-tersedia
        @minta-revoke="$emit('minta-revoke', $event)"
    />
</template>

<script>
import PermitReviewModal from '@/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue';

export default {
    name: 'PermitDetailDialog',
    components: { PermitReviewModal },
    props: {
        show: { type: Boolean, default: false },
        idPermit: { type: [Number, String], default: null },
    },
    emits: ['close', 'minta-revoke'],
};
</script>
