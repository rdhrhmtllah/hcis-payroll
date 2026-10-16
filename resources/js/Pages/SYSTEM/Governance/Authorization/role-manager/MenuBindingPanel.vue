<template>
    <div class="menu-binding-panel">
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-link-45deg me-2"></i>Menu Binding</h6>
            <div class="toolbar-actions">
                <select v-model="selectedGrup" class="form-select form-select-sm select-grup" @change="fetchBinding">
                    <option :value="null" disabled>Pilih Grup Fitur...</option>
                    <option v-for="g in grupList" :key="g.Id_Grup_Fitur" :value="g.Id_Grup_Fitur">
                        {{ g.Kode_Grup_Fitur }} — {{ g.Nama_Grup_Fitur }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Before selection -->
        <div v-if="!selectedGrup" class="empty-state">
            <i class="bi bi-arrow-up-circle"></i>
            <p>Pilih Grup Fitur di atas untuk mengelola menu binding.</p>
        </div>

        <!-- Loading -->
        <div v-else-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <p class="text-muted small mt-2">Memuat menu...</p>
        </div>

        <!-- Menu List -->
        <div v-else>
            <div class="search-bar mb-3">
                <i class="bi bi-search"></i>
                <input v-model="searchMenu" class="form-control form-control-sm" placeholder="Cari menu..." />
            </div>

            <div class="binding-stats mb-3">
                <span class="stat-badge stat-bound">
                    <i class="bi bi-check-circle-fill"></i> {{ boundCount }} terikat
                </span>
                <span class="stat-badge stat-available">
                    <i class="bi bi-circle"></i> {{ filteredMenus.length - boundCount }} tersedia
                </span>
            </div>

            <!-- Group by Header -->
            <div v-for="(menus, header) in groupedMenus" :key="header" class="header-group">
                <div class="header-group-title" @click="toggleCollapse(header)">
                    <i :class="collapsed[header] ? 'bi bi-chevron-right' : 'bi bi-chevron-down'"></i>
                    <span>{{ header || 'Tanpa Header' }}</span>
                    <span class="header-count"
                        >{{ menus.filter((m) => boundPages.includes(m.Jenis_page)).length }}/{{ menus.length }}</span
                    >
                </div>
                <div v-if="!collapsed[header]" class="menu-check-list">
                    <label
                        v-for="menu in menus"
                        :key="menu.Jenis_page"
                        class="menu-check-item"
                        :class="{ 'menu-bound': boundPages.includes(menu.Jenis_page) }"
                    >
                        <input type="checkbox" :value="menu.Jenis_page" v-model="boundPages" class="form-check-input" />
                        <div class="menu-check-info">
                            <div class="menu-check-name">{{ menu.Nama_Menu }}</div>
                            <div class="menu-check-page">{{ menu.Jenis_page }}</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Save Button -->
            <div class="save-bar mt-3" v-if="hasChanges">
                <span class="change-note"><i class="bi bi-pencil-fill"></i> Ada perubahan belum disimpan</span>
                <button class="btn-save" @click="saveBinding" :disabled="saving">
                    <span v-if="saving"><i class="bi bi-arrow-repeat spin"></i> Menyimpan...</span>
                    <span v-else><i class="bi bi-check-lg me-1"></i>Simpan Binding</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MenuBindingPanel',
    data() {
        return {
            grupList: [],
            selectedGrup: null,
            loading: false,
            saving: false,
            allMenus: [],
            boundPages: [],
            originalBound: [],
            searchMenu: '',
            collapsed: {},
        };
    },
    computed: {
        filteredMenus() {
            if (!this.searchMenu) return this.allMenus;
            const s = this.searchMenu.toLowerCase();
            return this.allMenus.filter(
                (m) => m.Nama_Menu?.toLowerCase().includes(s) || m.Jenis_page?.toLowerCase().includes(s),
            );
        },
        groupedMenus() {
            return this.filteredMenus.reduce((acc, m) => {
                const h = m.Header_Label || '';
                if (!acc[h]) acc[h] = [];
                acc[h].push(m);
                return acc;
            }, {});
        },
        boundCount() {
            return this.boundPages.length;
        },
        hasChanges() {
            const a = [...this.boundPages].sort();
            const b = [...this.originalBound].sort();
            return JSON.stringify(a) !== JSON.stringify(b);
        },
    },
    methods: {
        async fetchGrupList() {
            try {
                const res = await fetch('/api/v1/system-auth/grup-fitur');
                const data = await res.json();
                this.grupList = data.data || [];
            } catch (e) {
                console.error(e);
            }
        },
        async fetchBinding() {
            if (!this.selectedGrup) return;
            this.loading = true;
            try {
                const res = await fetch(`/api/v1/system-auth/menu-binding/${this.selectedGrup}`);
                const data = await res.json();
                this.allMenus = data.allMenus || [];
                this.boundPages = [...(data.boundPages || [])];
                this.originalBound = [...this.boundPages];
                this.collapsed = {};
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        async saveBinding() {
            this.saving = true;
            try {
                const res = await fetch(`/api/v1/system-auth/menu-binding/${this.selectedGrup}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pages: this.boundPages }),
                });
                const data = await res.json();
                if (!res.ok) {
                    alert(data.message);
                    return;
                }
                this.originalBound = [...this.boundPages];
                alert('✅ Menu binding berhasil disimpan.');
            } catch (e) {
                console.error(e);
                alert('Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        toggleCollapse(header) {
            this.collapsed[header] = !this.collapsed[header];
        },
    },
    mounted() {
        this.fetchGrupList();
    },
};
</script>

<style scoped>
.panel-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.panel-subtitle {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
}
.select-grup {
    min-width: 260px;
    border-radius: 8px;
    font-size: 0.82rem;
    border: 1px solid var(--hcis-slate-200);
}
.search-bar {
    position: relative;
}
.search-bar i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
}
.search-bar input {
    padding-left: 2rem;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
}
.binding-stats {
    display: flex;
    gap: 0.5rem;
}
.stat-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.stat-bound {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.stat-available {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}
.header-group {
    margin-bottom: 0.75rem;
}
.header-group-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.5rem;
    border-bottom: 2px solid var(--hcis-slate-100);
    cursor: pointer;
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--hcis-slate-700);
    user-select: none;
}
.header-group-title:hover {
    color: var(--hcis-indigo-500);
}
.header-count {
    margin-left: auto;
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    font-weight: 500;
}
.menu-check-list {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.25rem 0;
}
.menu-check-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    border: 1px solid transparent;
}
.menu-check-item:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
}
.menu-bound {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200) !important;
}
.menu-check-info {
    flex: 1;
    min-width: 0;
}
.menu-check-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.menu-check-page {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    font-family: monospace;
}
.save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
}
.change-note {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-amber-600);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-save {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 1rem;
    border: none;
    border-radius: 8px;
    background: var(--hcis-indigo-500);
    color: #fff;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-save:hover {
    background: var(--hcis-indigo-600);
}
.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--hcis-slate-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}
.form-check-input {
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.form-check-input:checked {
    background-color: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
}
</style>
