<template>
    <div class="tpo">
        <!-- Saringan organisasi -->
        <div class="tpo-filters">
            <div class="flex-grow-1">
                <HcisField
                    id="tpo-cari-orang"
                    v-model="q"
                    type="search"
                    clearable
                    placeholder="Cari nama / kode / no. induk…"
                    size="md"
                    @input="cariDebounce"
                >
                    <template #prefix>
                        <HcisIcon name="search" size="xs" class="text-muted" />
                    </template>
                </HcisField>
            </div>
            <el-select v-model="f.tipe" size="default" class="tpo-sel" @change="muat(1)">
                <el-option v-for="t in opsi.tipe || []" :key="t" :label="t === 'Semua' ? 'Semua tipe' : t" :value="t" />
            </el-select>
        </div>

        <div class="tpo-filters">
            <el-select
                v-model="f.dept"
                multiple
                filterable
                clearable
                collapse-tags
                collapse-tags-tooltip
                :max-collapse-tags="1"
                placeholder="Semua Departemen"
                class="tpo-sel"
                @change="muat(1)"
            >
                <el-option v-for="o in opsi.departemen || []" :key="o" :label="o" :value="o" />
            </el-select>
            <el-select
                v-model="f.divisi"
                multiple
                filterable
                clearable
                collapse-tags
                collapse-tags-tooltip
                :max-collapse-tags="1"
                placeholder="Semua Divisi"
                class="tpo-sel"
                @change="muat(1)"
            >
                <el-option v-for="o in opsi.divisi || []" :key="o" :label="o" :value="o" />
            </el-select>
            <el-select
                v-model="f.lokasi"
                multiple
                filterable
                clearable
                collapse-tags
                collapse-tags-tooltip
                :max-collapse-tags="1"
                placeholder="Semua Lokasi"
                class="tpo-sel"
                @change="muat(1)"
            >
                <el-option v-for="o in opsi.lokasi || []" :key="o" :label="o" :value="o" />
            </el-select>
            <el-select
                v-model="f.level"
                multiple
                filterable
                clearable
                collapse-tags
                collapse-tags-tooltip
                :max-collapse-tags="1"
                placeholder="Semua Level"
                class="tpo-sel"
                @change="muat(1)"
            >
                <el-option v-for="o in opsi.level || []" :key="o" :label="o" :value="o" />
            </el-select>
        </div>

        <!-- Bar pilih -->
        <div class="tpo-bar">
            <HcisCheckbox
                :model-value="semuaHalamanTerpilih"
                :indeterminate="sebagianTerpilih"
                label="Pilih halaman ini"
                size="sm"
                @update:model-value="toggleHalaman"
            />
            <HcisButton
                v-if="total > rows.length"
                variant="ghost"
                size="xs"
                leading-icon="check2-all"
                :label="`Pilih semua ${total} hasil`"
                :disabled="memuatSemua"
                :loading="memuatSemua"
                @click="pilihSemuaTersaring"
            />
            <div class="ms-auto tpo-count">
                <strong class="tnum">{{ modelValue.length }}</strong> terpilih
                <HcisButton
                    v-if="modelValue.length"
                    variant="ghost"
                    size="xs"
                    label="Bersihkan"
                    class="ms-2"
                    @click="bersihkanSemua"
                />
            </div>
        </div>

        <!-- Grid kandidat -->
        <div v-if="loading" class="tpo-grid">
            <div v-for="n in 6" :key="n" class="sop-skel" style="height: 62px; border-radius: 12px"></div>
        </div>
        <HcisEmptyState
            v-else-if="!rows.length"
            title="Tidak Ada Karyawan yang Cocok"
            description="Tidak ada karyawan yang sesuai dengan saringan organisasi yang dipilih."
            icon="person-x"
            variant="dashed"
            sentiment="neutral"
            class="my-3"
        />
        <div v-else class="tpo-grid">
            <button
                v-for="k in rows"
                :key="k.Kode_Karyawan"
                type="button"
                class="tpo-card"
                :class="{ on: terpilih(k.Kode_Karyawan), sudah: k.Sudah_Assign }"
                @click="toggle(k)"
            >
                <span class="avatar">{{ inisial(k.Nama) }}</span>
                <span class="tpo-info">
                    <span class="tpo-nama">{{ k.Nama }}</span>
                    <span class="tpo-meta">{{ k.Kode_Karyawan }} · {{ k.Jabatan || '—' }}</span>
                    <span class="tpo-meta faint">{{ k.Departemen || '—' }} · {{ k.Lokasi || '—' }}</span>
                </span>
                <span class="tpo-flags">
                    <HcisBadge
                        v-if="k.Sudah_Assign"
                        variant="warning"
                        size="xs"
                        title="Sudah ter-assign pada paket ini"
                    >
                        Sudah
                    </HcisBadge>
                    <HcisBadge
                        v-else-if="k.Jumlah_Paket_Aktif > 0"
                        variant="neutral"
                        size="xs"
                        :title="`${k.Jumlah_Paket_Aktif} dokumen aktif`"
                    >
                        {{ k.Jumlah_Paket_Aktif }} dokumen
                    </HcisBadge>
                    <i class="bi" :class="terpilih(k.Kode_Karyawan) ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                </span>
            </button>
        </div>

        <div v-if="totalHalaman > 1" class="sop-pager mt-2">
            <HcisButton
                variant="ghost"
                size="xs"
                leading-icon="chevron-left"
                aria-label="Halaman sebelumnya"
                :disabled="page <= 1"
                @click="muat(page - 1)"
            />
            <span class="small text-muted">Hal {{ page }} / {{ totalHalaman }} · {{ total }} orang</span>
            <HcisButton
                variant="ghost"
                size="xs"
                leading-icon="chevron-right"
                aria-label="Halaman berikutnya"
                :disabled="page >= totalHalaman"
                @click="muat(page + 1)"
            />
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import HcisButton from '../../../../../../components/ui/HcisButton.vue';
import HcisBadge from '../../../../../../components/ui/HcisBadge.vue';
import HcisField from '../../../../../../components/ui/HcisField.vue';
import HcisEmptyState from '../../../../../../components/ui/HcisEmptyState.vue';
import HcisIcon from '../../../../../../components/ui/HcisIcon.vue';
import HcisCheckbox from '../../../../../../components/ui/HcisCheckbox.vue';

const API = '/api/v1/sop/admin/penjadwalan';

/** Sama dengan jepitan limit di optionsKandidat — di atas ini pakai mode Unit. */
const BATAS_PILIH_SEMUA = 100;

/**
 * Mode target 1 — pilih orang satu per satu, dengan saringan organisasi dan
 * kesadaran "sudah ter-assign" terhadap paket yang dipilih.
 *
 * v-model = array Kode_Karyawan (bukan objek): daftar ini dikirim apa adanya ke
 * /assign, dan menyimpan objek penuh membuat "pilih semua tersaring" (yang hanya
 * mengembalikan kode) tak bisa bergabung dengan pilihan manual.
 */
export default {
    name: 'TargetPilihOrang',
    components: {
        HcisButton,
        HcisBadge,
        HcisField,
        HcisEmptyState,
        HcisIcon,
        HcisCheckbox,
    },
    props: {
        modelValue: { type: Array, default: () => [] },
        idPaket: { type: String, default: '' },
        opsi: { type: Object, default: () => ({}) },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            rows: [],
            total: 0,
            page: 1,
            limit: 24,
            loading: false,
            memuatSemua: false,
            q: '',
            cariTimer: null,
            f: { tipe: 'Semua', dept: [], divisi: [], lokasi: [], level: [] },
        };
    },
    computed: {
        totalHalaman() {
            return Math.max(1, Math.ceil(this.total / this.limit));
        },
        kodeHalaman() {
            return this.rows.map((r) => r.Kode_Karyawan);
        },
        semuaHalamanTerpilih() {
            return this.rows.length > 0 && this.kodeHalaman.every((k) => this.modelValue.includes(k));
        },
        sebagianTerpilih() {
            const n = this.kodeHalaman.filter((k) => this.modelValue.includes(k)).length;
            return n > 0 && n < this.rows.length;
        },
    },
    watch: {
        idPaket() {
            this.muat(1);
        },
    },
    mounted() {
        this.muat(1);
    },
    methods: {
        inisial(n) {
            return String(n || '?')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join('')
                .toUpperCase();
        },
        terpilih(k) {
            return this.modelValue.includes(k);
        },
        params() {
            return {
                q: this.q || undefined,
                paket: this.idPaket || undefined,
                tipe: this.f.tipe,
                dept: this.f.dept.length ? this.f.dept : undefined,
                divisi: this.f.divisi.length ? this.f.divisi : undefined,
                lokasi: this.f.lokasi.length ? this.f.lokasi : undefined,
                level: this.f.level.length ? this.f.level : undefined,
            };
        },
        cariDebounce() {
            clearTimeout(this.cariTimer);
            this.cariTimer = setTimeout(() => this.muat(1), 400);
        },
        async muat(page = 1) {
            this.loading = true;
            this.page = page;
            try {
                const r = await axios.get(`${API}/options/kandidat`, {
                    params: { ...this.params(), page, limit: this.limit },
                });
                if (r.data.success) {
                    this.rows = r.data.result || [];
                    this.total = r.data.pagination?.total ?? this.rows.length;
                }
            } catch (e) {
                this.rows = [];
                this.total = 0;
            } finally {
                this.loading = false;
            }
        },
        toggle(k) {
            const kode = k.Kode_Karyawan;
            const baru = this.terpilih(kode) ? this.modelValue.filter((x) => x !== kode) : [...this.modelValue, kode];
            this.$emit('update:modelValue', baru);
        },
        toggleHalaman() {
            const baru = this.semuaHalamanTerpilih
                ? this.modelValue.filter((k) => !this.kodeHalaman.includes(k))
                : Array.from(new Set([...this.modelValue, ...this.kodeHalaman]));
            this.$emit('update:modelValue', baru);
        },
        /**
         * Ambil SELURUH kode hasil saringan (lintas halaman) lewat ringkasan
         * server — halaman berikutnya tak perlu dijelajahi satu per satu.
         */
        async pilihSemuaTersaring() {
            // > 100 hasil: sarankan mode Unit, yang memang dirancang untuk itu
            // (tanpa memindahkan ribuan kode lewat kabel). Diperiksa SEBELUM
            // request — server memang menjepit limit di 100, jadi menariknya
            // dulu hanya membuang satu query yang hasilnya pasti dibuang.
            if (this.total > BATAS_PILIH_SEMUA) {
                ElMessage.warning(
                    `${this.total} orang terlalu banyak untuk dipilih satu per satu — gunakan mode "Pilih Unit".`,
                );
                return;
            }

            this.memuatSemua = true;
            try {
                const r = await axios.get(`${API}/options/kandidat`, {
                    params: { ...this.params(), page: 1, limit: BATAS_PILIH_SEMUA },
                });
                if (!r.data.success) return;

                const semua = (r.data.result || []).map((x) => x.Kode_Karyawan);
                this.$emit('update:modelValue', Array.from(new Set([...this.modelValue, ...semua])));
            } finally {
                this.memuatSemua = false;
            }
        },
    },
};
</script>

<style scoped>
.tpo-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.55rem;
}
.tpo-sel {
    flex: 1 1 160px;
    min-width: 140px;
}
.tpo-bar {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0.5rem 0.1rem;
    border-bottom: 1px solid var(--border-soft, #eef2f7);
    margin-bottom: 0.6rem;
}
.tpo-all {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    cursor: pointer;
    margin: 0;
}
.tpo-count {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
}
.tpo-count strong {
    color: var(--hcis-indigo-600);
}

.tpo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 0.5rem;
}
.tpo-card {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    text-align: left;
    padding: 0.55rem 0.7rem;
    border-radius: 12px;
    cursor: pointer;
    border: 1px solid var(--border, #dbe1ea);
    background: #fff;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.15s ease;
}
.tpo-card:hover {
    border-color: var(--hcis-indigo-300);
    transform: translateY(-1px);
}
.tpo-card.on {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.05);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.tpo-card.sudah {
    background: rgba(245, 158, 11, 0.05);
}
.tpo-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}
.tpo-nama {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--hcis-slate-800);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tpo-meta {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tpo-meta.faint {
    color: var(--hcis-slate-400);
}
.tpo-flags {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-shrink: 0;
    color: var(--hcis-slate-300);
}
.tpo-card.on .tpo-flags i {
    color: var(--hcis-indigo-500);
}
.tpo-pill {
    font-size: 0.62rem;
    font-weight: 800;
    padding: 0.15rem 0.4rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
}
.tpo-pill.warn {
    background: rgba(245, 158, 11, 0.16);
    color: var(--hcis-amber-800);
}

.tpo-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
}
.tpo-empty i {
    font-size: 1.6rem;
}

@media (max-width: 767px) {
    .tpo-sel {
        flex: 1 1 calc(50% - 0.25rem);
        min-width: 0;
    }
    .tpo-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
    }
    .tpo-count {
        margin-left: 0 !important;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 0.3rem;
        border-top: 1px dashed var(--hcis-slate-200);
    }
}
</style>
