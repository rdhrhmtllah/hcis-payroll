<template>
    <!-- Template SENGAJA statik (tanpa binding reaktif) agar Vue tak pernah mem-patch DOM Quill.
         Char count di-update via $refs (DOM langsung), bukan interpolasi reaktif. -->
    <div class="sop-rte">
        <div ref="editor" class="sop-rte-surface"></div>
        <div class="sop-rte-foot">
            <span><i class="bi bi-shield-check"></i> Konten disanitasi otomatis</span>
            <span ref="count" class="tnum">0 karakter</span>
        </div>
    </div>
</template>

<script>
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import DOMPurify from 'dompurify';

const Delta = Quill.import('delta');

/**
 * SopRichText — editor teks kaya (Quill) untuk item PARAGRAF.
 * v-model = HTML tersanitasi (disimpan ke Konten_Teks).
 * Kunci stabilitas: template statik → komponen tak re-render → toolbar Quill (bold/underline/
 * ukuran/warna) tetap terhubung. Update isi hanya lewat API Quill (setContents).
 */
export default {
    name: 'SopRichText',
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: 'Tulis konten dokumen di sini…' },
    },
    emits: ['update:modelValue'],
    data() {
        return { editor: null };
    },
    mounted() {
        // Isi awal ditaruh di container SEBELUM Quill init → dipakai Quill sebagai konten awal
        // (jalur init standar Quill: modul selection valid, tak crash & tetap bisa diedit).
        if (this.modelValue) {
            this.$refs.editor.innerHTML = this.sanitize(this.modelValue);
        }
        this.editor = new Quill(this.$refs.editor, {
            theme: 'snow',
            placeholder: this.placeholder,
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    [{ size: ['small', false, 'large', 'huge'] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['blockquote', 'link'],
                    ['clean'],
                ],
            },
        });

        this.updateCount();

        this.editor.on('text-change', (delta, oldDelta, source) => {
            this.updateCount();
            if (source !== 'user') return;
            const clean = this.editor.getText().trim() === '' ? '' : this.sanitize(this.editor.root.innerHTML);
            this.$emit('update:modelValue', clean);
        });

        // ===== BLOKIR GAMBAR (tak ada tombol gambar; cegah paste/drag agar konten dokumen bersih) =====
        // 1) buang <img> saat paste HTML (mis. paste dari Word/web)
        this.editor.clipboard.addMatcher('img', () => new Delta());
        // 2) cegah paste berkas gambar (mis. screenshot) & drag-drop gambar
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
        this.editor.root.addEventListener(
            'paste',
            (e) => blockImage(e, e.clipboardData && e.clipboardData.items),
            true,
        );
        this.editor.root.addEventListener('drop', (e) => blockImage(e, e.dataTransfer && e.dataTransfer.files), true);
    },
    beforeUnmount() {
        if (this.editor) this.editor.off('text-change');
        this.editor = null;
    },
    // TANPA watch modelValue: konten awal diset di mounted(); setelah itu editor jadi
    // sumber kebenaran isinya sendiri. Tiap item punya instance editor sendiri (remount
    // saat accordion dibuka), jadi tak perlu re-sync dari prop — dan tak ada yang menimpa editan.
    methods: {
        updateCount() {
            if (this.$refs.count && this.editor) {
                this.$refs.count.textContent = this.editor.getText().trim().length + ' karakter';
            }
        },
        sanitize(html) {
            // izinkan style (warna/latar/rata/ukuran) & class (ql-align-*/ql-size-*) — DOMPurify tetap membersihkan CSS berbahaya
            return DOMPurify.sanitize(html, {
                ALLOWED_TAGS: [
                    'p',
                    'br',
                    'b',
                    'strong',
                    'i',
                    'em',
                    'u',
                    's',
                    'h1',
                    'h2',
                    'h3',
                    'ul',
                    'ol',
                    'li',
                    'blockquote',
                    'a',
                    'span',
                ],
                ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'style'],
            });
        },
    },
};
</script>

<style scoped>
.sop-rte {
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 12px;
    background: #fff;
    position: relative;
}
.sop-rte :deep(.ql-toolbar.ql-snow) {
    border: 0;
    border-bottom: 1px solid var(--border-soft, #eef2f7);
    background: var(--surface-muted, var(--hcis-slate-50));
    border-radius: 12px 12px 0 0;
}
/* dropdown warna/ukuran/align jangan terpotong */
.sop-rte :deep(.ql-snow .ql-picker-options) {
    z-index: 30;
}
.sop-rte :deep(.ql-editor h1) {
    font-size: 1.5rem;
    font-weight: 800;
}
.sop-rte :deep(.ql-editor h2) {
    font-size: 1.2rem;
    font-weight: 800;
}
.sop-rte :deep(.ql-editor h3) {
    font-size: 1.05rem;
    font-weight: 800;
}
.sop-rte :deep(.ql-container.ql-snow) {
    border: 0;
    font-size: 0.9rem;
    font-family: inherit;
}
.sop-rte :deep(.ql-editor) {
    min-height: 150px;
    color: var(--hcis-slate-800);
}
.sop-rte :deep(.ql-editor.ql-blank::before) {
    color: var(--hcis-slate-400);
    font-style: normal;
}
.sop-rte-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0.7rem;
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    border-top: 1px solid var(--border-soft, #eef2f7);
    background: var(--surface-muted, var(--hcis-slate-50));
    border-radius: 0 0 12px 12px;
}
.sop-rte-foot .bi {
    color: var(--hcis-emerald-500);
}
</style>
