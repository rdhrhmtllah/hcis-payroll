<template>
    <div class="capability-binder-panel">
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-toggles me-2"></i>Menu Capability Binder</h6>
            <select v-model="selectedMenu" class="form-select form-select-sm select-menu" @change="fetchCapabilities">
                <option :value="null" disabled>Pilih Menu / Halaman...</option>
                <option v-for="m in menuList" :key="m.Jenis_page" :value="m.Jenis_page">
                    {{ m.Nama_Menu }} ({{ m.Jenis_page }})
                </option>
            </select>
        </div>

        <div v-if="!selectedMenu" class="empty-state">
            <i class="bi bi-toggles2"></i>
            <p>Pilih menu di atas untuk mengelola capability (aksi yang tersedia).</p>
        </div>

        <div v-else-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div v-else>
            <!-- Selected menu info -->
            <div class="menu-info mb-3" v-if="menuInfo">
                <div class="menu-info-name">{{ menuInfo.Nama_Menu }}</div>
                <div class="menu-info-page">{{ menuInfo.Jenis_page }}</div>
                <div class="menu-info-header text-muted">Header: {{ menuInfo.Header_Label || '-' }}</div>
            </div>

            <!-- Capability Matrix -->
            <div class="cap-table-wrapper">
                <table class="cap-table">
                    <thead>
                        <tr>
                            <th style="width: 40%">Aksi</th>
                            <th style="width: 30%; text-align: center">Tersedia</th>
                            <th style="width: 30%; text-align: center">Default Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cap in capabilities" :key="cap.id_aksi" :class="{ 'row-enabled': cap.enabled }">
                            <td>
                                <div class="aksi-name">
                                    <i :class="getAksiIcon(cap.nama_aksi)"></i>
                                    {{ cap.nama_aksi }}
                                </div>
                            </td>
                            <td class="text-center">
                                <div
                                    class="toggle-switch"
                                    @click="
                                        cap.enabled = !cap.enabled;
                                        if (!cap.enabled) cap.is_active_default = 'T';
                                    "
                                >
                                    <i
                                        :class="
                                            cap.enabled ? 'bi bi-toggle-on text-primary' : 'bi bi-toggle-off text-muted'
                                        "
                                        style="font-size: 1.3rem; cursor: pointer"
                                    ></i>
                                </div>
                            </td>
                            <td class="text-center">
                                <div
                                    class="toggle-switch"
                                    @click="
                                        cap.enabled &&
                                        (cap.is_active_default = cap.is_active_default === 'Y' ? 'T' : 'Y')
                                    "
                                >
                                    <i
                                        :class="
                                            cap.is_active_default === 'Y'
                                                ? 'bi bi-check-circle-fill text-success'
                                                : 'bi bi-circle text-muted'
                                        "
                                        :style="{
                                            fontSize: '1.1rem',
                                            cursor: cap.enabled ? 'pointer' : 'not-allowed',
                                            opacity: cap.enabled ? 1 : 0.3,
                                        }"
                                    ></i>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="cap-legend mt-2">
                <small class="text-muted">
                    <strong>Tersedia</strong> = aksi ini bisa digunakan untuk menu ini. <strong>Default Aktif</strong> =
                    otomatis dicentang saat pertama kali distribute akses ke user.
                </small>
            </div>

            <!-- Save -->
            <div class="save-bar mt-3" v-if="hasChanges">
                <span class="change-note"><i class="bi bi-pencil-fill"></i> Ada perubahan</span>
                <button class="btn-save" @click="saveCapabilities" :disabled="saving">
                    <span v-if="saving"><i class="bi bi-arrow-repeat spin"></i> Menyimpan...</span>
                    <span v-else><i class="bi bi-check-lg me-1"></i>Simpan Capabilities</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CapabilityBinderPanel',
    data() {
        return {
            menuList: [],
            selectedMenu: null,
            loading: false,
            saving: false,
            menuInfo: null,
            capabilities: [],
            originalSnapshot: '',
        };
    },
    computed: {
        hasChanges() {
            return JSON.stringify(this.capabilities) !== this.originalSnapshot;
        },
    },
    methods: {
        async fetchMenuList() {
            try {
                const res = await fetch('/api/v1/system-auth/distribution/filters');
                const data = await res.json();
                this.menuList = data.menus || [];
            } catch (e) {
                console.error(e);
            }
        },
        async fetchCapabilities() {
            if (!this.selectedMenu) return;
            this.loading = true;
            try {
                const res = await fetch(`/api/v1/system-auth/menu-capabilities/${this.selectedMenu}`);
                const data = await res.json();
                this.menuInfo = data.menu;
                this.capabilities = data.capabilities || [];
                this.originalSnapshot = JSON.stringify(this.capabilities);
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        async saveCapabilities() {
            this.saving = true;
            try {
                const res = await fetch(`/api/v1/system-auth/menu-capabilities/${this.selectedMenu}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ capabilities: this.capabilities }),
                });
                const data = await res.json();
                if (!res.ok) {
                    alert(data.message);
                    return;
                }
                this.originalSnapshot = JSON.stringify(this.capabilities);
                alert('✅ Capabilities berhasil disimpan.');
            } catch (e) {
                console.error(e);
                alert('Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        getAksiIcon(nama) {
            const map = {
                CREATE: 'bi bi-plus-circle',
                VIEW: 'bi bi-eye',
                EDIT: 'bi bi-pencil-square',
                DELETE: 'bi bi-trash',
                APPROVE: 'bi bi-check2-square',
                PRINT: 'bi bi-printer',
                SUMMARY: 'bi bi-bar-chart',
            };
            return map[nama] || 'bi bi-gear';
        },
    },
    mounted() {
        this.fetchMenuList();
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
.select-menu {
    min-width: 300px;
    border-radius: 8px;
    font-size: 0.82rem;
}
.menu-info {
    padding: 0.75rem 1rem;
    background: var(--hcis-slate-50);
    border-radius: 10px;
    border: 1px solid var(--hcis-slate-200);
}
.menu-info-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--hcis-slate-800);
}
.menu-info-page {
    font-size: 0.75rem;
    color: var(--hcis-indigo-500);
    font-family: monospace;
}
.menu-info-header {
    font-size: 0.72rem;
    margin-top: 0.2rem;
}
.cap-table-wrapper {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    overflow: hidden;
}
.cap-table {
    width: 100%;
    border-collapse: collapse;
}
.cap-table thead th {
    padding: 0.65rem 1rem;
    background: var(--hcis-slate-50);
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    border-bottom: 1px solid var(--hcis-slate-200);
}
.cap-table tbody tr {
    border-bottom: 1px solid var(--hcis-slate-100);
    transition: background 0.15s;
}
.cap-table tbody tr:last-child {
    border-bottom: none;
}
.cap-table tbody tr:hover {
    background: var(--hcis-slate-50);
}
.cap-table tbody td {
    padding: 0.6rem 1rem;
}
.row-enabled {
    background: #fafbff;
}
.aksi-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--hcis-slate-700);
}
.aksi-name i {
    color: var(--hcis-indigo-500);
}
.toggle-switch {
    display: flex;
    justify-content: center;
}
.cap-legend {
    font-size: 0.72rem;
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
</style>
