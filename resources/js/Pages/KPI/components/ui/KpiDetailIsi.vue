<template>
    <div class="kpi-detail-isi">
        <!-- 2. Modal Loading Skeleton -->
        <div v-if="loading" class="hcis-modal-skeleton py-2">
            <div class="row g-3 mb-3">
                <div class="col-12 col-lg-7">
                    <div class="hcis-detail-hero-card p-4">
                        <div class="d-flex align-items-center mb-3">
                            <HcisSkeleton width="48px" height="48px" rounded="full" class="me-3" />
                            <div class="flex-grow-1">
                                <HcisSkeleton width="55%" height="1.25rem" rounded="sm" class="mb-2" />
                                <HcisSkeleton width="35%" height="0.875rem" rounded="sm" />
                            </div>
                        </div>
                        <div class="row g-2">
                            <div v-for="i in 4" :key="i" class="col-6">
                                <HcisSkeleton width="100%" height="2.5rem" rounded="md" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="hcis-detail-hero-card p-4 text-center">
                        <HcisSkeleton width="50%" height="1rem" rounded="sm" class="mx-auto mb-3" />
                        <HcisSkeleton width="110px" height="110px" rounded="full" class="mx-auto mb-3" />
                        <div class="row g-2">
                            <div class="col-6"><HcisSkeleton width="100%" height="2.5rem" rounded="md" /></div>
                            <div class="col-6"><HcisSkeleton width="100%" height="2.5rem" rounded="md" /></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Periode tanpa isi (Empty State) -->
        <div v-else-if="kosong" class="hcis-detail-empty-card py-5 my-2">
            <div class="text-center">
                <div class="hcis-detail-empty-icon mb-3">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">{{ kosong.label || 'Periode Belum Berisi Data' }}</h5>
                <p class="text-muted small mb-0 px-3">{{ teksKosong }}</p>
            </div>
        </div>

        <!-- 4. Modal Content -->
        <div v-else class="hcis-detail-content py-1">
            <div class="row g-3 mb-4 align-items-stretch">
                <!-- Left: Employee Identity Card -->
                <div class="col-12 col-lg-7">
                    <div
                        class="hcis-detail-hero-card hcis-detail-hero-card--profile h-100 p-3 p-sm-4 d-flex flex-column gap-3"
                    >
                        <div
                            class="d-flex align-items-center justify-content-between pb-3 border-bottom flex-wrap gap-2"
                        >
                            <div class="d-flex align-items-center gap-3">
                                <div class="hcis-avatar-glow">
                                    <span class="hcis-avatar-glow__initials">{{ inisial }}</span>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold text-dark">
                                        {{ header.Nama_Lengkap || '...' }}
                                    </h5>
                                    <span class="text-muted small">
                                        {{ header.Nama_Jabatan || 'Staff / Pegawai' }}
                                    </span>
                                </div>
                            </div>
                            <HcisBadge :label="header.Periode || 'Periode Terpilih'" variant="brand" size="xs" dot />
                        </div>

                        <div class="row g-2 flex-grow-1 align-content-center">
                            <div class="col-12 col-sm-6">
                                <div class="hcis-meta-pod h-100">
                                    <div class="hcis-meta-pod__icon">
                                        <i class="bi bi-building"></i>
                                    </div>
                                    <div class="hcis-meta-pod__content">
                                        <span class="hcis-meta-pod__label">Departemen</span>
                                        <span class="hcis-meta-pod__val" :title="header.Nama_Departemen">
                                            {{ header.Nama_Departemen || '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="hcis-meta-pod h-100">
                                    <div class="hcis-meta-pod__icon">
                                        <i class="bi bi-briefcase"></i>
                                    </div>
                                    <div class="hcis-meta-pod__content">
                                        <span class="hcis-meta-pod__label">Posisi</span>
                                        <span class="hcis-meta-pod__val" :title="header.Nama_Jabatan">
                                            {{ header.Nama_Jabatan || '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="hcis-meta-pod h-100">
                                    <div class="hcis-meta-pod__icon">
                                        <i class="bi bi-envelope"></i>
                                    </div>
                                    <div class="hcis-meta-pod__content">
                                        <span class="hcis-meta-pod__label">Email</span>
                                        <span class="hcis-meta-pod__val" :title="header.Email">
                                            {{ header.Email || '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="hcis-meta-pod h-100">
                                    <div class="hcis-meta-pod__icon">
                                        <i class="bi bi-telephone"></i>
                                    </div>
                                    <div class="hcis-meta-pod__content">
                                        <span class="hcis-meta-pod__label">Kontak</span>
                                        <span class="hcis-meta-pod__val">
                                            {{ formatKontak(header.Kontak) || '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Performance Summary Pod -->
                <div class="col-12 col-lg-5">
                    <div
                        class="hcis-detail-hero-card hcis-detail-hero-card--cockpit h-100 p-3 p-sm-4 d-flex flex-column justify-content-between gap-2"
                    >
                        <div
                            class="w-100 d-flex align-items-center justify-content-between pb-2 border-bottom flex-wrap gap-1"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <div class="hcis-cockpit-icon">
                                    <i class="bi bi-award-fill"></i>
                                </div>
                                <div>
                                    <span class="hcis-detail-section-title d-block">Ringkasan Capaian</span>
                                    <span class="text-muted" style="font-size: 0.7rem">Skor Akumulasi Periode</span>
                                </div>
                            </div>
                            <HcisBadge
                                :label="header.Status || 'Capaian'"
                                :sentiment="sentimen"
                                size="xs"
                                variant="subtle"
                                dot
                            />
                        </div>

                        <div class="hcis-detail-ring-box my-auto position-relative">
                            <HcisChart :card="false" type="custom" :options="radialOptions" :height="100" />
                        </div>

                        <div class="row g-2 w-100">
                            <div class="col-6">
                                <div class="hcis-stat-pod hcis-stat-pod--target">
                                    <span class="hcis-stat-pod__label">
                                        <i class="bi bi-bullseye me-1 text-primary"></i>Target Skor
                                    </span>
                                    <span class="hcis-stat-pod__val font-monospace text-primary">100%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="hcis-stat-pod hcis-stat-pod--status">
                                    <span class="hcis-stat-pod__label">
                                        <i
                                            class="bi bi-shield-check me-1"
                                            :style="{ color: header.Warna || 'var(--hcis-brand-600)' }"
                                        ></i
                                        >Status
                                    </span>
                                    <span
                                        class="hcis-stat-pod__val text-truncate"
                                        :style="{ color: header.Warna || 'var(--hcis-brand-600)' }"
                                        :title="header.Status || 'N/A'"
                                    >
                                        {{ header.Status || 'N/A' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parameter Breakdown Section -->
            <div class="hcis-detail-parameters mt-3">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">Rincian Parameter KPI</h6>
                        <span class="text-muted small">Capaian riil dan bobot per parameter kinerja</span>
                    </div>
                    <HcisBadge :label="`${items.length} Parameter`" variant="purple" size="xs" />
                </div>

                <div v-if="items.length > 0" class="row g-3">
                    <div v-for="item in items" :key="item.id" class="col-12 col-lg-6">
                        <KpiDetailItem :item="item" :isFullYear="isFullYear" :bar-color="header.Warna" />
                    </div>
                </div>

                <div v-else class="hcis-detail-empty-card py-4 text-center">
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Data rincian KPI tidak ditemukan pada periode ini.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import * as echarts from 'echarts';
import { HcisBadge, HcisChart, HcisSkeleton } from '@/components/ui';
import KpiDetailItem from '../../Performance/Dashboard/ceo/components/recentKpiComponents.vue';

/**
 * Isi modal "Detail Performance": kerangka muat, keadaan kosong, kartu
 * identitas, ringkasan capaian, dan rincian parameter.
 *
 * Diangkat dari halaman individu supaya dashboard tim memakai BADAN yang sama.
 * Dulu blok ini ditulis ulang di dua halaman, sehingga setiap perbaikan
 * (deskripsi parameter, keadaan kosong per periode) harus dikerjakan dua kali
 * lalu tertinggal di salah satunya. Yang TIDAK ikut ke sini: pembungkus dialog,
 * pemilih periode, dan judulnya -- itu milik halaman, karena judul dan haknya
 * memang berbeda antara "performa saya" dan "performa tim".
 */
export default {
    name: 'KpiDetailIsi',
    components: { HcisBadge, HcisChart, HcisSkeleton, KpiDetailItem },
    props: {
        /** Header detail: nama, jabatan, departemen, kontak, Achievement, Status, Warna. */
        header: { type: Object, default: () => ({}) },
        /** Baris per parameter dari endpoint detail. */
        items: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        /** Diisi {status, label} bila periode terpilih memang tidak punya isi. */
        kosong: { type: Object, default: null },
        isFullYear: { type: Boolean, default: false },
    },
    computed: {
        inisial() {
            const nama = (this.header?.Nama_Lengkap || '').trim();
            if (!nama) return 'MP';
            const bagian = nama.split(/\s+/).filter(Boolean);
            if (bagian.length === 1) return bagian[0].substring(0, 2).toUpperCase();
            return (bagian[0][0] + bagian[1][0]).toUpperCase();
        },
        capaianAngka() {
            const mentah = this.header?.Achievement;
            if (mentah === undefined || mentah === null) return 0;
            const angka = typeof mentah === 'number' ? mentah : parseFloat(mentah);
            return isNaN(angka) ? 0 : angka;
        },
        capaianTerformat() {
            return this.formatScore(this.capaianAngka);
        },
        sentimen() {
            const status = (this.header?.Status || '').toLowerCase();
            const skor = this.capaianAngka;
            if (status.includes('excellent') || status.includes('sangat baik') || skor >= 100) return 'success';
            if (status.includes('good') || status.includes('baik') || skor >= 85) return 'brand';
            if (status.includes('satisfactory') || status.includes('cukup') || skor >= 70) return 'warning';
            if (status.includes('poor') || status.includes('kurang') || (skor > 0 && skor < 70)) return 'danger';
            return 'brand';
        },
        teksKosong() {
            return (
                {
                    belum: 'Belum ada update performance yang disetujui pada periode ini.',
                    menunggu: 'Update performance periode ini masih menunggu persetujuan.',
                    tanpa_target: 'Tidak ada target KPI yang jatuh tempo pada periode ini.',
                    mendatang: 'Periode ini belum berjalan.',
                    gagal: 'Detail periode ini gagal dimuat. Coba periode lain atau muat ulang halaman.',
                }[this.kosong?.status] || 'Belum ada data pada periode ini.'
            );
        },
        radialOptions() {
            const skor = this.capaianAngka;
            const terformat = this.capaianTerformat;

            const palet = {
                success: { start: '#10b981', end: '#059669', shadow: 'rgba(16, 185, 129, 0.25)' },
                brand: { start: '#6366f1', end: '#4f46e5', shadow: 'rgba(99, 102, 241, 0.25)' },
                warning: { start: '#f59e0b', end: '#d97706', shadow: 'rgba(245, 158, 11, 0.25)' },
                danger: { start: '#ef4444', end: '#dc2626', shadow: 'rgba(239, 68, 68, 0.25)' },
            }[this.sentimen] || { start: '#6366f1', end: '#4f46e5', shadow: 'rgba(99, 102, 241, 0.25)' };

            const warnaKhusus = this.header?.Warna;
            const warnaAwal = warnaKhusus || palet.start;
            const warnaAkhir = warnaKhusus || palet.end;

            return {
                series: [
                    {
                        type: 'gauge',
                        startAngle: 220,
                        endAngle: -40,
                        min: 0,
                        max: 100,
                        radius: '80%',
                        center: ['50%', '54%'],
                        pointer: { show: false },
                        progress: {
                            show: true,
                            overlap: false,
                            roundCap: true,
                            clip: false,
                            width: 7.5,
                            itemStyle: {
                                color: new echarts.graphic.LinearGradient(0, 0, 1, 1, [
                                    { offset: 0, color: warnaAwal },
                                    { offset: 1, color: warnaAkhir },
                                ]),
                                shadowColor: palet.shadow,
                                shadowBlur: 5,
                            },
                        },
                        axisLine: {
                            roundCap: true,
                            lineStyle: {
                                width: 7.5,
                                color: [[1, 'rgba(226, 232, 240, 0.75)']],
                            },
                        },
                        splitLine: { show: false },
                        axisTick: { show: false },
                        axisLabel: { show: false },
                        title: {
                            show: true,
                            offsetCenter: [0, '22%'],
                            fontSize: 8.5,
                            fontWeight: 600,
                            color: '#64748b',
                            fontFamily: 'Inter, system-ui, sans-serif',
                        },
                        detail: {
                            show: true,
                            valueAnimation: true,
                            offsetCenter: [0, '-10%'],
                            fontSize: 16,
                            fontWeight: 800,
                            fontFamily: 'Inter, system-ui, sans-serif',
                            color: '#0f172a',
                            formatter: () => `${terformat}%`,
                        },
                        data: [{ value: Math.min(Math.max(skor, 0), 100), name: 'Capaian' }],
                    },
                ],
            };
        },
    },
    methods: {
        formatKontak(kontak) {
            if (!kontak) return '';
            const bersih = kontak.toString().replace(/[\s-]/g, '');
            if (bersih.startsWith('+62')) return bersih;
            if (bersih.startsWith('62')) return '+' + bersih;
            if (bersih.startsWith('0')) return '+62' + bersih.substring(1);
            return bersih;
        },
        formatScore(nilai) {
            if (typeof nilai !== 'number' || !isFinite(nilai)) return 0;
            if (Number.isInteger(nilai)) return nilai;
            return parseFloat(nilai.toFixed(1));
        },
    },
};
</script>

<style scoped>
/* 6. Detail Modal Specific Styles */
.hcis-detail-hero-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 0.875rem);
    box-shadow:
        0 1px 3px 0 rgba(15, 23, 42, 0.03),
        0 1px 2px -1px rgba(15, 23, 42, 0.02);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.hcis-detail-hero-card--profile {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.8) 0%, #ffffff 100%);
}

.hcis-detail-hero-card--cockpit {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.85) 0%, #ffffff 100%);
}

.hcis-avatar-glow {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-indigo-500, #6366f1) 0%, var(--hcis-indigo-700, #4338ca) 100%);
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.12),
        0 4px 12px -2px rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.hcis-avatar-glow__initials {
    font-size: 0.9375rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: 0.02em;
}

.hcis-cockpit-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: rgba(99, 102, 241, 0.08);
    color: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.hcis-meta-pod {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: var(--hcis-radius-md, 0.625rem);
    min-width: 0;
    transition: all 0.2s ease;
}

.hcis-meta-pod:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
    transform: translateY(-1px);
}

.hcis-meta-pod__icon {
    width: 32px;
    height: 32px;
    border-radius: var(--hcis-radius-md, 0.45rem);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.hcis-meta-pod__content {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex-grow: 1;
}

.hcis-meta-pod__label {
    font-size: 0.65rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    line-height: 1.1;
    margin-bottom: 0.15rem;
}

.hcis-meta-pod__val {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
}

.hcis-detail-ring-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 104px;
    min-height: 104px;
}

.hcis-stat-pod {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: var(--hcis-radius-md, 0.5rem);
    padding: 0.45rem 0.6rem;
    text-align: center;
    transition: all 0.2s ease;
}

.hcis-stat-pod:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
}

.hcis-stat-pod__label {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.15rem;
}

.hcis-stat-pod__val {
    display: block;
    font-size: 0.875rem;
    font-weight: 800;
    line-height: 1.2;
}

.hcis-detail-section-title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: #1e293b;
    letter-spacing: -0.01em;
}

.hcis-detail-empty-card {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: var(--hcis-radius-xl, 0.875rem);
    padding: 2.5rem 1.5rem;
}

.hcis-detail-empty-icon {
    font-size: 2.25rem;
    color: #94a3b8;
}

/* Responsif */
@media (max-width: 992px) {
    .hcis-detail-hero-card--profile,
    .hcis-detail-hero-card--cockpit {
        height: auto !important;
    }
}

@media (max-width: 768px) {
    .hcis-detail-hero-card {
        padding: 0.875rem !important;
    }

    .hcis-detail-ring-box {
        height: 92px;
        min-height: 92px;
    }
}

@media (max-width: 576px) {
    .hcis-avatar-glow {
        width: 42px;
        height: 42px;
    }
    .hcis-avatar-glow__initials {
        font-size: 0.875rem;
    }
    .hcis-meta-pod {
        padding: 0.5rem 0.65rem;
    }
}
</style>
