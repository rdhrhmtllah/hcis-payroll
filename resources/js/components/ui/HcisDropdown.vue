<template>
    <div ref="dropdownRef" class="hcis-dropdown">
        <!-- Trigger Slot -->
        <div class="hcis-dropdown__trigger" @click="toggleOpen">
            <slot name="trigger">
                <HcisButton :size="size" :variant="variant" trailing-icon="chevron-down">
                    {{ label }}
                </HcisButton>
            </slot>
        </div>

        <!-- Dropdown Menu Popup -->
        <Transition name="hcis-dropdown-pop">
            <div
                v-if="isOpen"
                class="hcis-dropdown__menu"
                :class="[`hcis-dropdown__menu--align-${align}`]"
                @click.stop
            >
                <slot>
                    <template v-for="(item, idx) in items" :key="item.id || idx">
                        <hr v-if="item.divider" class="hcis-dropdown__divider" />
                        <button
                            v-else
                            type="button"
                            class="hcis-dropdown__item"
                            :class="[
                                item.variant ? `is-${item.variant}` : '',
                                { 'is-disabled': item.disabled }
                            ]"
                            :disabled="item.disabled"
                            @click="onItemClick(item)"
                        >
                            <HcisIcon v-if="item.icon" :name="item.icon" size="xs" class="me-2" />
                            <span>{{ item.label }}</span>
                        </button>
                    </template>
                </slot>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisButton from './HcisButton.vue';

defineOptions({ name: 'HcisDropdown' });

const props = defineProps({
    label: { type: String, default: 'Pilih Aksi' },
    items: { type: Array, default: () => [] },
    variant: { type: String, default: 'secondary' },
    size: { type: String, default: 'sm' },
    align: {
        type: String,
        default: 'left',
        validator: (v) => ['left', 'right'].includes(v),
    },
});

const emit = defineEmits(['select', 'open', 'close']);

const isOpen = ref(false);
const dropdownRef = ref(null);

function toggleOpen() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) emit('open');
    else emit('close');
}

function onItemClick(item) {
    if (item.disabled) return;
    isOpen.value = false;
    emit('close');
    emit('select', item);
    if (typeof item.action === 'function') {
        item.action(item);
    }
}

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        if (isOpen.value) {
            isOpen.value = false;
            emit('close');
        }
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('click', handleClickOutside);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleClickOutside);
    }
});
</script>

<style scoped>
.hcis-dropdown {
    position: relative;
    display: inline-block;
}

.hcis-dropdown__trigger {
    cursor: pointer;
}

.hcis-dropdown__menu {
    position: absolute;
    top: calc(100% + 0.375rem);
    z-index: 100;
    min-width: 11rem;
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    box-shadow: var(--hcis-shadow-popover);
    padding: 0.375rem;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.hcis-dropdown__menu--align-left { left: 0; }
.hcis-dropdown__menu--align-right { right: 0; }

.hcis-dropdown__item {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-md);
    border: none;
    background: transparent;
    color: var(--hcis-text-strong);
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    text-align: left;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-dropdown__item:hover:not(.is-disabled) {
    background: var(--hcis-surface-soft);
    color: var(--hcis-brand-700);
}

.hcis-dropdown__item.is-danger {
    color: var(--hcis-danger-600);
}

.hcis-dropdown__item.is-danger:hover:not(.is-disabled) {
    background: var(--hcis-danger-50);
    color: var(--hcis-danger-700);
}

.hcis-dropdown__item.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.hcis-dropdown__divider {
    margin: 0.375rem 0;
    border: 0;
    border-top: 1px solid var(--hcis-border-subtle);
}

/* Transition Pop */
.hcis-dropdown-pop-enter-active,
.hcis-dropdown-pop-leave-active {
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-dropdown-pop-enter-from,
.hcis-dropdown-pop-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}
</style>
