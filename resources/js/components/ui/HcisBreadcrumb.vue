<template>
    <nav
        class="hcis-breadcrumb-nav"
        :class="[
            `hcis-breadcrumb--variant-${variant}`,
            `hcis-breadcrumb--size-${size}`,
        ]"
        :aria-label="ariaLabel"
    >
        <ol class="hcis-breadcrumb">
            <!-- Home Icon Item -->
            <li v-if="showHome" class="hcis-breadcrumb__item">
                <a :href="urlAman(homeHref)" class="hcis-breadcrumb__link hcis-breadcrumb__link--home" aria-label="Beranda">
                    <HcisIcon name="house-door-fill" size="xs" class="hcis-breadcrumb__icon" />
                </a>
            </li>

            <!-- Separator after Home -->
            <li v-if="showHome && visibleItems.length" class="hcis-breadcrumb__sep" aria-hidden="true">
                <slot name="separator">
                    <HcisIcon v-if="separator === 'chevron'" name="chevron-right" size="xs" class="hcis-breadcrumb__sep-icon" />
                    <span v-else-if="separator === 'bullet'">•</span>
                    <span v-else-if="separator === 'slash'">/</span>
                    <HcisIcon v-else-if="separator.startsWith('bi ')" :name="separator.replace('bi bi-', '')" size="xs" />
                    <span v-else>{{ separator }}</span>
                </slot>
            </li>

            <template v-for="(item, index) in visibleItems" :key="item.key || item.label || index">
                <li
                    class="hcis-breadcrumb__item"
                    :class="{ 'is-current': isLastItem(index) }"
                >
                    <!-- Item Icon -->
                    <HcisIcon v-if="item.icon" :name="item.icon" size="xs" class="hcis-breadcrumb__icon" />

                    <!-- Link or Current Text -->
                    <a
                        v-if="item.href && !isLastItem(index)"
                        :href="urlAman(item.href)"
                        class="hcis-breadcrumb__link"
                    >
                        <slot :name="`item-${index}`" :item="item">{{ item.label }}</slot>
                    </a>
                    <span
                        v-else
                        :class="item.key === 'ellipsis-item' ? 'hcis-breadcrumb__ellipsis' : 'hcis-breadcrumb__current'"
                        :aria-current="isLastItem(index) ? 'page' : undefined"
                    >
                        <slot :name="`item-${index}`" :item="item">{{ item.label }}</slot>
                    </span>
                </li>

                <!-- Separator -->
                <li
                    v-if="!isLastItem(index)"
                    class="hcis-breadcrumb__sep"
                    aria-hidden="true"
                >
                    <slot name="separator">
                        <HcisIcon v-if="separator === 'chevron'" name="chevron-right" size="xs" class="hcis-breadcrumb__sep-icon" />
                        <span v-else-if="separator === 'bullet'">•</span>
                        <span v-else-if="separator === 'slash'">/</span>
                        <HcisIcon v-else-if="separator.startsWith('bi ')" :name="separator.replace('bi bi-', '')" size="xs" />
                        <span v-else>{{ separator }}</span>
                    </slot>
                </li>
            </template>
        </ol>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';
import { urlAman } from './urlAman';

defineOptions({ name: 'HcisBreadcrumb' });

const props = defineProps({
    items: { type: Array, required: true },
    separator: { type: String, default: 'slash' },
    variant: {
        type: String,
        default: 'plain',
        validator: (v) => ['plain', 'pills', 'card'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    maxItems: { type: Number, default: 0 },
    ariaLabel: { type: String, default: 'Remah roti' },
    showHome: { type: Boolean, default: false },
    homeHref: { type: String, default: '/' },
});

const visibleItems = computed(() => {
    if (!props.maxItems || props.items.length <= props.maxItems || props.maxItems < 3) {
        return props.items;
    }
    const first = props.items[0];
    const tailCount = props.maxItems - 2;
    const tail = props.items.slice(props.items.length - tailCount);
    const ellipsisItem = { label: '...', href: null, key: 'ellipsis-item' };
    return [first, ellipsisItem, ...tail];
});

function isLastItem(index) {
    return index === visibleItems.value.length - 1;
}
</script>
