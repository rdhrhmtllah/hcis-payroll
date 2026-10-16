<template>
    <div class="flow-item" :class="{ 'is-editing': editing }">
        <!-- Spine Node Indicator -->
        <div class="flow-node" :class="{ current: editing }">
            <i class="bi" :class="editing ? 'bi-record-circle-fill' : fileIcon"></i>
        </div>

        <!-- Main Item Card with Type Border Accent -->
        <article
            class="ritem"
            :class="[
                'type-accent-' + item.Tipe_Item,
                { highlight: item.highlight, editing: editing, current: editing },
            ]"
        >
            <!-- Top Ribbon Indicators -->
            <span v-if="item.highlight" class="ritem-ribbon"> <i class="bi bi-star-fill me-1"></i> Penting </span>
            <span v-if="editing" class="ritem-now"> <i class="bi bi-pencil-fill me-1"></i> Sedang Diedit </span>

            <div class="ri-head flex-column gap-2">
                <div class="d-flex align-items-center justify-content-between w-100 gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0 flex-wrap">
                        <ItemTypeIcon :tipe="item.Tipe_Item" class="flex-shrink-0" showLabel />
                        <div class="ri-meta d-flex flex-wrap align-items-center gap-2 min-w-0">
                            <span v-if="item.wajib" class="ri-wajib"><i class="bi bi-asterisk"></i> Wajib</span>
                            <span
                                v-if="item.Versi !== null && item.Versi !== undefined"
                                class="ri-ver-tag berlaku"
                                :title="'Versi ' + $vnum(item.Versi)"
                            >
                                <i class="bi bi-layers-fill me-1"></i>v{{ $vnum(item.Versi) }}
                            </span>
                            <span v-if="item.Nomor_SOP_Resmi" class="ri-nomor small"
                                >· No. {{ item.Nomor_SOP_Resmi }}</span
                            >
                        </div>
                    </div>
                    <StatusPill text="Belum Dibuka" variant="idle" :dot="false" class="flex-shrink-0 ms-auto" />
                </div>
                <div class="ri-title">{{ item.Judul_Item || 'Judul item' }}</div>
            </div>

            <p v-if="item.Deskripsi_Item" class="ri-desc mt-2 mb-0">{{ item.Deskripsi_Item }}</p>

            <!-- PARAGRAF -->
            <div v-if="item.Tipe_Item === 'PARAGRAF'" class="mt-3">
                <div class="ri-prose shadow-sm" v-html="paragrafHtml"></div>
                <div class="ri-actions mt-2.5 d-flex align-items-center flex-wrap gap-2">
                    <button class="stitch-btn primary sm" disabled>
                        <i class="bi bi-check2-circle me-1"></i> Tandai Selesai &amp; Pahami
                    </button>
                </div>
            </div>

            <!-- TAUTAN -->
            <div v-else-if="item.Tipe_Item === 'TAUTAN'" class="ri-link-card mt-3">
                <div class="ri-link-card-inner">
                    <div class="ri-link-main flex-grow-1 min-w-0">
                        <div class="ri-link-ico">
                            <i class="bi bi-globe2"></i>
                        </div>
                        <div class="ri-link-meta min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="ri-link-domain">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> TAUTAN RESMI
                                </span>
                            </div>
                            <div
                                class="ri-link-label text-truncate mt-1"
                                :title="item.Label_Tautan || 'Tautan Eksternal'"
                            >
                                {{ item.Label_Tautan || 'Tautan Eksternal Dokumen' }}
                            </div>
                            <div
                                v-if="item.URL_Tautan"
                                class="ri-link-url text-truncate mt-0.5"
                                :title="item.URL_Tautan"
                            >
                                {{ item.URL_Tautan }}
                            </div>
                        </div>
                    </div>
                    <div class="ri-actions flex-shrink-0 d-flex align-items-center gap-2 mt-2 mt-md-0">
                        <a class="stitch-btn primary sm" :href="item.URL_Tautan || '#'" target="_blank" rel="noopener">
                            <i class="bi bi-box-arrow-up-right me-1"></i>
                            {{ item.Label_Tautan ? 'Kunjungi Tautan' : 'Buka Tautan' }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- KUIS -->
            <div v-else-if="item.Tipe_Item === 'KUIS'" class="ri-quiz-card mt-3">
                <div class="ri-quiz-card-inner">
                    <div class="ri-quiz-main flex-grow-1 min-w-0">
                        <div class="ri-quiz-ico">
                            <i class="bi bi-patch-question-fill"></i>
                        </div>
                        <div class="ri-quiz-meta min-w-0 flex-grow-1">
                            <span class="ri-quiz-badge-type"
                                >KUIS EVALUASI{{ item.Jenis_Quiz ? ' · ' + item.Jenis_Quiz : '' }}</span
                            >
                            <div class="ri-quiz-label text-truncate mt-1 fw-bold">
                                {{ item.Judul_Item || 'Kuis Dokumen' }}
                            </div>
                            <div class="ri-quiz-sub text-truncate mt-0.5 text-muted small">
                                Nilai Minimum Lulus:
                                <b>{{
                                    item.Nilai_Minimum != null && item.Nilai_Minimum !== '' ? item.Nilai_Minimum : '70'
                                }}</b>
                            </div>
                        </div>
                    </div>
                    <div class="ri-actions flex-shrink-0 d-flex align-items-center gap-2 mt-2 mt-md-0">
                        <button class="ri-btn-quiz-cta" disabled>
                            <i class="bi bi-play-fill me-1"></i> Mulai Kuis
                        </button>
                    </div>
                </div>
            </div>

            <!-- BERKAS / DOKUMEN / FORM / VIDEO / GAMBAR / PDF -->
            <div v-else class="ri-doc-card mt-3">
                <div class="ri-doc-card-inner">
                    <div class="ri-doc-main flex-grow-1 min-w-0">
                        <div class="ri-doc-ico">
                            <i class="bi" :class="fileIcon"></i>
                        </div>
                        <div class="ri-doc-meta min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <span class="ri-prog-badge" :class="canDownload ? 'ok' : 'idle'">
                                    <i class="bi" :class="canDownload ? 'bi-download' : 'bi-eye'"></i>
                                    {{ canDownload ? 'Boleh Unduh' : 'Hanya Lihat' }}
                                </span>
                                <span v-if="item.Nomor_SOP_Resmi" class="text-muted small"
                                    >· No. {{ item.Nomor_SOP_Resmi }}</span
                                >
                            </div>
                            <div class="ri-doc-name text-truncate fw-bold" :title="berkasName">{{ berkasName }}</div>
                            <div class="text-muted small text-truncate">Dokumen Versi Aktif</div>
                        </div>
                    </div>
                    <div class="ri-actions flex-shrink-0 d-flex align-items-center gap-2 mt-2 mt-md-0">
                        <button class="ri-btn-doc-cta" disabled>
                            <i class="bi bi-book-half me-1"></i> Buka Reader
                        </button>
                    </div>
                </div>
            </div>

            <!-- Riwayat versi (preview builder, on-demand) -->
            <div v-if="isFileWithId" class="pv-versi mt-3 pt-2 border-top">
                <button class="linklike" @click="toggleVersi">
                    <i class="bi" :class="showVersi ? 'bi-chevron-up' : 'bi-clock-history'"></i> Riwayat Versi Dokumen
                </button>
                <transition name="sop-swap">
                    <div v-if="showVersi" class="pv-versi-body mt-2">
                        <SopVersiTimeline :versions="versiList" :loading="versiLoading" :show-baca="false" />
                    </div>
                </transition>
            </div>
        </article>
    </div>
</template>

<script>
import axios from 'axios';
import ItemTypeIcon from './ItemTypeIcon.vue';
import StatusPill from './StatusPill.vue';
import SopVersiTimeline from './SopVersiTimeline.vue';
import { isFileTipe } from '../../support/constants.js';
import { sanitasiSop, sanitasiSopAtau } from '@/utils/sopHtml';

/** SopPreviewItem — Rapi & Presisi 100% matched dengan SopDetail.vue */
export default {
    name: 'SopPreviewItem',
    components: { ItemTypeIcon, StatusPill, SopVersiTimeline },
    props: {
        item: { type: Object, required: true },
        n: { type: [Number, String], default: 1 },
        editing: { type: Boolean, default: false },
        berkasInfo: { type: Object, default: null },
    },
    data() {
        return { showVersi: false, versiList: [], versiLoading: false };
    },
    computed: {
        isFileWithId() {
            return isFileTipe(this.item.Tipe_Item) && this.item._id && this.item._id !== '__new__';
        },
        fileIcon() {
            const t = this.item.Tipe_Item;
            if (t === 'DOKUMEN') return 'bi-file-earmark-word-fill';
            if (t === 'FORM') return 'bi-file-earmark-spreadsheet-fill';
            if (t === 'VIDEO') return 'bi-file-earmark-play-fill';
            if (t === 'GAMBAR') return 'bi-file-earmark-image-fill';
            return 'bi-file-earmark-text-fill';
        },
        paragrafHtml() {
            return sanitasiSopAtau(
                this.item.Konten_Teks,
                '<p class="pv-empty text-muted fst-italic">Konten paragraf akan tampil di sini…</p>',
            );
        },
        canDownload() {
            return this.item.Mode_Akses === 'VIEW_UNDUH';
        },
        berkasName() {
            return this.item.Konten_Nama || (this.berkasInfo && this.berkasInfo.Nama_Berkas) || 'Konten versi aktif';
        },
    },
    methods: {
        async toggleVersi() {
            if (this.showVersi) {
                this.showVersi = false;
                return;
            }
            this.showVersi = true;
            this.versiList = [];
            this.versiLoading = true;
            try {
                const r = await axios.get(`/api/v1/sop/admin/berkas/item/${this.item._id}/versi`);
                const rows = r.data.success ? r.data.result : [];
                this.versiList = rows.map((v) => ({
                    id: v.Id_SOP_Item_Versi,
                    versi: v.Versi_Ke,
                    aktif: v.Status === 'PUBLISHED',
                    changelog: v.Ringkasan_Perubahan,
                    tanggal: v.Tanggal_Publish,
                    kontenNama: v.Konten_Nama,
                    kontenTipe: v.Konten_Tipe,
                    canView: false,
                }));
            } catch (e) {
                /* silent */
            } finally {
                this.versiLoading = false;
            }
        },
    },
};
</script>

<style scoped>
.flow-item {
    position: relative;
    padding-left: 48px;
    margin-bottom: 0.85rem;
}
.flow-node {
    position: absolute;
    left: 4px;
    top: 13px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 0.82rem;
    background: #fff;
    border: 2px solid var(--border, #dbe1ea);
    color: var(--hcis-slate-400);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    display: grid;
    place-items: center;
    z-index: 1;
}
.flow-node.current {
    background: var(--hcis-indigo-500) !important;
    border-color: var(--hcis-indigo-500) !important;
    color: var(--hcis-white) !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.45) !important;
}

/* ritem Card styling 100% matched to SopDetail.vue */
.ritem {
    --ri-lift: 0 1px 2px rgba(15, 23, 42, 0.05), 0 10px 26px -16px rgba(15, 23, 42, 0.22);
    --ri-gloss: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    position: relative;
    border: 1px solid var(--ri-line, var(--border, #dbe1ea));
    border-radius: 18px;
    background:
        radial-gradient(125% 115% at 0% 0%, var(--ri-tint, rgba(99, 102, 241, 0.06)), rgba(255, 255, 255, 0) 58%),
        var(--hcis-white);
    padding: 1.05rem 1.2rem;
    box-shadow: var(--ri-lift), var(--ri-gloss);
    transition:
        transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.3s cubic-bezier(0.22, 1, 0.36, 1),
        border-color 0.25s ease;
}
.ritem:hover {
    transform: translateY(-2px);
    border-color: var(--ri-glow, rgba(99, 102, 241, 0.42));
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.05),
        0 20px 40px -20px var(--ri-shadow, rgba(99, 102, 241, 0.35)),
        var(--ri-gloss);
}
.ritem.editing,
.ritem.current {
    --ri-line: rgba(99, 102, 241, 0.45);
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.1),
        0 18px 36px -18px rgba(99, 102, 241, 0.4),
        var(--ri-gloss);
}
.ritem.highlight {
    --ri-accent: var(--hcis-amber-500);
    --ri-line: rgba(217, 119, 6, 0.45);
    box-shadow:
        0 0 0 4px rgba(245, 158, 11, 0.12),
        0 18px 36px -18px rgba(245, 158, 11, 0.35),
        var(--ri-gloss);
}

.ritem-ribbon {
    position: absolute;
    z-index: 3;
    top: -1px;
    right: 14px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-amber-500), #f97316);
    border-radius: 0 0 8px 8px;
    padding: 0.2rem 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3);
}
.ritem-now {
    position: absolute;
    z-index: 3;
    top: -10px;
    left: 14px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    box-shadow: 0 5px 14px -3px rgba(99, 102, 241, 0.75);
}

.ri-head {
    display: flex;
}
.ri-wajib {
    color: var(--hcis-amber-700);
    font-weight: 700;
    font-size: 0.72rem;
}
.ri-nomor {
    font-weight: 700;
    color: var(--hcis-indigo-500);
    font-size: 0.72rem;
}
.ri-title {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 1.02rem;
    line-height: 1.35;
    letter-spacing: -0.01em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}
.ri-desc {
    font-size: 0.85rem;
    color: var(--hcis-slate-600);
    line-height: 1.55;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}

.ri-prose {
    margin: 0.6rem 0;
    font-size: 0.9rem;
    color: var(--hcis-slate-700);
    line-height: 1.6;
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    padding: 0.8rem 1rem;
    box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.03);
}
.ri-prose :deep(p) {
    margin-bottom: 0.5rem;
}
.ri-prose :deep(p:last-child) {
    margin-bottom: 0;
}
.ri-prose :deep(h1) {
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0.75rem 0 0.35rem;
}
.ri-prose :deep(h2) {
    font-size: 1.02rem;
    font-weight: 800;
    margin: 0.75rem 0 0.35rem;
}
.ri-prose :deep(h3) {
    font-size: 0.92rem;
    font-weight: 800;
    margin: 0.65rem 0 0.3rem;
}
.ri-prose :deep(ul),
.ri-prose :deep(ol) {
    padding-left: 1.15rem;
}
.ri-prose :deep(blockquote) {
    border-left: 3px solid var(--hcis-indigo-200);
    margin: 0.5rem 0;
    padding: 0.2rem 0 0.2rem 0.75rem;
    color: var(--hcis-slate-600);
}

.ri-link-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    transition: all 0.2s ease;
}
.ri-link-card:hover {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 16px -4px rgba(99, 102, 241, 0.12);
}
.ri-link-card-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.ri-link-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}
.ri-link-ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.14), rgba(99, 102, 241, 0.05));
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: var(--hcis-indigo-600);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.ri-link-domain {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 999px;
    padding: 0.12rem 0.5rem;
}
.ri-link-label {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
}
.ri-link-url {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    font-weight: 500;
}

.ri-quiz-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    transition: all 0.2s ease;
}
.ri-quiz-card-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.ri-quiz-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}
.ri-quiz-ico {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.14), rgba(124, 58, 237, 0.04));
    border: 1px solid rgba(124, 58, 237, 0.25);
    color: var(--hcis-violet-600);
    display: grid;
    place-items: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.ri-quiz-badge-type {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-violet-700);
    background: rgba(124, 58, 237, 0.1);
    border: 1px solid rgba(124, 58, 237, 0.25);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
}
.ri-quiz-label {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
}

.ri-btn-quiz-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    border: 1px solid var(--hcis-indigo-700);
    border-radius: 999px;
    padding: 0.48rem 1.1rem;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
    white-space: nowrap;
}

.ri-doc-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    transition: all 0.2s ease;
    max-width: 100%;
    overflow: hidden;
}
.ri-doc-card-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
    min-width: 0;
    width: 100%;
    max-width: 100%;
}
.ri-doc-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    max-width: 100%;
    flex: 1 1 0%;
}
.ri-doc-meta {
    min-width: 0;
    max-width: 100%;
    flex: 1 1 0%;
    overflow: hidden;
}
.ri-doc-name {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.92rem;
    line-height: 1.35;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ri-doc-ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--hcis-indigo-600);
    display: grid;
    place-items: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.ri-prog-badge {
    font-size: 0.66rem;
    font-weight: 800;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.ri-prog-badge.ok {
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
}
.ri-prog-badge.idle {
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.ri-btn-doc-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.5rem 1.1rem;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    color: var(--hcis-white) !important;
    font-weight: 700;
    font-size: 0.82rem;
    border: none;
    box-shadow: 0 5px 16px -4px rgba(99, 102, 241, 0.4);
    white-space: nowrap;
}

.pv-versi .linklike {
    border: 0;
    background: transparent;
    padding: 0.1rem 0;
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.pv-versi-body {
    padding: 0.7rem 0.8rem;
    background: var(--hcis-slate-50);
    border: 1px solid #eef2f7;
    border-radius: 12px;
}

/* Item Card Version Tags */
.ri-ver-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.14rem 0.48rem;
    border-radius: 6px;
    letter-spacing: 0.02em;
}
.ri-ver-tag.berlaku {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.ri-ver-tag.belum_berlaku {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border: 1px solid var(--hcis-amber-200);
}
.ri-ver-tag.versi_lama {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-300);
}
</style>
