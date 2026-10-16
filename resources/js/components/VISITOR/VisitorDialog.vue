<template>
    <HcisDialog
        :model-value="show"
        :title="title"
        :description="subtitle"
        :size="normalizedSize"
        :show-close="showHeader"
        :close-on-overlay="closeOnBackdrop"
        :close-on-esc="closeOnEscape"
        :loading="loading"
        sticky-footer
        esc-hint
        sentiment="primary"
        @update:model-value="onVisibilityChange"
        @close="emitClose"
    >
        <slot name="body">
            <slot />
        </slot>

        <template v-if="$slots.footer || showFooter" #footer>
            <slot name="footer">
                <HcisButton v-if="showCancel" variant="secondary" @click="emitClose">
                    {{ cancelText }}
                </HcisButton>
                <HcisButton v-if="showConfirm" :loading="loading" @click="$emit('confirm')">
                    {{ confirmText }}
                </HcisButton>
            </slot>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed } from 'vue';
import { HcisButton, HcisDialog } from '@/components/ui';

defineOptions({ name: 'VisitorDialog' });

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    size: { type: String, default: 'medium' },
    showHeader: { type: Boolean, default: true },
    showFooter: { type: Boolean, default: true },
    showCancel: { type: Boolean, default: false },
    showConfirm: { type: Boolean, default: false },
    cancelText: { type: String, default: 'Batal' },
    confirmText: { type: String, default: 'Simpan' },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEscape: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:show', 'close', 'confirm']);

const normalizedSize = computed(() => ({ small: 'sm', medium: 'md', large: 'xl' })[props.size] || props.size);

function onVisibilityChange(value) {
    emit('update:show', value);
}

function emitClose() {
    emit('update:show', false);
    emit('close');
}
</script>
