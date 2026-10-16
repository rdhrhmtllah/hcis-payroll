<template>
    <VisitorWorkspace
        :title="isB2b ? 'Verifikator Tamu B2B' : 'Verifikator Tamu Regular'"
        :subtitle="
            isB2b
                ? 'Validasi kunjungan tamu mitra berkendaraan sebelum Permit COKLAT-nya diajukan.'
                : 'Validasi kedatangan tamu dari antrean sampai riwayat operasional.'
        "
        kicker="Visitor · Verification Desk"
        :icon="isB2b ? 'truck' : 'people-fill'"
        sentiment="brand"
        :stats="statCards"
        :stat-columns="3"
        section-title="Meja verifikasi tamu"
        section-description="Antrean, keputusan, dan riwayat kunjungan tamu."
        section-icon="person-workspace"
    >
        <template v-if="statsError" #context>
            <div class="visitor-page-context">
                <HcisAlert
                    type="warning"
                    title="Ringkasan verifikasi belum dapat diperbarui"
                    :description="statsError"
                    closable
                    @close="statsError = ''"
                />
            </div>
        </template>

        <VerifikatorTamuPanel
            ref="tamuPanelRef"
            :tipe="tipeTamu"
            :api-prefix="apiPrefix"
            :boleh-buka-permit="bolehBukaPermit"
            @stats-updated="loadStats"
        />
    </VisitorWorkspace>
</template>

<script>
import axios from 'axios';
import { HcisAlert } from '@/components/ui';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import { bacaRujukanDetail, bacaJenisDetail } from '@/components/VISITOR/tautanDetail';
import VerifikatorTamuPanel from './components/VerifikatorTamuPanel.vue';

/**
 * Meja verifikasi kunjungan tamu — halamannya sendiri.
 *
 * Pasangan verifikatorPermit.vue; lihat catatan di sana untuk alasan
 * pemisahannya dan untuk sebab angka ringkasannya memakai rincian per domain.
 */
export default {
    name: 'VerifikatorTamuPage',
    components: {
        HcisAlert,
        VisitorWorkspace,
        VerifikatorTamuPanel,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        // Ditetapkan rute (DDL-2026-121): meja Regular dan B2B terpisah.
        tipeTamu: { type: String, default: 'REGULAR' },
        apiPrefix: { type: String, default: 'verifikator-tamu' },
        statsUrl: { type: String, default: '/verifikator-visitor-stats' },
        bolehBukaPermit: { type: Boolean, default: false },
    },
    data() {
        return {
            stats: {
                antrian_tamu: 0,
                disetujui_hari_ini_tamu: 0,
                ditolak_tamu: 0,
            },
            statsLoading: true,
            statsError: '',
        };
    },
    computed: {
        isB2b() {
            return this.tipeTamu === 'B2B';
        },
        statCards() {
            return [
                {
                    label: 'Antrean tamu',
                    value: this.stats.antrian_tamu,
                    suffix: 'Pengajuan',
                    caption: 'Menunggu verifikasi tamu',
                    icon: 'people-fill',
                    sentiment: 'warning',
                    loading: this.statsLoading,
                    interactive: false,
                    pattern: 'dots',
                    layout: 'compact',
                },
                {
                    label: 'Disetujui hari ini',
                    value: this.stats.disetujui_hari_ini_tamu,
                    suffix: 'Kunjungan',
                    caption: 'Terverifikasi & disetujui',
                    icon: 'check-circle-fill',
                    sentiment: 'success',
                    loading: this.statsLoading,
                    interactive: false,
                    pattern: 'crosshair',
                    layout: 'compact',
                },
                {
                    label: 'Total ditolak',
                    value: this.stats.ditolak_tamu,
                    suffix: 'Kunjungan',
                    caption: 'Pengajuan tidak valid',
                    icon: 'x-circle-fill',
                    sentiment: 'danger',
                    loading: this.statsLoading,
                    interactive: false,
                    pattern: 'rings',
                    layout: 'compact',
                },
            ];
        },
    },
    mounted() {
        this.loadStats();
        this.bukaDetailDariTautan();
    },
    methods: {
        /** Lihat catatan pada verifikatorPermit.vue: `jenis` tetap diperiksa. */
        bukaDetailDariTautan() {
            const rujukan = bacaRujukanDetail(window.location.search);
            const jenis = bacaJenisDetail(window.location.search);

            if (!rujukan || jenis === 'permit') {
                return;
            }

            this.$nextTick(() => {
                this.$refs.tamuPanelRef?.openReview({ Id_Transaksi_Tamu: rujukan });
            });
        },
        async loadStats() {
            this.statsLoading = true;
            this.statsError = '';
            try {
                const { data: json } = await axios.get(this.statsUrl);
                if (!json.success) throw new Error('Statistik tidak tersedia.');
                this.stats = json.result;
            } catch (error) {
                this.statsError =
                    'Panel verifikasi tetap dapat digunakan. Muat ulang halaman untuk memperbarui ringkasan.';
            } finally {
                this.statsLoading = false;
            }
        },
    },
};
</script>

<style scoped>
.visitor-page-context {
    display: grid;
    gap: var(--hcis-space-3, 0.75rem);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
</style>
