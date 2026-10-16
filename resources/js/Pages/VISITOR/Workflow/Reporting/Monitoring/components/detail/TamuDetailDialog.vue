<template>
    <TamuReviewModal
        :show="show"
        :tamu-id="idTamu"
        detail-endpoint="/laporanvisitor-detail-tamu"
        read-only
        @close="$emit('close')"
    />
</template>

<script>
import TamuReviewModal from '@/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue';

export default {
    name: 'TamuDetailDialog',
    components: { TamuReviewModal },
    props: {
        show: { type: Boolean, default: false },
        idTamu: { type: [Number, String], default: null },
    },
    emits: ['close'],
};
</script>
