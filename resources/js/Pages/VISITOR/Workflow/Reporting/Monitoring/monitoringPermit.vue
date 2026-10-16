<template>
    <div class="monitoring-permit-page">
        <VisitorWorkspace
            title="Monitoring Permit"
            subtitle="Pantau keadaan permit kendaraan harian dari sumber lifecycle yang konsisten."
            kicker="Visitor · Monitoring"
            icon="car-front-fill"
            sentiment="purple"
            section-title="Keadaan permit kendaraan"
            section-description="Ringkasan hari ini dan daftar lengkapnya. Perakitan berkas laporan ada di halaman Export Studio."
            section-icon="file-earmark-bar-graph-fill"
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

            <PermitMonitoringPanel :needs-approval="needsApproval" />
        </VisitorWorkspace>

        <VerifikasiKartuScanner v-model="cekKartuTerbuka" endpoint="/laporanvisitor-verifikasi-kartu" />
    </div>
</template>

<script>
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import PermitMonitoringPanel from './components/PermitMonitoringPanel.vue';
import VerifikasiKartuScanner from '@/components/VISITOR/VerifikasiKartuScanner.vue';
import { HcisButton } from '@/components/ui';

/**
 * Monitoring Permit — halamannya sendiri.
 *
 * Pasangan monitoringTamu.vue; lihat catatan di sana untuk alasan pemisahannya.
 */
export default {
    name: 'MonitoringPermitPage',
    components: {
        VisitorWorkspace,
        PermitMonitoringPanel,
        VerifikasiKartuScanner,
        HcisButton,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        needsApproval: { type: Boolean, default: true },
    },
    data() {
        return { cekKartuTerbuka: false };
    },
};
</script>
