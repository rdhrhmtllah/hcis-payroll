<template>
    <component
        :is="tag"
        class="hcis-row"
        :class="[
            align ? `hcis-row--align-${align}` : null,
            justify ? `hcis-row--justify-${justify}` : null,
            { 'hcis-row--nowrap': !wrap },
        ]"
        :style="rowStyle"
    >
        <slot />
    </component>
</template>

<script setup>
import { computed, provide } from 'vue';

defineOptions({ name: 'HcisRow' });

const props = defineProps({
    gutter: {
        type: [Number, String, Array],
        default: 16,
    },
    align: {
        type: String,
        default: 'top',
        validator: (v) => ['top', 'middle', 'bottom', 'stretch'].includes(v),
    },
    justify: {
        type: String,
        default: 'start',
        validator: (v) => ['start', 'end', 'center', 'space-around', 'space-between', 'space-evenly'].includes(v),
    },
    wrap: { type: Boolean, default: true },
    tag: { type: String, default: 'div' },
});

const parsedGutter = computed(() => {
    if (Array.isArray(props.gutter)) {
        return [Number(props.gutter[0]) || 0, Number(props.gutter[1]) || 0];
    }
    const val = Number(props.gutter) || 0;
    return [val, 0];
});

provide('hcisRowGutter', parsedGutter);

const rowStyle = computed(() => {
    const [gx, gy] = parsedGutter.value;
    const style = {};
    if (gx > 0) {
        style.marginLeft = `-${gx / 2}px`;
        style.marginRight = `-${gx / 2}px`;
    }
    if (gy > 0) {
        style.marginTop = `-${gy / 2}px`;
        style.marginBottom = `-${gy / 2}px`;
    }
    return style;
});
</script>

<style scoped>
.hcis-row {
    display: flex;
    flex-wrap: wrap;
    box-sizing: border-box;
}

.hcis-row--nowrap {
    flex-wrap: nowrap;
}

/* Alignments */
.hcis-row--align-top { align-items: flex-start; }
.hcis-row--align-middle { align-items: center; }
.hcis-row--align-bottom { align-items: flex-end; }
.hcis-row--align-stretch { align-items: stretch; }

/* Justifications */
.hcis-row--justify-start { justify-content: flex-start; }
.hcis-row--justify-end { justify-content: flex-end; }
.hcis-row--justify-center { justify-content: center; }
.hcis-row--justify-space-around { justify-content: space-around; }
.hcis-row--justify-space-between { justify-content: space-between; }
.hcis-row--justify-space-evenly { justify-content: space-evenly; }
</style>
