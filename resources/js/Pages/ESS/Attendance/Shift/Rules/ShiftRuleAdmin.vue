<template>
    <div class="cuti-app-container">
        <HeaderKpi
            title="Manajemen Shift Rule"
            subtitle="Admin mengatur rule shift berdasarkan filter organisasi"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />

        <quick-stat :stats="quickStatField" />

        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs
                    :tabs="tabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <template #daftar>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <!-- <div class="stitch-card p-3 mb-3">
                                <ModernFilter
                                    :config="filterConfig"
                                    :filters="filters"
                                    :resultsCount="pagination.total"
                                    @filters-update="handleFilterUpdate"
                                />
                            </div> -->

                            <div class="stitch-card p-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                                    <div>
                                        <h5 class="text-dark fw-bold m-0">
                                            <i class="bi bi-table me-2"></i>
                                            Daftar Shift Rule (Ringkas)
                                        </h5>
                                        <p class="small text-muted m-0">
                                            Pilih tampilan per filter, klik baris untuk lihat shift dan kurangi/hapus.
                                        </p>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <el-select
                                            v-model="displayMode"
                                            class="el-select-full"
                                            style="width: 250px"
                                            @change="onDisplayModeChange"
                                        >
                                            <el-option
                                                v-for="opt in displayModeOptions"
                                                :key="opt.value"
                                                :label="opt.label"
                                                :value="opt.value"
                                            />
                                        </el-select>
                                        <button class="stitch-action-btn" @click="loadRules()">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                </div>

                                <TableNoir
                                    :data="rules"
                                    :columns="columns"
                                    :loading="loading.list"
                                    :showActions="false"
                                    :showDelete="false"
                                    :showPagination="true"
                                    :currentPage="pagination.page"
                                    :totalPages="pagination.totalPages"
                                    :totalData="pagination.total"
                                    :fullPagination="pagination.links"
                                    rowKey="_key"
                                    emptyMessage="Belum ada data group shift rule."
                                    @page-change="goToPage"
                                    @row-click="openGroupDetail"
                                    :clickable="true"
                                >
                                    <template #column-group_label="{ item }">
                                        <div>
                                            <div class="fw-semibold">{{ item.group_label }}</div>
                                            <small class="text-muted">Key: {{ item.group_value ?? "NULL" }}</small>
                                        </div>
                                    </template>
                                    <template #column-total_shifts="{ item }">
                                        <span class="badge bg-primary-subtle text-primary border">
                                            {{ item.total_shifts }} Shift
                                        </span>
                                    </template>
                                    <template #column-active_rules="{ item }">
                                        <span
                                            class="badge border"
                                            :class="
                                                item.active_rules > 0
                                                    ? 'bg-success-subtle text-success-emphasis border-success-subtle'
                                                    : 'bg-secondary-subtle text-secondary border-secondary-subtle'
                                            "
                                        >
                                            {{ item.active_rules }} Aktif
                                        </span>
                                    </template>
                                    <template #column-aksi="{ item }">
                                        <button
                                            class="stitch-btn secondary btn-sm"
                                            @click.stop="openGroupDetail(item)"
                                        >
                                            Detail Shift
                                        </button>
                                    </template>
                                </TableNoir>
                            </div>

                            <div v-if="selectedGroup" class="stitch-card p-3 p-md-4 mt-4 border-0 shadow-sm">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="width: 48px; height: 48px;">
                                            <i class="bi bi-card-list" style="font-size: 1.25rem;"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold text-dark m-0 mb-1">
                                                Detail Group: {{ selectedGroup.group_label }}
                                            </h5>
                                            <span class="badge bg-light text-muted border">
                                                Mode: {{ selectedGroupLabel }}
                                            </span>
                                        </div>
                                    </div>
                                    <button class="stitch-btn secondary btn-sm d-flex align-items-center gap-2" @click="selectedGroup = null">
                                        <i class="bi bi-x-lg"></i> Tutup Detail
                                    </button>
                                </div>

                                <div v-if="loading.detail" class="d-flex justify-content-center align-items-center py-5 text-muted">
                                    <i class="bi bi-arrow-repeat spin-icon me-2 fs-4"></i> Memuat detail...
                                </div>
                                <div v-else-if="groupDetailItems.length === 0" class="d-flex flex-column align-items-center py-5 text-muted transition-hover rounded-4 bg-light border border-dashed">
                                    <i class="bi bi-inbox fs-1 mb-2 text-secondary"></i>
                                    <p class="m-0">Tidak ada shift aktif pada group ini.</p>
                                </div>
                                <div v-else class="row g-3">
                                    <div v-for="row in groupDetailItems" :key="`${row.id_shift}-${row.nama_shift}`" class="col-12 col-md-6 col-lg-4">
                                        <div class="p-3 rounded-4 border bg-white shadow-sm transition-hover h-100 d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-2 px-2 py-1 fs-6">
                                                        <i class="bi bi-clock-history me-1"></i> {{ row.nama_shift }}
                                                    </span>
                                                    <div class="text-muted small d-flex align-items-center gap-1 mt-1">
                                                        <i class="bi bi-diagram-3"></i> Total Rule: <strong class="text-dark">{{ row.active_rules || row.total_rules || 0 }}</strong>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="displayMode === 'kode_karyawan'" class="mb-3 flex-grow-1">
                                                <div class="text-xs text-muted mb-2 text-uppercase fw-bold"><i class="bi bi-filter-circle me-1"></i> Sumber Filter</div>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <span
                                                        v-for="(src, i) in (row.sources || [])"
                                                        :key="i"
                                                        class="badge bg-light text-secondary border px-2 py-1"
                                                    >
                                                        {{ src.source_label }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div v-else class="flex-grow-1 mb-3"></div>

                                            <div class="mt-auto border-top pt-3">
                                                <div v-if="displayMode !== 'kode_karyawan'">
                                                    <button
                                                        class="stitch-btn danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2"
                                                        @click="deactivateGroupShift(row)"
                                                    >
                                                        <i class="bi bi-trash3"></i> Hapus Shift
                                                    </button>
                                                </div>
                                                <div v-else class="d-flex flex-wrap gap-2">
                                                    <button
                                                        v-for="(src, i) in (row.sources || [])"
                                                        :key="`src-${i}`"
                                                        class="stitch-btn danger btn-sm flex-grow-1 d-flex align-items-center justify-content-center gap-2"
                                                        @click="deactivateSourceRule(src.rule)"
                                                    >
                                                        <i class="bi bi-trash3"></i> Hapus {{ i + 1 }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #buat>
                        <div class="p-4 rounded-4" style="background: var(--surface-soft)">
                            <div class="stitch-card p-4">
                                <div class="mb-3">
                                    <h5 class="text-dark fw-bold m-0">
                                        <i class="bi bi-plus-circle me-2"></i>
                                        Tambah Shift Rule
                                    </h5>
                                    <p class="text-muted small m-0 mt-1">
                                        Alur sederhana: pilih 1 jenis filter target, pilih banyak nilainya, lalu pilih shift.
                                    </p>
                                </div>

                                <div class="row g-4">
                                    <!-- Step 1 Card -->
                                    <div class="col-12 col-md-4">
                                        <div class="p-4 rounded-4 h-100 bg-white border border-light shadow-sm transition-hover">
                                            <div class="d-flex align-items-center gap-2 mb-3">
                                                <span class="badge bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">1</span>
                                                <h6 class="fw-bold m-0 text-dark">Pilih Scope</h6>
                                            </div>
                                            <div class="mb-3">
                                                <label class="stitch-filter-label"><i class="bi bi-building me-1"></i> Kode Perusahaan</label>
                                                <el-select v-model="form.kode_perusahaan" class="el-select-full">
                                                    <el-option
                                                        v-for="opt in (filterOptions?.kodePerusahaan || [])"
                                                        :key="opt.value"
                                                        :label="opt.label"
                                                        :value="opt.value"
                                                    />
                                                </el-select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="stitch-filter-label"><i class="bi bi-diagram-2 me-1"></i> Jenis Filter Target</label>
                                                <el-select v-model="form.scope_type" class="el-select-full">
                                                    <el-option
                                                        v-for="opt in scopeTypeOptions"
                                                        :key="opt.value"
                                                        :label="opt.label"
                                                        :value="opt.value"
                                                    />
                                                </el-select>
                                                <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle"></i> Tentukan level struktur organisasi.</small>
                                            </div>
                                            <div>
                                                <label class="stitch-filter-label"><i class="bi bi-toggle-on me-1"></i> Status Rule</label>
                                                <el-select v-model="form.flag_aktif" class="el-select-full">
                                                    <el-option label="Aktif" value="Y" />
                                                    <el-option label="Non Aktif" value="N" />
                                                </el-select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 2 Card -->
                                    <div class="col-12 col-md-4">
                                        <div class="p-4 rounded-4 h-100 bg-white border border-light shadow-sm transition-hover">
                                            <div class="d-flex align-items-center gap-2 mb-3">
                                                <span class="badge bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">2</span>
                                                <h6 class="fw-bold m-0 text-dark">Tentukan Nilai</h6>
                                            </div>
                                            <div v-if="form.scope_type !== 'global'">
                                                <label class="stitch-filter-label"><i class="bi bi-ui-checks-grid me-1"></i> Nilai Filter (Multi-select)</label>
                                                <el-select
                                                    v-model="form.scope_values"
                                                    multiple
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    filterable
                                                    :allow-create="form.scope_type === 'kode_karyawan'"
                                                    default-first-option
                                                    clearable
                                                    class="el-select-full"
                                                    placeholder="Pilih 1 atau banyak nilai"
                                                    style="min-height: 120px;"
                                                >
                                                    <el-option
                                                        v-for="opt in currentScopeOptions"
                                                        :key="opt.value"
                                                        :label="String(opt.label)"
                                                        :value="opt.value"
                                                    />
                                                </el-select>
                                                <div class="mt-3 p-3 bg-light rounded-3 text-muted small border border-dashed text-center">
                                                    <i class="bi bi-lightbulb text-warning mb-1 fs-5 d-block"></i>
                                                    Anda dapat memilih beberapa nilai sekaligus seperti Divisi A, B, dan C.
                                                </div>
                                            </div>
                                            <div v-else class="d-flex flex-column align-items-center justify-content-center h-100 border border-dashed rounded-3 p-3 bg-light">
                                                <i class="bi bi-globe-americas fs-1 text-primary mb-2"></i>
                                                <p class="text-center text-muted small m-0 fw-medium">Mode Global Aktif</p>
                                                <p class="text-center text-secondary m-0" style="font-size: 0.75rem;">Rule ini akan berlaku untuk seluruh organisasi tanpa terkecuali.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3 Card -->
                                    <div class="col-12 col-md-4">
                                        <div class="p-4 rounded-4 h-100 bg-white border border-light shadow-sm transition-hover">
                                            <div class="d-flex align-items-center gap-2 mb-3">
                                                <span class="badge bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">3</span>
                                                <h6 class="fw-bold m-0 text-dark">Pilih Shift</h6>
                                            </div>
                                            <div>
                                                <label class="stitch-filter-label"><i class="bi bi-clock-history me-1"></i> Shift Target (Multi-select)</label>
                                                <el-select
                                                    v-model="form.id_shift"
                                                    multiple
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    filterable
                                                    clearable
                                                    class="el-select-full"
                                                    placeholder="Pilih shift"
                                                    style="min-height: 120px;"
                                                >
                                                    <el-option
                                                        v-for="opt in (filterOptions?.shift || [])"
                                                        :key="opt.value"
                                                        :label="opt.label"
                                                        :value="opt.value"
                                                    />
                                                </el-select>
                                                <div class="mt-3 p-3 bg-light rounded-3 text-muted small border border-dashed text-center">
                                                    <i class="bi bi-link-45deg text-success mb-1 fs-5 d-block"></i>
                                                    Pilih shift yang akan diassign ke nilai filter pada Step 2.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info mt-4 py-3 px-4 mb-0 border-info-subtle shadow-sm d-flex align-items-center gap-3 rounded-4">
                                    <i class="bi bi-calculator text-info fs-3"></i>
                                    <div>
                                        <div class="fw-bold text-dark">Perkiraan kombinasi rule baru: <span class="badge bg-info text-white ms-1 fs-6">{{ estimatedCombination }}</span></div>
                                        <div class="text-muted small mt-1">Duplikat pada parameter filter yang sama akan otomatis diabaikan oleh sistem.</div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button class="stitch-btn secondary" @click="resetForm">
                                        Reset
                                    </button>
                                    <button
                                        class="stitch-btn primary"
                                        :disabled="loading.submit || !canSubmitRule"
                                        @click="submitRule"
                                    >
                                        <span v-if="loading.submit">
                                            <i class="bi bi-arrow-repeat spin-icon me-1"></i>
                                            Menyimpan...
                                        </span>
                                        <span v-else>
                                            <i class="bi bi-check2-circle me-1"></i>
                                            Simpan Shift Rule
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </ApprovalTabs>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { ElMessage } from "element-plus";
import HeaderKpi from "@/components/legacy/header.vue";
import ApprovalTabs from "@/components/legacy/toggleSectionV2.vue";
import quickStat from "@/components/legacy/quickStat.vue";
import ModernFilter from "@/components/legacy/Filter/modernFilter.vue";
import TableNoir from "@/components/legacy/table-noir.vue";

export default {
    name: "ShiftRuleAdmin",
    components: {
        HeaderKpi,
        ApprovalTabs,
        quickStat,
        ModernFilter,
        TableNoir,
    },
    props: {
        serverTime: { type: String, default: null },
        initialStats: { type: Object, default: () => ({}) },
        filterOptions: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            activeTab: "daftar",
            tabs: [
                { id: "daftar", label: "Daftar Rule", icon: "bi bi-table d-flex justify-content-center align-items-center", count:null },
                { id: "buat", label: "Tambah Rule", icon: "bi bi-plus-circle d-flex justify-content-center align-items-center", count:null },
            ],
            rules: [],
            displayMode: "id_divisi",
            displayModeOptions: [
                { value: "id_divisi", label: "Tampilkan per Divisi" },
                { value: "id_sub_divisi", label: "Tampilkan per Sub Divisi" },
                { value: "id_level", label: "Tampilkan per Level" },
                { value: "id_workgroup", label: "Tampilkan per Workgroup" },
                { value: "cabang_untuk_gaji", label: "Tampilkan per Cabang Gaji" },
                { value: "kode_karyawan", label: "Tampilkan per Karyawan" },
            ],
            selectedGroup: null,
            groupDetailItems: [],
            filters: {
                search: "",
                flag_aktif: "",
            },
            pagination: {
                page: 1,
                limit: 10,
                total: 0,
                totalPages: 1,
                links: {},
            },
            loading: {
                list: false,
                submit: false,
                detail: false,
            },
            stats: { ...this.initialStats },
            form: {
                kode_perusahaan: "001",
                scope_type: "id_divisi",
                scope_values: [],
                id_shift: [],
                flag_aktif: "Y",
            },
            columns: [
                { key: "group_label", title: "Group Filter", width: "280px" },
                { key: "total_shifts", title: "Total Shift", width: "120px" },
                { key: "total_rules", title: "Total Rule", width: "120px" },
                { key: "active_rules", title: "Rule Aktif", width: "120px" },
                { key: "aksi", title: "Aksi", width: "140px" },
            ],
        };
    },
    computed: {
        currentDate() {
            return this.serverTime ? new Date(this.serverTime) : new Date();
        },
        dayNight() {
            const hour = this.currentDate.getHours();
            return hour >= 6 && hour < 18 ? "Siang" : "Malam";
        },
        formattedDate() {
            return this.currentDate.toLocaleDateString("id-ID", {
                weekday: "long",
                day: "2-digit",
                month: "long",
                year: "numeric",
            });
        },
        quickStatField() {
            return [
                {
                    class: "col-md-3 col-6",
                    size: "",
                    title: "Total Rule",
                    value: this.stats?.total || 0,
                    icon: "bi bi-diagram-3",
                    bgIcon: "bg-primary-light",
                    unit: "",
                },
                {
                    class: "col-md-3 col-6",
                    size: "",
                    title: "Aktif",
                    value: this.stats?.aktif || 0,
                    icon: "bi bi-check-circle",
                    bgIcon: "bg-success-light",
                    unit: "",
                },
                {
                    class: "col-md-3 col-6",
                    size: "",
                    title: "Non Aktif",
                    value: this.stats?.non_aktif || 0,
                    icon: "bi bi-pause-circle",
                    bgIcon: "bg-warning-light",
                    unit: "",
                },
                {
                    class: "col-md-3 col-6",
                    size: "",
                    title: "Spesifik Karyawan",
                    value: this.stats?.spesifik_karyawan || 0,
                    icon: "bi bi-person-badge",
                    bgIcon: "bg-primary-light",
                    unit: "",
                },
            ];
        },
        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: "Cari group filter...",
                searchCol: 4,
                showActions: true,
                showReset: true,
                showActiveFilters: true,
                filters: [
                    {
                        key: "flag_aktif",
                        label: "Status",
                        type: "select",
                        colClass: "col-md-2 col-6",
                        options: this.filterOptions?.flagAktif || [],
                        clearable: true,
                    },
                ],
            };
        },
        selectedGroupLabel() {
            const found = this.displayModeOptions.find((e) => e.value === this.displayMode);
            return found ? found.label : this.displayMode;
        },
        estimatedCombination() {
            const shiftCount = Array.isArray(this.form.id_shift) ? this.form.id_shift.length : 0;
            const scopeCount =
                this.form.scope_type === "global"
                    ? 1
                    : Array.isArray(this.form.scope_values)
                      ? this.form.scope_values.length
                      : 0;
            return shiftCount * scopeCount;
        },
        canSubmitRule() {
            const hasShift = Array.isArray(this.form.id_shift) && this.form.id_shift.length > 0;
            if (!hasShift) return false;
            if (this.form.scope_type === "global") return true;
            return Array.isArray(this.form.scope_values) && this.form.scope_values.length > 0;
        },
        scopeTypeOptions() {
            return [
                { value: "id_divisi", label: "Divisi" },
                { value: "id_sub_divisi", label: "Sub Divisi" },
                { value: "id_level", label: "Level" },
                { value: "id_workgroup", label: "Workgroup" },
                { value: "cabang_untuk_gaji", label: "Cabang Untuk Gaji" },
                { value: "kode_karyawan", label: "Kode Karyawan" },
                { value: "global", label: "Global (tanpa filter)" },
            ];
        },
        currentScopeOptions() {
            switch (this.form.scope_type) {
                case "id_divisi":
                    return this.filterOptions?.divisi || [];
                case "id_sub_divisi":
                    return this.filterOptions?.subDivisi || [];
                case "id_level":
                    return this.filterOptions?.level || [];
                case "id_workgroup":
                    return this.filterOptions?.workgroup || [];
                case "cabang_untuk_gaji":
                    return this.filterOptions?.cabang || [];
                case "kode_karyawan":
                    return this.filterOptions?.kodeKaryawan || [];
                default:
                    return [];
            }
        },
    },
    mounted() {
        this.loadRules();
        this.loadStats();
    },
    methods: {
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },
        handleFilterUpdate(newFilters) {
            this.filters = { ...this.filters, ...newFilters };
            this.pagination.page = 1;
            this.loadRules();
        },
        onDisplayModeChange() {
            this.pagination.page = 1;
            this.selectedGroup = null;
            this.groupDetailItems = [];
            this.loadRules();
        },
        async loadRules(url = null) {
            this.loading.list = true;
            try {
                const params = {
                    search: this.filters.search || "",
                    flag_aktif: this.filters.flag_aktif || "",
                    view_by: this.displayMode,
                    page: this.pagination.page,
                    limit: this.pagination.limit,
                };
                const res = await axios.get(
                    url || "/leader-shift/admin/shift-rule/groups",
                    { params },
                );
                this.rules = Array.isArray(res.data?.result) ? res.data.result : [];
                this.pagination.total = res.data?.pagination?.total || 0;
                this.pagination.totalPages = res.data?.pagination?.total_pages || 1;
                this.pagination.page = res.data?.pagination?.current_page || 1;
                this.pagination.links = res.data?.pagination || {};
            } catch (err) {
                console.error(err);
                ElMessage.error("Gagal memuat shift rule");
            } finally {
                this.loading.list = false;
            }
        },
        async openGroupDetail(item) {
            if (!item) return;
            this.selectedGroup = item;
            this.loading.detail = true;
            this.groupDetailItems = [];
            try {
                const params = {
                    view_by: this.displayMode,
                    group_value: item.group_value ?? "__NULL__",
                    flag_aktif: this.filters.flag_aktif || "",
                };
                const res = await axios.get("/leader-shift/admin/shift-rule/group-detail", { params });
                this.groupDetailItems = Array.isArray(res.data?.result) ? res.data.result : [];
            } catch (err) {
                console.error(err);
                ElMessage.error("Gagal memuat detail group");
            } finally {
                this.loading.detail = false;
            }
        },
        async deactivateGroupShift(shiftRow) {
            if (!this.selectedGroup || !shiftRow?.id_shift) return;
            this.loading.detail = true;
            try {
                await axios.post("/leader-shift/admin/shift-rule/deactivate-group-shift", {
                    view_by: this.displayMode,
                    group_value: this.selectedGroup.group_value ?? "__NULL__",
                    id_shift: shiftRow.id_shift,
                });
                ElMessage.success("Shift pada group berhasil dinonaktifkan");
                await this.openGroupDetail(this.selectedGroup);
                await this.loadRules();
                await this.loadStats();
            } catch (err) {
                const message = err?.response?.data?.message || "Gagal menonaktifkan shift";
                ElMessage.error(message);
            } finally {
                this.loading.detail = false;
            }
        },
        async deactivateSourceRule(rule) {
            this.loading.detail = true;
            try {
                await axios.post("/leader-shift/admin/shift-rule/deactivate-source", rule);
                ElMessage.success("Source rule berhasil dinonaktifkan");
                await this.openGroupDetail(this.selectedGroup);
                await this.loadRules();
                await this.loadStats();
            } catch (err) {
                const message = err?.response?.data?.message || "Gagal menonaktifkan source rule";
                ElMessage.error(message);
            } finally {
                this.loading.detail = false;
            }
        },
        goToPage(urlOrPage) {
            if (typeof urlOrPage === "string" && urlOrPage) {
                this.loadRules(urlOrPage);
                return;
            }
            if (typeof urlOrPage === "number") {
                this.pagination.page = urlOrPage;
                this.loadRules();
            }
        },
        async loadStats() {
            try {
                const res = await axios.get("/leader-shift/admin/shift-rule/stats");
                this.stats = res.data?.result || { ...this.stats };
            } catch (err) {
                console.error(err);
            }
        },
        resetForm() {
            this.form = {
                kode_perusahaan: "001",
                scope_type: "id_divisi",
                scope_values: [],
                id_shift: [],
                flag_aktif: "Y",
            };
        },
        async submitRule() {
            if (!this.canSubmitRule) {
                ElMessage.warning(
                    this.form.scope_type === "global"
                        ? "Shift wajib dipilih"
                        : "Pilih nilai filter target dan shift",
                );
                return;
            }
            this.loading.submit = true;
            try {
                const payload = {
                    kode_perusahaan: this.form.kode_perusahaan,
                    id_shift: this.form.id_shift,
                    id_divisi: this.form.scope_type === "id_divisi" ? this.form.scope_values : [],
                    id_sub_divisi:
                        this.form.scope_type === "id_sub_divisi" ? this.form.scope_values : [],
                    id_level: this.form.scope_type === "id_level" ? this.form.scope_values : [],
                    id_workgroup:
                        this.form.scope_type === "id_workgroup" ? this.form.scope_values : [],
                    cabang_untuk_gaji:
                        this.form.scope_type === "cabang_untuk_gaji"
                            ? this.form.scope_values
                            : [],
                    kode_karyawan:
                        this.form.scope_type === "kode_karyawan" ? this.form.scope_values : [],
                    flag_aktif: this.form.flag_aktif,
                };

                const res = await axios.post("/leader-shift/admin/shift-rule", payload);
                const info = res?.data?.result;
                if (info && typeof info.created !== "undefined") {
                    ElMessage.success(
                        `Berhasil: ${info.created}, duplikat dilewati: ${info.skipped}`,
                    );
                } else {
                    ElMessage.success("Shift rule berhasil diproses");
                }
                this.resetForm();
                this.activeTab = "daftar";
                this.loadRules();
                this.loadStats();
            } catch (err) {
                const message = err?.response?.data?.message || "Gagal menambahkan shift rule";
                ElMessage.error(message);
            } finally {
                this.loading.submit = false;
            }
        },
    },
    watch: {
        "form.scope_type"() {
            this.form.scope_values = [];
        },
    },
};
</script>

<style scoped>
.stitch-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow);
}

.stitch-filter-label {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.4rem;
    font-weight: 600;
    text-transform: uppercase;
}

.stitch-action-btn {
    height: 38px;
    width: 38px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    color: var(--text);
}

.stitch-btn {
    border: 1px solid transparent;
    border-radius: 12px;
    font-weight: 600;
    padding: 0.55rem 1rem;
}

.stitch-btn.primary {
    background: var(--primary);
    color: #fff;
}

.stitch-btn.secondary {
    background: #fff;
    color: var(--text);
    border-color: var(--border);
}

.stitch-btn.danger {
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
    border-color: var(--hcis-red-200);
}

.spin-icon {
    display: inline-block;
    animation: spin 1s linear infinite;
}

.bg-light{
    background-color: #eef2f7 !important;
}

.alert-light{
    background-color: #eef2f7 !important;
}

.cuti-app-container {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --radius-lg: 16px;
    --gradient-primary: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );

    background-color: #eef2f7;
    min-height: 100vh;
    color: var(--text);
    padding-bottom: 2rem;
}

.main-content-wrapper {
    position: relative;
    z-index: 10;
}

.content-container {
    min-height: 500px;
    overflow: hidden;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Match stitch action behavior from Leader/Self Shift */
.stitch-action-btn:hover {
    border-color: var(--primary-light);
    color: var(--primary);
    background: var(--surface-soft);
}

.bi{
    display:contents !important;
}

/* Normalize Element Plus control heights to existing pages */
:deep(.el-input__wrapper),
:deep(.el-select .el-select__wrapper) {
    min-height: 38px;
    border-radius: 10px;
}

:deep(.el-input__wrapper.is-focus),
:deep(.el-select .el-select__wrapper.is-focused) {
    box-shadow: 0 0 0 3px var(--primary-light) !important;
}
</style>
