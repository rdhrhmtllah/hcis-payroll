<template>
    <div class="ds-api-container">
        <div v-if="propList.length" class="ds-api">
            <h3 class="ds-api__label">Props (Parameter)</h3>
            <div class="hcis-table-wrap ds-table-card">
                <table class="hcis-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Nilai Bawaan</th>
                            <th>Catatan & Opsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in propList" :key="p.name">
                            <td>
                                <code class="ds-prop-name">{{ p.name }}</code>
                            </td>
                            <td>
                                <span class="ds-type-pill" :class="getTypeClass(p.type)">{{ p.type }}</span>
                            </td>
                            <td>
                                <code class="ds-prop-default">{{ p.default ?? '—' }}</code>
                            </td>
                            <td class="ds-prop-note">{{ p.note || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="events.length" class="ds-api">
            <h3 class="ds-api__label">Events (Peristiwa)</h3>
            <div class="ds-api-card">
                <div v-for="e in events" :key="e.name" class="ds-api-item">
                    <code class="ds-event-name">@{{ e.name }}</code>
                    <span class="ds-api-item__desc">{{ e.note || e.payload || 'Disulut saat aksi dipicu' }}</span>
                </div>
            </div>
        </div>

        <div v-if="slotList.length" class="ds-api">
            <h3 class="ds-api__label">Slots (Wadah Isi)</h3>
            <div class="ds-api-card">
                <div v-for="s in slotList" :key="s.name" class="ds-api-item">
                    <code class="ds-slot-name">#{{ s.name }}</code>
                    <span class="ds-api-item__desc">{{ s.note }}</span>
                </div>
            </div>
        </div>

        <div v-if="allowed.length" class="ds-api">
            <h3 class="ds-api__label">Prop yang direstui (Element Plus Bridge)</h3>
            <div class="ds-pill-list">
                <span v-for="a in allowed" :key="a" class="ds-allowed-pill"><code>{{ a }}</code></span>
            </div>
        </div>

        <div v-if="forbidden.length" class="ds-api">
            <h3 class="ds-api__label ds-api__label--forbidden">Larangan Keras</h3>
            <div class="ds-forbidden-grid">
                <div v-for="f in forbidden" :key="f.what" class="ds-forbidden-card">
                    <div class="ds-forbidden-card__head">
                        <code>{{ f.what }}</code>
                    </div>
                    <p class="ds-forbidden-card__why">{{ f.why }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    propList: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    slotList: { type: Array, default: () => [] },
    allowed: { type: Array, default: () => [] },
    forbidden: { type: Array, default: () => [] },
});

function getTypeClass(type = '') {
    const t = String(type).toLowerCase();
    if (t.includes('string')) return 'ds-type-pill--string';
    if (t.includes('boolean')) return 'ds-type-pill--boolean';
    if (t.includes('number')) return 'ds-type-pill--number';
    if (t.includes('array') || t.includes('object')) return 'ds-type-pill--object';
    if (t.includes('function')) return 'ds-type-pill--function';
    return 'ds-type-pill--default';
}
</script>

<style scoped>
.ds-api-container {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-6);
}

.ds-api__label {
    margin: 0 0 var(--hcis-space-3);
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.ds-api__label--forbidden {
    color: var(--hcis-danger-700);
}

.ds-table-card {
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    overflow: hidden;
    box-shadow: var(--hcis-shadow-xs);
}

.ds-prop-name {
    color: var(--hcis-brand-700);
    font-weight: 700;
    font-family: var(--hcis-font-mono);
    font-size: 0.85em;
    background: var(--hcis-brand-50);
    padding: 2px 6px;
    border-radius: var(--hcis-radius-sm);
}

.ds-prop-default {
    color: var(--hcis-slate-700);
    font-family: var(--hcis-font-mono);
    font-size: 0.8em;
    background: var(--hcis-surface-soft);
    padding: 2px 6px;
    border-radius: var(--hcis-radius-sm);
}

.ds-prop-note {
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
    line-height: var(--hcis-line-normal);
}

.ds-type-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: var(--hcis-radius-pill);
    font-size: 0.75rem;
    font-weight: 700;
    font-family: var(--hcis-font-mono);
    line-height: 1.2;
}

.ds-type-pill--string { background: var(--hcis-purple-100); color: var(--hcis-purple-700); }
.ds-type-pill--boolean { background: var(--hcis-green-100); color: var(--hcis-green-700); }
.ds-type-pill--number { background: var(--hcis-sky-100); color: var(--hcis-sky-700); }
.ds-type-pill--object { background: var(--hcis-warning-100); color: var(--hcis-warning-700); }
.ds-type-pill--function { background: var(--hcis-rose-100); color: var(--hcis-rose-700); }
.ds-type-pill--default { background: var(--hcis-surface-soft); color: var(--hcis-text-muted); }

.ds-api-card {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2);
}

.ds-api-item {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-3) var(--hcis-space-4);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-surface);
}

.ds-event-name {
    color: var(--hcis-sky-600);
    font-weight: 700;
    font-family: var(--hcis-font-mono);
    background: var(--hcis-sky-100);
    padding: 2px 8px;
    border-radius: var(--hcis-radius-sm);
}

.ds-slot-name {
    color: var(--hcis-warning-600);
    font-weight: 700;
    font-family: var(--hcis-font-mono);
    background: var(--hcis-warning-100);
    padding: 2px 8px;
    border-radius: var(--hcis-radius-sm);
}

.ds-api-item__desc {
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-sm);
}

.ds-pill-list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--hcis-space-2);
}

.ds-allowed-pill code {
    background: var(--hcis-brand-50);
    border: 1px solid var(--hcis-brand-200);
    color: var(--hcis-brand-700);
    padding: 4px 10px;
    border-radius: var(--hcis-radius-pill);
    font-family: var(--hcis-font-mono);
    font-size: 0.8em;
}

.ds-forbidden-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--hcis-space-3);
}

.ds-forbidden-card {
    padding: var(--hcis-space-4);
    border: 1px solid var(--hcis-danger-300);
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-danger-50);
}

.ds-forbidden-card__head code {
    color: var(--hcis-danger-700);
    font-weight: 800;
    font-family: var(--hcis-font-mono);
    background: rgb(var(--hcis-danger-500-rgb) / 0.1);
    padding: 2px 6px;
    border-radius: var(--hcis-radius-sm);
}

.ds-forbidden-card__why {
    margin: var(--hcis-space-2) 0 0;
    color: var(--hcis-danger-800);
    font-size: var(--hcis-font-size-xs);
    line-height: var(--hcis-line-normal);
}
</style>
