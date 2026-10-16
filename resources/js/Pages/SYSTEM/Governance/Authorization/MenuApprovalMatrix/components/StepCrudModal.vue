<template>
    <Modal
        :show="show"
        :title="isEdit ? 'Edit Step Approval' : 'Tambah Step Approval'"
        subtitle="Satu step = satu level dalam flow approval config ini."
        size="medium"
        :showFooter="false"
        :skeleton="false"
        @close="$emit('close')"
    >
        <template #body>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label-sm">Urutan (Approval_Order) <span class="text-danger">*</span></label>
                    <el-input-number v-model="form.Approval_Order" :min="1" :max="20" style="width: 100%" />
                </div>
                <div class="col-md-8">
                    <label class="form-label-sm">Mode Approver</label>
                    <el-radio-group v-model="mode">
                        <el-radio label="jenis">Jenis Approval (dinamis)</el-radio>
                        <el-radio label="custom">Custom Approver (hardcode)</el-radio>
                    </el-radio-group>
                </div>

                <div class="col-12" v-if="mode === 'jenis'">
                    <label class="form-label-sm">Jenis Approval <span class="text-danger">*</span></label>
                    <el-select v-model="form.Id_Jenis_Approval" filterable clearable placeholder="Pilih jenis approval..." style="width: 100%">
                        <el-option
                            v-for="j in jenisOptions"
                            :key="j.Id_Jenis_Approval"
                            :label="`${j.Nama_Approval} (${j.Source_Type}${j.Source_Reference ? ': ' + j.Source_Reference : ''})`"
                            :value="j.Id_Jenis_Approval"
                        />
                    </el-select>
                </div>

                <div class="col-12" v-else>
                    <label class="form-label-sm">Custom Approver <span class="text-danger">*</span></label>
                    <el-select
                        v-model="form.Custom_Approver_Kode"
                        filterable
                        remote
                        clearable
                        placeholder="Cari karyawan..."
                        :remote-method="searchKaryawan"
                        :loading="karyawanLoading"
                        style="width: 100%"
                    >
                        <el-option
                            v-for="k in karyawanOptions"
                            :key="k.Kode_Karyawan"
                            :label="`${k.Nama} (${k.Kode_Karyawan})`"
                            :value="k.Kode_Karyawan"
                        />
                    </el-select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-sm">Limit Level Approver</label>
                    <el-input-number v-model="form.Order_Approver_Limit" :min="1" :max="10" style="width: 100%" placeholder="Semua" />
                    <div class="hint">Kosongkan untuk ambil semua level dari sumber.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label-sm">Mandatory</label>
                    <div><el-switch v-model="isMandatory" active-text="Ya" inactive-text="Tidak" /></div>
                    <div class="hint">Jika ditolak dan mandatory, flow berhenti.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label-sm">Stop Jika Ditolak</label>
                    <div><el-switch v-model="stopIfRejected" active-text="Ya" inactive-text="Tidak" /></div>
                </div>
                <div class="col-md-4" v-if="isEdit">
                    <label class="form-label-sm">Status</label>
                    <div><el-switch v-model="flagAktif" active-text="Aktif" inactive-text="Nonaktif" /></div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <el-button @click="$emit('close')" :disabled="saving">Batal</el-button>
                <el-button type="primary" :loading="saving" :disabled="!isValid" @click="save">
                    <i class="bi bi-check-lg me-1"></i>Simpan
                </el-button>
            </div>
        </template>
    </Modal>
</template>

<script>
import axios from "axios";
import { ElMessage } from "element-plus";
import Modal from "@/components/legacy/modalV2-noir.vue";

const emptyForm = () => ({
    Approval_Order: 1,
    Id_Jenis_Approval: null,
    Custom_Approver_Kode: null,
    Order_Approver_Limit: null,
    Is_Mandatory: "Y",
    Stop_If_Rejected: "Y",
    Flag_Aktif: "Y",
});

export default {
    name: "StepCrudModal",
    components: { Modal },
    props: {
        show: { type: Boolean, default: false },
        configId: { type: Number, required: true },
        grupId: { type: Number, required: true },
        step: { type: Object, default: null },
    },
    emits: ["close", "saved"],
    data() {
        return {
            saving: false,
            mode: "jenis",
            form: emptyForm(),
            jenisOptions: [],
            karyawanOptions: [],
            karyawanLoading: false,
        };
    },
    computed: {
        isEdit() {
            return !!this.step?.Id;
        },
        isValid() {
            if (this.mode === "jenis") return !!this.form.Id_Jenis_Approval;
            return !!this.form.Custom_Approver_Kode;
        },
        isMandatory: {
            get() {
                return this.form.Is_Mandatory === "Y";
            },
            set(value) {
                this.form.Is_Mandatory = value ? "Y" : "T";
            },
        },
        stopIfRejected: {
            get() {
                return this.form.Stop_If_Rejected === "Y";
            },
            set(value) {
                this.form.Stop_If_Rejected = value ? "Y" : "T";
            },
        },
        flagAktif: {
            get() {
                return this.form.Flag_Aktif === "Y";
            },
            set(value) {
                this.form.Flag_Aktif = value ? "Y" : "T";
            },
        },
    },
    watch: {
        show(value) {
            if (!value) return;
            this.fetchJenisOptions();
            if (this.step) {
                this.form = {
                    Approval_Order: this.step.Approval_Order || 1,
                    Id_Jenis_Approval: this.step.Id_Jenis_Approval || null,
                    Custom_Approver_Kode: this.step.Custom_Approver_Kode || null,
                    Order_Approver_Limit: this.step.Order_Approver_Limit || null,
                    Is_Mandatory: this.step.Is_Mandatory || "Y",
                    Stop_If_Rejected: this.step.Stop_If_Rejected || "Y",
                    Flag_Aktif: this.step.Flag_Aktif || "Y",
                };
                this.mode = this.step.Custom_Approver_Kode ? "custom" : "jenis";
            } else {
                this.form = emptyForm();
                this.mode = "jenis";
            }
        },
        mode(value) {
            if (value === "jenis") this.form.Custom_Approver_Kode = null;
            else this.form.Id_Jenis_Approval = null;
        },
    },
    methods: {
        async fetchJenisOptions() {
            try {
                const { data } = await axios.get("/api/v1/menu-approval-matrix/jenis-approval");
                this.jenisOptions = (data.items || []).filter((j) => j.Flag_Aktif === "Y");
            } catch {
                this.jenisOptions = [];
            }
        },
        async searchKaryawan(query) {
            this.karyawanLoading = true;
            try {
                const { data } = await axios.get("/api/v1/menu-approval-matrix/karyawan/search", {
                    params: { grup_id: this.grupId, purpose: 'approver', search: query || undefined },
                });
                this.karyawanOptions = data.items || [];
            } catch {
                this.karyawanOptions = [];
            } finally {
                this.karyawanLoading = false;
            }
        },
        async save() {
            this.saving = true;
            try {
                const payload = { ...this.form, grup_id: this.grupId };

                const { data } = this.isEdit
                    ? await axios.put(`/api/v1/menu-approval-matrix/config-details/${this.step.Id}`, payload)
                    : await axios.post(`/api/v1/menu-approval-matrix/configs/${this.configId}/details`, payload);

                ElMessage.success(data.message || "Step approval disimpan.");
                this.$emit("saved");
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal menyimpan step approval.");
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
.hint {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    margin-top: 0.25rem;
}
</style>
