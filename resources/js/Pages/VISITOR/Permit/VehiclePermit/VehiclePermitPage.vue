<template>
    <div class="vehicle-permit-page">
        <VisitorWorkspace
            v-model="activeTab"
            title="Permit Kendaraan Karyawan"
            subtitle="Ajukan akses kendaraan, ikuti proses persetujuan, dan pantau masa berlaku permit."
            kicker="Visitor · Vehicle Permit"
            icon="car-front-fill"
            sentiment="brand"
            :stats="statCards"
            :stat-columns="statCards.length >= 4 ? 4 : 3"
            :tabs="tabs"
            section-title="Pengajuan dan status permit"
            section-description="Lengkapi data kendaraan atau buka daftar permit untuk melihat validitas dan riwayat."
            section-icon="card-checklist"
        >
            <!-- Kontak bantuan permit tidak lagi di kepala halaman: ada di Pusat
                 Bantuan (ikon ? di topbar), kelompok "Bantuan VMS · Permit"
                 paling atas di halaman VISITOR (keputusan user 29 Sep 2026). -->

            <!--
                Tombol pemindai kartu untuk karyawan sengaja tidak ada lagi
                (keputusan user 28 Sep 2026): QR kartu kini membuka halaman cek
                kartu publik dari kamera HP mana pun. Pemindai lengkap tetap ada
                di halaman admin (Verifikator Permit, Agenda Inspeksi, Monitoring).
            -->
            <template v-if="statsError" #context>
                <div class="visitor-page-context">
                    <HcisAlert
                        variant="warning"
                        title="Ringkasan permit belum dapat diperbarui"
                        :description="statsError"
                        closable
                        @close="statsError = ''"
                    />
                </div>
            </template>

            <template #panel-cards>
                <PermitDigitalPass
                    ref="cardsRef"
                    :user="user"
                    @navigate-to-form="activeTab = 'form'"
                    @view-detail="handleViewDetail"
                />
            </template>

            <template #panel-form>
                <PermitForm
                    :config="config"
                    :approval-policy="approvalPolicy"
                    :approver-flows="approverFlows"
                    :kuota="kuotaPermit"
                    :aturan-inspeksi="aturanInspeksi"
                    :kartu-nfc="kartuNfc"
                    @saved="onPermitSaved"
                    @navigate-to-status="activeTab = 'status'"
                    @navigate-to-cards="activeTab = 'cards'"
                    @refresh-kuota="loadStats"
                />
            </template>

            <template #panel-status>
                <PermitStatusList
                    ref="statusListRef"
                    :user="user"
                    :aturan-inspeksi="aturanInspeksi"
                    :kontak-bantuan="kontakBantuan"
                    @permit-canceled="onPermitCanceled"
                    @navigate-to-form="activeTab = 'form'"
                    @navigate-to-cards="activeTab = 'cards'"
                />
            </template>
        </VisitorWorkspace>

    </div>
</template>

<script>
import { HcisAlert } from '@/components/ui';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import { bacaRujukanDetail } from '@/components/VISITOR/tautanDetail';
import PermitDigitalPass from './components/PermitDigitalPass.vue';
import PermitForm from './components/PermitForm.vue';
import PermitStatusList from './components/PermitStatusList.vue';

export default {
    name: 'PermitPage',
    components: {
        HcisAlert,
        VisitorWorkspace,
        PermitDigitalPass,
        PermitForm,
        PermitStatusList,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        config: { type: Object, default: () => ({}) },
        approvalPolicy: { type: Object, default: () => ({}) },
        approverFlows: { type: Array, default: () => [] },
        serverDate: { type: String, default: null },
        aturanInspeksi: { type: Object, default: () => ({}) },
        kartuNfc: { type: Object, default: null },
        kontakBantuan: { type: Array, default: () => [] },
    },
    data() {
        return {
            activeTab: 'cards',
            stats: {
                permit_aktif: 0,
                menunggu_approve: 0,
                ditolak: 0,
                dicabut: 0,
            },
            statsLoading: true,
            statsError: '',
        };
    },
    computed: {
        tabs() {
            return [
                {
                    key: 'cards',
                    label: 'Pass Digital',
                    icon: 'person-vcard-fill',
                    badge: this.stats.permit_aktif > 0 ? this.stats.permit_aktif : null,
                    badgeVariant: 'success',
                },
                {
                    key: 'form',
                    label: 'Buat Baru',
                    icon: 'plus-circle-fill',
                },
                {
                    key: 'status',
                    label: 'Riwayat Permit',
                    icon: 'clock-history',
                    badge: this.stats.menunggu_approve > 0 ? this.stats.menunggu_approve : null,
                    badgeVariant: 'warning',
                },
            ];
        },
        // Jatah pengajuan permit, apa adanya dari stats. Halaman tidak
        // menghitungnya sendiri: angkanya lahir dari VisitorPermitQuotaService
        // di server, sumber yang sama dengan penjaga yang menolak pengajuan.
        kuotaPermit() {
            return {
                terpakai: Number(this.stats.kuota_terpakai ?? 0),
                maks: Number(this.stats.kuota_maks ?? 0),
                // Dua batas lain yang sama-sama bisa menolak pengajuan: berapa
                // boleh menunggu keputusan sekaligus, dan berapa permit boleh
                // mendapat jadwal inspeksi hari ini.
                pendingTerpakai: Number(this.stats.pending_terpakai ?? 0),
                pendingMaks: Number(this.stats.pending_maks ?? 0),
                aksesTerpakai: Number(this.stats.akses_terpakai ?? 0),
                aksesMaks: Number(this.stats.akses_maks ?? 0),
            };
        },
        statCards() {
            return [
                {
                    label: 'Permit Aktif',
                    value: this.stats.permit_aktif,
                    suffix: 'Kendaraan',
                    icon: 'car-front-fill',
                    sentiment: 'success',
                    pattern: 'grid',
                    caption: this.kuotaPermit.maks > 0 ? `Kuota ${this.kuotaPermit.terpakai}/${this.kuotaPermit.maks} Terpakai` : 'Tanda Pass Valid & Aktif',
                    targetTab: 'cards',
                    interactive: true,
                    loading: this.statsLoading,
                },
                {
                    label: 'Menunggu Review',
                    value: this.stats.menunggu_approve,
                    suffix: 'Pengajuan',
                    icon: 'hourglass-split',
                    iconAnimation: this.stats.menunggu_approve ? 'flip' : '',
                    sentiment: 'warning',
                    pattern: 'dots',
                    caption: this.stats.menunggu_approve > 0 ? 'Menunggu Approval Pejabat' : 'Semua Terverifikasi',
                    targetTab: 'status',
                    interactive: true,
                    loading: this.statsLoading,
                },
                {
                    label: 'Perlu Revisi / Ditolak',
                    value: this.stats.ditolak,
                    suffix: 'Pengajuan',
                    icon: 'x-circle-fill',
                    sentiment: 'danger',
                    pattern: 'crosshair',
                    caption: this.stats.ditolak > 0 ? 'Perlu Pembaruan Dokumen' : 'Tidak Ada Penolakan',
                    targetTab: 'status',
                    interactive: true,
                    loading: this.statsLoading,
                },
                // Kartu ini hanya muncul kalau memang ada yang dicabut. Kartu
                // permanen bernilai nol hanya memperpanjang layar; tapi ketika
                // angkanya ada, ia justru yang paling perlu dijelaskan —
                // dicabut BUKAN ditolak, dan kuotanya sudah kembali.
                ...(this.stats.dicabut > 0
                    ? [{
                        label: 'Akses Dicabut',
                        value: this.stats.dicabut,
                        suffix: 'Permit',
                        icon: 'slash-circle-fill',
                        sentiment: 'danger',
                        pattern: 'stripes',
                        caption: 'Lihat alasannya di Status Pengajuan',
                        targetTab: 'status',
                        interactive: true,
                        loading: this.statsLoading,
                    }]
                    : []),
            ];
        },
    },
    mounted() {
        this.loadStats();
        this.bukaDetailDariTautan();
    },
    methods: {
        // Notifikasi Visitor menautkan /transaksipermit?detail=<token>. Membuka
        // detailnya memakai jalur yang sama dengan klik dari tabel; token
        // diteruskan apa adanya, endpoint show() yang menerjemahkannya.
        bukaDetailDariTautan() {
            const rujukan = bacaRujukanDetail(window.location.search);
            if (!rujukan) {
                return;
            }

            this.handleViewDetail({ Id: rujukan });
        },
        async loadStats() {
            this.statsLoading = true;
            this.statsError = '';
            try {
                const response = await fetch('/transaksipermit-stats');
                const json = await response.json();
                if (!response.ok || !json.success) {
                    throw new Error('Respons statistik tidak valid.');
                }
                this.stats = json.result;
            } catch (error) {
                this.statsError =
                    'Data utama tetap dapat digunakan. Coba muat ulang halaman untuk memperbarui ringkasan.';
            } finally {
                this.statsLoading = false;
            }
        },
        onPermitSaved() {
            this.activeTab = 'cards';
            this.loadStats();
            this.$nextTick(() => {
                this.$refs.cardsRef?.fetchPermits();
                this.$refs.statusListRef?.fetchData();
            });
        },
        onPermitCanceled() {
            this.loadStats();
            this.$nextTick(() => {
                this.$refs.cardsRef?.fetchPermits();
            });
        },
        handleViewDetail(permit) {
            this.activeTab = 'status';
            if (permit) {
                this.$nextTick(() => {
                    if (this.$refs.statusListRef) {
                        this.$refs.statusListRef.viewDetail(permit);
                    }
                });
            }
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
