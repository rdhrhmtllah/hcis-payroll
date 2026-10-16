<!--
    Sengaja tidak diimpor siapa pun untuk sementara.

    Tab Dokumen dinonaktifkan di SopMonitoring.vue, dan komentar di sana
    menyatakan berkas ini dibiarkan utuh supaya gampang dipasang lagi.

    Analisis keterjangkauan otomatis akan menandai berkas ini sebagai mati —
    itu benar secara teknis dan salah secara maksud. Jangan arsipkan tanpa
    memeriksa lebih dulu apakah tab-nya sudah jadi dipasang atau memang
    dibatalkan.
-->
<template>
    <div class="sop-stack">
        <div
            class="stitch-card stitch-pad sop-fade-up sop-glass-card shadow-sm border-0 position-relative overflow-hidden"
            style="border-radius: 20px"
        >
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <div class="sop-eyebrow d-flex align-items-center gap-1">
                        <i class="bi bi-shield-check text-primary"></i> Kontrol dokumen
                    </div>
                    <h6 class="sop-h fw-extrabold fs-5 mb-1">Register Dokumen Berlaku</h6>
                    <p class="small text-muted mb-0">
                        Hanya versi <strong>aktif</strong> tiap item yang diaudit — versi lama tak lagi mengikat siapa
                        pun. Kolom <em>Sudah Konfirmasi</em> menunjukkan berapa karyawan yang sudah menyatakan membaca
                        versi itu, dari total yang terikat padanya.
                    </p>
                </div>
            </div>

            <!-- Chip ember: sekaligus ringkasan dan saringan -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="bucket-chip" :class="{ on: !bucket }" @click="pilih(null)">
                    Semua <span class="n">{{ ringkas.total || 0 }}</span>
                </button>
                <button
                    v-for="b in embers"
                    :key="b.key"
                    class="bucket-chip"
                    :class="[b.tone, { on: bucket === b.key }]"
                    @click="pilih(b.key)"
                >
                    <i class="bi" :class="b.icon"></i> {{ b.label }}
                    <span class="n">{{ ringkas[b.field] || 0 }}</span>
                </button>
            </div>

            <div class="sop-search mb-3" style="max-width: 320px">
                <i class="bi bi-search"></i>
                <input
                    v-model="q"
                    type="search"
                    class="sop-field"
                    placeholder="Cari Dokumen / dokumen / no. dokumen…"
                    @input="cari"
                />
            </div>

            <div v-if="loading" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <EmptyState
                v-else-if="!rows.length"
                :title="bucket ? 'Tak ada dokumen pada kategori ini' : 'Belum ada dokumen berlaku'"
                subtitle="Dokumen muncul di sini setelah versinya diterbitkan di Builder."
                icon="bi-file-earmark-check"
            />
            <div v-else class="doc-list">
                <div v-for="d in kelompok" :key="d.__key" class="doc">
                    <div class="doc-main">
                        <div class="d-flex align-items-start gap-2 min-w-0">
                            <ItemTypeIcon :tipe="d.Tipe_Item" />
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="fw-bold text-truncate">{{ d.Judul_Item }}</span>
                                    <button
                                        v-if="d.Id_SOP_Dokumen"
                                        class="doc-open"
                                        title="Lihat dokumen: paket pemakai & riwayat versi"
                                        @click="bukaDokumen(d.Id_SOP_Dokumen)"
                                    >
                                        <i class="bi bi-arrows-angle-expand"></i>
                                    </button>
                                </div>
                                <div class="small text-muted text-truncate">
                                    <span v-if="d.Nomor_SOP_Resmi">{{ d.Nomor_SOP_Resmi }} · </span>
                                    <span class="vbadge">{{ $vlabel(d.Versi_Ke) }}</span>
                                </div>

                                <!-- Paket pemakai sebagai pil. Satu dokumen bisa dipakai
                                     beberapa paket; menampilkannya sebagai baris terpisah
                                     akan mengulang dokumen yang sama berkali-kali. -->
                                <div class="doc-pakets">
                                    <span v-for="p in d.__paket" :key="p" class="doc-paket">{{ p }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Temuan ditampilkan sebagai pil, bukan satu label tunggal:
                             satu dokumen sering kena lebih dari satu masalah sekaligus. -->
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <span v-for="b in d.Bucket" :key="b" class="temuan" :class="toneOf(b)">{{
                                labelEmber(b)
                            }}</span>
                            <span v-if="!d.Bucket.length" class="temuan ok">Tanpa temuan</span>
                        </div>
                    </div>

                    <div class="doc-meta">
                        <div class="dm">
                            <span class="dm-lab">Berlaku sejak</span>
                            <span class="dm-val">{{ d.Tanggal_Berlaku || '—' }}</span>
                        </div>
                        <div class="dm">
                            <span class="dm-lab">Pengesahan</span>
                            <span class="dm-val">
                                <template v-if="d.Disahkan_Oleh"
                                    >{{ d.Disahkan_Oleh
                                    }}<small v-if="d.Nomor_Pengesahan"> · {{ d.Nomor_Pengesahan }}</small></template
                                >
                                <span v-else class="text-danger fw-bold">Belum disahkan</span>
                            </span>
                        </div>
                        <div class="dm">
                            <span class="dm-lab">Sudah Konfirmasi</span>
                            <span class="dm-val tnum">{{ d.Terdampak_Dikonfirmasi }} / {{ d.Terdampak_Target }}</span>
                            <div class="sop-meter mt-1">
                                <span :style="{ width: persenDampak(d) + '%' }"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pembatasan wajib dilaporkan, bukan didiamkan: pengelompokan
                 dilakukan di sisi klien atas SATU halaman, jadi satu halaman
                 berisi N baris yang bisa menjadi kurang dari N dokumen. -->
            <p v-if="rows.length && kelompok.length < rows.length" class="doc-note">
                {{ rows.length }} baris pada halaman ini tergabung menjadi {{ kelompok.length }} dokumen. Penggabungan
                berlaku per halaman, sehingga dokumen yang barisnya terpisah antar halaman akan tampil di keduanya.
            </p>

            <div v-if="pagination.total_page > 1" class="sop-pager">
                <button class="stitch-icon-btn" :disabled="pagination.page <= 1" @click="fetch(pagination.page - 1)">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span class="tnum"
                    >Halaman {{ pagination.page }} / {{ pagination.total_page }} · {{ pagination.total }} dokumen</span
                >
                <button
                    class="stitch-icon-btn"
                    :disabled="pagination.page >= pagination.total_page"
                    @click="fetch(pagination.page + 1)"
                >
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>

        <SopDokumenDetailDrawer :show="!!dokumenTerbuka" :id-dokumen="dokumenTerbuka" @close="dokumenTerbuka = null" />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import ItemTypeIcon from '../../../Shared/components/ui/ItemTypeIcon.vue';
import EmptyState from '../../../Shared/components/ui/EmptyState.vue';
import SopDokumenDetailDrawer from '../../../Shared/components/ui/SopDokumenDetailDrawer.vue';

const API = '/api/v1/sop/admin/monitoring';

/** Label + rona per ember; dipakai chip filter dan pil temuan sekaligus. */
const EMBER = {
    TANPA_PENGESAHAN: {
        label: 'Tanpa pengesahan',
        field: 'tanpa_pengesahan',
        icon: 'bi-patch-exclamation',
        tone: 'danger',
    },
    RENDER_GAGAL: { label: 'Render gagal', field: 'render_gagal', icon: 'bi-exclamation-triangle', tone: 'warn' },
};

/**
 * Tab Dokumen — kontrol dokumen.
 *
 * Menjawab dua pertanyaan pertama saat audit: dokumen mana yang berlaku tanpa ada
 * yang mengesahkannya, dan berkas mana yang gagal dirender sehingga karyawan
 * membuka halaman kosong. Keduanya keadaan yang benar-benar salah — bukan
 * kolom tanggal yang kebetulan belum diisi.
 *
 * Angka "Sudah Konfirmasi" yang mengubahnya dari catatan administratif jadi risiko
 * terukur: 142 orang terikat pada dokumen yang tak seorang pun menandatangani.
 *
 * MASA BERLAKU SUDAH TAK ADA DI SINI — konsep kadaluarsa & review berikutnya
 * dicabut 15 Agu 2026. Sebuah dokumen berlaku sampai ada revisi versi baru.
 */
export default {
    name: 'TabDokumen',
    components: { ItemTypeIcon, EmptyState, SopDokumenDetailDrawer },
    props: { awal: { type: Object, default: () => ({}) } },
    emits: ['lencana'],
    data() {
        return {
            rows: [],
            ringkas: {},
            bucket: null,
            q: '',
            timer: null,
            loading: false,
            pagination: { page: 1, per_page: 20, total_page: 1, total: 0 },
            embers: Object.entries(EMBER).map(([key, v]) => ({ key, ...v })),
            dokumenTerbuka: null,
        };
    },
    computed: {
        /**
         * Baris register digabung per DOKUMEN.
         *
         * register() mengembalikan satu baris per ITEM. Hari ini 1 dokumen = 1
         * item sehingga hasilnya sama saja, tapi begitu Fase 6 menyalakan
         * berbagi, satu dokumen yang dipakai 5 paket akan muncul 5 kali — di
         * layar yang justru mengaku mengendalikan dokumen.
         *
         * Digabung DI KLIEN, bukan di SQL, karena register() sudah berhalaman:
         * mengelompokkan di SQL akan mengubah arti `total` dan menggeser semua
         * angka ember sekaligus. Konsekuensinya (gabungan hanya berlaku dalam
         * satu halaman) dilaporkan di UI, tidak didiamkan.
         *
         * Kunci selalu dinormalkan ke STRING: sqlsrv mengembalikan kolom
         * numerik sebagai string, dan mencampur int dengan string akan memecah
         * satu dokumen jadi dua kelompok tanpa gejala yang jelas.
         */
        kelompok() {
            const urut = [];
            const peta = new Map();

            for (const r of this.rows) {
                const id =
                    r.Id_SOP_Dokumen === null || r.Id_SOP_Dokumen === undefined ? null : String(r.Id_SOP_Dokumen);

                // Baris tanpa dokumen (versi yatim) TIDAK digabung — mereka
                // bukan satu dokumen terkendali, dan membuangnya akan
                // menyembunyikan mayoritas baris di data dev.
                if (id === null) {
                    urut.push({ ...r, __key: `v${r.Id_SOP_Item_Versi}`, __paket: [r.Nama_Paket].filter(Boolean) });
                    continue;
                }

                if (!peta.has(id)) {
                    const baris = { ...r, __key: `d${id}`, __paket: [] };
                    peta.set(id, baris);
                    urut.push(baris);
                }

                const baris = peta.get(id);
                if (r.Nama_Paket && !baris.__paket.includes(r.Nama_Paket)) baris.__paket.push(r.Nama_Paket);
            }

            return urut;
        },
    },
    mounted() {
        if (this.awal?.bucket) this.bucket = this.awal.bucket;
        this.muatSemua();
    },
    watch: {
        awal: {
            deep: true,
            handler(v) {
                this.bucket = v?.bucket || null;
                this.fetch(1);
            },
        },
    },
    methods: {
        bukaDokumen(id) {
            this.dokumenTerbuka = id;
        },
        labelEmber(b) {
            return (EMBER[b] || {}).label || b;
        },
        toneOf(b) {
            return (EMBER[b] || {}).tone || '';
        },
        persenDampak(d) {
            return d.Terdampak_Target > 0 ? Math.round((d.Terdampak_Dikonfirmasi / d.Terdampak_Target) * 100) : 0;
        },
        pilih(b) {
            this.bucket = this.bucket === b ? null : b;
            this.fetch(1);
        },
        cari() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetch(1), 450);
        },
        muatSemua() {
            this.fetchRingkas();
            this.fetch(1);
        },
        async fetchRingkas() {
            try {
                const r = await axios.get(`${API}/dokumen/ringkasan`);
                if (r.data.success) {
                    this.ringkas = r.data.result;
                    // Lencana tab = dokumen yang benar-benar bermasalah, bukan totalnya.
                    const bermasalah =
                        (this.ringkas.kadaluarsa || 0) +
                        (this.ringkas.review_lewat || 0) +
                        (this.ringkas.render_gagal || 0);
                    this.$emit('lencana', { key: 'dokumen', value: bermasalah || null });
                }
            } catch (e) {
                /* chip boleh kosong */
            }
        },
        async fetch(page = 1) {
            if (page < 1) return;
            this.loading = true;
            try {
                const r = await axios.get(`${API}/dokumen`, {
                    params: {
                        page,
                        limit: this.pagination.per_page,
                        bucket: this.bucket || undefined,
                        q: this.q || undefined,
                    },
                });
                this.rows = r.data.success ? r.data.result || [] : [];
                this.pagination.page = r.data.page || page;
                this.pagination.total_page = r.data.total_page || 1;
                this.pagination.total = r.data.total_data ?? this.rows.length;
            } catch (e) {
                this.rows = [];
                ElMessage.error('Gagal memuat register dokumen');
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
/* Fase 5 — daftar bersumbu dokumen */
.doc-open {
    border: 0;
    background: transparent;
    color: var(--hcis-slate-500);
    padding: 0 0.15rem;
    line-height: 1;
    border-radius: 6px;
    flex: none;
}
.doc-open:hover {
    color: var(--hcis-slate-900);
    background: var(--hcis-slate-100);
}

.doc-pakets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.35rem;
}
.doc-paket {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.doc-note {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    margin: 0.75rem 0 0;
    padding-top: 0.6rem;
    border-top: 1px dashed var(--bs-border-color, #e5e7eb);
}

.doc-list {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.doc {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.6fr);
    gap: 1rem;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.9rem 1rem;
    background: #fff;
}
.doc-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
}
.dm {
    min-width: 0;
}
.dm-lab {
    display: block;
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-400);
}
.dm-val {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    margin-top: 2px;
}
.dm-val small {
    font-weight: 600;
    color: var(--hcis-slate-400);
}

.temuan {
    font-size: 0.64rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.12rem 0.5rem;
    background: var(--surface-soft, var(--hcis-slate-100));
    color: var(--hcis-slate-500);
}
.temuan.danger {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-700);
}
.temuan.warn {
    background: rgba(245, 158, 11, 0.14);
    color: var(--hcis-amber-800);
}
.temuan.ok {
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-700);
}

.sop-meter {
    height: 6px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    overflow: hidden;
}
.sop-meter > span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6);
}

@media (max-width: 991.98px) {
    .doc {
        grid-template-columns: 1fr;
    }
}
</style>
