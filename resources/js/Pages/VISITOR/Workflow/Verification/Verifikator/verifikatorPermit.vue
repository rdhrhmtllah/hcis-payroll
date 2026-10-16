<template>
    <div class="verifikator-permit-page">
        <VisitorWorkspace
            title="Verifikator Permit"
            subtitle="Validasi permit kendaraan dari antrean sampai riwayat operasional."
            kicker="Visitor · Verification Desk"
            icon="car-front-fill"
            sentiment="brand"
            :stats="statCards"
            :stat-columns="3"
            section-title="Meja verifikasi permit"
            section-description="Antrean, keputusan, dan riwayat permit kendaraan."
            section-icon="person-workspace"
        >
            <template #actions>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="upc-scan"
                    label="Cek Verifikasi Kartu"
                    title="Pindai QR pada kartu permit untuk memeriksa keasliannya"
                    @click="cekKartuTerbuka = true"
                />
            </template>

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

            <VerifikatorPermitPanel ref="permitPanelRef" @stats-updated="loadStats" />
        </VisitorWorkspace>

        <VerifikasiKartuScanner v-model="cekKartuTerbuka" endpoint="/laporanvisitor-verifikasi-kartu" />
    </div>
</template>

<script>
import axios from 'axios';
import { HcisAlert, HcisButton } from '@/components/ui';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import VerifikasiKartuScanner from '@/components/VISITOR/VerifikasiKartuScanner.vue';
import { bacaRujukanDetail, bacaJenisDetail } from '@/components/VISITOR/tautanDetail';
import VerifikatorPermitPanel from './components/VerifikatorPermitPanel.vue';

/**
 * Meja verifikasi permit kendaraan — halamannya sendiri.
 *
 * Dipisah dari meja gabungan (DML-2026-105) supaya wewenang menyetujui bisa
 * diberikan terpisah: pemegang kunci tamu tidak bisa menyetujui permit
 * kendaraan, termasuk dengan memanggil endpointnya langsung.
 *
 * Angka ringkasannya memakai rincian PER DOMAIN. Angka gabungan yang dipakai
 * meja lama akan memajang pekerjaan domain lain sebagai milik halaman ini, dan
 * tidak ada yang akan menyadarinya karena angkanya tetap masuk akal.
 */
export default {
    name: 'VerifikatorPermitPage',
    components: {
        HcisButton,
        VerifikasiKartuScanner,
        HcisAlert,
        VisitorWorkspace,
        VerifikatorPermitPanel,
    },
    props: {
        user: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            cekKartuTerbuka: false,
            stats: {
                antrian_permit: 0,
                disetujui_hari_ini_permit: 0,
                ditolak_permit: 0,
            },
            statsLoading: true,
            statsError: '',
        };
    },
    computed: {
        statCards() {
            return [
                {
                    label: 'Antrean permit',
                    value: this.stats.antrian_permit,
                    suffix: 'Pengajuan',
                    caption: 'Menunggu verifikasi permit',
                    icon: 'car-front-fill',
                    sentiment: 'warning',
                    loading: this.statsLoading,
                    interactive: false,
                    pattern: 'grid',
                    layout: 'compact',
                },
                {
                    label: 'Disetujui hari ini',
                    value: this.stats.disetujui_hari_ini_permit,
                    suffix: 'Permit',
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
                    value: this.stats.ditolak_permit,
                    suffix: 'Permit',
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
        /**
         * Notifikasi antrean menautkan `?detail=<token>&jenis=permit`.
         *
         * `jenis` tetap diperiksa walau halaman ini hanya melayani permit:
         * tautan lama mendarat di sini lewat pengalihan /verifikator-visitor,
         * dan membuka token milik domain lain di panel ini akan memanggil
         * detail permit dengan Id kunjungan tamu — permintaan yang pasti gagal,
         * dengan pesan yang tidak menyebut sebab sebenarnya.
         */
        bukaDetailDariTautan() {
            const rujukan = bacaRujukanDetail(window.location.search);
            const jenis = bacaJenisDetail(window.location.search);

            if (!rujukan || jenis === 'tamu') {
                return;
            }

            this.$nextTick(() => {
                this.$refs.permitPanelRef?.openReview({ Id_Transaksi_Permit: rujukan });
            });
        },
        async loadStats() {
            this.statsLoading = true;
            this.statsError = '';
            try {
                const { data: json } = await axios.get('/verifikator-visitor-stats');
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
