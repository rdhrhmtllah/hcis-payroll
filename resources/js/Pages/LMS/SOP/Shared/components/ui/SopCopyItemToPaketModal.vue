<template>
    <SopModal :show="show" title="Salin Item ke Paket Lain" icon="bi-send-plus" size="md" @close="$emit('close')">
        <div class="cip-src">
            <span class="cip-src-lbl">Menyalin:</span>
            <span class="cip-src-judul text-truncate">{{ sourceItem?.Judul_Item || 'Item' }}</span>
        </div>

        <!-- Langkah 1: pilih paket tujuan -->
        <div v-if="step === 'paket'" class="cip-step">
            <div class="sop-search mb-3 position-relative">
                <i class="bi bi-search"></i>
                <input v-model="q" type="search" class="sop-field" placeholder="Cari paket draft…" autofocus />
            </div>

            <div v-if="loadingPaket" class="cip-loading">
                <span class="spinner-border spinner-border-sm"></span> Memuat paket draft…
            </div>

            <div v-else-if="!paketList.length" class="cip-empty">
                <i class="bi bi-journal-x"></i>
                <template v-if="q">
                    <span>Tidak ada paket draft yang cocok dengan "{{ q }}".</span>
                </template>
                <template v-else>
                    <span>Belum ada paket berstatus Draft lain untuk disalin.</span>
                    <span class="cip-empty-hint"
                        >Buat paket draft baru dulu, atau pakai "Duplikat" untuk menyalin di paket ini.</span
                    >
                </template>
            </div>

            <div v-else class="cip-paket-list">
                <button
                    v-for="p in paketList"
                    :key="p.Id_SOP_Paket"
                    type="button"
                    class="cip-paket"
                    @click="pilihPaket(p)"
                >
                    <span class="cip-paket-info">
                        <span class="cip-paket-nama text-truncate">{{ p.Nama_Paket }}</span>
                        <span class="cip-paket-badge">Draft</span>
                    </span>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Langkah 2: pilih section tujuan -->
        <div v-else class="cip-step">
            <button type="button" class="cip-back" @click="kembaliKePaket">
                <i class="bi bi-arrow-left"></i> Ganti paket
            </button>

            <div class="cip-target">
                <i class="bi bi-folder2-open"></i>
                <span class="text-truncate">{{ selectedPaket?.Nama_Paket }}</span>
            </div>

            <div v-if="loadingSection" class="cip-loading">
                <span class="spinner-border spinner-border-sm"></span> Memuat section…
            </div>

            <div v-else-if="!sectionList.length" class="cip-empty">
                <i class="bi bi-inbox"></i>
                <span>Paket ini belum punya section.</span>
                <span class="cip-empty-hint">Buka paket tujuan dan tambah section dulu sebelum menyalin ke sini.</span>
            </div>

            <div v-else class="cip-section-list">
                <label v-for="s in sectionList" :key="s.Id_SOP_Section" class="cip-section">
                    <input type="radio" name="cip-section" :value="s.Id_SOP_Section" v-model="selectedSection" />
                    <span class="text-truncate">{{ s.Nama_Section }}</span>
                </label>
            </div>
        </div>

        <template #footer>
            <button class="stitch-btn secondary" :disabled="copying" @click="$emit('close')">Batal</button>
            <button
                v-if="step === 'section'"
                class="stitch-btn primary"
                :disabled="!selectedSection || copying"
                @click="konfirmasiSalin"
            >
                <span v-if="copying" class="spinner-border spinner-border-sm"></span>
                <i v-else class="bi bi-send-plus"></i> Salin
            </button>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import SopModal from './SopModal.vue';

const API = '/api/v1/sop/admin/builder';

/**
 * SopCopyItemToPaketModal — dua langkah (pilih paket draft lain → pilih
 * section-nya) lalu POST item/{id}/copy-to-paket. Item asal & progres
 * karyawan di paket asal tidak pernah disentuh — lihat SopItemService::copyToPaket().
 */
export default {
    name: 'SopCopyItemToPaketModal',
    components: { SopModal },
    props: {
        show: { type: Boolean, default: false },
        sourceItem: { type: Object, default: null }, // { Id_SOP_Paket_Item, Id_SOP_Paket, Judul_Item }
    },
    emits: ['close', 'copied'],
    data() {
        return {
            step: 'paket', // 'paket' | 'section'
            q: '',
            _qTimer: null,
            loadingPaket: false,
            paketList: [],
            selectedPaket: null,
            loadingSection: false,
            sectionList: [],
            selectedSection: null,
            copying: false,
        };
    },
    watch: {
        show(v) {
            if (v) this.reset();
        },
        q() {
            clearTimeout(this._qTimer);
            this._qTimer = setTimeout(() => this.cariPaket(), 300);
        },
    },
    methods: {
        reset() {
            this.step = 'paket';
            this.q = '';
            this.paketList = [];
            this.selectedPaket = null;
            this.sectionList = [];
            this.selectedSection = null;
            this.copying = false;
            this.cariPaket();
        },
        async cariPaket() {
            this.loadingPaket = true;
            try {
                const r = await axios.get(`${API}/paket/current`, {
                    params: { status: 'DRAFT', q: this.q || undefined, limit: 20, page: 1 },
                });
                const rows = r.data?.result?.data || r.data?.result || [];
                this.paketList = rows.filter((p) => p.Id_SOP_Paket !== this.sourceItem?.Id_SOP_Paket);
            } catch (e) {
                // 404 dari server = "tak ada hasil", bukan error — daftar kosong saja.
                if (e.response?.status !== 404) {
                    ElMessage.error(e.response?.data?.message || 'Gagal memuat daftar paket');
                }
                this.paketList = [];
            } finally {
                this.loadingPaket = false;
            }
        },
        async pilihPaket(p) {
            this.selectedPaket = p;
            this.selectedSection = null;
            this.step = 'section';
            this.loadingSection = true;
            try {
                const r = await axios.get(`${API}/paket/${p.Id_SOP_Paket}/section-options`);
                this.sectionList = r.data?.result?.sections || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat section paket tujuan');
                this.sectionList = [];
            } finally {
                this.loadingSection = false;
            }
        },
        kembaliKePaket() {
            this.step = 'paket';
            this.selectedPaket = null;
            this.selectedSection = null;
        },
        async konfirmasiSalin() {
            if (!this.sourceItem || !this.selectedPaket || !this.selectedSection || this.copying) return;
            this.copying = true;
            try {
                const r = await axios.post(`${API}/item/${this.sourceItem.Id_SOP_Paket_Item}/copy-to-paket`, {
                    Id_SOP_Paket_Tujuan: this.selectedPaket.Id_SOP_Paket,
                    Id_SOP_Section_Tujuan: this.selectedSection,
                });
                this.$emit('copied', {
                    Id_SOP_Paket_Item: r.data.result.Id_SOP_Paket_Item,
                    Id_SOP_Paket_Tujuan: r.data.result.Id_SOP_Paket_Tujuan,
                    Nama_Paket_Tujuan: this.selectedPaket.Nama_Paket,
                });
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyalin item');
            } finally {
                this.copying = false;
            }
        },
    },
};
</script>

<style scoped>
.cip-src {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    padding: 0.55rem 0.75rem;
    background: var(--hcis-slate-50);
    border: 1px solid #eef2f7;
    border-radius: 10px;
    margin-bottom: 1rem;
    font-size: 0.82rem;
    min-width: 0;
}
.cip-src-lbl {
    color: var(--hcis-slate-500);
    font-weight: 700;
    flex-shrink: 0;
}
.cip-src-judul {
    color: var(--hcis-slate-800);
    font-weight: 700;
    min-width: 0;
}
.cip-loading,
.cip-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    padding: 2rem 1rem;
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
    text-align: center;
}
.cip-empty .bi {
    font-size: 1.6rem;
    color: var(--hcis-slate-300);
}
.cip-empty-hint {
    font-size: 0.76rem;
    color: var(--hcis-slate-400);
}
.cip-paket-list,
.cip-section-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 320px;
    overflow-y: auto;
}
.cip-paket {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.7rem 0.85rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    min-width: 0;
}
.cip-paket:hover {
    border-color: var(--hcis-indigo-500);
    background: #f5f6ff;
}
.cip-paket-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    flex: 1;
}
.cip-paket-nama {
    font-weight: 700;
    color: var(--hcis-slate-800);
    font-size: 0.88rem;
    min-width: 0;
}
.cip-paket-badge {
    flex-shrink: 0;
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-amber-800);
    background: rgba(245, 158, 11, 0.14);
    border-radius: 999px;
    padding: 0.15rem 0.5rem;
}
.cip-back {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 0;
    background: none;
    color: var(--hcis-indigo-500);
    font-weight: 700;
    font-size: 0.82rem;
    padding: 0;
    margin-bottom: 0.75rem;
    cursor: pointer;
}
.cip-target {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.8rem;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 10px;
    color: var(--hcis-indigo-700);
    font-weight: 700;
    font-size: 0.85rem;
    margin-bottom: 1rem;
    min-width: 0;
}
.cip-section {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
}
.cip-section:has(input:checked) {
    border-color: var(--hcis-indigo-500);
    background: #f5f6ff;
}
</style>
