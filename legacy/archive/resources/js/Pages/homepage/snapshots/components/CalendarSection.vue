<template>
    <div class="calendar-section mb-4">
        <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <div class="icon-box bg-primary-subtle p-2 rounded-3">
                        <i class="bi bi-calendar-event text-primary"></i>
                    </div>
                    Kalender Cutoff & Jadwal
                </h5>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <span v-if="cutoffPeriod" class="badge bg-info-subtle text-info border-info-subtle px-3 py-2 rounded-pill small">
                        <i class="bi bi-calendar-range me-1"></i>
                        {{ cutoffPeriod.label || `${cutoffPeriod.start_date} - ${cutoffPeriod.end_date}` }}
                    </span>
                    <!-- Filter dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" :class="{ active: filterStatus === 'all' }" href="#" @click.prevent="setFilter('all')">Semua</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li v-for="f in statusFilters" :key="f.key">
                                <a class="dropdown-item d-flex align-items-center gap-2" :class="{ active: filterStatus === f.key }" href="#" @click.prevent="setFilter(f.key)">
                                    <span class="rounded-circle" :style="{ width: '10px', height: '10px', background: f.color, display: 'inline-block' }"></span>
                                    {{ f.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <!-- Export dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" @click.prevent="exportCalendar('csv')"><i class="bi bi-file-earmark-excel me-2"></i>Export CSV</a></li>
                            <li><a class="dropdown-item" href="#" @click.prevent="exportCalendar('print')"><i class="bi bi-printer me-2"></i>Print / PDF</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                <span class="small fw-bold text-muted">Legenda:</span>
                <span v-for="f in statusFilters" :key="f.key" class="d-flex align-items-center gap-1 legend-item" :class="{ 'opacity-25': filterStatus !== 'all' && filterStatus !== f.key }" @click="setFilter(f.key)" style="cursor: pointer;">
                    <span class="rounded-circle" :style="{ width: '10px', height: '10px', background: f.color, display: 'inline-block' }"></span>
                    <small>{{ f.label }}</small>
                </span>
            </div>

            <!-- Active Filter -->
            <div v-if="filterStatus !== 'all'" class="mb-3 d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-3 py-2">{{ filterStatus }}</span>
                <button class="btn btn-link btn-sm p-0 text-decoration-none" @click="setFilter('all')">
                    <i class="bi bi-x-circle me-1"></i>Hapus Filter
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="calendar-loading-state">
                <div class="d-flex justify-content-center align-items-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-3 text-muted">Memuat data kalender...</span>
                </div>
            </div>

            <!-- Calendar -->
            <div v-else class="calendar-container">
                <FullCalendar ref="calendarRef" :options="calendarOpts" />
            </div>
        </div>

        <!-- Event Detail Modal -->
        <div v-if="showEventModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);z-index:1055;" @click.self="closeModal">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                            <div class="icon-box" :style="{ background: selectedEvent?.backgroundColor || '#6c757d' }">
                                <i class="bi bi-calendar-event text-white"></i>
                            </div>
                            Detail Event
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal"></button>
                    </div>
                    <div class="modal-body" v-if="selectedEvent">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="text-muted small fw-bold">JUDUL EVENT</label>
                                    <h6 class="fw-bold mb-0">{{ selectedEvent.title }}</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">TANGGAL</label>
                                            <p class="mb-0 fw-semibold">{{ formatDate(selectedEvent.start) }}</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">STATUS</label>
                                            <p class="mb-0">
                                                <span v-if="selectedEvent.status" class="badge rounded-pill" :style="{ backgroundColor: selectedEvent.status_color || '#6c757d' }">
                                                    {{ selectedEvent.status }}
                                                </span>
                                                <span v-else class="badge bg-secondary rounded-pill">-</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div v-if="selectedEvent.shift_type" class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">SHIFT</label>
                                            <p class="mb-0 fw-semibold">{{ selectedEvent.shift_type }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedEvent.jam_masuk" class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">JAM MASUK</label>
                                            <p class="mb-0 fw-semibold">{{ selectedEvent.jam_masuk }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedEvent.jam_keluar" class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">JAM KELUAR</label>
                                            <p class="mb-0 fw-semibold">{{ selectedEvent.jam_keluar }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedEvent.check_in" class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">CHECK IN</label>
                                            <p class="mb-0 fw-semibold">{{ selectedEvent.check_in }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedEvent.check_out" class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">CHECK OUT</label>
                                            <p class="mb-0 fw-semibold">{{ selectedEvent.check_out }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedEvent.real_late && selectedEvent.real_late > 0" class="col-6">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">TERLAMBAT</label>
                                            <p class="mb-0 fw-semibold text-danger">{{ selectedEvent.real_late }} menit</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedEvent.alasan" class="col-12">
                                        <div class="p-3 bg-light rounded-3">
                                            <label class="text-muted small fw-bold">KETERANGAN</label>
                                            <p class="mb-0">{{ selectedEvent.alasan }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 h-100">
                                    <label class="text-muted small fw-bold">TIPE EVENT</label>
                                    <div class="mt-2">
                                        <span v-if="selectedEvent.event_type === 'lembur'" class="badge bg-danger rounded-pill px-3 py-2">
                                            <i class="bi bi-clock me-1"></i>LEMBUR
                                        </span>
                                        <span v-else-if="selectedEvent.event_type === 'izin'" class="badge rounded-pill px-3 py-2" style="background:#8B5CF6;">
                                            <i class="bi bi-card-text me-1"></i>{{ selectedEvent.jenis || 'IZIN' }}
                                        </span>
                                        <span v-else class="badge bg-primary rounded-pill px-3 py-2">
                                            <i class="bi bi-calendar-check me-1"></i>SHIFT
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary rounded-pill" @click="closeModal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import axios from 'axios';

export default {
    name: 'CalendarSection',
    components: { FullCalendar },
    props: {
        calendarEndpoint: {
            type: String,
            default: '/homepage/calendar-data',
        },
    },
    data() {
        return {
            calendarEvents: [],
            loading: false,
            cutoffPeriod: null,
            currentMonth: null,
            currentYear: null,
            filterStatus: 'all',
            selectedEvent: null,
            showEventModal: false,
            statusFilters: [
                { key: 'HADIR', label: 'Hadir', color: '#10B981' },
                { key: 'TERLAMBAT', label: 'Terlambat', color: '#F59E0B' },
                { key: 'PAGI', label: 'Shift Pagi', color: '#10B981' },
                { key: 'SIANG', label: 'Shift Siang', color: '#3B82F6' },
                { key: 'SORE', label: 'Shift Sore', color: '#F59E0B' },
                { key: 'MALAM', label: 'Shift Malam', color: '#6366F1' },
                { key: 'IZIN', label: 'Izin', color: '#8B5CF6' },
                { key: 'SAKIT', label: 'Sakit', color: '#EC4899' },
                { key: 'CUTI', label: 'Cuti', color: '#14B8A6' },
                { key: 'LEMBUR', label: 'Lembur', color: '#FF6B6B' },
                { key: 'ALPHA', label: 'Alpha', color: '#EF4444' },
            ],
        };
    },
    computed: {
        filteredEvents() {
            if (this.filterStatus === 'all') return this.calendarEvents;
            return this.calendarEvents.filter(e =>
                e.extendedProps?.status === this.filterStatus ||
                e.extendedProps?.shift_type === this.filterStatus ||
                (this.filterStatus === 'LEMBUR' && e.extendedProps?.event_type === 'lembur')
            );
        },
        isMobile() {
            return window.innerWidth < 768;
        },
        calendarOpts() {
            return {
                plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
                initialView: this.isMobile ? 'listMonth' : 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: this.isMobile ? 'listMonth' : 'dayGridMonth,timeGridWeek,listWeek',
                },
                buttonText: { today: 'Hari Ini', month: 'Bulan', week: 'Minggu', list: 'Daftar' },
                events: this.filteredEvents,
                editable: false,
                selectable: true,
                selectMirror: true,
                dayMaxEvents: true,
                weekends: true,
                height: 'auto',
                eventClick: this.handleEventClick,
                datesSet: this.handleDatesSet,
                eventContent: this.renderEventContent,
            };
        },
    },
    mounted() {
        this.fetchCalendarData();
    },
    methods: {
        async fetchCalendarData(bulan = null, tahun = null) {
            try {
                const date = new Date();
                const targetBulan = bulan || date.getMonth() + 1;
                const targetTahun = tahun || date.getFullYear();

                if (this.loading) return;
                if (this.currentMonth === targetBulan && this.currentYear === targetTahun) return;

                this.loading = true;
                const response = await axios.get(this.calendarEndpoint, {
                    params: { bulan: targetBulan, tahun: targetTahun },
                });

                if (response.status === 200 && response.data.data) {
                    this.calendarEvents = response.data.data;
                    this.cutoffPeriod = response.data.cutoff;
                    this.currentMonth = targetBulan;
                    this.currentYear = targetTahun;
                }
            } catch (error) {
                console.error('Error fetching calendar data:', error);
            } finally {
                this.loading = false;
            }
        },
        handleEventClick(info) {
            info.jsEvent.preventDefault();
            this.selectedEvent = {
                title: info.event.title,
                start: info.event.start,
                end: info.event.end,
                backgroundColor: info.event.backgroundColor,
                ...info.event.extendedProps,
            };
            this.showEventModal = true;
        },
        handleDatesSet(info) {
            const viewStart = new Date(info.view.currentStart);
            const midDate = new Date(viewStart.getTime() + 15 * 24 * 60 * 60 * 1000);
            this.fetchCalendarData(midDate.getMonth() + 1, midDate.getFullYear());
        },
        renderEventContent(arg) {
            const status = arg.event.extendedProps?.status || arg.event.extendedProps?.shift_type || '';
            const bgColor = arg.event.backgroundColor || '#6c757d';
            return {
                html: `<div class="fc-event-inner" style="background:${bgColor};padding:2px 4px;border-radius:3px;font-size:11px;">
                    <span class="fc-event-title">${arg.event.title}</span>
                    ${status ? `<span class="fc-event-status" style="font-size:9px;display:block;opacity:0.9;">${status}</span>` : ''}
                </div>`,
            };
        },
        setFilter(status) {
            this.filterStatus = status;
        },
        closeModal() {
            this.showEventModal = false;
            this.selectedEvent = null;
        },
        exportCalendar(format) {
            if (format === 'print') {
                window.print();
            } else {
                const events = this.filteredEvents.map(e => ({
                    tanggal: e.start,
                    judul: e.title,
                    tipe: e.extendedProps?.event_type || e.extendedProps?.shift_type || 'shift',
                    status: e.extendedProps?.status || '-',
                    jam_masuk: e.extendedProps?.jam_masuk || '-',
                    jam_keluar: e.extendedProps?.jam_keluar || '-',
                    keterangan: e.extendedProps?.keterangan || '-',
                }));
                if (!events.length) return;
                const headers = Object.keys(events[0]);
                const csv = [headers.join(','), ...events.map(r => headers.map(h => `"${r[h] || ''}"`).join(','))].join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `kalender-cutoff-${this.cutoffPeriod?.label || 'export'}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
            }
        },
        formatDate(date) {
            if (!date) return '-';
            return new Date(date).toLocaleDateString('id-ID', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            });
        },
    },
};
</script>

<style scoped>
.icon-box {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.2rem;
}

.legend-item {
    transition: opacity 0.2s ease;
}

.legend-item:hover {
    opacity: 1 !important;
}

.calendar-loading-state {
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.calendar-container { max-width: 100%; overflow-x: auto; }
.calendar-container :deep(.fc) { font-family: inherit; }
.calendar-container :deep(.fc-toolbar-title) { font-size: 1.25rem !important; font-weight: 600; }
.calendar-container :deep(.fc-button) { background-color: #6c757d !important; border-color: #6c757d !important; padding: 0.4rem 0.8rem !important; font-size: 0.85rem !important; border-radius: 0.358rem !important; }
.calendar-container :deep(.fc-button:hover) { background-color: #5a6268 !important; border-color: #5a6268 !important; }
.calendar-container :deep(.fc-button-active) { background-color: #0d6efd !important; border-color: #0d6efd !important; }
.calendar-container :deep(.fc-daygrid-day) { min-height: 80px !important; }
.calendar-container :deep(.fc-daygrid-day-number) { font-size: 0.85rem; padding: 4px 8px; }
.calendar-container :deep(.fc-event) { cursor: pointer; font-size: 0.75rem; padding: 2px 4px; margin: 1px 2px; border-radius: 3px; border-width: 0 !important; }
.calendar-container :deep(.fc-event:hover) { opacity: 0.85; }
.calendar-container :deep(.fc-event-inner) { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.calendar-container :deep(.fc-day-today) { background-color: rgba(13, 110, 253, 0.05) !important; }
.calendar-container :deep(.fc-day-today .fc-daygrid-day-number) { background-color: #0d6efd; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; }

@media print {
    .calendar-container :deep(.fc-button) { display: none !important; }
    .calendar-container :deep(.fc-toolbar) { justify-content: center !important; }
}

@media (max-width: 768px) {
    .calendar-container :deep(.fc-toolbar) { flex-direction: column; gap: 0.5rem; }
    .calendar-container :deep(.fc-toolbar-title) { font-size: 1rem !important; }
}
</style>
