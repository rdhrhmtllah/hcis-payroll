<template>
    <HcisAvatar
        v-bind="props"
        @group-click="$emit('group-click', $event)"
        @member-click="$emit('member-click', $event)"
    />
</template>

<script setup>
import HcisAvatar from './HcisAvatar.vue';

defineOptions({ name: 'HcisAvatarGroup' });

const props = defineProps({
    users: { type: Array, default: () => [] },
    max: { type: Number, default: 4 },
    size: { type: String, default: 'sm' },
    spacing: { type: String, default: 'normal' },
    shape: { type: String, default: 'circle' },
    ring: { type: String, default: 'white' },
    interactive: { type: Boolean, default: true },
});

defineEmits(['group-click', 'member-click']);
</script>
