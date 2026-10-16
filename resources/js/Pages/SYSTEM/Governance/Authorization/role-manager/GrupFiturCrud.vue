<template>
    <div class="grup-fitur-crud">
        <div class="panel-toolbar">
            <h6 class="panel-subtitle"><i class="bi bi-collection me-2"></i>Master Grup Fitur</h6>
            <button class="btn-add" @click="showForm = true; editItem = null; formData = { Kode_Grup_Fitur: '', Nama_Grup_Fitur: '' }">
                <i class="bi bi-plus-lg me-1"></i> Tambah Grup
            </button>
        </div>

        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <p class="text-muted small mt-2">Memuat data...</p>
        </div>

        <div v-else class="grup-list">
            <div v-for="g in items" :key="g.Id_Grup_Fitur" class="grup-item">
                <div class="grup-item-main">
                    <div class="grup-kode">{{ g.Kode_Grup_Fitur }}</div>
                    <div class="grup-nama">{{ g.Nama_Grup_Fitur }}</div>
                    <div class="grup-stat">
                        <span class="stat-chip"><i class="bi bi-link-45deg"></i> {{ g.menu_count }} menu</span>
                    </div>
                </div>
                <div class="grup-actions">
                    <button class="btn-icon-action" @click="openEdit(g)" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-icon-action btn-icon-danger" @click="confirmDelete(g)" title="Hapus">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <div v-if="items.length === 0" class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>Belum ada Grup Fitur.</p>
            </div>
        </div>

        <!-- Form Modal -->
        <Modal
            :show="showForm"
            :title="editItem ? 'Edit Grup Fitur' : 'Tambah Grup Fitur'"
            size="small"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            :confirmText="editItem ? 'Simpan' : 'Buat'"
            :loading="saving"
            @close="showForm = false"
            @confirm="saveItem"
        >
            <template #body>
                <div class="form-group mb-3">
                    <label class="form-label-sm">Kode Grup Fitur</label>
                    <input v-model="formData.Kode_Grup_Fitur" class="form-control form-control-sm" placeholder="Contoh: HCIS" />
                </div>
                <div class="form-group">
                    <label class="form-label-sm">Nama Grup Fitur</label>
                    <input v-model="formData.Nama_Grup_Fitur" class="form-control form-control-sm" placeholder="Contoh: Human Capital Information System" />
                </div>
            </template>
        </Modal>

        <!-- Delete Confirm -->
        <Modal
            :show="showDelete"
            title="Hapus Grup Fitur"
            size="small"
            :showFooter="true" :showCancel="true" :showConfirm="true"
            confirmText="Hapus" :loading="deleting"
            @close="showDelete = false"
            @confirm="executeDelete"
        >
            <template #body>
                <div class="text-center py-2">
                    <div class="delete-name">{{ deleteItem?.Kode_Grup_Fitur }}</div>
                    <small class="text-muted">{{ deleteItem?.Nama_Grup_Fitur }}</small>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import Modal from "@/components/legacy/modalV2-noir.vue";

export default {
    name: "GrupFiturCrud",
    components: { Modal },
    data() {
        return {
            items: [],
            loading: false,
            showForm: false,
            saving: false,
            editItem: null,
            formData: { Kode_Grup_Fitur: '', Nama_Grup_Fitur: '' },
            showDelete: false,
            deleting: false,
            deleteItem: null,
        };
    },
    methods: {
        async fetchData() {
            this.loading = true;
            try {
                const res = await fetch('/api/v1/system-auth/grup-fitur');
                const data = await res.json();
                this.items = data.data || [];
            } catch (e) { console.error(e); }
            finally { this.loading = false; }
        },
        openEdit(item) {
            this.editItem = item;
            this.formData = {
                Kode_Grup_Fitur: item.Kode_Grup_Fitur,
                Nama_Grup_Fitur: item.Nama_Grup_Fitur,
            };
            this.showForm = true;
        },
        async saveItem() {
            this.saving = true;
            try {
                const url = this.editItem
                    ? `/api/v1/system-auth/grup-fitur/${this.editItem.Id_Grup_Fitur}`
                    : '/api/v1/system-auth/grup-fitur';
                const res = await fetch(url, {
                    method: this.editItem ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.formData),
                });
                const data = await res.json();
                if (!res.ok) { alert(data.message); return; }
                this.showForm = false;
                this.fetchData();
            } catch (e) { console.error(e); alert('Gagal menyimpan.'); }
            finally { this.saving = false; }
        },
        confirmDelete(item) {
            this.deleteItem = item;
            this.showDelete = true;
        },
        async executeDelete() {
            this.deleting = true;
            try {
                const res = await fetch(`/api/v1/system-auth/grup-fitur/${this.deleteItem.Id_Grup_Fitur}`, {
                    method: 'DELETE',
                    headers: {},
                });
                const data = await res.json();
                if (!res.ok) { alert(data.message); return; }
                this.showDelete = false;
                this.fetchData();
            } catch (e) { console.error(e); alert('Gagal menghapus.'); }
            finally { this.deleting = false; }
        },
    },
    mounted() { this.fetchData(); },
};
</script>

<style scoped>
.panel-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.panel-subtitle { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--hcis-slate-700); display: flex; align-items: center; }
.btn-add { display: inline-flex; align-items: center; padding: 0.45rem 1rem; border: none; border-radius: 10px; background: var(--hcis-indigo-500); color: #fff; font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
.btn-add:hover { background: var(--hcis-indigo-600); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
.grup-list { display: flex; flex-direction: column; gap: 0.5rem; }
.grup-item { display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; border: 1px solid var(--hcis-slate-200); border-radius: 10px; background: #fff; transition: all 0.2s; }
.grup-item:hover { border-color: var(--hcis-indigo-200); background: #fafbff; }
.grup-item-main { flex: 1; }
.grup-kode { font-weight: 700; font-size: 0.95rem; color: var(--hcis-indigo-500); letter-spacing: 0.3px; }
.grup-nama { font-size: 0.82rem; color: var(--hcis-slate-600); margin-top: 0.1rem; }
.grup-stat { margin-top: 0.3rem; }
.stat-chip { font-size: 0.68rem; padding: 0.1rem 0.45rem; border-radius: 6px; background: var(--hcis-slate-100); color: var(--hcis-slate-500); display: inline-flex; align-items: center; gap: 0.25rem; }
.grup-actions { display: flex; gap: 0.3rem; }
.btn-icon-action { width: 30px; height: 30px; border: 1px solid var(--hcis-slate-200); border-radius: 8px; background: #fff; color: var(--hcis-slate-500); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; transition: all 0.2s; }
.btn-icon-action:hover { background: var(--hcis-indigo-500); color: #fff; border-color: var(--hcis-indigo-500); }
.btn-icon-danger:hover { background: var(--hcis-red-500); border-color: var(--hcis-red-500); }
.empty-state { text-align: center; padding: 2rem; color: var(--hcis-slate-400); }
.empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }
.form-label-sm { font-size: 0.78rem; font-weight: 600; color: var(--hcis-slate-600); margin-bottom: 0.25rem; display: block; }
.delete-name { font-weight: 700; font-size: 1.1rem; color: var(--hcis-red-500); }
</style>
