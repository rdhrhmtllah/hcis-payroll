<template>
    <!-- Group / Stacked Avatars Mode -->
    <div
        v-if="users && users.length"
        class="hcis-avatar-group"
        :class="[
            `hcis-avatar-group--${size}`,
            `hcis-avatar-group--spacing-${spacing}`,
            { 'is-interactive': interactive },
        ]"
        @click="toggleRosterPopover"
    >
        <div class="hcis-avatar-stack">
            <template v-for="(user, idx) in visibleUsers" :key="user.id || user.name || idx">
                <div
                    class="hcis-avatar-stack-item"
                    :style="{ zIndex: visibleUsers.length - idx }"
                >
                    <HcisAvatar
                        :name="user.name || 'User'"
                        :src="user.src || user.avatar || ''"
                        :size="size"
                        :shape="shape"
                        :ring="ring"
                        :status="user.status || ''"
                    />
                </div>
            </template>

            <!-- Overflow Count Pill (+N) -->
            <div
                v-if="overflowCount > 0"
                class="hcis-avatar-stack-item hcis-avatar-overflow-item"
                :style="{ zIndex: 0 }"
                :title="`${overflowCount} Anggota Lainnya`"
            >
                <div class="hcis-avatar-overflow-badge" :class="`hcis-avatar--${size}`">
                    <span>+{{ overflowCount }}</span>
                </div>
            </div>
        </div>

        <!-- Glassmorphic Full Roster Popover -->
        <Transition name="hcis-popover-fade">
            <div
                v-if="showRoster && interactive"
                class="hcis-avatar-roster-popover"
                @click.stop
            >
                <div class="hcis-avatar-roster__header">
                    <div class="d-flex align-items-center gap-2">
                        <HcisIcon name="people-fill" size="xs" class="text-indigo-600" />
                        <strong class="hcis-avatar-roster__title">Tim Anggota & Pejabat ({{ users.length }})</strong>
                    </div>
                    <button
                        type="button"
                        class="hcis-avatar-roster__close"
                        @click.stop="showRoster = false"
                    >
                        <HcisIcon name="x" size="xs" />
                    </button>
                </div>

                <div class="hcis-avatar-roster__body">
                    <div
                        v-for="(user, i) in users"
                        :key="user.id || i"
                        class="hcis-avatar-roster__item"
                        @click="onMemberClick(user, i)"
                    >
                        <HcisAvatar
                            :name="user.name || 'User'"
                            :src="user.src || user.avatar || ''"
                            size="sm"
                            :shape="shape"
                            :status="user.status || ''"
                        />
                        <div class="hcis-avatar-roster__meta">
                            <strong class="hcis-avatar-roster__name">{{ user.name }}</strong>
                            <small class="hcis-avatar-roster__subtext">
                                {{ user.role || user.department || 'Anggota Tim' }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>

    <!-- Single Avatar Mode -->
    <span
        v-else
        class="hcis-avatar-container"
        :class="[
            `hcis-avatar--${size}`,
            `hcis-avatar--shape-${shape}`,
            ring ? `hcis-avatar--ring-${ring}` : null,
            {
                'has-status': Boolean(status),
                'is-interactive': interactive,
            }
        ]"
        :title="name"
    >
        <span
            class="hcis-avatar"
            :style="avatarBgStyle"
        >
            <img v-if="src && !gagal" :src="src" :alt="name" @error="gagal = true" />
            <span v-else class="hcis-avatar__initials" aria-hidden="true">{{ inisial }}</span>
        </span>

        <!-- Status Indicator Dot -->
        <span
            v-if="status"
            class="hcis-avatar__status-dot"
            :class="`hcis-avatar__status--${status}`"
            :title="status"
        >
            <span v-if="status === 'online'" class="hcis-avatar__status-pulse" />
        </span>
    </span>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisAvatar' });

const props = defineProps({
    name: { type: String, default: '' },
    src: { type: String, default: '' },
    users: { type: Array, default: () => [] },
    max: { type: Number, default: 4 },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl', '2xl'].includes(v),
    },
    spacing: {
        type: String,
        default: 'normal',
        validator: (v) => ['tight', 'normal', 'loose'].includes(v),
    },
    shape: {
        type: String,
        default: 'circle',
        validator: (v) => ['circle', 'rounded', 'square'].includes(v),
    },
    status: {
        type: String,
        default: '',
        validator: (v) => ['', 'online', 'offline', 'busy', 'away'].includes(v),
    },
    ring: {
        type: String,
        default: '',
        validator: (v) => ['', 'white', 'brand', 'success', 'warning', 'danger', 'gradient'].includes(v),
    },
    interactive: { type: Boolean, default: true },
    autoColor: { type: Boolean, default: true },
});

const emit = defineEmits(['member-click', 'group-click']);

const gagal = ref(false);
const showRoster = ref(false);

/* Group mode computed */
const visibleUsers = computed(() => (props.users ? props.users.slice(0, props.max) : []));
const overflowCount = computed(() => (props.users ? Math.max(0, props.users.length - props.max) : 0));

function toggleRosterPopover() {
    if (props.interactive === false) return;
    showRoster.value = !showRoster.value;
    emit('group-click', { users: props.users, count: props.users.length });
}

function onMemberClick(user, index) {
    showRoster.value = false;
    emit('member-click', { user, index });
}

function handleGlobalClick(e) {
    if (!e.target.closest('.hcis-avatar-group')) {
        showRoster.value = false;
    }
}

/* Single mode computed */
const inisial = computed(() => {
    if (!props.name) return '?';
    const kata = props.name.trim().split(/\s+/).filter(Boolean);
    if (!kata.length) return '?';
    if (kata.length === 1) return kata[0][0].toUpperCase();
    return (kata[0][0] + kata[kata.length - 1][0]).toUpperCase();
});

const MESH_GRADIENTS = [
    'linear-gradient(135deg, #10b981 0%, #06b6d4 100%)',
    'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)',
    'linear-gradient(135deg, #f59e0b 0%, #f43f5e 100%)',
    'linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%)',
    'linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%)',
    'linear-gradient(135deg, #f43f5e 0%, #fb923c 100%)',
    'linear-gradient(135deg, #2563eb 0%, #4f46e5 100%)',
    'linear-gradient(135deg, #059669 0%, #84cc16 100%)',
];

const avatarBgStyle = computed(() => {
    if (props.src && !gagal.value) return {};
    if (!props.autoColor || !props.name) return {};

    let hash = 0;
    for (let i = 0; i < props.name.length; i++) {
        hash = props.name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % MESH_GRADIENTS.length;
    return { background: MESH_GRADIENTS[index] };
});

onMounted(() => document.addEventListener('click', handleGlobalClick));
onUnmounted(() => document.removeEventListener('click', handleGlobalClick));
</script>

<style scoped>
/* Group Mode Styles */
.hcis-avatar-group {
    position: relative;
    display: inline-flex;
    align-items: center;
    box-sizing: border-box;
}

.hcis-avatar-group.is-interactive {
    cursor: pointer;
}

.hcis-avatar-stack {
    display: flex;
    align-items: center;
}

.hcis-avatar-stack-item {
    position: relative;
    transition: transform 180ms ease, z-index 180ms ease;
}

.hcis-avatar-group--spacing-tight .hcis-avatar-stack-item + .hcis-avatar-stack-item {
    margin-left: -0.85rem;
}

.hcis-avatar-group--spacing-normal .hcis-avatar-stack-item + .hcis-avatar-stack-item {
    margin-left: -0.625rem;
}

.hcis-avatar-group--spacing-loose .hcis-avatar-stack-item + .hcis-avatar-stack-item {
    margin-left: -0.375rem;
}

.hcis-avatar-group.is-interactive .hcis-avatar-stack-item:hover {
    transform: translateY(-3px) scale(1.08);
    z-index: 50 !important;
}

.hcis-avatar-overflow-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-brand-600) 0%, var(--hcis-brand-700) 100%);
    color: var(--hcis-white);
    font-weight: 800;
    border: 2px solid var(--hcis-white);
    box-shadow: 0 2px 6px rgb(var(--hcis-slate-900-rgb) / 0.12);
    box-sizing: border-box;
}

.hcis-avatar-overflow-badge.hcis-avatar--xs { width: 1.5rem; height: 1.5rem; font-size: 0.6rem; }
.hcis-avatar-overflow-badge.hcis-avatar--sm { width: 2rem; height: 2rem; font-size: 0.7rem; }
.hcis-avatar-overflow-badge.hcis-avatar--md { width: 2.5rem; height: 2.5rem; font-size: 0.8rem; }
.hcis-avatar-overflow-badge.hcis-avatar--lg { width: 3rem; height: 3rem; font-size: 0.9rem; }

.hcis-avatar-roster-popover {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    width: 16rem;
    max-height: 18rem;
    background: rgb(var(--hcis-white-rgb) / 0.95);
    backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.25);
    border-radius: 14px;
    padding: 0.75rem;
    box-shadow: 0 16px 36px -8px rgb(var(--hcis-slate-900-rgb) / 0.2);
    z-index: 100;
    overflow-y: auto;
}

.hcis-avatar-roster__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.hcis-avatar-roster__title {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.hcis-avatar-roster__close {
    border: none;
    background: transparent;
    color: var(--hcis-slate-400);
    cursor: pointer;
    padding: 2px;
    border-radius: 4px;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: ease;
}

.hcis-avatar-roster__close:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
}

.hcis-avatar-roster__body {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.hcis-avatar-roster__item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.375rem 0.5rem;
    border-radius: 8px;
    transition: background 120ms ease;
    cursor: pointer;
}

.hcis-avatar-roster__item:hover {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.hcis-avatar-roster__meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.hcis-avatar-roster__name {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-avatar-roster__subtext {
    font-size: 0.675rem;
    color: var(--hcis-slate-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-popover-fade-enter-active,
.hcis-popover-fade-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.hcis-popover-fade-enter-from,
.hcis-popover-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
