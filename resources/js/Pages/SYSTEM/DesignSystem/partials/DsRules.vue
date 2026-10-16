<template>
    <div class="ds-rules">
        <div class="ds-rules__col ds-rules__col--do">
            <div class="ds-rules__head">
                <span class="ds-rules__icon ds-rules__icon--do" aria-hidden="true">✓</span>
                <h3>Lakukan</h3>
            </div>
            <ul class="ds-rules__list">
                <li v-for="r in doList" :key="r">{{ r }}</li>
            </ul>
        </div>
        <div class="ds-rules__col ds-rules__col--dont">
            <div class="ds-rules__head">
                <span class="ds-rules__icon ds-rules__icon--dont" aria-hidden="true">✕</span>
                <h3>Jangan</h3>
            </div>
            <ul class="ds-rules__list">
                <li v-for="r in dontList" :key="r">{{ r }}</li>
            </ul>
        </div>
    </div>
</template>

<script setup>
defineProps({
    doList: { type: Array, default: () => [] },
    dontList: { type: Array, default: () => [] },
});
</script>

<style scoped>
.ds-rules {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));
    gap: var(--hcis-space-5);
}
.ds-rules__col {
    border-radius: var(--hcis-radius-xl);
    padding: var(--hcis-space-5);
    border: 1px solid transparent;
}
.ds-rules__col--do {
    background: var(--hcis-success-50);
    border-color: var(--hcis-success-200);
}
.ds-rules__col--dont {
    background: var(--hcis-danger-50);
    border-color: var(--hcis-danger-200);
}
.ds-rules__head {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    margin-bottom: var(--hcis-space-3);
}
.ds-rules__icon {
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: var(--hcis-radius-pill);
    font-size: var(--hcis-font-size-xs);
    font-weight: 800;
}
.ds-rules__icon--do {
    background: var(--hcis-success-500);
    color: var(--hcis-white);
}
.ds-rules__icon--dont {
    background: var(--hcis-danger-500);
    color: var(--hcis-white);
}
.ds-rules__head h3 {
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.ds-rules__col--do h3 {
    color: var(--hcis-success-700);
}
.ds-rules__col--dont h3 {
    color: var(--hcis-danger-700);
}
.ds-rules__list {
    margin: 0;
    padding-left: var(--hcis-space-4);
    color: var(--hcis-text);
    font-size: var(--hcis-font-size-sm);
    line-height: var(--hcis-line-relaxed);
}
.ds-rules__list li {
    margin-bottom: var(--hcis-space-2);
}
.ds-rules__list li:last-child {
    margin-bottom: 0;
}
</style>
