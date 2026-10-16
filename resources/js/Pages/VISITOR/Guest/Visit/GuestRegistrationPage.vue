<template>
    <VisitorWorkspace
        v-model="activeTab"
        :title="isB2b ? 'Kunjungan Tamu B2B' : 'Kunjungan Tamu Regular'"
        :subtitle="
            isB2b
                ? 'Daftarkan tamu mitra berkendaraan yang membutuhkan Permit COKLAT, lalu pantau persetujuannya.'
                : 'Daftarkan kunjungan tamu, pantau proses persetujuan, dan lihat riwayat tamu dalam satu workspace.'
        "
        :kicker="isB2b ? 'Visitor · Tamu B2B' : 'Visitor · Tamu Regular'"
        :icon="isB2b ? 'truck' : 'person-vcard-fill'"
        sentiment="brand"
        :stats="statCards"
        :tabs="tabs"
        section-title="Registrasi dan buku tamu"
        section-description="Gunakan formulir untuk kunjungan baru atau buka riwayat untuk memantau status terkini."
        section-icon="people-fill"
    >
        <template #context>
            <div class="visitor-page-context">
                <HcisAlert
                    v-if="statsError"
                    variant="warning"
                    title="Ringkasan belum dapat diperbarui"
                    :description="statsError"
                    closable
                    @close="statsError = ''"
                />
            </div>
        </template>

        <template #panel-form>
            <TamuForm
                :tipe="tipeTamu"
                :api-prefix="apiPrefix"
                :server-now="serverNow"
                :aturan-form="aturanForm"
                :b2b-units="b2bUnits"
                :config="config"
                :approval-policy="approvalPolicy"
                :approver-flows="approverFlows"
                @saved="onTamuSaved"
            />
        </template>

        <template #panel-history>
            <TamuHistory
                ref="historyRef"
                :tipe="tipeTamu"
                :api-prefix="apiPrefix"
                @tamu-updated="loadStats"
                @tamu-canceled="loadStats"
                @navigate-to-form="activeTab = 'form'"
            />
        </template>
    </VisitorWorkspace>
</template>

<script>
import { HcisAlert } from '@/components/ui';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import { bacaRujukanDetail } from '@/components/VISITOR/tautanDetail';
import TamuForm from './components/TamuForm.vue';
import TamuHistory from './components/TamuHistory.vue';

export default {
    name: 'TamuPage',
    components: {
        HcisAlert,
        VisitorWorkspace,
        TamuForm,
        TamuHistory,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        config: { type: Object, default: () => ({}) },
        approvalPolicy: { type: Object, default: () => ({}) },
        approverFlows: { type: Array, default: () => [] },
        serverDate: { type: String, default: null },
        serverNow: { type: String, default: null },
        // Ditetapkan rute (DDL-2026-121): halaman Regular dan B2B memakai
        // komponen ini, API-nya berbeda prefiks dan kunci halaman.
        tipeTamu: { type: String, default: 'REGULAR' },
        apiPrefix: { type: String, default: 'transaksitamu' },
        aturanForm: { type: Object, default: () => ({}) },
        // Unit B2B yang boleh diajukan pengguna (matriks departemen, DDL-2026-122).
        b2bUnits: { type: Array, default: () => [] },
    },
    data() {
        return {
            activeTab: 'form',
            stats: {
                menunggu_persetujuan: 0,
                sudah_disetujui: 0,
                selesai_keluar: 0,
            },
            statsLoading: true,
            statsError: '',
        };
    },
    computed: {
        isB2b() {
            return this.tipeTamu === 'B2B';
        },
        tabs() {
            return [
                {
                    key: 'form',
                    label: 'Registrasi Baru',
                    icon: 'person-plus-fill',
                },
                {
                    key: 'history',
                    label: 'Buku Tamu & Riwayat',
                    icon: 'journal-text',
                    badge: this.stats.menunggu_persetujuan > 0 ? this.stats.menunggu_persetujuan : null,
                    badgeVariant: 'warning',
                },
            ];
        },
        statCards() {
            return [
                {
                    label: 'Menunggu Approval',
                    value: this.stats.menunggu_persetujuan,
                    suffix: 'Pengajuan',
                    icon: 'hourglass-split',
                    iconAnimation: this.stats.menunggu_persetujuan ? 'flip' : '',
                    sentiment: 'warning',
                    pattern: 'dots',
                    caption: this.stats.menunggu_persetujuan > 0 ? 'Menunggu Approval Pejabat' : 'Semua Terverifikasi',
                    targetTab: 'history',
                    interactive: true,
                    loading: this.statsLoading,
                },
                {
                    label: 'Scheduled / Disetujui',
                    value: this.stats.sudah_disetujui,
                    suffix: 'Tamu',
                    icon: 'person-check-fill',
                    sentiment: 'success',
                    pattern: 'grid',
                    caption: this.stats.sudah_disetujui > 0 ? 'Tamu Berizin Aktif' : 'Belum Ada Tamu Aktif',
                    targetTab: 'history',
                    interactive: true,
                    loading: this.statsLoading,
                },
                {
                    label: 'Check-Out (Selesai)',
                    value: this.stats.selesai_keluar,
                    suffix: 'Tamu',
                    icon: 'box-arrow-right',
                    sentiment: 'neutral',
                    pattern: 'crystal',
                    caption: 'Kunjungan Telah Selesai',
                    targetTab: 'history',
                    interactive: true,
                    loading: this.statsLoading,
                },
            ];
        },
    },
    mounted() {
        this.loadStats();
        this.bukaDetailDariTautan();
    },
    methods: {
        // Notifikasi Visitor menautkan /transaksitamu?detail=<token>. Membuka
        // detailnya memakai jalur yang sama dengan klik dari tabel riwayat;
        // token diteruskan apa adanya, endpoint show() yang menerjemahkannya.
        bukaDetailDariTautan() {
            const rujukan = bacaRujukanDetail(window.location.search);
            if (!rujukan) {
                return;
            }

            this.handleViewDetail({ Id: rujukan });
        },
        handleViewDetail(tamu) {
            this.activeTab = 'history';
            this.$nextTick(() => {
                this.$refs.historyRef?.viewDetail(tamu);
            });
        },
        async loadStats() {
            this.statsLoading = true;
            this.statsError = '';
            try {
                const response = await fetch(`/${this.apiPrefix}-stats`);
                const json = await response.json();
                if (!response.ok || !json.success) {
                    throw new Error('Respons statistik tidak valid.');
                }
                this.stats = {
                    menunggu_persetujuan: json.result.menunggu_persetujuan || 0,
                    sudah_disetujui: json.result.sudah_disetujui || 0,
                    selesai_keluar: json.result.selesai_keluar || 0,
                };
            } catch (error) {
                this.statsError =
                    'Data utama tetap dapat digunakan. Coba muat ulang halaman untuk memperbarui ringkasan.';
            } finally {
                this.statsLoading = false;
            }
        },
        onTamuSaved() {
            this.activeTab = 'history';
            this.loadStats();
            this.$nextTick(() => this.$refs.historyRef?.fetchData());
        },
    },
};
</script>

<style scoped>
.visitor-page-context {
    display: grid;
    gap: var(--hcis-space-3, 0.75rem);
}
</style>
