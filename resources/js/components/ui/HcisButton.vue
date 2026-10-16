<template>
    <component
        :is="hrefAman ? 'a' : 'button'"
        v-if="isRenderable"
        :type="hrefAman ? undefined : type"
        :href="hrefAman || undefined"
        class="hcis-btn"
        :class="buttonClasses"
        :disabled="hrefAman ? undefined : (disabled || loading)"
        :aria-disabled="hrefAman && (disabled || loading) ? 'true' : undefined"
        :aria-busy="loading ? 'true' : undefined"
        v-bind="$attrs"
    >
        <!-- Loading Indicator: Laser Spinner or Custom Animated Icon -->
        <template v-if="loading">
            <HcisIcon
                v-if="loadingIcon"
                :name="loadingIcon"
                :animation="loadingAnimation || 'spin'"
                class="hcis-btn__icon hcis-btn__icon--loading"
            />
            <span v-else class="hcis-btn__spinner" aria-hidden="true" />
        </template>

        <!-- Leading Icon Slot / Prop -->
        <slot v-else name="leading">
            <HcisIcon
                v-if="resolvedLeadingIcon"
                :name="resolvedLeadingIcon"
                :animation="leadingAnimation"
                class="hcis-btn__icon hcis-btn__icon--leading"
            />
        </slot>

        <!-- Button Content / Label -->
        <span v-if="!resolvedIconOnly" class="hcis-btn__text">
            <slot>{{ label }}</slot>
        </span>
        <slot v-else />

        <!-- Trailing Icon Slot / Prop -->
        <slot v-if="!loading && !resolvedIconOnly" name="trailing">
            <HcisIcon
                v-if="trailingIcon"
                :name="trailingIcon"
                :animation="trailingAnimation"
                class="hcis-btn__icon hcis-btn__icon--trailing"
            />
        </slot>
    </component>
</template>

<script setup>
import { computed, useAttrs, useSlots } from 'vue';
import HcisIcon from './HcisIcon.vue';
import { urlAman } from './urlAman';

defineOptions({ name: 'HcisButton', inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: '' },
    icon: { type: String, default: '' },
    type: { type: String, default: 'button' },
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => [
            'primary',
            'secondary',
            'obsidian',
            'outline',
            'ghost',
            'glass',
            'success',
            'warning',
            'danger',
            'danger-quiet',
            'danger-ghost',
        ].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
    },
    href: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    loadingIcon: { type: String, default: '' },
    loadingAnimation: { type: String, default: '' },
    block: { type: Boolean, default: false },
    pill: { type: Boolean, default: false },
    iconOnly: { type: Boolean, default: false },
    leadingIcon: { type: String, default: '' },
    leadingAnimation: { type: String, default: '' },
    trailingIcon: { type: String, default: '' },
    trailingAnimation: { type: String, default: '' },
});

const attrs = useAttrs();
const slots = useSlots();

const hrefAman = computed(() => urlAman(props.href));

const resolvedLeadingIcon = computed(() => props.leadingIcon || props.icon);

const hasSlotContent = computed(() => {
    if (!slots.default) return false;
    const defaultNodes = slots.default();
    return Boolean(
        defaultNodes &&
        defaultNodes.some((node) => {
            if (typeof node.children === 'string') return node.children.trim().length > 0;
            if (Array.isArray(node.children)) return node.children.length > 0;
            return Boolean(node.type);
        })
    );
});

const resolvedIconOnly = computed(() => props.iconOnly || (!props.label && !attrs.label && !hasSlotContent.value && Boolean(resolvedLeadingIcon.value)));

const isRenderable = computed(() => {
    if (resolvedIconOnly.value && !attrs['aria-label'] && !attrs.title && !props.label) {
        console.warn('[HcisButton] iconOnly wajib disertai aria-label atau title untuk aksesibilitas.');
    }
    return true;
});

const normalizedVariant = computed(() => {
    if (props.variant === 'outline') return 'secondary';
    if (props.variant === 'danger-ghost') return 'danger-quiet';
    return props.variant;
});

const buttonClasses = computed(() => [
    `hcis-btn--${normalizedVariant.value}`,
    `hcis-btn--${props.size}`,
    {
        'hcis-btn--block': props.block,
        'hcis-btn--pill': props.pill,
        'hcis-btn--icon': resolvedIconOnly.value,
        'is-loading': props.loading,
        'is-disabled': props.disabled,
    },
]);
</script>
