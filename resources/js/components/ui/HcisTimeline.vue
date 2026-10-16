<template>
    <div
        class="hcis-timeline-wrapper"
        :class="[
            `hcis-timeline-wrapper--sentiment-${sentiment}`,
            `hcis-timeline-wrapper--variant-${variant}`,
            `hcis-timeline-wrapper--direction-${direction}`,
            { 'hcis-timeline-wrapper--standalone': !card }
        ]"
    >
        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 1: WRAPPED INSIDE HCISCARD SUITE EKSEKUTIF
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisCard
            v-if="card"
            :title="title || 'Jejak Riwayat & Audit Trail Otorisasi'"
            :kicker="kicker || 'AUDIT TRAIL & LOG AKTIVITAS'"
            :description="description || 'Kronologi persetujuan berjenjang dan catatan perubahan data'"
            :badge="badge || `${items.length} Riwayat Aktivitas`"
            :badge-variant="badgeVariant || 'brand'"
            :badge-radar="badgeRadar"
            :icon="icon || 'clock-history'"
            :sentiment="sentiment"
            :variant="cardVariant"
            :pattern="pattern"
            :pattern-placement="patternPlacement"
        >
            <!-- Header Actions Slot -->
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>

            <!-- Timeline Core Stream -->
            <div class="hcis-timeline-stream" :class="[`hcis-timeline-stream--${direction}`, `hcis-timeline-stream--${variant}`]">
                <div
                    v-for="(item, index) in items"
                    :key="item.id || index"
                    class="hcis-timeline-node"
                    :class="[
                        getItemStatusClass(item, index),
                        {
                            'is-active': isItemActive(item, index),
                            'is-last': index === items.length - 1,
                            'is-interactive': interactive || item.clickable,
                        }
                    ]"
                    @click="onItemClick(item, index)"
                >
                    <!-- Continuous Runway Cable Line (Vertical or Horizontal) -->
                    <div v-if="index !== items.length - 1" class="hcis-timeline-cable">
                        <div
                            class="hcis-timeline-cable__fill"
                            :class="{ 'is-completed': index < resolvedActiveIndex }"
                        />
                    </div>

                    <!-- Milestone Jewel Marker -->
                    <div class="hcis-timeline-marker-box">
                        <div v-if="isItemActive(item, index)" class="hcis-timeline-beacon-halo" />

                        <span class="hcis-timeline-marker" aria-hidden="true">
                            <slot name="marker" :item="item" :index="index">
                                <HcisIcon :name="getItemIcon(item, index)" size="xs" />
                            </slot>
                        </span>
                    </div>

                    <!-- Milestone Content Card -->
                    <div class="hcis-timeline-body">
                        <div class="hcis-timeline-card">
                            <!-- Card Header: Actor, Role, Title, Time -->
                            <div class="hcis-timeline-card__head">
                                <div class="hcis-timeline-card__actor">
                                    <div class="hcis-timeline-avatar" :class="`hcis-timeline-avatar--${getItemSentiment(item)}`">
                                        <slot name="avatar" :item="item" :index="index">
                                            <img v-if="item.avatar" :src="item.avatar" :alt="item.name || item.title" />
                                            <span v-else-if="item.initials">{{ item.initials }}</span>
                                            <span v-else-if="item.avatarText">{{ item.avatarText }}</span>
                                            <span v-else>{{ getInitials(item.name || item.title) }}</span>
                                        </slot>
                                    </div>

                                    <div class="hcis-timeline-actor-info">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <strong class="hcis-timeline-card__title">{{ item.title }}</strong>
                                            <span v-if="item.status || item.role" class="hcis-timeline-status-tag" :class="`hcis-timeline-status-tag--${getItemSentiment(item)}`">
                                                <HcisIcon v-if="getItemStatusIcon(item)" :name="getItemStatusIcon(item)" size="2xs" class="me-1" />
                                                {{ item.status || item.role }}
                                            </span>
                                        </div>

                                        <div v-if="item.name || item.role" class="hcis-timeline-actor-meta">
                                            <span v-if="item.name" class="hcis-timeline-actor-name">
                                                <HcisIcon name="person" size="2xs" class="me-1" />{{ item.name }}
                                            </span>
                                            <span v-if="item.department || item.role" class="hcis-timeline-actor-dept">
                                                • {{ item.department || item.role }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Timestamp Badge -->
                                <span v-if="item.time" class="hcis-timeline-time-badge">
                                    <HcisIcon name="clock" size="2xs" class="me-1" />
                                    {{ item.time }}
                                </span>
                            </div>

                            <!-- Comment Note / Approval Description -->
                            <div v-if="item.content || item.description || $slots.itemContent" class="hcis-timeline-card__content">
                                <slot name="itemContent" :item="item" :index="index">
                                    <p class="hcis-timeline-card__text">
                                        {{ item.content || item.description }}
                                    </p>
                                </slot>
                            </div>

                            <!-- Metadata Key-Value Highlights (e.g. SLA, Skor, Nominal) -->
                            <div v-if="item.meta && item.meta.length" class="hcis-timeline-card__meta-grid">
                                <div v-for="(meta, mIdx) in item.meta" :key="mIdx" class="hcis-timeline-meta-pill">
                                    <span class="hcis-timeline-meta-label">{{ meta.label }}:</span>
                                    <span class="hcis-timeline-meta-val">{{ meta.value }}</span>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div v-if="(item.actions && item.actions.length) || $slots.itemActions" class="hcis-timeline-card__footer">
                                <slot name="itemActions" :item="item" :index="index">
                                    <template v-for="act in item.actions" :key="act.key || act.label">
                                        <HcisButton
                                            :label="act.label"
                                            :variant="act.variant || 'secondary'"
                                            size="xs"
                                            :leading-icon="act.icon"
                                            @click.stop="onActionClick(act, item, index)"
                                        />
                                    </template>
                                </slot>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Controls Slot -->
            <template v-if="$slots.footer" #footer>
                <slot name="footer" />
            </template>
        </HcisCard>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 2: STANDALONE TIMELINE (Tanpa Card Wrapper)
             ═══════════════════════════════════════════════════════════════════ -->
        <div v-else class="hcis-timeline-stream" :class="[`hcis-timeline-stream--${direction}`, `hcis-timeline-stream--${variant}`]">
            <div
                v-for="(item, index) in items"
                :key="item.id || index"
                class="hcis-timeline-node"
                :class="[
                    getItemStatusClass(item, index),
                    {
                        'is-active': isItemActive(item, index),
                        'is-last': index === items.length - 1,
                        'is-interactive': interactive || item.clickable,
                    }
                ]"
                @click="onItemClick(item, index)"
            >
                <div v-if="index !== items.length - 1" class="hcis-timeline-cable">
                    <div
                        class="hcis-timeline-cable__fill"
                        :class="{ 'is-completed': index < resolvedActiveIndex }"
                    />
                </div>

                <div class="hcis-timeline-marker-box">
                    <div v-if="isItemActive(item, index)" class="hcis-timeline-beacon-halo" />

                    <span class="hcis-timeline-marker" aria-hidden="true">
                        <slot name="marker" :item="item" :index="index">
                            <HcisIcon :name="getItemIcon(item, index)" size="xs" />
                        </slot>
                    </span>
                </div>

                <div class="hcis-timeline-body">
                    <div class="hcis-timeline-card">
                        <div class="hcis-timeline-card__head">
                            <div class="hcis-timeline-card__actor">
                                <div class="hcis-timeline-avatar" :class="`hcis-timeline-avatar--${getItemSentiment(item)}`">
                                    <slot name="avatar" :item="item" :index="index">
                                        <img v-if="item.avatar" :src="item.avatar" :alt="item.name || item.title" />
                                        <span v-else-if="item.initials">{{ item.initials }}</span>
                                        <span v-else-if="item.avatarText">{{ item.avatarText }}</span>
                                        <span v-else>{{ getInitials(item.name || item.title) }}</span>
                                    </slot>
                                </div>

                                <div class="hcis-timeline-actor-info">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <strong class="hcis-timeline-card__title">{{ item.title }}</strong>
                                        <span v-if="item.status || item.role" class="hcis-timeline-status-tag" :class="`hcis-timeline-status-tag--${getItemSentiment(item)}`">
                                            <HcisIcon v-if="getItemStatusIcon(item)" :name="getItemStatusIcon(item)" size="2xs" class="me-1" />
                                            {{ item.status || item.role }}
                                        </span>
                                    </div>

                                    <div v-if="item.name || item.role" class="hcis-timeline-actor-meta">
                                        <span v-if="item.name" class="hcis-timeline-actor-name">
                                            <HcisIcon name="person" size="2xs" class="me-1" />{{ item.name }}
                                        </span>
                                        <span v-if="item.department || item.role" class="hcis-timeline-actor-dept">
                                            • {{ item.department || item.role }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <span v-if="item.time" class="hcis-timeline-time-badge">
                                <HcisIcon name="clock" size="2xs" class="me-1" />
                                {{ item.time }}
                            </span>
                        </div>

                        <div v-if="item.content || item.description || $slots.itemContent" class="hcis-timeline-card__content">
                            <slot name="itemContent" :item="item" :index="index">
                                <p class="hcis-timeline-card__text">
                                    {{ item.content || item.description }}
                                </p>
                            </slot>
                        </div>

                        <div v-if="item.meta && item.meta.length" class="hcis-timeline-card__meta-grid">
                            <div v-for="(meta, mIdx) in item.meta" :key="mIdx" class="hcis-timeline-meta-pill">
                                <span class="hcis-timeline-meta-label">{{ meta.label }}:</span>
                                <span class="hcis-timeline-meta-val">{{ meta.value }}</span>
                            </div>
                        </div>

                        <div v-if="(item.actions && item.actions.length) || $slots.itemActions" class="hcis-timeline-card__footer">
                            <slot name="itemActions" :item="item" :index="index">
                                <template v-for="act in item.actions" :key="act.key || act.label">
                                    <HcisButton
                                        :label="act.label"
                                        :variant="act.variant || 'secondary'"
                                        size="xs"
                                        :leading-icon="act.icon"
                                        @click.stop="onActionClick(act, item, index)"
                                    />
                                </template>
                            </slot>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisCard from './HcisCard.vue';
import HcisIcon from './HcisIcon.vue';
import HcisButton from './HcisButton.vue';

defineOptions({ name: 'HcisTimeline' });

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    kicker: { type: String, default: '' },
    description: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: '' },
    badgeRadar: { type: Boolean, default: false },
    icon: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    activeStep: { type: [Number, String], default: null },
    direction: {
        type: String,
        default: 'vertical',
        validator: (v) => ['vertical', 'horizontal'].includes(v),
    },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'glass', 'flat', 'compact'].includes(v),
    },
    sentiment: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'primary', 'purple', 'violet', 'teal', 'cyan', 'success', 'warning', 'danger', 'info', 'neutral'].includes(v),
    },
    card: { type: Boolean, default: false },
    cardVariant: {
        type: String,
        default: 'glass',
        validator: (v) => ['glass', 'default', 'flat', 'raised', 'outlined', 'obsidian'].includes(v),
    },
    pattern: {
        type: String,
        default: 'none',
        validator: (v) => ['none', 'grid', 'dots', 'crosshair', 'stripes', 'rings', 'hex'].includes(v),
    },
    patternPlacement: { type: String, default: 'header' },
    interactive: { type: Boolean, default: false },
});

const emit = defineEmits(['item-click', 'action-click']);

const resolvedActiveIndex = computed(() => {
    if (props.activeStep !== null && typeof props.activeStep === 'number') {
        return props.activeStep;
    }
    const idx = props.items.findIndex((item, i) => isItemActive(item, i));
    return idx >= 0 ? idx : props.items.length;
});

function isItemActive(item, index) {
    if (props.activeStep !== null && props.activeStep !== undefined) {
        if (typeof props.activeStep === 'number') return index === props.activeStep;
        return item.id === props.activeStep || item.status === props.activeStep;
    }
    return Boolean(item.active);
}

function getItemSentiment(item) {
    if (item.sentiment) {
        const s = String(item.sentiment).toLowerCase();
        if (['success', 'danger', 'warning', 'info', 'brand', 'purple', 'violet', 'teal', 'neutral'].includes(s)) {
            return s;
        }
    }
    const status = String(item.status || item.state || '').toLowerCase();
    if (status.includes('approved') || status.includes('success') || status.includes('setuju') || status.includes('selesai') || status.includes('publish') || status.includes('aktif') || status.includes('berlaku')) return 'success';
    if (status.includes('reject') || status.includes('danger') || status.includes('tolak') || status.includes('batal')) return 'danger';
    if (status.includes('pending') || status.includes('review') || status.includes('warning') || status.includes('menunggu')) return 'warning';
    if (status.includes('draft') || status.includes('info')) return 'warning';
    if (status.includes('obsolete') || status.includes('arsip') || status.includes('lama') || status.includes('neutral')) return 'neutral';
    if (status.includes('purple') || status.includes('violet')) return 'purple';
    if (status.includes('teal')) return 'teal';
    return 'brand';
}

function getItemStatusClass(item, index) {
    const s = getItemSentiment(item);
    return `is-${s}`;
}

function getItemIcon(item, index) {
    if (item.icon) return item.icon;
    const s = getItemSentiment(item);
    if (s === 'success') return 'check-lg';
    if (s === 'danger') return 'x-lg';
    if (s === 'warning') return 'hourglass-split';
    if (s === 'info') return 'pencil';
    return 'circle-fill';
}

function getItemStatusIcon(item) {
    const s = getItemSentiment(item);
    if (s === 'success') return 'check-circle-fill';
    if (s === 'danger') return 'x-circle-fill';
    if (s === 'warning') return 'clock-fill';
    return null;
}

function getInitials(name) {
    if (!name) return 'HC';
    const parts = name.split(' ').filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
}

function onItemClick(item, index) {
    emit('item-click', { item, index });
}

function onActionClick(action, item, index) {
    emit('action-click', { action, item, index });
}
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - TIMELINE COMPONENT
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-timeline-wrapper {
    position: relative;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-timeline-stream {
    position: relative;
    width: 100%;
    display: flex;
}

/* ═══════════════════════════════════════════════════════════════════════════
   VERTICAL TIMELINE (Default Activity Stream)
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-timeline-stream--vertical {
    flex-direction: column;
    gap: 0;
}

.hcis-timeline-stream--vertical .hcis-timeline-node {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: 1.25rem;
    position: relative;
    padding-bottom: 1.75rem;
}

.hcis-timeline-stream--vertical .hcis-timeline-node.is-last {
    padding-bottom: 0;
}

/* Absolute Continuous Cable Line */
.hcis-timeline-stream--vertical .hcis-timeline-cable {
    position: absolute;
    left: 1.125rem;
    transform: translateX(-50%);
    top: 2.25rem;
    bottom: 0;
    width: 3px;
    background: rgba(226, 232, 240, 0.95);
    border-radius: 999px;
    overflow: hidden;
    z-index: 1;
}

.hcis-timeline-stream--vertical .hcis-timeline-cable__fill {
    width: 100%;
    height: 0%;
    background: linear-gradient(180deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    transition: height 350ms ease;
}

.hcis-timeline-stream--vertical .hcis-timeline-cable__fill.is-completed {
    height: 100%;
    background: var(--hcis-emerald-600);
}

/* ═══════════════════════════════════════════════════════════════════════════
   HORIZONTAL TIMELINE (Pipeline Stream)
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-timeline-stream--horizontal {
    flex-direction: row;
    align-items: flex-start;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.hcis-timeline-stream--horizontal .hcis-timeline-node {
    flex: 1;
    min-width: 260px;
    display: flex;
    flex-direction: column;
    position: relative;
    padding-right: 1.25rem;
}

.hcis-timeline-stream--horizontal .hcis-timeline-node.is-last {
    flex: 0 0 auto;
    padding-right: 0;
}

.hcis-timeline-stream--horizontal .hcis-timeline-cable {
    position: absolute;
    top: 1.125rem;
    left: 2.25rem;
    right: 0;
    height: 3px;
    background: rgba(226, 232, 240, 0.95);
    border-radius: 999px;
    overflow: hidden;
    z-index: 1;
}

.hcis-timeline-stream--horizontal .hcis-timeline-cable__fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    transition: width 350ms ease;
}

.hcis-timeline-stream--horizontal .hcis-timeline-cable__fill.is-completed {
    width: 100%;
    background: var(--hcis-emerald-600);
}

/* ═══════════════════════════════════════════════════════════════════════════
   MARKER JEWEL NODES
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-timeline-marker-box {
    position: relative;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.hcis-timeline-marker {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.125rem;
    height: 2.125rem;
    border-radius: 50%;
    background: var(--hcis-white);
    border: 2px solid rgba(203, 213, 225, 0.95);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-weight: 850;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    transition-property: var(--hcis-transition-props);
    transition-duration: 250ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.is-success .hcis-timeline-marker {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-500) 100%);
    border-color: var(--hcis-emerald-600);
    color: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(16, 185, 129, 0.3);
}

.is-danger .hcis-timeline-marker {
    background: linear-gradient(135deg, var(--hcis-red-600) 0%, var(--hcis-red-500) 100%);
    border-color: var(--hcis-red-600);
    color: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(239, 68, 68, 0.3);
}

.is-warning .hcis-timeline-marker {
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
    border-color: var(--hcis-amber-600);
    color: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
}

.is-brand .hcis-timeline-marker,
.is-info .hcis-timeline-marker {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(99, 102, 241, 0.3);
}

/* Static Halo Ring for Active Event (Zero Animation Repaint) */
.hcis-timeline-beacon-halo {
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.15);
    border: 2px solid rgba(99, 102, 241, 0.35);
    z-index: -1;
    pointer-events: none;
}

.is-success .hcis-timeline-beacon-halo {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.35);
}

/* ═══════════════════════════════════════════════════════════════════════════
   EXECUTIVE MILESTONE CARD
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-timeline-body {
    flex: 1;
    min-width: 0;
}

.hcis-timeline-card {
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 1rem 1.125rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.hcis-timeline-node:hover .hcis-timeline-card {
    border-color: rgba(165, 180, 252, 0.8);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
}

.hcis-timeline-stream--glass .hcis-timeline-card {
    background: var(--hcis-white);
}

.hcis-timeline-stream--flat .hcis-timeline-card {
    background: rgba(248, 250, 252, 0.85);
    border-color: rgba(226, 232, 240, 0.8);
    box-shadow: none;
}

.hcis-timeline-stream--compact .hcis-timeline-card {
    padding: 0.625rem 0.875rem;
    gap: 0.375rem;
}

.hcis-timeline-stream--compact .hcis-timeline-card__head {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.35rem;
}

.hcis-timeline-stream--compact .hcis-timeline-time-badge {
    align-self: flex-start;
    margin-left: 2.75rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
}

/* Card Head */
.hcis-timeline-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.hcis-timeline-card__actor {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

/* Avatar */
.hcis-timeline-avatar {
    width: 2.125rem;
    height: 2.125rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    color: var(--hcis-white);
    font-size: 0.75rem;
    font-weight: 850;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.hcis-timeline-avatar--success {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-500) 100%);
    color: var(--hcis-white);
    border: 2px solid var(--hcis-emerald-200);
    box-shadow: 0 1px 3px rgba(16, 185, 129, 0.3);
}
.hcis-timeline-avatar--danger {
    background: linear-gradient(135deg, var(--hcis-red-600) 0%, var(--hcis-red-500) 100%);
    color: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(239, 68, 68, 0.3);
}
.hcis-timeline-avatar--warning {
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
    color: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
}
.hcis-timeline-avatar--neutral {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 2px solid var(--hcis-slate-300);
    box-shadow: none;
}

.hcis-timeline-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.hcis-timeline-actor-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.hcis-timeline-card__title {
    font-size: 0.84375rem;
    font-weight: 800;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    line-height: 1.25;
}

.hcis-timeline-status-tag {
    display: inline-flex;
    align-items: center;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 0.65625rem;
    font-weight: 750;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-timeline-status-tag--success {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
    border-color: rgba(167, 243, 208, 0.8);
}

.hcis-timeline-status-tag--danger {
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
    border-color: rgba(254, 202, 202, 0.8);
}

.hcis-timeline-status-tag--warning {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border-color: rgba(253, 230, 138, 0.8);
}

.hcis-timeline-status-tag--brand,
.hcis-timeline-status-tag--info {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-color: rgba(199, 210, 254, 0.8);
}

.hcis-timeline-actor-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.71875rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.hcis-timeline-actor-name {
    font-weight: 650;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

.hcis-timeline-time-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.6875rem;
    font-weight: 650;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    white-space: nowrap;
}

/* Card Content / Note */
.hcis-timeline-card__content {
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.625rem 0.875rem;
}

.hcis-timeline-card__text {
    font-size: 0.75rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    line-height: 1.45;
    margin: 0;
}

/* Metadata Highlights */
.hcis-timeline-card__meta-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.hcis-timeline-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(241, 245, 249, 0.8);
    font-size: 0.6875rem;
}

.hcis-timeline-meta-label {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-weight: 600;
}

.hcis-timeline-meta-val {
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    font-weight: 700;
}

/* Footer Actions */
.hcis-timeline-card__footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-top: 4px;
}

/* Interactive Node */
.is-interactive {
    cursor: pointer;
}
</style>
