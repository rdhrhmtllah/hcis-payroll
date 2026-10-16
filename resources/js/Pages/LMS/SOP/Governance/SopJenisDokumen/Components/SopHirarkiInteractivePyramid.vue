<template>
    <div class="sop-hirarki-interactive">
        <!-- ══ TOP EXECUTIVE BANNER ══ -->
        <div class="hirarki-hero-banner mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="hirarki-hero-icon">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                    <div>
                        <h3 class="hirarki-hero-title">Piramid Arsitektur Mutu ISO 9001</h3>
                        <p class="hirarki-hero-desc mb-0">
                            Struktur dokumentasi bertingkat dari pucuk kebijakan strategis hingga formulir rekaman teknis di lapangan.
                        </p>
                    </div>
                </div>

                <!-- Live Summary Pill -->
                <div class="d-flex align-items-center gap-2">
                    <div class="hirarki-stat-pill">
                        <span class="hirarki-stat-pill__num">{{ tiers.length }}</span>
                        <span class="hirarki-stat-pill__lbl">Tingkat Lapis</span>
                    </div>
                    <div class="hirarki-stat-pill">
                        <span class="hirarki-stat-pill__num">{{ totalDokumen }}</span>
                        <span class="hirarki-stat-pill__lbl">Total Dokumen</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ DUAL-PANE INTERACTIVE MATRIX ══ -->
        <div class="row g-4 align-items-stretch">
            <!-- ── LEFT PANE: 3D STEPPED QUALITY PYRAMID TOWER ── -->
            <div class="col-12 col-xl-6">
                <div class="hirarki-pyramid-container h-100">
                    <div class="hirarki-pyramid-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="pyramid-label-top">
                                <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                                Puncak Arahan Strategis (Apex)
                            </span>
                            <span class="pyramid-hint-badge">
                                <i class="bi bi-cursor-fill me-1"></i>Klik lapis untuk inspeksi
                            </span>
                        </div>
                    </div>

                    <!-- Flow Indicator: Top-Down vs Bottom-Up -->
                    <div class="pyramid-flow-wrapper">
                        <!-- Left Vector: Top Down -->
                        <div class="pyramid-flow-vector flow-left" title="Top-Down: Menurunkan mandat & arahan kebijakan">
                            <i class="bi bi-arrow-down-short"></i>
                            <span class="flow-text">Arahan Kebijakan</span>
                        </div>

                        <!-- Stepped Pyramid Funnel -->
                        <div class="pyramid-stepped-tower">
                            <div
                                v-for="(tier, idx) in tiers"
                                :key="`pyr-${tier.level}`"
                                class="pyramid-step-tier"
                                :class="{ 'is-selected': selectedLevel === tier.level }"
                                :style="{
                                    '--tier-color': tier.color,
                                    '--step-width': `${getTierWidth(idx, tiers.length)}%`,
                                    '--tier-z': tiers.length - idx,
                                }"
                                tabindex="0"
                                role="button"
                                :aria-label="`Tingkat ${tier.level}: ${tier.nama}. ${tier.dokumen} dokumen terdaftar.`"
                                @click="selectLevel(tier.level)"
                                @keyup.enter="selectLevel(tier.level)"
                            >
                                <div class="pyramid-step-tier__inner">
                                    <div class="step-tier__left">
                                        <span class="step-tier__badge">L{{ tier.level }}</span>
                                        <span class="step-tier__name text-truncate">{{ tier.nama }}</span>
                                    </div>
                                    <div class="step-tier__right">
                                        <span class="step-tier__count">
                                            <i class="bi bi-file-earmark-text me-1 opacity-75"></i>
                                            {{ tier.dokumen }} dok
                                        </span>
                                        <i class="bi bi-chevron-right step-tier__chevron"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Vector: Bottom Up -->
                        <div class="pyramid-flow-vector flow-right" title="Bottom-Up: Rekaman bukti kepatuhan operasional">
                            <i class="bi bi-arrow-up-short"></i>
                            <span class="flow-text">Bukti Rekaman</span>
                        </div>
                    </div>

                    <div class="hirarki-pyramid-footer">
                        <div class="d-flex align-items-center justify-content-between text-muted small">
                            <span><i class="bi bi-layers-fill text-success me-1"></i>Dasar Bukti Operasional (Base)</span>
                            <span class="font-monospace">Standar ISO 9001:2015</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT PANE: EXECUTIVE TIER INSPECTOR ── -->
            <div class="col-12 col-xl-6">
                <div v-if="activeTier" class="hirarki-inspector-card h-100" :style="{ '--tier-accent': activeTier.color }">
                    <!-- Inspector Header -->
                    <div class="inspector-head">
                        <div class="d-flex align-items-start gap-3">
                            <div class="inspector-badge">
                                <span class="inspector-badge__num">L{{ activeTier.level }}</span>
                                <span class="inspector-badge__tag">TINGKAT {{ activeTier.level }}</span>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <h4 class="inspector-title mb-0">{{ activeTier.nama }}</h4>
                                    <HcisBadge
                                        :label="`${activeTier.dokumen} Dokumen Terdaftar`"
                                        :variant="activeTier.dokumen > 0 ? 'brand' : 'neutral'"
                                        appearance="subtle"
                                        size="xs"
                                        :leading-icon="activeTier.dokumen > 0 ? 'file-earmark-check-fill' : 'file-earmark'"
                                    />
                                </div>
                                <span class="inspector-mission-pill">
                                    <i class="bi bi-bullseye me-1.5"></i>
                                    {{ activeTier.pertanyaan }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Inspector Body -->
                    <div class="inspector-body">
                        <!-- 1. Deskripsi Ruang Lingkup -->
                        <div class="inspector-section mb-3">
                            <span class="inspector-section__title">
                                <i class="bi bi-card-text text-primary me-1.5"></i>Ruang Lingkup &amp; Peran Tata Kelola
                            </span>
                            <div class="inspector-desc-box">
                                <p class="mb-0">{{ activeTier.deskripsi }}</p>
                            </div>
                        </div>

                        <!-- 2. Governance Characteristics Grid -->
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-sm-6">
                                <div class="inspector-meta-box">
                                    <span class="meta-lbl">Tingkat Otoritas Penyetuju</span>
                                    <span class="meta-val fw-bold">
                                        <i class="bi bi-person-badge text-primary me-1"></i>
                                        {{ getTierAuthority(activeTier.level) }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="inspector-meta-box">
                                    <span class="meta-lbl">Karakteristik &amp; Volume</span>
                                    <span class="meta-val">
                                        <i class="bi bi-speedometer2 text-primary me-1"></i>
                                        {{ getTierVolumeTrait(activeTier.level) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Jenis Dokumen yang Dinaungi -->
                        <div class="inspector-section mb-3">
                            <span class="inspector-section__title">
                                <i class="bi bi-files text-primary me-1.5"></i>Jenis Dokumen Terdaftar di Tingkat Ini
                            </span>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <button
                                    v-for="item in activeTier.items"
                                    :key="item.Id_SOP_Jenis_Dokumen"
                                    type="button"
                                    class="inspector-chip-btn"
                                    :title="`Klik untuk melihat detail ${item.Nama_Jenis}`"
                                    @click="$emit('view-detail', item)"
                                >
                                    <span v-if="item.Kode_Jenis_Dokumen" class="chip-code">#{{ item.Kode_Jenis_Dokumen }}</span>
                                    <span class="chip-name">{{ item.Nama_Jenis }}</span>
                                    <span class="chip-count">({{ item.Jumlah_Dokumen || 0 }})</span>
                                    <i class="bi bi-eye-fill chip-icon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- 4. Format Nomor & Tipe Item -->
                        <div class="inspector-section">
                            <div class="inspector-syntax-box">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="syntax-label">
                                        <i class="bi bi-code-square me-1"></i>Contoh Format Penomoran Terbitan
                                    </span>
                                    <span class="syntax-preview font-monospace">
                                        {{ (activeTier.items[0]?.Kode_Jenis_Dokumen || 'KODE') }}/DIV/DEP/YY/NN
                                    </span>
                                </div>
                                <div v-if="activeTier.items[0]?.Tipe_Item?.length" class="d-flex align-items-center gap-1.5 flex-wrap mt-2 pt-2 border-top">
                                    <span class="syntax-sub-label">Tipe Item Sah:</span>
                                    <HcisBadge
                                        v-for="tipe in activeTier.items[0].Tipe_Item"
                                        :key="tipe"
                                        :label="tipe"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inspector Footer -->
                    <div class="inspector-footer mt-auto pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small">
                                <i class="bi bi-info-circle me-1"></i>Gunakan mode tabel untuk ubah urutan
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <HcisButton
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="table"
                                    label="Lihat di Daftar Master"
                                    @click="$emit('switch-to-master', activeTier.level)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ BOTTOM 3-PILLAR COMPARATIVE GUIDE STRIP ══ -->
        <div class="hirarki-pillars-grid mt-4">
            <div class="pillar-card pillar--strategic">
                <div class="pillar-card__icon">
                    <i class="bi bi-compass-fill"></i>
                </div>
                <div>
                    <h5 class="pillar-card__title">1. Lapis Strategis (L1 - L2)</h5>
                    <p class="pillar-card__desc mb-0">
                        Kebijakan &amp; Ketetapan Direksi yang menetapkan komitmen, prinsip, dan arah tata kelola perusahaan.
                    </p>
                </div>
            </div>

            <div class="pillar-card pillar--tactical">
                <div class="pillar-card__icon">
                    <i class="bi bi-diagram-3-fill"></i>
                </div>
                <div>
                    <h5 class="pillar-card__title">2. Lapis Taktis &amp; Proses (L3)</h5>
                    <p class="pillar-card__desc mb-0">
                        Prosedur Operasional Standar (SOP) lintas divisi yang mendefinisikan alur proses bisnis baku (Siapa, Apa, Kapan).
                    </p>
                </div>
            </div>

            <div class="pillar-card pillar--operational">
                <div class="pillar-card__icon">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <div>
                    <h5 class="pillar-card__title">3. Lapis Operasional &amp; Bukti (L4 - L7)</h5>
                    <p class="pillar-card__desc mb-0">
                        Instruksi Kerja teknis (Bagaimana), Standar Mutu, Formulir, dan Manual yang merekam bukti nyata pelaksanaan kerja.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisBadge, HcisButton } from '@/components/ui';

defineOptions({ name: 'SopHirarkiInteractivePyramid' });

const props = defineProps({
    tiers: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['view-detail', 'switch-to-master']);

const selectedLevel = ref(props.tiers[0]?.level || 1);

watch(
    () => props.tiers,
    (newTiers) => {
        if (newTiers.length && !newTiers.some((t) => t.level === selectedLevel.value)) {
            selectedLevel.value = newTiers[0].level;
        }
    },
    { immediate: true }
);

const selectLevel = (lvl) => {
    selectedLevel.value = lvl;
};

const activeTier = computed(() => {
    return props.tiers.find((t) => t.level === selectedLevel.value) || props.tiers[0] || null;
});

const totalDokumen = computed(() => {
    return props.tiers.reduce((acc, cur) => acc + (cur.dokumen || 0), 0);
});

/** Menghitung lebar piramid berjenjang dari puncak (48%) ke dasar (100%) */
const getTierWidth = (idx, totalCount) => {
    if (totalCount <= 1) return 100;
    const minW = 50;
    const maxW = 100;
    const step = (maxW - minW) / (totalCount - 1);
    return Math.round(minW + idx * step);
};

const getTierAuthority = (level) => {
    const authorities = {
        1: 'Direksi / Puncak Manajemen (CEO/BOD)',
        2: 'Direksi / Komite Tata Kelola',
        3: 'Head of Division / Kepala Divisi',
        4: 'Department Head / Supervisor Teknis',
        5: 'Quality Assurance / Tim Standarisasi',
        6: 'Seluruh Unit Kerja / Pelaksana Lapangan',
        7: 'Management Representative / Lead Auditor',
    };
    return authorities[level] || 'Pimpinan Unit Kerja';
};

const getTierVolumeTrait = (level) => {
    const traits = {
        1: 'Volume Rendah · Otoritas Tertinggi',
        2: 'Volume Rendah · Mandat Mengikat',
        3: 'Volume Sedang · Alur Lintas Fungsi',
        4: 'Volume Tinggi · Spesifik Per Tugas',
        5: 'Volume Sedang · Parameter Mutu',
        6: 'Volume Sangat Tinggi · Rekaman Harian',
        7: 'Volume Rendah · Referensi Umum',
    };
    return traits[level] || 'Standar Dokumen';
};
</script>

<style scoped>
.sop-hirarki-interactive {
    width: 100%;
}

/* Hero Banner */
.hirarki-hero-banner {
    padding: 1.1rem 1.35rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(124, 58, 237, 0.06) 100%);
    border: 1px solid rgba(37, 99, 235, 0.2);
    box-shadow: 0 2px 10px -2px rgba(37, 99, 235, 0.1);
}

.hirarki-hero-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--hcis-radius-lg, 12px);
    display: grid;
    place-items: center;
    font-size: 1.35rem;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-blue-600), var(--hcis-violet-600));
    box-shadow: 0 4px 10px -2px rgba(37, 99, 235, 0.4);
    flex-shrink: 0;
}

.hirarki-hero-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0 0 0.15rem 0;
}

.hirarki-hero-desc {
    font-size: 0.835rem;
    line-height: 1.45;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.hirarki-stat-pill {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.35rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.8);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.hirarki-stat-pill__num {
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-brand-700, var(--hcis-sky-700));
    line-height: 1;
}

.hirarki-stat-pill__lbl {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin-top: 0.2rem;
}

/* Pyramid Left Container */
.hirarki-pyramid-container {
    display: flex;
    flex-direction: column;
    padding: 1.25rem 1.35rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 4px 14px -3px rgba(0, 0, 0, 0.05);
}

.hirarki-pyramid-header,
.hirarki-pyramid-footer {
    padding: 0.25rem 0;
}

.pyramid-label-top {
    font-size: 0.825rem;
    font-weight: 750;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.pyramid-hint-badge {
    font-size: 0.725rem;
    font-weight: 600;
    color: var(--hcis-brand-700, var(--hcis-sky-700));
    background: rgba(2, 132, 199, 0.1);
    padding: 0.15rem 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
}

/* Stepped Tower with Flow Vectors */
.pyramid-flow-wrapper {
    position: relative;
    display: flex;
    align-items: stretch;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem 0;
    margin: 0.5rem 0;
}

.pyramid-flow-vector {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 28px;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-300);
    font-size: 1.15rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    flex-shrink: 0;
    padding: 0.5rem 0.15rem;
}

.pyramid-flow-vector.flow-left {
    color: var(--hcis-brand-600, var(--hcis-sky-600));
    border-color: rgba(2, 132, 199, 0.3);
    background: rgba(2, 132, 199, 0.04);
}

.pyramid-flow-vector.flow-right {
    color: var(--hcis-emerald-600, var(--hcis-emerald-600));
    border-color: rgba(5, 150, 105, 0.3);
    background: rgba(5, 150, 105, 0.04);
}

.flow-text {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin: 0.35rem 0;
}

/* Stepped Pyramid Tower */
.pyramid-stepped-tower {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.pyramid-step-tier {
    width: var(--step-width, 100%);
    min-width: 180px;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, color-mix(in srgb, var(--tier-color, var(--hcis-blue-600)) 8%, #fff) 100%);
    border: 1.5px solid color-mix(in srgb, var(--tier-color, var(--hcis-blue-600)) 35%, var(--hcis-slate-200));
    box-shadow: 0 2px 6px -1px rgba(0, 0, 0, 0.04);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}

.pyramid-step-tier::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: var(--tier-color, var(--hcis-blue-600));
    transition: width 0.2s ease;
}

.pyramid-step-tier:hover {
    transform: translateY(-2px) scale(1.015);
    border-color: var(--tier-color, var(--hcis-blue-600));
    box-shadow: 0 8px 18px -4px color-mix(in srgb, var(--tier-color, var(--hcis-blue-600)) 25%, rgba(0, 0, 0, 0.08));
}

.pyramid-step-tier:hover::before {
    width: 8px;
}

.pyramid-step-tier.is-selected {
    border-color: var(--tier-color, var(--hcis-blue-600));
    background: linear-gradient(135deg, color-mix(in srgb, var(--tier-color, var(--hcis-blue-600)) 18%, #fff) 0%, color-mix(in srgb, var(--tier-color, var(--hcis-blue-600)) 8%, #fff) 100%);
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--tier-color, var(--hcis-blue-600)) 30%, transparent),
        0 8px 20px -4px color-mix(in srgb, var(--tier-color, var(--hcis-blue-600)) 30%, rgba(0, 0, 0, 0.1));
    transform: scale(1.02);
}

.pyramid-step-tier.is-selected::before {
    width: 8px;
}

.pyramid-step-tier__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0.85rem 0.55rem 1rem;
    gap: 0.5rem;
}

.step-tier__left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.step-tier__badge {
    display: inline-grid;
    place-items: center;
    min-width: 28px;
    height: 24px;
    padding: 0 0.35rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: var(--tier-color, var(--hcis-blue-600));
    color: #fff;
    font-size: 0.725rem;
    font-weight: 850;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    flex-shrink: 0;
}

.step-tier__name {
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
}

.step-tier__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.step-tier__count {
    font-size: 0.735rem;
    font-weight: 600;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(203, 213, 225, 0.7);
    padding: 0.1rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
}

.step-tier__chevron {
    font-size: 0.75rem;
    color: var(--tier-color, var(--hcis-blue-600));
    transition: transform 0.2s ease;
}

.pyramid-step-tier.is-selected .step-tier__chevron {
    transform: translateX(2px);
}

/* Right Pane: Executive Inspector Card */
.hirarki-inspector-card {
    display: flex;
    flex-direction: column;
    padding: 1.35rem 1.5rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 4px 14px -3px rgba(0, 0, 0, 0.05);
    border-top: 4px solid var(--tier-accent, var(--hcis-blue-600));
}

.inspector-head {
    margin-bottom: 1.15rem;
}

.inspector-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 54px;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, var(--tier-accent, var(--hcis-blue-600)) 0%, color-mix(in srgb, var(--tier-accent, var(--hcis-blue-600)) 80%, #000) 100%);
    color: #fff;
    box-shadow: 0 3px 8px -2px color-mix(in srgb, var(--tier-accent, var(--hcis-blue-600)) 40%, transparent);
    flex-shrink: 0;
}

.inspector-badge__num {
    font-size: 1.25rem;
    font-weight: 850;
    line-height: 1;
}

.inspector-badge__tag {
    font-size: 0.55rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    opacity: 0.85;
    margin-top: 0.15rem;
}

.inspector-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
}

.inspector-mission-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.785rem;
    font-weight: 700;
    color: var(--tier-accent, var(--hcis-blue-600));
    background: color-mix(in srgb, var(--tier-accent, var(--hcis-blue-600)) 12%, #fff);
    border: 1px solid color-mix(in srgb, var(--tier-accent, var(--hcis-blue-600)) 26%, transparent);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.65rem;
    margin-top: 0.25rem;
}

/* Sections */
.inspector-section__title {
    display: flex;
    align-items: center;
    font-size: 0.785rem;
    font-weight: 750;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    margin-bottom: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.inspector-desc-box {
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.85rem;
    line-height: 1.55;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

.inspector-meta-box {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    display: flex;
    flex-direction: column;
}

.meta-lbl {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.meta-val {
    font-size: 0.825rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    margin-top: 0.2rem;
}

.inspector-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    cursor: pointer;
    transition: all 0.15s ease;
}

.inspector-chip-btn:hover {
    background: color-mix(in srgb, var(--tier-accent, var(--hcis-blue-600)) 12%, #fff);
    border-color: var(--tier-accent, var(--hcis-blue-600));
    color: var(--tier-accent, var(--hcis-blue-600));
    transform: translateY(-1px);
}

.chip-code {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.7rem;
    font-weight: 750;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

.chip-count {
    font-size: 0.725rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.chip-icon {
    font-size: 0.725rem;
    opacity: 0.7;
}

.inspector-syntax-box {
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.03) 0%, rgba(15, 23, 42, 0.06) 100%);
    border: 1px solid var(--hcis-slate-300);
}

.syntax-label {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.syntax-preview {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-brand-700, var(--hcis-sky-700));
    background: var(--hcis-white);
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    border: 1px solid var(--hcis-slate-200);
}

.syntax-sub-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

/* Bottom Pillars */
.hirarki-pillars-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 992px) {
    .hirarki-pillars-grid {
        grid-template-columns: 1fr;
    }
}

.pillar-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.pillar-card__icon {
    width: 36px;
    height: 36px;
    border-radius: var(--hcis-radius-md, 8px);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.pillar--strategic .pillar-card__icon {
    color: var(--hcis-blue-600);
    background: rgba(37, 99, 235, 0.1);
}

.pillar--tactical .pillar-card__icon {
    color: var(--hcis-sky-600);
    background: rgba(2, 132, 199, 0.1);
}

.pillar--operational .pillar-card__icon {
    color: var(--hcis-emerald-600);
    background: rgba(5, 150, 105, 0.1);
}

.pillar-card__title {
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0 0 0.15rem 0;
}

.pillar-card__desc {
    font-size: 0.785rem;
    line-height: 1.45;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}
</style>
