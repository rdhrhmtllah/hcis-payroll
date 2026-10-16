<template>
    <article
        class="pk-card"
        :class="{ 'is-busy': busy }"
        :style="{ '--accent': theme.color }"
        @click="!busy && $emit('open', paket)"
    >
        <!-- ══ COVER SECTION ══ -->
        <div class="pk-cover" :class="{ 'is-fallback': !hasCover }" :style="!hasCover ? fallbackStyle : null">
            <img
                v-if="hasCover"
                :src="paket.Cover_Url"
                :style="{ objectPosition: paket.Cover_Position || 'center' }"
                alt=""
                loading="lazy"
                @error="imgFailed = true"
            />
            <i v-else class="bi pk-cover-ico" :class="theme.icon"></i>

            <!-- Status & Versi Floating Badges -->
            <div class="pk-cover-badges-top">
                <span class="pk-status" :class="statusVariant">
                    <span class="pk-dot"></span>
                    {{ statusLabel }}
                </span>

                <span v-if="paket.Versi_Paket_Aktif" class="pk-badge-version">
                    <i class="bi bi-layers-fill"></i> v{{ paket.Versi_Paket_Aktif }}
                </span>
            </div>

            <div v-if="paket.Has_Draft_Revisi" class="pk-cover-badge-revisi" title="Paket ini memiliki draft revisi yang sedang disunting">
                <i class="bi bi-pencil-fill me-1"></i> Draft Revisi Aktif
            </div>
        </div>

        <!-- ══ CARD BODY ══ -->
        <div class="pk-body">
            <!-- Kode & Identitas Singkat -->
            <div v-if="paket.Kode_Paket" class="pk-code-row">
                <span class="pk-code-badge">{{ paket.Kode_Paket }}</span>
                <span v-if="paket.Mode_Distribusi === 'KHUSUS'" class="pk-distribusi-badge khusus" title="Akses Terbatas / Khusus">
                    <i class="bi bi-lock-fill"></i> Khusus
                </span>
                <span v-else class="pk-distribusi-badge publik" title="Distribusi Terbuka / Publik">
                    <i class="bi bi-globe2"></i> Publik
                </span>
            </div>

            <!-- Judul Paket -->
            <h3 class="pk-title" :title="paket.Nama_Paket">
                {{ paket.Nama_Paket }}
            </h3>

            <!-- Attribute Chips (Prioritas, Absensi, Durasi) -->
            <div class="pk-chips">
                <StatusPill :text="prio.label" :warna="prio.warna" />

                <span v-if="paket.Flag_Absensi === 'Y'" class="pk-chip amber" title="Karyawan diwajibkan absensi foto saat membaca">
                    <i class="bi bi-camera-fill"></i> Absensi Wajib
                </span>

                <span v-if="paket.Estimasi_Waktu_Baca_Menit" class="pk-chip" title="Estimasi waktu penyelesaian">
                    <i class="bi bi-clock"></i> ~{{ paket.Estimasi_Waktu_Baca_Menit }} mnt
                </span>
            </div>

            <!-- ══ METRICS STRIP ══ -->
            <div class="pk-metrics-strip">
                <div class="pk-metric-item" title="Jumlah Section dalam paket">
                    <i class="bi bi-collection"></i>
                    <span><strong>{{ paket.Total_Section || 0 }}</strong> Section</span>
                </div>

                <div class="pk-metric-item" :title="`Total ${paket.Total_Item || 0} item materi (${paket.Total_Wajib || 0} wajib)`">
                    <i class="bi bi-list-check"></i>
                    <span><strong>{{ paket.Total_Item || 0 }}</strong> Item</span>
                    <span v-if="paket.Total_Wajib" class="pk-metric-wajib">({{ paket.Total_Wajib }} wjb)</span>
                </div>

                <div v-if="paket.Total_Dokumen" class="pk-metric-item dok" title="Jumlah master dokumen terkendali yang ditautkan">
                    <i class="bi bi-journal-bookmark-fill text-primary"></i>
                    <span><strong>{{ paket.Total_Dokumen }}</strong> Master Dok</span>
                </div>
            </div>

            <!-- Content Material Types Badges -->
            <div v-if="paket.Tipe_Items && paket.Tipe_Items.length" class="pk-material-types">
                <span
                    v-for="t in paket.Tipe_Items"
                    :key="t"
                    class="pk-type-dot"
                    :class="t.toLowerCase()"
                    :title="`Memuat konten tipe: ${t}`"
                >
                    <i class="bi" :class="tipeIcon(t)"></i>
                    <span>{{ t }}</span>
                </span>
            </div>
        </div>

        <!-- ══ ACTIONS FOOTER ══ -->
        <div class="pk-foot" @click.stop>
            <button
                type="button"
                class="stitch-btn ghost sm flex-grow-1 pk-action-main"
                :disabled="busy"
                @click="$emit('open', paket)"
            >
                <i class="bi bi-sliders2 me-1"></i> Kelola Struktur & Materi
            </button>

            <button
                type="button"
                class="stitch-icon-btn"
                title="Duplikat paket dokumen"
                :disabled="busy"
                @click="$emit('duplicate', paket)"
            >
                <span v-if="busy" class="spinner-border spinner-border-sm"></span>
                <i v-else class="bi bi-files"></i>
            </button>

            <button
                type="button"
                class="stitch-icon-btn danger"
                title="Hapus paket dokumen"
                :disabled="busy"
                @click="$emit('delete', paket)"
            >
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </article>
</template>

<script>
import StatusPill from './StatusPill.vue';
import { statusPaketLabel, temaAksen } from '../../support/constants.js';
import { metaPrioritas } from '../../support/prioritas.js';

/**
 * SopPaketCard — Kartu Paket Dokumen Modern & Kaya Informasi untuk Builder LMS SOP.
 */
export default {
    name: 'SopPaketCard',
    components: { StatusPill },
    props: {
        paket: { type: Object, required: true },
        busy: { type: Boolean, default: false },
    },
    emits: ['open', 'duplicate', 'delete'],
    data() {
        return { imgFailed: false };
    },
    computed: {
        theme() {
            return temaAksen(this.paket.Nama_Paket);
        },
        prio() {
            return metaPrioritas(this.paket.Prioritas);
        },
        statusLabel() {
            return statusPaketLabel(this.paket.Status);
        },
        statusVariant() {
            return { DRAFT: 'idle', PUBLISHED: 'ok', ARCHIVED: 'bad', OBSOLETE: 'idle' }[this.paket.Status] || 'idle';
        },
        hasCover() {
            return !!this.paket.Cover_Url && !this.imgFailed;
        },
        fallbackStyle() {
            return { background: `linear-gradient(135deg, ${this.theme.color}, ${this.theme.color}cc)` };
        },
    },
    watch: {
        'paket.Cover_Url'() {
            this.imgFailed = false;
        },
    },
    methods: {
        tipeIcon(t) {
            return (
                {
                    DOKUMEN: 'bi-file-earmark-pdf-fill',
                    FORM: 'bi-ui-checks-grid',
                    VIDEO: 'bi-play-circle-fill',
                    KUIS: 'bi-patch-question-fill',
                    TAUTAN: 'bi-link-45deg',
                    PARAGRAF: 'bi-text-paragraph',
                }[t] || 'bi-file-earmark'
            );
        },
    },
};
</script>

<style scoped>
.pk-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--hcis-white);
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    transition:
        transform 0.18s cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 0.18s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.18s ease;
    position: relative;
}

.pk-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 36px -16px rgba(15, 23, 42, 0.25);
    border-color: color-mix(in srgb, var(--accent) 45%, var(--hcis-slate-300));
}

.pk-card.is-busy {
    opacity: 0.65;
    cursor: progress;
}

.pk-card.is-busy:hover {
    transform: none;
}

/* ── COVER ──────────────────────────────────────────────────────── */
.pk-cover {
    position: relative;
    height: 140px;
    overflow: hidden;
    display: grid;
    place-items: center;
    background: var(--hcis-slate-900);
}

.pk-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

.pk-card:hover .pk-cover img {
    transform: scale(1.03);
}

.pk-cover.is-fallback::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.22) 1px, transparent 1px);
    background-size: 13px 13px;
}

.pk-cover-ico {
    position: relative;
    color: rgba(255, 255, 255, 0.95);
    font-size: 2.3rem;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.pk-cover-badges-top {
    position: absolute;
    top: 0.6rem;
    left: 0.6rem;
    right: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    z-index: 2;
}

.pk-status {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(6px);
    color: var(--hcis-slate-600);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.pk-status .pk-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.pk-status.ok {
    color: var(--hcis-emerald-700);
    background: rgba(236, 253, 245, 0.95);
}

.pk-status.bad {
    color: var(--hcis-red-700);
    background: rgba(254, 242, 242, 0.95);
}

.pk-status.idle {
    color: var(--hcis-indigo-600);
    background: rgba(238, 242, 255, 0.95);
}

.pk-badge-version {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(6px);
    color: var(--hcis-white);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.pk-cover-badge-revisi {
    position: absolute;
    bottom: 0.55rem;
    left: 0.6rem;
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    background: rgba(245, 158, 11, 0.95);
    backdrop-filter: blur(4px);
    color: var(--hcis-amber-900);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    z-index: 2;
}

/* ── BODY ────────────────────────────────────────────────────────── */
.pk-body {
    padding: 0.85rem 0.95rem 0.75rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.pk-code-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.35rem;
}

.pk-code-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}

.pk-distribusi-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
}

.pk-distribusi-badge.publik {
    color: var(--hcis-emerald-600);
    background: var(--hcis-emerald-50);
}

.pk-distribusi-badge.khusus {
    color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
}

.pk-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    margin: 0;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pk-chips {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.55rem;
}

.pk-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border-radius: 999px;
    padding: 0.16rem 0.5rem;
}

.pk-chip.amber {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.12);
}

/* ── METRICS STRIP ──────────────────────────────────────────────── */
.pk-metrics-strip {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-top: 0.65rem;
    padding-top: 0.55rem;
    border-top: 1px dashed var(--hcis-slate-200);
    flex-wrap: wrap;
}

.pk-metric-item {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}

.pk-metric-item i {
    font-size: 0.8rem;
    color: var(--hcis-slate-400);
}

.pk-metric-item.dok i {
    color: var(--hcis-indigo-600);
}

.pk-metric-wajib {
    font-size: 0.68rem;
    color: var(--hcis-rose-600);
    font-weight: 600;
    margin-left: 0.15rem;
}

/* ── MATERIAL TYPES ─────────────────────────────────────────────── */
.pk-material-types {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

.pk-type-dot {
    display: inline-flex;
    align-items: center;
    gap: 0.22rem;
    font-size: 0.64rem;
    font-weight: 600;
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}

.pk-type-dot.dokumen {
    color: var(--hcis-red-700);
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
}

.pk-type-dot.kuis {
    color: var(--hcis-violet-600);
    border-color: #ddd6fe;
    background: var(--hcis-violet-50);
}

.pk-type-dot.video {
    color: var(--hcis-blue-600);
    border-color: #bfdbfe;
    background: #eff6ff;
}

.pk-type-dot.form {
    color: var(--hcis-emerald-600);
    border-color: var(--hcis-emerald-200);
    background: var(--hcis-emerald-50);
}

/* ── FOOTER ──────────────────────────────────────────────────────── */
.pk-foot {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.6rem 0.95rem;
    border-top: 1px solid var(--hcis-slate-100);
    background: #fafcff;
}

.pk-action-main:hover {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}

@media (prefers-reduced-motion: reduce) {
    .pk-card {
        transition: none;
    }
    .pk-card:hover {
        transform: none;
    }
}
</style>
