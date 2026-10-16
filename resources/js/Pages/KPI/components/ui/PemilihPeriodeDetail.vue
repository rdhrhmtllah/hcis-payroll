<template>
    <!-- Pemilih periode di modal "Detail Employee Performance".
         Mengganti periode hanya memuat ulang ISI MODAL untuk orang yang sama;
         dashboard di belakangnya baru ikut bila pengguna menekan "Terapkan". -->
    <div class="ppd" :class="{ 'ppd--memuat': loading }">
        <div class="ppd__baris">
            <!-- 1. Quick Presets Segment (Bulan Ini, YTD, Full-Year) & Terapkan -->
            <div class="ppd__presets-wrap">
                <div class="ppd__presets">
                    <span class="ppd__preset-label d-none d-sm-inline-flex">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Preset:
                    </span>
                    <div class="hcis-filter-segmented">
                        <button
                            type="button"
                            class="hcis-filter-pill"
                            :class="{ 'hcis-filter-pill--active': isQuickActive('bulan_ini') }"
                            :disabled="loading"
                            @click="pilihQuick('bulan_ini')"
                        >
                            <i class="bi bi-calendar2-check me-1"></i>Bulan Ini
                        </button>
                        <button
                            type="button"
                            class="hcis-filter-pill hcis-filter-pill--ytd"
                            :class="{ 'hcis-filter-pill--active': isQuickActive('ytd') }"
                            :disabled="loading"
                            @click="pilihQuick('ytd')"
                        >
                            <i class="bi bi-graph-up-arrow me-1"></i>YTD
                        </button>
                        <button
                            type="button"
                            class="hcis-filter-pill"
                            :class="{ 'hcis-filter-pill--active': isQuickActive('full') }"
                            :disabled="loading"
                            @click="pilihQuick('full')"
                        >
                            <i class="bi bi-calendar-range me-1"></i>Full-Year
                        </button>
                    </div>
                </div>

                <button
                    v-if="bisaTerapkan"
                    type="button"
                    class="ppd__terapkan ppd__terapkan--mobile d-flex d-md-none"
                    :disabled="loading"
                    title="Ubah periode dashboard mengikuti periode di detail ini"
                    @click="$emit('terapkan')"
                >
                    <i class="bi bi-box-arrow-in-down-left me-1" aria-hidden="true"></i>Terapkan
                </button>
            </div>

            <!-- 2. HcisSelect Custom Period Dropdowns & Navigation Steppers -->
            <div class="ppd__pilih" role="group" aria-label="Periode detail">
                <button
                    v-if="modeBulan"
                    type="button"
                    class="ppd__panah"
                    :disabled="loading || bulanAngka <= 1"
                    aria-label="Bulan sebelumnya"
                    title="Bulan sebelumnya"
                    @click="gantiBulan(bulanAngka - 1)"
                >
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>

                <HcisSelect
                    :model-value="nilai"
                    :disabled="loading"
                    :clearable="false"
                    :filterable="false"
                    size="sm"
                    leading-icon="calendar-month"
                    placeholder="Pilih Periode"
                    class="ppd-select-period"
                    @update:model-value="ganti($event, tahun)"
                >
                    <HcisSelectOption
                        v-for="item in opsiPeriode"
                        :key="item.value"
                        :value="item.value"
                        :label="item.label"
                    />
                </HcisSelect>

                <HcisSelect
                    :model-value="tahun"
                    :disabled="loading"
                    :clearable="false"
                    :filterable="false"
                    size="sm"
                    placeholder="Tahun"
                    class="ppd-select-year"
                    @update:model-value="ganti(nilai, Number($event))"
                >
                    <HcisSelectOption
                        v-for="t in opsiTahun"
                        :key="t"
                        :value="t"
                        :label="String(t)"
                    />
                </HcisSelect>

                <button
                    v-if="modeBulan"
                    type="button"
                    class="ppd__panah"
                    :disabled="loading || bulanAngka >= 12"
                    aria-label="Bulan berikutnya"
                    title="Bulan berikutnya"
                    @click="gantiBulan(bulanAngka + 1)"
                >
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>

                <button
                    v-if="bisaTerapkan"
                    type="button"
                    class="ppd__terapkan ppd__terapkan--desktop d-none d-md-inline-flex"
                    :disabled="loading"
                    title="Ubah periode dashboard mengikuti periode di detail ini"
                    @click="$emit('terapkan')"
                >
                    <i class="bi bi-box-arrow-in-down-left me-1" aria-hidden="true"></i>Terapkan
                </button>
            </div>
        </div>

        <!-- Strip 12 bulan: status tiap bulan sebelum diklik -->
        <div v-if="navigasi.length" class="ppd__strip" role="list" aria-label="Status per bulan">
            <button
                v-for="b in navigasi"
                :key="b.periode"
                type="button"
                role="listitem"
                class="ppd__sel"
                :class="[`ppd__sel--${b.status}`, { 'ppd__sel--aktif': modeBulan && bulanAngka === b.bulan }]"
                :disabled="loading || b.status === 'mendatang'"
                :title="`${NAMA_BULAN[b.bulan - 1]} ${tahun} — ${LABEL_STATUS[b.status] || b.status}`"
                :aria-label="`${NAMA_BULAN[b.bulan - 1]} ${tahun}, ${LABEL_STATUS[b.status] || b.status}`"
                :aria-current="modeBulan && bulanAngka === b.bulan ? 'true' : undefined"
                @click="gantiBulan(b.bulan)"
            >
                {{ SINGKAT[b.bulan - 1] }}
            </button>
        </div>

        <ul v-if="navigasi.length" class="ppd__legenda" aria-hidden="true">
            <li v-for="s in STATUS_LEGENDA" :key="s"><span :class="`ppd__titik ppd__sel--${s}`"></span>{{ LABEL_STATUS[s] }}</li>
        </ul>
    </div>
</template>

<script>
import { HcisSelect, HcisSelectOption } from '@/components/ui';

const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const SINGKAT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const LABEL_STATUS = {
    terisi: 'Sudah diisi',
    menunggu: 'Menunggu persetujuan',
    belum: 'Belum diisi',
    tanpa_target: 'Tidak ada target',
    mendatang: 'Belum berjalan',
};

export default {
    name: 'PemilihPeriodeDetail',
    components: {
        HcisSelect,
        HcisSelectOption,
    },
    props: {
        /** '01'..'12' | 'ytd' | 'full' */
        nilai: { type: String, required: true },
        tahun: { type: Number, required: true },
        navigasi: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        bisaTerapkan: { type: Boolean, default: true },
    },
    emits: ['ganti', 'terapkan'],
    data() {
        return { NAMA_BULAN, SINGKAT, LABEL_STATUS, STATUS_LEGENDA: ['terisi', 'menunggu', 'belum', 'tanpa_target'] };
    },
    computed: {
        modeBulan() {
            return /^\d{2}$/.test(this.nilai);
        },
        bulanAngka() {
            return this.modeBulan ? Number(this.nilai) : 0;
        },
        opsiTahun() {
            const kini = new Date().getFullYear();
            const daftar = [kini - 2, kini - 1, kini];
            return daftar.includes(this.tahun) ? daftar : [...daftar, this.tahun].sort();
        },
        opsiPeriode() {
            const bulanList = this.NAMA_BULAN.map((nama, i) => ({
                value: String(i + 1).padStart(2, '0'),
                label: nama,
            }));
            return [
                ...bulanList,
                { value: 'ytd', label: 'YTD (Akumulasi)' },
                { value: 'full', label: 'Full Year (12 Bulan)' },
            ];
        },
    },
    methods: {
        ganti(nilai, tahun) {
            this.$emit('ganti', { nilai, tahun });
        },
        gantiBulan(bulan) {
            if (bulan < 1 || bulan > 12) return;
            this.ganti(String(bulan).padStart(2, '0'), this.tahun);
        },
        isQuickActive(type) {
            const now = new Date();
            const currMonthStr = String(now.getMonth() + 1).padStart(2, '0');
            const currYear = now.getFullYear();

            if (type === 'bulan_ini') {
                return this.nilai === currMonthStr && this.tahun === currYear;
            }
            if (type === 'ytd') {
                return this.nilai === 'ytd';
            }
            if (type === 'full') {
                return this.nilai === 'full';
            }
            return false;
        },
        pilihQuick(type) {
            const now = new Date();
            if (type === 'bulan_ini') {
                const currMonthStr = String(now.getMonth() + 1).padStart(2, '0');
                this.ganti(currMonthStr, now.getFullYear());
            } else if (type === 'ytd') {
                this.ganti('ytd', this.tahun);
            } else if (type === 'full') {
                this.ganti('full', this.tahun);
            }
        },
    },
};
</script>

<style scoped>
.ppd {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 0.875rem);
    padding: 0.875rem 1rem;
    margin-bottom: 1.25rem;
    box-shadow:
        0 1px 3px 0 rgba(15, 23, 42, 0.03),
        0 1px 2px -1px rgba(15, 23, 42, 0.02);
    transition: all 0.2s ease;
}
.ppd--memuat {
    opacity: 0.75;
}
.ppd__baris {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.ppd__presets-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.ppd__presets {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.ppd__preset-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-600, #475569);
    display: inline-flex;
    align-items: center;
    letter-spacing: 0.01em;
}
.hcis-filter-segmented {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    padding: 0.2rem;
    border-radius: var(--hcis-radius-full, 9999px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    gap: 0.2rem;
}
.hcis-filter-pill {
    border: none;
    background: transparent;
    color: var(--hcis-slate-600, #475569);
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: var(--hcis-radius-full, 9999px);
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    line-height: 1.25;
}
.hcis-filter-pill:hover {
    color: var(--hcis-indigo-600, #4f46e5);
    background: rgba(255, 255, 255, 0.75);
}
.hcis-filter-pill--active {
    background: #ffffff !important;
    color: var(--hcis-indigo-600, #4f46e5) !important;
    box-shadow:
        0 1px 3px rgba(0, 0, 0, 0.08),
        0 1px 2px rgba(0, 0, 0, 0.04);
    font-weight: 700;
}
.ppd__pilih {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.ppd-select-period {
    width: 175px;
    min-width: 135px;
}
.ppd-select-year {
    width: 88px;
    min-width: 82px;
}
.ppd__panah {
    width: 32px;
    height: 32px;
    border-radius: var(--hcis-radius-md, 0.5rem);
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.ppd__panah:not(:disabled):hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}
.ppd__panah:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
.ppd__terapkan {
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.3rem 0.75rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    height: 32px;
    white-space: nowrap;
    flex-shrink: 0;
}
.ppd__terapkan:hover,
.ppd__terapkan:focus-visible {
    background: rgba(99, 102, 241, 0.16);
    border-color: rgba(99, 102, 241, 0.35);
    color: #4338ca;
}
.ppd__strip {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 5px;
    margin-top: 0.875rem;
}
.ppd__sel {
    height: 30px;
    border-radius: var(--hcis-radius-md, 0.45rem);
    border: 1px solid transparent;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0;
    cursor: pointer;
    transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    justify-content: center;
}
.ppd__sel:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
}
.ppd__sel:disabled {
    cursor: not-allowed;
}
.ppd__sel--terisi {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
    box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2);
}
.ppd__sel--menunggu {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #92400e;
}
.ppd__sel--belum {
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    color: #475569;
}
.ppd__sel--tanpa_target {
    background: repeating-linear-gradient(135deg, #f8fafc, #f8fafc 4px, #f1f5f9 4px, #f1f5f9 8px);
    border-color: #e2e8f0;
    color: #94a3b8;
}
.ppd__sel--mendatang {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    color: #cbd5e1;
}
.ppd__sel--aktif {
    box-shadow:
        0 0 0 2px #ffffff,
        0 0 0 3.5px var(--hcis-primary, #4f46e5) !important;
    font-weight: 800;
    z-index: 2;
}
.ppd__legenda {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 1rem;
    list-style: none;
    margin: 0.625rem 0 0;
    padding: 0;
    font-size: 0.7rem;
    color: #64748b;
}
.ppd__legenda li {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.ppd__titik {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .ppd {
        padding: 0.75rem;
        margin-bottom: 1rem;
    }
    .ppd__baris {
        gap: 0.5rem;
    }
    .ppd-select-period {
        width: 140px;
    }
}

@media (max-width: 576px) {
    .ppd__baris {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    .ppd__presets-wrap {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.375rem;
    }
    .ppd__presets {
        flex: 1 1 auto;
        min-width: 0;
    }
    .ppd__presets .hcis-filter-segmented {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2px;
    }
    .ppd__presets .hcis-filter-pill {
        justify-content: center;
        padding: 0.35rem 0.2rem;
        font-size: 0.72rem;
    }
    .ppd__pilih {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .ppd-select-period {
        flex: 1 1 auto;
        min-width: 110px;
    }
    .ppd-select-year {
        width: 82px;
        min-width: 80px;
        flex-shrink: 0;
    }
    .ppd__panah {
        width: 32px;
        height: 32px;
    }
    .ppd__strip {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 4px;
        margin-top: 0.625rem;
    }
    .ppd__sel {
        height: 30px;
        font-size: 0.75rem;
        font-weight: 700;
        border-radius: var(--hcis-radius-md, 0.4rem);
    }
    .ppd__legenda {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        font-size: 0.6875rem;
        gap: 0.35rem 0.5rem;
        margin-top: 0.5rem;
    }
}
</style>
