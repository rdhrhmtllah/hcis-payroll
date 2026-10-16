<template>
    <div class="ds-migration-container">
        <div v-for="m in items" :key="m.from" class="ds-migration-card">
            <div class="ds-migration-col ds-migration-col--from">
                <span class="ds-migration-badge ds-migration-badge--old">Pola Lama (Pensiun)</span>
                <code class="ds-migration-code ds-migration-code--old">{{ m.from }}</code>
            </div>
            <div class="ds-migration-arrow" aria-hidden="true">→</div>
            <div class="ds-migration-col ds-migration-col--to">
                <span class="ds-migration-badge ds-migration-badge--new">Standar Baru (HCIS UI)</span>
                <code class="ds-migration-code ds-migration-code--new">{{ m.to }}</code>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({ items: { type: Array, default: () => [] } });
</script>

<style scoped>
.ds-migration-container {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3);
}

.ds-migration-card {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-4);
    padding: var(--hcis-space-4);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
    box-shadow: var(--hcis-shadow-xs);
}

.ds-migration-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2);
}

.ds-migration-arrow {
    color: var(--hcis-brand-500);
    font-weight: 800;
    font-size: var(--hcis-font-size-lg);
}

.ds-migration-badge {
    align-self: flex-start;
    padding: 1px 8px;
    border-radius: var(--hcis-radius-pill);
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.ds-migration-badge--old {
    background: var(--hcis-danger-50);
    color: var(--hcis-danger-700);
    border: 1px solid var(--hcis-danger-200);
}

.ds-migration-badge--new {
    background: var(--hcis-success-50);
    color: var(--hcis-success-700);
    border: 1px solid var(--hcis-success-200);
}

.ds-migration-code {
    padding: var(--hcis-space-2) var(--hcis-space-3);
    border-radius: var(--hcis-radius-md);
    font-family: var(--hcis-font-mono);
    font-size: 0.85em;
    line-height: var(--hcis-line-normal);
}

.ds-migration-code--old {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
    text-decoration: line-through;
    opacity: 0.85;
}

.ds-migration-code--new {
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    border: 1px solid var(--hcis-brand-200);
    font-weight: 700;
}

@media (max-width: 767.98px) {
    .ds-migration-card {
        flex-direction: column;
        align-items: stretch;
    }
    .ds-migration-arrow {
        text-align: center;
        transform: rotate(90deg);
    }
}
</style>
