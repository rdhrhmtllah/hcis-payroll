<template>
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center justify-between hover:shadow-md transition-shadow duration-300">
        <div>
            <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">{{ title }}</p>
            <h3 class="text-3xl font-bold text-gray-800">{{ value }}</h3>
        </div>
        <div :class="`p-3 rounded-xl bg-${color}-50 text-${color}-600`">
            <component :is="iconComponent" class="w-8 h-8" style="width: 2rem; height: 2rem;" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { 
    DocumentTextIcon, 
    ClockIcon, 
    CheckCircleIcon,
    DocumentDuplicateIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    title: String,
    value: [Number, String],
    icon: String,
    color: String
});

const iconComponent = computed(() => {
    switch (props.icon) {
        case 'DocumentTextIcon': return DocumentTextIcon;
        case 'document-duplicate': return DocumentDuplicateIcon;
        case 'ClockIcon': return ClockIcon;
        case 'clock': return ClockIcon;
        case 'CheckCircleIcon': return CheckCircleIcon;
        case 'check-circle': return CheckCircleIcon;
        default: return DocumentTextIcon;
    }
});
</script>
