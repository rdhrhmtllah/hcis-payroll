<template>
    <div class="container-fluid px-0 fade-in">
        <!-- Header -->
        <HeaderKpi
            title="Module Role Manager"
            subtitle="Kelola hak akses dan role untuk modul yang Anda kelola"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />
        <BannerAksesPeran :modul-baru="modulBaru" :tautan="tautanAksesPeran" />

        <!-- Mode Tabs: Unified vs Legacy -->
        <div class="mode-switcher mb-3">
            <button class="mode-btn" :class="{ active: viewMode === 'unified' }" @click="viewMode = 'unified'">
                <i class="bi bi-shield-check me-1"></i> Unified Roles
            </button>
            <button class="mode-btn" :class="{ active: viewMode === 'legacy' }" @click="viewMode = 'legacy'">
                <i class="bi bi-clock-history me-1"></i> Legacy Access
            </button>
        </div>

        <!-- Unified View -->
        <div v-if="viewMode === 'unified'">
            <UserRoleManager
                :allowedModules="unifiedModules"
                :scope="scopeMetadata"
                :showModuleFilter="false"
                @save-roles="onRolesSaved"
            />
        </div>

        <!-- Legacy View -->
        <div v-else>
            <!-- Main Content -->
            <div class="row justify-content-center g-4">
                <!-- Kolom Kiri: Daftar Administrator -->
                <div class="col-lg-4">
                    <div class="master-panel">
                        <div class="panel-header">
                            <h5 class="panel-title">
                                <i class="bi d-flex justify-content-center align-items-center bi-person-badge me-2"></i
                                >Daftar Administrator
                            </h5>
                            <div class="search-box-sm">
                                <i class="bi d-flex justify-content-center align-items-center bi-search"></i>
                                <input
                                    type="text"
                                    v-model="adminSearch"
                                    placeholder="Cari admin..."
                                    class="search-input-sm"
                                />
                            </div>
                        </div>
                        <div v-if="loading.initial" class="panel-body-loading">
                            <div class="loading-spinner">
                                <i class="bi d-flex justify-content-center align-items-center bi-arrow-repeat"></i>
                            </div>
                            <p>Memuat Administrator...</p>
                        </div>
                        <div v-else-if="!allAdmins.length" class="empty-state-compact">
                            <i class="bi d-flex justify-content-center align-items-center bi-person-x"></i>
                            <h6>Tidak ada data admin</h6>
                        </div>
                        <div class="panel-body">
                            <div v-if="filteredAdmins.length > 0" class="admin-list">
                                <div
                                    v-for="admin in filteredAdmins"
                                    :key="admin.UserID_Web"
                                    class="admin-card"
                                    :class="{
                                        active: selectedAdmin && selectedAdmin.UserID_Web === admin.UserID_Web,
                                    }"
                                    @click="selectAdmin(admin)"
                                >
                                    <div class="admin-info">
                                        <div class="avatar-sm">
                                            {{ getInitials(admin.Nama_User) }}
                                        </div>
                                        <div class="admin-details">
                                            <h6 class="admin-name">
                                                {{ admin.Nama_User }}
                                            </h6>
                                            <p class="admin-id">ID: {{ admin.UserID_Web }}</p>
                                        </div>
                                    </div>
                                    <span class="badge-role">{{ admin.Role }}</span>
                                </div>
                            </div>
                            <div v-else class="empty-state-compact">
                                <i class="bi d-flex justify-content-center align-items-center bi-person-x"></i>
                                <h6>Admin tidak ditemukan</h6>
                                <p>Tidak ada admin yang cocok dengan pencarian.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Detail Izin -->
                <div class="col-lg-8">
                    <div class="detail-panel">
                        <div v-if="!selectedAdmin" class="empty-state-detail">
                            <i class="bi d-flex justify-content-center align-items-center bi-person-check-fill"></i>
                            <h4>Pilih Administrator</h4>
                            <p>
                                Klik salah satu administrator di sebelah kiri untuk melihat dan mengelola hak akses
                                mereka.
                            </p>
                        </div>
                        <div v-else-if="!loading.permissions" class="detail-content">
                            <div class="detail-header">
                                <h4 class="detail-title">
                                    Izin untuk:
                                    <span class="admin-name-highlight">{{ selectedAdmin.Nama_User }}</span>
                                </h4>
                                <span class="badge-role-detail">{{ selectedAdmin.Role }}</span>
                            </div>

                            <ApprovalTabs :tabs="permissionTabs" :activeTab="activeTab" @tab-change="handleTabChange">
                                <!-- Tab 1: Akses Halaman -->
                                <template #page_access>
                                    <div class="tab-pane-content">
                                        <p class="tab-description">
                                            Pilih halaman mana saja yang dapat diakses oleh administrator ini.
                                        </p>
                                        <div class="permission-group-container">
                                            <div
                                                v-for="(pages, header) in groupedMenus"
                                                :key="header"
                                                class="permission-group"
                                            >
                                                <div class="permission-group-header">
                                                    <h6>{{ header }}</h6>
                                                </div>
                                                <div class="permission-group-body">
                                                    <div
                                                        v-for="page in pages"
                                                        :key="page.Jenis_page"
                                                        class="form-check form-check-lg"
                                                    >
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :value="page.Jenis_page"
                                                            :id="`page-${page.Jenis_page}`"
                                                            v-model="selectedAdminPermissions.pages"
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            :for="`page-${page.Jenis_page}`"
                                                        >
                                                            {{ page.Nama_Menu }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Tab 2: Izin Aksi -->
                                <template #action_permissions>
                                    <div class="tab-pane-content">
                                        <p class="tab-description">
                                            Atur aksi yang dapat dilakukan (View, Create, Approve, etc.) pada setiap
                                            halaman yang telah diberi akses.
                                        </p>
                                        <div
                                            v-if="selectedAdminPermissions.pages.length === 0"
                                            class="empty-state-compact"
                                        >
                                            <i
                                                class="bi d-flex justify-content-center align-items-center bi-shield-slash"
                                            ></i>
                                            <h6>Belum ada halaman</h6>
                                            <p>Beri akses ke halaman terlebih dahulu di tab "Akses Halaman".</p>
                                        </div>
                                        <div v-else class="permission-group-container">
                                            <div
                                                v-for="pageId in selectedAdminPermissions.pages"
                                                :key="`action-${pageId}`"
                                                class="permission-group"
                                            >
                                                <div class="permission-group-header">
                                                    <h6>
                                                        {{ getMenuName(pageId) }}
                                                    </h6>
                                                </div>
                                                <div class="permission-group-body-horizontal">
                                                    <div
                                                        v-for="action in allActions"
                                                        :key="action.Id_Aksi"
                                                        class="form-check form-check-inline"
                                                    >
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :value="action.Id_Aksi"
                                                            :id="`action-${pageId}-${action.Id_Aksi}`"
                                                            v-model="selectedAdminPermissions.actions[pageId]"
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            :for="`action-${pageId}-${action.Id_Aksi}`"
                                                        >
                                                            {{ action.Nama_Aksi }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Tab 3: Pembatasan Wilayah -->
                                <template #feature_restrictions>
                                    <div class="tab-pane-content">
                                        <p class="tab-description">
                                            Batasi grup fitur mana yang dapat dikelola oleh admin ini saat mereka
                                            mendelegasikan akses ke karyawan lain.
                                        </p>
                                        <div class="permission-group-container">
                                            <div class="permission-group">
                                                <div class="permission-group-header">
                                                    <h6>Grup Fitur HCIS</h6>
                                                </div>
                                                <div class="permission-group-body">
                                                    <div
                                                        v-for="feature in allFeatureGroups"
                                                        :key="feature.ID_Grup_Fitur"
                                                        class="form-check form-check-lg"
                                                    >
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :value="feature.ID_Grup_Fitur"
                                                            :id="`feature-${feature.ID_Grup_Fitur}`"
                                                            v-model="selectedAdminPermissions.featureGroups"
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            :for="`feature-${feature.ID_Grup_Fitur}`"
                                                        >
                                                            {{ feature.Kode_Grup_Fitur }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Tab 4: Pembatasan Konten -->
                                <template #content_restrictions>
                                    <div class="tab-pane-content">
                                        <p class="tab-description">
                                            Batasi tab atau konten spesifik yang dapat dilihat atau dikelola oleh admin
                                            ini di halaman tertentu.
                                        </p>
                                        <div class="permission-group-container">
                                            <div class="permission-group">
                                                <div class="permission-group-header">
                                                    <h6>Tab Halaman Manajemen Cuti</h6>
                                                </div>
                                                <div class="permission-group-body">
                                                    <div
                                                        v-for="tab in allContentTabs"
                                                        :key="tab.id"
                                                        class="form-check form-check-lg"
                                                    >
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :value="tab.id"
                                                            :id="`content-${tab.id}`"
                                                            v-model="selectedAdminPermissions.contentTabs"
                                                        />
                                                        <label class="form-check-label" :for="`content-${tab.id}`">
                                                            {{ tab.label }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </ApprovalTabs>

                            <div class="detail-footer">
                                <button class="btn-modern btn-primary" @click="savePermissions">
                                    <i class="bi d-flex justify-content-center align-items-center bi-save me-2"></i
                                    >Simpan Perubahan
                                </button>
                            </div>
                        </div>
                        <div v-else class="panel-body-loading">
                            <div class="loading-spinner">
                                <i class="bi d-flex justify-content-center align-items-center bi-arrow-repeat"></i>
                            </div>
                            <p>Memuat Izin untuk {{ selectedAdmin.Nama_User }}...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /legacy -->
    </div>
</template>

<script>
import BannerAksesPeran from '../../AksesPeran/components/BannerAksesPeran.vue';
import HeaderKpi from '@/components/legacy/header.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import UserRoleManager from '../../role-manager/UserRoleManager.vue';
import { ElNotification } from 'element-plus';
import axios from 'axios';
import 'element-plus/dist/index.css';

export default {
    name: 'PagesAccessSystem',
    props: {
        modulBaru: { type: Array, default: () => [] },
        tautanAksesPeran: { type: String, default: null },
        scopeMetadata: {
            type: Object,
            default: () => null,
        },
    },
    components: {
        BannerAksesPeran,
        HeaderKpi,
        ApprovalTabs,
        UserRoleManager,
    },
    data() {
        return {
            viewMode: 'unified',
            unifiedModules: [],
            currentDate: new Date(),
            activeTab: 'page_access',
            adminSearch: '',
            allAdmins: [],
            allMenus: [],
            allActions: [],
            allFeatureGroups: [],
            allContentTabs: [],
            selectedAdmin: null,
            selectedAdminPermissions: {
                pages: [],
                actions: {},
                featureGroups: [],
                contentTabs: [],
            },
            loading: {
                initial: false,
                permissions: false,
                saving: false,
            },
        };
    },
    computed: {
        dayNight() {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        filteredAdmins() {
            if (!this.adminSearch) {
                return this.allAdmins;
            }
            const search = this.adminSearch.toLowerCase();
            return this.allAdmins.filter(
                (admin) => admin.Nama_User.toLowerCase().includes(search) || admin.Role.toLowerCase().includes(search),
            );
        },
        permissionTabs() {
            return [
                {
                    id: 'page_access',
                    label: 'Akses Halaman',
                    icon: 'd-flex justify-content-center align-items-center bi bi-files',
                },
                {
                    id: 'action_permissions',
                    label: 'Izin Aksi',
                    icon: 'd-flex justify-content-center align-items-center bi bi-toggles',
                },
                {
                    id: 'feature_restrictions',
                    label: 'Pembatasan Wilayah',
                    icon: 'd-flex justify-content-center align-items-center bi bi-geo-alt-fill',
                },
                {
                    id: 'content_restrictions',
                    label: 'Pembatasan Konten',
                    icon: 'd-flex justify-content-center align-items-center bi bi-layout-text-sidebar-reverse',
                },
            ];
        },
        groupedMenus() {
            return this.allMenus.reduce((acc, menu) => {
                const header = menu.Header_Label || 'Lainnya';
                if (!acc[header]) {
                    acc[header] = [];
                }
                acc[header].push(menu);
                return acc;
            }, {});
        },
    },
    methods: {
        formatDateString(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },
        selectAdmin(admin) {
            this.selectedAdmin = admin;
            console.log(admin);
            this.loadPermissionsForAdmin(admin.UserID_Web);
            this.activeTab = 'page_access'; // Reset to first tab on selection
        },
        async loadPermissionsForAdmin(userId) {
            //
            this.loading.permissions = true;
            try {
                const response = await axios.get(`/api/v1/admin/role-manager/permissions/${userId}`);
                const permissions = response.data;

                // Buat objek actions yang lengkap terlebih dahulu
                const newActions = {};
                this.allMenus.forEach((menu) => {
                    // Ambil izin yang ada, atau default ke array kosong
                    newActions[menu.Jenis_page] = permissions.actions?.[menu.Jenis_page] || [];
                });

                // Ganti seluruh objek selectedAdminPermissions agar reaktif
                this.selectedAdminPermissions = {
                    pages: permissions.pages || [],
                    actions: newActions,
                    featureGroups: permissions.featureGroups || [],
                    contentTabs: permissions.contentTabs || [],
                };
            } catch (error) {
                console.error('Gagal memuat izin admin:', error);
                ElNotification({
                    title: 'Error',
                    message: 'Gagal memuat data izin untuk admin ini.',
                    type: 'error',
                });
            } finally {
                this.loading.permissions = false;
            }
        },
        getMenuName(pageId) {
            const menu = this.allMenus.find((m) => m.Jenis_page === pageId);
            return menu ? menu.Nama_Menu : 'Halaman Tidak Dikenal';
        },
        async savePermissions() {
            if (!this.selectedAdmin) return;

            this.loading.saving = true;
            try {
                await axios.post(
                    `/api/v1/admin/role-manager/permissions/${this.selectedAdmin.UserID_Web}`,
                    this.selectedAdminPermissions,
                );

                ElNotification({
                    title: 'Sukses',
                    message: `Hak akses untuk ${this.selectedAdmin.Nama_User} berhasil diperbarui.`,
                    type: 'success',
                });

                this.loadPermissionsForAdmin(this.selectedAdmin.UserID_Web);
            } catch (error) {
                console.error('Gagal menyimpan izin:', error);
                ElNotification({
                    title: 'Error',
                    message: error.response?.data?.message || 'Terjadi kesalahan saat menyimpan.',
                    type: 'error',
                });
            } finally {
                this.loading.saving = false;
            }
        },
        async fetchInitialData() {
            this.loading.initial = true;
            try {
                const response = await axios.get('/api/v1/admin/role-manager/initial-data');
                const data = response.data;
                this.allAdmins = data.admins;
                this.allMenus = data.menus;
                this.allActions = data.actions;
                this.allFeatureGroups = data.featureGroups;
                this.allContentTabs = data.contentTabs;
            } catch (error) {
                console.error('Gagal mengambil data awal:', error);
                ElNotification({
                    title: 'Error',
                    message: 'Gagal memuat data konfigurasi awal.',
                    type: 'error',
                });
            } finally {
                this.loading.initial = false;
            }
        },
        async fetchUnifiedModules() {
            try {
                const res = await axios.get('/api/v1/admin/role-manager/dashboard-stats');
                this.unifiedModules = res.data.modules || [];
            } catch (e) {
                console.error('Failed to fetch unified modules:', e);
            }
        },
        onRolesSaved() {
            this.fetchUnifiedModules();
        },
    },
    mounted() {
        this.fetchInitialData();
        this.fetchUnifiedModules();
    },
    watch: {
        // Watcher untuk membersihkan izin aksi jika halaman di-uncheck
        'selectedAdminPermissions.pages': {
            handler(newPages, oldPages) {
                if (!this.selectedAdmin) return;

                const removedPages = oldPages.filter((p) => !newPages.includes(p));
                removedPages.forEach((pageId) => {
                    if (this.selectedAdminPermissions.actions[pageId]) {
                        this.selectedAdminPermissions.actions[pageId] = [];
                    }
                });
            },
            deep: true,
        },
    },
};
</script>

<style scoped>
/* Mode Switcher */
.mode-switcher {
    display: flex;
    gap: 0.25rem;
    padding: 0.35rem;
    background: var(--hcis-slate-100);
    border-radius: 12px;
    width: fit-content;
}

.mode-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 10px;
    background: transparent;
    color: var(--hcis-slate-500);
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.25s ease;
}

.mode-btn:hover {
    background: rgba(99, 102, 241, 0.05);
    color: var(--hcis-indigo-500);
}

.mode-btn.active {
    background: #fff;
    color: var(--hcis-indigo-500);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    font-weight: 600;
}
/* Menggunakan variabel CSS dari file referensi untuk konsistensi */
:root {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-50);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --radius: 12px;
}

.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Master Panel (Kiri) */
.master-panel {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    height: calc(100vh - 200px);
    display: flex;
    flex-direction: column;
}

.panel-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}

.panel-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 1rem;
}

.search-box-sm {
    position: relative;
}
.search-box-sm i {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-input-sm {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.25rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    transition: all 0.3s ease;
}
.search-input-sm:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
}

.panel-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 0.75rem;
}
.panel-body-loading {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    gap: 1rem;
    font-weight: 500;
}

.admin-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.admin-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}

.admin-card:hover {
    background-color: var(--surface-soft);
    border-color: var(--border);
}

.admin-card.active {
    background-color: var(--hcis-indigo-50); /* Indigo 50 */
    border-color: var(--primary-light);
    box-shadow: 0 0 0 2px var(--primary-light);
}

.admin-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
}

.admin-details .admin-name {
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.admin-details .admin-id {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
}

.badge-role {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
    white-space: nowrap;
}

/* Detail Panel (Kanan) */
.detail-panel {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    height: calc(100vh - 200px);
    display: flex;
    flex-direction: column;
}

.empty-state-detail {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    height: 100%;
    color: var(--text-muted);
    padding: 2rem;
}

.empty-state-detail i {
    font-size: 4rem;
    color: var(--primary);
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state-detail h4 {
    color: var(--text);
    font-weight: 600;
}

.detail-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.detail-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-muted);
    margin: 0;
}

.admin-name-highlight {
    color: var(--primary-dark);
}

.badge-role-detail {
    background-color: var(--primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
}

.tab-pane-content {
    padding: 1.5rem;
    height: 100%;
    overflow-y: auto;
}

.tab-description {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    background-color: var(--surface-soft);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border-left: 4px solid var(--primary-light);
}

.permission-group-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.permission-group {
    background-color: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 10px;
}

.permission-group-header {
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border);
}

.permission-group-header h6 {
    margin: 0;
    font-weight: 600;
    color: var(--text);
}

.permission-group-body {
    padding: 1.25rem;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.permission-group-body-horizontal {
    padding: 1.25rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.form-check-lg {
    font-size: 0.95rem;
}
.form-check-lg .form-check-input {
    width: 1.2em;
    height: 1.2em;
}
.form-check-lg .form-check-label {
    padding-top: 2px;
}

.detail-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    background-color: var(--surface-soft);
    text-align: right;
    flex-shrink: 0;
    border-bottom-left-radius: var(--radius);
    border-bottom-right-radius: var(--radius);
}

.btn-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-modern.btn-primary {
    background-color: var(--primary);
    color: white;

    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modern.btn-primary {
    background-color: var(--primary);
    color: white;
}
.btn-modern.btn-primary:hover {
    background-color: var(--primary-dark);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
}

.empty-state-compact {
    padding: 2rem;
    text-align: center;
    color: var(--text-muted);
}
.empty-state-compact i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.6;
}
.empty-state-compact h6 {
    font-weight: 600;
    color: var(--text);
}

/* Override ApprovalTabs styling */
::v-deep .nav-tabs .nav-link {
    padding: 0.75rem 1.25rem;
    font-size: 0.95rem;
}
::v-deep .tab-content {
    flex-grow: 1;
    overflow-y: auto;
}

@media (max-width: 991px) {
    .master-panel,
    .detail-panel {
        height: auto;
        min-height: 400px;
    }
}
</style>

<style scoped>
/* Menggunakan variabel CSS dari file lain untuk konsistensi */
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-100);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --success-light: var(--hcis-emerald-100);
    --warning: var(--hcis-amber-500);
    --warning-light: var(--hcis-amber-100);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-blue-500);
    --info-light: #dbeafe;
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --transition: all 0.3s ease;
}

.tab-content-improved {
    padding: 1.5rem;
    background-color: var(--surface-soft);
}

/* Dashboard Styles */
.stat-card-dashboard {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    height: 100%;
}
.stat-card-dashboard:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.stat-icon.bg-warning-light {
    background-color: var(--warning-light);
    color: var(--warning);
}
.stat-icon.bg-primary-light {
    background-color: var(--primary-light);
    color: var(--primary);
}
.stat-icon.bg-success-light {
    background-color: var(--success-light);
    color: var(--success);
}
.stat-icon.bg-info-light {
    background-color: var(--info-light);
    color: var(--info);
}
.stat-content h4 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: var(--dark);
}
.stat-content p {
    margin: 0.25rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.chart-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    height: 100%;
}
.chart-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1.5rem;
}

/* Filter & Search Section */
.filter-section {
    background: var(--surface);
    padding: 1rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
}
.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-input,
.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    transition: var(--transition);
}
.search-input {
    padding-left: 2.5rem;
}
.search-input:focus,
.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Pengajuan List Styles */
.pengajuan-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}
.pengajuan-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}
.pengajuan-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-lg);
    transform: translateY(-5px);
}
.pengajuan-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.employee-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}
.employee-name {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}
.employee-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0;
}
.pengajuan-meta {
    text-align: right;
}
.pengajuan-date {
    font-size: 0.8rem;
    color: var(--text-muted);
    display: block;
    margin-top: 0.25rem;
}
.pengajuan-content {
    flex-grow: 1;
    margin-bottom: 1.5rem;
}
.pengajuan-details {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    color: var(--text);
    font-size: 0.9rem;
}
.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.detail-item i {
    color: var(--primary);
}
.pengajuan-reason {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    font-style: italic;
    color: var(--text-muted);
}
.pengajuan-footer {
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}
.pengajuan-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
}
.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}
.btn-view:hover {
    background: var(--border);
}
.btn-success {
    background: var(--success-light);
    color: var(--success);
}
.btn-success:hover {
    background: var(--success);
    color: white;
}
.btn-danger {
    background: var(--hcis-red-100);
    color: var(--danger);
}
.btn-danger:hover {
    background: var(--danger);
    color: white;
}

/* Riwayat Table */
.table-responsive {
    overflow-x: auto;
}
.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.modern-table th {
    background-color: var(--surface-soft);
    font-weight: 600;
    color: var(--text);
}
.modern-table tr:hover {
    background-color: var(--surface-soft);
}

/* Modal Styles */
.detail-content {
    padding: 1rem;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}
.detail-label {
    font-weight: 600;
    color: var(--text-muted);
}
.detail-value {
    color: var(--dark);
    text-align: right;
}
.approval-trace-section {
    flex-direction: column;
    align-items: flex-start;
}
.approval-trace {
    width: 100%;
    margin-top: 0.5rem;
}
.trace-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: var(--transition);
}
.trace-item.trace-approved {
    background-color: var(--success-light);
}
.trace-item.trace-rejected {
    background-color: var(--hcis-red-100);
}
.trace-item.trace-pending {
    background-color: var(--surface-soft);
    border: 1px solid var(--border);
}
.trace-icon {
    font-size: 1.25rem;
}
.trace-approved .trace-icon {
    color: var(--success);
}
.trace-rejected .trace-icon {
    color: var(--danger);
}
.trace-pending .trace-icon {
    color: var(--warning);
}
.trace-info {
    flex-grow: 1;
}
.trace-name {
    font-weight: 600;
    color: var(--dark);
}
.trace-date {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.file-link {
    color: var(--primary);
    text-decoration: none;
}
.file-link:hover {
    text-decoration: underline;
}
.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
.btn-modern {
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}
.btn-modern.btn-secondary {
    background: var(--surface-soft);
    color: var(--text);
}
.btn-modern.btn-secondary:hover {
    background: var(--border);
}
.btn-modern.btn-success {
    background: var(--success);
    color: white;
}
.btn-modern.btn-danger {
    background: var(--danger);
    color: white;
}

/* Loading & Empty States */
.loading-state,
.empty-state-admin {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem;
    text-align: center;
    color: var(--text-muted);
}
.loading-spinner {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
    animation: spin 1.5s linear infinite;
}
.empty-state-admin i {
    font-size: 4rem;
    color: var(--border);
    margin-bottom: 1rem;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 768px) {
    .pengajuan-list {
        grid-template-columns: 1fr;
    }
    .stat-card-dashboard {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
