<template>
    <div class="container-fluid px-0 verifikasi-kpi-page">
        <HeaderKpi
            title="Verifikasi KPI"
            subtitle="Verifikasi parameter dan performance KPI dengan alur yang sesuai modul asal."
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />

        <div class="verifikasi-tabs">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="verifikasi-tab"
                :class="{ active: activeTab === tab.id }"
                @click="activeTab = tab.id"
            >
                <i :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
            </button>
        </div>

        <div class="verifikasi-panel">
            <VerifikasiParameterPanel
                v-if="activeTab === 'parameter'"
                key="parameter-queue"
                embedded
                mode="queue"
            />

            <VerifikasiPerformancePanel
                v-if="activeTab === 'performance'"
                key="performance-queue"
                embedded
                mode="queue"
            />

            <section v-if="activeTab === 'history'" class="history-shell">
                <div class="history-filter">
                    <div>
                        <h3>Riwayat Verifikasi</h3>
                        <p>Pilih tipe riwayat terlebih dahulu agar row dan detail mengikuti modul yang benar.</p>
                    </div>
                    <select v-model="historyType" class="history-type-select">
                        <option value="">Pilih Tipe Riwayat</option>
                        <option value="PARAMETER">Parameter</option>
                        <option value="PERFORMANCE">Performance</option>
                    </select>
                </div>

                <div v-if="!historyType" class="empty-history-state">
                    <i class="bi bi-funnel"></i>
                    <h4>Pilih tipe riwayat</h4>
                    <p>Riwayat sengaja tidak menampilkan data sebelum tipe dipilih.</p>
                </div>

                <VerifikasiParameterPanel
                    v-else-if="historyType === 'PARAMETER'"
                    key="parameter-history"
                    embedded
                    mode="history"
                />

                <VerifikasiPerformancePanel
                    v-else
                    key="performance-history"
                    embedded
                    mode="history"
                />
            </section>
        </div>
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import VerifikasiParameterPanel from './VerifikasiParameterPanel.vue';
import VerifikasiPerformancePanel from './VerifikasiPerformancePanel.vue';

export default {
    name: 'VerifikasiKPI',
    components: {
        HeaderKpi,
        VerifikasiParameterPanel,
        VerifikasiPerformancePanel,
    },
    data() {
        return {
            activeTab: 'parameter',
            historyType: '',
            currentDate: new Date(),
            tabs: [
                {
                    id: 'parameter',
                    label: 'Verifikasi Parameter',
                    icon: 'bi bi-list-check',
                },
                {
                    id: 'performance',
                    label: 'Verifikasi Performance',
                    icon: 'bi bi-speedometer2',
                },
                {
                    id: 'history',
                    label: 'Riwayat Verifikasi',
                    icon: 'bi bi-clock-history',
                },
            ],
        };
    },
    computed: {
        dayNight() {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            return new Intl.DateTimeFormat('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }).format(this.currentDate);
        },
    },
};
</script>

<style scoped>
.verifikasi-kpi-page {
    color: #1f2937;
}

.verifikasi-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 12px;
    border: 1px solid #dbe3ef;
    border-radius: 14px 14px 0 0;
    background: #eef3f9;
}

.verifikasi-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 16px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: #516783;
    font-weight: 700;
    transition: 160ms ease;
}

.verifikasi-tab.active {
    background: #635bff;
    color: #fff;
    box-shadow: 0 10px 22px rgba(99, 91, 255, 0.25);
}

.verifikasi-panel {
    padding: 18px;
    border: 1px solid #dbe3ef;
    border-top: 0;
    border-radius: 0 0 14px 14px;
    background: #fff;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
}

.history-shell {
    display: grid;
    gap: 16px;
}

.history-filter {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: var(--hcis-slate-50);
}

.history-filter h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0;
}

.history-filter p {
    margin: 4px 0 0;
    color: var(--hcis-slate-500);
}

.history-type-select {
    min-width: 240px;
    min-height: 40px;
    border: 1px solid var(--hcis-slate-300);
    border-radius: 8px;
    background: #fff;
    padding: 0 12px;
}

.empty-history-state {
    display: grid;
    place-items: center;
    min-height: 240px;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 12px;
    background: var(--hcis-slate-50);
    text-align: center;
}

.empty-history-state i {
    color: var(--hcis-slate-400);
    font-size: 36px;
}

.empty-history-state h4 {
    margin: 10px 0 4px;
    font-weight: 800;
}

.empty-history-state p {
    margin: 0;
    color: var(--hcis-slate-500);
}

@media (max-width: 768px) {
    .history-filter {
        align-items: flex-start;
        flex-direction: column;
    }

    .verifikasi-panel {
        padding: 12px;
    }

    .history-type-select,
    .verifikasi-tab {
        width: 100%;
    }
}
</style>
