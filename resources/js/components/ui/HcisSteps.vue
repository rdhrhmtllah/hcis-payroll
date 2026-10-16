<template>
    <div
        class="hcis-steps-wrapper"
        :class="[
            `hcis-steps-wrapper--sentiment-${sentiment}`,
            `hcis-steps-wrapper--variant-${variant}`,
            { 'hcis-steps-wrapper--standalone': !card }
        ]"
    >
        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 1: TERBUNGKUS HCISCARD SUITE EKSEKUTIF
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisCard
            v-if="card"
            :title="title"
            :kicker="kicker"
            :description="description"
            :badge="badge || `Tahap ${current + 1} dari ${steps.length}`"
            :badge-variant="badgeVariant || 'brand'"
            :badge-radar="badgeRadar"
            :icon="icon || 'diagram-3'"
            :sentiment="sentiment"
            :variant="cardVariant"
            :pattern="pattern"
            :pattern-placement="patternPlacement"
        >
            <!-- Header Actions Slot -->
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>

            <!-- Stepper Body -->
            <div class="hcis-steps__inner-container">
                <!-- VARIANT: CARDS GRID -->
                <div v-if="variant === 'cards'" class="hcis-steps__cards-grid">
                    <div
                        v-for="(step, index) in steps"
                        :key="step.label || step.title || index"
                        class="hcis-steps__card-item"
                        :class="[
                            `is-${keadaan(step, index)}`,
                            `hcis-steps__card-item--${sentiment}`,
                            {
                                'is-interactive': clickable && !step.disabled && (allowAllClickable || index <= current + 1),
                                'is-disabled': step.disabled
                            }
                        ]"
                        :aria-current="keadaan(step, index) === 'current' ? 'step' : undefined"
                        :tabindex="bisaDiklik(step, index) ? 0 : undefined"
                        :role="bisaDiklik(step, index) ? 'button' : undefined"
                        :aria-disabled="clickable && !bisaDiklik(step, index) ? 'true' : undefined"
                        @click="onClickStep(step, index)"
                        @keydown.enter.prevent="onClickStep(step, index)"
                        @keydown.space.prevent="onClickStep(step, index)"
                    >
                        <div class="hcis-steps__card-inner">
                            <div class="hcis-steps__card-top">
                                <div class="hcis-steps__card-marker-group">
                                    <span class="hcis-steps__marker" aria-hidden="true">
                                        <slot name="marker" :step="step" :index="index" :state="keadaan(step, index)">
                                            <HcisIcon v-if="step.icon" :name="step.icon" size="xs" />
                                            <HcisIcon v-else-if="keadaan(step, index) === 'done'" name="check-lg" size="xs" />
                                            <HcisIcon v-else-if="keadaan(step, index) === 'rejected'" name="x-lg" size="xs" />
                                            <HcisIcon v-else-if="keadaan(step, index) === 'warning'" name="exclamation-lg" size="xs" />
                                            <template v-else>{{ index + 1 }}</template>
                                        </slot>
                                    </span>
                                    <span class="hcis-steps__stage-kicker">TAHAP {{ index + 1 }}</span>
                                </div>

                                <span class="hcis-steps__status-pill">
                                    <HcisIcon v-if="keadaan(step, index) === 'done'" name="check-circle-fill" size="2xs" class="me-1" />
                                    {{ step.status || (keadaan(step, index) === 'done' ? 'Selesai' : (keadaan(step, index) === 'current' ? 'Sedang Berjalan' : 'Menunggu')) }}
                                </span>
                            </div>

                            <div class="hcis-steps__card-body">
                                <h4 class="hcis-steps__title">
                                    <slot name="label" :step="step" :index="index">{{ step.label || step.title }}</slot>
                                </h4>
                                <p v-if="step.caption || step.subtitle || $slots.caption" class="hcis-steps__subtitle">
                                    <slot name="caption" :step="step" :index="index">{{ step.caption || step.subtitle }}</slot>
                                </p>
                                <p v-if="step.description" class="hcis-steps__desc">
                                    {{ step.description }}
                                </p>
                            </div>

                            <div v-if="step.actor || step.time" class="hcis-steps__card-footer">
                                <span v-if="step.actor" class="hcis-steps__meta-item">
                                    <HcisIcon name="person" size="2xs" class="me-1" />{{ step.actor }}
                                </span>
                                <span v-if="step.time" class="hcis-steps__meta-item">
                                    <HcisIcon name="clock" size="2xs" class="me-1" />{{ step.time }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VARIANT: PILLS PIPELINE -->
                <div v-else-if="variant === 'pills'" class="hcis-steps__pills-flow">
                    <template v-for="(step, index) in steps" :key="step.label || step.title || index">
                        <button
                            type="button"
                            class="hcis-steps__pill"
                            :class="[
                                `is-${keadaan(step, index)}`,
                                `hcis-steps__pill--${sentiment}`,
                                {
                                    'is-interactive': clickable && !step.disabled && (allowAllClickable || index <= current + 1),
                                    'is-disabled': step.disabled
                                }
                            ]"
                            :disabled="!clickable || step.disabled"
                            @click="onClickStep(step, index)"
                        >
                            <span class="hcis-steps__pill-marker">
                                <HcisIcon v-if="keadaan(step, index) === 'done'" name="check-lg" size="2xs" />
                                <HcisIcon v-else-if="step.icon" :name="step.icon" size="2xs" />
                                <span v-else>{{ index + 1 }}</span>
                            </span>
                            <span class="hcis-steps__pill-text">{{ step.label || step.title }}</span>
                        </button>
                        <HcisIcon
                            v-if="index < steps.length - 1"
                            name="chevron-right"
                            size="2xs"
                            class="hcis-steps__pill-separator"
                        />
                    </template>
                </div>

                <!-- VARIANT: DEFAULT STEPPER (HORIZONTAL & VERTICAL) -->
                <ol v-else class="hcis-steps__list" :class="`hcis-steps__list--${direction}`">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.label || step.title || index"
                        class="hcis-steps__item"
                        :class="[
                            `is-${keadaan(step, index)}`,
                            `hcis-steps__item--${sentiment}`,
                            {
                                'is-interactive': clickable && !step.disabled && (allowAllClickable || index <= current + 1),
                                'is-disabled': step.disabled
                            }
                        ]"
                        :aria-current="keadaan(step, index) === 'current' ? 'step' : undefined"
                        :tabindex="bisaDiklik(step, index) ? 0 : undefined"
                        :role="bisaDiklik(step, index) ? 'button' : undefined"
                        :aria-disabled="clickable && !bisaDiklik(step, index) ? 'true' : undefined"
                        @click="onClickStep(step, index)"
                        @keydown.enter.prevent="onClickStep(step, index)"
                        @keydown.space.prevent="onClickStep(step, index)"
                    >
                        <!-- Horizontal Direction -->
                        <template v-if="direction === 'horizontal'">
                            <div class="hcis-steps__head">
                                <span class="hcis-steps__marker" aria-hidden="true">
                                    <slot name="marker" :step="step" :index="index" :state="keadaan(step, index)">
                                        <HcisIcon v-if="step.icon" :name="step.icon" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'done'" name="check-lg" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'rejected'" name="x-lg" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'warning'" name="exclamation-lg" size="xs" />
                                        <template v-else>{{ index + 1 }}</template>
                                    </slot>
                                </span>

                                <div v-if="index < steps.length - 1" class="hcis-steps__connector-track">
                                    <div
                                        class="hcis-steps__connector-fill"
                                        :style="{ width: index < current ? '100%' : '0%' }"
                                    />
                                </div>
                            </div>

                            <div class="hcis-steps__content">
                                <span class="hcis-steps__title">
                                    <slot name="label" :step="step" :index="index">{{ step.label || step.title }}</slot>
                                </span>
                                <span v-if="step.caption || step.subtitle || $slots.caption" class="hcis-steps__subtitle">
                                    <slot name="caption" :step="step" :index="index">{{ step.caption || step.subtitle }}</slot>
                                </span>
                                <span v-if="step.time" class="hcis-steps__time-badge">
                                    <HcisIcon name="clock" size="2xs" class="me-1" />{{ step.time }}
                                </span>
                            </div>
                        </template>

                        <!-- Vertical Direction (Seamless Gapless Continuous Track) -->
                        <template v-else>
                            <!-- Continuous line absolute on li.hcis-steps__item extending from top marker to next marker -->
                            <div v-if="index < steps.length - 1" class="hcis-steps__vertical-line">
                                <div
                                    class="hcis-steps__vertical-fill"
                                    :style="{ height: index < current ? '100%' : '0%' }"
                                />
                            </div>

                            <div class="hcis-steps__vertical-gutter">
                                <span class="hcis-steps__marker" aria-hidden="true">
                                    <slot name="marker" :step="step" :index="index" :state="keadaan(step, index)">
                                        <HcisIcon v-if="step.icon" :name="step.icon" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'done'" name="check-lg" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'rejected'" name="x-lg" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'warning'" name="exclamation-lg" size="xs" />
                                        <template v-else>{{ index + 1 }}</template>
                                    </slot>
                                </span>
                            </div>

                            <div class="hcis-steps__content">
                                <span class="hcis-steps__title">
                                    <slot name="label" :step="step" :index="index">{{ step.label || step.title }}</slot>
                                </span>
                                <span v-if="step.caption || step.subtitle || $slots.caption" class="hcis-steps__subtitle">
                                    <slot name="caption" :step="step" :index="index">{{ step.caption || step.subtitle }}</slot>
                                </span>
                                <p v-if="step.description" class="hcis-steps__desc">
                                    {{ step.description }}
                                </p>
                                <span v-if="step.time" class="hcis-steps__time-badge">
                                    <HcisIcon name="clock" size="2xs" class="me-1" />{{ step.time }}
                                </span>
                            </div>
                        </template>
                    </li>
                </ol>
            </div>

            <!-- Footer Navigation Controls Slot -->
            <template v-if="$slots.footer" #footer>
                <slot name="footer" />
            </template>
        </HcisCard>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 2: STANDALONE STEPPER (Tanpa Card Wrapper)
             ═══════════════════════════════════════════════════════════════════ -->
        <div v-else class="hcis-steps__inner-container">
            <!-- VARIANT: CARDS GRID -->
            <div v-if="variant === 'cards'" class="hcis-steps__cards-grid">
                <div
                    v-for="(step, index) in steps"
                    :key="step.label || step.title || index"
                    class="hcis-steps__card-item"
                    :class="[
                        `is-${keadaan(step, index)}`,
                        `hcis-steps__card-item--${sentiment}`,
                        {
                            'is-interactive': clickable && !step.disabled && (allowAllClickable || index <= current + 1),
                            'is-disabled': step.disabled
                        }
                    ]"
                    :aria-current="keadaan(step, index) === 'current' ? 'step' : undefined"
                    :tabindex="bisaDiklik(step, index) ? 0 : undefined"
                    :role="bisaDiklik(step, index) ? 'button' : undefined"
                    :aria-disabled="clickable && !bisaDiklik(step, index) ? 'true' : undefined"
                    @click="onClickStep(step, index)"
                    @keydown.enter.prevent="onClickStep(step, index)"
                    @keydown.space.prevent="onClickStep(step, index)"
                >
                    <div class="hcis-steps__card-inner">
                        <div class="hcis-steps__card-top">
                            <div class="hcis-steps__card-marker-group">
                                <span class="hcis-steps__marker" aria-hidden="true">
                                    <slot name="marker" :step="step" :index="index" :state="keadaan(step, index)">
                                        <HcisIcon v-if="step.icon" :name="step.icon" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'done'" name="check-lg" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'rejected'" name="x-lg" size="xs" />
                                        <HcisIcon v-else-if="keadaan(step, index) === 'warning'" name="exclamation-lg" size="xs" />
                                        <template v-else>{{ index + 1 }}</template>
                                    </slot>
                                </span>
                                <span class="hcis-steps__stage-kicker">TAHAP {{ index + 1 }}</span>
                            </div>

                            <span class="hcis-steps__status-pill">
                                <HcisIcon v-if="keadaan(step, index) === 'done'" name="check-circle-fill" size="2xs" class="me-1" />
                                {{ step.status || (keadaan(step, index) === 'done' ? 'Selesai' : (keadaan(step, index) === 'current' ? 'Sedang Berjalan' : 'Menunggu')) }}
                            </span>
                        </div>

                        <div class="hcis-steps__card-body">
                            <h4 class="hcis-steps__title">
                                <slot name="label" :step="step" :index="index">{{ step.label || step.title }}</slot>
                            </h4>
                            <p v-if="step.caption || step.subtitle || $slots.caption" class="hcis-steps__subtitle">
                                <slot name="caption" :step="step" :index="index">{{ step.caption || step.subtitle }}</slot>
                            </p>
                            <p v-if="step.description" class="hcis-steps__desc">
                                {{ step.description }}
                            </p>
                        </div>

                        <div v-if="step.actor || step.time" class="hcis-steps__card-footer">
                            <span v-if="step.actor" class="hcis-steps__meta-item">
                                <HcisIcon name="person" size="2xs" class="me-1" />{{ step.actor }}
                            </span>
                            <span v-if="step.time" class="hcis-steps__meta-item">
                                <HcisIcon name="clock" size="2xs" class="me-1" />{{ step.time }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VARIANT: PILLS PIPELINE -->
            <div v-else-if="variant === 'pills'" class="hcis-steps__pills-flow">
                <template v-for="(step, index) in steps" :key="step.label || step.title || index">
                    <button
                        type="button"
                        class="hcis-steps__pill"
                        :class="[
                            `is-${keadaan(step, index)}`,
                            `hcis-steps__pill--${sentiment}`,
                            {
                                'is-interactive': clickable && !step.disabled && (allowAllClickable || index <= current + 1),
                                'is-disabled': step.disabled
                            }
                        ]"
                        :disabled="!clickable || step.disabled"
                        @click="onClickStep(step, index)"
                    >
                        <span class="hcis-steps__pill-marker">
                            <HcisIcon v-if="keadaan(step, index) === 'done'" name="check-lg" size="2xs" />
                            <HcisIcon v-else-if="step.icon" :name="step.icon" size="2xs" />
                            <span v-else>{{ index + 1 }}</span>
                        </span>
                        <span class="hcis-steps__pill-text">{{ step.label || step.title }}</span>
                    </button>
                    <HcisIcon
                        v-if="index < steps.length - 1"
                        name="chevron-right"
                        size="2xs"
                        class="hcis-steps__pill-separator"
                    />
                </template>
            </div>

            <!-- VARIANT: DEFAULT STEPPER -->
            <ol v-else class="hcis-steps__list" :class="`hcis-steps__list--${direction}`">
                <li
                    v-for="(step, index) in steps"
                    :key="step.label || step.title || index"
                    class="hcis-steps__item"
                    :class="[
                        `is-${keadaan(step, index)}`,
                        `hcis-steps__item--${sentiment}`,
                        {
                            'is-interactive': clickable && !step.disabled && (allowAllClickable || index <= current + 1),
                            'is-disabled': step.disabled
                        }
                    ]"
                    :aria-current="keadaan(step, index) === 'current' ? 'step' : undefined"
                    :tabindex="bisaDiklik(step, index) ? 0 : undefined"
                    :role="bisaDiklik(step, index) ? 'button' : undefined"
                    :aria-disabled="clickable && !bisaDiklik(step, index) ? 'true' : undefined"
                    @click="onClickStep(step, index)"
                    @keydown.enter.prevent="onClickStep(step, index)"
                    @keydown.space.prevent="onClickStep(step, index)"
                >
                    <!-- Horizontal Direction -->
                    <template v-if="direction === 'horizontal'">
                        <div class="hcis-steps__head">
                            <span class="hcis-steps__marker" aria-hidden="true">
                                <slot name="marker" :step="step" :index="index" :state="keadaan(step, index)">
                                    <HcisIcon v-if="step.icon" :name="step.icon" size="xs" />
                                    <HcisIcon v-else-if="keadaan(step, index) === 'done'" name="check-lg" size="xs" />
                                    <HcisIcon v-else-if="keadaan(step, index) === 'rejected'" name="x-lg" size="xs" />
                                    <HcisIcon v-else-if="keadaan(step, index) === 'warning'" name="exclamation-lg" size="xs" />
                                    <template v-else>{{ index + 1 }}</template>
                                </slot>
                            </span>

                            <div v-if="index < steps.length - 1" class="hcis-steps__connector-track">
                                <div
                                    class="hcis-steps__connector-fill"
                                    :style="{ width: index < current ? '100%' : '0%' }"
                                />
                            </div>
                        </div>

                        <div class="hcis-steps__content">
                            <span class="hcis-steps__title">
                                <slot name="label" :step="step" :index="index">{{ step.label || step.title }}</slot>
                            </span>
                            <span v-if="step.caption || step.subtitle || $slots.caption" class="hcis-steps__subtitle">
                                <slot name="caption" :step="step" :index="index">{{ step.caption || step.subtitle }}</slot>
                            </span>
                            <span v-if="step.time" class="hcis-steps__time-badge">
                                <HcisIcon name="clock" size="2xs" class="me-1" />{{ step.time }}
                            </span>
                        </div>
                    </template>

                    <!-- Vertical Direction (Seamless Gapless Continuous Track) -->
                    <template v-else>
                        <div v-if="index < steps.length - 1" class="hcis-steps__vertical-line">
                            <div
                                class="hcis-steps__vertical-fill"
                                :style="{ height: index < current ? '100%' : '0%' }"
                            />
                        </div>

                        <div class="hcis-steps__vertical-gutter">
                            <span class="hcis-steps__marker" aria-hidden="true">
                                <slot name="marker" :step="step" :index="index" :state="keadaan(step, index)">
                                    <HcisIcon v-if="step.icon" :name="step.icon" size="xs" />
                                    <HcisIcon v-else-if="keadaan(step, index) === 'done'" name="check-lg" size="xs" />
                                    <HcisIcon v-else-if="keadaan(step, index) === 'rejected'" name="x-lg" size="xs" />
                                    <HcisIcon v-else-if="keadaan(step, index) === 'warning'" name="exclamation-lg" size="xs" />
                                    <template v-else>{{ index + 1 }}</template>
                                </slot>
                            </span>
                        </div>

                        <div class="hcis-steps__content">
                            <span class="hcis-steps__title">
                                <slot name="label" :step="step" :index="index">{{ step.label || step.title }}</slot>
                            </span>
                            <span v-if="step.caption || step.subtitle || $slots.caption" class="hcis-steps__subtitle">
                                <slot name="caption" :step="step" :index="index">{{ step.caption || step.subtitle }}</slot>
                            </span>
                            <p v-if="step.description" class="hcis-steps__desc">
                                {{ step.description }}
                            </p>
                            <span v-if="step.time" class="hcis-steps__time-badge">
                                <HcisIcon name="clock" size="2xs" class="me-1" />{{ step.time }}
                            </span>
                        </div>
                    </template>
                </li>
            </ol>
        </div>
    </div>
</template>

<script setup>
import HcisCard from './HcisCard.vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisSteps' });

const props = defineProps({
    current: { type: Number, default: 0 },
    steps: { type: Array, required: true },
    direction: {
        type: String,
        default: 'horizontal',
        validator: (v) => ['horizontal', 'vertical'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    variant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'cards', 'pills', 'dots'].includes(v),
    },
    clickable: { type: Boolean, default: false },
    allowAllClickable: { type: Boolean, default: false },

    // HCIS Enterprise Suite Card Wrapper Props
    card: { type: Boolean, default: false },
    title: { type: String, default: '' },
    kicker: { type: String, default: '' },
    description: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: '' },
    badgeRadar: { type: Boolean, default: false },
    icon: { type: String, default: '' },

    // Styling & Theme
    sentiment: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'primary', 'purple', 'violet', 'teal', 'cyan', 'success', 'warning', 'danger', 'info', 'neutral'].includes(v),
    },
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
});

const emit = defineEmits(['update:current', 'change']);

function keadaan(step, index) {
    if (step.state) return step.state;
    if (index < props.current) return 'done';
    if (index === props.current) return 'current';
    return 'todo';
}

/**
 * Langkah yang bisa dipilih: dipakai untuk klik DAN keyboard (tabindex, Enter,
 * Spasi), supaya stepper yang bisa diklik tetap terjangkau tanpa mouse —
 * setara dengan <button> yang digantikannya di form-form wizard.
 */
function bisaDiklik(step, index) {
    if (!props.clickable || step.disabled) return false;
    return props.allowAllClickable || index <= props.current + 1;
}

function onClickStep(step, index) {
    if (!bisaDiklik(step, index)) return;
    emit('update:current', index);
    emit('change', { step, index });
}
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - STEPS COMPONENT
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-steps-wrapper {
    position: relative;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-steps__inner-container {
    width: 100%;
}

/* ═══════════════════════════════════════════════════════════════════════════
   DEFAULT HORIZONTAL STEPPER
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-steps__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    width: 100%;
}

.hcis-steps__list--horizontal {
    flex-direction: row;
    align-items: flex-start;
}

.hcis-steps__list--horizontal .hcis-steps__item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    position: relative;
    min-width: 0;
}

.hcis-steps__list--horizontal .hcis-steps__item:last-child {
    flex: 0 0 auto;
    min-width: 120px;
}

.hcis-steps__list--horizontal .hcis-steps__head {
    display: flex;
    align-items: center;
    width: 100%;
    position: relative;
    margin-bottom: 0.625rem;
}

.hcis-steps__connector-track {
    flex: 1;
    height: 3px;
    background: rgba(226, 232, 240, 0.9);
    margin: 0 10px;
    border-radius: 999px;
    overflow: hidden;
    position: relative;
}

.hcis-steps__connector-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
    transition: width 350ms cubic-bezier(0.16, 1, 0.3, 1);
}

.is-done .hcis-steps__connector-fill {
    background: var(--hcis-emerald-600) !important;
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.4) !important;
}

/* Jewel Node Marker */
.hcis-steps__marker {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.125rem;
    height: 2.125rem;
    border-radius: 50%;
    background: var(--hcis-white);
    border: 2px solid rgba(203, 213, 225, 0.95);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-weight: 800;
    font-size: 0.8125rem;
    z-index: 2;
    transition-property: var(--hcis-transition-props);
    transition-duration: 250ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.hcis-steps--sm .hcis-steps__marker { width: 1.75rem; height: 1.75rem; font-size: 0.6875rem; }
.hcis-steps--lg .hcis-steps__marker { width: 2.75rem; height: 2.75rem; font-size: 1rem; }

/* Completed State (Done) */
.hcis-steps__item.is-done .hcis-steps__marker {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-500) 100%);
    border-color: var(--hcis-emerald-600);
    color: var(--hcis-white);
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.45);
}

/* Active State (Current) with Radar Beacon Glow */
.hcis-steps__item.is-current .hcis-steps__marker {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    border-color: var(--hcis-white);
    color: var(--hcis-white);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.25), 0 0 0 8px rgba(99, 102, 241, 0.1), 0 4px 14px rgba(79, 70, 229, 0.45);
}

.hcis-steps__item.is-current .hcis-steps__title {
    color: var(--hcis-indigo-600) !important;
    font-weight: 850 !important;
}

/* Error State (Rejected) */
.hcis-steps__item.is-rejected .hcis-steps__marker {
    background: linear-gradient(135deg, var(--hcis-red-600) 0%, var(--hcis-red-500) 100%);
    border-color: var(--hcis-red-600);
    color: var(--hcis-white);
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.45);
}

.hcis-steps__item.is-rejected .hcis-steps__title {
    color: var(--hcis-red-600) !important;
}

/* Warning State */
.hcis-steps__item.is-warning .hcis-steps__marker {
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
    border-color: var(--hcis-amber-600);
    color: var(--hcis-white);
    box-shadow: 0 0 12px rgba(245, 158, 11, 0.45);
}

/* Content */
.hcis-steps__content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    gap: 3px;
    padding-right: 12px;
}

.hcis-steps__title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    line-height: 1.25;
    margin: 0;
}

.hcis-steps__subtitle {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1.35;
    margin: 0;
}

.hcis-steps__desc {
    font-size: 0.75rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    margin: 3px 0 0 0;
    line-height: 1.35;
}

.hcis-steps__time-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 650;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin-top: 2px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   VERTICAL MODE (100% Continuous Gapless Vertical Track)
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-steps__list--vertical {
    flex-direction: column;
    gap: 0;
}

.hcis-steps__list--vertical .hcis-steps__item {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: 1.25rem;
    position: relative;
    padding-bottom: 2rem;
}

.hcis-steps__list--vertical .hcis-steps__item:last-child {
    padding-bottom: 0;
}

.hcis-steps__vertical-gutter {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.125rem;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}

.hcis-steps--sm .hcis-steps__vertical-gutter { width: 1.75rem; }
.hcis-steps--lg .hcis-steps__vertical-gutter { width: 2.75rem; }

/* Absolute connector line from bottom of marker i to top of marker i+1 */
.hcis-steps__list--vertical .hcis-steps__vertical-line {
    position: absolute;
    left: 1.0625rem;
    transform: translateX(-50%);
    top: 2.125rem;
    bottom: 0;
    width: 3px;
    background: rgba(226, 232, 240, 0.95);
    border-radius: 999px;
    overflow: hidden;
    z-index: 1;
}

.hcis-steps--sm .hcis-steps__list--vertical .hcis-steps__vertical-line {
    left: 0.875rem;
    top: 1.75rem;
}

.hcis-steps--lg .hcis-steps__list--vertical .hcis-steps__vertical-line {
    left: 1.375rem;
    top: 2.75rem;
}

.hcis-steps__list--vertical .hcis-steps__vertical-fill {
    width: 100%;
    height: 100%;
    background: linear-gradient(180deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
    transition: transform 350ms cubic-bezier(0.16, 1, 0.3, 1);
    transform-origin: top;
}

.is-done .hcis-steps__vertical-fill {
    background: var(--hcis-emerald-600) !important;
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.4) !important;
}

.hcis-steps__list--vertical .hcis-steps__content {
    align-items: flex-start;
    text-align: left;
    padding-top: 2px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   VARIANT: CARDS GRID (Executive Workflow Stage Cards)
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-steps__cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.875rem;
    width: 100%;
}

.hcis-steps__card-item {
    cursor: default;
}

.hcis-steps__card-inner {
    padding: 1rem 1.125rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition-property: var(--hcis-transition-props);
    transition-duration: 220ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    height: 100%;
}

.is-current .hcis-steps__card-inner {
    border-color: rgba(99, 102, 241, 0.8);
    background: linear-gradient(180deg, var(--hcis-white) 0%, rgba(238, 242, 255, 0.55) 100%);
    box-shadow: 0 6px 20px -2px rgba(79, 70, 229, 0.15), 0 0 0 1px rgba(99, 102, 241, 0.35);
}

.is-done .hcis-steps__card-inner {
    border-color: rgba(167, 243, 208, 0.85);
    background: linear-gradient(180deg, var(--hcis-white) 0%, rgba(236, 253, 245, 0.45) 100%);
}

.hcis-steps__card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.hcis-steps__card-marker-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.hcis-steps__stage-kicker {
    font-size: 0.625rem;
    font-weight: 850;
    letter-spacing: 0.07em;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    text-transform: uppercase;
}

.hcis-steps__status-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 999px;
    font-size: 0.65625rem;
    font-weight: 750;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    border: 1px solid rgba(226, 232, 240, 0.95);
}

.is-done .hcis-steps__status-pill {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
    border-color: rgba(167, 243, 208, 0.8);
}

.is-current .hcis-steps__status-pill {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-color: rgba(199, 210, 254, 0.8);
}

.hcis-steps__card-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.hcis-steps__desc {
    font-size: 0.71875rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    margin: 4px 0 0 0;
    line-height: 1.35;
}

.hcis-steps__card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.6875rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    margin-top: auto;
    padding-top: 6px;
    border-top: 1px solid rgba(241, 245, 249, 0.9);
}

.hcis-steps__meta-item {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
}

/* ═══════════════════════════════════════════════════════════════════════════
   VARIANT: PILLS PIPELINE
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-steps__pills-flow {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.hcis-steps__pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px 6px 8px;
    border-radius: 999px;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.95);
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    cursor: default;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-steps__pill.is-done {
    background: var(--hcis-emerald-50);
    border-color: rgba(167, 243, 208, 0.8);
    color: var(--hcis-emerald-700);
}

.hcis-steps__pill.is-current {
    background: linear-gradient(90deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-white);
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.35);
}

.hcis-steps__pill-marker {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.85);
    color: inherit;
    font-size: 0.65rem;
    font-weight: 800;
}

.is-current .hcis-steps__pill-marker {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
}

.hcis-steps__pill-separator {
    color: var(--hcis-slate-400);
    margin: 0 2px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   INTERACTIVE / CLICKABLE
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-steps--clickable .is-interactive {
    cursor: pointer !important;
}

.hcis-steps--clickable .hcis-steps__card-item.is-interactive:hover .hcis-steps__card-inner {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.09);
    border-color: rgba(165, 180, 252, 0.8);
}

.hcis-steps--clickable .hcis-steps__item.is-interactive:hover .hcis-steps__marker {
    transform: scale(1.12);
    border-color: var(--hcis-indigo-600);
}

.hcis-steps--clickable .hcis-steps__pill.is-interactive:hover {
    border-color: rgba(165, 180, 252, 0.8);
    transform: translateY(-1px);
}
</style>
