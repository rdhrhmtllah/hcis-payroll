<template>
    <div class="sop-apqc-taxonomy-visualizer">
        <!-- ══ 1. EXECUTIVE GUIDE BANNER ══ -->
        <div class="apqc-hero-banner mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="apqc-hero-icon">
                        <i class="bi bi-bounding-box"></i>
                    </div>
                    <div>
                        <h3 class="apqc-hero-title">Taksonomi Proses Bisnis APQC (PCF v7.3)</h3>
                        <p class="apqc-hero-desc mb-0">
                            Kerangka taksonomi proses standar global yang membagi proses bisnis ke dalam 2 domain: <strong>Operasional Inti (1.0 - 6.0)</strong> dan <strong>Manajemen &amp; Pendukung (7.0 - 13.0)</strong>.
                        </p>
                    </div>
                </div>

                <!-- Live Summary Pill -->
                <div class="d-flex align-items-center gap-2">
                    <div class="apqc-stat-pill">
                        <span class="apqc-stat-pill__num">{{ operatingProcesses.length }}</span>
                        <span class="apqc-stat-pill__lbl">Operasional</span>
                    </div>
                    <div class="apqc-stat-pill">
                        <span class="apqc-stat-pill__num">{{ supportProcesses.length }}</span>
                        <span class="apqc-stat-pill__lbl">Support</span>
                    </div>
                    <div class="apqc-stat-pill">
                        <span class="apqc-stat-pill__num">{{ totalDokumen }}</span>
                        <span class="apqc-stat-pill__lbl">Dokumen</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 2. DOMAIN 1: OPERATING PROCESSES (01.0 - 06.0) ══ -->
        <div class="apqc-domain-card mb-4 is-operating">
            <div class="apqc-domain-head">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="apqc-domain-icon operating-icon">
                            <i class="bi bi-gear-fill"></i>
                        </div>
                        <div>
                            <h4 class="apqc-domain-title">1.0 – 6.0: Operating Processes (Proses Operasional Inti)</h4>
                            <p class="apqc-domain-subtitle mb-0">
                                Rantai nilai utama (*Value Chain*) dari perumusan visi strategi, pengembangan produk, pemasaran, hingga rantai pasok dan layanan pelanggan.
                            </p>
                        </div>
                    </div>
                    <HcisBadge
                        :label="`${operatingDokumenTotal} Dokumen Terbit`"
                        variant="brand"
                        appearance="glass"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Grid of Operating Processes -->
            <div class="apqc-process-grid mt-3">
                <div
                    v-for="proc in operatingProcesses"
                    :key="proc.Id_SOP_APQC"
                    class="apqc-interactive-node"
                    :style="{ '--node-color': getProcessColor(proc.Kode_Proses) }"
                    tabindex="0"
                    role="button"
                    :aria-label="`Proses ${proc.Kode_Proses}: ${proc.Nama_Proses}`"
                    @click="$emit('view-detail', proc)"
                    @keyup.enter="$emit('view-detail', proc)"
                >
                    <div class="node-head">
                        <span class="node-code">PCF #{{ proc.Kode_Proses }}</span>
                        <span class="node-count" :class="(proc.Jumlah_Dokumen || 0) > 0 ? 'has-docs' : 'no-docs'">
                            <i class="bi" :class="(proc.Jumlah_Dokumen || 0) > 0 ? 'bi-file-earmark-check-fill' : 'bi-dash-circle'"></i>
                            {{ proc.Jumlah_Dokumen || 0 }} Dok
                        </span>
                    </div>

                    <h5 class="node-title" :title="proc.Nama_Proses">
                        {{ proc.Nama_Proses }}
                    </h5>

                    <p class="node-desc" :class="{ 'is-empty': !proc.Deskripsi }">
                        {{ proc.Deskripsi || getProcessDefaultDesc(proc.Kode_Proses) }}
                    </p>

                    <div class="node-footer">
                        <div v-if="proc.Tipe_Item && proc.Tipe_Item.length > 0" class="d-flex flex-wrap gap-1">
                            <span v-for="t in proc.Tipe_Item.slice(0, 2)" :key="t" class="node-type-pill">
                                {{ t }}
                            </span>
                            <span v-if="proc.Tipe_Item.length > 2" class="node-type-more">
                                +{{ proc.Tipe_Item.length - 2 }}
                            </span>
                        </div>
                        <span v-else class="text-muted small font-italic" style="font-size: 0.7rem;">
                            Semua Tipe
                        </span>

                        <span class="node-inspect-btn">
                            <span>Detail</span>
                            <i class="bi bi-arrow-right-short"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 3. DOMAIN 2: MANAGEMENT & SUPPORT SERVICES (07.0 - 13.0) ══ -->
        <div class="apqc-domain-card mb-5 is-support">
            <div class="apqc-domain-head">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="apqc-domain-icon support-icon">
                            <i class="bi bi-building-fill"></i>
                        </div>
                        <div>
                            <h4 class="apqc-domain-title">7.0 – 13.0: Management &amp; Support Services (Proses Manajemen &amp; Pendukung)</h4>
                            <p class="apqc-domain-subtitle mb-0">
                                Fondasi kapabilitas dan tata kelola pendukung mencakup SDM, TI, Keuangan, Manajemen Aset, Manajemen Risiko, Kepatuhan, dan Hubungan Eksternal.
                            </p>
                        </div>
                    </div>
                    <HcisBadge
                        :label="`${supportDokumenTotal} Dokumen Terbit`"
                        variant="purple"
                        appearance="glass"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Grid of Support Processes -->
            <div class="apqc-process-grid mt-3">
                <div
                    v-for="proc in supportProcesses"
                    :key="proc.Id_SOP_APQC"
                    class="apqc-interactive-node"
                    :style="{ '--node-color': getProcessColor(proc.Kode_Proses) }"
                    tabindex="0"
                    role="button"
                    :aria-label="`Proses ${proc.Kode_Proses}: ${proc.Nama_Proses}`"
                    @click="$emit('view-detail', proc)"
                    @keyup.enter="$emit('view-detail', proc)"
                >
                    <div class="node-head">
                        <span class="node-code">PCF #{{ proc.Kode_Proses }}</span>
                        <span class="node-count" :class="(proc.Jumlah_Dokumen || 0) > 0 ? 'has-docs' : 'no-docs'">
                            <i class="bi" :class="(proc.Jumlah_Dokumen || 0) > 0 ? 'bi-file-earmark-check-fill' : 'bi-dash-circle'"></i>
                            {{ proc.Jumlah_Dokumen || 0 }} Dok
                        </span>
                    </div>

                    <h5 class="node-title" :title="proc.Nama_Proses">
                        {{ proc.Nama_Proses }}
                    </h5>

                    <p class="node-desc" :class="{ 'is-empty': !proc.Deskripsi }">
                        {{ proc.Deskripsi || getProcessDefaultDesc(proc.Kode_Proses) }}
                    </p>

                    <div class="node-footer">
                        <div v-if="proc.Tipe_Item && proc.Tipe_Item.length > 0" class="d-flex flex-wrap gap-1">
                            <span v-for="t in proc.Tipe_Item.slice(0, 2)" :key="t" class="node-type-pill">
                                {{ t }}
                            </span>
                            <span v-if="proc.Tipe_Item.length > 2" class="node-type-more">
                                +{{ proc.Tipe_Item.length - 2 }}
                            </span>
                        </div>
                        <span v-else class="text-muted small font-italic" style="font-size: 0.7rem;">
                            Semua Tipe
                        </span>

                        <span class="node-inspect-btn">
                            <span>Detail</span>
                            <i class="bi bi-arrow-right-short"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge } from '@/components/ui';

defineOptions({ name: 'SopApqcTaxonomyVisualizer' });

const props = defineProps({
    processes: {
        type: Array,
        required: true,
    },
});

defineEmits(['view-detail', 'switch-to-master']);

const parseProcNum = (kode) => {
    const raw = String(kode || '').replace(/\D/g, '');
    return parseInt(raw, 10) || 0;
};

const operatingProcesses = computed(() => {
    return props.processes
        .filter((p) => {
            const num = parseProcNum(p.Kode_Proses);
            return num >= 1 && num <= 6;
        })
        .sort((a, b) => parseProcNum(a.Kode_Proses) - parseProcNum(b.Kode_Proses));
});

const supportProcesses = computed(() => {
    return props.processes
        .filter((p) => {
            const num = parseProcNum(p.Kode_Proses);
            return num >= 7;
        })
        .sort((a, b) => parseProcNum(a.Kode_Proses) - parseProcNum(b.Kode_Proses));
});

const totalDokumen = computed(() => {
    return props.processes.reduce((acc, cur) => acc + (cur.Jumlah_Dokumen || 0), 0);
});

const operatingDokumenTotal = computed(() => {
    return operatingProcesses.value.reduce((acc, cur) => acc + (cur.Jumlah_Dokumen || 0), 0);
});

const supportDokumenTotal = computed(() => {
    return supportProcesses.value.reduce((acc, cur) => acc + (cur.Jumlah_Dokumen || 0), 0);
});

const getProcessColor = (kode) => {
    const num = parseProcNum(kode);
    const colors = {
        1: '#2563eb', // Strategy
        2: '#0284c7', // Products
        3: '#0ea5e9', // Sales
        4: '#06b6d4', // Supply Chain
        5: '#0d9488', // Services
        6: '#10b981', // Customer Service
        7: '#8b5cf6', // HR / Human Capital
        8: '#6366f1', // IT
        9: '#f59e0b', // Finance
        10: '#d97706', // Assets
        11: '#dc2626', // Risk & Compliance
        12: '#ec4899', // External
        13: '#7c3aed', // Capabilities
    };
    return colors[num] || '#0284c7';
};

const getProcessDefaultDesc = (kode) => {
    const num = parseProcNum(kode);
    const descs = {
        1: 'Perumusan arah strategis, visi, misi, dan model bisnis perusahaan.',
        2: 'Perancangan, rekayasa, dan manajemen siklus hidup produk atau jasa.',
        3: 'Strategi pemasaran, penjualan, penetapan harga, dan akuisisi pelanggan.',
        4: 'Pengadaan, logistik, pergudangan, dan distribusi rantai pasok fisik.',
        5: 'Pemberian dan penyerahan layanan operasional kepada pelanggan.',
        6: 'Layanan purna jual, penanganan keluhan, dan retensi pelanggan.',
        7: 'Rekrutmen, pengembangan kompetensi, remunerasi, dan manajemen SDM.',
        8: 'Pengelolaan infrastruktur TI, sistem informasi, dan keamanan siber.',
        9: 'Manajemen perbendaharaan, akuntansi, anggaran, dan pelaporan keuangan.',
        10: 'Akuisisi, konstruksi, pemeliharaan, dan manajemen aset fisik & fasilitas.',
        11: 'Manajemen risiko organisasi, kepatuhan hukum, audit, dan resiliensi.',
        12: 'Pengelolaan relasi dengan pemerintah, regulator, investor, dan publik.',
        13: 'Pengembangan transformasi bisnis, manajemen kualitas, dan kapabilitas.',
    };
    return descs[num] || 'Klasifikasi proses bisnis dalam kerangka APQC PCF.';
};
</script>

<style scoped>
.sop-apqc-taxonomy-visualizer {
    width: 100%;
}

/* Hero Banner */
.apqc-hero-banner {
    padding: 1.1rem 1.35rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(124, 58, 237, 0.06) 100%);
    border: 1px solid rgba(37, 99, 235, 0.2);
    box-shadow: 0 2px 10px -2px rgba(37, 99, 235, 0.1);
}

.apqc-hero-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--hcis-radius-lg, 12px);
    display: grid;
    place-items: center;
    font-size: 1.35rem;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-sky-600), var(--hcis-blue-600));
    box-shadow: 0 4px 10px -2px rgba(2, 132, 199, 0.4);
    flex-shrink: 0;
}

.apqc-hero-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0 0 0.15rem 0;
}

.apqc-hero-desc {
    font-size: 0.835rem;
    line-height: 1.45;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.apqc-stat-pill {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.35rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.8);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.apqc-stat-pill__num {
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-brand-700, var(--hcis-sky-700));
    line-height: 1;
}

.apqc-stat-pill__lbl {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin-top: 0.2rem;
}

/* Domain Cards */
.apqc-domain-card {
    padding: 1.25rem 1.35rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 4px 14px -3px rgba(0, 0, 0, 0.05);
}

.apqc-domain-card.is-operating {
    border-top: 4px solid var(--hcis-sky-600);
}

.apqc-domain-card.is-support {
    border-top: 4px solid var(--hcis-violet-600);
}

.apqc-domain-icon {
    width: 38px;
    height: 38px;
    border-radius: var(--hcis-radius-md, 8px);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    color: #fff;
    flex-shrink: 0;
}

.operating-icon {
    background: linear-gradient(135deg, var(--hcis-sky-600), var(--hcis-blue-600));
    box-shadow: 0 2px 6px -1px rgba(2, 132, 199, 0.35);
}

.support-icon {
    background: linear-gradient(135deg, var(--hcis-violet-600), var(--hcis-indigo-500));
    box-shadow: 0 2px 6px -1px rgba(124, 58, 237, 0.35);
}

.apqc-domain-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0 0 0.15rem 0;
}

.apqc-domain-subtitle {
    font-size: 0.8125rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    line-height: 1.4;
}

/* Process Grid */
.apqc-process-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.85rem;
}

@media (max-width: 1200px) {
    .apqc-process-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .apqc-process-grid {
        grid-template-columns: 1fr;
    }
}

/* Interactive Node Card */
.apqc-interactive-node {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.95rem 1.05rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, color-mix(in srgb, var(--node-color, var(--hcis-sky-600)) 5%, #fff) 100%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-left: 3.5px solid var(--node-color, var(--hcis-sky-600));
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    cursor: pointer;
    transition: all 0.18s ease;
}

.apqc-interactive-node:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--node-color, var(--hcis-sky-600)) 40%, var(--hcis-slate-200));
    box-shadow: 0 8px 18px -4px color-mix(in srgb, var(--node-color, var(--hcis-sky-600)) 18%, rgba(0, 0, 0, 0.06));
}

.node-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.45rem;
}

.node-code {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.725rem;
    font-weight: 800;
    color: var(--node-color, var(--hcis-sky-600));
    background: color-mix(in srgb, var(--node-color, var(--hcis-sky-600)) 10%, #fff);
    border: 1px solid color-mix(in srgb, var(--node-color, var(--hcis-sky-600)) 25%, transparent);
    border-radius: 4px;
    padding: 0.08rem 0.4rem;
}

.node-count {
    font-size: 0.725rem;
    font-weight: 600;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.08rem 0.45rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.node-count.has-docs {
    color: var(--hcis-brand-700, var(--hcis-sky-700));
    background: var(--hcis-sky-100);
    border: 1px solid var(--hcis-sky-200);
}

.node-count.no-docs {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
}

.node-title {
    font-size: 0.885rem;
    font-weight: 750;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0 0 0.35rem 0;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}

.node-desc {
    font-size: 0.785rem;
    line-height: 1.45;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    margin: 0 0 0.65rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
    min-height: 2.25em;
}

.node-desc.is-empty {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-style: italic;
}

.node-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.55rem;
    border-top: 1px dashed var(--hcis-slate-200);
}

.node-type-pill {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 4px;
    padding: 0.05rem 0.3rem;
}

.node-type-more {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.node-inspect-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    font-size: 0.725rem;
    font-weight: 700;
    color: var(--node-color, var(--hcis-sky-600));
}
</style>
