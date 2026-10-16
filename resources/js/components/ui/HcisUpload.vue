<template>
    <div
        class="hcis-upload"
        :class="[
            `hcis-upload--sentiment-${sentiment}`,
            `hcis-upload--variant-${variant}`,
            `hcis-upload--layout-${resolvedLayout}`,
            {
                'hcis-upload--disabled': disabled,
                'hcis-upload--drag': drag,
            }
        ]"
    >
        <!-- Label Berkas (Jika Diisi) -->
        <label v-if="label" class="hcis-upload__label">
            {{ label }}
        </label>

        <!-- Hidden Input File HTML -->
        <input
            ref="fileInputRef"
            type="file"
            class="hcis-upload__input"
            :accept="accept"
            :multiple="multiple"
            :disabled="disabled"
            @change="handleFileSelect"
        />

        <!-- Hidden Input Camera HTML (Dedicated Mobile Camera Capture) -->
        <input
            v-if="camera"
            ref="cameraInputRef"
            type="file"
            class="hcis-upload__input"
            accept="image/*"
            capture="environment"
            :disabled="disabled"
            @change="handleFileSelect"
        />

        <!-- State 1 & 2: Dropzone & Trigger Button (Disembunyikan Otomatis Bila Limit Terpenuhi) -->
        <transition name="hcis-upload-fade">
            <div v-if="!isLimitReached">
                <!-- Zone 1: Drag & Drop Area Dropzone (Box Mode) -->
                <div
                    v-if="resolvedLayout === 'dropzone'"
                    class="hcis-upload__dragger"
                    :class="[
                        `hcis-upload__dragger--${borderStyle}`,
                        { 'is-dragover': isDragOver }
                    ]"
                    tabindex="0"
                    role="button"
                    :aria-label="label || 'Unggah berkas'"
                    @click="triggerFilePicker"
                    @keydown.enter.prevent="triggerFilePicker"
                    @keydown.space.prevent="triggerFilePicker"
                    @dragover.prevent="handleDragOver"
                    @dragenter.prevent="handleDragOver"
                    @dragleave.prevent="handleDragLeave"
                    @drop.prevent="handleDrop"
                >
                    <!-- Pattern Overlays in Dropzone -->
                    <div v-if="pattern === 'grid'" class="hcis-pattern-grid hcis-pattern-grid--diagonal" aria-hidden="true" />
                    <div v-else-if="pattern === 'dots'" class="hcis-pattern-dots" aria-hidden="true" />
                    <div v-else-if="pattern === 'crosshair'" class="hcis-pattern-crosshair" aria-hidden="true" />
                    <div v-else-if="pattern === 'stripes'" class="hcis-pattern-stripes" aria-hidden="true" />
                    <div v-else-if="pattern === 'rings'" class="hcis-pattern-rings" aria-hidden="true" />
                    <div v-else-if="pattern === 'hex'" class="hcis-pattern-hex" aria-hidden="true" />

                    <slot name="dragger">
                        <div class="hcis-upload__drop-content">
                            <!-- Jewel Cloud Icon Badge -->
                            <div class="hcis-upload__icon-badge">
                                <HcisIcon :name="icon || 'cloud-arrow-up-fill'" size="md" />
                            </div>

                            <div v-if="!camera" class="hcis-upload__title">
                                Tarik & seret berkas ke sini, atau <span class="hcis-upload__browse-btn">Pilih Berkas</span>
                            </div>
                            <div v-else class="hcis-upload__title hcis-upload__title--camera">
                                Tarik &amp; seret berkas ke sini, atau
                            </div>

                            <!-- Kamera dan File terpisah: pemilih file Android terbaru
                                 (photo picker) tidak menawarkan kamera, jadi kamera
                                 harus dibuka lewat input ber-capture sendiri. -->
                            <div v-if="camera" class="hcis-upload__action-buttons" @click.stop>
                                <button
                                    type="button"
                                    class="hcis-upload__action-btn hcis-upload__action-btn--single hcis-upload__action-btn--camera"
                                    :disabled="disabled"
                                    title="Ambil foto dengan kamera"
                                    aria-label="Ambil foto dengan kamera"
                                    @click="triggerCameraPicker"
                                >
                                    <HcisIcon name="camera-fill" size="xs" />
                                    <span>Kamera</span>
                                </button>
                                <button
                                    type="button"
                                    class="hcis-upload__action-btn hcis-upload__action-btn--single"
                                    :disabled="disabled"
                                    title="Pilih berkas dari penyimpanan"
                                    aria-label="Pilih berkas dari penyimpanan"
                                    @click="triggerFilePicker"
                                >
                                    <HcisIcon name="folder2-open" size="xs" />
                                    <span>File</span>
                                </button>
                            </div>

                            <div v-if="hintText" class="hcis-upload__hint">
                                {{ hintText }}
                            </div>
                        </div>
                    </slot>
                </div>

                <!-- Zone 2: Compact Solid Upload Bar -->
                <div
                    v-else-if="resolvedLayout === 'bar'"
                    class="hcis-upload__bar"
                    :class="[
                        `hcis-upload__bar--${borderStyle}`,
                        { 'is-dragover': isDragOver }
                    ]"
                    tabindex="0"
                    role="button"
                    :aria-label="label || 'Unggah berkas'"
                    @click="triggerFilePicker"
                    @keydown.enter.prevent="triggerFilePicker"
                    @keydown.space.prevent="triggerFilePicker"
                    @dragover.prevent="handleDragOver"
                    @dragenter.prevent="handleDragOver"
                    @dragleave.prevent="handleDragLeave"
                    @drop.prevent="handleDrop"
                >
                    <div class="hcis-upload__bar-left">
                        <div class="hcis-upload__bar-icon">
                            <HcisIcon :name="icon || 'cloud-arrow-up-fill'" size="xs" />
                        </div>
                        <div class="hcis-upload__bar-text">
                            <span class="hcis-upload__bar-title">Klik atau seret berkas ke sini</span>
                            <span v-if="hintText" class="hcis-upload__bar-hint">{{ hintText }}</span>
                        </div>
                    </div>
                    <div class="hcis-upload__bar-right" @click.stop>
                        <HcisButton
                            v-if="camera"
                            label="Kamera"
                            variant="secondary"
                            size="xs"
                            leading-icon="camera-fill"
                            class="hcis-upload__camera-trigger"
                            :disabled="disabled"
                            @click="triggerCameraPicker"
                        />
                        <HcisButton
                            :label="camera ? 'File' : 'Pilih Berkas'"
                            variant="secondary"
                            size="xs"
                            leading-icon="folder2-open"
                            :disabled="disabled"
                            @click="triggerFilePicker"
                        />
                    </div>
                </div>

                <!-- Zone 3: Button Trigger Only (Bila layout = 'button' atau drag = false) -->
                <div v-else class="hcis-upload__trigger-box">
                    <slot>
                        <HcisButton
                            v-if="camera"
                            label="Kamera"
                            :variant="sentiment === 'brand' || sentiment === 'primary' ? 'primary' : 'secondary'"
                            size="sm"
                            leading-icon="camera-fill"
                            class="hcis-upload__camera-trigger"
                            :disabled="disabled"
                            @click="triggerCameraPicker"
                        />
                        <HcisButton
                            :label="camera ? 'File' : 'Pilih Berkas'"
                            :variant="camera ? 'secondary' : (sentiment === 'brand' || sentiment === 'primary' ? 'primary' : 'secondary')"
                            size="sm"
                            :leading-icon="camera ? 'folder2-open' : 'upload'"
                            :disabled="disabled"
                            @click="triggerFilePicker"
                        />
                    </slot>
                    <span v-if="hintText" class="hcis-upload__hint hcis-upload__hint--inline">
                        {{ hintText }}
                    </span>
                </div>
            </div>

            <!-- State Limit Reached: Ringkas Tanpa Teks redundant -->
            <div v-else class="hcis-upload__limit-banner">
                <HcisIcon name="check-circle-fill" size="xs" />
                <span>Kapasitas Unggah Penuh ({{ internalFileList.length }}/{{ limit }})</span>
            </div>
        </transition>

        <!-- Error Alert Notifikasi (Validasi Ukuran / Jenis / Limit) -->
        <transition name="hcis-upload-fade">
            <div v-if="errorMessage" class="hcis-upload__alert" role="alert">
                <HcisIcon name="exclamation-triangle-fill" size="xs" />
                <span>{{ errorMessage }}</span>
                <button type="button" class="hcis-upload__alert-close" aria-label="Tutup pesan galat" @click="errorMessage = ''">
                    <HcisIcon name="x-lg" size="xs" />
                </button>
            </div>
        </transition>

        <!-- Zone 4: File List Terpilih (Sleek Executive File Cards) -->
        <div v-if="internalFileList.length > 0" class="hcis-upload__list">
            <transition-group name="hcis-upload-list">
                <div
                    v-for="(file, index) in internalFileList"
                    :key="file.id || file.name + index"
                    class="hcis-upload__file-card"
                    :class="`hcis-upload__file-card--${file.status || 'success'}`"
                >
                    <!-- Jewel File Icon Box or Rich Image Thumbnail -->
                    <div
                        v-if="isImageFile(file) && file.url"
                        class="hcis-upload__file-thumb"
                        title="Klik untuk melihat pratinjau foto"
                        @click.stop="openPreview(file)"
                    >
                        <img
                            :src="file.url"
                            :alt="file.name"
                            class="hcis-upload__thumb-img"
                            loading="lazy"
                        />
                        <div class="hcis-upload__thumb-overlay" aria-hidden="true">
                            <HcisIcon name="zoom-in" size="2xs" />
                        </div>
                    </div>
                    <div v-else class="hcis-upload__file-icon" :class="getFileIconClass(file.name)">
                        <HcisIcon :name="getFileIcon(file.name)" size="sm" />
                    </div>

                    <!-- Detail Berkas -->
                    <div class="hcis-upload__file-info">
                        <div class="hcis-upload__file-name" :title="file.name">
                            {{ file.name }}
                        </div>
                        <div class="hcis-upload__file-meta">
                            <span v-if="file.size">{{ formatFileSize(file.size) }}</span>
                            <span v-if="file.status === 'uploading'" class="hcis-upload__status-badge hcis-upload__status-badge--uploading">
                                Mengunggah {{ file.percentage || 0 }}%
                            </span>
                            <span v-else-if="file.status === 'error'" class="hcis-upload__status-badge hcis-upload__status-badge--error">
                                Gagal Mengunggah
                            </span>
                        </div>

                        <!-- Progress Bar (Jika Sedang Mengunggah) -->
                        <div v-if="file.status === 'uploading'" class="hcis-upload__progress-bar">
                            <div
                                class="hcis-upload__progress-fill"
                                :style="{ width: `${file.percentage || 0}%` }"
                            />
                        </div>
                    </div>

                    <!-- Aksi Tombol (Pratinjau & Hapus Berkas) -->
                    <div class="hcis-upload__file-actions">
                        <button
                            type="button"
                            class="hcis-upload__file-btn hcis-upload__file-btn--preview"
                            title="Pratinjau berkas"
                            aria-label="Pratinjau berkas"
                            @click.stop="openPreview(file)"
                        >
                            <HcisIcon name="eye" size="xs" />
                        </button>

                        <button
                            type="button"
                            class="hcis-upload__file-btn hcis-upload__file-btn--remove"
                            :disabled="disabled"
                            aria-label="Hapus berkas"
                            title="Hapus berkas"
                            @click.stop="removeFile(index)"
                        >
                            <HcisIcon name="trash3" size="xs" />
                        </button>
                    </div>
                </div>
            </transition-group>
        </div>

        <!-- Lightbox Pratinjau Berkas HcisFileViewer -->
        <HcisFileViewer
            v-model="showPreview"
            :file="activePreviewFile"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import HcisButton from './HcisButton.vue';
import HcisFileViewer from './HcisFileViewer.vue';
import HcisIcon from './HcisIcon.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    fileList: { type: Array, default: () => [] },
    accept: { type: String, default: '' },
    multiple: { type: Boolean, default: false },
    limit: { type: Number, default: 0 },
    maxSize: { type: Number, default: 0 }, // Ukuran maks dalam MB
    drag: { type: Boolean, default: true },
    camera: { type: Boolean, default: false }, // Opsi kamera mobile / desktop
    layout: {
        type: String,
        default: '',
        validator: (val) => ['', 'dropzone', 'bar', 'button'].includes(val),
    },
    borderStyle: {
        type: String,
        default: 'dashed',
        validator: (val) => ['dashed', 'solid'].includes(val),
    },
    autoUpload: { type: Boolean, default: false },
    action: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    icon: { type: String, default: 'cloud-arrow-up-fill' },
    sentiment: {
        type: String,
        default: 'brand',
        validator: (val) => ['brand', 'primary', 'purple', 'violet', 'teal', 'cyan', 'success', 'warning', 'danger', 'neutral'].includes(val),
    },
    variant: {
        type: String,
        default: 'glass',
        validator: (val) => ['glass', 'default', 'flat', 'outlined'].includes(val),
    },
    pattern: {
        type: String,
        default: 'none',
        validator: (val) => ['none', 'grid', 'dots', 'crosshair', 'stripes', 'rings', 'hex'].includes(val),
    },
    customTrigger: { type: Boolean, default: false }, // Jika true, cegah pembukaan dialog berkas native OS (mis. untuk modal Library Konten)
    preventNative: { type: Boolean, default: false }, // Alias untuk customTrigger
});

const emit = defineEmits([
    'update:modelValue',
    'update:fileList',
    'change',
    'remove',
    'exceed',
    'error',
    'trigger',
    'trigger-camera',
]);

const fileInputRef = ref(null);
const cameraInputRef = ref(null);
const isDragOver = ref(false);
const errorMessage = ref('');
const showPreview = ref(false);
const activePreviewFile = ref(null);

const resolvedLayout = computed(() => {
    if (props.layout) return props.layout;
    if (!props.drag) return 'button';
    return 'dropzone';
});

const openPreview = (file) => {
    activePreviewFile.value = file;
    showPreview.value = true;

    // Jika berkas biner non-browser (ZIP, Excel, Word, dll), picu juga unduhan otomatis langsung
    const ext = file.name ? file.name.split('.').pop().toLowerCase() : '';
    const isBrowserViewable = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'ogg', 'mp3', 'wav'].includes(ext);

    if (!isBrowserViewable) {
        let downloadUrl = file.url;
        if (!downloadUrl && typeof window !== 'undefined' && file.raw && (file.raw instanceof Blob || file.raw instanceof File)) {
            try {
                downloadUrl = URL.createObjectURL(file.raw);
            } catch {
                downloadUrl = '';
            }
        }
        if (downloadUrl) {
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = file.name || 'unduhan-berkas';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }
};

// List Berkas Internal
const internalFileList = ref([]);

watch(
    [() => props.modelValue, () => props.fileList],
    () => {
        const source = props.modelValue.length > 0 ? props.modelValue : props.fileList;
        internalFileList.value = source.map((file, idx) => {
            let url = file.url || '';
            const rawObj = file.raw || file;
            if (!url && typeof window !== 'undefined' && rawObj && (rawObj instanceof Blob || rawObj instanceof File)) {
                try {
                    url = URL.createObjectURL(rawObj);
                } catch {
                    url = '';
                }
            }
            return {
                id: file.id || `file-${Date.now()}-${idx}`,
                name: file.name || (rawObj instanceof File ? rawObj.name : 'Berkas tanpa nama'),
                size: file.size || (rawObj instanceof File ? rawObj.size : 0),
                status: file.status || 'success',
                percentage: file.percentage || 100,
                raw: rawObj,
                url: url,
            };
        });
    },
    { immediate: true, deep: true }
);

const isLimitReached = computed(() => {
    return props.limit > 0 && internalFileList.value.length >= props.limit;
});

const hintText = computed(() => {
    if (props.hint) return props.hint;
    const parts = [];
    if (props.accept) parts.push(props.accept.toUpperCase().replace(/\./g, ''));
    if (props.maxSize > 0) parts.push(`Maksimal ${props.maxSize} MB`);
    if (props.limit > 0) parts.push(`Maksimal ${props.limit} berkas`);
    return parts.join(' · ');
});

const triggerFilePicker = (e) => {
    if (props.disabled) return;
    errorMessage.value = '';
    emit('trigger', e);
    if (props.customTrigger || props.preventNative) {
        return;
    }
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const triggerCameraPicker = (e) => {
    if (props.disabled) return;
    errorMessage.value = '';
    emit('trigger-camera', e);
    if (props.customTrigger || props.preventNative) {
        return;
    }
    if (cameraInputRef.value) {
        cameraInputRef.value.click();
    }
};

const handleDragOver = () => {
    if (props.disabled) return;
    isDragOver.value = true;
};

const handleDragLeave = () => {
    isDragOver.value = false;
};

const handleDrop = (e) => {
    if (props.disabled) return;
    isDragOver.value = false;
    errorMessage.value = '';
    const files = Array.from(e.dataTransfer.files || []);
    processFiles(files);
};

const handleFileSelect = (e) => {
    const files = Array.from(e.target.files || []);
    processFiles(files);
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
    if (cameraInputRef.value) {
        cameraInputRef.value.value = '';
    }
};

const processFiles = (rawFiles) => {
    if (!rawFiles || rawFiles.length === 0) return;

    // 1. Cek Limit
    if (props.limit > 0 && internalFileList.value.length + rawFiles.length > props.limit) {
        errorMessage.value = `Jumlah berkas melebihi batas maksimal (${props.limit} berkas).`;
        emit('exceed', rawFiles, internalFileList.value);
        return;
    }

    const validNewFiles = [];

    for (const file of rawFiles) {
        // 2. Cek Ukuran Maksimal
        if (props.maxSize > 0 && file.size > props.maxSize * 1024 * 1024) {
            errorMessage.value = `Berkas "${file.name}" melebihi ukuran maksimal ${props.maxSize} MB.`;
            emit('error', new Error('File size exceeded'), file, internalFileList.value);
            return;
        }

        // 3. Cek Format Ekstensi
        if (props.accept) {
            const acceptedTypes = props.accept
                .split(',')
                .map((t) => t.trim().toLowerCase());
            const fileExt = '.' + file.name.split('.').pop().toLowerCase();
            const mimeType = file.type.toLowerCase();

            const isAccepted = acceptedTypes.some((type) => {
                if (type.startsWith('.')) return type === fileExt;
                if (type.endsWith('/*')) return mimeType.startsWith(type.replace('/*', ''));
                return type === mimeType;
            });

            if (!isAccepted) {
                const isOnlyImages = acceptedTypes.every((t) => t.startsWith('image/') || t === '.jpg' || t === '.jpeg' || t === '.png' || t === '.webp');
                errorMessage.value = isOnlyImages
                    ? `Berkas "${file.name}" harus berupa gambar/foto (JPG, PNG).`
                    : `Format berkas "${file.name}" tidak didukung (${props.accept}).`;
                emit('error', new Error('Invalid file type'), file, internalFileList.value);
                return;
            }
        }

        validNewFiles.push({
            id: `file-${Date.now()}-${Math.random().toString(36).substr(2, 5)}`,
            name: file.name,
            size: file.size,
            status: props.autoUpload ? 'uploading' : 'success',
            percentage: props.autoUpload ? 0 : 100,
            raw: file,
            url: URL.createObjectURL(file),
        });
    }

    const updatedList = props.multiple
        ? [...internalFileList.value, ...validNewFiles]
        : [...validNewFiles];

    internalFileList.value = updatedList;
    emitUpdate(updatedList);
};

const removeFile = (index) => {
    if (props.disabled) return;
    const removedItem = internalFileList.value[index];
    const updatedList = internalFileList.value.filter((_, idx) => idx !== index);
    internalFileList.value = updatedList;
    emit('remove', removedItem, updatedList);
    emitUpdate(updatedList);
};

const emitUpdate = (list) => {
    emit('update:modelValue', list);
    emit('update:fileList', list);
    emit('change', list);
};

const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const getFileIcon = (fileName) => {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['pdf'].includes(ext)) return 'file-earmark-pdf-fill';
    if (['jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif', 'tiff', 'tif'].includes(ext)) return 'file-earmark-image-fill';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'file-earmark-excel-fill';
    if (['doc', 'docx'].includes(ext)) return 'file-earmark-word-fill';
    if (['zip', 'rar', '7z'].includes(ext)) return 'file-earmark-zip-fill';
    return 'file-earmark-text-fill';
};

const getFileIconClass = (fileName) => {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['pdf'].includes(ext)) return 'is-pdf';
    if (['jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif', 'tiff', 'tif'].includes(ext)) return 'is-image';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'is-excel';
    if (['doc', 'docx'].includes(ext)) return 'is-word';
    if (['zip', 'rar', '7z'].includes(ext)) return 'is-zip';
    return 'is-generic';
};

const isImageFile = (file) => {
    if (!file) return false;
    const name = file.name || (file.raw instanceof File ? file.raw.name : '');
    const ext = name ? name.split('.').pop().toLowerCase() : '';
    const isImgExt = ['jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif', 'ico'].includes(ext);
    const mime = file.raw?.type || file.type || '';
    return isImgExt || (typeof mime === 'string' && mime.startsWith('image/'));
};

defineExpose({
    triggerFilePicker,
    triggerCameraPicker,
    clearFiles: () => {
        internalFileList.value = [];
        emitUpdate([]);
    },
});
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - UPLOAD COMPONENT
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2, 8px);
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-upload--disabled {
    opacity: 0.6;
    pointer-events: none;
}

.hcis-upload__label {
    font-size: var(--hcis-font-size-sm, 0.875rem);
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    letter-spacing: -0.01em;
}

.hcis-upload__input {
    display: none !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   DROPZONE DRAGGER (Frosted Surface & Subtle Pattern Mask)
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload__dragger {
    position: relative;
    border-radius: var(--hcis-radius-xl, 16px);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.85) 0%, rgba(241, 245, 249, 0.95) 100%);
    backdrop-filter: blur(12px) saturate(160%);
    -webkit-backdrop-filter: blur(12px) saturate(160%);
    padding: 1.75rem 1.25rem;
    text-align: center;
    cursor: pointer;
    overflow: hidden;
    outline: none;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1), box-shadow 200ms ease, border-color 200ms ease, background 200ms ease;
}

/* Border Styles: Dashed (Default) vs Solid (Garis Lurus) */
.hcis-upload__dragger--dashed {
    border: 2px dashed rgba(203, 213, 225, 0.9);
}

.hcis-upload__dragger--solid {
    border: 1.5px solid rgba(203, 213, 225, 0.95);
}

.hcis-upload__dragger:focus-visible {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3), 0 4px 12px rgba(15, 23, 42, 0.08);
}

.hcis-upload__dragger:hover,
.hcis-upload__dragger.is-dragover {
    border-color: var(--hcis-brand-500, var(--hcis-indigo-500)) !important;
    background: linear-gradient(180deg, rgba(238, 242, 255, 0.9) 0%, rgba(224, 231, 255, 0.85) 100%);
    box-shadow:
        0 0 0 4px rgba(99, 102, 241, 0.15),
        0 10px 25px -4px rgba(99, 102, 241, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transform: translateY(-2px);
}

/* Sentiment Variants on Hover / Dragover */
.hcis-upload--sentiment-purple .hcis-upload__dragger:hover,
.hcis-upload--sentiment-purple .hcis-upload__dragger.is-dragover {
    border-color: var(--hcis-violet-600) !important;
    background: linear-gradient(180deg, rgba(245, 243, 255, 0.9) 0%, rgba(237, 233, 254, 0.85) 100%);
    box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15), 0 10px 25px -4px rgba(124, 58, 237, 0.2);
}

.hcis-upload--sentiment-teal .hcis-upload__dragger:hover,
.hcis-upload--sentiment-teal .hcis-upload__dragger.is-dragover {
    border-color: #0d9488 !important;
    background: linear-gradient(180deg, rgba(240, 253, 250, 0.9) 0%, rgba(204, 251, 241, 0.85) 100%);
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15), 0 10px 25px -4px rgba(13, 148, 136, 0.2);
}

.hcis-upload--sentiment-danger .hcis-upload__dragger {
    border-color: #f87171 !important;
    background: linear-gradient(180deg, rgba(254, 242, 242, 0.85) 0%, rgba(254, 226, 226, 0.95) 100%);
}

.hcis-upload--sentiment-danger .hcis-upload__dragger:hover,
.hcis-upload--sentiment-danger .hcis-upload__dragger.is-dragover {
    border-color: #ef4444 !important;
    background: linear-gradient(180deg, rgba(254, 242, 242, 0.95) 0%, rgba(254, 202, 202, 0.9) 100%);
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15), 0 10px 25px -4px rgba(239, 68, 68, 0.2);
}

.hcis-upload--sentiment-warning .hcis-upload__dragger:hover,
.hcis-upload--sentiment-warning .hcis-upload__dragger.is-dragover {
    border-color: #f59e0b !important;
    background: linear-gradient(180deg, rgba(255, 251, 235, 0.9) 0%, rgba(254, 243, 199, 0.85) 100%);
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15), 0 10px 25px -4px rgba(245, 158, 11, 0.2);
}

/* Pattern Vignette inside Dragger */
.hcis-upload__dragger .hcis-pattern-grid,
.hcis-upload__dragger .hcis-pattern-dots,
.hcis-upload__dragger .hcis-pattern-crosshair,
.hcis-upload__dragger .hcis-pattern-stripes,
.hcis-upload__dragger .hcis-pattern-rings,
.hcis-upload__dragger .hcis-pattern-hex {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    opacity: var(--hcis-pattern-opacity, 0.15) !important;
    -webkit-mask-image: radial-gradient(ellipse 90% 75% at 100% 0%, black 0%, rgba(0, 0, 0, 0.4) 38%, transparent 75%);
    mask-image: radial-gradient(ellipse 90% 75% at 100% 0%, black 0%, rgba(0, 0, 0, 0.4) 38%, transparent 75%);
}

.hcis-upload__drop-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.375rem;
}

/* ═══════════════════════════════════════════════════════════════════════════
   COMPACT HORIZONTAL UPLOAD BAR (Bar Mode)
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload__bar {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 0.5rem 0.875rem;
    min-height: 2.75rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.85) 0%, rgba(241, 245, 249, 0.95) 100%);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03), inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-upload__bar--dashed {
    border: 1.5px dashed rgba(203, 213, 225, 0.9);
}

.hcis-upload__bar:hover,
.hcis-upload__bar.is-dragover {
    border-color: var(--hcis-brand-500, var(--hcis-indigo-500)) !important;
    background: linear-gradient(180deg, rgba(238, 242, 255, 0.9) 0%, rgba(224, 231, 255, 0.85) 100%);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15), 0 4px 12px -2px rgba(99, 102, 241, 0.15);
    transform: translateY(-1px);
}

.hcis-upload--sentiment-purple .hcis-upload__bar:hover,
.hcis-upload--sentiment-purple .hcis-upload__bar.is-dragover {
    border-color: var(--hcis-violet-600) !important;
    background: linear-gradient(180deg, rgba(245, 243, 255, 0.9) 0%, rgba(237, 233, 254, 0.85) 100%);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15), 0 4px 12px -2px rgba(124, 58, 237, 0.15);
}

.hcis-upload--sentiment-teal .hcis-upload__bar:hover,
.hcis-upload--sentiment-teal .hcis-upload__bar.is-dragover {
    border-color: #0d9488 !important;
    background: linear-gradient(180deg, rgba(240, 253, 250, 0.9) 0%, rgba(204, 251, 241, 0.85) 100%);
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15), 0 4px 12px -2px rgba(13, 148, 136, 0.15);
}

.hcis-upload--sentiment-danger .hcis-upload__bar {
    border-color: #f87171 !important;
    background: linear-gradient(180deg, rgba(254, 242, 242, 0.9) 0%, rgba(254, 226, 226, 0.85) 100%);
}

.hcis-upload--sentiment-danger .hcis-upload__bar:hover,
.hcis-upload--sentiment-danger .hcis-upload__bar.is-dragover {
    border-color: #ef4444 !important;
    background: linear-gradient(180deg, rgba(254, 242, 242, 0.95) 0%, rgba(254, 202, 202, 0.9) 100%);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15), 0 4px 12px -2px rgba(239, 68, 68, 0.15);
}

.hcis-upload--sentiment-danger .hcis-upload__bar-icon {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #fee2e2 100%);
    border-color: rgba(252, 165, 165, 0.8);
    color: #dc2626;
}

.hcis-upload--sentiment-purple .hcis-upload__bar-icon {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #ede9fe 100%);
    border-color: rgba(196, 181, 253, 0.8);
    color: var(--hcis-violet-600);
}

.hcis-upload--sentiment-teal .hcis-upload__bar-icon {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #ccfbf1 100%);
    border-color: rgba(153, 246, 228, 0.8);
    color: #0d9488;
}

.hcis-upload__bar-left {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
    flex: 1;
}

.hcis-upload__bar-icon {
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-indigo-100) 100%);
    border: 1px solid rgba(165, 180, 252, 0.8);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.hcis-upload__bar-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.hcis-upload__bar-title {
    font-size: 0.8125rem;
    font-weight: 650;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-upload__bar-hint {
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-upload__bar-right {
    flex-shrink: 0;
    display: flex;
    gap: 0.375rem;
}

/* ═══════════════════════════════════════════════════════════════════════════
   JEWEL CLOUD ICON BADGE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload__icon-badge {
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-indigo-100) 100%);
    border: 1px solid rgba(165, 180, 252, 0.8);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 4px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 4px 12px -2px rgba(79, 70, 229, 0.18);
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1), box-shadow 200ms ease;
}

.hcis-upload__dragger:hover .hcis-upload__icon-badge,
.hcis-upload__dragger.is-dragover .hcis-upload__icon-badge {
    transform: scale(1.1) translateY(-2px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 1), 0 8px 18px -2px rgba(79, 70, 229, 0.28);
}

.hcis-upload--sentiment-purple .hcis-upload__icon-badge {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #ede9fe 100%);
    border-color: rgba(196, 181, 253, 0.8);
    color: var(--hcis-violet-600);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 4px 12px -2px rgba(124, 58, 237, 0.18);
}

.hcis-upload--sentiment-teal .hcis-upload__icon-badge {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #ccfbf1 100%);
    border-color: rgba(153, 246, 228, 0.8);
    color: #0d9488;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 4px 12px -2px rgba(13, 148, 136, 0.18);
}

.hcis-upload--sentiment-danger .hcis-upload__icon-badge {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #fee2e2 100%);
    border-color: rgba(252, 165, 165, 0.8);
    color: #dc2626;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 4px 12px -2px rgba(220, 38, 38, 0.18);
}

/* ═══════════════════════════════════════════════════════════════════════════
   DROP CONTENT TYPOGRAPHY & BROWSE PILL
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload__title {
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-size: 0.875rem;
    font-weight: 650;
    line-height: 1.4;
}

.hcis-upload__browse-btn {
    display: inline-block;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    font-weight: 750;
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
    transition: color 140ms ease;
}

.hcis-upload__browse-btn:hover {
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.hcis-upload__hint {
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.775rem;
    font-weight: 450;
    margin-top: 2px;
}

.hcis-upload__hint--inline {
    margin-left: 10px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   CAMERA & FILE SINGLE ACTION BUTTON (MOBILE / DESKTOP)
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload__action-buttons {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin: 0.5rem 0 0.25rem;
    z-index: 2;
    position: relative;
}

.hcis-upload__action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8125rem;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 180ms var(--hcis-ease), border-color 180ms var(--hcis-ease),
        color 180ms var(--hcis-ease), box-shadow 180ms var(--hcis-ease), transform 180ms var(--hcis-ease);
    -webkit-tap-highlight-color: transparent;
    user-select: none;
    min-height: 2.45rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.hcis-upload__action-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    pointer-events: none;
}

.hcis-upload__action-btn--single {
    background: linear-gradient(135deg, #ffffff 0%, var(--hcis-brand-50, #eef2ff) 100%);
    color: var(--hcis-brand-700, #4338ca);
    border: 1.5px solid var(--hcis-brand-200, #c7d2fe);
}

.hcis-upload__action-btn--single:hover:not(:disabled) {
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    border-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    transform: translateY(-1px);
}

.hcis-upload__action-btn--single:active:not(:disabled) {
    transform: translateY(0);
}

/* Desktop mengabaikan atribut capture; tombol Kamera hanya akan membuka
   pemilih berkas yang sama dengan tombol File. */
@media (hover: hover) and (pointer: fine) {
    .hcis-upload__action-btn--camera,
    .hcis-upload__camera-trigger {
        display: none;
    }
}

.hcis-upload__title--camera {
    margin-bottom: 0.15rem;
}

@media (max-width: 575.98px) {
    .hcis-upload__action-buttons {
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .hcis-upload__action-btn--single {
        flex: 1 1 0;
        max-width: 160px;
        min-height: 2.65rem; /* Touch friendly >= 42px */
        font-size: 0.8125rem;
    }
}

.hcis-upload__trigger-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ═══════════════════════════════════════════════════════════════════════════
   LIMIT BANNER & ERROR ALERT
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload__limit-banner {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.9) 0%, rgba(224, 231, 255, 0.6) 100%);
    border: 1px dashed rgba(165, 180, 252, 0.8);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    font-size: 0.8125rem;
    font-weight: 650;
}

.hcis-upload__limit-banner i,
.hcis-upload__limit-banner :deep(svg) {
    font-size: 0.95rem;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-upload__alert {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: var(--hcis-radius-lg, 12px);
    background-color: var(--hcis-danger-50, var(--hcis-red-50));
    border: 1px solid rgba(254, 202, 202, 0.9);
    color: var(--hcis-danger-600, var(--hcis-red-600));
    font-size: 0.8125rem;
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(220, 38, 38, 0.08);
}

.hcis-upload__alert-close {
    margin-left: auto;
    border: none;
    background: transparent;
    color: var(--hcis-danger-600, var(--hcis-red-600));
    font-size: 0.95rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px;
    border-radius: 4px;
    transition: background 140ms ease;
}

.hcis-upload__alert-close:hover {
    background-color: rgba(220, 38, 38, 0.1);
}

/* ═══════════════════════════════════════════════════════════════════════════
   EXECUTIVE FILE CARDS
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-upload__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 4px;
}

.hcis-upload__file-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-radius: var(--hcis-radius-xl, 14px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background-color: var(--hcis-surface, var(--hcis-white));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03), inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition: transform 180ms cubic-bezier(0.16, 1, 0.3, 1), box-shadow 180ms ease, border-color 180ms ease;
    will-change: transform;
}

.hcis-upload__file-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.08), inset 0 1px 0 rgba(255, 255, 255, 1);
    border-color: rgba(203, 213, 225, 0.95);
}

.hcis-upload__file-card:active {
    transform: scale(0.99) translateY(0);
}

/* Micro-Glow per File Type */
.hcis-upload__file-card:has(.is-pdf):hover {
    border-color: rgba(252, 165, 165, 0.85);
    box-shadow: 0 8px 20px -4px rgba(220, 38, 38, 0.12);
}
.hcis-upload__file-card:has(.is-image):hover {
    border-color: rgba(186, 230, 253, 0.85);
    box-shadow: 0 8px 20px -4px rgba(2, 132, 199, 0.12);
}
.hcis-upload__file-card:has(.is-excel):hover {
    border-color: rgba(167, 243, 208, 0.85);
    box-shadow: 0 8px 20px -4px rgba(5, 150, 105, 0.12);
}
.hcis-upload__file-card:has(.is-word):hover {
    border-color: rgba(165, 180, 252, 0.85);
    box-shadow: 0 8px 20px -4px rgba(79, 70, 229, 0.12);
}
.hcis-upload__file-card:has(.is-zip):hover {
    border-color: rgba(253, 230, 138, 0.85);
    box-shadow: 0 8px 20px -4px rgba(217, 119, 6, 0.12);
}

/* Rich Image Thumbnail Preview */
.hcis-upload__file-thumb {
    position: relative;
    width: 3.5rem;
    height: 3rem;
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
    flex-shrink: 0;
    background-color: var(--hcis-slate-100, #f1f5f9);
    border: 1.5px solid rgba(203, 213, 225, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hcis-upload__thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 200ms ease;
}

.hcis-upload__file-thumb:hover .hcis-upload__thumb-img {
    transform: scale(1.1);
}

.hcis-upload__thumb-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(1.5px);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    opacity: 0;
    transition: opacity 160ms ease;
}

.hcis-upload__file-thumb:hover .hcis-upload__thumb-overlay {
    opacity: 1;
}

/* Jewel File Icons */
.hcis-upload__file-icon {
    width: 2.375rem;
    height: 2.375rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 2px 6px -1px rgba(15, 23, 42, 0.06);
}

.hcis-upload__file-icon.is-pdf {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-red-100) 100%);
    border: 1px solid rgba(254, 202, 202, 0.8);
    color: var(--hcis-danger-600, var(--hcis-red-600));
}
.hcis-upload__file-icon.is-image {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-sky-100) 100%);
    border: 1px solid rgba(186, 230, 253, 0.8);
    color: var(--hcis-info-600, var(--hcis-sky-600));
}
.hcis-upload__file-icon.is-excel {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-emerald-100) 100%);
    border: 1px solid rgba(167, 243, 208, 0.8);
    color: var(--hcis-success-600, var(--hcis-emerald-600));
}
.hcis-upload__file-icon.is-word {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-indigo-100) 100%);
    border: 1px solid rgba(165, 180, 252, 0.8);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}
.hcis-upload__file-icon.is-zip {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-amber-100) 100%);
    border: 1px solid rgba(253, 230, 138, 0.8);
    color: var(--hcis-warning-600, var(--hcis-amber-600));
}
.hcis-upload__file-icon.is-generic {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-slate-100) 100%);
    border: 1px solid rgba(203, 213, 225, 0.8);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.hcis-upload__file-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.hcis-upload__file-name {
    font-size: 0.84375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.01em;
}

.hcis-upload__file-meta {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 1px;
}

.hcis-upload__status-badge--uploading { color: var(--hcis-brand-600, var(--hcis-indigo-600)); font-weight: 650; }
.hcis-upload__status-badge--error { color: var(--hcis-danger-600, var(--hcis-red-600)); font-weight: 650; }

.hcis-upload__progress-bar {
    width: 100%;
    height: 4px;
    border-radius: 999px;
    background-color: rgba(226, 232, 240, 0.8);
    margin-top: 6px;
    overflow: hidden;
}

.hcis-upload__progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-brand-500, var(--hcis-indigo-500)), var(--hcis-brand-600, var(--hcis-indigo-600)));
    border-radius: 999px;
    transition: width 200ms ease;
}

.hcis-upload__file-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.hcis-upload__file-btn {
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 50%;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.95rem;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-upload__file-btn--preview:hover {
    background-color: rgba(238, 242, 255, 0.9);
    border-color: rgba(199, 210, 254, 0.8);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    transform: scale(1.08);
}

.hcis-upload__file-btn--remove:hover:not(:disabled) {
    background-color: rgba(254, 242, 242, 0.9);
    border-color: rgba(254, 202, 202, 0.8);
    color: var(--hcis-danger-600, var(--hcis-red-600));
    transform: scale(1.08);
}

/* Transisi Vue */
.hcis-upload-fade-enter-active,
.hcis-upload-fade-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}
.hcis-upload-fade-enter-from,
.hcis-upload-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.hcis-upload-list-enter-active,
.hcis-upload-list-leave-active {
    transition: opacity 200ms ease, transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
}
.hcis-upload-list-enter-from,
.hcis-upload-list-leave-to {
    opacity: 0;
    transform: scale(0.96) translateY(-6px);
}
</style>
