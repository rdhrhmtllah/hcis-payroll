<template>
    <div class="assignment-context-card">
        <div v-if="!step" class="context-empty">
            <div class="empty-icon">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <div class="empty-copy">
                <span class="context-eyebrow">Posisi approval belum dipilih</span>
                <strong>{{ isSystemAdmin ? 'Pilih config dan tahap approval' : 'Pilih langsung dari visualisasi alur fitur' }}</strong>
                <span>
                    {{
                        isSystemAdmin
                            ? 'Gunakan pilihan config dan step di atas untuk menentukan posisi assignment yang akan dikelola.'
                            : 'Klik node assignment yang dapat diedit pada Alur Fitur. Single Assign dan Bulk Assign akan bekerja pada posisi yang Anda pilih tersebut.'
                    }}
                </span>
            </div>
            <button v-if="!isSystemAdmin" type="button" class="context-primary-btn" @click="$emit('change-mode', 'configs')">
                <i class="bi bi-cursor-fill"></i>
                Pilih dari Alur Fitur
            </button>
        </div>

        <template v-else>
            <div class="context-topbar">
                <div>
                    <span class="context-eyebrow">
                        <i class="bi bi-crosshair2"></i>
                        Posisi approval aktif
                    </span>
                    <div class="active-position">
                        <span class="position-order">{{ step.Approval_Order || '-' }}</span>
                        <div>
                            <strong>{{ stepName }}</strong>
                            <span>{{ isSystemAdmin ? configName : featureName }}</span>
                        </div>
                    </div>
                </div>
                <div class="context-badges">
                    <span v-if="!isSystemAdmin" class="context-badge selected-from-flow">
                        <i class="bi bi-diagram-3-fill"></i>
                        Dipilih dari flow
                    </span>
                    <span class="context-badge" :class="canAssign ? 'editable-badge' : 'readonly-badge'">
                        <i class="bi" :class="canAssign ? 'bi-pencil-square' : 'bi-eye-fill'"></i>
                        {{ canAssign ? 'Assignment dapat diedit' : 'Hanya dapat dilihat' }}
                    </span>
                </div>
            </div>

            <div class="context-grid" :class="{ 'context-grid-admin': isSystemAdmin }">
                <div v-if="isSystemAdmin" class="context-item">
                    <span class="context-label">Approval Config</span>
                    <strong>{{ config?.Kode_Config || 'Belum dipilih' }}</strong>
                    <span class="context-value">{{ config?.Nama_Config || 'Config approval aktif' }}</span>
                </div>
                <div class="context-item">
                    <span class="context-label">Source Assignment</span>
                    <strong class="font-monospace">{{ step.Source_Reference || 'Belum ditentukan' }}</strong>
                    <span class="context-value">{{ sourceTypeLabel }}</span>
                </div>
                <div class="context-item">
                    <span class="context-label">Data yang diubah</span>
                    <strong class="mapping-value">
                        Kode_Karyawan_Requester <i class="bi bi-arrow-right"></i> {{ approverColumn }}
                    </strong>
                    <span class="context-value">Requester dipasangkan dengan approver dan diurutkan melalui order_flow</span>
                </div>
            </div>

            <div v-if="!isSystemAdmin" class="context-actions">
                <div class="mode-actions">
                    <span class="action-label">Kelola posisi ini:</span>
                    <button
                        type="button"
                        class="mode-btn"
                        :class="{ active: activeMode === 'single' }"
                        :disabled="!canAssign"
                        @click="$emit('change-mode', 'single')"
                    >
                        <i class="bi bi-person-fill"></i>
                        Single Assign
                    </button>
                    <button
                        type="button"
                        class="mode-btn"
                        :class="{ active: activeMode === 'bulk' }"
                        :disabled="!canAssign"
                        @click="$emit('change-mode', 'bulk')"
                    >
                        <i class="bi bi-lightning-charge-fill"></i>
                        Bulk Assign
                    </button>
                    <button
                        type="button"
                        class="mode-btn"
                        :class="{ active: activeMode === 'history' }"
                        @click="$emit('change-mode', 'history')"
                    >
                        <i class="bi bi-clock-history"></i>
                        Riwayat
                    </button>
                </div>
                <button type="button" class="change-position-btn" @click="$emit('change-mode', 'configs')">
                    <i class="bi bi-cursor-fill"></i>
                    Ganti posisi dari flow
                </button>
            </div>
        </template>
    </div>
</template>

<script>
export default {
    name: 'AssignmentContextCard',
    emits: ['change-mode'],
    props: {
        config: { type: Object, default: null },
        step: { type: Object, default: null },
        featureName: { type: String, default: 'Fitur terpilih' },
        isSystemAdmin: { type: Boolean, default: false },
        activeMode: { type: String, default: 'single' },
        canAssign: { type: Boolean, default: false },
    },
    computed: {
        stepName() {
            return this.step?.Nama_Approval || this.step?.Custom_Approver_Kode || 'Posisi approver';
        },
        configName() {
            return this.config?.Nama_Config || this.config?.Kode_Config || 'Approval config aktif';
        },
        sourceTypeLabel() {
            if (this.step?.Source_Type === 'KPI_TABLE') return 'Tabel assignment KPI';
            if (this.step?.Source_Type === 'TABLE') return 'Tabel assignment';
            return this.step?.Source_Type || this.step?.approver_mode || 'Source assignment';
        },
        approverColumn() {
            return this.step?.Source_Type === 'KPI_TABLE' ? 'Kode_Karyawan_Approver' : 'Kode_Karyawan';
        },
    },
};
</script>

<style scoped>
.assignment-context-card {
    margin-bottom: 1rem;
    padding: 1rem;
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 14px;
    background: linear-gradient(135deg, #fff 0%, #f8faff 100%);
    box-shadow: 0 8px 24px rgba(30, 41, 59, 0.05);
}
.context-empty,
.context-topbar,
.context-actions,
.mode-actions,
.active-position,
.context-badges {
    display: flex;
    align-items: center;
}
.context-empty {
    gap: 0.85rem;
}
.empty-icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border-radius: 12px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 1.15rem;
}
.empty-copy {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    color: var(--hcis-slate-500);
    font-size: 0.75rem;
}
.empty-copy strong {
    color: var(--hcis-slate-800);
    font-size: 0.88rem;
}
.context-eyebrow {
    display: block;
    margin-bottom: 0.35rem;
    color: var(--hcis-indigo-600);
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.055em;
    text-transform: uppercase;
}
.context-topbar {
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}
.active-position {
    gap: 0.65rem;
}
.active-position > div {
    display: flex;
    flex-direction: column;
}
.active-position strong {
    color: #172554;
    font-size: 0.92rem;
    line-height: 1.25;
}
.active-position span:last-child {
    color: var(--hcis-slate-500);
    font-size: 0.72rem;
}
.position-order {
    display: grid;
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    place-items: center;
    border-radius: 50%;
    background: var(--hcis-indigo-600);
    color: #fff !important;
    font-size: 0.75rem !important;
    font-weight: 800;
}
.context-badges {
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.35rem;
}
.context-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.55rem;
    border-radius: 999px;
    font-size: 0.66rem;
    font-weight: 700;
}
.selected-from-flow {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}
.editable-badge {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
}
.readonly-badge {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.context-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    margin-top: 0.75rem;
}
.context-grid-admin {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.context-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 0.65rem 0.75rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.75);
}
.context-label,
.action-label {
    color: var(--hcis-slate-500);
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.035em;
    text-transform: uppercase;
}
.context-item strong {
    overflow-wrap: anywhere;
    color: var(--hcis-slate-800);
    font-size: 0.78rem;
}
.context-value {
    color: var(--hcis-slate-500);
    font-size: 0.69rem;
    line-height: 1.35;
}
.mapping-value {
    color: var(--hcis-indigo-700) !important;
}
.mapping-value i {
    margin: 0 0.2rem;
}
.context-actions {
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--hcis-slate-200);
}
.mode-actions {
    flex-wrap: wrap;
    gap: 0.35rem;
}
.action-label {
    margin-right: 0.25rem;
}
.mode-btn,
.change-position-btn,
.context-primary-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    transition: 0.18s ease;
}
.mode-btn {
    padding: 0.42rem 0.62rem;
    border: 1px solid #dbe3ef;
    background: #fff;
    color: var(--hcis-slate-600);
}
.mode-btn:hover:not(:disabled),
.mode-btn.active {
    border-color: var(--hcis-indigo-400);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}
.mode-btn:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
.change-position-btn,
.context-primary-btn {
    padding: 0.46rem 0.68rem;
    border: 1px solid var(--hcis-indigo-200);
    background: #fff;
    color: var(--hcis-indigo-700);
}
.change-position-btn:hover,
.context-primary-btn:hover {
    background: var(--hcis-indigo-50);
}
@media (max-width: 991px) {
    .context-topbar,
    .context-actions {
        align-items: flex-start;
        flex-direction: column;
    }
    .context-badges {
        justify-content: flex-start;
    }
}
@media (max-width: 767px) {
    .context-grid,
    .context-grid-admin {
        grid-template-columns: 1fr;
    }
    .context-empty {
        align-items: flex-start;
        flex-wrap: wrap;
    }
}
</style>
