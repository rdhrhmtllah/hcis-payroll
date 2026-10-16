<template>
    <div class="leader-dashboard-page my-activity-page">
        <div class="leader-dashboard-container">
            <!-- HERO -->
            <section class="leader-hero">
                <div class="leader-hero__content">
                    <div class="hero-left">
                        <div class="hero-topline">
                            <div class="hero-eyebrow">
                                <i class="bi bi-shield-check"></i>
                                Keamanan & Aktivitas
                            </div>
                            <span class="period-pill">
                                <i class="bi bi-person-lines-fill"></i>
                                Akun Saya
                            </span>
                        </div>
                        <div class="hero-title-row">
                            <h1>Aktivitas Saya</h1>
                            <p>Jejak login, modul yang diakses, perangkat, dan kejadian keamanan dari akun Anda.</p>
                        </div>
                    </div>
                    <div class="hero-right" style="align-self: center;">
                        <div class="hero-focus-card" style="display:flex; flex-direction:column; justify-content:center; padding: 1.25rem; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-radius: 0.75rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap: 1rem;">
                                <div>
                                    <span style="display:block; color:rgba(255,255,255,0.72); font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">Lacak Lokasi Presisi</span>
                                    <strong style="display:block; color:white; font-size:1.1rem; font-weight:800; margin-top:0.25rem;">Akses GPS (Opsional)</strong>
                                </div>
                                <div class="ma-gps-toggle-hero" @click.stop>
                                    <el-switch
                                        v-model="gpsOptIn"
                                        inline-prompt
                                        active-text="ON"
                                        inactive-text="OFF"
                                        @change="onGpsToggle"
                                        style="--el-switch-on-color: #10b981;"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- COMPACT STATS -->
            <section class="quick-stat-grid is-leader-focused">
                <div class="quick-stat-card is-primary">
                    <div class="quick-stat-card__icon"><i class="bi bi-box-arrow-in-right"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ summary.login_7d ?? 0 }}</strong>
                        <span>Login</span>
                        <small>7 hari terakhir</small>
                    </div>
                </div>
                <div class="quick-stat-card is-info">
                    <div class="quick-stat-card__icon"><i class="bi bi-clock-history"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ formatMinutes(summary.active_minutes_7d) }}</strong>
                        <span>Total aktif</span>
                        <small>7 hari terakhir</small>
                    </div>
                </div>
                <div class="quick-stat-card is-teal">
                    <div class="quick-stat-card__icon"><i class="bi bi-star-fill"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong class="text-truncate" style="max-width:120px; display:inline-block;">{{ summary.top_module?.Nama_Grup_Fitur ?? summary.top_module?.Kode_Grup_Fitur ?? '—' }}</strong>
                        <span>Modul favorit</span>
                        <small>{{ summary.top_module?.Hits ?? 0 }} akses</small>
                    </div>
                </div>
                <div class="quick-stat-card is-purple">
                    <div class="quick-stat-card__icon"><i class="bi bi-laptop"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ summary.device_count ?? 0 }}</strong>
                        <span>Perangkat dikenali</span>
                        <small>Akses terdaftar</small>
                    </div>
                </div>
                <div class="quick-stat-card is-danger" :class="{ 'is-priority': summary.failed_24h > 0 }">
                    <div class="quick-stat-card__icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="quick-stat-card__copy">
                        <strong>{{ summary.failed_24h ?? 0 }}</strong>
                        <span>Login gagal</span>
                        <small>24 jam terakhir</small>
                    </div>
                </div>
            </section>

            <!-- MAIN -->
            <section class="leader-main-grid">
                <main class="leader-main" style="width:100%;">
                    <el-tabs v-model="activeTab" class="custom-tabs" @tab-change="onTabChange">
                        <el-tab-pane name="ESS/Workflow/Task/timeline">
                            <template #label><i class="bi bi-list-nested"></i> Timeline</template>
                            
                            <div class="filters" style="display:flex; gap:0.75rem; align-items:center;">
                                <el-date-picker
                                    v-model="dateRange"
                                    type="daterange"
                                    unlink-panels
                                    range-separator="—"
                                    start-placeholder="Dari"
                                    end-placeholder="Sampai"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    :shortcuts="dateShortcuts"
                                    @change="reloadTimeline"
                                    style="width: 260px"
                                />
                                <el-select
                                    v-model="eventTypeFilter"
                                    multiple
                                    collapse-tags
                                    placeholder="Tipe event"
                                    clearable
                                    @change="reloadTimeline"
                                    style="width: 220px"
                                >
                                    <el-option v-for="t in eventTypeOptions" :key="t.value" :label="t.label" :value="t.value" />
                                </el-select>
                            </div>
            
                            <div v-if="timeline.loading" class="empty-state">
                                <i class="bi bi-arrow-repeat action-loading-spin"></i>
                                <span>Memuat timeline...</span>
                            </div>
                            <div v-else-if="!timeline.items.length" class="empty-state">
                                <i class="bi bi-check2-circle"></i>
                                <span>Tidak ada aktivitas pada rentang ini.</span>
                            </div>
                            <div v-else class="timeline-list">
                                <div v-for="group in timelineGrouped" :key="group.date" class="timeline-day">
                                    <div class="timeline-date"><i class="bi bi-calendar3"></i> {{ group.date }}</div>
                                    <div
                                        v-for="ev in group.items"
                                        :key="ev.Event_Id"
                                        class="timeline-item"
                                        :class="[{ 'is-open': isExpanded(ev.Event_Id) }, iconToneClass(ev.Event_Type)]"
                                    >
                                        <button type="button" class="ti-head" @click="toggleEvent(ev)">
                                            <div class="ti-time">{{ formatTime(ev.Created_At) }}</div>
                                            <div class="ti-icon" :class="iconToneClass(ev.Event_Type)">
                                                <i :class="iconBootstrap(ev.Event_Type)"></i>
                                            </div>
                                            <div class="ti-body">
                                                <div class="ti-title">
                                                    {{ describe(ev) }}
                                                    <span v-if="eventBadge(ev.Event_Type)" class="ti-badge" :class="iconToneClass(ev.Event_Type)">
                                                        {{ eventBadge(ev.Event_Type) }}
                                                    </span>
                                                </div>
                                                <div class="ti-sub">
                                                    <span v-if="ev.Module_Name || ev.Module_Code">
                                                        {{ ev.Module_Name || ev.Module_Code }}
                                                    </span>
                                                    <span v-if="ev.Method"> · {{ ev.Method }}
                                                        <em :class="statusClass(ev.Http_Status)">{{ ev.Http_Status || '' }}</em>
                                                    </span>
                                                    <span v-if="ev.Duration_Ms"> · {{ ev.Duration_Ms }}ms</span>
                                                    <span v-if="ev.Path" class="ti-path"> · {{ ev.Path }}</span>
                                                </div>
                                            </div>
                                            <i class="ti-chevron bi" :class="isExpanded(ev.Event_Id) ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        </button>
            
                                        <transition name="ti-expand">
                                            <div v-if="isExpanded(ev.Event_Id)" class="ti-detail">
                                                <div v-if="detailLoadingId === ev.Event_Id" class="ti-detail-loading">
                                                    <i class="bi bi-arrow-repeat action-loading-spin"></i> Memuat detail...
                                                </div>
                                                <div v-else-if="detailCache[ev.Event_Id]" class="ti-detail-grid">
                                                    <div class="insight-card p-3 m-0 shadow-sm">
                                                        <div class="insight-card__head" style="margin-bottom:0.5rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                                                            <div><span class="section-label">Aktivitas</span><h2 style="font-size:0.85rem;">Detail Event</h2></div>
                                                        </div>
                                                        <dl class="detail-dl">
                                                            <div><dt>Waktu</dt><dd>{{ formatDateTime(detailCache[ev.Event_Id].event.Created_At) }}</dd></div>
                                                            <div><dt>Jenis</dt><dd>{{ eventTypeLabel(detailCache[ev.Event_Id].event.Event_Type) }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Action_Code"><dt>Aksi</dt><dd>{{ detailCache[ev.Event_Id].event.Action_Code }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Module_Name"><dt>Modul</dt><dd>{{ detailCache[ev.Event_Id].event.Module_Name }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Menu_Code"><dt>Menu</dt><dd>{{ detailCache[ev.Event_Id].event.Menu_Code }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Method"><dt>Method</dt><dd>{{ detailCache[ev.Event_Id].event.Method }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Http_Status"><dt>Status</dt><dd :class="statusClass(detailCache[ev.Event_Id].event.Http_Status)">{{ detailCache[ev.Event_Id].event.Http_Status }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Duration_Ms"><dt>Durasi</dt><dd>{{ detailCache[ev.Event_Id].event.Duration_Ms }} ms</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Path"><dt>Path</dt><dd class="mono">{{ detailCache[ev.Event_Id].event.Path }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Route_Name"><dt>Route</dt><dd class="mono">{{ detailCache[ev.Event_Id].event.Route_Name }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Resource_Type"><dt>Resource</dt><dd>{{ detailCache[ev.Event_Id].event.Resource_Type }} #{{ detailCache[ev.Event_Id].event.Resource_Id }}</dd></div>
                                                            <div><dt>IP</dt><dd>{{ detailCache[ev.Event_Id].event.Ip_Address || '—' }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].event.Trace_Id"><dt>Trace</dt><dd class="mono small">{{ detailCache[ev.Event_Id].event.Trace_Id }}</dd></div>
                                                        </dl>
                                                    </div>
            
                                                    <div class="insight-card p-3 m-0 shadow-sm" v-if="detailCache[ev.Event_Id].session">
                                                        <div class="insight-card__head" style="margin-bottom:0.5rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                                                            <div><span class="section-label">Perangkat</span><h2 style="font-size:0.85rem;">Konteks Sesi</h2></div>
                                                        </div>
                                                        <dl class="detail-dl">
                                                            <div><dt>Perangkat</dt><dd>{{ detailCache[ev.Event_Id].session.Browser }} {{ detailCache[ev.Event_Id].session.Browser_Version || '' }}</dd></div>
                                                            <div><dt>OS</dt><dd>{{ detailCache[ev.Event_Id].session.Os }} {{ detailCache[ev.Event_Id].session.Os_Version || '' }}</dd></div>
                                                            <div><dt>Tipe</dt><dd>{{ detailCache[ev.Event_Id].session.Device_Type }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].session.Ip_City"><dt>Lokasi</dt><dd>{{ detailCache[ev.Event_Id].session.Ip_City }}, {{ detailCache[ev.Event_Id].session.Ip_Country }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].session.Ip_Isp"><dt>ISP</dt><dd>{{ detailCache[ev.Event_Id].session.Ip_Isp }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].session.Timezone"><dt>Zona Waktu</dt><dd>{{ detailCache[ev.Event_Id].session.Timezone }}</dd></div>
                                                            <div><dt>Login sesi</dt><dd>{{ formatDateTime(detailCache[ev.Event_Id].session.Login_At) }}</dd></div>
                                                            <div v-if="detailCache[ev.Event_Id].session.Device_Fingerprint"><dt>Fingerprint</dt><dd class="mono small">{{ (detailCache[ev.Event_Id].session.Device_Fingerprint || '').slice(0, 16) }}…</dd></div>
                                                            <div class="detail-link mt-3" v-if="detailCache[ev.Event_Id].event.Session_Id">
                                                                <el-button size="small" type="primary" link @click.stop="openSession(detailCache[ev.Event_Id].event.Session_Id)" style="font-weight: 700; padding: 0;">
                                                                    Lihat semua event di sesi ini →
                                                                </el-button>
                                                            </div>
                                                        </dl>
                                                    </div>
                                                </div>
                                                <div v-else class="ti-detail-loading text-danger">Gagal memuat detail.</div>
                                            </div>
                                        </transition>
                                    </div>
                                </div>
            
                                <div v-if="timeline.hasMore" class="more">
                                    <el-button @click="loadMoreTimeline" :loading="timeline.loading" type="primary" plain round>
                                        Muat lebih banyak
                                    </el-button>
                                </div>
                            </div>
                        </el-tab-pane>
            
                        <el-tab-pane name="sessions">
                            <template #label><i class="bi bi-layout-text-window"></i> Sesi Login</template>
                            <div v-if="sessions.loading" class="empty-state">
                                <i class="bi bi-arrow-repeat action-loading-spin"></i>
                                <span>Memuat sesi...</span>
                            </div>
                            <div v-else-if="!sessions.items.length" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <span>Belum ada sesi login.</span>
                            </div>
                            <div v-else class="session-grid">
                                <div
                                    v-for="s in sessions.items"
                                    :key="s.Session_Id"
                                    class="insight-card session-card"
                                    :class="{ 'is-active': !s.Logout_At, 'is-forced': s.Logout_Type === 'forced' }"
                                >
                                    <div class="insight-card__head is-between" style="margin-bottom:0.5rem; padding-bottom:0.5rem; border-bottom:1px solid #f1f5f9;">
                                        <div style="display:flex; align-items:center; gap:0.4rem;">
                                            <i class="bi bi-pc-display-horizontal text-primary"></i>
                                            <strong style="font-size:0.85rem;">{{ formatDateTime(s.Login_At) }}</strong>
                                        </div>
                                        <span class="mini-badge" :class="badgeClass(s)">{{ badgeText(s) }}</span>
                                    </div>
                                    <div class="sc-body">
                                        <div class="sc-row">
                                            <span>Perangkat:</span>
                                            <strong>{{ s.Browser }} {{ s.Browser_Version || '' }} · {{ s.Os }}</strong>
                                        </div>
                                        <div class="sc-row">
                                            <span>IP:</span>
                                            <strong>{{ s.Ip_Address || '—' }}</strong>
                                            <span v-if="s.Ip_City"> ({{ s.Ip_City }}, {{ s.Ip_Country }})</span>
                                        </div>
                                        <div class="sc-row">
                                            <span>Aktivitas:</span>
                                            <strong>{{ s.Total_Events }} event · {{ Math.round((s.Active_Seconds || 0) / 60) }} menit</strong>
                                        </div>
                                        <div class="sc-row" v-if="s.Logout_At">
                                            <span>Selesai:</span>
                                            <strong>{{ formatDateTime(s.Logout_At) }} ({{ s.Logout_Type }})</strong>
                                        </div>
                                    </div>
                                    <div class="sc-actions mt-3" style="display:flex; gap:0.5rem;">
                                        <el-button size="small" type="primary" plain @click="openSession(s.Session_Id)">Lihat event</el-button>
                                        <el-button
                                            size="small"
                                            type="danger"
                                            plain
                                            v-if="s.Logout_Type !== 'forced'"
                                            @click="markNotMe(s.Session_Id)"
                                        >
                                            Ini bukan saya
                                        </el-button>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>
            
                        <el-tab-pane name="devices">
                            <template #label><i class="bi bi-phone"></i> Perangkat &amp; Lokasi</template>
                            <div v-if="devices.loading" class="empty-state">
                                <i class="bi bi-arrow-repeat action-loading-spin"></i>
                                <span>Memuat perangkat...</span>
                            </div>
                            <div v-else-if="!devices.items.length" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <span>Belum ada perangkat tercatat.</span>
                            </div>
                            <div v-else class="device-grid">
                                <div v-for="d in devices.items" :key="d.Device_Fingerprint" class="insight-card device-card">
                                    <div class="insight-card__head is-between" style="margin-bottom:0.5rem; padding-bottom:0.5rem; border-bottom:1px solid #f1f5f9;">
                                        <div style="display:flex; align-items:center; gap:0.4rem;">
                                            <i class="bi" :class="d.Flag_Trusted === 'Y' ? 'bi-shield-check text-success' : 'bi-shield'"></i>
                                            <strong style="font-size:0.85rem;">{{ d.Label || (d.Last_Browser + ' / ' + d.Last_Os) || 'Perangkat tanpa nama' }}</strong>
                                        </div>
                                        <span v-if="d.Flag_Trusted === 'Y'" class="mini-badge is-success">Dipercaya</span>
                                    </div>
                                    <div class="sc-body mb-3">
                                        <div class="dc-sub">
                                            <div>Pertama dikenal: {{ formatDateTime(d.First_Seen_At) }}</div>
                                            <div>Terakhir aktif: {{ formatDateTime(d.Last_Seen_At) }}</div>
                                            <div class="muted">FP: {{ (d.Device_Fingerprint || '').slice(0, 12) }}…</div>
                                        </div>
                                    </div>
                                    <div class="dc-actions" style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                        <el-input
                                            v-model="deviceLabels[d.Device_Fingerprint]"
                                            :placeholder="d.Label || 'Beri nama perangkat'"
                                            size="small"
                                            style="max-width: 200px"
                                        />
                                        <el-button size="small" type="primary" plain @click="saveDevice(d)">Simpan</el-button>
                                        <el-button
                                            size="small"
                                            :type="d.Flag_Trusted === 'Y' ? 'danger' : 'success'"
                                            plain
                                            @click="toggleTrusted(d)"
                                        >
                                            {{ d.Flag_Trusted === 'Y' ? 'Cabut' : 'Percayai' }}
                                        </el-button>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>
            
                        <el-tab-pane name="security">
                            <template #label><i class="bi bi-shield-exclamation"></i> Keamanan</template>
                            <div v-if="security.loading" class="empty-state">
                                <i class="bi bi-arrow-repeat action-loading-spin"></i>
                                <span>Memuat data...</span>
                            </div>
                            <div v-else-if="!security.items.length" class="empty-state">
                                <i class="bi bi-shield-check"></i>
                                <span>Tidak ada kejadian keamanan baru-baru ini.</span>
                            </div>
                            <div v-else class="security-list">
                                <div v-for="ev in security.items" :key="ev.Event_Id" class="insight-card security-item" :class="securityToneClass(ev)">
                                    <div class="si-icon"><i class="bi" :class="securityIcon(ev)"></i></div>
                                    <div class="si-body">
                                        <div class="si-time">{{ formatDateTime(ev.Created_At) }}</div>
                                        <strong>{{ securityLabel(ev) }}</strong>
                                        <div class="muted">{{ ev.Description }}</div>
                                        <div class="muted" v-if="ev.Ip_Address">IP: {{ ev.Ip_Address }}</div>
                                    </div>
                                </div>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </main>
            </section>
        </div>

        <!-- Drawer detail sesi -->
        <el-drawer v-model="drawerOpen" title="Detail Sesi" direction="rtl" size="520px" class="custom-drawer">
            <div v-if="!sessionDetail" class="empty-state">
                <i class="bi bi-arrow-repeat action-loading-spin"></i>
                <span>Memuat...</span>
            </div>
            <div v-else>
                <div class="sd-section insight-card shadow-sm p-3 mb-3 m-0">
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <i class="bi bi-calendar-event text-primary"></i>
                        <strong>{{ formatDateTime(sessionDetail.session.Login_At) }}</strong>
                    </div>
                    <div class="muted mt-1">
                        {{ sessionDetail.session.Browser }} · {{ sessionDetail.session.Os }} · {{ sessionDetail.session.Ip_Address }}
                    </div>
                </div>
                <div class="sd-events timeline-list">
                    <div
                        v-for="ev in sessionDetail.events"
                        :key="ev.Event_Id"
                        class="timeline-item is-open"
                        style="cursor: default;"
                    >
                        <div class="ti-head">
                            <div class="ti-time">{{ formatTime(ev.Created_At) }}</div>
                            <div class="ti-icon" :class="iconToneClass(ev.Event_Type)">
                                <i :class="iconBootstrap(ev.Event_Type)"></i>
                            </div>
                            <div class="ti-body">
                                <div class="ti-title">{{ describe(ev) }}</div>
                                <div class="ti-sub">
                                    {{ ev.Module_Name || ev.Module_Code || '' }}
                                    <span v-if="ev.Path" class="ti-path"> · {{ ev.Path }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { setActivityGpsOptIn, getActivityGpsOptIn } from '@/composables/useActivityHandshake';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    mask_ip: { type: Boolean, default: true },
});

const activeTab = ref('timeline');
const dateRange = ref(defaultRange());
const eventTypeFilter = ref([]);
const eventTypeOptions = [
    { label: 'Pageview', value: 'pageview' },
    { label: 'API Call', value: 'api_call' },
    { label: 'Action', value: 'action' },
    { label: 'Login', value: 'auth.login' },
    { label: 'Logout', value: 'auth.logout' },
    { label: 'Login Gagal', value: 'auth.failed' },
    { label: 'Akses Ditolak', value: 'permission_denied' },
    { label: 'Error', value: 'error' },
    { label: 'Download', value: 'download' },
    { label: 'Export', value: 'export' },
];

const dateShortcuts = [
    { text: 'Hari ini', value: () => { const d = new Date(); return [d, d]; } },
    { text: '7 hari', value: () => { const e = new Date(); const s = new Date(); s.setDate(s.getDate() - 6); return [s, e]; } },
    { text: '30 hari', value: () => { const e = new Date(); const s = new Date(); s.setDate(s.getDate() - 29); return [s, e]; } },
];

const timeline = reactive({ loading: false, items: [], page: 1, perPage: 50, total: 0, hasMore: false });
const sessions = reactive({ loading: false, items: [] });
const devices  = reactive({ loading: false, items: [] });
const security = reactive({ loading: false, items: [] });
const deviceLabels = reactive({});

const drawerOpen = ref(false);
const sessionDetail = ref(null);

// Accordion timeline: detail event lazy-load
const expandedIds = ref([]);
const detailCache = reactive({});
const detailLoadingId = ref(null);

const gpsOptIn = ref(getActivityGpsOptIn());

const timelineGrouped = computed(() => {
    const groups = new Map();
    timeline.items.forEach((ev) => {
        const date = (ev.Created_At || '').slice(0, 10);
        if (!groups.has(date)) groups.set(date, []);
        groups.get(date).push(ev);
    });
    return Array.from(groups.entries()).map(([date, items]) => ({ date, items }));
});

function defaultRange() {
    const e = new Date();
    const s = new Date();
    s.setDate(s.getDate() - 6);
    return [fmtDate(s), fmtDate(e)];
}
function fmtDate(d) {
    return d.toISOString().slice(0, 10);
}

async function loadTimeline(reset = true) {
    timeline.loading = true;
    if (reset) {
        timeline.items = [];
        timeline.page = 1;
    }
    try {
        const params = {
            from: dateRange.value?.[0],
            to: dateRange.value?.[1],
            page: timeline.page,
            per_page: timeline.perPage,
            event_types: eventTypeFilter.value,
        };
        const { data } = await axios.get('/my/activity/timeline', { params });
        const body = data?.result ?? [];
        timeline.items = reset ? body : timeline.items.concat(body);
        const total = data?.pagination?.total ?? body.length;
        timeline.total = total;
        timeline.hasMore = timeline.items.length < total;
    } catch (e) {
        ElMessage.error('Gagal memuat timeline');
    } finally {
        timeline.loading = false;
    }
}

function loadMoreTimeline() {
    timeline.page += 1;
    loadTimeline(false);
}

function reloadTimeline() {
    loadTimeline(true);
}

async function loadSessions() {
    sessions.loading = true;
    try {
        const { data } = await axios.get('/my/activity/sessions', { params: { days: 30 } });
        sessions.items = data?.result ?? [];
    } catch {
        ElMessage.error('Gagal memuat sesi');
    } finally {
        sessions.loading = false;
    }
}

async function loadDevices() {
    devices.loading = true;
    try {
        const { data } = await axios.get('/my/activity/devices');
        devices.items = data?.result ?? [];
    } catch {
        ElMessage.error('Gagal memuat perangkat');
    } finally {
        devices.loading = false;
    }
}

async function loadSecurity() {
    security.loading = true;
    try {
        const { data } = await axios.get('/my/activity/security');
        security.items = data?.result ?? [];
    } catch {
        ElMessage.error('Gagal memuat data keamanan');
    } finally {
        security.loading = false;
    }
}

function onTabChange(name) {
    if (name === 'sessions' && !sessions.items.length) loadSessions();
    if (name === 'devices' && !devices.items.length) loadDevices();
    if (name === 'security' && !security.items.length) loadSecurity();
}

async function openSession(id) {
    drawerOpen.value = true;
    sessionDetail.value = null;
    try {
        const { data } = await axios.get(`/my/activity/sessions/${id}`);
        sessionDetail.value = data?.result;
    } catch {
        ElMessage.error('Gagal memuat detail sesi');
        drawerOpen.value = false;
    }
}

async function markNotMe(id) {
    try {
        await ElMessageBox.confirm(
            'Tandai sesi ini sebagai bukan saya? Tim keamanan akan diberitahu.',
            'Konfirmasi',
            { confirmButtonText: 'Tandai', cancelButtonText: 'Batal', type: 'warning' },
        );
    } catch {
        return;
    }
    try {
        await axios.post(`/my/activity/sessions/${id}/not-me`);
        ElMessage.success('Sesi ditandai sebagai bukan saya');
        loadSessions();
    } catch {
        ElMessage.error('Gagal menandai sesi');
    }
}

async function saveDevice(d) {
    try {
        await axios.post(`/my/activity/devices/${d.Device_Fingerprint}/label`, {
            label: deviceLabels[d.Device_Fingerprint] ?? d.Label ?? '',
        });
        ElMessage.success('Label perangkat disimpan');
        loadDevices();
    } catch {
        ElMessage.error('Gagal menyimpan');
    }
}

async function toggleTrusted(d) {
    try {
        await axios.post(`/my/activity/devices/${d.Device_Fingerprint}/label`, {
            trusted: d.Flag_Trusted !== 'Y',
        });
        loadDevices();
    } catch {
        ElMessage.error('Gagal memperbarui status');
    }
}

function onGpsToggle(val) {
    setActivityGpsOptIn(val);
    ElMessage.success(val ? 'GPS akan dipakai pada login berikutnya' : 'GPS dinonaktifkan');
}

/* Formatters & describe */

function formatDateTime(s) {
    if (!s) return '—';
    return new Date(s).toLocaleString('id-ID', { hour12: false });
}
function formatTime(s) {
    if (!s) return '';
    return new Date(s).toLocaleTimeString('id-ID', { hour12: false });
}
function formatMinutes(min) {
    min = Number(min || 0);
    if (min < 60) return `${min} mnt`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${h}j ${m}m`;
}

function humanizePath(path) {
    if (!path) return '';
    let p = String(path).split('?')[0].replace(/^\/+|\/+$/g, '');
    if (!p) return 'Beranda';
    const dict = {
        udash: 'Dashboard', dashboard: 'Dashboard', home: 'Beranda', homepage: 'Homepage',
        profile: 'Profil', userprofile: 'Profil', 'user-profile': 'Profil',
        cuti: 'Cuti', lembur: 'Lembur', overtime: 'Lembur', kpi: 'KPI',
        lms: 'Learning', training: 'Training', shift: 'Shift', approval: 'Approval',
        'transaksi-tamu': 'Tamu', 'transaksi-permit': 'Surat Izin',
        'approval-visitor': 'Approval Tamu', 'laporan-visitor': 'Laporan Tamu',
        admin: 'Admin', 'role-manager': 'Role Manager', 'system-auth': 'System Auth',
        activity: 'Aktivitas', absensisales: 'Absensi Sales', api: 'API', v1: '',
    };
    const segs = p.split('/')
        .filter((s) => s && !/^\d+$/.test(s))
        .map((s) => {
            const k = s.toLowerCase();
            if (k in dict) return dict[k];
            return s.replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        })
        .filter(Boolean);
    return segs.length ? segs.join(' › ') : 'Halaman';
}

function describe(ev) {
    if (ev.Description) return ev.Description;
    const label = ev.Module_Name || ev.Module_Code || humanizePath(ev.Path);
    switch (ev.Event_Type) {
        case 'auth.login':  return 'Login berhasil';
        case 'auth.logout': return 'Logout';
        case 'auth.failed': return 'Login gagal';
        case 'permission_denied': return `Akses ditolak${label ? ' — ' + label : ''}`;
        case 'pageview':    return label ? `Membuka ${label}` : 'Membuka halaman';
        case 'download':    return label ? `Download — ${label}` : 'Download file';
        case 'export':      return label ? `Export — ${label}` : 'Export data';
        case 'error':       return label ? `Error di ${label}` : 'Terjadi error';
        case 'action':
        case 'api_call': {
            const verb = {
                create: 'Menambah data', update: 'Mengubah data', delete: 'Menghapus data',
                approve: 'Menyetujui', reject: 'Menolak',
            }[ev.Action_Code] || 'Aksi';
            return label ? `${verb} — ${label}` : verb;
        }
        default:            return ev.Action_Code || ev.Event_Type;
    }
}

function eventTypeLabel(type) {
    return {
        'auth.login': 'Login', 'auth.logout': 'Logout', 'auth.failed': 'Login gagal',
        permission_denied: 'Akses ditolak', pageview: 'Kunjungan halaman',
        api_call: 'API call', action: 'Aksi', download: 'Download', export: 'Export', error: 'Error',
    }[type] || type;
}

function eventBadge(type) {
    return {
        'auth.login': 'Login', 'auth.logout': 'Logout', 'auth.failed': 'Gagal',
        permission_denied: 'Ditolak', download: 'Download', export: 'Export',
        action: 'Aksi', error: 'Error',
    }[type] || '';
}

function statusClass(status) {
    if (!status) return '';
    if (status >= 500) return 'text-danger fw-bold';
    if (status >= 400) return 'text-warning fw-bold';
    if (status >= 300) return 'text-info fw-bold';
    return 'text-success fw-bold';
}

/* ===== Accordion timeline ===== */
function isExpanded(id) {
    return expandedIds.value.includes(id);
}
async function toggleEvent(ev) {
    const id = ev.Event_Id;
    if (isExpanded(id)) {
        expandedIds.value = expandedIds.value.filter((x) => x !== id);
        return;
    }
    expandedIds.value = [...expandedIds.value, id];
    if (!detailCache[id]) {
        await loadEventDetail(id);
    }
}
async function loadEventDetail(id) {
    detailLoadingId.value = id;
    try {
        const { data } = await axios.get(`/my/activity/events/${id}`);
        detailCache[id] = data?.result || null;
    } catch {
        detailCache[id] = null;
        ElMessage.error('Gagal memuat detail event');
    } finally {
        detailLoadingId.value = null;
    }
}

function iconBootstrap(type) {
    return {
        'auth.login': 'bi-key', 'auth.logout': 'bi-door-closed', 'auth.failed': 'bi-exclamation-triangle',
        'permission_denied': 'bi-slash-circle', 'pageview': 'bi-file-earmark-text', 'api_call': 'bi-lightning',
        'download': 'bi-download', 'export': 'bi-box-arrow-up', 'action': 'bi-check2-circle', 'error': 'bi-bug',
    }[type] || 'bi-record-circle';
}
function iconToneClass(type) {
    if (['auth.failed', 'permission_denied', 'error'].includes(type)) return 'is-danger';
    if (type === 'auth.login') return 'is-success';
    if (type === 'auth.logout') return 'is-muted';
    if (type === 'action' || type === 'api_call') return 'is-primary';
    return 'is-info';
}

function badgeClass(s) {
    if (s.Logout_Type === 'forced') return 'is-danger';
    if (!s.Logout_At) return 'is-success';
    if (s.Logout_Type === 'expired') return 'is-muted';
    return 'is-info';
}
function badgeText(s) {
    if (s.Logout_Type === 'forced') return 'Ditandai bukan saya';
    if (!s.Logout_At) return 'Aktif';
    if (s.Logout_Type === 'expired') return 'Idle (otomatis)';
    return 'Selesai';
}

function securityToneClass(ev) {
    if (ev.Event_Type === 'auth.failed' || ev.Event_Type === 'permission_denied') return 'is-danger';
    if (ev.Event_Type === 'auth.login') return 'is-success';
    return 'is-info';
}
function securityIcon(ev) {
    if (ev.Event_Type === 'auth.failed' || ev.Event_Type === 'permission_denied') return 'bi-shield-exclamation';
    if (ev.Event_Type === 'auth.login') return 'bi-shield-check';
    return 'bi-shield';
}
function securityLabel(ev) {
    return {
        'auth.login': 'Login berhasil',
        'auth.logout': 'Logout',
        'auth.failed': 'Login gagal',
        'permission_denied': 'Akses ditolak',
    }[ev.Event_Type] || ev.Event_Type;
}

onMounted(() => loadTimeline(true));
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
.mini-badge.is-muted { background: var(--hcis-slate-100); color: var(--hcis-slate-500); }

/* Hero */
.leader-hero {
    position: relative; overflow: hidden; border-radius: 1rem; padding: 1.25rem; color: white;
    background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.24), transparent 17rem), linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-blue-600));
    box-shadow: 0 16px 34px rgba(37, 99, 235, 0.18); margin-bottom: 1rem;
}
.leader-hero__content { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 330px); gap: 1rem; }
@media (max-width: 768px) { .leader-hero__content { grid-template-columns: 1fr; } }
.hero-topline { display: flex; gap: 0.5rem; align-items: center; justify-content: start; flex-wrap: wrap; }
.hero-eyebrow, .period-pill { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.6rem; border-radius: 999px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.18); font-size: 0.7rem; font-weight: 800; }
.period-pill { color: rgba(255, 255, 255, 0.9); }
.hero-title-row { margin-top: 1rem; }
.hero-title-row h1 { margin: 0; color: white; font-size: clamp(1.5rem, 2.5vw, 2.2rem); font-weight: 900; letter-spacing: -0.04em; }
.hero-title-row p { margin: 0.5rem 0 0; color: rgba(255, 255, 255, 0.84); font-size: 0.85rem; line-height: 1.5; max-width: 600px; }
.hero-focus-card { background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.18); border-radius: 1rem; height: 100%; }

/* Quick Stats Grid */
.quick-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
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
.quick-stat-card.is-purple .quick-stat-card__icon { background: var(--hcis-purple-100); color: var(--hcis-purple-600); }
.quick-stat-card.is-danger .quick-stat-card__icon { background: var(--hcis-red-100); color: var(--hcis-red-600); }
.quick-stat-card__copy strong { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.2; color: var(--hcis-slate-900); }
.quick-stat-card__copy span { display: block; font-size: 0.75rem; font-weight: 600; color: var(--hcis-slate-600); margin-top: 0.15rem; }
.quick-stat-card__copy small { display: block; font-size: 0.65rem; color: var(--hcis-slate-400); }

/* Insight Card (used for Tabs, Tables, Lists) */
.insight-card { background: #fff; border-radius: 1rem; border: 1px solid var(--hcis-slate-200); padding: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.insight-card__head { margin-bottom: 1rem; }
.insight-card__head.is-between { display: flex; justify-content: space-between; align-items: flex-start; }
.insight-card__head h2 { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--hcis-slate-900); }

/* Custom Tabs overrides */
.custom-tabs :deep(.el-tabs__nav-wrap::after) { height: 1px; background-color: var(--hcis-slate-200); }
.custom-tabs :deep(.el-tabs__item) { font-weight: 600; font-size: 0.9rem; color: var(--hcis-slate-500); padding: 0 1.25rem; }
.custom-tabs :deep(.el-tabs__item.is-active) { color: var(--hcis-indigo-600); }
.custom-tabs :deep(.el-tabs__active-bar) { background-color: var(--hcis-indigo-600); height: 3px; border-radius: 3px 3px 0 0; }

.filters { display: flex; gap: 0.75rem; flex-wrap: wrap; margin: 1rem 0 1.5rem; }

.empty-state { padding: 4rem 2rem; text-align: center; color: var(--hcis-slate-400); display: flex; flex-direction: column; align-items: center; gap: 1rem; }
.empty-state i { font-size: 2.5rem; color: var(--hcis-slate-300); }
.empty-state span { font-size: 0.9rem; font-weight: 500; }
.action-loading-spin { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Timeline List */
.timeline-list { display: flex; flex-direction: column; gap: 1.5rem; }
.timeline-day { border-left: 2px solid var(--hcis-slate-200); padding-left: 1.25rem; position: relative; margin-left: 10px; }
.timeline-date { font-weight: 800; font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--hcis-slate-700); display: flex; align-items: center; gap: 0.5rem; margin-left: -2px; }
.timeline-item { border: 1px solid transparent; border-radius: 0.75rem; margin-bottom: 4px; transition: all 0.2s ease; }
.timeline-item.is-open { background: var(--hcis-slate-50); border-color: var(--hcis-slate-200); box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
.timeline-item.is-danger.is-open { background: var(--hcis-red-50); border-color: var(--hcis-red-200); }

.ti-head { width: 100%; background: none; border: 0; cursor: pointer; text-align: left; display: flex; align-items: flex-start; gap: 1rem; padding: 0.75rem 1rem; border-radius: 0.75rem; }
.ti-head:hover { background: rgba(15, 23, 42, 0.03); }
.ti-time { width: 60px; color: var(--hcis-slate-500); font-size: 0.8rem; font-variant-numeric: tabular-nums; padding-top: 6px; font-weight: 600; flex-shrink: 0; }
.ti-icon { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; font-size: 0.95rem; }
.is-danger .ti-icon { background: var(--hcis-red-100); color: var(--hcis-red-600); }
.is-success .ti-icon { background: var(--hcis-green-100); color: #166534; }
.is-info .ti-icon { background: var(--hcis-sky-100); color: var(--hcis-sky-600); }
.is-primary .ti-icon { background: var(--hcis-indigo-100); color: var(--hcis-indigo-600); }
.is-muted .ti-icon { background: var(--hcis-slate-100); color: var(--hcis-slate-500); }
.ti-body { flex: 1; min-width: 0; padding-top: 2px; }
.ti-title { font-weight: 600; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; color: var(--hcis-slate-900); font-size: 0.9rem; }
.ti-sub { font-size: 0.75rem; color: var(--hcis-slate-500); margin-top: 4px; }
.ti-path { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.7rem; color: var(--hcis-slate-400); }
.ti-badge { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px; border-radius: 999px; }
.is-success.ti-badge { background: var(--hcis-green-100); color: #166534; }
.is-danger.ti-badge { background: var(--hcis-red-100); color: var(--hcis-red-800); }
.is-muted.ti-badge { background: var(--hcis-slate-100); color: var(--hcis-slate-500); }
.is-info.ti-badge { background: var(--hcis-sky-100); color: var(--hcis-sky-600); }
.is-primary.ti-badge { background: var(--hcis-indigo-100); color: var(--hcis-indigo-600); }
.ti-chevron { font-size: 1rem; color: var(--hcis-slate-400); padding-top: 5px; transition: transform 0.2s ease; }

.ti-detail { padding: 0 1rem 1rem 4.5rem; }
.ti-detail-loading { padding: 1rem; color: var(--hcis-slate-500); font-size: 0.85rem; display: flex; gap: 0.5rem; align-items: center; }
.ti-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 768px) { .ti-detail-grid { grid-template-columns: 1fr; } .ti-detail { padding-left: 1rem; } }

.detail-dl { margin: 0; display: flex; flex-direction: column; gap: 6px; }
.detail-dl > div { display: grid; grid-template-columns: 100px 1fr; gap: 8px; font-size: 0.8rem; }
.detail-dl dt { color: var(--hcis-slate-500); font-weight: 500; }
.detail-dl dd { margin: 0; color: var(--hcis-slate-900); word-break: break-word; font-weight: 600; }
.detail-dl dd.mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.75rem; font-weight: 500; }
.detail-dl dd.small { font-size: 0.7rem; color: var(--hcis-slate-500); }

.ti-expand-enter-active, .ti-expand-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.ti-expand-enter-from, .ti-expand-leave-to { opacity: 0; transform: translateY(-5px); }
.more { text-align: center; margin-top: 2rem; }

/* Grid session/device */
.session-grid, .device-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
.session-card { transition: border-color 0.2s ease; border-width: 2px; }
.session-card.is-active { border-color: var(--hcis-emerald-500); }
.session-card.is-forced { border-color: var(--hcis-red-500); background: var(--hcis-red-50); }
.sc-row { display: flex; justify-content: space-between; font-size: 0.8rem; padding: 0.35rem 0; border-bottom: 1px dashed var(--hcis-slate-100); }
.sc-row:last-child { border-bottom: none; }
.sc-row span { color: var(--hcis-slate-500); }
.sc-row strong { color: var(--hcis-slate-900); text-align: right; }
.dc-sub { font-size: 0.8rem; display: flex; flex-direction: column; gap: 0.35rem; }
.dc-sub > div { display: flex; justify-content: space-between; border-bottom: 1px dashed var(--hcis-slate-100); padding-bottom: 0.25rem; }

.security-list { display: flex; flex-direction: column; gap: 0.75rem; }
.security-item { display: flex; gap: 1.25rem; padding: 1.25rem; }
.security-item.is-danger { background: var(--hcis-red-50); border-color: var(--hcis-red-200); }
.security-item.is-success { background: var(--hcis-green-50); border-color: var(--hcis-green-200); }
.si-icon { font-size: 1.5rem; }
.is-danger .si-icon { color: var(--hcis-red-600); }
.is-success .si-icon { color: #166534; }
.is-info .si-icon { color: var(--hcis-sky-600); }
.si-time { font-size: 0.8rem; color: var(--hcis-slate-500); font-weight: 600; margin-bottom: 0.25rem; }
.si-body strong { display: block; font-size: 1rem; color: var(--hcis-slate-900); margin-bottom: 0.25rem; }
.si-body .muted { font-size: 0.85rem; color: var(--hcis-slate-500); }

.muted { color: var(--hcis-slate-500); font-size: 0.85rem; }
</style>
