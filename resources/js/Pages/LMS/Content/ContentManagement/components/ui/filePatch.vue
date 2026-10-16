<template>
    <div class="file-card stitch-card mb-0 h-100" :class="'type-' + fileType">
        <!-- Thumbnail -->
        <div class="file-thumbnail">
            <img
                v-if="resolvedThumbnail && !imageError"
                class="thumbnail-image"
                :src="resolvedThumbnail"
                :alt="fileName"
                @error="imageError = true"
            />

            <!-- Badge status render (khusus PDF) -->
            <span v-if="showRenderBadge" class="render-badge" :class="renderBadgeClass" :title="renderBadgeTitle">
                <span v-if="isRenderProcessing" class="render-spinner"></span>
                {{ renderBadgeText }}
            </span>

            <!--
                Nama format, ditulis apa adanya. Ikon dan warna saja tidak cukup
                membedakan docx dari xlsx bagi orang yang belum hafal warnanya.
            -->
            <span v-if="extBadge" class="ext-badge" :title="hanyaUnduh ? 'Hanya bisa diunduh' : ''">
                {{ extBadge }}
                <i v-if="hanyaUnduh" class="bi bi-download ext-badge-unduh"></i>
            </span>

            <div class="overlay-icon">
                <!-- Format tanpa penyaji: yang ditawarkan memang unduhan -->
                <svg
                    v-if="hanyaUnduh"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    :style="{ color: iconColor }"
                >
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <!-- Play Icon for Video/Audio -->
                <svg
                    v-else-if="fileType === 'video' || fileType === 'audio'"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    :style="{ color: iconColor }"
                >
                    <path d="M8 5V19L19 12L8 5Z" />
                </svg>
                <!-- Document Icon for PDF -->
                <svg
                    v-else-if="fileType === 'pdf'"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    :style="{ color: iconColor }"
                >
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <!-- Image Icon -->
                <svg
                    v-else-if="fileType === 'image'"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    :style="{ color: iconColor }"
                >
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                <!-- Archive Icon -->
                <svg
                    v-else-if="fileType === 'archive'"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    :style="{ color: iconColor }"
                >
                    <polyline points="21 8 21 21 3 21 3 8"></polyline>
                    <rect x="1" y="3" width="22" height="5"></rect>
                    <line x1="10" y1="12" x2="14" y2="12"></line>
                </svg>
                <!-- Default Icon -->
                <svg
                    v-else
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    :style="{ color: iconColor }"
                >
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                    <polyline points="13 2 13 9 20 9"></polyline>
                </svg>
            </div>
        </div>

        <!-- Footer -->
        <div class="file-footer">
            <div class="file-type-icon">
                <!-- Bottom Small Icon (Simplified & Dynamic) -->
                <svg
                    v-if="fileType === 'video' || fileType === 'audio'"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="white"
                >
                    <path d="M8 5V19L19 12L8 5Z" />
                </svg>
                <svg
                    v-else-if="fileType === 'pdf'"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="white"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <svg
                    v-else-if="fileType === 'image'"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="white"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                <svg
                    v-else-if="fileType === 'archive'"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="white"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="21 8 21 21 3 21 3 8"></polyline>
                    <rect x="1" y="3" width="22" height="5"></rect>
                    <line x1="10" y1="12" x2="14" y2="12"></line>
                </svg>
                <svg
                    v-else
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="white"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                    <polyline points="14 2 14 8 20 8" />
                </svg>
            </div>

            <div class="file-info">
                <h3 class="file-name" :title="fileName">{{ fileName }}</h3>
                <p class="file-subtitle">{{ subtitle }}</p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FileCard',
    props: {
        fileName: { type: String, required: true },
        subtitle: { type: String, default: 'General File' },
        fileType: {
            type: String,
            default: 'default',
            validator: (value) =>
                ['video', 'pdf', 'docx', 'xlsx', 'pptx', 'audio', 'image', 'document', 'archive', 'default'].includes(
                    value,
                ),
        },
        // Ekstensi sebenarnya — satu-satunya yang membedakan docx dari xlsx.
        ext: { type: String, default: '' },
        // Cara penyajian dari server: viewer | inline | putar | unduh
        tampilan: { type: String, default: '' },
        thumbnailUrl: { type: String, default: '' },
        previewUrl: { type: String, default: '' },
        // Status render PDF: PENDING | PROCESSING | DONE | FAILED | '' (non-PDF)
        renderStatus: { type: String, default: '' },
        // Status verifikasi berkas: MENUNGGU | SIAP | DITOLAK |
        // TERTUNDA_PEMINDAIAN | LAMA | '' (video, yang tak melewati penyimpanan kita)
        statusVerifikasi: { type: String, default: '' },
    },
    data() {
        return {
            imageError: false,
        };
    },
    watch: {
        resolvedThumbnail() {
            this.imageError = false;
        },
    },
    computed: {
        resolvedThumbnail() {
            return this.previewUrl || this.thumbnailUrl || '';
        },
        extBadge() {
            const e = (this.ext || '').toUpperCase();
            // Video tak punya berkas di penyimpanan kita, jadi tak ada ekstensi
            // yang bermakna untuk ditampilkan.
            return e && this.fileType !== 'video' ? e : '';
        },
        hanyaUnduh() {
            return (this.tampilan || '').toLowerCase() === 'unduh';
        },
        /*
         * Verifikasi mendahului render, jadi ia juga mendahului di badge.
         *
         * Selama berkas belum lolos verifikasi ia belum pindah dari karantina —
         * bicara soal "render" pada saat itu menyesatkan, dan status verifikasi
         * berlaku untuk SEMUA jenis berkas, bukan cuma PDF seperti render.
         */
        verifikasiTertunda() {
            const v = (this.statusVerifikasi || '').toUpperCase();
            return ['MENUNGGU', 'DITOLAK', 'TERTUNDA_PEMINDAIAN'].includes(v);
        },
        showRenderBadge() {
            if (this.verifikasiTertunda) return true;
            if (this.fileType !== 'pdf') return false;
            const s = (this.renderStatus || '').toUpperCase();
            return ['PENDING', 'PROCESSING', 'FAILED', 'DONE'].includes(s);
        },
        isRenderProcessing() {
            const v = (this.statusVerifikasi || '').toUpperCase();
            if (v === 'MENUNGGU' || v === 'TERTUNDA_PEMINDAIAN') return true;
            if (this.verifikasiTertunda) return false; // DITOLAK: berhenti, bukan berputar
            const s = (this.renderStatus || '').toUpperCase();
            return s === 'PROCESSING' || s === 'PENDING';
        },
        renderBadgeText() {
            const v = (this.statusVerifikasi || '').toUpperCase();
            if (v === 'MENUNGGU') return 'Memeriksa';
            if (v === 'TERTUNDA_PEMINDAIAN') return 'Menunggu pindai';
            if (v === 'DITOLAK') return 'Ditolak';

            const map = {
                PENDING: 'Menunggu',
                PROCESSING: 'Memproses',
                DONE: 'Siap',
                FAILED: 'Gagal',
            };
            return map[(this.renderStatus || '').toUpperCase()] || '';
        },
        renderBadgeClass() {
            const v = (this.statusVerifikasi || '').toUpperCase();
            if (v === 'DITOLAK') return 'render-failed';
            if (v === 'MENUNGGU' || v === 'TERTUNDA_PEMINDAIAN') return 'render-processing';

            return 'render-' + (this.renderStatus || '').toLowerCase();
        },
        renderBadgeTitle() {
            const v = (this.statusVerifikasi || '').toUpperCase();
            if (v === 'MENUNGGU') return 'Isi berkas sedang diperiksa — belum bisa dipakai di Builder';
            if (v === 'TERTUNDA_PEMINDAIAN') return 'Menunggu pemindaian antivirus';
            if (v === 'DITOLAK') return 'Berkas ditolak saat verifikasi dan tidak bisa dipakai';

            const s = (this.renderStatus || '').toUpperCase();
            if (s === 'DONE') return 'Dokumen siap dibaca di Secure Viewer';
            if (s === 'FAILED') return 'Render gagal — coba render ulang';
            return 'Dokumen sedang disiapkan untuk Secure Viewer';
        },
        // Memberikan warna ikon overlay berdasarkan tipe agar kontras.
        // Warnanya mengikuti konvensi Office yang sudah dikenal orang: Word biru,
        // Excel hijau, PowerPoint jingga — supaya tak perlu dihafal ulang.
        iconColor() {
            const colors = {
                video: '#0284c7',
                pdf: '#dc2626',
                docx: '#2563eb',
                xlsx: '#15803d',
                pptx: '#ea580c',
                audio: '#9333ea',
                image: '#16a34a',
                document: '#4f46e5',
                archive: '#d97706',
                default: '#475569',
            };
            return colors[this.fileType] || colors.default;
        },
    },
};
</script>
<style scoped>
/* CUSTOM CSS UNTUK FILE CARD */
.file-card {
    width: 100%;
    cursor: pointer;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    border: 1px solid var(--color-border, var(--hcis-slate-200));
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    box-shadow:
        0 4px 20px -2px rgba(0, 0, 0, 0.05),
        0 2px 8px -1px rgba(0, 0, 0, 0.03);
    background-color: var(--hcis-white);
}

.file-card:hover {
    transform: translateY(-5px);
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.08),
        0 10px 10px -5px rgba(0, 0, 0, 0.03);
    border-color: var(--color-primary, var(--hcis-blue-500));
}

/* Thumbnail Area */
.file-thumbnail {
    aspect-ratio: 1.8 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    transition: background-color 0.3s ease;
}

.thumbnail-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.file-card:hover .thumbnail-image {
    transform: scale(1.06);
}

/* Varian Warna Thumbnail Berdasarkan Tipe - Gradient Modern */
.type-video .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-sky-100) 0%, var(--hcis-sky-200) 100%);
}
.type-pdf .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-red-100) 0%, var(--hcis-red-200) 100%);
}
.type-audio .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-purple-100) 0%, #e9d5ff 100%);
}
.type-image .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-green-100) 0%, var(--hcis-green-200) 100%);
}
.type-archive .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-amber-100) 0%, var(--hcis-amber-200) 100%);
}
.type-document .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
}
.type-docx .file-thumbnail {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
}
.type-xlsx .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-green-100) 0%, var(--hcis-green-200) 100%);
}
.type-pptx .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-orange-100) 0%, #fed7aa 100%);
}
.type-default .file-thumbnail {
    background: linear-gradient(135deg, var(--hcis-slate-100) 0%, var(--hcis-slate-200) 100%);
}

/* Badge status render (PDF) */
.render-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 999px;
    letter-spacing: 0.02em;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
}
.render-pending {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}
.render-processing {
    background: #dbeafe;
    color: #1e40af;
}
.render-done {
    background: var(--hcis-green-100);
    color: #166534;
}
.render-failed {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

/* Nama format di sudut kiri-atas thumbnail */
.ext-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    border-radius: 6px;
    color: var(--hcis-slate-900);
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(4px);
}

.ext-badge-unduh {
    font-size: 0.7rem;
    opacity: 0.7;
}

.render-spinner {
    width: 9px;
    height: 9px;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: render-spin 0.7s linear infinite;
}
@keyframes render-spin {
    to {
        transform: rotate(360deg);
    }
}

/* Glassmorphic Play & Media Buttons */
.overlay-icon {
    position: absolute;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(8px);
    background-color: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.4);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    z-index: 2;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.file-card:hover .overlay-icon {
    transform: scale(1.15);
    background-color: rgba(255, 255, 255, 0.95);
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.15);
}

/* Footer Section */
.file-footer {
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    background-color: var(--hcis-white);
    border-top: 1px solid var(--hcis-slate-50);
}

/* Ikon Kecil di Footer */
.file-type-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.file-card:hover .file-type-icon {
    transform: scale(1.1);
}

/* Warna Ikon Footer */
.type-video .file-type-icon {
    background-color: var(--hcis-blue-500);
}
.type-pdf .file-type-icon {
    background-color: var(--hcis-red-500);
}
.type-audio .file-type-icon {
    background-color: var(--hcis-purple-500);
}
.type-image .file-type-icon {
    background-color: #22c55e;
}
.type-archive .file-type-icon {
    background-color: var(--hcis-amber-500);
}
.type-document .file-type-icon {
    background-color: var(--hcis-indigo-600);
}
.type-docx .file-type-icon {
    background-color: var(--hcis-blue-600);
}
.type-xlsx .file-type-icon {
    background-color: var(--hcis-green-700);
}
.type-pptx .file-type-icon {
    background-color: var(--hcis-orange-600);
}
.type-default .file-type-icon {
    background-color: var(--hcis-slate-500);
}

/* Typography */
.file-info {
    overflow: hidden;
}
.file-name {
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
    font-weight: 600;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-subtitle {
    color: var(--hcis-slate-500);
    font-size: 0.72rem;
    font-weight: 500;
    margin: 2px 0 0 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
