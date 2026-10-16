<template>
    <HcisDialog
        v-model="buka"
        title="Tambah Karyawan ke Batch Distribusi"
        size="lg"
        sentiment="purple"
        icon="person-plus-fill"
        :loading="menyimpan"
    >
        <div class="sop-scope tkd py-1">
            <HcisContextBanner
                variant="surface"
                sentiment="purple"
                icon="people-fill"
                title="Penambahan Alokasi Batch"
                :subtitle="`Menambah alokasi ke paket ${batch?.Nama_Paket || 'Dokumen'}. Tenggat dan pengingat akan mengikuti ketentuan batch ini. Karyawan yang sudah memiliki penugasan paket ini akan otomatis dilewati.`"
                class="mb-3"
            />

            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">Pilih karyawan target penerima dokumen:</span>
                <HcisBadge variant="brand" size="xs">
                    {{ kodes.length }} karyawan dipilih
                </HcisBadge>
            </div>

            <TargetPilihOrang
                v-if="buka"
                v-model="kodes"
                :id-paket="batch?.Id_SOP_Paket || ''"
                :opsi="opsiFilter"
            />
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
                    leading-icon="person-plus"
                    :label="kodes.length ? `Tambahkan (${kodes.length}) Karyawan` : 'Tambahkan Karyawan'"
                    :disabled="menyimpan || !kodes.length"
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
import { HcisBadge, HcisButton, HcisContextBanner, HcisDialog } from '@/components/ui';
import TargetPilihOrang from './TargetPilihOrang.vue';

const API = '/api/v1/sop/admin/penjadwalan';

export default {
    name: 'TambahKaryawanDialog',
    components: {
        HcisBadge,
        HcisButton,
        HcisContextBanner,
        HcisDialog,
        TargetPilihOrang,
    },
    props: {
        modelValue: { type: Boolean, default: false },
        batch: { type: Object, default: null },
        opsiFilter: { type: Object, default: () => ({}) },
    },
    emits: ['update:modelValue', 'tersimpan'],
    data() {
        return { kodes: [], menyimpan: false };
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
    },
    watch: {
        modelValue(v) {
            if (v) this.reset();
        },
    },
    methods: {
        reset() {
            this.kodes = [];
            this.menyimpan = false;
        },
        async simpan() {
            this.menyimpan = true;
            try {
                const r = await axios.post(`${API}/batch/${this.batch.Id_SOP_Penjadwalan}/karyawan`, {
                    kode_karyawan: this.kodes,
                });
                if (r.data.success) {
                    ElMessage.success(r.data.message);
                    this.buka = false;
                    this.$emit('tersimpan');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menambahkan');
            } finally {
                this.menyimpan = false;
            }
        },
    },
};
</script>
