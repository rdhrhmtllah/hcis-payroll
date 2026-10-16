<template>
    <transition name="dp-pop">
        <div v-if="visible && items.length" class="dp-panel" :class="{ collapsed }">
            <div class="dp-head" @click="collapsed = !collapsed">
                <div class="dp-title">
                    <i class="bi bi-cloud-arrow-down-fill"></i>
                    Export Data Training
                    <span v-if="runningCount" class="dp-badge">{{ runningCount }} berjalan</span>
                </div>
                <button class="dp-toggle" type="button">
                    <i class="bi" :class="collapsed ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
            </div>

            <div v-show="!collapsed" class="dp-body">
                <div v-for="exp in items" :key="exp.export_id" class="dp-item">
                    <div class="dp-item-top">
                        <span class="dp-status" :class="`st-${exp.status}`">
                            <i
                                class="bi"
                                :class="statusIcon(exp.status)"
                                :style="
                                    exp.status === 'processing' || exp.status === 'queued'
                                        ? 'animation: dpspin 0.8s linear infinite'
                                        : ''
                                "
                            ></i>
                        </span>
                        <div class="dp-item-info">
                            <div class="dp-item-label">{{ exp.label }}</div>
                            <div class="dp-item-sub">
                                {{ exp.format_label }} ·
                                <span v-if="exp.file_size_label">{{ exp.file_size_label }} · </span>
                                {{ exp.requested_at }}
                            </div>
                        </div>
                        <button class="dp-dismiss" type="button" title="Sembunyikan" @click="dismiss(exp)">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    <!-- progress -->
                    <div v-if="exp.status === 'queued' || exp.status === 'processing'" class="dp-progress">
                        <div class="dp-bar-track">
                            <div class="dp-bar-fill" :style="{ width: exp.percent + '%' }"></div>
                        </div>
                        <span class="dp-pct"
                            >{{ exp.processed_records }}/{{ exp.total_records }} ({{ exp.percent }}%)</span
                        >
                    </div>

                    <!-- actions -->
                    <div class="dp-actions">
                        <button v-if="exp.status === 'ready'" class="dp-act dl" type="button" @click="download(exp)">
                            <i class="bi bi-download"></i> Download
                        </button>
                        <button
                            v-if="exp.status === 'failed'"
                            class="dp-act retry"
                            type="button"
                            :disabled="retryingId === exp.export_id"
                            @click="retry(exp)"
                        >
                            <i class="bi bi-arrow-clockwise"></i> Coba Lagi
                        </button>
                        <span v-if="exp.status === 'failed'" class="dp-err">{{ exp.error_message || 'Gagal.' }}</span>
                        <span v-if="exp.status === 'expired'" class="dp-expired">File kedaluwarsa</span>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script>
import axios from 'axios';

const BASE = '/lms/admin/monitoring';
const POLL_INTERVAL = 4000;

export default {
    name: 'ExportProgressPanel',
    data() {
        return {
            items: [],
            collapsed: false,
            visible: true,
            pollTimer: null,
            retryingId: null,
        };
    },
    computed: {
        runningCount() {
            return this.items.filter((e) => ['queued', 'processing'].includes(e.status)).length;
        },
    },
    mounted() {
        this.loadList();
    },
    beforeUnmount() {
        this.stopPolling();
    },
    methods: {
        // Dipanggil parent setelah dispatch export baru.
        refresh() {
            this.visible = true;
            this.collapsed = false;
            this.loadList();
        },
        async loadList() {
            try {
                const { data } = await axios.get(`${BASE}/export/list`);
                this.items = data.result || [];
                this.syncPolling();
            } catch (e) {
                /* non-kritis */
            }
        },
        hasRunning() {
            return this.items.some((e) => ['queued', 'processing'].includes(e.status));
        },
        syncPolling() {
            if (this.hasRunning()) {
                if (!this.pollTimer) this.pollTimer = setInterval(this.loadList, POLL_INTERVAL);
            } else {
                this.stopPolling();
            }
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        download(exp) {
            window.location.href = `${BASE}/export/download/${exp.export_id}`;
        },
        async retry(exp) {
            this.retryingId = exp.export_id;
            try {
                await axios.post(`${BASE}/export/retry/${exp.export_id}`);
                await this.loadList();
            } catch (e) {
                /* abaikan */
            } finally {
                this.retryingId = null;
            }
        },
        dismiss(exp) {
            this.items = this.items.filter((e) => e.export_id !== exp.export_id);
            this.syncPolling();
            axios.post(`${BASE}/export/dismiss/${exp.export_id}`).catch(() => {});
        },
        statusIcon(status) {
            switch (status) {
                case 'ready':
                    return 'bi-check-circle-fill';
                case 'failed':
                    return 'bi-x-circle-fill';
                case 'expired':
                    return 'bi-clock-history';
                default:
                    return 'bi-arrow-repeat';
            }
        },
    },
};
</script>

<style scoped>
.dp-panel {
    position: fixed;
    right: 22px;
    bottom: 22px;
    width: 360px;
    max-width: calc(100vw - 32px);
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    box-shadow: 0 12px 36px rgba(15, 23, 42, 0.18);
    z-index: 1050;
    overflow: hidden;
}
.dp-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: var(--hcis-indigo-600);
    color: #fff;
    cursor: pointer;
}
.dp-title {
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dp-badge {
    background: rgba(255, 255, 255, 0.22);
    border-radius: 12px;
    font-size: 10.5px;
    padding: 2px 8px;
    font-weight: 600;
}
.dp-toggle {
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    font-size: 14px;
}
.dp-body {
    max-height: 50vh;
    overflow-y: auto;
    padding: 8px;
}
.dp-item {
    border: 1px solid #eef2f7;
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 8px;
    background: #fafbff;
}
.dp-item:last-child {
    margin-bottom: 0;
}
.dp-item-top {
    display: flex;
    align-items: flex-start;
    gap: 9px;
}
.dp-status {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.st-ready {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
}
.st-failed {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
}
.st-expired {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
}
.st-queued,
.st-processing {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
}
.dp-item-info {
    flex: 1;
    min-width: 0;
}
.dp-item-label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.dp-item-sub {
    font-size: 11px;
    color: var(--hcis-slate-400);
    margin-top: 1px;
}
.dp-dismiss {
    background: transparent;
    border: none;
    color: var(--hcis-slate-300);
    cursor: pointer;
    font-size: 15px;
    line-height: 1;
}
.dp-dismiss:hover {
    color: var(--hcis-slate-500);
}
.dp-progress {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 9px;
}
.dp-bar-track {
    flex: 1;
    height: 7px;
    background: var(--hcis-slate-200);
    border-radius: 4px;
    overflow: hidden;
}
.dp-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    border-radius: 4px;
    transition: width 0.4s ease;
}
.dp-pct {
    font-size: 10.5px;
    color: var(--hcis-slate-500);
    white-space: nowrap;
}
.dp-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 9px;
    flex-wrap: wrap;
}
.dp-act {
    border: none;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.dp-act.dl {
    background: var(--hcis-indigo-600);
    color: #fff;
}
.dp-act.dl:hover {
    background: var(--hcis-indigo-700);
}
.dp-act.retry {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}
.dp-err {
    font-size: 11px;
    color: var(--hcis-red-600);
}
.dp-expired {
    font-size: 11.5px;
    color: var(--hcis-slate-400);
}

.dp-pop-enter-active,
.dp-pop-leave-active {
    transition: all 0.25s ease;
}
.dp-pop-enter-from,
.dp-pop-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

@keyframes dpspin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 640px) {
    .dp-panel {
        right: 8px;
        left: 8px;
        bottom: 8px;
        width: auto;
    }
}
</style>
