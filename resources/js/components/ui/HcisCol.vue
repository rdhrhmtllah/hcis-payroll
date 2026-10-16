<template>
    <component
        :is="tag"
        class="hcis-col"
        :class="colClasses"
        :style="colStyle"
    >
        <slot />
    </component>
</template>

<script setup>
import { computed, inject } from 'vue';

defineOptions({ name: 'HcisCol' });

const props = defineProps({
    span: { type: [Number, String], default: 24 },
    offset: { type: [Number, String], default: 0 },
    order: { type: [Number, String], default: null },
    xs: { type: [Number, String, Object], default: null },
    sm: { type: [Number, String, Object], default: null },
    md: { type: [Number, String, Object], default: null },
    lg: { type: [Number, String, Object], default: null },
    xl: { type: [Number, String, Object], default: null },
    xxl: { type: [Number, String, Object], default: null },
    tag: { type: String, default: 'div' },
});

const gutter = inject('hcisRowGutter', computed(() => [0, 0]));

const colClasses = computed(() => {
    const classes = [];

    // Base span & offset
    if (props.span !== undefined && props.span !== null) {
        classes.push(`hcis-col-${props.span}`);
    }
    if (props.offset) {
        classes.push(`hcis-col-offset-${props.offset}`);
    }

    // Responsive Breakpoints
    ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'].forEach((bp) => {
        const val = props[bp];
        if (val !== null && val !== undefined) {
            if (typeof val === 'number' || typeof val === 'string') {
                classes.push(`hcis-col-${bp}-${val}`);
            } else if (typeof val === 'object') {
                if (val.span !== undefined) classes.push(`hcis-col-${bp}-${val.span}`);
                if (val.offset !== undefined) classes.push(`hcis-col-${bp}-offset-${val.offset}`);
            }
        }
    });

    return classes;
});

const colStyle = computed(() => {
    const style = {};
    if (gutter.value) {
        const [gx, gy] = gutter.value;
        if (gx > 0) {
            style.paddingLeft = `${gx / 2}px`;
            style.paddingRight = `${gx / 2}px`;
        }
        if (gy > 0) {
            style.paddingTop = `${gy / 2}px`;
            style.paddingBottom = `${gy / 2}px`;
        }
    }
    if (props.order !== null && props.order !== undefined) {
        style.order = props.order;
    }
    return style;
});
</script>

<style scoped>
.hcis-col {
    position: relative;
    max-width: 100%;
    box-sizing: border-box;
    flex: 0 0 auto;
}

/* 24-Column Grid System Generator */
.hcis-col-1  { width: 4.166667%; flex: 0 0 4.166667%; }
.hcis-col-2  { width: 8.333333%; flex: 0 0 8.333333%; }
.hcis-col-3  { width: 12.5%;     flex: 0 0 12.5%; }
.hcis-col-4  { width: 16.666667%; flex: 0 0 16.666667%; }
.hcis-col-5  { width: 20.833333%; flex: 0 0 20.833333%; }
.hcis-col-6  { width: 25%;       flex: 0 0 25%; }
.hcis-col-7  { width: 29.166667%; flex: 0 0 29.166667%; }
.hcis-col-8  { width: 33.333333%; flex: 0 0 33.333333%; }
.hcis-col-9  { width: 37.5%;     flex: 0 0 37.5%; }
.hcis-col-10 { width: 41.666667%; flex: 0 0 41.666667%; }
.hcis-col-11 { width: 45.833333%; flex: 0 0 45.833333%; }
.hcis-col-12 { width: 50%;       flex: 0 0 50%; }
.hcis-col-13 { width: 54.166667%; flex: 0 0 54.166667%; }
.hcis-col-14 { width: 58.333333%; flex: 0 0 58.333333%; }
.hcis-col-15 { width: 62.5%;     flex: 0 0 62.5%; }
.hcis-col-16 { width: 66.666667%; flex: 0 0 66.666667%; }
.hcis-col-17 { width: 70.833333%; flex: 0 0 70.833333%; }
.hcis-col-18 { width: 75%;       flex: 0 0 75%; }
.hcis-col-19 { width: 79.166667%; flex: 0 0 79.166667%; }
.hcis-col-20 { width: 83.333333%; flex: 0 0 83.333333%; }
.hcis-col-21 { width: 87.5%;     flex: 0 0 87.5%; }
.hcis-col-22 { width: 91.666667%; flex: 0 0 91.666667%; }
.hcis-col-23 { width: 95.833333%; flex: 0 0 95.833333%; }
.hcis-col-24 { width: 100%;      flex: 0 0 100%; }

/* Offsets */
.hcis-col-offset-1  { margin-left: 4.166667%; }
.hcis-col-offset-2  { margin-left: 8.333333%; }
.hcis-col-offset-3  { margin-left: 12.5%; }
.hcis-col-offset-4  { margin-left: 16.666667%; }
.hcis-col-offset-6  { margin-left: 25%; }
.hcis-col-offset-8  { margin-left: 33.333333%; }
.hcis-col-offset-12 { margin-left: 50%; }

/* Responsive Media Queries */
@media (max-width: 575.98px) {
    .hcis-col-xs-24 { width: 100%; flex: 0 0 100%; }
    .hcis-col-xs-12 { width: 50%; flex: 0 0 50%; }
}

@media (min-width: 576px) {
    .hcis-col-sm-24 { width: 100%; flex: 0 0 100%; }
    .hcis-col-sm-12 { width: 50%; flex: 0 0 50%; }
    .hcis-col-sm-8  { width: 33.333333%; flex: 0 0 33.333333%; }
    .hcis-col-sm-6  { width: 25%; flex: 0 0 25%; }
}

@media (min-width: 768px) {
    .hcis-col-md-24 { width: 100%; flex: 0 0 100%; }
    .hcis-col-md-18 { width: 75%; flex: 0 0 75%; }
    .hcis-col-md-16 { width: 66.666667%; flex: 0 0 66.666667%; }
    .hcis-col-md-12 { width: 50%; flex: 0 0 50%; }
    .hcis-col-md-8  { width: 33.333333%; flex: 0 0 33.333333%; }
    .hcis-col-md-6  { width: 25%; flex: 0 0 25%; }
}

@media (min-width: 992px) {
    .hcis-col-lg-24 { width: 100%; flex: 0 0 100%; }
    .hcis-col-lg-18 { width: 75%; flex: 0 0 75%; }
    .hcis-col-lg-16 { width: 66.666667%; flex: 0 0 66.666667%; }
    .hcis-col-lg-12 { width: 50%; flex: 0 0 50%; }
    .hcis-col-lg-8  { width: 33.333333%; flex: 0 0 33.333333%; }
    .hcis-col-lg-6  { width: 25%; flex: 0 0 25%; }
    .hcis-col-lg-4  { width: 16.666667%; flex: 0 0 16.666667%; }
}

@media (min-width: 1200px) {
    .hcis-col-xl-24 { width: 100%; flex: 0 0 100%; }
    .hcis-col-xl-18 { width: 75%; flex: 0 0 75%; }
    .hcis-col-xl-16 { width: 66.666667%; flex: 0 0 66.666667%; }
    .hcis-col-xl-12 { width: 50%; flex: 0 0 50%; }
    .hcis-col-xl-8  { width: 33.333333%; flex: 0 0 33.333333%; }
    .hcis-col-xl-6  { width: 25%; flex: 0 0 25%; }
    .hcis-col-xl-4  { width: 16.666667%; flex: 0 0 16.666667%; }
}
</style>
