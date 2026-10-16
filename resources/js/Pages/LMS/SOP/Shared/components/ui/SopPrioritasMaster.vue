<template>
    <div class="stitch-card stitch-pad">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <div class="sop-eyebrow">Master data</div>
                <h6 class="sop-h">Tingkat Prioritas Dokumen</h6>
                <p class="small text-muted mb-0">
                    Urutan menentukan tingkat urgensi —
                    <strong>semakin kecil angka urutan, semakin tinggi prioritasnya di papan risiko</strong>.
                </p>
            </div>
        </div>

        <!-- Warning jika DDL belum aktif -->
        <div v-if="!siap && !loading" class="pri-belum">
            <i class="bi bi-database-exclamation"></i>
            <div>
                <strong>Master prioritas belum aktif di database ini.</strong>
                <p class="m-0 small">
                    Sampai skrip <code>35_master_prioritas.sql</code> dijalankan, tiga tingkat bawaan (Sangat Penting,
                    Tinggi, Normal) tetap berlaku dan tak bisa diubah dari sini.
                </p>
            </div>
        </div>

        <!-- Skeleton Loading -->
        <div v-else-if="loading" class="row g-3">
            <div v-for="n in 3" :key="n" class="col-12">
                <div class="sop-skel" style="height: 68px; border-radius: 14px"></div>
            </div>
        </div>

        <!-- Empty State -->
        <EmptyState
            v-else-if="!rows.length"
            icon="bi-sliders"
            title="Belum ada tingkat prioritas"
            subtitle="Klik tombol di atas untuk menambahkan tingkat prioritas baru."
        />

        <!-- Priority List Cards -->
        <div v-else class="pri-card-list">
            <div
                v-for="p in rows"
                :key="p.Id_SOP_Prioritas"
                class="pri-card-item"
                :class="{ 'is-inactive': p.Flag_Aktif !== 'Y' }"
            >
                <div class="pri-rank-badge" :title="'Urutan Prioritas: #' + p.Urutan">
                    <span class="pri-rank-num">#{{ p.Urutan }}</span>
                </div>

                <div class="pri-info flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <StatusPill :warna="p.Warna" :text="p.Nama" />
                        <code class="pri-code-badge"><i class="bi bi-code-slash me-1"></i>{{ p.Kode }}</code>
                    </div>
                </div>

                <div class="pri-meta-group d-flex align-items-center gap-3">
                    <span class="status-indicator-tag" :class="p.Flag_Aktif === 'Y' ? 'is-active' : 'is-inactive'">
                        <i
                            class="bi"
                            :class="p.Flag_Aktif === 'Y' ? 'bi-check-circle-fill' : 'bi-dash-circle-fill'"
                        ></i>
                        {{ p.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}
                    </span>

                    <span class="pri-usage-badge" :class="{ empty: !p.Jumlah_Paket }">
                        <i class="bi bi-box-seam me-1"></i> {{ p.Jumlah_Paket || 0 }} Paket
                    </span>
                </div>

                <div class="pri-actions-btns">
                    <button class="stitch-icon-btn" title="Sunting Prioritas" @click="buka(p)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="stitch-icon-btn danger" title="Hapus Prioritas" @click="mintaHapus(p)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Ultra-Premium Modal Tambah / Edit Prioritas -->
        <SopModal
            :show="modal"
            :title="form.Id_SOP_Prioritas ? 'Sunting Tingkat Prioritas' : 'Tambah Tingkat Prioritas'"
            subtitle="Konfigurasi tingkat urgensi &amp; identitas prioritas"
            :icon="form.Id_SOP_Prioritas ? 'bi-pencil-square' : 'bi-plus-circle-fill'"
            size="md"
            @close="modal = false"
        >
            <!-- Live Preview Banner Box -->
            <div class="priority-live-preview mb-3">
                <div class="plp-header d-flex align-items-center justify-content-between mb-2">
                    <span class="plp-label"><i class="bi bi-eye-fill me-1"></i> Pratinjau Tampilan Live</span>
                    <span class="plp-rank">Urutan: #{{ form.Urutan || 0 }}</span>
                </div>
                <div class="plp-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <StatusPill :warna="form.Warna" :text="form.Nama || 'Nama Prioritas'" />
                        <code class="pri-code-badge"
                            ><i class="bi bi-code-slash me-1"></i>{{ form.Kode || 'KODE_PRIORITAS' }}</code
                        >
                    </div>
                    <span class="status-indicator-tag" :class="form.Flag_Aktif ? 'is-active' : 'is-inactive'">
                        <i class="bi" :class="form.Flag_Aktif ? 'bi-check-circle-fill' : 'bi-dash-circle-fill'"></i>
                        {{ form.Flag_Aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label class="sop-label">Nama Tampilan *</label>
                    <input v-model="form.Nama" type="text" class="sop-field" placeholder="Contoh: Sangat Penting" />
                </div>

                <div class="col-12 col-md-5">
                    <label class="sop-label">Kode Prioritas *</label>
                    <input
                        v-model="form.Kode"
                        type="text"
                        class="sop-field"
                        placeholder="Contoh: SANGAT_TINGGI"
                        :disabled="kodeTerkunci"
                        @input="form.Kode = form.Kode.toUpperCase()"
                    />
                    <p v-if="kodeTerkunci" class="field-hint text-warning mt-1">
                        <i class="bi bi-lock-fill"></i> Terkunci — {{ dipakaiSekarang }} paket menggunakan kode ini.
                    </p>
                    <p v-else class="field-hint mt-1">Gunakan huruf kapital, angka &amp; garis bawah.</p>
                </div>

                <div class="col-12 col-md-5">
                    <label class="sop-label">Urutan Prioritas *</label>
                    <input v-model.number="form.Urutan" type="number" min="0" max="9998" class="sop-field" />
                    <p class="field-hint mt-1">Angka lebih kecil = lebih mendesak.</p>
                </div>

                <div class="col-12 col-md-7">
                    <label class="sop-label">Warna</label>
                    <!-- Swatch tinggal jalan pintas, bukan lagi daftar tertutup:
                         warnanya kini hex bebas, jadi apa pun di luar lima ini
                         tetap bisa dipakai lewat pemilih atau kotak di bawah. -->
                    <div class="color-swatches-grid">
                        <button
                            v-for="sw in colorSwatches"
                            :key="sw.value"
                            type="button"
                            class="color-swatch-item"
                            :class="{ active: samaWarna(form.Warna, sw.value) }"
                            :title="sw.label"
                            @click="form.Warna = sw.value"
                        >
                            <span class="swatch-dot" :style="{ background: sw.value }"></span>
                            <span class="swatch-lbl">{{ sw.shortLabel }}</span>
                            <i v-if="samaWarna(form.Warna, sw.value)" class="bi bi-check2 swatch-check"></i>
                        </button>
                    </div>

                    <div class="pri-warna-bebas mt-2">
                        <input v-model="form.Warna" type="color" class="pri-warna-pick" title="Pilih warna bebas" />
                        <input
                            v-model="form.Warna"
                            type="text"
                            class="sop-field"
                            placeholder="#EF4444"
                            maxlength="7"
                            @blur="form.Warna = rapikanHex(form.Warna)"
                        />
                    </div>
                    <p class="field-hint mt-1">Hex 6 digit. Boleh warna apa pun di luar pilihan di atas.</p>
                </div>

                <div class="col-12">
                    <div class="status-toggle-card p-2.5 rounded-3 border bg-slate-50">
                        <HcisCheckbox
                            v-model="form.Flag_Aktif"
                            size="md"
                        >
                            <div>
                                <strong class="d-block text-dark small">Status Aktif</strong>
                                <span class="text-muted extra-small">
                                    Prioritas ini akan muncul di seluruh pemilih prioritas pada Builder &amp; Penjadwalan.
                                </span>
                            </div>
                        </HcisCheckbox>
                    </div>
                </div>
            </div>

            <template #footer>
                <button class="stitch-btn secondary" @click="modal = false">Batal</button>
                <button class="stitch-btn primary" :disabled="sibuk" @click="simpan">
                    <span v-if="sibuk" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-check-lg me-1"></i>
                    {{ sibuk ? 'Menyimpan…' : 'Simpan' }}
                </button>
            </template>
        </SopModal>

        <!-- Standardized Delete Confirmation Modal -->
        <SopKonfirmasi
            :show="konf.buka"
            judul="Hapus Tingkat Prioritas"
            :subjudul="konf.nama"
            pesan="Tingkat ini akan dihapus dari daftar pilihan. Paket yang sudah memakainya tidak terpengaruh, namun prioritas yang sedang dipakai paket tidak dapat dihapus."
            teks-setuju="Hapus Prioritas"
            ikon-tombol="bi-trash"
            ragam="danger"
            :sibuk="sibuk"
            @update:show="konf.buka = $event"
            @setuju="hapus"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { HcisCheckbox } from '@/components/ui';
import SopModal from './SopModal.vue';
import SopKonfirmasi from './SopKonfirmasi.vue';
import StatusPill from './StatusPill.vue';
import EmptyState from './EmptyState.vue';

const API = '/api/v1/sop/admin/prioritas';

/**
 * Master tingkat prioritas paket dokumen (Redesigned ultra-premium UI/UX).
 */
export default {
    name: 'SopPrioritasMaster',
    components: { HcisCheckbox, SopModal, SopKonfirmasi, StatusPill, EmptyState },
    /* Dideklarasikan supaya tak jatuh jadi atribut pada elemen akar. Angkanya
       dipakai lencana di kepala halaman — satu sumber, bukan permintaan kedua
       yang bisa menyimpang dari daftar di bawahnya. */
    emits: ['berubah'],
    data() {
        return {
            rows: [],
            loading: true,
            siap: true,
            modal: false,
            sibuk: false,
            form: this.formKosong(),
            dipakaiSekarang: 0,
            konf: { buka: false, id: null, nama: '' },
            colorSwatches: [
                { value: '#EF4444', label: 'Merah (Kritis)', shortLabel: 'Merah' },
                { value: '#F59E0B', label: 'Kuning (Tinggi)', shortLabel: 'Kuning' },
                { value: '#0EA5E9', label: 'Biru (Normal)', shortLabel: 'Biru' },
                { value: '#10B981', label: 'Hijau (Rendah)', shortLabel: 'Hijau' },
                { value: '#94A3B8', label: 'Abu-abu (Netral)', shortLabel: 'Abu' },
            ],
        };
    },
    computed: {
        kodeTerkunci() {
            return !!this.form.Id_SOP_Prioritas && this.dipakaiSekarang > 0;
        },
    },
    created() {
        this.muat();
    },
    methods: {
        /** Banding hex tanpa peduli huruf besar/kecil — '#ef4444' == '#EF4444'. */
        samaWarna(a, b) {
            return String(a || '').toUpperCase() === String(b || '').toUpperCase();
        },
        /**
         * Rapikan ketikan manusia jadi hex 6 digit berhuruf kapital.
         *
         * Yang ditolerir: tanpa '#', huruf kecil, dan bentuk 3 digit (#f00).
         * Yang tak terbaca DIKEMBALIKAN APA ADANYA supaya validasi server yang
         * menolaknya dengan pesan jelas — bukan diam-diam diganti warna lain
         * yang tak pernah dipilih siapa pun.
         */
        rapikanHex(v) {
            let x = String(v || '')
                .trim()
                .replace(/^#*/, '');
            if (/^[0-9a-f]{3}$/i.test(x)) x = x[0] + x[0] + x[1] + x[1] + x[2] + x[2];

            return /^[0-9a-f]{6}$/i.test(x) ? '#' + x.toUpperCase() : v;
        },
        formKosong() {
            return {
                Id_SOP_Prioritas: null,
                Kode: '',
                Nama: '',
                Urutan: 100,
                Warna: '#94A3B8',
                Flag_Aktif: true,
            };
        },
        async muat() {
            this.loading = true;
            try {
                const r = await axios.get(`${API}/current`);
                this.rows = r.data?.result || [];
                this.siap = true;
                this.$emit('berubah', this.rows.length);
            } catch (e) {
                this.siap = e.response?.status !== 409;
                this.$emit('berubah', 0);
                if (this.siap) ElMessage.error(e.response?.data?.message || 'Gagal memuat tingkat prioritas');
            } finally {
                this.loading = false;
            }
        },
        buka(p = null) {
            this.form = p
                ? {
                      Id_SOP_Prioritas: p.Id_SOP_Prioritas,
                      Kode: p.Kode,
                      Nama: p.Nama,
                      Urutan: Number(p.Urutan),
                      Warna: p.Warna || '#94A3B8',
                      Flag_Aktif: p.Flag_Aktif === 'Y',
                  }
                : this.formKosong();
            this.dipakaiSekarang = p ? Number(p.Jumlah_Paket || 0) : 0;
            this.modal = true;
        },
        async simpan() {
            if (!this.form.Nama.trim()) {
                ElMessage.warning('Nama prioritas wajib diisi');
                return;
            }
            if (!this.form.Kode.trim()) {
                ElMessage.warning('Kode prioritas wajib diisi');
                return;
            }

            this.sibuk = true;
            try {
                const body = {
                    Kode: this.form.Kode.trim(),
                    Nama: this.form.Nama.trim(),
                    Urutan: this.form.Urutan,
                    Warna: this.form.Warna,
                    Flag_Aktif: this.form.Flag_Aktif,
                };
                if (this.form.Id_SOP_Prioritas) {
                    await axios.put(`${API}/update/${this.form.Id_SOP_Prioritas}`, body);
                } else {
                    await axios.post(`${API}/store`, body);
                }
                this.modal = false;
                await this.muat();
                ElMessage.success('Tingkat prioritas disimpan');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan');
            } finally {
                this.sibuk = false;
            }
        },
        mintaHapus(p) {
            this.konf = { buka: true, id: p.Id_SOP_Prioritas, nama: `${p.Nama} (${p.Kode})` };
        },
        async hapus() {
            this.sibuk = true;
            try {
                await axios.delete(`${API}/delete/${this.konf.id}`);
                this.konf.buka = false;
                await this.muat();
                ElMessage.success('Tingkat prioritas dihapus');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menghapus');
            } finally {
                this.sibuk = false;
            }
        },
    },
};
</script>

<style scoped>
.pri-belum {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 1rem 1.15rem;
    border-radius: 14px;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}
.pri-belum i {
    font-size: 1.15rem;
    flex-shrink: 0;
}

/* Priority Card List */
.pri-card-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.pri-card-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.1rem;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 16px;
    background: var(--hcis-white);
    transition: all 0.18s ease;
}
.pri-card-item:hover {
    border-color: var(--hcis-slate-300);
    background: #fdfdfe;
    box-shadow: 0 4px 14px -4px rgba(0, 0, 0, 0.04);
}
.pri-card-item.is-inactive {
    opacity: 0.6;
    background: var(--hcis-slate-50);
}

.pri-rank-badge {
    min-width: 42px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 0.9rem;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    flex-shrink: 0;
}

.pri-code-badge {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    padding: 0.2rem 0.55rem;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
}

.status-indicator-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
}
.status-indicator-tag.is-active {
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
}
.status-indicator-tag.is-inactive {
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
}

.pri-usage-badge {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    padding: 0.25rem 0.65rem;
    border-radius: 10px;
}
.pri-usage-badge.empty {
    color: var(--hcis-slate-400);
}

.pri-actions-btns {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* Modal Live Preview Banner */
.priority-live-preview {
    padding: 0.85rem 1.1rem;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border: 1px solid var(--hcis-slate-200);
}
.plp-label {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}
.plp-rank {
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}

/* Color Swatches Grid */
.pri-warna-bebas {
    display: flex;
    gap: 0.45rem;
    align-items: center;
}
.pri-warna-pick {
    width: 46px;
    height: 38px;
    padding: 2px;
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    flex-shrink: 0;
}
.color-swatches-grid {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.color-swatch-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.7rem;
    border-radius: 10px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    transition: all 0.15s ease;
}
.color-swatch-item:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
}
.color-swatch-item.active {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-slate-800);
    box-shadow: 0 2px 8px -2px rgba(99, 102, 241, 0.25);
}
.swatch-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.swatch-check {
    color: var(--hcis-indigo-500);
    font-size: 0.9rem;
}

/* Status Toggle Box */
.status-toggle-card {
    padding: 0.8rem 1rem;
    border-radius: 12px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}
.pri-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}
.extra-small {
    font-size: 0.74rem;
}

@media (max-width: 575px) {
    .pri-card-item {
        flex-wrap: wrap;
    }
    .pri-meta-group {
        width: 100%;
        justify-content: space-between;
    }
    .pri-actions-btns {
        margin-left: auto;
    }
}
</style>
