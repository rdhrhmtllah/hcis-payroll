<template>
    <section class="policy-panel">
        <header class="policy-header">
            <div>
                <small>KEAMANAN DELEGASI</small>
                <h5>Delegasi Menu & Target Scope</h5>
                <p>Atur menu yang boleh didelegasikan dan periksa kesiapan scope setiap role admin.</p>
            </div>
            <el-select v-model="grupId" filterable placeholder="Pilih grup" @change="loadPolicies">
                <el-option v-for="group in groups" :key="group.Id_Grup_Fitur" :value="group.Id_Grup_Fitur" :label="`${group.Kode_Grup_Fitur} · ${group.Nama_Grup_Fitur}`" />
            </el-select>
        </header>

        <div v-if="loading" class="state-card"><i class="bi bi-arrow-repeat spin"></i> Memuat kebijakan...</div>
        <div v-else-if="!ddlReady && grupId" class="state-card state-card--warning">
            <i class="bi bi-database-exclamation"></i>
            Tabel policy belum tersedia. Jalankan DDL manual terlebih dahulu.
        </div>

        <div v-if="grupId && policies.length" class="policy-list">
            <article v-for="item in policies" :key="item.jenis_page" class="policy-row">
                <div class="policy-copy">
                    <strong>{{ item.nama_menu }}</strong>
                    <span>{{ item.header_label || 'Tanpa header' }} · {{ item.jenis_page }}</span>
                </div>
                <el-select v-model="item.mode" class="mode-select">
                    <el-option label="Open" value="OPEN" />
                    <el-option label="Restricted" value="RESTRICTED" />
                    <el-option label="System Only" value="SYSTEM_ONLY" />
                </el-select>
                <el-select
                    v-if="item.mode === 'RESTRICTED'"
                    v-model="item.allowed_role_ids"
                    multiple
                    collapse-tags
                    class="role-select"
                    placeholder="Role yang diizinkan"
                >
                    <el-option v-for="role in roles" :key="role.id" :value="role.id" :label="role.name" />
                </el-select>
                <span v-else class="mode-note">
                    {{ item.mode === 'OPEN' ? 'Semua admin grup dengan hak assign' : 'Hanya System Admin' }}
                </span>
            </article>
            <div class="policy-actions">
                <button class="btn btn-primary" :disabled="saving || !ddlReady" @click="savePolicies">
                    <i class="bi bi-check2-circle"></i> {{ saving ? 'Menyimpan...' : 'Simpan Kebijakan' }}
                </button>
            </div>
        </div>

        <section class="readiness">
            <div class="readiness-title">
                <div><small>TARGET SCOPE READINESS</small><h6>Role Admin yang Perlu Ditinjau</h6></div>
                <button class="btn btn-light btn-sm" @click="loadReadiness"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>
            <div class="readiness-grid">
                <article v-for="item in readiness" :key="item.role_id" class="readiness-card" :class="{ 'readiness-card--ready': item.ready }">
                    <span class="status-dot"></span>
                    <div><strong>{{ item.role_name }}</strong><small>{{ item.role_code }} · Grup {{ item.grup_id }}</small></div>
                    <span>{{ item.ready ? (item.scope_codes || []).join(', ') : 'Scope kosong / tidak didukung' }}</span>
                </article>
            </div>
        </section>
    </section>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';

export default {
    name: 'DelegationScopePanel',
    data: () => ({
        groups: [],
        grupId: null,
        policies: [],
        roles: [],
        readiness: [],
        ddlReady: false,
        loading: false,
        saving: false,
    }),
    async mounted() {
        await Promise.all([this.loadGroups(), this.loadReadiness()]);
    },
    methods: {
        async loadGroups() {
            const { data } = await axios.get('/api/v1/system-auth/grup-fitur');
            this.groups = data.data || [];
            if (!this.grupId && this.groups.length) {
                this.grupId = this.groups[0].Id_Grup_Fitur;
                await this.loadPolicies();
            }
        },
        async loadPolicies() {
            if (!this.grupId) return;
            this.loading = true;
            try {
                const { data } = await axios.get(`/api/v1/system-auth/menu-delegation-policies/${this.grupId}`);
                this.ddlReady = !!data.ddl_ready;
                this.policies = data.items || [];
                this.roles = data.roles || [];
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'Gagal memuat kebijakan delegasi.');
            } finally {
                this.loading = false;
            }
        },
        async loadReadiness() {
            try {
                const { data } = await axios.get('/api/v1/system-auth/target-scope-readiness');
                this.readiness = data.items || [];
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'Gagal memuat target scope readiness.');
            }
        },
        async savePolicies() {
            this.saving = true;
            try {
                await axios.post(`/api/v1/system-auth/menu-delegation-policies/${this.grupId}`, { items: this.policies });
                ElMessage.success('Kebijakan delegasi menu berhasil disimpan.');
                await this.loadPolicies();
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'Gagal menyimpan kebijakan delegasi.');
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>

<style scoped>
.policy-panel{display:grid;gap:1rem}.policy-header,.readiness-title,.policy-row{display:flex;align-items:center;gap:1rem}.policy-header,.readiness{padding:1rem;border:1px solid var(--hcis-slate-200);border-radius:14px;background:#fff}.policy-header{justify-content:space-between}.policy-header h5,.readiness-title h6{margin:.1rem 0;color:#172554}.policy-header p{margin:0;color:var(--hcis-slate-500);font-size:.78rem}.policy-header small,.readiness-title small{color:var(--hcis-indigo-600);font-size:.65rem;font-weight:800;letter-spacing:.08em}.policy-list{overflow:hidden;border:1px solid var(--hcis-slate-200);border-radius:14px;background:#fff}.policy-row{padding:.8rem 1rem;border-bottom:1px solid #eef2f7}.policy-copy{display:grid;min-width:220px;flex:1}.policy-copy span,.readiness-card small{color:var(--hcis-slate-500);font-size:.68rem}.mode-select{width:150px}.role-select{width:min(340px,40vw)}.mode-note{width:min(340px,40vw);color:var(--hcis-slate-500);font-size:.72rem}.policy-actions{display:flex;justify-content:flex-end;padding:1rem}.state-card{padding:1rem;border-radius:12px;background:var(--hcis-indigo-50);color:var(--hcis-indigo-700)}.state-card--warning{background:#fff7ed;color:#c2410c}.readiness-title{justify-content:space-between}.readiness-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.6rem;margin-top:.8rem}.readiness-card{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.6rem;padding:.7rem;border:1px solid #fed7aa;border-radius:10px;background:#fff7ed;font-size:.72rem}.readiness-card div{display:grid}.readiness-card--ready{border-color:var(--hcis-green-200);background:var(--hcis-green-50)}.status-dot{width:8px;height:8px;border-radius:50%;background:#f97316}.readiness-card--ready .status-dot{background:#22c55e}.spin{display:inline-block;animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:768px){.policy-header,.policy-row{align-items:stretch;flex-direction:column}.mode-select,.role-select,.mode-note{width:100%}}
</style>
