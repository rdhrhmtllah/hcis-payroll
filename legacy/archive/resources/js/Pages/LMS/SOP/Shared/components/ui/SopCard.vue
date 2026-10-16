<template>
    <div class="sop-card" :class="{ 'is-loading': loading }">
        <!-- cover -->
        <div v-if="showCover" class="sop-card-cover" :style="coverStyle">
            <i class="bi" :class="icon"></i>
            <span v-if="wajib" class="sop-card-wajib"><i class="bi bi-pin-angle-fill"></i> Wajib</span>
            <span v-if="versiBadge" class="sop-card-versi">{{ versiBadge }}</span>
        </div>

        <div class="sop-card-body">
            <div class="d-flex align-items-start gap-2 mb-1">
                <h6 class="sop-card-title flex-grow-1">{{ nama }}</h6>
                <span v-if="wajib && !showCover" class="badge tag-wajib">Wajib</span>
            </div>

            <div class="d-flex flex-wrap gap-1 mb-2">
                <StatusPill v-if="kategori" :text="kategori" variant="acc" :dot="false" />
                <StatusPill v-if="prio" :text="prio.label" :warna="prio.warna" />
                <StatusPill v-if="versiBadge && !showCover" :text="versiBadge" variant="upd" :dot="false" />
            </div>

            <div v-if="$slots.meta" class="sop-card-meta">
                <slot name="meta" />
            </div>

            <ProgressBar v-if="showProgress" :value="persen" :done="done" :total="total" showLabel class="mb-3 mt-1" />

            <div class="mt-auto pt-2">
                <slot name="action" />
            </div>
        </div>

        <!-- loading overlay saat navigasi -->
        <transition name="ov-fade">
            <div v-if="loading" class="sop-card-overlay">
                <div class="spinner-border spinner-border-sm"></div>
                <span>Membuka…</span>
            </div>
        </transition>
    </div>
</template>

<script>
import StatusPill from './StatusPill.vue';
import ProgressBar from './ProgressBar.vue';
import { metaPrioritas } from '../../support/prioritas.js';

/**
 * SopCard — kartu paket premium. Dipakai di Repository & SOP Saya.
 * Slot: #meta (info tambahan), #action (tombol CTA).
 * Props baru (opsional & backward-compatible): accent (warna), icon (cover), loading.
 */
export default {
    name: 'SopCard',
    components: { StatusPill, ProgressBar },
    props: {
        nama: { type: String, required: true },
        kategori: { type: String, default: '' },
        prioritas: { type: String, default: '' },
        wajib: { type: Boolean, default: false },
        versiBadge: { type: String, default: null },
        showCover: { type: Boolean, default: false },
        showProgress: { type: Boolean, default: false },
        persen: { type: Number, default: 0 },
        done: { type: Number, default: null },
        total: { type: Number, default: null },
        accent: { type: String, default: '#6366f1' },
        icon: { type: String, default: 'bi-file-earmark-text' },
        loading: { type: Boolean, default: false },
    },
    computed: {
        prio() {
            return this.prioritas ? metaPrioritas(this.prioritas) : null;
        },
        coverStyle() {
            return {
                background: `linear-gradient(135deg, ${this.accent}14, ${this.accent}30)`,
                color: this.accent,
            };
        },
    },
};
</script>

<style scoped>
.sop-card {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
    transition:
        transform 0.22s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.22s ease,
        border-color 0.22s ease;
}
.sop-card:hover {
    transform: translateY(-2px);
    box-shadow:
        0 2px 4px rgba(15, 23, 42, 0.05),
        0 16px 34px -20px rgba(15, 23, 42, 0.3);
    border-color: rgba(99, 102, 241, 0.32);
}
.sop-card-cover {
    position: relative;
    height: 84px;
    display: grid;
    place-items: center;
    font-size: 1.7rem;
}
.sop-card-cover::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.55) 1px, transparent 1px);
    background-size: 14px 14px;
    opacity: 0.4;
}
.sop-card-wajib {
    position: absolute;
    top: 8px;
    left: 8px;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #b45309;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 999px;
    padding: 0.16rem 0.5rem;
    z-index: 1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}
.sop-card-versi {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 0.62rem;
    font-weight: 800;
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 999px;
    padding: 0.16rem 0.5rem;
    z-index: 1;
}
.sop-card-body {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 0.95rem 1rem 1rem;
}
.sop-card-title {
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.3;
    color: #1e293b;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.tag-wajib {
    background: rgba(245, 158, 11, 0.14);
    color: #b45309;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.sop-card-meta {
    font-size: 0.78rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}
.sop-card-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.78);
    backdrop-filter: blur(2px);
    display: grid;
    place-items: center;
    gap: 0.4rem;
    grid-auto-flow: row;
    color: #4f46e5;
    font-weight: 700;
    font-size: 0.82rem;
}
.ov-fade-enter-active,
.ov-fade-leave-active {
    transition: opacity 0.2s ease;
}
.ov-fade-enter-from,
.ov-fade-leave-to {
    opacity: 0;
}
</style>
