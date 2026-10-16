<template>
    <div
        class="hcis-rich-text"
        :class="[
            {
                'is-disabled': disabled,
                'is-focused': isFocused,
                'has-error': !!error,
            },
        ]"
    >
        <!-- Header / Label Row -->
        <div v-if="label || $slots.label || $slots.corner" class="hcis-rich-text__header">
            <label v-if="label || $slots.label" class="hcis-rich-text__label" :for="id">
                <slot name="label">{{ label }}</slot>
                <span v-if="required" class="hcis-rich-text__required" aria-hidden="true">*</span>
            </label>
            <slot name="corner" />
        </div>

        <!-- Editor Shell Container -->
        <div class="hcis-rich-text__shell">
            <!-- Quill Editor Container Surface (Kept static for Quill DOM mutations) -->
            <div ref="editorRef" :id="id" class="hcis-rich-text__surface" :style="surfaceStyles"></div>

            <!-- Footer Bar with Sanitation Status & Word/Char Counter -->
            <div v-if="showFooter" class="hcis-rich-text__footer">
                <div class="hcis-rich-text__footer-left">
                    <span class="hcis-rich-text__sanitize-tag">
                        <HcisIcon name="shield-check" size="2xs" />
                        <span>Format HTML Tersanitasi</span>
                    </span>
                    <span v-if="hint && !error" class="hcis-rich-text__hint">{{ hint }}</span>
                </div>

                <div v-if="showCount" class="hcis-rich-text__footer-right">
                    <span class="hcis-rich-text__counter">{{ charCount }} karakter · {{ wordCount }} kata</span>
                </div>
            </div>
        </div>

        <!-- Error Message Row -->
        <p v-if="error" class="hcis-rich-text__error" role="alert">
            <HcisIcon name="exclamation-circle-fill" size="2xs" />
            <span>{{ error }}</span>
        </p>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import DOMPurify from 'dompurify';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisRichText', inheritAttrs: false });

const Delta = Quill.import('delta');

const props = defineProps({
    id: {
        type: String,
        default: () => `hcis-rt-${Math.random().toString(36).slice(2, 9)}`,
    },
    label: { type: String, default: '' },
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Tuliskan catatan di sini…' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    minHeight: { type: String, default: '130px' },
    maxHeight: { type: String, default: '320px' },
    toolbar: {
        type: [Array, String],
        default: () => [
            [{ header: [3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ color: [] }, { background: [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote'],
            ['clean'],
        ],
    },
    showFooter: { type: Boolean, default: true },
    showCount: { type: Boolean, default: true },
    allowImages: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'focus', 'blur', 'change']);

const editorRef = ref(null);
const isFocused = ref(false);
const charCount = ref(0);
const wordCount = ref(0);

let quillInstance = null;

const surfaceStyles = computed(() => ({
    minHeight: props.minHeight,
    maxHeight: props.maxHeight,
}));

function sanitizeHtml(html) {
    if (!html) return '';
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: [
            'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li',
            'blockquote', 'a', 'span', 'code', 'pre',
        ],
        ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'style', 'color'],
    });
}

function updateCounts() {
    if (!quillInstance) {
        charCount.value = 0;
        wordCount.value = 0;
        return;
    }

    const rawText = quillInstance.getText().trim();
    charCount.value = rawText.length;
    wordCount.value = rawText.length > 0 ? rawText.split(/\s+/).filter(Boolean).length : 0;
}

onMounted(() => {
    if (!editorRef.value) return;

    if (props.modelValue) {
        editorRef.value.innerHTML = sanitizeHtml(props.modelValue);
    }

    quillInstance = new Quill(editorRef.value, {
        theme: 'snow',
        placeholder: props.placeholder,
        readOnly: props.disabled,
        modules: {
            toolbar: props.toolbar,
        },
    });

    updateCounts();

    quillInstance.on('text-change', (delta, oldDelta, source) => {
        updateCounts();

        if (source !== 'user') return;

        const text = quillInstance.getText().trim();
        const cleanHtml = text === '' ? '' : sanitizeHtml(quillInstance.root.innerHTML);
        emit('update:modelValue', cleanHtml);
        emit('change', cleanHtml);
    });

    quillInstance.root.addEventListener('focus', (e) => {
        isFocused.value = true;
        emit('focus', e);
    });

    quillInstance.root.addEventListener('blur', (e) => {
        isFocused.value = false;
        emit('blur', e);
    });

    if (!props.allowImages) {
        // Block image paste & drop
        quillInstance.clipboard.addMatcher('img', () => new Delta());

        const blockImage = (e, list) => {
            for (const it of list || []) {
                if (it && it.type && it.type.indexOf('image/') === 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    return true;
                }
            }
            return false;
        };

        quillInstance.root.addEventListener('paste', (e) => blockImage(e, e.clipboardData?.items), true);
        quillInstance.root.addEventListener('drop', (e) => blockImage(e, e.dataTransfer?.files), true);
    }
});

watch(
    () => props.modelValue,
    (newVal) => {
        if (!quillInstance) return;

        const currentHtml = quillInstance.root.innerHTML;
        const sanitizedNew = sanitizeHtml(newVal || '');

        // Sync when external change occurs (e.g. participant switch, reset)
        if (sanitizedNew !== currentHtml && !isFocused.value) {
            if (!sanitizedNew) {
                quillInstance.setText('');
            } else {
                quillInstance.root.innerHTML = sanitizedNew;
            }
            updateCounts();
        }
    },
);

watch(
    () => props.disabled,
    (newDisabled) => {
        if (quillInstance) {
            quillInstance.enable(!newDisabled);
        }
    },
);

onBeforeUnmount(() => {
    if (quillInstance) {
        quillInstance.off('text-change');
        quillInstance = null;
    }
});
</script>

<style scoped>
.hcis-rich-text {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    width: 100%;
    min-width: 0;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-rich-text__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.hcis-rich-text__label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.hcis-rich-text__required {
    color: var(--hcis-danger-500, #ef4444);
    font-weight: 800;
}

.hcis-rich-text__shell {
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 180ms ease;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.hcis-rich-text:hover:not(.is-disabled) .hcis-rich-text__shell {
    border-color: var(--hcis-brand-400, #818cf8);
}

.hcis-rich-text.is-focused .hcis-rich-text__shell {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15), 0 1px 2px rgba(99, 102, 241, 0.1);
}

.hcis-rich-text.has-error .hcis-rich-text__shell {
    border-color: var(--hcis-danger-500, #ef4444);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
}

.hcis-rich-text.is-disabled .hcis-rich-text__shell {
    background: var(--hcis-surface-subtle, #f8fafc);
    border-color: var(--hcis-border-subtle, #e2e8f0);
    opacity: 0.75;
    cursor: not-allowed;
}

/* Quill Toolbar Customization */
.hcis-rich-text :deep(.ql-toolbar.ql-snow) {
    border: none;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 0.45rem 0.65rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.2rem;
}

.hcis-rich-text :deep(.ql-snow .ql-formats) {
    margin-right: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
}

.hcis-rich-text :deep(.ql-snow .ql-stroke) {
    stroke: #475569;
    stroke-width: 1.75;
}

.hcis-rich-text :deep(.ql-snow .ql-fill) {
    fill: #475569;
}

.hcis-rich-text :deep(.ql-snow button:hover .ql-stroke),
.hcis-rich-text :deep(.ql-snow .ql-picker:hover .ql-stroke),
.hcis-rich-text :deep(.ql-snow button.ql-active .ql-stroke) {
    stroke: #4f46e5;
}

.hcis-rich-text :deep(.ql-snow button:hover .ql-fill),
.hcis-rich-text :deep(.ql-snow .ql-picker:hover .ql-fill),
.hcis-rich-text :deep(.ql-snow button.ql-active .ql-fill) {
    fill: #4f46e5;
}

.hcis-rich-text :deep(.ql-snow button) {
    border-radius: 6px;
    padding: 3px 5px;
    height: 26px;
    width: 28px;
    transition: background-color 120ms ease;
}

.hcis-rich-text :deep(.ql-snow button:hover),
.hcis-rich-text :deep(.ql-snow button.ql-active) {
    background-color: #eef2ff;
}

.hcis-rich-text :deep(.ql-snow .ql-picker) {
    height: 26px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
}

.hcis-rich-text :deep(.ql-snow .ql-picker-label) {
    border-radius: 6px;
    padding: 2px 6px;
    border: 1px solid transparent;
}

.hcis-rich-text :deep(.ql-snow .ql-picker-label:hover) {
    background-color: #f1f5f9;
}

.hcis-rich-text :deep(.ql-snow .ql-picker.ql-expanded .ql-picker-label) {
    border-color: #cbd5e1;
    background-color: #ffffff;
}

.hcis-rich-text :deep(.ql-snow .ql-picker-options) {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    background: #ffffff;
    padding: 0.35rem;
    z-index: 50;
}

/* Quill Surface & Editor Content */
.hcis-rich-text :deep(.ql-container.ql-snow) {
    border: none;
    font-family: inherit;
    font-size: 0.84rem;
    line-height: 1.6;
}

.hcis-rich-text :deep(.ql-editor) {
    padding: 0.75rem 0.9rem;
    color: var(--hcis-text-strong, #0f172a);
    overflow-y: auto;
}

.hcis-rich-text :deep(.ql-editor.ql-blank::before) {
    color: var(--hcis-text-muted, #94a3b8);
    font-style: normal;
    font-weight: 450;
    left: 0.9rem;
    right: 0.9rem;
}

.hcis-rich-text :deep(.ql-editor h3) {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    margin: 0.5rem 0 0.3rem;
}

.hcis-rich-text :deep(.ql-editor h4) {
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #1e293b);
    margin: 0.4rem 0 0.25rem;
}

.hcis-rich-text :deep(.ql-editor p) {
    margin-bottom: 0.35rem;
}

.hcis-rich-text :deep(.ql-editor ul),
.hcis-rich-text :deep(.ql-editor ol) {
    padding-left: 1.25rem;
    margin-bottom: 0.35rem;
}

.hcis-rich-text :deep(.ql-editor li) {
    margin-bottom: 0.15rem;
}

.hcis-rich-text :deep(.ql-editor blockquote) {
    border-left: 3px solid #6366f1;
    background: #f8fafc;
    padding: 0.4rem 0.75rem;
    margin: 0.4rem 0;
    color: #334155;
    border-radius: 0 6px 6px 0;
}

/* Footer Status & Counter Bar */
.hcis-rich-text__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.35rem 0.75rem;
    background: var(--hcis-surface-subtle, #f8fafc);
    border-top: 1px solid var(--hcis-border-subtle, #e2e8f0);
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
    user-select: none;
}

.hcis-rich-text__footer-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.hcis-rich-text__sanitize-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 550;
}

.hcis-rich-text__sanitize-tag :deep(.bi-shield-check) {
    color: #10b981;
}

.hcis-rich-text__hint {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}

.hcis-rich-text__counter {
    font-weight: 650;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-text-muted, #64748b);
}

.hcis-rich-text__error {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin: 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-danger-500, #ef4444);
}
</style>
