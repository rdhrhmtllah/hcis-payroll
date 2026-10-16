<template>
    <div class="sop-stack bab-publik">
        <div v-if="memuat" class="bpb-memuat">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <span>Memuat jangkauan dokumen publik…</span>
        </div>

        <HcisAlert v-else-if="galat" variant="danger" appearance="subtle" :description="galat" />

        <HcisEmptyState
            v-else-if="!data.Jangkauan.Dokumen_Publik"
            icon="megaphone"
            title="Belum Ada Dokumen Publik"
            description="Belum ada dokumen publik yang beredar."
            variant="flat"
            sentiment="neutral"
            size="sm"
        />

        <HcisCard
            v-else
            variant="glass"
            sentiment="teal"
            size="sm"
            icon="broadcast"
            pattern="grid"
            title="Jangkauan Dokumen Publik"
            description="Tingkat ketertarikan dan pembacaan sukarela atas dokumen publik."
        >
            <PanelJangkauan :jangkauan="data.Jangkauan" />
        </HcisCard>
    </div>
</template>

<script>
import { HcisCard, HcisAlert, HcisEmptyState } from '@/components/ui';
import { ambil } from './agregat.js';
import PanelJangkauan from './panel/PanelJangkauan.vue';

const KOSONG = {
    Jangkauan: { Karyawan_Aktif: 0, Tersentuh: 0, Membuka: 0, Persen: 0, Dokumen_Publik: 0 },
};

export default {
    name: 'BabJangkauanPublik',
    components: { HcisCard, HcisAlert, HcisEmptyState, PanelJangkauan },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    data() {
        return { data: { ...KOSONG }, memuat: false, galat: '' };
    },
    watch: {
        saringan: { immediate: true, deep: true, handler() { this.muat(); } },
    },
    methods: {
        async muat() {
            this.memuat = true;
            this.galat = '';
            try {
                this.data = { ...KOSONG, ...(await ambil('/dokumen/jangkauan-publik', this.saringan)) };
            } catch (e) {
                this.data = { ...KOSONG };
                this.galat = e?.message || 'Gagal memuat jangkauan dokumen publik.';
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
.bab-publik {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.bpb-memuat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}
</style>
