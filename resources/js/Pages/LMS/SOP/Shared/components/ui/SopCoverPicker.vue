<template>
    <div class="cover-picker">
        <div
            ref="stage"
            class="cover-stage"
            :class="{ 'has-img': !!displaySrc, drag: dragging, panning }"
            :style="!displaySrc ? defaultStyle : null"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="onDrop"
            @pointerdown="startPan"
        >
            <img
                v-if="displaySrc"
                ref="img"
                :src="displaySrc"
                alt="Cover Dokumen"
                class="cover-img"
                :style="imgStyle"
                draggable="false"
                @load="onImgLoad"
            />
            <div v-else class="cover-default">
                <i class="bi" :class="icon"></i>
                <span>{{ nama || 'Cover otomatis' }}</span>
            </div>

            <div v-if="displaySrc" class="cover-grid"></div>
            <div v-if="displaySrc && !panning" class="cover-move-hint">
                <i class="bi bi-arrows-move"></i> Seret untuk atur posisi
            </div>

            <div class="cover-overlay">
                <button type="button" class="stitch-btn secondary sm" @click.stop="pick" @pointerdown.stop>
                    <i class="bi bi-image"></i> {{ displaySrc ? 'Ganti' : 'Unggah cover' }}
                </button>
                <button
                    v-if="displaySrc"
                    type="button"
                    class="stitch-btn danger sm"
                    @click.stop="clear"
                    @pointerdown.stop
                >
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div class="cover-hint">
            <i class="bi bi-info-circle"></i>
            <template v-if="displaySrc"
                >Seret gambar untuk menggeser posisi cover (seperti di Canva) — biar crop-nya pas.</template
            >
            <template v-else>JPG/PNG, maks 2MB. Kosongkan untuk pakai cover otomatis dari kategori.</template>
        </div>
        <input ref="file" type="file" accept="image/png,image/jpeg,image/webp" hidden @change="onChange" />
    </div>
</template>

<script>
import { ElMessage } from 'element-plus';

/**
 * SopCoverPicker — pilih/preview cover paket (base64-in-JSON, pola LMS thumbnail).
 * v-model = nilai untuk dikirim sbg `Cover`: base64 (baru) | path lama | '' (hapus).
 * `coverUrl` = signed URL utk menampilkan cover lama.
 */
export default {
    name: 'SopCoverPicker',
    props: {
        modelValue: { type: String, default: '' },
        coverUrl: { type: String, default: '' },
        accent: { type: String, default: '#6366f1' },
        icon: { type: String, default: 'bi-journal-text' },
        nama: { type: String, default: '' },
        position: { type: String, default: '50% 50%' }, // titik fokus cover (object-position)
    },
    emits: ['update:modelValue', 'update:position'],
    data() {
        return {
            dragging: false,
            localBase64: '',
            panning: false,
            pan: null,
            natW: 0,
            natH: 0,
            frameW: 0,
            frameH: 0,
            zoom: 1.2,
        };
    },
    computed: {
        displaySrc() {
            if (this.localBase64) return this.localBase64;
            if (this.modelValue && this.modelValue.startsWith('data:')) return this.modelValue;
            if (this.modelValue && this.coverUrl) return this.coverUrl; // path lama + signed url
            return '';
        },
        defaultStyle() {
            return { background: `linear-gradient(135deg, ${this.accent}, ${this.accent}bb)` };
        },
        /** Ukuran & overflow gambar setelah di-"cover" + zoom (memberi ruang geser 2 arah). */
        geom() {
            if (!this.natW || !this.natH || !this.frameW || !this.frameH) return null;
            const coverScale = Math.max(this.frameW / this.natW, this.frameH / this.natH);
            const dispW = this.natW * coverScale * this.zoom;
            const dispH = this.natH * coverScale * this.zoom;
            return {
                dispW,
                dispH,
                overflowX: Math.max(0, dispW - this.frameW),
                overflowY: Math.max(0, dispH - this.frameH),
            };
        },
        imgStyle() {
            const g = this.geom;
            if (!g) return { objectPosition: this.position }; // fallback sebelum ukuran terukur
            const p = this.parsePos();
            const tx = -g.overflowX * (p.x / 100);
            const ty = -g.overflowY * (p.y / 100);
            return {
                position: 'absolute',
                left: '0',
                top: '0',
                maxWidth: 'none',
                width: `${g.dispW}px`,
                height: `${g.dispH}px`,
                objectFit: 'fill',
                transform: `translate(${tx}px, ${ty}px)`,
            };
        },
    },
    beforeUnmount() {
        window.removeEventListener('pointermove', this.onPanMove);
        window.removeEventListener('pointerup', this.endPan);
    },
    methods: {
        pick() {
            this.$refs.file.click();
        },
        parsePos() {
            const [x = '50%', y = '50%'] = String(this.position || '50% 50%').split(/\s+/);
            return { x: parseFloat(x) || 50, y: parseFloat(y) || 50 };
        },
        measureFrame() {
            const el = this.$refs.stage;
            if (el) {
                const r = el.getBoundingClientRect();
                this.frameW = r.width;
                this.frameH = r.height;
            }
        },
        onImgLoad(e) {
            this.natW = e.target.naturalWidth || 0;
            this.natH = e.target.naturalHeight || 0;
            this.measureFrame();
        },
        startPan(e) {
            if (!this.displaySrc) return;
            e.preventDefault();
            this.measureFrame();
            const g = this.geom;
            const p = this.parsePos();
            const tx0 = g ? -g.overflowX * (p.x / 100) : 0;
            const ty0 = g ? -g.overflowY * (p.y / 100) : 0;
            this.pan = {
                x0: e.clientX,
                y0: e.clientY,
                tx0,
                ty0,
                overflowX: g ? g.overflowX : 0,
                overflowY: g ? g.overflowY : 0,
            };
            this.panning = true;
            try {
                this.$refs.stage.setPointerCapture(e.pointerId);
            } catch (err) {
                /* noop */
            }
            window.addEventListener('pointermove', this.onPanMove);
            window.addEventListener('pointerup', this.endPan);
        },
        onPanMove(e) {
            if (!this.panning || !this.pan) return;
            const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
            // Geser gambar mengikuti kursor (translate), lalu turunkan kembali ke % posisi.
            const tx = clamp(this.pan.tx0 + (e.clientX - this.pan.x0), -this.pan.overflowX, 0);
            const ty = clamp(this.pan.ty0 + (e.clientY - this.pan.y0), -this.pan.overflowY, 0);
            const x = this.pan.overflowX > 0 ? Math.round((-tx / this.pan.overflowX) * 100) : 50;
            const y = this.pan.overflowY > 0 ? Math.round((-ty / this.pan.overflowY) * 100) : 50;
            this.$emit('update:position', `${x}% ${y}%`);
        },
        endPan() {
            this.panning = false;
            this.pan = null;
            window.removeEventListener('pointermove', this.onPanMove);
            window.removeEventListener('pointerup', this.endPan);
        },
        onChange(e) {
            const f = e.target.files && e.target.files[0];
            if (f) this.handle(f);
            e.target.value = '';
        },
        onDrop(e) {
            this.dragging = false;
            const f = e.dataTransfer.files && e.dataTransfer.files[0];
            if (f) this.handle(f);
        },
        handle(file) {
            if (!/^image\/(png|jpe?g|webp)$/.test(file.type)) {
                ElMessage.warning('Format harus JPG/PNG/WebP');
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                ElMessage.warning('Ukuran maks 2MB');
                return;
            }
            const reader = new FileReader();
            reader.onload = () => {
                this.localBase64 = reader.result;
                this.$emit('update:modelValue', reader.result);
            };
            reader.readAsDataURL(file);
        },
        clear() {
            this.localBase64 = '';
            this.$emit('update:modelValue', '');
        },
    },
};
</script>

<style scoped>
.cover-stage {
    position: relative;
    height: 150px;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--border, var(--hcis-slate-200));
    display: grid;
    place-items: center;
}
.cover-stage.drag {
    outline: 2px dashed var(--primary, var(--hcis-indigo-500));
    outline-offset: -4px;
}
.cover-stage.has-img {
    cursor: grab;
    touch-action: none;
    user-select: none;
}
.cover-stage.panning {
    cursor: grabbing;
}
.cover-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    user-select: none;
    -webkit-user-drag: none;
    will-change: transform;
}
.cover-grid {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.18s ease;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.5) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.5) 1px, transparent 1px);
    background-size:
        100% 33.34%,
        33.34% 100%;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
}
.cover-stage.panning .cover-grid,
.cover-stage.has-img:hover .cover-grid {
    opacity: 0.55;
}
.cover-move-hint {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    z-index: 2;
    pointer-events: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.66rem;
    font-weight: 700;
    color: #fff;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
    border-radius: 999px;
    padding: 0.18rem 0.5rem;
    opacity: 0;
    transition: opacity 0.18s ease;
}
.cover-stage.has-img:hover .cover-move-hint {
    opacity: 1;
}
.cover-default {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    color: rgba(255, 255, 255, 0.95);
    font-weight: 700;
    font-size: 0.85rem;
}
.cover-default .bi {
    font-size: 1.9rem;
}
.cover-default span {
    max-width: 80%;
    text-align: center;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
}
.cover-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: flex-end;
    justify-content: flex-end;
    gap: 0.4rem;
    padding: 0.6rem;
    background: linear-gradient(to top, rgba(15, 23, 42, 0.5), transparent 55%);
    opacity: 0;
    transition: opacity 0.18s ease;
}
.cover-stage:hover .cover-overlay,
.cover-stage:not(.has-img) .cover-overlay {
    opacity: 1;
}
.cover-hint {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    margin-top: 0.45rem;
}
.cover-hint .bi {
    color: var(--hcis-indigo-500);
}
</style>
