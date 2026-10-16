<template>
    <SopModal
        :show="show"
        title="Export Data SOP"
        subtitle="Pilih jenis, format, lalu antrekan"
        icon="bi-download"
        size="lg"
        @close="$emit('close')"
    >
        <div class="sop-eyebrow mb-2">Jenis data</div>
        <div class="jenis-grid mb-4">
            <button
                v-for="j in jenisList"
                :key="j.key"
                class="jenis"
                :class="{ on: jenis === j.key }"
                @click="pilihJenis(j.key)"
            >
                <i class="bi" :class="j.icon"></i>
                <div class="min-w-0">
                    <div class="j-nama">{{ j.label }}</div>
                    <div class="j-ket">{{ j.ket }}</div>
                </div>
            </button>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="sop-label">Nama berkas (opsional)</label>
                <input
                    v-model="nama"
                    type="text"
                    maxlength="120"
                    class="sop-field"
                    placeholder="mis. Kepatuhan Produksi Q3"
                />
            </div>
            <div class="col-12 col-md-6">
                <label class="sop-label">Format</label>
                <div class="fmt-row">
                    <button
                        v-for="f in formats"
                        :key="f.key"
                        class="fmt"
                        :class="{ on: format === f.key }"
                        @click="format = f.key"
                    >
                        <i class="bi" :class="f.icon"></i> {{ f.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Estimasi datang dari satu COUNT di server. Yang disimpan ke job adalah
             SARINGAN, bukan daftar id — jadi tak ada batas 25.000 yang bisa ditabrak
             dan hasilnya dijamin sama dengan yang tampil di layar. -->
        <div class="est mt-4">
            <div>
                <div class="sop-eyebrow">Perkiraan cakupan</div>
                <div class="est-val">
                    <span v-if="memuatEstimasi" class="spinner-border spinner-border-sm text-primary"></span>
                    <template v-else
                        ><span class="tnum">{{ estimasi }}</span> baris</template
                    >
                </div>
            </div>
            <p class="est-ket mb-0">
                Mengikuti saringan yang sedang aktif di layar. Berkas dibuat di latar belakang — pantau di
                <strong>Pusat Aktivitas</strong>.
            </p>
        </div>

        <template #footer>
            <button class="stitch-btn secondary" @click="$emit('close')">Batal</button>
            <button class="stitch-btn primary" :disabled="mengirim" @click="kirim">
                <span v-if="mengirim" class="spinner-border spinner-border-sm"></span>
                <i v-else class="bi bi-download"></i>
                Export
            </button>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import SopModal from '../../../Shared/components/ui/SopModal.vue';
import { useActivityCenter } from '../../../../../../composables/useActivityCenter.js';

const API = '/api/v1/sop/admin/monitoring';

/**
 * Modal export SOP — berbasis SARINGAN, bukan pemilihan id.
 *
 * Monitoring LMS menyimpan hingga 25.000 id terenkripsi per permintaan: 25.000
 * panggilan Crypt, payload multi-MB, dan mode gagal "terlalu banyak, persempit
 * saringan" yang dilihat pengguna. Di sini yang dititipkan ke job cuma saringannya;
 * job memanggil service yang sama dengan yang dipakai layar. Satu COUNT
 * menggantikan seluruh ritual itu, dan hasilnya tak mungkin menyimpang dari layar.
 */
export default {
    name: 'SopExportModal',
    components: { SopModal },
    props: {
        show: { type: Boolean, default: false },
        jenisAwal: { type: String, default: 'karyawan' },
        filters: { type: Object, default: () => ({}) },
    },
    emits: ['close'],
    data() {
        return {
            jenis: 'karyawan',
            format: 'xlsx',
            nama: '',
            estimasi: 0,
            memuatEstimasi: false,
            mengirim: false,
            jenisList: [
                {
                    key: 'karyawan',
                    label: 'Kepatuhan per Karyawan',
                    icon: 'bi-people-fill',
                    ket: 'Satu baris per karyawan + ringkasan SOP-nya',
                },
                {
                    key: 'coverage',
                    label: 'Coverage per Paket',
                    icon: 'bi-collection-fill',
                    ket: 'Target, selesai, terlambat, coverage tiap SOP',
                },
                {
                    key: 'dokumen',
                    label: 'Register Kontrol Dokumen',
                    icon: 'bi-shield-check',
                    ket: 'Masa berlaku, review, pengesahan, dampak',
                },
                {
                    key: 'risiko',
                    label: 'Papan Risiko',
                    icon: 'bi-exclamation-triangle-fill',
                    ket: 'SOP paling berisiko, terurut & bisa ditelusuri',
                },
            ],
            formats: [
                { key: 'xlsx', label: 'Excel', icon: 'bi-file-earmark-excel' },
                { key: 'csv', label: 'CSV', icon: 'bi-filetype-csv' },
                { key: 'pdf', label: 'PDF', icon: 'bi-file-earmark-pdf' },
            ],
        };
    },
    watch: {
        show(v) {
            if (!v) return;
            this.jenis = this.jenisAwal || 'karyawan';
            this.ambilEstimasi();
        },
    },
    methods: {
        pilihJenis(k) {
            this.jenis = k;
            this.ambilEstimasi();
        },
        async ambilEstimasi() {
            this.memuatEstimasi = true;
            try {
                const r = await axios.get(`${API}/export/estimasi`, { params: { jenis: this.jenis, ...this.filters } });
                this.estimasi = r.data.success ? r.data.result.total : 0;
            } catch (e) {
                this.estimasi = 0;
            } finally {
                this.memuatEstimasi = false;
            }
        },
        async kirim() {
            this.mengirim = true;
            try {
                const r = await axios.post(`${API}/export/request`, {
                    jenis: this.jenis,
                    format: this.format,
                    nama: this.nama || null,
                    filters: this.filters,
                });
                ElMessage.success(r.data.message || 'Export diproses.');
                useActivityCenter().notifyNewTask();
                this.$emit('close');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memulai export');
            } finally {
                this.mengirim = false;
            }
        },
    },
};
</script>

<style scoped>
.jenis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 0.6rem;
}
.jenis {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    text-align: left;
    padding: 0.8rem 0.9rem;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    background: #fff;
    cursor: pointer;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease;
}
.jenis:hover {
    border-color: rgba(99, 102, 241, 0.4);
}
.jenis.on {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.jenis i {
    font-size: 1.15rem;
    color: #6366f1;
    margin-top: 2px;
}
.j-nama {
    font-weight: 800;
    font-size: 0.85rem;
    color: #1e293b;
}
.j-ket {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 2px;
    line-height: 1.4;
}

.fmt-row {
    display: flex;
    gap: 0.4rem;
}
.fmt {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.5rem 0.6rem;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 10px;
    background: #fff;
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
}
.fmt.on {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.08);
    color: #4f46e5;
}

.est {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.9rem 1rem;
    border-radius: 14px;
    background: var(--surface-muted, #f8fafc);
    border: 1px solid var(--border, #dbe1ea);
}
.est-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: #4f46e5;
    line-height: 1.1;
}
.est-ket {
    font-size: 0.75rem;
    color: #64748b;
    flex: 1;
    min-width: 200px;
}
</style>
