<template>
    <div class="leader-dashboard-page admin-activity-page">
        <div class="leader-dashboard-container">
            <!-- HERO -->
            <section class="leader-hero">
                <div class="leader-hero__content">
                    <div class="hero-left">
                        <div class="hero-topline">
                            <div class="hero-eyebrow">
                                <i class="bi bi-shield-lock-fill"></i>
                                Admin Area
                            </div>
                            <span class="period-pill">
                                <i class="bi bi-activity"></i>
                                Real-time
                            </span>
                        </div>
                        <div class="hero-title-row">
                            <h1>Monitoring Aktivitas</h1>
                            <p>Pantau adopsi modul, anomali keamanan, dan aktivitas user secara real-time.</p>
                        </div>
                        <div class="hero-brief-grid">
                            <div class="action-buttons-hero mt-3" style="display:flex; gap:0.5rem;">
                                <el-button @click="reloadAll" :loading="loadingAny" type="primary" round>
                                    <i class="bi bi-arrow-repeat me-1"></i> Muat ulang
                                </el-button>
                                <el-button @click="exportXlsx" type="success" plain round>
                                    <i class="bi bi-file-earmark-excel me-1"></i> Export XLSX
                                </el-button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TAB SWITCHER: Aktivitas Pengguna | Kesehatan Sistem -->
            <div class="aa-maintabs mt-3" style="display:flex; justify-content:center;">
                <el-radio-group v-model="mainTab" size="large">
                    <el-radio-button label="activity"><i class="bi bi-people-fill me-1"></i> Aktivitas Pengguna</el-radio-button>
                    <el-radio-button label="health"><i class="bi bi-heart-pulse-fill me-1"></i> Kesehatan Sistem</el-radio-button>
                </el-radio-group>
            </div>

            <div v-show="mainTab === 'activity'"><!-- tab: activity -->
            <!-- COMPACT STATS -->
            <section class="quick-stat-grid is-leader-focused mt-4">
                <div class="quick-stat-card is-success">
                    <div class="quick-stat-card__icon"><i class="bi bi-people-fill"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ kpis.active_now ?? '—' }}</strong>
                        <span>Active now</span>
                        <small>15 menit terakhir</small>
                    </div>
                </div>
                <div class="quick-stat-card is-primary">
                    <div class="quick-stat-card__icon"><i class="bi bi-graph-up"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ kpis.dau ?? '—' }}</strong>
                        <span>DAU</span>
                        <small :class="deltaClass(kpis.dau_delta)">{{ deltaText(kpis.dau_delta) }} vs kemarin</small>
                    </div>
                </div>
                <div class="quick-stat-card is-info">
                    <div class="quick-stat-card__icon"><i class="bi bi-calendar-check"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ kpis.mau ?? '—' }}</strong>
                        <span>MAU</span>
                        <small>30 hari</small>
                    </div>
                </div>
                <div class="quick-stat-card is-teal">
                    <div class="quick-stat-card__icon"><i class="bi bi-stopwatch"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ kpis.avg_session_min ?? 0 }} <span style="font-size:0.8rem">mnt</span></strong>
                        <span>Avg session</span>
                        <small>30 hari</small>
                    </div>
                </div>
                <div class="quick-stat-card is-danger" :class="{ 'is-priority': (kpis.failed_24h || 0) > 0 }">
                    <div class="quick-stat-card__icon"><i class="bi bi-shield-exclamation"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ kpis.failed_24h ?? 0 }}</strong>
                        <span>Login gagal (24j)</span>
                        <small>{{ formatNumber(kpis.total_events_24h ?? 0) }} total events</small>
                    </div>
                </div>
            </section>

            <!-- 2 kolom: Heatmap + Top modules -->
            <section class="aa-grid mt-4">
                <div class="insight-card">
                    <div class="insight-card__head is-between" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                        <div>
                            <span class="section-label">Trafik</span>
                            <h2 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin:0;">Heatmap aktivitas (jam × hari)</h2>
                        </div>
                        <el-select v-model="heatmapDays" size="small" style="width: 120px" @change="loadHeatmap">
                            <el-option :value="7" label="7 hari" />
                            <el-option :value="30" label="30 hari" />
                            <el-option :value="90" label="90 hari" />
                        </el-select>
                    </div>
                    <div v-if="heatmap.loading" class="empty-state">
                        <i class="bi bi-arrow-repeat action-loading-spin"></i>
                        <span>Memuat...</span>
                    </div>
                    <div v-else class="heatmap" style="overflow-x:auto;">
                        <table class="heatmap-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th v-for="h in 24" :key="h">{{ h - 1 }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(dayName, i) in dowNames" :key="i">
                                    <th>{{ dayName }}</th>
                                    <td
                                        v-for="h in 24"
                                        :key="h"
                                        class="cell"
                                        :style="{ background: heatColor(getHits(i + 1, h - 1)) }"
                                        :title="dayName + ' ' + (h-1) + ':00 — ' + getHits(i + 1, h - 1) + ' event'"
                                    ></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-card__head is-between" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                        <div>
                            <span class="section-label">Aksesibilitas</span>
                            <h2 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin:0;">Top modul</h2>
                        </div>
                        <el-select v-model="topModulesDays" size="small" style="width: 120px" @change="loadTopModules">
                            <el-option :value="7" label="7 hari" />
                            <el-option :value="30" label="30 hari" />
                        </el-select>
                    </div>
                    <div v-if="topModules.loading" class="empty-state">
                        <i class="bi bi-arrow-repeat action-loading-spin"></i>
                        <span>Memuat...</span>
                    </div>
                    <div v-else class="modules-list">
                        <div v-for="m in topModules.items" :key="m.Id_Grup_Fitur" class="module-row">
                            <span class="m-name">{{ m.Nama_Grup_Fitur || m.Kode_Grup_Fitur }}</span>
                            <span class="m-bar-wrap">
                                <span class="m-bar" :style="{ width: percentBar(m.Hits) + '%' }"></span>
                            </span>
                            <span class="m-hits">{{ formatNumber(m.Hits) }}</span>
                            <span class="m-users">{{ m.Users }} user</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Anomaly feed -->
            <section class="insight-card aa-anomalies mt-4">
                <div class="insight-card__head" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                    <span class="section-label">Alerts</span>
                    <h2 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin:0;">Anomaly feed (24 jam)</h2>
                </div>
                <div v-if="anomalies.loading" class="empty-state">
                    <i class="bi bi-arrow-repeat action-loading-spin"></i>
                    <span>Memuat...</span>
                </div>
                <div v-else-if="!anomalies.items.length" class="empty-state">
                    <i class="bi bi-shield-check text-success" style="font-size:2.5rem;"></i>
                    <span>Tidak ada anomali terdeteksi.</span>
                </div>
                <div v-else class="anomaly-list">
                    <div
                        v-for="(a, idx) in anomalies.items"
                        :key="idx"
                        class="anomaly-item"
                        :class="'sev-' + a.severity"
                    >
                        <div class="aa-sev">{{ a.severity }}</div>
                        <div class="aa-body">
                            <div class="aa-title">{{ a.title }}</div>
                            <div class="muted">
                                User: <strong>{{ a.kode_karyawan || '—' }}</strong>
                                <span v-if="a.ip"> · IP {{ a.ip }}</span>
                            </div>
                        </div>
                        <div class="aa-time">{{ formatDateTime(a.at) }}</div>
                        <el-button
                            v-if="a.kode_karyawan"
                            size="small"
                            type="danger"
                            plain
                            @click="openUser(a.kode_karyawan)"
                        >Buka</el-button>
                    </div>
                </div>
            </section>

            <!-- Tabel user -->
            <section class="insight-card mt-4 mb-4">
                <div class="insight-card__head" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                    <span class="section-label">Detail</span>
                    <h2 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin:0;">Aktivitas per user</h2>
                </div>

                <div class="filters">
                    <el-input
                        v-model="filters.q"
                        placeholder="Cari kode/nama karyawan…"
                        clearable
                        style="max-width: 260px"
                        @change="reloadUsers"
                    >
                        <template #prefix>
                            <i class="bi bi-search"></i>
                        </template>
                    </el-input>
                    <el-select
                        v-model="filters.module_id"
                        placeholder="Modul"
                        clearable
                        style="min-width: 200px"
                        @change="reloadUsers"
                    >
                        <el-option
                            v-for="m in modules"
                            :key="m.id"
                            :label="m.nama"
                            :value="m.id"
                        />
                    </el-select>
                    <el-date-picker
                        v-model="filters.range"
                        type="daterange"
                        range-separator="—"
                        start-placeholder="Dari"
                        end-placeholder="Sampai"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :shortcuts="dateShortcuts"
                        @change="reloadUsers"
                    />
                </div>

                <div class="table-responsive custom-table-wrapper mt-3">
                    <el-table
                        :data="users.items"
                        v-loading="users.loading"
                        style="width: 100%"
                        stripe
                        @sort-change="onSortChange"
                    >
                        <el-table-column prop="Kode_Karyawan" label="Kode" width="120" sortable="custom" />
                        <el-table-column label="Nama">
                            <template #default="{ row }">
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <div class="avatar-placeholder"><i class="bi bi-person"></i></div>
                                    <div>
                                        <strong style="color:#0f172a;">{{ row.Nama_Karyawan || '—' }}</strong>
                                        <div class="muted" style="font-size:0.75rem;">{{ row.Kode_Karyawan }}</div>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="Total_Events" label="Event" width="100" sortable="custom" align="right">
                            <template #default="{ row }"><strong>{{ formatNumber(row.Total_Events) }}</strong></template>
                        </el-table-column>
                        <el-table-column prop="Total_Login" label="Login" width="80" sortable="custom" align="right">
                            <template #default="{ row }"><strong>{{ row.Total_Login }}</strong></template>
                        </el-table-column>
                        <el-table-column label="Gagal" width="80" align="right">
                            <template #default="{ row }">
                                <span :class="{ 'danger-text': row.Total_Failed > 0 }">{{ row.Total_Failed }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Modul Terbanyak" min-width="160">
                            <template #default="{ row }">{{ row.Top_Module_Name || '—' }}</template>
                        </el-table-column>
                        <el-table-column label="IP" width="80" align="right">
                            <template #default="{ row }">{{ row.Distinct_Ips }}</template>
                        </el-table-column>
                        <el-table-column label="Modul" width="80" align="right">
                            <template #default="{ row }">{{ row.Distinct_Modules }}</template>
                        </el-table-column>
                        <el-table-column label="Terakhir Aktif" width="180" sortable="custom" prop="Last_Activity_At">
                            <template #default="{ row }"><span class="muted">{{ formatDateTime(row.Last_Activity_At) }}</span></template>
                        </el-table-column>
                        <el-table-column label="" width="100" fixed="right">
                            <template #default="{ row }">
                                <el-button type="primary" plain size="small" @click="openUser(row.Kode_Karyawan)">Detail</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <div class="pagination mt-3">
                    <el-pagination
                        v-model:current-page="users.page"
                        v-model:page-size="users.perPage"
                        :total="users.total"
                        :page-sizes="[25, 50, 100, 200]"
                        layout="total, sizes, prev, pager, next"
                        @current-change="reloadUsers"
                        @size-change="reloadUsers"
                        background
                    />
                </div>
            </section>
            </div><!-- /tab: activity -->

            <div v-show="mainTab === 'health'"><!-- tab: health -->
                <SystemHealth />
            </div>
        </div>

        <!-- Drawer drill-down user -->
        <el-drawer v-model="drawer.open" :title="drawer.title" direction="rtl" size="640px" class="custom-drawer">
            <div v-if="!drawer.data" class="empty-state">
                <i class="bi bi-arrow-repeat action-loading-spin"></i>
                <span>Memuat...</span>
            </div>
            <div v-else>
                <div class="dd-header insight-card shadow-sm p-3 m-0 mb-3" style="display:flex; align-items:center; gap:1rem;">
                    <div class="avatar-placeholder" style="width:40px; height:40px; font-size:1.5rem;"><i class="bi bi-person"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:#0f172a;">{{ drawer.data.karyawan.Nama_Karyawan }}</h3>
                        <div class="muted">{{ drawer.data.karyawan.Kode_Karyawan }} · UserID {{ drawer.data.karyawan.UserID_Web }}</div>
                    </div>
                </div>

                <el-tabs v-model="drawer.tab" class="custom-tabs">
                    <el-tab-pane name="sessions">
                        <template #label><i class="bi bi-layout-text-window"></i> Sesi (50)</template>
                        <div v-if="!drawer.data.sessions?.length" class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <span>Tidak ada sesi.</span>
                        </div>
                        <div v-else class="dd-list">
                            <div v-for="s in drawer.data.sessions" :key="s.Session_Id" class="dd-row insight-card shadow-sm">
                                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                                    <i class="bi bi-calendar2-week text-primary"></i>
                                    <strong>{{ formatDateTime(s.Login_At) }}</strong>
                                    <span class="muted">— {{ formatDateTime(s.Logout_At) || 'aktif' }}</span>
                                </div>
                                <div class="muted" style="margin-left:1.5rem;">
                                    {{ s.Browser }} · {{ s.Os }} · {{ s.Ip_Address }} ({{ s.Ip_City || s.Ip_Country || '—' }})
                                </div>
                                <div class="muted" style="margin-left:1.5rem;">
                                    {{ s.Total_Events }} event · {{ Math.round((s.Active_Seconds||0)/60) }} mnt
                                    <span v-if="s.Logout_Type === 'forced'" class="danger-text">· FORCED</span>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>
                    <el-tab-pane name="events">
                        <template #label><i class="bi bi-list-nested"></i> Event 7 hari</template>
                        <div v-if="!drawer.data.events?.length" class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <span>Tidak ada event.</span>
                        </div>
                        <div v-else class="dd-list dd-events">
                            <div v-for="e in drawer.data.events" :key="e.Event_Id" class="dd-row timeline-item is-open" style="border:1px solid #e2e8f0;">
                                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                                    <span class="mini-badge" :class="e.Http_Status >= 400 ? 'is-danger' : 'is-info'">{{ e.Event_Type }}</span>
                                    <strong style="font-size:0.9rem; color:#0f172a;">{{ e.Description || e.Action_Code || e.Path }}</strong>
                                </div>
                                <div class="muted" style="font-size:0.75rem;">
                                    {{ formatDateTime(e.Created_At) }} · {{ e.Module_Name || e.Module_Code || '—' }}
                                    <span v-if="e.Http_Status" :class="e.Http_Status >= 400 ? 'text-danger fw-bold' : ''"> · HTTP {{ e.Http_Status }}</span>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>
                    <el-tab-pane name="devices">
                        <template #label><i class="bi bi-phone"></i> Perangkat</template>
                        <div v-if="!drawer.data.devices?.length" class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <span>Belum ada perangkat.</span>
                        </div>
                        <div v-else class="dd-list">
                            <div v-for="d in drawer.data.devices" :key="d.Device_Fingerprint" class="dd-row insight-card shadow-sm">
                                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                                    <i class="bi bi-display text-primary"></i>
                                    <strong style="color:#0f172a;">{{ d.Label || (d.Last_Browser + ' · ' + d.Last_Os) }}</strong>
                                </div>
                                <div class="muted" style="margin-left:1.5rem;">
                                    Pertama: {{ formatDateTime(d.First_Seen_At) }} · Terakhir: {{ formatDateTime(d.Last_Seen_At) }}
                                </div>
                                <div class="muted" style="margin-left:1.5rem;">FP: {{ (d.Device_Fingerprint || '').slice(0, 16) }}…</div>
                            </div>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import SystemHealth from './SystemHealth.vue';

const props = defineProps({
    modules: { type: Array, default: () => [] },
});

// Tab utama: 'activity' (existing) | 'health' (diagnostik sistem baru)
const mainTab = ref('activity');

const dowNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const kpis = ref({});
const heatmap = reactive({ loading: false, items: [] });
const heatmapDays = ref(7);
const topModules = reactive({ loading: false, items: [] });
const topModulesDays = ref(7);
const anomalies = reactive({ loading: false, items: [] });
const users = reactive({ loading: false, items: [], page: 1, perPage: 25, total: 0, sort: 'last_activity', dir: 'desc' });

const filters = reactive({
    q: '',
    module_id: null,
    range: defaultRange(),
});

const drawer = reactive({ open: false, title: '', data: null, tab: 'sessions' });

const dateShortcuts = [
    { text: 'Hari ini', value: () => { const d = new Date(); return [fmtDate(d), fmtDate(d)]; } },
    { text: '7 hari', value: () => { const e = new Date(); const s = new Date(); s.setDate(s.getDate() - 6); return [fmtDate(s), fmtDate(e)]; } },
    { text: '30 hari', value: () => { const e = new Date(); const s = new Date(); s.setDate(s.getDate() - 29); return [fmtDate(s), fmtDate(e)]; } },
];

function fmtDate(d) { return d.toISOString().slice(0, 10); }
function defaultRange() {
    const e = new Date(); const s = new Date(); s.setDate(s.getDate() - 6);
    return [fmtDate(s), fmtDate(e)];
}

const loadingAny = computed(() => kpis.loading || heatmap.loading || users.loading || anomalies.loading);

const maxHeat = computed(() => Math.max(1, ...heatmap.items.map((r) => r.hits)));
const maxModuleHits = computed(() => Math.max(1, ...topModules.items.map((r) => r.Hits || 0)));

function getHits(dow, hour) {
    const m = heatmap.items.find((r) => r.dow === dow && r.hour === hour);
    return m ? m.hits : 0;
}
function heatColor(hits) {
    if (!hits) return '#f8fafc';
    const ratio = Math.min(1, hits / maxHeat.value);
    // green → red ramp
    const r = Math.round(248 + (220 - 248) * ratio); // 248 f8 -> 220
    const g = Math.round(250 - (250 - 38) * ratio);
    const b = Math.round(252 - (252 - 38) * ratio);
    return `rgb(${r}, ${g}, ${b})`;
}
function percentBar(hits) {
    return Math.max(2, Math.round((hits / maxModuleHits.value) * 100));
}

async function loadKpis() {
    try {
        const { data } = await axios.get('/admin/activity/kpis');
        kpis.value = data?.result || {};
    } catch {
        ElMessage.error('Gagal memuat KPI');
    }
}
async function loadHeatmap() {
    heatmap.loading = true;
    try {
        const { data } = await axios.get('/admin/activity/heatmap', { params: { days: heatmapDays.value } });
        heatmap.items = data?.result || [];
    } catch {
        ElMessage.error('Gagal memuat heatmap');
    } finally {
        heatmap.loading = false;
    }
}
async function loadTopModules() {
    topModules.loading = true;
    try {
        const { data } = await axios.get('/admin/activity/top-modules', { params: { days: topModulesDays.value } });
        topModules.items = data?.result || [];
    } catch {
        ElMessage.error('Gagal memuat top modul');
    } finally {
        topModules.loading = false;
    }
}
async function loadAnomalies() {
    anomalies.loading = true;
    try {
        const { data } = await axios.get('/admin/activity/anomalies');
        anomalies.items = data?.result || [];
    } catch {
        ElMessage.error('Gagal memuat anomalies');
    } finally {
        anomalies.loading = false;
    }
}
async function loadUsers() {
    users.loading = true;
    try {
        const params = {
            q: filters.q || undefined,
            module_id: filters.module_id || undefined,
            from: filters.range?.[0],
            to: filters.range?.[1],
            page: users.page,
            per_page: users.perPage,
            sort: users.sort,
            dir: users.dir,
        };
        const { data } = await axios.get('/admin/activity/users', { params });
        users.items = data?.result || [];
        users.total = data?.pagination?.total ?? users.items.length;
    } catch {
        ElMessage.error('Gagal memuat tabel user');
    } finally {
        users.loading = false;
    }
}
function reloadUsers() {
    users.page = 1;
    loadUsers();
}
function onSortChange({ prop, order }) {
    if (!order) {
        users.sort = 'last_activity';
        users.dir = 'desc';
    } else {
        const map = {
            'Kode_Karyawan': 'kode',
            'Total_Events': 'total_events',
            'Total_Login': 'total_login',
            'Last_Activity_At': 'last_activity',
        };
        users.sort = map[prop] || 'last_activity';
        users.dir = order === 'ascending' ? 'asc' : 'desc';
    }
    loadUsers();
}

async function openUser(kode) {
    drawer.open = true;
    drawer.title = 'Detail Aktivitas: ' + kode;
    drawer.data = null;
    drawer.tab = 'sessions';
    try {
        const { data } = await axios.get(`/admin/activity/users/${encodeURIComponent(kode)}`);
        drawer.data = data?.result;
    } catch {
        ElMessage.error('Gagal memuat detail user');
        drawer.open = false;
    }
}

function exportXlsx() {
    const params = new URLSearchParams();
    if (filters.q) params.set('q', filters.q);
    if (filters.module_id) params.set('module_id', filters.module_id);
    if (filters.range?.[0]) params.set('from', filters.range[0]);
    if (filters.range?.[1]) params.set('to', filters.range[1]);
    window.location.href = '/admin/activity/export?' + params.toString();
}

function reloadAll() {
    loadKpis();
    loadHeatmap();
    loadTopModules();
    loadAnomalies();
    loadUsers();
}

/* Formatters */
function formatDateTime(s) { return s ? new Date(s).toLocaleString('id-ID', { hour12: false }) : '—'; }
function formatNumber(n) { return (n ?? 0).toLocaleString('id-ID'); }
function deltaClass(d) { return (d || 0) >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold'; }
function deltaText(d) {
    d = d || 0;
    const sign = d > 0 ? '+' : '';
    return `${sign}${d}`;
}

onMounted(() => reloadAll());
</script>

<style scoped>
/* Copied Leader Dashboard Layout Styles */
.leader-dashboard-page { width: 100%; min-width: 0; min-height: auto; color: var(--hcis-slate-900); }
.leader-dashboard-container { width: 100%; min-width: 0; margin: 0 auto; padding-bottom: 2rem; }

.section-label { display: block; font-size: 0.58rem; font-weight: 950; letter-spacing: 0.08em; text-transform: uppercase; color: var(--hcis-slate-500); }
.mini-badge { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; background: var(--hcis-slate-200); color: var(--hcis-slate-600); }
.mini-badge.is-success { background: var(--hcis-green-100); color: #166534; }
.mini-badge.is-danger { background: var(--hcis-red-100); color: var(--hcis-red-800); }
.mini-badge.is-info { background: var(--hcis-indigo-100); color: var(--hcis-indigo-800); }

/* Hero */
.leader-hero {
    position: relative; overflow: hidden; border-radius: 1rem; padding: 1.25rem; color: white;
    background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 17rem), linear-gradient(135deg, var(--hcis-slate-900), var(--hcis-slate-700));
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.18); margin-bottom: 1rem;
}
.leader-hero__content { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 330px); gap: 1rem; }
@media (max-width: 768px) { .leader-hero__content { grid-template-columns: 1fr; } }
.hero-topline { display: flex; gap: 0.5rem; align-items: center; justify-content: start; flex-wrap: wrap; }
.hero-eyebrow, .period-pill { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.6rem; border-radius: 999px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.18); font-size: 0.7rem; font-weight: 800; }
.period-pill { color: rgba(255, 255, 255, 0.9); }
.hero-title-row { margin-top: 1rem; }
.hero-title-row h1 { margin: 0; color: white; font-size: clamp(1.5rem, 2.5vw, 2.2rem); font-weight: 900; letter-spacing: -0.04em; }
.hero-title-row p { margin: 0.5rem 0 0; color: rgba(255, 255, 255, 0.84); font-size: 0.85rem; line-height: 1.5; max-width: 600px; }

/* Quick Stats Grid */
.quick-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
.quick-stat-card {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    padding: 0.85rem 1rem;
    background: #fff;
    border-radius: 1rem;
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    will-change: transform, box-shadow;
    transition: transform 350ms cubic-bezier(0.16, 1, 0.3, 1), box-shadow 350ms cubic-bezier(0.16, 1, 0.3, 1), border-color 250ms ease;
}
.quick-stat-card:hover {
    transform: translateY(-6px);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 20px 35px -8px rgba(15, 23, 42, 0.12), 0 6px 12px -3px rgba(15, 23, 42, 0.04);
}
.quick-stat-card:hover .quick-stat-card__icon {
    transform: scale(1.12);
}
.quick-stat-card__icon { display: grid; place-items: center; width: 2.8rem; height: 2.8rem; border-radius: 0.75rem; font-size: 1.25rem; flex-shrink: 0; }
.quick-stat-card.is-primary .quick-stat-card__icon { background: var(--hcis-indigo-100); color: var(--hcis-indigo-600); }
.quick-stat-card.is-info .quick-stat-card__icon { background: var(--hcis-sky-100); color: var(--hcis-sky-600); }
.quick-stat-card.is-teal .quick-stat-card__icon { background: #ccfbf1; color: #0d9488; }
.quick-stat-card.is-success .quick-stat-card__icon { background: var(--hcis-green-100); color: #166534; }
.quick-stat-card.is-danger .quick-stat-card__icon { background: var(--hcis-red-100); color: var(--hcis-red-600); }
.quick-stat-card__copy strong { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.2; color: var(--hcis-slate-900); }
.quick-stat-card__copy span { display: block; font-size: 0.75rem; font-weight: 600; color: var(--hcis-slate-600); margin-top: 0.15rem; }
.quick-stat-card__copy small { display: block; font-size: 0.65rem; color: var(--hcis-slate-400); }

/* 2 Columns layout */
.aa-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem; }
@media (max-width: 1100px) { .aa-grid { grid-template-columns: 1fr; } }

/* Insight Card */
.insight-card { background: #fff; border-radius: 1rem; border: 1px solid var(--hcis-slate-200); padding: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.insight-card__head { margin-bottom: 1rem; }
.insight-card__head.is-between { display: flex; justify-content: space-between; align-items: flex-start; }

.empty-state { padding: 4rem 2rem; text-align: center; color: var(--hcis-slate-400); display: flex; flex-direction: column; align-items: center; gap: 1rem; }
.empty-state i { font-size: 2.5rem; color: var(--hcis-slate-300); }
.empty-state span { font-size: 0.9rem; font-weight: 500; }
.action-loading-spin { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Heatmap */
.heatmap-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
.heatmap-table th { font-weight: 600; color: var(--hcis-slate-500); padding: 0.25rem; text-align: center; }
.heatmap-table tbody th { text-align: right; padding-right: 0.75rem; }
.cell { width: 1.7rem; height: 1.7rem; border: 2px solid #fff; border-radius: 4px; transition: transform 0.1s ease; }
.cell:hover { transform: scale(1.1); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

/* Modules */
.modules-list { display: flex; flex-direction: column; gap: 0.75rem; }
.module-row { display: grid; grid-template-columns: minmax(120px, 1fr) 2fr 60px 80px; gap: 0.5rem; align-items: center; font-size: 0.85rem; }
.m-name { font-weight: 600; color: var(--hcis-slate-700); }
.m-bar-wrap { background: var(--hcis-slate-100); height: 0.4rem; border-radius: 9999px; overflow: hidden; }
.m-bar { display: block; height: 100%; background: var(--hcis-indigo-600); border-radius: 9999px; }
.m-hits { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--hcis-slate-900); }
.m-users { color: var(--hcis-slate-500); font-size: 0.75rem; text-align: right; }

/* Anomalies */
.anomaly-list { display: flex; flex-direction: column; gap: 0.75rem; }
.anomaly-item { display: grid; grid-template-columns: 80px 1fr 180px auto; gap: 1rem; align-items: center; padding: 1rem; border-radius: 0.75rem; background: var(--hcis-slate-50); border: 1px solid var(--hcis-slate-200); }
.anomaly-item.sev-high { background: var(--hcis-red-50); border-color: var(--hcis-red-200); }
.anomaly-item.sev-medium { background: var(--hcis-amber-50); border-color: var(--hcis-amber-200); }
.aa-sev { text-transform: uppercase; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; padding: 0.25rem 0.5rem; border-radius: 0.5rem; text-align: center; }
.sev-high .aa-sev { color: var(--hcis-red-700); background: var(--hcis-red-100); }
.sev-medium .aa-sev { color: var(--hcis-amber-700); background: var(--hcis-amber-100); }
.aa-title { font-weight: 600; color: var(--hcis-slate-900); font-size: 0.95rem; margin-bottom: 0.15rem; }
.aa-time { color: var(--hcis-slate-500); font-size: 0.8rem; font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; }

/* Filters & Table */
.filters { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.custom-table-wrapper :deep(.el-table) { border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--hcis-slate-200); }
.custom-table-wrapper :deep(.el-table th) { background-color: var(--hcis-slate-50) !important; color: var(--hcis-slate-600); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
.avatar-placeholder { width: 32px; height: 32px; border-radius: 50%; background: var(--hcis-indigo-100); color: var(--hcis-indigo-600); display: grid; place-items: center; font-size: 1.1rem; flex-shrink: 0; }
.danger-text { color: var(--hcis-red-600); font-weight: 700; }
.pagination { display: flex; justify-content: flex-end; }

/* Drawer bridged globally to HcisDialog design system */
.dd-list { display: flex; flex-direction: column; gap: 0.75rem; }
.dd-row { padding: 1rem; border-radius: 0.75rem; background: #fff; }

/* Tabs inside Drawer */
.custom-tabs :deep(.el-tabs__nav-wrap::after) { height: 1px; background-color: var(--hcis-slate-200); }
.custom-tabs :deep(.el-tabs__item) { font-weight: 600; font-size: 0.9rem; color: var(--hcis-slate-500); }
.custom-tabs :deep(.el-tabs__item.is-active) { color: var(--hcis-indigo-600); }
.custom-tabs :deep(.el-tabs__active-bar) { background-color: var(--hcis-indigo-600); height: 3px; border-radius: 3px 3px 0 0; }

.muted { color: var(--hcis-slate-500); font-size: 0.85rem; }
</style>
