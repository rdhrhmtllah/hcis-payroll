<template>
    <div class="leader-monitoring-card shell-card shell-card--inner">
        <!-- HEADER -->
        <div class="leader-header">
            <div class="leader-header__title-group">
                <div class="leader-header__icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="leader-header__subtitle">HCIS Intelligence</div>
                    <h2 class="leader-header__title">Team Leader Dashboard</h2>
                </div>
            </div>
            <div class="leader-header__actions">
                <button class="shell-btn leader-action-btn" aria-label="Refresh Data">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- MAIN GRID -->
        <div class="leader-grid">
            <!-- 1. ATTENDANCE STATS -->
            <div class="leader-tile">
                <div class="leader-tile__head">
                    <span class="leader-tile__label">Attendance Today</span>
                    <i class="bi bi-fingerprint leader-tile__icon"></i>
                </div>
                <div class="leader-tile__body">
                    <div class="leader-attendance">
                        <div class="leader-attendance__main">
                            <strong>{{ teamStats.presentPercent }}%</strong>
                            <span>Hadir ({{ teamStats.presentCount }}/{{ teamStats.totalMembers }})</span>
                        </div>
                        <div class="leader-attendance__breakdown">
                            <div class="leader-breakdown-item is-danger has-tooltip">
                                <span class="leader-breakdown-item__val">{{ teamStats.absentCount }}</span>
                                <span class="leader-breakdown-item__lbl">Absent</span>
                                <div class="leader-tooltip">
                                    <div class="leader-tooltip__title">Absen Hari Ini</div>
                                    <ul v-if="teamStats.absentMembers.length">
                                        <li v-for="m in teamStats.absentMembers" :key="m.name">{{ m.name }} - {{ m.reason }}</li>
                                    </ul>
                                    <span v-else>Tidak ada</span>
                                </div>
                            </div>
                            <div class="leader-breakdown-item is-warning has-tooltip">
                                <span class="leader-breakdown-item__val">{{ teamStats.overtimeCount }}</span>
                                <span class="leader-breakdown-item__lbl">Lembur</span>
                                <div class="leader-tooltip">
                                    <div class="leader-tooltip__title">Lembur Berjalan</div>
                                    <ul v-if="teamStats.overtimeMembers.length">
                                        <li v-for="m in teamStats.overtimeMembers" :key="m.name">{{ m.name }} ({{ m.hours }}h)</li>
                                    </ul>
                                    <span v-else>Tidak ada</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. KPI TRACKER -->
            <div class="leader-tile">
                <div class="leader-tile__head">
                    <span class="leader-tile__label">Team KPI Progress</span>
                    <i class="bi bi-graph-up-arrow leader-tile__icon text-indigo"></i>
                </div>
                <div class="leader-tile__body">
                    <div class="leader-kpi">
                        <div class="leader-kpi__score">
                            <strong>{{ teamStats.kpiScore }}</strong>
                            <span>Rata-rata Skor</span>
                        </div>
                        <div class="leader-kpi__bar-wrap">
                            <div class="leader-kpi__bar-header">
                                <span>Pencapaian vs Target</span>
                                <span>{{ teamStats.kpiTarget }}</span>
                            </div>
                            <div class="leader-kpi__bar">
                                <div class="leader-kpi__fill" :style="{ width: `${(teamStats.kpiScore / teamStats.kpiTarget) * 100}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. TRAINING & DEVELOPMENT -->
            <div class="leader-tile">
                <div class="leader-tile__head">
                    <span class="leader-tile__label">Training & Dev</span>
                    <i class="bi bi-journal-bookmark leader-tile__icon text-teal"></i>
                </div>
                <div class="leader-tile__body">
                    <div class="leader-training">
                        <div class="leader-training-stat is-active">
                            <i class="bi bi-play-circle"></i>
                            <div>
                                <strong>{{ teamStats.trainingActive }}</strong>
                                <span>Active</span>
                            </div>
                        </div>
                        <div class="leader-training-stat is-completed">
                            <i class="bi bi-check2-circle"></i>
                            <div>
                                <strong>{{ teamStats.trainingCompleted }}</strong>
                                <span>Completed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMART ALERTS (OPERATIONAL GUARDRAILS) -->
        <div class="leader-alerts">
            <h3 class="leader-alerts__title">Operational Guardrails</h3>
            <div class="leader-alerts__grid">
                
                <div class="guardrail-card" :class="{ 'is-danger': guardrails.conflictShifts.length > 0 }">
                    <div class="guardrail-card__icon"><i class="bi bi-calendar-x"></i></div>
                    <div class="guardrail-card__content">
                        <div class="guardrail-card__title">Conflict Shift</div>
                        <div class="guardrail-card__desc">
                            {{ guardrails.conflictShifts.length > 0 ? `${guardrails.conflictShifts.length} Potensi bentrok` : 'Jadwal aman' }}
                        </div>
                    </div>
                    <div class="leader-tooltip" v-if="guardrails.conflictShifts.length > 0">
                        <div class="leader-tooltip__title">Detail Konflik</div>
                        <ul>
                            <li v-for="c in guardrails.conflictShifts" :key="c.name">{{ c.name }}: {{ c.issue }}</li>
                        </ul>
                    </div>
                </div>

                <div class="guardrail-card" :class="{ 'is-warning': guardrails.overtimeAlerts.length > 0 }">
                    <div class="guardrail-card__icon"><i class="bi bi-clock-history"></i></div>
                    <div class="guardrail-card__content">
                        <div class="guardrail-card__title">Overtime Limit</div>
                        <div class="guardrail-card__desc">
                            {{ guardrails.overtimeAlerts.length > 0 ? `${guardrails.overtimeAlerts.length} Mendekati batas` : 'Durasi wajar' }}
                        </div>
                    </div>
                    <div class="leader-tooltip" v-if="guardrails.overtimeAlerts.length > 0">
                        <div class="leader-tooltip__title">Overtime Alerts</div>
                        <ul>
                            <li v-for="o in guardrails.overtimeAlerts" :key="o.name">{{ o.name }} ({{ o.hours }}h)</li>
                        </ul>
                    </div>
                </div>

                <div class="guardrail-card" :class="{ 'is-info': guardrails.disciplineIssues.length > 0 }">
                    <div class="guardrail-card__icon"><i class="bi bi-person-exclamation"></i></div>
                    <div class="guardrail-card__content">
                        <div class="guardrail-card__title">Discipline</div>
                        <div class="guardrail-card__desc">
                            {{ guardrails.disciplineIssues.length > 0 ? `${guardrails.disciplineIssues.length} Isu kedisiplinan` : 'Disiplin baik' }}
                        </div>
                    </div>
                    <div class="leader-tooltip" v-if="guardrails.disciplineIssues.length > 0">
                        <div class="leader-tooltip__title">Isu Kedisiplinan</div>
                        <ul>
                            <li v-for="d in guardrails.disciplineIssues" :key="d.name">{{ d.name }}: {{ d.type }}</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        default: () => ({})
    }
});

// Mocked data for demonstration. In production, this would come from `props.data`.
const teamStats = computed(() => {
    return {
        totalMembers: 12,
        presentCount: 10,
        presentPercent: 83,
        absentCount: 2,
        overtimeCount: 3,
        absentMembers: [
            { name: 'Budi Santoso', reason: 'Sakit' },
            { name: 'Ani Yudhoyono', reason: 'Cuti Tahunan' }
        ],
        overtimeMembers: [
            { name: 'Joko Widodo', hours: 4 },
            { name: 'Susi Pudjiastuti', hours: 3 },
            { name: 'Ridwan Kamil', hours: 2 }
        ],
        kpiScore: 88,
        kpiTarget: 100,
        trainingActive: 4,
        trainingCompleted: 15
    };
});

const guardrails = computed(() => {
    return {
        conflictShifts: [
            { name: 'Ahmad Dani', issue: 'Shift Pagi & Malam di hari sama' }
        ],
        overtimeAlerts: [
            { name: 'Joko Widodo', hours: 14 } // e.g. accumulated in a week > 14
        ],
        disciplineIssues: [
            { name: 'Bagus Prakoso', type: 'Terlambat 3x berturut-turut' },
            { name: 'Citra Kirana', type: 'Pulang cepat tanpa izin' }
        ]
    };
});
</script>

<style scoped>
.leader-monitoring-card {
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.8), rgba(248, 250, 252, 0.6));
    border: 1px solid rgba(226, 232, 240, 0.8);
    backdrop-filter: blur(14px);
    border-radius: 1.25rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255,255,255,0.7);
}

.leader-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.leader-header__title-group {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.leader-header__icon {
    width: 2.8rem;
    height: 2.8rem;
    border-radius: 0.8rem;
    background: linear-gradient(135deg, #4f46e5, #3b82f6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 6px 14px rgba(79, 70, 229, 0.2);
}

.leader-header__subtitle {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
}

.leader-header__title {
    font-size: 1.15rem;
    font-weight: 900;
    color: #0f172a;
    margin: 0.1rem 0 0 0;
}

.leader-action-btn {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.6rem;
    background: rgba(241, 245, 249, 0.8);
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.leader-action-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.leader-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

.leader-tile {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(226, 232, 240, 0.6);
    border-radius: 1rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.leader-tile__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.leader-tile__label {
    font-size: 0.7rem;
    font-weight: 800;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.leader-tile__icon {
    font-size: 1.1rem;
    color: #94a3b8;
}

.leader-tile__icon.text-indigo { color: #6366f1; }
.leader-tile__icon.text-teal { color: #14b8a6; }

/* ATTENDANCE */
.leader-attendance {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.leader-attendance__main {
    display: flex;
    flex-direction: column;
}

.leader-attendance__main strong {
    font-size: 1.4rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}

.leader-attendance__main span {
    font-size: 0.65rem;
    color: #64748b;
    margin-top: 0.2rem;
    font-weight: 600;
}

.leader-attendance__breakdown {
    display: flex;
    gap: 0.5rem;
}

.leader-breakdown-item {
    position: relative;
    padding: 0.3rem 0.6rem;
    border-radius: 0.6rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #f8fafc;
    cursor: pointer;
}

.leader-breakdown-item.is-danger { background: #fee2e2; color: #b91c1c; }
.leader-breakdown-item.is-warning { background: #fef3c7; color: #b45309; }

.leader-breakdown-item__val {
    font-size: 0.9rem;
    font-weight: 900;
    line-height: 1;
}

.leader-breakdown-item__lbl {
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-top: 0.15rem;
}

/* KPI */
.leader-kpi {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.leader-kpi__score strong {
    font-size: 1.4rem;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}

.leader-kpi__score span {
    font-size: 0.65rem;
    color: #64748b;
    margin-left: 0.4rem;
    font-weight: 600;
}

.leader-kpi__bar-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.6rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 0.3rem;
}

.leader-kpi__bar {
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.leader-kpi__fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    border-radius: 999px;
    transition: width 1s ease-out;
}

/* TRAINING */
.leader-training {
    display: flex;
    gap: 0.8rem;
}

.leader-training-stat {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem;
    border-radius: 0.8rem;
    background: #f8fafc;
}

.leader-training-stat i {
    font-size: 1.4rem;
}

.leader-training-stat.is-active i { color: #3b82f6; }
.leader-training-stat.is-completed i { color: #10b981; }

.leader-training-stat div {
    display: flex;
    flex-direction: column;
}

.leader-training-stat strong {
    font-size: 1.1rem;
    font-weight: 900;
    line-height: 1;
    color: #0f172a;
}

.leader-training-stat span {
    font-size: 0.6rem;
    font-weight: 700;
    color: #64748b;
    margin-top: 0.15rem;
    text-transform: uppercase;
}

/* ALERTS */
.leader-alerts {
    margin-top: 0.5rem;
    padding-top: 1.25rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.8);
}

.leader-alerts__title {
    font-size: 0.75rem;
    font-weight: 900;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 0.8rem 0;
}

.leader-alerts__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.8rem;
}

.guardrail-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.7rem 0.8rem;
    border-radius: 0.8rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    cursor: pointer;
}

.guardrail-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.guardrail-card__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: #e2e8f0;
    color: #64748b;
}

.guardrail-card.is-danger {
    background: #fff1f2;
    border-color: #fecaca;
}
.guardrail-card.is-danger .guardrail-card__icon {
    background: #fee2e2;
    color: #e11d48;
}

.guardrail-card.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
}
.guardrail-card.is-warning .guardrail-card__icon {
    background: #fef3c7;
    color: #d97706;
}

.guardrail-card.is-info {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.guardrail-card.is-info .guardrail-card__icon {
    background: #dbeafe;
    color: #2563eb;
}

.guardrail-card__title {
    font-size: 0.7rem;
    font-weight: 800;
    color: #0f172a;
}

.guardrail-card__desc {
    font-size: 0.6rem;
    color: #475569;
    margin-top: 0.15rem;
    font-weight: 600;
}

/* TOOLTIP */
.leader-tooltip {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%) translateY(10px);
    background: rgba(15, 23, 42, 0.95);
    color: #fff;
    padding: 0.75rem;
    border-radius: 0.75rem;
    font-size: 0.65rem;
    width: max-content;
    max-width: 200px;
    z-index: 100;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    pointer-events: none;
}

.leader-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: rgba(15, 23, 42, 0.95) transparent transparent transparent;
}

.has-tooltip:hover .leader-tooltip,
.guardrail-card:hover .leader-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.leader-tooltip__title {
    font-weight: 800;
    margin-bottom: 0.4rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-size: 0.55rem;
    letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 0.3rem;
}

.leader-tooltip ul {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: left;
}

.leader-tooltip li {
    margin-bottom: 0.25rem;
    line-height: 1.3;
}

.leader-tooltip li:last-child {
    margin-bottom: 0;
}

/* Mobile Adjustments */
@media (max-width: 767.98px) {
    .leader-grid {
        grid-template-columns: 1fr;
    }
    
    .leader-alerts__grid {
        grid-template-columns: 1fr;
    }
}
</style>
