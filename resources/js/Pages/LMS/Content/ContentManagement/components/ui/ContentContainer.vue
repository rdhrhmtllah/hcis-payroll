<template>
    <div class="content-container">
        <!-- EMPTY STATE -->
        <div class="empty-state mt-4" v-if="categories.length === 0 && files.length === 0">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <p class="mt-3 text-muted fw-medium">No items found.</p>
        </div>

        <template v-else>
            <!-- GRID VIEW -->
            <div v-if="viewMode === 'grid'">
                <div class="folders-section mb-4" v-if="categories.length > 0">
                    <!-- <h2 class="section-title">Kategori</h2> -->
                    <div class="folder-grid">
                        <FolderCard
                            v-for="category in categories"
                            :key="category.id"
                            :folderName="category.name"
                            :fileCount="category.contentCount"
                            :totalSize="category.totalSize"
                            :created_by="category.created_by"
                            :members="category.members"
                            @click="$emit('item-click', category)"
                        />
                    </div>
                </div>

                <div class="files-section mb-4" v-if="files.length > 0">
                    <h2 class="section-title">Konten</h2>
                    <div class="file-grid">
                        <div
                            v-for="file in files"
                            :key="file.id"
                            class="file-select-wrapper"
                            :class="{
                                selected: isSelected(file.id),
                            }"
                        >
                            <label v-if="enableSelection" class="select-badge" @click.stop>
                                <input
                                    class="form-check-input custom-grid-checkbox m-0"
                                    type="checkbox"
                                    :checked="isSelected(file.id)"
                                    @change="toggleSelect(file.id)"
                                />
                            </label>

                            <FileCard
                                :fileName="file.name"
                                :subtitle="buildCardSubtitle(file)"
                                :fileType="normalizeFileType(file)"
                                :thumbnailUrl="file.thumbnailUrl || file.previewUrl"
                                :previewUrl="file.previewUrl || file.thumbnailUrl"
                                :renderStatus="file.renderStatus || (file.metadata && file.metadata.renderStatus) || ''"
                                :statusVerifikasi="file.statusVerifikasi || ''"
                                :ext="file.ext || ''"
                                :tampilan="file.tampilan || ''"
                                @click="$emit('item-click', file)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- LIST VIEW -->
            <div v-else class="list-view-container">
                <div class="table-container shadow-sm bg-white">
                    <table class="table content-table align-middle mb-0 table-hover">
                        <thead class="small text-uppercase">
                            <tr>
                                <th v-if="enableSelection" class="py-3 ps-4" style="width: 56px">
                                    <input
                                        class="form-check-input m-0 custom-list-checkbox"
                                        type="checkbox"
                                        :checked="allFilesSelected"
                                        :disabled="files.length === 0"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="ps-4 py-3" style="width: 40%">Name</th>
                                <th class="py-3">Created By</th>
                                <th class="py-3">Date Modified</th>
                                <th class="py-3 text-end pe-4">Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Kategori -->
                            <tr
                                v-for="category in categories"
                                :key="'c-' + category.id"
                                @click="$emit('item-click', category)"
                                class="list-item-row"
                                style="cursor: pointer"
                            >
                                <td v-if="enableSelection" class="ps-4"></td>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="list-icon-wrapper folder-bg">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="#3b82f6">
                                                <path
                                                    d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"
                                                />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark list-title mb-1" style="font-size: 14px">
                                                {{ category.name }}
                                            </div>
                                            <div class="small text-muted" style="font-size: 12px; font-weight: 500">
                                                {{ category.contentCount }}
                                                konten
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" v-if="category.created_by">
                                        <img
                                            :src="
                                                category?.owner?.avatar ||
                                                'https://api.dicebear.com/9.x/big-smile/svg?seed=Amaya'
                                            "
                                            class="rounded-circle border"
                                            width="28"
                                            height="28"
                                            style="object-fit: cover"
                                        />
                                        <span class="fw-semibold text-dark" style="font-size: 13px">{{
                                            category.created_by
                                        }}</span>
                                    </div>
                                    <span v-else class="text-muted">-</span>
                                </td>
                                <td>
                                    <span class="fw-medium text-muted" style="font-size: 13px">{{
                                        formatDate(
                                            category.metadata?.lastModified || category.metadata?.createdDate || '-',
                                        )
                                    }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <span
                                        v-if="category.totalSize && category.totalSize !== '-'"
                                        class="badge bg-light text-dark border fw-bold px-2 py-1"
                                        >{{ category.totalSize }}</span
                                    >
                                    <span v-else class="text-muted fw-medium" style="font-size: 13px">-</span>
                                </td>
                            </tr>

                            <!-- Files -->
                            <tr
                                v-for="file in files"
                                :key="'g-' + file.id"
                                @click="$emit('item-click', file)"
                                class="list-item-row"
                                style="cursor: pointer"
                            >
                                <td v-if="enableSelection" class="ps-4" @click.stop>
                                    <input
                                        class="form-check-input m-0 custom-list-checkbox"
                                        type="checkbox"
                                        :checked="isSelected(file.id)"
                                        @change="toggleSelect(file.id)"
                                    />
                                </td>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="list-icon-wrapper" :class="'type-' + normalizeFileType(file)">
                                            <!-- Document icon for file -->
                                            <svg
                                                v-if="normalizeFileType(file) === 'pdf'"
                                                width="22"
                                                height="22"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                                                ></path>
                                                <polyline points="14 2 14 8 20 8"></polyline>
                                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                                <polyline points="10 9 9 9 8 9"></polyline>
                                            </svg>
                                            <!-- Video icon -->
                                            <svg
                                                v-else-if="
                                                    normalizeFileType(file) === 'video' ||
                                                    normalizeFileType(file) === 'audio'
                                                "
                                                width="22"
                                                height="22"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                            >
                                                <path d="M8 5V19L19 12L8 5Z" />
                                            </svg>
                                            <!-- Image icon -->
                                            <svg
                                                v-else-if="normalizeFileType(file) === 'image'"
                                                width="22"
                                                height="22"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                <polyline points="21 15 16 10 5 21"></polyline>
                                            </svg>
                                            <!-- Archive icon -->
                                            <svg
                                                v-else-if="normalizeFileType(file) === 'archive'"
                                                width="22"
                                                height="22"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <polyline points="21 8 21 21 3 21 3 8"></polyline>
                                                <rect x="1" y="3" width="22" height="5"></rect>
                                                <line x1="10" y1="12" x2="14" y2="12"></line>
                                            </svg>
                                            <!-- Default -->
                                            <svg
                                                v-else
                                                width="22"
                                                height="22"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"
                                                ></path>
                                                <polyline points="13 2 13 9 20 9"></polyline>
                                            </svg>
                                        </div>
                                        <div>
                                            <div
                                                class="fw-semibold text-dark list-title text-truncate mb-1"
                                                style="max-width: 320px; font-size: 14px"
                                            >
                                                {{ file.name }}
                                            </div>
                                            <div
                                                class="small text-muted text-uppercase"
                                                style="font-size: 11px; letter-spacing: 0.05em; font-weight: 700"
                                            >
                                                {{ buildCardSubtitle(file) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" v-if="file.owner">
                                        <img
                                            :src="file.owner.avatar"
                                            class="rounded-circle border"
                                            width="28"
                                            height="28"
                                            style="object-fit: cover"
                                        />
                                        <span class="fw-semibold text-dark" style="font-size: 13px">{{
                                            file.owner.name
                                        }}</span>
                                    </div>
                                    <span v-else class="text-muted">-</span>
                                </td>
                                <td>
                                    <span class="fw-medium text-muted" style="font-size: 13px">{{
                                        formatDate(file.metadata?.uploadedAt || '-')
                                    }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <span
                                        v-if="getSizeLabel(file) !== '-'"
                                        class="badge bg-light text-dark border fw-bold px-2 py-1"
                                        >{{ getSizeLabel(file) }}</span
                                    >
                                    <span v-else class="text-muted fw-medium" style="font-size: 13px">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import FolderCard from './folderPatch.vue';
import FileCard from './filePatch.vue';

export default {
    name: 'ContentContainer',
    components: { FolderCard, FileCard },
    props: {
        categories: { type: Array, default: () => [] },
        files: { type: Array, default: () => [] },
        viewMode: { type: String, default: 'grid' },
        selectedFileIds: { type: Array, default: () => [] },
        enableSelection: { type: Boolean, default: false },
    },
    emits: ['item-click', 'toggle-file-selection', 'toggle-select-all'],
    computed: {
        allFilesSelected() {
            if (this.files.length === 0) {
                return false;
            }

            return this.files.every((file) => this.isSelected(file.id));
        },
    },
    methods: {
        isSelected(fileId) {
            return this.selectedFileIds.includes(String(fileId));
        },

        toggleSelect(fileId) {
            this.$emit('toggle-file-selection', fileId);
        },

        toggleSelectAll() {
            this.$emit('toggle-select-all');
        },

        normalizeFileType(file) {
            /*
             * Ekstensi didahulukan. Tipe_Konten menyebut pdf, docx, xlsx, dan
             * pptx sama-sama "document", sehingga keempatnya tampil identik —
             * padahal hanya PDF yang bisa dibaca di aplikasi.
             */
            const ext = (file?.ext || '').toLowerCase();
            if (['pdf', 'docx', 'xlsx', 'pptx'].includes(ext)) return ext;
            if (['jpg', 'jpeg', 'png'].includes(ext)) return 'image';

            const fileType = (file?.fileType || file?.type || '').toLowerCase();
            if (['video', 'audio', 'image', 'pdf', 'archive', 'document'].includes(fileType)) {
                return fileType;
            }

            const mime = (file?.metadata?.typeFile || '').toLowerCase();
            if (mime.startsWith('image/')) return 'image';
            if (mime.startsWith('video/')) return 'video';
            if (mime.startsWith('audio/')) return 'audio';
            if (mime.includes('pdf')) return 'pdf';
            return 'default';
        },

        getSizeLabel(file) {
            const metadata = file?.metadata || {};
            if (metadata.sizeHuman) return metadata.sizeHuman;
            if (typeof metadata.sizeBytes === 'number') {
                return this.formatBytes(metadata.sizeBytes);
            }
            if (metadata.storageSizeBytes) {
                return this.formatBytes(Number(metadata.storageSizeBytes));
            }
            return '-';
        },

        buildCardSubtitle(file) {
            const metadata = file?.metadata || {};
            const typeLabel = this.getContentTypeLabel(file);

            if (this.normalizeFileType(file) === 'video') {
                const videoParts = [typeLabel];
                if (metadata.durationSeconds) {
                    videoParts.push(`${metadata.durationSeconds}s`);
                }
                if (metadata.resolution) {
                    videoParts.push(metadata.resolution);
                }
                if (metadata.encodeProgress !== null && metadata.encodeProgress !== undefined) {
                    videoParts.push(`${metadata.encodeProgress}%`);
                }
                return videoParts.join(' • ');
            }

            const fileParts = [typeLabel];

            const sizeLabel = this.getSizeLabel(file);
            if (sizeLabel !== '-') {
                fileParts.push(sizeLabel);
            }

            return fileParts.join(' • ');
        },

        getContentTypeLabel(file) {
            const type = this.normalizeFileType(file);
            const labels = {
                video: 'VIDEO',
                audio: 'AUDIO',
                image: 'IMAGE',
                pdf: 'PDF',
                archive: 'ARCHIVE',
                document: 'DOCUMENT',
                default: 'FILE',
            };
            return labels[type] || labels.default;
        },

        formatBytes(bytes) {
            if (!Number.isFinite(bytes) || bytes <= 0) return '-';
            if (bytes < 1024) return `${bytes} B`;

            const units = ['KB', 'MB', 'GB', 'TB'];
            let value = bytes;
            let unitIndex = -1;

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024;
                unitIndex++;
            }

            return `${value.toFixed(2)} ${units[unitIndex]}`;
        },

        formatDate(dateStr) {
            if (!dateStr || dateStr === '-') return '-';
            try {
                // MySQL timestamp format "YYYY-MM-DD HH:MM:SS.mmm" is not directly parsed in all browsers.
                // Replacing space with 'T' makes it ISO-8601 compliant.
                const cleanStr = String(dateStr).trim().replace(' ', 'T');
                const date = new Date(cleanStr);
                if (isNaN(date.getTime())) return dateStr;

                const options = {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                };
                // Format: "06 Apr 2026 15:12"
                return new Intl.DateTimeFormat('id-ID', options).format(date);
            } catch (e) {
                return dateStr;
            }
        },
    },
};
</script>
<style scoped>
.bi {
    display: inline-table;
}

.section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    margin-bottom: 24px;
    letter-spacing: -0.01em;
}

.content-container {
    width: 100%;
}

/* Grid Layouts */
.folder-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    padding: 10px 8px;
    margin: 0 -8px -10px -8px;
}

.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    padding: 10px 8px;
    margin: 0 -8px -10px -8px;
}

.file-select-wrapper {
    position: relative;
    border-radius: 16px;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.file-select-wrapper.selected {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.35);
}

.select-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
    transform: scale(0.85);
}

.file-select-wrapper:hover .select-badge,
.file-select-wrapper.selected .select-badge {
    opacity: 1;
    transform: scale(1);
}

.custom-grid-checkbox {
    width: 20px !important;
    height: 20px !important;
    border-radius: 50% !important;
    background-color: rgba(255, 255, 255, 0.95);
    border: 1.5px solid rgba(203, 213, 225, 0.8) !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    cursor: pointer;
    transition: all 0.2s ease;
}

.custom-grid-checkbox:hover,
.file-select-wrapper.selected .custom-grid-checkbox {
    background-color: var(--hcis-white);
    border-color: var(--hcis-blue-500) !important;
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.15);
}

.custom-grid-checkbox:checked {
    background-color: var(--hcis-blue-500) !important;
    border-color: var(--hcis-blue-500) !important;
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);
}

.custom-list-checkbox {
    width: 1.15em !important;
    height: 1.15em !important;
    cursor: pointer;
}

/* List View Styles */
.table-container {
    overflow: hidden;
    border-radius: 16px;
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
}

.content-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.content-table th {
    background-color: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    padding: 14px 16px;
    border-bottom: 1px solid var(--hcis-slate-200);
    text-transform: uppercase;
}

.content-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--hcis-slate-100);
    background-color: var(--hcis-white);
    transition: background-color 0.2s ease;
}

.content-table tr:last-child td {
    border-bottom: none;
}

.list-item-row {
    transition: background-color 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.list-item-row:hover td {
    background-color: var(--hcis-slate-50);
}

.list-icon-wrapper {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.list-icon-wrapper.folder-bg {
    background-color: #eff6ff;
}

/* Colors matching filePatch.vue */
.type-video {
    background-color: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}
.type-pdf {
    background-color: var(--hcis-red-100);
    color: var(--hcis-red-600);
}
.type-audio {
    background-color: var(--hcis-purple-100);
    color: var(--hcis-purple-600);
}
.type-image {
    background-color: var(--hcis-green-100);
    color: var(--hcis-green-600);
}
.type-archive {
    background-color: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
}
.type-default {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.type-document {
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 0;
    color: var(--hcis-slate-400);
    background: var(--hcis-zinc-50);
    border-radius: 24px;
    border: 2px dashed var(--hcis-slate-200);
}
</style>
