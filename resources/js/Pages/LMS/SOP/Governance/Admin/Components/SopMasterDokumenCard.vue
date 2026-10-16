<template>
    <div
        class="sop-master-item-row"
        :class="{
            'is-selected': isSelected,
            'has-alert': !item.Punya_Versi_Berlaku || item.Tanpa_Pengesah || !!item.Tinjau_Keadaan,
        }"
        :style="{
            '--item-accent': typeMeta.color,
            '--item-bg': typeMeta.bg,
            '--item-border': typeMeta.border,
        }"
        tabindex="0"
        role="button"
        :aria-selected="isSelected"
        :aria-label="`Pilih Dokumen: ${item.Judul}`"
        @click="$emit('select', item)"
        @keydown.enter.prevent="$emit('select', item)"
    >
        <!-- Left Active Accent Bar -->
        <div class="item-accent-bar" aria-hidden="true"></div>

        <!-- Left Jewel: Jenis Dokumen Code / Hierarchy Level -->
        <div class="item-jewel" :title="`Jenis: ${item.Jenis_Nama || item.Tipe || 'Dokumen'} (Level ${item.Jenis_Level || '-'})`">
            <span v-if="item.Jenis_Kode" class="item-jewel__code text-truncate">{{ item.Jenis_Kode }}</span>
            <span v-else-if="item.Jenis_Level" class="item-jewel__lvl text-truncate">L{{ item.Jenis_Level }}</span>
            <i v-else class="bi" :class="typeMeta.icon"></i>
        </div>

        <!-- Main Card Content -->
        <div class="item-content min-w-0">
            <!-- Line 1: Title & Type Pill -->
            <div class="item-header-row">
                <span class="item-title" :title="item.Judul">
                    {{ item.Judul }}
                </span>
                <span class="item-type-pill" :class="`type-${String(item.Tipe || '').toLowerCase()}`">
                    {{ item.Tipe }}
                </span>
            </div>

            <!-- Line 2: Monospace Document Number (Full / Official) -->
            <div class="item-number-row">
                <span
                    v-if="item.Nomor_Lengkap || item.Nomor_Pratinjau || item.Nomor_SOP_Resmi"
                    class="item-number-text font-monospace"
                    :title="`Nomor Dokumen: ${item.Nomor_Lengkap || item.Nomor_SOP_Resmi || item.Nomor_Pratinjau}`"
                >
                    <i class="bi bi-hash flex-shrink-0"></i>
                    <span class="text-truncate">{{ item.Nomor_Lengkap || item.Nomor_SOP_Resmi || item.Nomor_Pratinjau }}</span>
                </span>
                <span v-else class="item-number-empty font-monospace">
                    <i class="bi bi-dash flex-shrink-0"></i>
                    <span>Belum Bernomor</span>
                </span>
            </div>

            <!-- Line 3: Multi-Faceted Identity Badges (Dept, APQC, Entitas) -->
            <div class="item-identity-row">
                <span v-if="item.Departemen_Kode" class="identity-badge dept-badge" :title="`Departemen: ${item.Departemen_Nama || item.Departemen_Kode}`">
                    <i class="bi bi-diagram-3-fill flex-shrink-0 me-1"></i>
                    <span class="text-truncate">{{ item.Departemen_Kode }}</span>
                </span>
                <span v-else-if="item.Pemilik_Label" class="identity-badge dept-badge" :title="`Pemilik: ${item.Pemilik_Label}`">
                    <i class="bi bi-diagram-3-fill flex-shrink-0 me-1"></i>
                    <span class="text-truncate">{{ item.Pemilik_Label }}</span>
                </span>

                <span v-if="item.APQC_Kode" class="identity-badge apqc-badge" :title="`APQC: ${item.APQC_Kode} - ${item.APQC_Nama || ''}`">
                    <i class="bi bi-bounding-box flex-shrink-0 me-1"></i>
                    <span class="text-truncate">{{ item.APQC_Kode }}</span>
                </span>

                <span v-if="item.Entitas_Pemilik_Singkat || item.Entitas_Pemilik_Kode" class="identity-badge entitas-badge" :title="`Entitas: ${item.Entitas_Pemilik_Nama || item.Entitas_Pemilik_Kode}`">
                    <i class="bi bi-building flex-shrink-0 me-1"></i>
                    <span class="text-truncate">{{ item.Entitas_Pemilik_Singkat || item.Entitas_Pemilik_Kode }}</span>
                </span>
            </div>

            <!-- Line 4: Lifecycle Stage Status Dot & Usage Stats -->
            <div class="item-footer-row">
                <!-- Status Dot & Label -->
                <div class="item-footer-left min-w-0">
                    <span class="stage-dot" :class="stageMeta.dotClass"></span>
                    <span class="stage-label text-truncate" :class="stageMeta.textClass" :title="stageMeta.label">
                        {{ stageMeta.label }}
                    </span>

                    <span
                        v-if="item.Tanpa_Pengesah"
                        class="warning-badge"
                        title="Versi berlaku belum disahkan oleh Admin"
                    >
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <span class="text-truncate">Tanpa Pengesah</span>
                    </span>

                    <!-- TANGGAL YANG MENAGIH -->
                    <span
                        v-if="item.Tinjau_Keadaan"
                        class="warning-badge"
                        :class="{ 'is-soon': item.Tinjau_Keadaan === 'SEGERA' }"
                        :title="tinjauJudul"
                    >
                        <i class="bi bi-alarm-fill flex-shrink-0"></i>
                        <span class="text-truncate">{{ tinjauLabel }}</span>
                    </span>
                </div>

                <!-- Footprint Stats (Versi & Paket) -->
                <div class="item-footprint ms-auto">
                    <span v-if="item.Versi_Berlaku_Ke" class="footprint-pill version-pill" title="Versi yang sedang berlaku">
                        v{{ item.Versi_Berlaku_Ke }}
                    </span>
                    <span
                        class="footprint-pill package-pill"
                        :class="{ 'is-zero': !item.Jumlah_Paket_Pemakai }"
                        :title="`${item.Jumlah_Paket_Pemakai || 0} Paket Dokumen mengadopsi dokumen ini`"
                    >
                        <i class="bi bi-box-seam me-1 flex-shrink-0"></i>{{ item.Jumlah_Paket_Pemakai || 0 }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

defineOptions({ name: 'SopMasterDokumenCard' });

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    isSelected: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['select']);

/* Lencana tanggal: SEBABNYA disebut, bukan sekadar "perlu perhatian".
   "Review lewat" dan "Kadaluarsa 12 hari lagi" menuntut tindakan yang berbeda,
   dan lencana yang menyamarkan keduanya memaksa admin membuka dokumennya
   hanya untuk tahu ia sedang melihat yang mana. */
const TINJAU_SEBAB = { REVIEW: 'Review', KADALUARSA: 'Kedaluwarsa' };

const tinjauLabel = computed(() => {
    const sebab = TINJAU_SEBAB[props.item?.Tinjau_Sebab] || 'Tinjau';
    return props.item?.Tinjau_Keadaan === 'TERLAMBAT' ? `${sebab} lewat` : `${sebab} dekat`;
});

const tinjauJudul = computed(() => {
    const tgl = props.item?.Tinjau_Tanggal;
    if (!tgl) return 'Tanggal tinjauan menagih';
    const [y, m, d] = String(tgl).split('-');
    const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const rapi = `${Number(d)} ${bulan[Number(m) - 1]} ${y}`;
    const sebab = TINJAU_SEBAB[props.item?.Tinjau_Sebab] || 'Tinjauan';
    return props.item?.Tinjau_Keadaan === 'TERLAMBAT'
        ? `${sebab} jatuh tempo ${rapi} dan sudah lewat`
        : `${sebab} jatuh tempo ${rapi}`;
});

const typeMeta = computed(() => {
    const type = String(props.item?.Tipe || '').toUpperCase();
    switch (type) {
        case 'DOKUMEN':
            return {
                icon: 'bi-file-earmark-text-fill',
                color: '#2563eb',
                bg: '#eff6ff',
                border: '#bfdbfe',
            };
        case 'FORM':
            return {
                icon: 'bi-ui-checks-grid',
                color: '#0d9488',
                bg: '#f0fdfa',
                border: '#99f6e4',
            };
        case 'VIDEO':
            return {
                icon: 'bi-play-circle-fill',
                color: '#d97706',
                bg: '#fffbeb',
                border: '#fde68a',
            };
        case 'KUIS':
            return {
                icon: 'bi-patch-question-fill',
                color: '#7c3aed',
                bg: '#f5f3ff',
                border: '#ddd6fe',
            };
        case 'TAUTAN':
            return {
                icon: 'bi-link-45deg',
                color: '#0284c7',
                bg: '#f0f9ff',
                border: '#bae6fd',
            };
        default:
            return {
                icon: 'bi-journal-text',
                color: '#475569',
                bg: '#f8fafc',
                border: '#e2e8f0',
            };
    }
});

const stageMeta = computed(() => {
    const tahap = props.item?.Tahap;
    switch (tahap) {
        case 'BARU':
            return {
                label: 'Baru (Tanpa Berkas)',
                dotClass: 'dot-neutral',
                textClass: 'text-neutral',
            };
        case 'DRAFT':
            return {
                label: 'Draft (Penyusunan)',
                dotClass: 'dot-warning',
                textClass: 'text-warning',
            };
        case 'SIAP':
            return {
                label: 'Siap Terbit',
                dotClass: 'dot-brand',
                textClass: 'text-brand',
            };
        case 'DIPAKAI':
            return {
                label: 'Aktif Dipakai',
                dotClass: 'dot-success',
                textClass: 'text-success',
            };
        /* Bukan tahap kelima melainkan keluar dari daur hidupnya. Warnanya
           netral, bukan merah: dokumen yang dipensiunkan adalah keputusan yang
           sudah selesai, bukan masalah yang menunggu ditangani. */
        case 'NONAKTIF':
            return {
                label: 'Dinonaktifkan',
                dotClass: 'dot-neutral',
                textClass: 'text-neutral',
            };
        default:
            return {
                label: tahap || 'Aktif',
                dotClass: 'dot-neutral',
                textClass: 'text-neutral',
            };
    }
});
</script>

<style scoped>
.sop-master-item-row {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem 0.65rem 0.8rem;
    margin-bottom: 0.45rem;
    background: var(--hcis-white, #ffffff);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 10px);
    cursor: pointer;
    transition:
        background-color 0.18s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.18s cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 0.18s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    outline: none;
    user-select: none;
    overflow: hidden;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.sop-master-item-row:focus-visible {
    border-color: var(--hcis-brand-500, #3b82f6);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

.sop-master-item-row:hover {
    background: #ffffff;
    border-color: rgba(148, 163, 184, 0.85);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.08);
    transform: translateX(3px);
}

.sop-master-item-row.is-selected {
    background: linear-gradient(90deg, rgba(238, 242, 255, 0.98) 0%, rgba(248, 250, 252, 0.92) 100%);
    border-color: var(--hcis-brand-500, #3b82f6);
    box-shadow: 0 4px 16px -2px rgba(37, 99, 235, 0.18);
}

/* Left Indicator Bar */
.item-accent-bar {
    position: absolute;
    left: 0;
    top: 6px;
    bottom: 6px;
    width: 3.5px;
    border-radius: 0 4px 4px 0;
    background: transparent;
    transition: background-color 0.18s ease;
}

.sop-master-item-row.is-selected .item-accent-bar {
    background: linear-gradient(180deg, var(--hcis-brand-600, #2563eb) 0%, var(--hcis-brand-500, #3b82f6) 100%);
    box-shadow: 0 0 6px rgba(37, 99, 235, 0.4);
}

/* Jewel Icon / Code */
.item-jewel {
    min-width: 34px;
    max-width: 48px;
    height: 34px;
    padding: 0 4px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--item-accent);
    background: var(--item-bg);
    border: 1px solid var(--item-border);
    flex-shrink: 0;
    margin-top: 0.1rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transition: transform 0.15s ease;
    overflow: hidden;
}

.sop-master-item-row:hover .item-jewel {
    transform: scale(1.04);
}

.item-jewel__code {
    font-family: var(--hcis-font-mono, monospace);
    line-height: 1;
    letter-spacing: -0.02em;
    max-width: 100%;
}

.item-jewel__lvl {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.75rem;
    line-height: 1;
    max-width: 100%;
}

/* Content */
.item-content {
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
    min-width: 0;
    flex: 1 1 auto;
    width: 100%;
    overflow: hidden;
}

/* Line 1: Header Row */
.item-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.4rem;
    min-width: 0;
    width: 100%;
}

.item-title {
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.35;
    margin: 0;
    flex: 1 1 auto;
    min-width: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
    transition: color 0.15s ease;
}

.sop-master-item-row.is-selected .item-title {
    color: var(--hcis-brand-800, #1e40af);
}

.item-type-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.08rem 0.42rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.625rem;
    font-weight: 750;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    flex-shrink: 0;
    white-space: nowrap;
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-600, #475569);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.item-type-pill.type-dokumen {
    color: #1d4ed8;
    background: #eff6ff;
    border-color: #dbeafe;
}

.item-type-pill.type-form {
    color: #0f766e;
    background: #f0fdfa;
    border-color: #ccfbf1;
}

.item-type-pill.type-video {
    color: #b45309;
    background: #fffbeb;
    border-color: #fef3c7;
}

.item-type-pill.type-kuis {
    color: #7c3aed;
    background: #faf5ff;
    border-color: #f3e8ff;
}

.item-type-pill.type-tautan {
    color: #0284c7;
    background: #f0f9ff;
    border-color: #e0f2fe;
}

/* Line 2: Monospace Number */
.item-number-row {
    display: flex;
    align-items: center;
    min-width: 0;
    width: 100%;
}

.item-number-text {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.725rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: 4px;
    padding: 0.06rem 0.38rem;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
    letter-spacing: 0.01em;
}

.item-number-text .text-truncate {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.item-number-empty {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
    font-style: italic;
    color: var(--hcis-slate-400, #94a3b8);
    background: rgba(248, 250, 252, 0.85);
    border: 1px dashed rgba(203, 213, 225, 0.75);
    border-radius: 4px;
    padding: 0.04rem 0.35rem;
    max-width: 100%;
    min-width: 0;
}

/* Line 3: Identity Row */
.item-identity-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.3rem;
    min-width: 0;
    width: 100%;
}

.identity-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.675rem;
    font-weight: 650;
    border-radius: 4px;
    padding: 0.05rem 0.35rem;
    line-height: 1.25;
    max-width: 140px;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
}

.identity-badge .text-truncate {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dept-badge {
    color: #0284c7;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
}

.apqc-badge {
    color: var(--hcis-violet-700, #6d28d9);
    background: #faf5ff;
    border: 1px solid #ddd6fe;
}

.entitas-badge {
    color: var(--hcis-slate-600, #475569);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

/* Line 4: Footer Row */
.item-footer-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    padding-top: 0.28rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.85);
    margin-top: 0.15rem;
    min-width: 0;
    width: 100%;
}

.item-footer-left {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    flex: 1 1 auto;
    overflow: hidden;
}

/* Stage Dot & Label */
.stage-dot {
    width: 6.5px;
    height: 6.5px;
    border-radius: 50%;
    flex-shrink: 0;
}

.dot-neutral {
    background: var(--hcis-slate-400, #94a3b8);
}

.dot-warning {
    background: var(--hcis-amber-500, #f59e0b);
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
}

.dot-brand {
    background: var(--hcis-blue-500, #3b82f6);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.dot-success {
    background: var(--hcis-emerald-500, #10b981);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}

.stage-label {
    font-size: 0.6875rem;
    font-weight: 650;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 95px;
    flex-shrink: 0;
}

.text-neutral { color: var(--hcis-slate-500, #64748b); }
.text-warning { color: var(--hcis-amber-700, #b45309); }
.text-brand { color: #1d4ed8; }
.text-success { color: var(--hcis-emerald-700, #047857); }

.warning-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-red-600, #dc2626);
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.03rem 0.38rem;
    max-width: 105px;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    flex-shrink: 1;
}

.warning-badge.is-soon {
    color: var(--hcis-amber-700, #b45309);
    background: #fffbeb;
    border-color: #fde68a;
}

/* Footprint */
.item-footprint {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
    margin-left: auto;
}

.footprint-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 4px;
    padding: 0.05rem 0.32rem;
    white-space: nowrap;
    flex-shrink: 0;
}

.version-pill {
    color: var(--hcis-indigo-700, #4338ca);
    background: #eef2ff;
    border: 1px solid #c7d2fe;
}

.package-pill {
    color: var(--hcis-slate-700, #334155);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-300, #cbd5e1);
}

.package-pill.is-zero {
    color: var(--hcis-slate-400, #94a3b8);
    background: var(--hcis-slate-50, #f8fafc);
    border-color: var(--hcis-slate-200, #e2e8f0);
}
</style>
