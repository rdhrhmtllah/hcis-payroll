<template>
    <div class="container-fluid px-0 sop-scope mon-page-root">
        <!-- ══ HEADER UTAMA MONITORING DENGAN INTEGRATED TABS (HCIS Design System) ══ -->
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="lg"
            icon="shield-check"
            kicker="DMS GOVERNANCE & AUDIT"
            title="Monitoring Dokumen"
            subtitle="Pantau kepatuhan karyawan, pencapaian target, dan sebaran paket dokumen secara langsung."
            class="sop-fade-up mb-4 mon-header-hero"
        >
            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="mon-header-date">
                        <HcisIcon name="calendar3" size="2xs" class="text-brand-600 me-1" />
                        <span>{{ formattedDate }}</span>
                    </span>
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="search"
                        label="Lompat ke… (Ctrl+K)"
                        class="d-none d-sm-inline-flex"
                        @click="palet = true"
                    />
                </div>
            </template>

            <!-- Integrated Header Tabs Navigation Slot -->
            <template #tabs>
                <HcisTabs
                    v-model="tab"
                    :tabs="tabItems"
                    variant="header"
                    size="md"
                    aria-label="Navigasi Monitoring SOP"
                    @update:model-value="gantiTab"
                />
            </template>
        </HcisHeader>

        <div class="mb-5 sop-stack">
            <!-- Tiap tab memuat datanya sendiri; shell tak mengambil data tab apa pun. -->
            <TabRingkasan
                v-if="tab === 'ringkasan'"
                :filter-options="filterOptions"
                @buka-tab="bukaTabTersaring"
                @lencana="setLencana"
            />
            <TabKaryawan
                v-else-if="tab === 'karyawan'"
                :filter-options="filterOptions"
                :awal="filterAwal"
                @export="modalExport = true"
                @filter-berubah="filterKaryawan = $event"
            />
            <TabPaket v-else-if="tab === 'paket'" :filter-options="filterOptions" />
        </div>

        <CommandPalette
            :show="palet"
            :sources="sumberPalet"
            placeholder="Cari karyawan, paket, atau perintah…"
            recent-key="sop_mon_cp_terakhir"
            :debounce="280"
            @close="palet = false"
            @jalankan="jalankanPalet"
        />

        <SopExportStudio
            :show="modalExport"
            :filter-options="filterOptions"
            :filter-awal="filterKaryawan"
            :jenis-awal="tab === 'paket' ? 'coverage' : 'rinci'"
            @close="modalExport = false"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { HcisHeader, HcisButton, HcisIcon, HcisTabs } from '@/components/ui';
import TabPaket from './monitoring/TabPaket.vue';
import TabKaryawan from './monitoring/TabKaryawan.vue';
import TabRingkasan from './monitoring/TabRingkasan.vue';
import SopExportStudio from './monitoring/SopExportStudio.vue';
import CommandPalette from '../../../components/ui/CommandPalette.vue';
import { sumberMonitoring } from './monitoring/sumberPalet.js';
import pageMeta from '../../Shared/support/pageMeta.js';
import { useActivityCenter } from '../../../../../composables/useActivityCenter.js';
import '../../Shared/support/sopTheme.css';

const API = '/api/v1/sop/admin/monitoring';

export default {
    name: 'SopMonitoring',
    components: {
        HcisHeader,
        HcisButton,
        HcisIcon,
        HcisTabs,
        TabPaket,
        TabKaryawan,
        TabRingkasan,
        SopExportStudio,
        CommandPalette,
    },
    mixins: [pageMeta],
    data() {
        return {
            tab: 'ringkasan',
            filterOptions: {},
            filterAwal: {},
            filterKaryawan: {},
            modalExport: false,
            palet: false,
            paketOptions: [],
            lencana: {},
            tabs: [
                {
                    key: 'ringkasan',
                    label: 'Ringkasan',
                    icon: 'speedometer2',
                    subtitle: 'Yang perlu ditangani hari ini.',
                    aksi: 'Export Data',
                },
                {
                    key: 'karyawan',
                    label: 'Karyawan',
                    icon: 'people-fill',
                    subtitle: 'Kepatuhan per karyawan.',
                    aksi: 'Export Data',
                },
                {
                    key: 'paket',
                    label: 'Paket',
                    icon: 'collection-fill',
                    subtitle: 'Tingkat penyelesaian per paket.',
                    aksi: 'Export Penyelesaian',
                },
            ],
        };
    },
    computed: {
        tabItems() {
            return this.tabs.map((t) => ({
                key: t.key,
                id: t.key,
                label: t.label,
                icon: t.icon,
                badge: this.lencana[t.key] || undefined,
                badgeVariant: t.key !== 'ringkasan' ? 'danger' : 'neutral',
            }));
        },

        sumberPalet() {
            return sumberMonitoring(() => ({ paketOptions: this.paketOptions }));
        },

        tabAktif() {
            return this.tabs.find((t) => t.key === this.tab) || this.tabs[0];
        },

        tabIndex() {
            const idx = this.tabs.findIndex((t) => t.key === this.tab);
            return idx >= 0 ? idx : 0;
        },
    },
    mounted() {
        this.bacaUrl();
        this.fetchFilterOptions();
        this.fetchPaket();
        window.addEventListener('popstate', this.bacaUrl);
        window.addEventListener('keydown', this.pintasan);
    },
    beforeUnmount() {
        window.removeEventListener('popstate', this.bacaUrl);
        window.removeEventListener('keydown', this.pintasan);
    },
    methods: {
        pintasan(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                this.palet = true;
            }
        },

        jalankanPalet(item) {
            if (!item) return;

            if (item.tipe === 'perintah') {
                if (item.aksi === 'export') this.modalExport = true;
                if (item.tab && this.tabs.some((t) => t.key === item.tab)) {
                    this.gantiTab(item.tab);
                }
                return;
            }

            if (item.tipe === 'paket') {
                this.gantiTab('paket');
                return;
            }

            if (item.tipe === 'karyawan') {
                this.gantiTab('karyawan');
                this.filterAwal = { cari: item.label };
            }
        },

        bukaTabTersaring({ tab, filters }) {
            if (!this.tabs.some((t) => t.key === tab)) return;
            this.gantiTab(tab);
            if (tab === 'karyawan' && filters) {
                this.filterAwal = { ...filters };
            }
        },

        setLencana({ tab, hitungan }) {
            if (!tab) return;
            this.lencana = { ...this.lencana, [tab]: hitungan };
        },

        aksiUtama() {
            if (this.tab === 'ringkasan') {
                this.exportRingkasanKhusus();
                return;
            }
            this.modalExport = true;
        },

        exportRingkasanKhusus() {
            const modalEl = document.querySelector('.tr-toolbar-actions button');
            if (modalEl) {
                modalEl.click();
            } else {
                this.modalExport = true;
            }
        },

        async fetchFilterOptions() {
            try {
                const res = await axios.get(`${API}/filter-options`);
                if (res.data?.success) {
                    this.filterOptions = res.data.result || {};
                }
            } catch (e) {
                // Biarkan tab fallback ke nilai lokalnya
            }
        },

        async fetchPaket() {
            try {
                const res = await axios.get(`${API}/paket-options`);
                if (res.data?.success) {
                    this.paketOptions = res.data.result || [];
                }
            } catch (e) {
                // Non-fatal
            }
        },

        bacaUrl() {
            const params = new URLSearchParams(window.location.search);
            const t = params.get('tab');
            if (t && this.tabs.some((item) => item.key === t)) {
                this.tab = t;

                return;
            }

            this.tab = 'ringkasan';

            /* URL-nya IKUT dibersihkan, bukan cuma isinya yang diganti. Membiarkan
               ?tab=dokumen terpampang sementara layar menampilkan Ringkasan membuat
               alamatnya berbohong: di-reload ia mengulang kekeliruan yang sama, dan
               dibagikan ia menyesatkan orang berikutnya. Tab "dokumen" dan
               "analitik" memang pernah ada, jadi tautan lamanya masih beredar. */
            if (!t) return;

            const url = new URL(window.location.href);
            url.searchParams.delete('tab');
            window.history.replaceState({}, '', url.toString());
        },

        gantiTab(key) {
            if (!key) return;
            this.tab = key;

            const url = new URL(window.location.href);
            if (url.searchParams.get('tab') !== key) {
                url.searchParams.set('tab', key);
                window.history.pushState({}, '', url.toString());
            }

            const activity = useActivityCenter();
            if (activity?.track) {
                activity.track({
                    id_fitur: 2,
                    aksi: 'Ganti Tab Monitoring',
                    keterangan: `Membuka tab ${key}`,
                });
            }
        },
    },
};
</script>

<style scoped>
/* Scoped overrides for SopMonitoring page root */
.mon-page-root {
    position: relative;
}

.mon-header-hero {
    position: relative;
}

.mon-header-date {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.65rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.85);
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}
</style>
