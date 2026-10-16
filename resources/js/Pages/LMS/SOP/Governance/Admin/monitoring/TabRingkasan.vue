<template>
    <div class="sop-stack tab-ringkasan">
        <!-- ══ CONTROL SURFACE: PERIOD FILTER & EXPORT TOOLBAR ══ -->
        <div class="tr-toolbar-card">
            <div class="tr-toolbar-top">
                <!-- Preset Chips Row -->
                <div class="tr-preset-chips" role="tablist" aria-label="Preset Cepat Periode">
                    <button
                        v-for="p in presets"
                        :key="p.key"
                        type="button"
                        class="tr-preset-btn"
                        :class="{ 'is-active': saringan.preset === p.key }"
                        @click="pilihPreset(p.key)"
                    >
                        <HcisIcon :name="p.icon" size="2xs" />
                        <span>{{ p.label }}</span>
                    </button>
                    <button
                        type="button"
                        class="tr-preset-btn"
                        :class="{ 'is-active': saringan.preset === 'custom' }"
                        @click="toggleCustomPicker"
                    >
                        <HcisIcon name="calendar-range" size="2xs" />
                        <span>Kustom</span>
                        <HcisIcon :name="bukaKustom ? 'chevron-up' : 'chevron-down'" size="3xs" class="ms-0.5 opacity-70" />
                    </button>
                </div>

                <!-- Export Action -->
                <div class="tr-toolbar-actions ms-auto">
                    <HcisButton
                        variant="primary"
                        size="sm"
                        leading-icon="download"
                        label="Export Ringkasan"
                        @click="exportModal = true"
                    />
                </div>
            </div>

            <!-- Custom Date Range Picker Container (Collapsible) -->
            <transition name="tr-slide">
                <div v-if="bukaKustom || saringan.preset === 'custom'" class="tr-custom-picker-row">
                    <div class="tr-custom-picker-field">
                        <label class="tr-custom-label">
                            <HcisIcon name="calendar3" size="2xs" class="text-brand-600" />
                            Rentang Tanggal:
                        </label>
                        <HcisDatePicker
                            v-model="customDateRange"
                            type="daterange"
                            size="sm"
                            clearable
                            placeholder="Pilih rentang tanggal"
                            start-placeholder="Mulai"
                            end-placeholder="Sampai"
                            @update:model-value="onCustomDateRange"
                        />
                    </div>
                </div>
            </transition>

            <!-- Status Indicator Strip (Clean & Compact) -->
            <div class="tr-status-strip">
                <div class="tr-status-pill">
                    <HcisIcon name="info-circle-fill" size="xs" class="text-brand-600" />
                    <span>Periode: <strong>{{ labelPeriodeAktif }}</strong></span>
                </div>

                <div v-if="bukanBaku" class="tr-reset-wrap">
                    <button type="button" class="tr-reset-btn" @click="resetSaringan">
                        <HcisIcon name="arrow-counterclockwise" size="xs" />
                        <span>Kembali ke 30 Hari</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ BAB 1 · EXECUTIVE STAT KPI ══ -->
        <section class="zn-sec">
            <BabKeadaan
                :saringan="params"
                :dokumen="penyebut"
                @lencana="$emit('lencana', $event)"
            />
        </section>

        <!-- ══ LAPIS 1 · KESEHATAN DOKUMEN MASTER ══ -->
        <section class="zn-sec">
            <BabKesehatanDokumen
                :saringan="params"
                @ringkas="penyebut = $event"
            />
        </section>

        <!-- Bab 2 — Momentum & Proyeksi (Hidden) -->
        <section v-show="false" class="zn-sec">
            <BabMomentum
                :saringan="params"
                @ringkas="ringkasan = $event"
            />
        </section>

        <!-- ══ LAPIS 2 · KEPATUHAN ORANG & TITIK MACET ══ -->
        <section class="zn-sec">
            <BabTitikMacet
                :saringan="params"
                @buka-drawer="bukaDrawer"
                @buka-tab="bukaTab"
                @saring="saringDari"
            />
        </section>

        <!-- ══ BAB 4 · HEATMAP PENYELESAIAN ══ -->
        <section class="zn-sec">
            <BabKualitas
                :saringan="params"
                @buka-drawer="bukaDrawer"
                @buka-tab="bukaTab"
            />
        </section>

        <!-- ══ JANGKAUAN DOKUMEN PUBLIK (Minat Baca Sukarela) ══ -->
        <section class="zn-sec">
            <BabJangkauanPublik :saringan="paramsPublik" />
        </section>

        <!-- Drawer inspeksi cepat karyawan -->
        <KaryawanDrawer
            :show="drawer.show"
            :kode="drawer.kode"
            :token="drawer.token"
            :nama="drawer.nama"
            @close="drawer.show = false"
        />

        <!-- Modal Export Khusus Ringkasan -->
        <ModalExportRingkasan
            :show="exportModal"
            :saringan="params"
            :prop-orang="ringkasan.orang"
            @close="exportModal = false"
        />
    </div>
</template>

<script>
import { HcisButton, HcisIcon, HcisDatePicker } from '@/components/ui';
import BabKesehatanDokumen from './ringkasan/BabKesehatanDokumen.vue';
import BabJangkauanPublik from './ringkasan/BabJangkauanPublik.vue';
import BabKeadaan from './ringkasan/BabKeadaan.vue';
import BabMomentum from './ringkasan/BabMomentum.vue';
import BabTitikMacet from './ringkasan/BabTitikMacet.vue';
import BabKualitas from './ringkasan/BabKualitas.vue';
import ModalExportRingkasan from './ringkasan/panel/ModalExportRingkasan.vue';
import KaryawanDrawer from './KaryawanDrawer.vue';
import { formatLabelRentang } from './ringkasan/agregat.js';

const SUMBER_KEWAJIBAN = ['MANUAL', 'AUTO_RULE'];

function tglLokal(d) {
    const bulan = String(d.getMonth() + 1).padStart(2, '0');
    const hari = String(d.getDate()).padStart(2, '0');

    return `${d.getFullYear()}-${bulan}-${hari}`;
}

function rentangHari(n) {
    const kini = new Date();
    const lalu = new Date();
    lalu.setDate(kini.getDate() - n);

    return [tglLokal(lalu), tglLokal(kini)];
}

const RENTANG_BAKU = 30;

export default {
    name: 'TabRingkasan',
    components: {
        HcisButton,
        HcisIcon,
        HcisDatePicker,
        BabKesehatanDokumen,
        BabJangkauanPublik,
        BabKeadaan,
        BabMomentum,
        BabTitikMacet,
        BabKualitas,
        ModalExportRingkasan,
        KaryawanDrawer,
    },
    props: {
        filterOptions: { type: Object, default: () => ({}) },
    },
    emits: ['buka-tab', 'lencana'],
    data() {
        return {
            exportModal: false,
            bukaKustom: false,
            customDateRange: rentangHari(RENTANG_BAKU),
            presets: [
                { key: '7d', label: '7 Hari', icon: 'calendar-week' },
                { key: '30d', label: '30 Hari (Default)', icon: 'calendar-month' },
                { key: '90d', label: '90 Hari', icon: 'calendar3' },
                { key: 'month', label: 'Bulan Ini', icon: 'calendar-event' },
                { key: 'year', label: 'Tahun Ini', icon: 'calendar-range' },
            ],
            penyebut: {
                total: 0,
                fungsional: 0,
                publik: 0,
                belumBeredar: 0,
                tersaring: { Penugasan: 0, Paket: 0 },
                leadTime: { rerata: 0, terbit: 0 },
            },
            saringan: {
                preset: '30d',
                date_range: rentangHari(RENTANG_BAKU),
                date_mode: 'assign',
            },
            ringkasan: {},
            drawer: {
                show: false,
                kode: '',
                token: '',
                nama: '',
            },
        };
    },
    computed: {
        paramsPublik() {
            const p = { ...this.params };
            delete p.sumber;
            delete p.date_mode;

            return p;
        },

        bukanBaku() {
            const r = this.saringan.date_range;
            const baku = rentangHari(RENTANG_BAKU);

            return !Array.isArray(r) || r[0] !== baku[0] || r[1] !== baku[1] || this.saringan.preset !== '30d';
        },

        labelPeriodeAktif() {
            const r = this.saringan.date_range;
            const labelTgl = formatLabelRentang(this.params);
            if (!labelTgl) return '30 Hari Terakhir';

            const pObj = this.presets.find((x) => x.key === this.saringan.preset);
            const namaPreset = pObj ? pObj.label : 'Kustom';

            return `${namaPreset} · ${labelTgl}`;
        },

        params() {
            const s = this.saringan;
            const out = {};
            if (Array.isArray(s.date_range) && s.date_range.length === 2 && s.date_range[0] && s.date_range[1]) {
                out.date_start = s.date_range[0];
                out.date_end = s.date_range[1];
                out.dari = s.date_range[0];
                out.sampai = s.date_range[1];
            } else if (s.date_start && s.date_end) {
                out.date_start = s.date_start;
                out.date_end = s.date_end;
                out.dari = s.date_start;
                out.sampai = s.date_end;
            }
            if (s.date_mode) out.date_mode = s.date_mode;

            out.sumber = SUMBER_KEWAJIBAN.join(',');

            return out;
        },
    },
    methods: {
        pilihPreset(key) {
            const formatTgl = (d) => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const today = new Date();
            let val = rentangHari(RENTANG_BAKU);

            if (key === '7d') {
                const past = new Date();
                past.setDate(today.getDate() - 7);
                val = [formatTgl(past), formatTgl(today)];
            } else if (key === '30d') {
                val = rentangHari(RENTANG_BAKU);
            } else if (key === '90d') {
                const past = new Date();
                past.setDate(today.getDate() - 90);
                val = [formatTgl(past), formatTgl(today)];
            } else if (key === 'month') {
                const start = new Date(today.getFullYear(), today.getMonth(), 1);
                val = [formatTgl(start), formatTgl(today)];
            } else if (key === 'year') {
                const start = new Date(today.getFullYear(), 0, 1);
                val = [formatTgl(start), formatTgl(today)];
            }

            this.bukaKustom = false;
            this.customDateRange = val;
            this.onSaringan({ date_range: val, preset: key });
        },

        toggleCustomPicker() {
            this.bukaKustom = !this.bukaKustom;
            if (this.bukaKustom) {
                this.saringan.preset = 'custom';
            }
        },

        onCustomDateRange(val) {
            if (Array.isArray(val) && val.length === 2 && val[0] && val[1]) {
                this.onSaringan({ date_range: val, preset: 'custom' });
            }
        },

        onSaringan(v) {
            const baru = { ...this.saringan, ...v };
            const r = baru.date_range;
            if (!Array.isArray(r) || r.length !== 2 || !r[0] || !r[1]) {
                baru.date_range = rentangHari(RENTANG_BAKU);
                baru.preset = '30d';
            }

            this.saringan = baru;
        },

        resetSaringan() {
            this.bukaKustom = false;
            const baku = rentangHari(RENTANG_BAKU);
            this.customDateRange = baku;
            this.saringan = {
                preset: '30d',
                date_range: baku,
                date_mode: 'assign',
            };
        },

        saringDari({ kunci, nilai }) {
            if (!kunci || !nilai) return;
            this.$emit('buka-tab', { tab: 'karyawan', filters: { [kunci]: Array.isArray(nilai) ? nilai : [nilai] } });
        },

        bukaDrawer({ kode, token, nama }) {
            this.drawer = { show: true, kode: kode || '', token: token || '', nama: nama || '' };
        },

        bukaTab(payload) {
            this.$emit('buka-tab', payload);
        },
    },
};
</script>

<style scoped>
/* ══ CONTROL SURFACE: PERIOD FILTER & EXPORT CARD ══ */
.tr-toolbar-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 2px 10px -2px rgba(15, 23, 42, 0.04);
    margin-bottom: 1rem;
}

.tr-toolbar-top {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: space-between;
}

.tr-preset-chips {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
    background: rgba(241, 245, 249, 0.75);
    padding: 0.2rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.tr-preset-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    border-radius: 6px;
    padding: 0.3rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
    white-space: nowrap;
}

.tr-preset-btn:hover {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: rgba(255, 255, 255, 0.9);
}

.tr-preset-btn.is-active {
    background: var(--hcis-white);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    border-color: rgba(99, 102, 241, 0.3);
    font-weight: 800;
    box-shadow: 0 2px 6px -2px rgba(99, 102, 241, 0.2);
}

.tr-preset-btn.is-active :deep(.hcis-icon) {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

/* Custom Date Picker Row */
.tr-custom-picker-row {
    padding: 0.6rem;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
}

.tr-custom-picker-field {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.tr-custom-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    margin: 0;
}

/* Status Indicator Strip (Clean & Compact) */
.tr-status-strip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-top: 0.25rem;
}

.tr-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.735rem;
    font-weight: 600;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 9999px;
    padding: 0.2rem 0.6rem;
}

.tr-reset-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid rgba(245, 158, 11, 0.35);
    background: rgba(254, 243, 199, 0.8);
    color: var(--hcis-amber-700);
    border-radius: 9999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.7125rem;
    font-weight: 750;
    cursor: pointer;
    transition: all 0.16s ease;
}

.tr-reset-btn:hover {
    background: var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}

.zn-sec {
    scroll-margin-top: 5rem;
}

/* Animations */
.tr-slide-enter-active,
.tr-slide-leave-active {
    transition: all 0.2s ease-out;
}
.tr-slide-enter-from,
.tr-slide-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (max-width: 767.98px) {
    .tr-toolbar-top {
        flex-direction: column;
        align-items: stretch;
    }
    .tr-toolbar-actions {
        margin-left: 0 !important;
    }
    .tr-toolbar-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
    .tr-preset-chips {
        width: 100%;
        justify-content: flex-start;
        overflow-x: auto;
    }
}
</style>
