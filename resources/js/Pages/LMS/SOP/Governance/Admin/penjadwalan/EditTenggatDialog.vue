<template>
    <HcisDialog
        v-model="buka"
        title="Ubah Detail Penjadwalan & Tenggat"
        size="md"
        sentiment="purple"
        icon="pencil-square"
        :loading="menyimpan"
    >
        <div class="sop-scope etd py-1">
            <!-- Alert Info Lead -->
            <HcisContextBanner
                variant="surface"
                sentiment="purple"
                icon="info-circle-fill"
                title="Cakupan Batch Distribusi"
                :subtitle="`Perubahan ini berlaku untuk seluruh ${batch?.Total_Target ?? 0} karyawan pada batch ini. Keterlambatan dihitung otomatis tanpa mengubah progres membaca karyawan.`"
                class="mb-3"
            />

            <!-- Label Batch -->
            <div class="mb-3">
                <label class="sop-label mb-1">Label Penjadwalan</label>
                <input
                    v-model="f.nama_batch"
                    type="text"
                    class="sop-field form-control"
                    maxlength="150"
                    :placeholder="labelOtomatis"
                />
                <p class="etd-hint">
                    Kosongkan untuk memakai label otomatis: <em>{{ labelOtomatis }}</em>
                </p>
            </div>

            <!-- Keterangan -->
            <div class="mb-3">
                <label class="sop-label mb-1">Keterangan <span class="etd-opt">(opsional)</span></label>
                <textarea
                    v-model="f.keterangan"
                    class="sop-field form-control"
                    rows="2"
                    maxlength="500"
                    placeholder="Konteks: alasan distribusi ini dibuat atau instruksi khusus..."
                ></textarea>
            </div>

            <hr class="etd-sep" />

            <!-- Pilihan Tenggat -->
            <div class="mb-3">
                <label class="sop-label mb-1.5">Kebijakan Tenggat Waktu</label>
                <div class="btn-group w-100 mb-2.5">
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="f.tipe_tenggat === 'SAMPAI_DICABUT' ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="f.tipe_tenggat = 'SAMPAI_DICABUT'"
                    >
                        <i class="bi bi-infinity me-1"></i> Tanpa Tenggat
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="f.tipe_tenggat === 'DEADLINE' ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="f.tipe_tenggat = 'DEADLINE'"
                    >
                        <i class="bi bi-calendar-event me-1"></i> Berdeadline
                    </button>
                </div>

                <template v-if="f.tipe_tenggat === 'DEADLINE'">
                    <input
                        v-model="f.tanggal_deadline"
                        type="date"
                        class="sop-field form-control"
                        :min="hariIni"
                        style="max-width: 240px"
                    />
                    <p v-if="lampau" class="etd-warn">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Tenggat berada di masa lalu.
                    </p>
                </template>
                <p v-else class="etd-hint">
                    Deadline dikosongkan — tidak ada keterlambatan yang dihitung untuk batch ini.
                </p>
            </div>

            <hr class="etd-sep" />

            <!-- Pengingat / Reminder -->
            <div>
                <HcisCheckbox
                    v-model="f.flag_reminder"
                    label="Ingatkan berkala lewat notifikasi"
                    size="sm"
                />
                <div v-if="f.flag_reminder" class="mt-2.5 ms-4">
                    <label class="sop-label mb-1">Irama Pengingat (Tiap Berapa Hari)</label>
                    <div class="d-flex align-items-center gap-2">
                        <input
                            v-model.number="f.irama_reminder_hari"
                            type="number"
                            min="1"
                            max="365"
                            class="sop-field form-control"
                            style="max-width: 120px"
                        />
                        <span class="small text-muted">hari sekali</span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                <HcisButton
                    variant="secondary"
                    size="sm"
                    label="Batal"
                    :disabled="menyimpan"
                    @click="buka = false"
                />
                <HcisButton
                    variant="primary"
                    sentiment="purple"
                    size="sm"
                    leading-icon="check-lg"
                    label="Simpan Perubahan"
                    :disabled="menyimpan || !sah"
                    :loading="menyimpan"
                    @click="simpan"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { HcisButton, HcisContextBanner, HcisDialog, HcisCheckbox } from '@/components/ui';

const API = '/api/v1/sop/admin/penjadwalan';

export default {
    name: 'EditTenggatDialog',
    components: {
        HcisButton,
        HcisContextBanner,
        HcisDialog,
        HcisCheckbox,
    },
    props: {
        modelValue: { type: Boolean, default: false },
        batch: { type: Object, default: null },
    },
    emits: ['update:modelValue', 'tersimpan'],
    data() {
        return {
            f: {
                nama_batch: '',
                keterangan: '',
                tipe_tenggat: 'SAMPAI_DICABUT',
                tanggal_deadline: '',
                flag_reminder: false,
                irama_reminder_hari: 3,
            },
            menyimpan: false,
        };
    },
    computed: {
        buka: {
            get() {
                return this.modelValue;
            },
            set(v) {
                this.$emit('update:modelValue', v);
            },
        },
        hariIni() {
            return new Date().toISOString().slice(0, 10);
        },
        lampau() {
            return (
                this.f.tipe_tenggat === 'DEADLINE' && this.f.tanggal_deadline && this.f.tanggal_deadline < this.hariIni
            );
        },
        sah() {
            return this.f.tipe_tenggat !== 'DEADLINE' || (!!this.f.tanggal_deadline && !this.lampau);
        },
        /** Label yang akan dipakai server bila kolomnya dikosongkan. */
        labelOtomatis() {
            const b = this.batch || {};
            if (!b.Tanggal_Assign) return 'Angkatan -';
            const t = new Date(b.Tanggal_Assign).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
            if (b.Sumber === 'MANDIRI') return 'Pendaftaran mandiri \u00b7 ' + t;

            return 'Angkatan ' + t + (b.Total_Target > 0 ? ' \u00b7 ' + b.Total_Target + ' orang' : '');
        },
    },
    watch: {
        modelValue(v) {
            if (v) this.isiDariBatch();
        },
    },
    methods: {
        isiDariBatch() {
            const b = this.batch || {};
            this.f = {
                nama_batch: b.Nama_Batch || '',
                keterangan: b.Keterangan || '',
                tipe_tenggat: b.Tipe_Tenggat || 'SAMPAI_DICABUT',
                tanggal_deadline: b.Tanggal_Deadline ? String(b.Tanggal_Deadline).slice(0, 10) : '',
                flag_reminder: b.Flag_Reminder === 'Y',
                irama_reminder_hari: b.Irama_Reminder_Hari || 3,
            };
        },
        reset() {
            this.menyimpan = false;
        },
        async simpan() {
            this.menyimpan = true;
            try {
                const r = await axios.post(`${API}/batch/${this.batch.Id_SOP_Penjadwalan}/detail`, {
                    nama_batch: this.f.nama_batch || null,
                    keterangan: this.f.keterangan || null,
                    tipe_tenggat: this.f.tipe_tenggat,
                    tanggal_deadline: this.f.tipe_tenggat === 'DEADLINE' ? this.f.tanggal_deadline : null,
                    flag_reminder: this.f.flag_reminder,
                    irama_reminder_hari: this.f.flag_reminder ? this.f.irama_reminder_hari : null,
                });
                if (r.data.success) {
                    ElMessage.success(r.data.message);
                    this.buka = false;
                    this.$emit('tersimpan');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan');
            } finally {
                this.menyimpan = false;
            }
        },
    },
};
</script>

<style scoped>
.sop-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.etd-hint {
    font-size: 0.74rem;
    color: var(--hcis-slate-400);
    margin-top: 0.35rem;
    margin-bottom: 0;
}
.etd-opt {
    font-weight: 500;
    color: var(--hcis-slate-400);
    text-transform: none;
    letter-spacing: 0;
}
.etd-warn {
    font-size: 0.75rem;
    color: var(--hcis-red-600);
    font-weight: 600;
    margin-top: 0.35rem;
    margin-bottom: 0;
}
.etd-sep {
    border: 0;
    border-top: 1px solid var(--hcis-slate-100);
    margin: 1rem 0;
}
.wajib-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
    cursor: pointer;
    margin: 0;
}
.wajib-toggle input {
    display: none;
}
.wt-box {
    width: 22px;
    height: 22px;
    border-radius: 7px;
    border: 2px solid var(--hcis-slate-300);
    display: grid;
    place-items: center;
    color: transparent;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.wajib-toggle input:checked + .wt-box {
    background: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    color: #fff;
}
</style>
