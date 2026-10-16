<template>
    <!-- Modal pemilihan & penamaan file. Engine upload + floating widget kini
         GLOBAL via useMediaUpload (hidup di AppShell), jadi modal hanya menyiapkan
         draft lalu menyerahkan ke composable saat "Mulai Upload". -->
    <Modal
        :show="show"
        title="Upload Konten"
        :subtitle="modalSubtitle"
        size="xlarge"
        :showFooter="true"
        cancelText="Batal & Tutup"
        confirmText="Mulai Upload Sekarang"
        :showCancel="true"
        :showConfirm="draftQueue.length > 0"
        :confirmDisabled="!isFileValid"
        @close="handleClose"
        @confirm="startUpload"
        @update:show="$emit('update:show', $event)"
    >
        <template #body>
            <div class="upload-form">
                <div class="upload-layout">
                    <section class="upload-main">
                        <div class="form-section">
                            <div class="section-title-row">
                                <h6>Daftar File (Maks {{ maxFiles }} file)</h6>
                                <span class="drop-hint">{{ dropHintSizeText }}</span>
                            </div>

                            <div
                                v-if="draftQueue.length < maxFiles"
                                class="file-drop-zone mb-3"
                                :class="{ 'drag-over': isDragging }"
                                @dragover.prevent="isDragging = true"
                                @dragleave="isDragging = false"
                                @drop.prevent="handleDrop"
                                @click="openFilePicker"
                            >
                                <input
                                    ref="fileInput"
                                    type="file"
                                    :accept="acceptedFileTypes"
                                    multiple
                                    @change="handleFileSelect"
                                    style="display: none"
                                />
                                <div class="drop-placeholder">
                                    <i class="bi bi-cloud-upload drop-icon"></i>
                                    <p class="drop-text">Drag & drop berkas ke sini atau klik untuk memilih</p>
                                    <p class="drop-hint">{{ dropHintFormatsText }}</p>
                                    <small class="allowed-format-text">Format didukung: {{ allowedFormatsText }}</small>
                                </div>
                            </div>

                            <div v-if="errorMessage" class="alert-error mb-3">
                                <i class="bi bi-exclamation-triangle me-2"></i> {{ errorMessage }}
                            </div>

                            <div v-if="draftQueue.length > 0" class="queue-list">
                                <div v-for="(item, index) in draftQueue" :key="item.id" class="queue-item-editable">
                                    <div class="editable-header">
                                        <span class="file-icon">{{ getFileIcon(item.file.name) }}</span>
                                        <div class="input-name-wrapper w-100">
                                            <label class="name-label">Judul Konten (Bisa diedit)</label>
                                            <input
                                                type="text"
                                                v-model="item.customName"
                                                class="form-control custom-name-input"
                                                placeholder="Masukkan judul konten..."
                                            />
                                        </div>
                                        <button
                                            class="btn-action btn-delete ms-2"
                                            @click.stop="removeDraft(index)"
                                            title="Hapus dari daftar"
                                        >
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                    <div class="editable-footer mt-2">
                                        <span class="file-size"
                                            >{{ item.file.name }} ({{ formatFileSize(item.file.size) }})</span
                                        >
                                        <span
                                            class="badge-type"
                                            :class="{
                                                'bg-primary-subtle text-primary': item.tipe_konten === 'video',
                                                'bg-danger-subtle text-danger': item.tipe_konten === 'document',
                                                'bg-success-subtle text-success': item.tipe_konten === 'image',
                                            }"
                                        >
                                            {{ item.tipe_konten.toUpperCase() }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="draftQueue.length === 0" class="empty-queue">
                                Belum ada file yang dipilih. Silakan pilih file terlebih dahulu.
                            </div>
                        </div>
                    </section>

                    <aside class="upload-sidebar">
                        <div class="summary-card">
                            <h6>Informasi Tujuan</h6>
                            <ul class="summary-list">
                                <li>
                                    <span>Kategori {{ isLmsScope ? 'LMS' : 'SOP' }}</span>
                                    <strong>{{ categoryName || '-' }}</strong>
                                </li>
                                <li>
                                    <span>Total Dipilih</span>
                                    <strong>{{ draftQueue.length }} / {{ maxFiles }} File</strong>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from '../../../../components/ui/modal.vue';
import { ElMessage } from 'element-plus';
import 'element-plus/es/components/message/style/css';
import { useMediaUpload } from '../../../../../../composables/useMediaUpload.js';

const MAX_FILES = 5;

/*
 * Aturan ekstensi & batas ukuran dikelola terpusat lewat useMediaUpload (mencerminkan KontenPolicy server).
 * LMS: dibatasi hanya format PDF dan Video.
 * SOP: mendukung seluruh format dokumen & gambar modern.
 */

export default {
    name: 'UploadKontenSystem',
    components: { Modal },
    props: {
        show: { type: Boolean, required: true },
        categoryId: { type: [Number, String, null], default: null },
        categoryName: { type: String, default: '' },
        scope: {
            type: String,
            default: () =>
                typeof window !== 'undefined' && window.location.pathname.startsWith('/sop') ? 'SOP' : 'LMS',
        },
    },
    emits: ['close', 'update:show', 'success'],
    setup() {
        return { media: useMediaUpload() };
    },
    data() {
        return {
            draftQueue: [], // hanya draft pemilihan (belum diupload)
            isDragging: false,
            errorMessage: '',
            maxFiles: MAX_FILES,
        };
    },
    computed: {
        resolvedScope() {
            return String(
                this.scope ||
                    (typeof window !== 'undefined' && window.location.pathname.startsWith('/sop') ? 'SOP' : 'LMS'),
            ).toUpperCase();
        },
        isLmsScope() {
            return this.resolvedScope === 'LMS';
        },
        modalSubtitle() {
            return this.categoryName ? `Kategori aktif: ${this.categoryName}` : 'Pilih file dan beri judul.';
        },
        acceptedFileTypes() {
            return this.media.getAcceptAttr
                ? this.media.getAcceptAttr(this.resolvedScope)
                : this.isLmsScope
                  ? '.pdf,.mp4,.mov,.mkv,.webm'
                  : this.media.ACCEPT_ATTR;
        },
        allowedFormatsText() {
            const list = this.media.getExtDiizinkan
                ? this.media.getExtDiizinkan(this.resolvedScope)
                : this.isLmsScope
                  ? ['pdf', 'mp4', 'mov', 'mkv', 'webm']
                  : this.media?.EXT_DIIZINKAN;
            return list.map((ext) => ext.toUpperCase()).join(', ');
        },
        dropHintSizeText() {
            return this.isLmsScope
                ? 'Maks: 2 GB (Video) · 50 MB (PDF)'
                : 'Maks: 2 GB (Video) · 50 MB (Dokumen) · 10 MB (Gambar)';
        },
        dropHintFormatsText() {
            return this.isLmsScope
                ? 'Dokumen (PDF) & Video (MP4, MOV, MKV, WebM)'
                : 'Dokumen (PDF, Word, Excel, PPT), Video (MP4, MOV, MKV, WebM), & Gambar (JPG, PNG)';
        },
        isFileValid() {
            const hasFiles = this.draftQueue.length > 0 && this.draftQueue.length <= this.maxFiles;
            const allNamesFilled = this.draftQueue.every((item) => item.customName && item.customName.trim() !== '');
            return hasFiles && allNamesFilled && !!this.categoryId;
        },
    },
    watch: {
        show(newVal) {
            if (newVal) {
                // Reset draft tiap kali modal dibuka.
                this.draftQueue = [];
                this.errorMessage = '';
            }
        },
    },
    methods: {
        handleClose() {
            this.$emit('close');
            this.$emit('update:show', false);
        },
        openFilePicker() {
            this.$refs.fileInput?.click();
        },
        handleDrop(event) {
            this.isDragging = false;
            this.processSelectedFiles(event.dataTransfer.files);
        },
        handleFileSelect(event) {
            this.processSelectedFiles(event.target.files);
            event.target.value = '';
        },
        processSelectedFiles(filesList) {
            this.errorMessage = '';
            if (!filesList || filesList.length === 0) return;

            const remainingSlots = this.maxFiles - this.draftQueue.length;
            const filesToProcess = Array.from(filesList).slice(0, remainingSlots);

            if (filesList.length > remainingSlots) {
                ElMessage.warning(`Maksimal antrean ${this.maxFiles} file.`);
            }

            filesToProcess.forEach((file) => {
                // Satu pemeriksa untuk ekstensi, ukuran per-format, dan berkas
                // kosong — alasannya sudah siap tampil apa adanya.
                const periksa = this.media.periksaBerkas(file, this.resolvedScope);
                if (!periksa.ok) {
                    ElMessage.error(periksa.alasan);
                    return;
                }

                this.draftQueue.push({
                    id: Math.random().toString(36).substr(2, 9),
                    file,
                    customName: this.deriveTitleFromFileName(file.name),
                    tipe_konten: periksa.tipe,
                    isTus: periksa.tipe === 'video',
                });
            });
        },
        removeDraft(index) {
            this.draftQueue.splice(index, 1);
        },
        startUpload() {
            if (!this.isFileValid) {
                ElMessage.error('Pastikan semua file sudah diberi judul.');
                return;
            }

            // Serahkan ke engine global. Karena judul bisa diedit, kirim file +
            // customName satu per satu agar nama kustom ikut terbawa.
            this.draftQueue.forEach((draft) => {
                const accepted = this.media.enqueue([draft.file], {
                    categoryId: this.categoryId,
                    categoryName: this.categoryName,
                    scope: this.resolvedScope,
                });
                if (accepted > 0) {
                    // enqueue menaruh item di akhir queue; set nama kustomnya.
                    const last = this.media.state.queue[this.media.state.queue.length - 1];
                    if (last) last.customName = draft.customName;
                }
            });

            this.media.startQueueUploads();

            ElMessage({
                message:
                    '⚠️ Upload berjalan di background. Selama transfer berlangsung, jangan tutup tab atau refresh halaman!',
                type: 'warning',
                duration: 8000,
                showClose: true,
            });

            // Beri tahu parent untuk refresh daftar konten, lalu tutup modal.
            this.$emit('success');
            this.draftQueue = [];
            this.handleClose();
        },

        // Helpers
        getFileExtension(fileName) {
            const parts = String(fileName || '').split('.');
            return parts.length < 2 ? '' : parts.pop().toLowerCase();
        },
        deriveTitleFromFileName(fileName) {
            return String(fileName || '')
                .replace(/\.[^/.]+$/, '')
                .replace(/[._-]+/g, ' ')
                .trim()
                .slice(0, 255);
        },
        getFileIcon(fileName) {
            const ext = this.getFileExtension(fileName);
            const icons = {
                pdf: '📄',
                docx: '📝',
                doc: '📝',
                xlsx: '📊',
                xls: '📊',
                pptx: '📑',
                ppt: '📑',
                jpg: '🖼️',
                jpeg: '🖼️',
                png: '🖼️',
                mp4: '🎬',
                mov: '🎬',
                avi: '🎬',
                mkv: '🎬',
                webm: '🎬',
            };
            return icons[ext] || '📄';
        },
        formatFileSize(bytes) {
            if (!bytes) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
            return (bytes / 1073741824).toFixed(2) + ' GB';
        },
    },
};
</script>

<style scoped>
/* ===================== MODAL STYLES ===================== */
.upload-layout {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 16px;
}
.upload-main,
.upload-sidebar {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.form-section,
.summary-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: var(--hcis-white);
    padding: 18px;
}
.section-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.section-title-row h6,
.summary-card h6 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

.file-drop-zone {
    border: 2px dashed #bfdbfe;
    border-radius: 14px;
    padding: 36px 18px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--hcis-slate-50);
}
.file-drop-zone:hover {
    border-color: var(--hcis-sky-400);
    background: var(--hcis-sky-50);
}
.drop-icon {
    font-size: 38px;
    color: var(--hcis-sky-600);
}
.drop-text {
    font-weight: 700;
    color: var(--hcis-slate-900);
    margin: 0;
    font-size: 15px;
}
.drop-hint {
    font-size: 12px;
    color: var(--hcis-slate-500);
}
.allowed-format-text {
    font-size: 11px;
    color: var(--hcis-slate-400);
}

.queue-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.queue-item-editable {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 14px;
    background: var(--hcis-slate-50);
}
.editable-header {
    display: flex;
    align-items: center;
    gap: 12px;
}
.file-icon {
    font-size: 28px;
}
.input-name-wrapper {
    display: flex;
    flex-direction: column;
    flex: 1;
}
.name-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.custom-name-input {
    border: 1px solid var(--hcis-slate-300);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 600;
    color: var(--hcis-slate-800);
    background: white;
    transition: all 0.2s;
}
.custom-name-input:focus {
    border-color: var(--hcis-sky-600);
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    outline: none;
}
.btn-delete {
    background: white;
    border: 1px solid var(--hcis-red-200);
    color: var(--hcis-red-500);
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.btn-delete:hover {
    background: var(--hcis-red-100);
}
.editable-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-left: 40px;
}
.file-size {
    font-size: 11px;
    color: var(--hcis-slate-500);
}
.badge-type {
    font-size: 10px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
}
.empty-queue {
    font-size: 13px;
    color: var(--hcis-slate-400);
    text-align: center;
    padding: 20px;
}
.alert-error {
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    color: var(--hcis-red-700);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
}

.summary-list {
    list-style: none;
    padding: 0;
    margin: 12px 0 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.summary-list li {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    border-bottom: 1px dashed var(--hcis-slate-200);
    padding-bottom: 8px;
}
.summary-list li:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
</style>
