<template>
    <i
        class="hcis-icon"
        :class="[
            iconClassName,
            isPredefinedSize ? `hcis-icon--${size}` : null,
            isPredefinedColor ? `hcis-icon--color-${color}` : null,
            variant !== 'plain' ? `hcis-icon--variant-${variant}` : null,
            resolvedAnimationClass,
            {
                'hcis-icon--spin': spin || animation === 'spin',
                'hcis-icon--spin-reverse': spinReverse || animation === 'spin-reverse',
                'hcis-icon--pulse': pulse || animation === 'pulse',
                'hcis-icon--glow': glow || animation === 'glow',
                'hcis-icon--bounce': bounce || animation === 'bounce',
                'hcis-icon--float': float || animation === 'float',
                'hcis-icon--flip': flip || animation === 'flip' || animation === 'hourglass',
                'hcis-icon--ring': ring || animation === 'ring' || animation === 'bell',
                'hcis-icon--compass': animation === 'compass',
                'hcis-icon--stream': animation === 'stream',
                'hcis-icon--sparkle': sparkle || animation === 'sparkle',
                'hcis-icon--gear': animation === 'gear',
            }
        ]"
        :style="customStyles"
        :aria-hidden="label ? undefined : 'true'"
        :aria-label="label || undefined"
        :role="label ? 'img' : undefined"
    />
</template>

<script setup>
import { computed } from 'vue';

defineOptions({ name: 'HcisIcon' });

const props = defineProps({
    name: { type: String, required: true },
    size: {
        type: [String, Number],
        default: 'md',
    },
    color: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'plain',
        validator: (v) => ['plain', 'boxed', 'circle', 'glass'].includes(v),
    },
    animation: {
        type: String,
        default: '',
        validator: (v) => [
            '',
            'spin',
            'spin-reverse',
            'pulse',
            'glow',
            'bounce',
            'float',
            'flip',
            'hourglass',
            'ring',
            'bell',
            'compass',
            'stream',
            'sparkle',
            'gear',
        ].includes(v),
    },
    spin: { type: Boolean, default: false },
    spinReverse: { type: Boolean, default: false },
    pulse: { type: Boolean, default: false },
    glow: { type: Boolean, default: false },
    bounce: { type: Boolean, default: false },
    float: { type: Boolean, default: false },
    flip: { type: Boolean, default: false },
    ring: { type: Boolean, default: false },
    sparkle: { type: Boolean, default: false },
    label: { type: String, default: '' },
});

const PREDEFINED_SIZES = ['2xs', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl'];
const PREDEFINED_COLORS = [
    'brand',
    'primary',
    'purple',
    'violet',
    'teal',
    'cyan',
    'success',
    'warning',
    'danger',
    'info',
    'obsidian',
    'dark',
    'muted',
    'subtle',
];

const isPredefinedSize = computed(() => {
    return typeof props.size === 'string' && PREDEFINED_SIZES.includes(props.size);
});

const isPredefinedColor = computed(() => {
    return props.color && PREDEFINED_COLORS.includes(props.color);
});

const resolvedAnimationClass = computed(() => {
    if (props.animation && props.animation !== 'spin' && props.animation !== 'pulse' && props.animation !== 'glow' && props.animation !== 'bounce' && props.animation !== 'float' && props.animation !== 'spin-reverse') {
        return `hcis-icon--${props.animation}`;
    }
    return null;
});

const iconClassName = computed(() => {
    if (!props.name) return '';
    const cleanName = props.name.trim();

    // FontAwesome Support
    if (cleanName.startsWith('fa-') || cleanName.startsWith('fas ') || cleanName.startsWith('far ') || cleanName.startsWith('fab ') || cleanName.startsWith('fa ')) {
        return cleanName;
    }

    // Bootstrap Icon Support
    if (cleanName.startsWith('bi bi-')) {
        return cleanName;
    }
    if (cleanName.startsWith('bi-')) {
        return `bi ${cleanName}`;
    }
    return `bi bi-${cleanName}`;
});

const customStyles = computed(() => {
    const styles = {};

    // Custom Size (px / rem / number)
    if (!isPredefinedSize.value && props.size) {
        styles.fontSize = typeof props.size === 'number' ? `${props.size}px` : props.size;
    }

    // Custom Color (Hex / RGB / CSS Var)
    if (!isPredefinedColor.value && props.color) {
        styles.color = props.color;
    }

    return styles;
});
</script>

<style scoped>
.hcis-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    vertical-align: middle;
    flex-shrink: 0;
}

.hcis-icon::before {
    display: inline-block;
    line-height: 1;
    vertical-align: 0;
}
</style>
