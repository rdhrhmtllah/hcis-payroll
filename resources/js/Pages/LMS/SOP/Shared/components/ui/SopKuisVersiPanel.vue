<template>
    <div class="kv-panel">
        <div class="kv-head">
            <div>
                <div class="sop-eyebrow">Kendali versi</div>
                <div class="kv-title">
                    Versi Ujian <span v-if="aktifVersi" class="kv-badge">v{{ aktifVersi }} aktif</span>
                </div>
            </div>
        </div>

        <div v-if="loading" class="kv-loading-box py-2">
            <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold small">
                <span class="spinner-border spinner-border-sm"></span>
                <span>Memuat riwayat ujian kuis...</span>
            </div>
            <div class="sop-skel" style="height: 52px; border-radius: 12px"></div>
        </div>
        <template v-else>
            <SopVersiTimeline v-if="mapped.length" :versions="mapped" :show-baca="false" />
            <div v-else class="kv-empty">
                <i class="bi bi-layers"></i> Belum ada versi. Pilih ujian lalu simpan untuk membuat v1.
            </div>
        </template>

        <p class="kv-hint">
            <i class="bi bi-info-circle"></i> Versi baru otomatis terbentuk saat Anda mengganti ujian item ini —
            penerima yang sudah lulus akan diminta ujian ulang.
        </p>
    </div>
</template>

<script>
import axios from 'axios';
import SopVersiTimeline from './SopVersiTimeline.vue';

const APIB = '/api/v1/sop/admin/berkas';

/**
 * SopKuisVersiPanel — riwayat versi READ-ONLY untuk item KUIS (Builder).
 * Versi KUIS dibuat otomatis (v1 saat simpan, v2 saat ganti ujian) — panel ini
 * hanya MENAMPILKAN (reuse SopVersiTimeline). Data dari endpoint versi item.
 */
export default {
    name: 'SopKuisVersiPanel',
    components: { SopVersiTimeline },
    props: {
        idItem: { type: String, required: true },
        namaUjian: { type: String, default: '' },
    },
    data() {
        return { versiList: [], loading: false };
    },
    computed: {
        aktifVersi() {
            const a = (this.versiList || []).find((v) => v.Status === 'PUBLISHED');
            return a ? a.Versi_Ke : null;
        },
        mapped() {
            return (this.versiList || []).map((v) => ({
                id: v.Id_SOP_Item_Versi,
                versi: v.Versi_Ke,
                aktif: v.Status === 'PUBLISHED',
                changelog: v.Ringkasan_Perubahan,
                tanggal: v.Tanggal_Publish || v.Tanggal_Berlaku,
                kontenNama: v.Status === 'PUBLISHED' ? this.namaUjian || 'Ujian aktif' : null,
                kontenTipe: 'kuis',
                canView: false,
            }));
        },
    },
    watch: {
        idItem: {
            immediate: true,
            handler() {
                this.load();
            },
        },
    },
    methods: {
        async load() {
            if (!this.idItem) return;
            this.loading = true;
            try {
                const r = await axios.get(`${APIB}/item/${this.idItem}/versi`);
                this.versiList = r.data.success ? r.data.result : [];
            } catch (e) {
                this.versiList = [];
            } finally {
                this.loading = false;
            }
        },
        reload() {
            this.load();
        },
    },
};
</script>

<style scoped>
.kv-panel {
    border-top: 1px dashed var(--border, var(--hcis-slate-200));
    padding-top: 1rem;
    margin-top: 1rem;
}
.kv-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.7rem;
}
.kv-title {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.kv-badge {
    font-size: 0.64rem;
    font-weight: 800;
    background: rgba(16, 185, 129, 0.14);
    color: var(--hcis-emerald-700);
    border-radius: 999px;
    padding: 0.14rem 0.5rem;
}
.kv-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--hcis-slate-400);
    background: var(--surface-muted, var(--hcis-slate-50));
    border-radius: 10px;
    padding: 0.7rem 0.85rem;
}
.kv-empty .bi {
    font-size: 1.1rem;
}
.kv-hint {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    margin: 0.6rem 0 0;
    display: flex;
    gap: 0.4rem;
}
.kv-hint .bi {
    color: var(--hcis-indigo-500);
}
</style>
