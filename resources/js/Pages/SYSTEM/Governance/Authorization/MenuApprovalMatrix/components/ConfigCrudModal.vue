<template>
    <Modal
        :show="show"
        :title="isEdit ? 'Edit Approval Config' : 'Tambah Approval Config'"
        subtitle="Config otomatis terikat ke grup aktif pada URL."
        size="medium"
        :showFooter="false"
        :skeleton="false"
        @close="$emit('close')"
    >
        <template #body>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label-sm">Kode Config <span class="text-danger">*</span></label>
                    <el-input v-model="form.Kode_Config" placeholder="cth: LEADER_SHIFT" maxlength="100" />
                </div>
                <div class="col-12">
                    <label class="form-label-sm">Nama Config</label>
                    <el-input v-model="form.Nama_Config" placeholder="Nama deskriptif config" maxlength="200" />
                </div>
                <div class="col-12">
                    <label class="form-label-sm">Deskripsi</label>
                    <el-input v-model="form.Deskripsi" type="textarea" :rows="2" maxlength="500" />
                </div>
                <div class="col-md-8">
                    <label class="form-label-sm">Grup Fitur</label>
                    <div class="locked-group">
                        <i class="bi bi-lock-fill"></i>
                        <span>{{ activeGrup?.Kode_Grup_Fitur }} - {{ activeGrup?.Nama_Grup_Fitur }}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label-sm">Status</label>
                    <div><el-switch v-model="flagAktif" active-text="Aktif" inactive-text="Nonaktif" /></div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <el-button @click="$emit('close')" :disabled="saving">Batal</el-button>
                <el-button type="primary" :loading="saving" :disabled="!form.Kode_Config" @click="save">
                    <i class="bi bi-check-lg me-1"></i>Simpan
                </el-button>
            </div>
        </template>
    </Modal>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import Modal from '@/components/legacy/modalV2-noir.vue';

const emptyForm = () => ({
    Kode_Config: '',
    Nama_Config: '',
    Deskripsi: '',
    Flag_Aktif: 'Y',
});

export default {
    name: 'ConfigCrudModal',
    components: { Modal },
    props: {
        show: { type: Boolean, default: false },
        config: { type: Object, default: null },
        grupId: { type: Number, required: true },
        activeGrup: { type: Object, required: true },
    },
    emits: ['close', 'saved'],
    data() {
        return { saving: false, form: emptyForm() };
    },
    computed: {
        isEdit() {
            return !!this.config?.Id;
        },
        flagAktif: {
            get() {
                return this.form.Flag_Aktif === 'Y';
            },
            set(value) {
                this.form.Flag_Aktif = value ? 'Y' : 'T';
            },
        },
    },
    watch: {
        show(value) {
            if (!value) return;
            this.form = this.config
                ? {
                      Kode_Config: this.config.Kode_Config || '',
                      Nama_Config: this.config.Nama_Config || '',
                      Deskripsi: this.config.Deskripsi || '',
                      Flag_Aktif: this.config.Flag_Aktif || 'Y',
                  }
                : emptyForm();
        },
    },
    methods: {
        async save() {
            this.saving = true;
            try {
                const payload = {
                    ...this.form,
                    grup_id: this.grupId,
                    Id_Grup_Fitur: this.grupId,
                    Nama_Config: this.form.Nama_Config || null,
                    Deskripsi: this.form.Deskripsi || null,
                };
                const { data } = this.isEdit
                    ? await axios.put(`/api/v1/menu-approval-matrix/configs/${this.config.Id}`, payload)
                    : await axios.post('/api/v1/menu-approval-matrix/configs', payload);

                ElMessage.success(data.message || 'Approval config disimpan.');
                this.$emit('saved');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan approval config.');
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>

<style scoped>
.form-label-sm {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    margin-bottom: 0.25rem;
    display: block;
}
.locked-group {
    min-height: 40px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 8px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
    font-size: 0.85rem;
    font-weight: 600;
}
</style>
