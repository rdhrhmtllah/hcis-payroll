<template>
    <div class="ver-panel">
        <!-- ══ HEADER TOGGLE SAKLAR ══ -->
        <div class="ver-head is-toggle">
            <button type="button" class="ver-toggle" :aria-expanded="terbuka" @click="toggle">
                <i class="bi ver-chev" :class="terbuka ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                <div class="ver-title-wrap min-w-0">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary"></i>
                        <span class="ver-title">Riwayat Versi &amp; Konten</span>
                        <span v-if="sudahDimuat" class="ver-ring">
                            <span class="ver-ring-n tnum">{{ versiList.length }}</span> Versi
                        </span>
                        <span v-else class="ver-ring samar">Jejak Konten</span>
                    </div>
                    <span class="ver-subtitle text-muted">Jejak rilis versi, pengesahan, dan isi dokumen terkendali.</span>
                </div>
            </button>

            <a
                v-if="tautanMaster"
                :href="tautanMaster"
                target="_blank"
                rel="noopener"
                class="stitch-btn secondary sm flex-shrink-0"
                title="Buka dan kelola versi di Master Dokumen"
            >
                <i class="bi bi-box-arrow-up-right me-1"></i> Master Dokumen
            </a>
        </div>

        <!-- ══ VERSI AKTIF BERLAKU (RINGKASAN SAAT COLLAPSED) ══ -->
        <div v-if="memuatAktif" class="ver-aktif-skel sop-skel"></div>

        <div v-else-if="!terbuka && versiAktif" class="ver-aktif" @click="toggle">
            <div class="va-dot">
                <span>{{ $vlabel(versiAktif.Versi_Ke) }}</span>
            </div>
            <div class="va-isi min-w-0 flex-grow-1">
                <div class="va-atas">
                    <StatusPill
                        :text="statusLabel(versiAktif.Status)"
                        :variant="statusVariant(versiAktif.Status)"
                        :dot="false"
                    />
                    <span class="vn-active">
                        <span class="pulse-dot"></span> Aktif Berlaku
                    </span>
                    <span v-if="versiAktif.Tanggal_Berlaku" class="va-tgl">
                        <i class="bi bi-calendar-check me-1"></i> Berlaku sejak {{ fmt(versiAktif.Tanggal_Berlaku) }}
                    </span>
                </div>
                <!-- Isi versi berlaku: berkas utama (contoh pengisian untuk FORM)
                     dan form kosong yang ikut diterima karyawan. -->
                <ul class="isi-list mt-1">
                    <li v-for="r in isiVersi(versiAktif)" :key="r.kunci" class="isi-baris">
                        <span class="isi-label">{{ r.label }}</span>
                        <span v-if="!r.berkas.length" class="isi-kosong">{{ r.kosong }}</span>
                        <span v-for="(b, i) in r.berkas" :key="i" class="isi-berkas">
                            <i class="bi" :class="kontenIcon(b.Ext || versiAktif.Konten_Tipe)"></i>
                            <span class="isi-nama text-truncate" :title="b.Nama">{{ b.Nama }}</span>
                            <span v-if="b.Ext" class="isi-ext">{{ b.Ext }}</span>
                            <span v-if="b.Ukuran" class="isi-ukuran">{{ fmtUkuran(b.Ukuran) }}</span>
                        </span>
                    </li>
                </ul>
            </div>
            <div class="va-action-hint">
                <span class="va-hint-pill">
                    Lihat Riwayat <i class="bi bi-chevron-right"></i>
                </span>
            </div>
        </div>

        <div v-else-if="!terbuka && sudahCekAktif" class="ver-aktif kosong">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
            <div class="min-w-0">
                <div class="fw-bold">Belum Ada Versi Berlaku</div>
                <div class="small text-muted">Dokumen ini belum memiliki versi yang berstatus Published di Master Dokumen.</div>
            </div>
        </div>

        <!-- ══ EXPANDED TIMELINE RIWAYAT ══ -->
        <template v-if="terbuka">
            <div class="ver-lingkup">
                <i class="bi bi-info-circle-fill text-primary"></i>
                <span>
                    Versi dan berkas dikelola secara terpusat di <strong>Master Dokumen</strong> melalui alur pengesahan resmi.
                    Revisi yang diterbitkan di sana otomatis tersinkronisasi ke seluruh paket pemakai.
                </span>
            </div>

            <!-- Loading Spinner -->
            <div v-if="loading" class="ver-loading-box py-3 px-2">
                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold small">
                    <span class="spinner-border spinner-border-sm"></span>
                    <span>Memuat riwayat versi &amp; konten...</span>
                </div>
                <div class="sop-skel mb-2" style="height: 58px; border-radius: 12px"></div>
                <div class="sop-skel" style="height: 48px; border-radius: 12px"></div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!versiList.length" class="ver-empty">
                <i class="bi bi-layers text-muted fs-4"></i>
                <div>
                    <div class="fw-bold text-dark">Belum ada versi rilis</div>
                    <div class="small text-muted">Ajukan dan sahkan versi pertama melalui modul Master Dokumen.</div>
                </div>
            </div>

            <!-- Timeline List -->
            <div v-else class="ver-timeline">
                <div
                    v-for="v in versiTampil"
                    :key="v.Id_SOP_Item_Versi"
                    class="ver-node"
                    :class="vClass(v.Status)"
                >
                    <!-- Timeline Rail Indicator -->
                    <div class="vn-rail">
                        <div class="vn-dot" :class="{ 'is-active': v.Status === 'PUBLISHED' }">
                            {{ $vlabel(v.Versi_Ke) }}
                        </div>
                    </div>

                    <!-- Timeline Content Card -->
                    <div class="vn-body">
                        <!-- Top Header Status -->
                        <div class="vn-top">
                            <StatusPill
                                :text="statusLabel(v.Status)"
                                :variant="statusVariant(v.Status)"
                                :dot="false"
                            />
                            <span v-if="v.Status === 'PUBLISHED'" class="vn-active">
                                <span class="pulse-dot"></span> Versi Aktif
                            </span>

                            <span v-if="v.Tanggal_Berlaku" class="vn-meta-pill date" title="Tanggal mulai berlaku">
                                <i class="bi bi-calendar-check"></i> Berlaku {{ fmt(v.Tanggal_Berlaku) }}
                            </span>
                            <span v-if="v.Nomor_Pengesahan" class="vn-meta-pill no-sah" title="Nomor Dokumen Pengesahan">
                                <i class="bi bi-patch-check"></i> {{ v.Nomor_Pengesahan }}
                            </span>
                        </div>

                        <!-- Content Attachment Box -->
                        <div class="vn-konten">
                            <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                                <div class="vn-konten-icon">
                                    <i class="bi" :class="kontenIcon(v.Konten_Tipe)"></i>
                                </div>
                                <ul class="isi-list min-w-0">
                                    <li v-for="r in isiVersi(v)" :key="r.kunci" class="isi-baris">
                                        <span class="isi-label">{{ r.label }}</span>
                                        <span v-if="!r.berkas.length" class="isi-kosong">{{ r.kosong }}</span>
                                        <span v-for="(b, i) in r.berkas" :key="i" class="isi-berkas">
                                            <span class="isi-nama text-truncate" :title="b.Nama">{{ b.Nama }}</span>
                                            <span v-if="b.Ext" class="isi-ext">{{ b.Ext }}</span>
                                            <span v-if="b.Ukuran" class="isi-ukuran">{{ fmtUkuran(b.Ukuran) }}</span>
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <button
                                v-if="v.Id_Master_Konten"
                                type="button"
                                class="stitch-btn ghost sm vn-konten-btn"
                                title="Buka pratinjau konten berkas"
                                :disabled="busyId === v.Id_SOP_Item_Versi"
                                @click="openView(v)"
                            >
                                <span
                                    v-if="busyId === v.Id_SOP_Item_Versi"
                                    class="spinner-border spinner-border-sm"
                                ></span>
                                <template v-else>
                                    <i class="bi bi-eye me-1"></i> Buka Berkas
                                </template>
                            </button>
                        </div>

                        <!-- Ringkasan Perubahan -->
                        <div v-if="v.Ringkasan_Perubahan" class="vn-change">
                            <i class="bi bi-quote me-1"></i>
                            <span>{{ v.Ringkasan_Perubahan }}</span>
                        </div>

                        <!-- Jejak Aktor (Dibuat & Diubah) -->
                        <div v-if="v.Dibuat_Oleh_Nama || v.Diubah_Oleh_Nama" class="vn-jejak">
                            <span v-if="v.Dibuat_Oleh_Nama" class="vn-jejak-item">
                                <i class="bi bi-person-check"></i>
                                Dibuat oleh <strong>{{ v.Dibuat_Oleh_Nama }}</strong>
                                <template v-if="v.Created_At"> · {{ fmtWaktu(v.Created_At) }}</template>
                            </span>
                            <span v-if="tampilDiubah(v)" class="vn-jejak-item">
                                <i class="bi bi-pencil-square"></i>
                                Diubah oleh <strong>{{ v.Diubah_Oleh_Nama }}</strong>
                                <template v-if="v.Updated_At"> · {{ fmtWaktu(v.Updated_At) }}</template>
                            </span>
                        </div>

                        <!-- Blok Pengesah & Penanda Tangan -->
                        <div v-if="(v.Pengesah || []).length" class="vn-sah">
                            <div class="vn-sah-title">
                                <i class="bi bi-shield-check me-1 text-primary"></i> Otoritas Pengesahan
                            </div>
                            <div class="vn-sah-grid">
                                <div v-for="(p, i) in v.Pengesah" :key="i" class="vn-sah-card">
                                    <div class="vn-sah-role">{{ p.Peran || 'PENGESAH' }}</div>
                                    <div class="vn-sah-nama-row">
                                        <span class="vn-sah-nama text-truncate" :title="p.Nama">{{ p.Nama }}</span>
                                        <i
                                            v-if="p.Terverifikasi"
                                            class="bi bi-patch-check-fill text-success"
                                            title="Tertaut identitas karyawan terverifikasi"
                                        ></i>
                                    </div>
                                    <div v-if="p.Jabatan" class="vn-sah-jab text-truncate" :title="p.Jabatan">
                                        {{ p.Jabatan }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination Load More -->
                <div v-if="sisaVersi" class="ver-lagi">
                    <button type="button" class="stitch-btn secondary sm" @click="muatLagi">
                        <i class="bi bi-arrow-down-circle me-1"></i>
                        Tampilkan {{ Math.min(sisaVersi, PER_MUAT) }} Versi Terdahulu
                    </button>
                    <span class="ver-lagi-ket">{{ versiTampil.length }} dari {{ versiList.length }} versi ditampilkan</span>
                </div>
            </div>
        </template>

        <!-- ══ MODAL PRATINJAU BERKAS LENGKAP (HcisFileViewer) ══ -->
        <HcisFileViewer
            v-if="viewerFile"
            v-model="showViewer"
            :file="viewerFile"
            :title="viewerFile.name"
            :allow-download="true"
            :allow-print="true"
            :allow-external-link="true"
            @close="showViewer = false"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import StatusPill from './StatusPill.vue';
import HcisFileViewer from '@/components/ui/HcisFileViewer.vue';

const APIB = '/api/v1/sop/admin/berkas';
const PER_MUAT = 5;

/**
 * SopVersiRingkas — Riwayat Versi & Konten Item Dokumen Terkendali.
 */
export default {
    name: 'SopVersiRingkas',
    components: { StatusPill, HcisFileViewer },
    props: {
        idItem: { type: String, required: true },
        /** Hashid dokumen item ini — dipakai menautkan ke Master Dokumen */
        idDokumen: { type: String, default: '' },
        /** Tipe item (DOKUMEN | FORM | VIDEO) — menentukan label isi versi. */
        tipe: { type: String, default: '' },
    },
    data() {
        return {
            versiList: [],
            loading: false,
            busyId: null,
            terbuka: false,
            sudahDimuat: false,
            versiAktif: null,
            memuatAktif: false,
            sudahCekAktif: false,
            tampil: PER_MUAT,
            showViewer: false,
            viewerFile: null,
        };
    },
    computed: {
        PER_MUAT: () => PER_MUAT,
        versiTampil() {
            return this.versiList.slice(0, this.tampil);
        },
        sisaVersi() {
            return Math.max(0, this.versiList.length - this.tampil);
        },
        /**
         * null saat item belum bertaut dokumen — dan itu yang membuat `v-if`
         * di atasnya berarti.
         *
         * Sebelumnya ia memulangkan '/sop/admin/dokumen' sebagai cadangan.
         * Nilai itu truthy, sehingga `v-if="tautanMaster"` TAK PERNAH BISA
         * SALAH: penjaganya jadi kode mati, dan tombol "Master Dokumen" tetap
         * ditawarkan pada item yang belum punya dokumen sama sekali —
         * mengantar orang ke daftar kosong alih-alih memberi tahu bahwa
         * dokumennya memang belum ada.
         *
         * Uji SopVersiRingkas sempat menegaskan justru sebaliknya ("tak
         * menawarkan tautan saat belum bertaut") dan LULUS bertahun-tahun tanpa
         * memeriksa apa pun, karena selektornya menunjuk tempat yang salah.
         * Begitu selektornya dibetulkan, ketidakcocokan ini terlihat.
         */
        tautanMaster() {
            return this.idDokumen ? `/sop/admin/dokumen?id=${encodeURIComponent(this.idDokumen)}` : null;
        },
    },
    watch: {
        idItem: {
            immediate: true,
            handler(v) {
                if (!v) return;
                this.versiList = [];
                this.versiAktif = null;
                this.sudahDimuat = false;
                this.sudahCekAktif = false;
                this.muatAktif();
                if (this.terbuka) this.load();
            },
        },
        idDokumen() {
            this.versiList = [];
            this.versiAktif = null;
            this.sudahDimuat = false;
            this.sudahCekAktif = false;
            this.muatAktif();
            if (this.terbuka) this.load();
        },
    },
    methods: {
        fmt(d) {
            if (!d) return '—';
            const x = new Date(d);
            return isNaN(x) ? d : x.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        fmtWaktu(d) {
            if (!d) return '';
            const x = new Date(String(d).replace(' ', 'T'));
            if (isNaN(x)) return d;
            return (
                x.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) +
                ' ' +
                x.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
            );
        },
        tampilDiubah(v) {
            if (!v.Diubah_Oleh_Nama) return false;
            if (v.Diubah_Oleh_Nama !== v.Dibuat_Oleh_Nama) return true;
            return this.fmtWaktu(v.Updated_At) !== this.fmtWaktu(v.Created_At);
        },
        statusLabel(s) {
            return { DRAFT: 'Draft', READY: 'Siap Rilis', PUBLISHED: 'Published', OBSOLETE: 'Obsolete' }[s] || s;
        },
        statusVariant(s) {
            return { DRAFT: 'idle', READY: 'ok', PUBLISHED: 'ok', OBSOLETE: 'bad' }[s] || 'idle';
        },
        vClass(s) {
            return { PUBLISHED: 'is-active', OBSOLETE: 'is-obsolete' }[s] || '';
        },
        /**
         * Isi sebuah versi sebagai daftar berlabel: berkas utama, lalu form
         * kosong untuk FORM. Admin perlu tahu apa yang sebenarnya diterima
         * karyawan — satu judul berkas tak menyebut jenisnya, apalagi form
         * kosong yang ikut terpasang.
         */
        isiVersi(v) {
            if (!v) return [];
            const form = this.tipe === 'FORM';
            const baris = [
                {
                    kunci: 'utama',
                    label: form ? 'Contoh pengisian' : 'Berkas',
                    berkas: v.Konten_Nama
                        ? [{ Nama: v.Konten_Nama, Ext: v.Konten_Ext, Ukuran: v.Ukuran_File }]
                        : [],
                    kosong: 'Belum ada berkas',
                },
            ];
            if (form) {
                const lampiran = v.Lampiran || [];
                baris.push({
                    kunci: 'lampiran',
                    label: lampiran.length ? `Form kosong (${lampiran.length})` : 'Form kosong',
                    berkas: lampiran,
                    kosong: 'Belum ada form kosong',
                });
            }
            return baris;
        },
        fmtUkuran(b) {
            if (!b) return '';
            if (b < 1024) return `${b} B`;
            if (b < 1024 * 1024) return `${(b / 1024).toLocaleString('id-ID', { maximumFractionDigits: 0 })} KB`;
            return `${(b / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB`;
        },
        kontenIcon(t) {
            const s = String(t || '').toLowerCase();
            if (s.includes('video')) return 'bi-play-btn-fill text-primary';
            if (s.includes('pdf')) return 'bi-file-earmark-pdf-fill text-danger';
            if (s.includes('image') || ['png', 'jpg', 'jpeg'].includes(s)) return 'bi-file-earmark-image-fill text-success';
            if (['xls', 'xlsx', 'csv'].includes(s)) return 'bi-file-earmark-spreadsheet-fill text-success';
            if (['doc', 'docx'].includes(s)) return 'bi-file-earmark-word-fill text-primary';
            return 'bi-file-earmark-text-fill text-primary';
        },
        async muatAktif() {
            if (!this.idItem) return;
            this.memuatAktif = true;
            try {
                const r = await axios.get(`${APIB}/item/${this.idItem}/versi`, { params: { ringkas: 1 } });
                const rows = r.data.success ? r.data.result || [] : [];
                this.versiAktif = rows[0] || null;
                this.sudahCekAktif = true;
            } catch (e) {
                this.versiAktif = null;
            } finally {
                this.memuatAktif = false;
            }
        },
        async load() {
            if (!this.idItem) return;
            this.loading = true;
            try {
                const r = await axios.get(`${APIB}/item/${this.idItem}/versi`);
                this.versiList = r.data.success ? r.data.result : [];
                this.sudahDimuat = true;
                this.versiAktif = this.versiList.find((v) => v.Status === 'PUBLISHED') || null;
                this.sudahCekAktif = true;
            } catch (e) {
                this.versiList = [];
            } finally {
                this.loading = false;
            }
            this.tampil = PER_MUAT;
        },
        toggle() {
            this.terbuka = !this.terbuka;
            if (this.terbuka && !this.sudahDimuat && !this.loading) this.load();
        },
        muatLagi() {
            this.tampil += PER_MUAT;
        },
        async openView(v) {
            this.busyId = v.Id_SOP_Item_Versi;
            try {
                const r = await axios.get(`${APIB}/versi/${v.Id_SOP_Item_Versi}/konten`);
                const res = r.data?.result || {};
                const url = r.data.success ? res.url : null;
                if (url) {
                    this.viewerFile = {
                        url: url,
                        name: res.nama_berkas || res.judul || v.Konten_Nama || 'Dokumen Resmi.pdf',
                        type: res.mime || (String(res.type).toLowerCase() === 'video' ? 'video/mp4' : 'application/pdf'),
                        size: res.size || null,
                        allowDownload: true,
                        allowPrint: true,
                        allowExternalLink: true,
                    };
                    this.showViewer = true;
                } else {
                    ElMessage.error(r.data.message || 'Gagal memuat konten berkas');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat konten berkas');
            } finally {
                this.busyId = null;
            }
        },
    },
};
</script>

<style scoped>
.ver-panel {
    margin-top: 0.95rem;
    border-top: 1px dashed var(--hcis-slate-200);
    padding-top: 0.85rem;
}

/* ── HEADER TOGGLE ──────────────────────────────────────────────── */
.ver-head.is-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    margin-bottom: 0.5rem;
}

.ver-toggle {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    border: none;
    background: transparent;
    padding: 0.2rem 0;
    text-align: left;
    cursor: pointer;
}

.ver-chev {
    color: var(--hcis-slate-400);
    font-size: 0.95rem;
    transition: all 0.16s ease;
    flex-shrink: 0;
}

.ver-toggle:hover .ver-chev {
    color: var(--hcis-indigo-600);
    transform: scale(1.1);
}

.ver-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    line-height: 1.2;
}

.ver-subtitle {
    font-size: 0.72rem;
    display: block;
    line-height: 1.2;
    margin-top: 0.15rem;
}

.ver-ring {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 999px;
    padding: 0.12rem 0.5rem;
    line-height: 1.1;
}

.ver-ring.samar {
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
}

/* ── ACTIVE SUMMARY CARD ────────────────────────────────────────── */
.ver-aktif {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid rgba(99, 102, 241, 0.22);
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.04), rgba(240, 253, 244, 0.5));
    cursor: pointer;
    transition: all 0.16s ease;
}

.ver-aktif:hover {
    border-color: var(--hcis-indigo-500);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(240, 253, 244, 0.8));
}

.ver-aktif.kosong {
    border-color: var(--hcis-amber-200);
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
    cursor: default;
}

.ver-aktif-skel {
    height: 54px;
    border-radius: 12px;
    margin-top: 0.5rem;
}

.va-dot {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-cyan-500));
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
    flex-shrink: 0;
}

.va-atas {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.vn-active {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-emerald-600);
}

.pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-emerald-500);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
}

.va-tgl {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}

/* ── ISI VERSI (berkas utama + form kosong) ─────────────────────── */
.isi-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.isi-baris {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0.3rem 0.6rem;
    min-width: 0;
}

.isi-label {
    flex-shrink: 0;
    min-width: 8.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}

.isi-berkas {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    max-width: 100%;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
}

.isi-nama {
    min-width: 0;
}

.isi-ext {
    flex-shrink: 0;
    font-size: 0.64rem;
    font-weight: 700;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.isi-ukuran {
    flex-shrink: 0;
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--hcis-slate-500);
}

.isi-kosong {
    font-size: 0.76rem;
    font-style: italic;
    color: var(--hcis-slate-400);
}

.va-action-hint {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.va-hint-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-indigo-600);
    background: var(--hcis-white);
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 999px;
    padding: 0.16rem 0.55rem;
    line-height: 1.2;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.va-hint-pill .bi {
    font-size: 0.65rem;
    transition: transform 0.15s ease;
}

.ver-aktif:hover .va-hint-pill {
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    border-color: var(--hcis-indigo-600);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.ver-aktif:hover .va-hint-pill .bi {
    transform: translateX(2px);
}

/* ── LINGKUP NOTICE ─────────────────────────────────────────────── */
.ver-lingkup {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin: 0.6rem 0;
    padding: 0.6rem 0.8rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: var(--hcis-slate-50);
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
    line-height: 1.45;
}

/* ── TIMELINE ───────────────────────────────────────────────────── */
.ver-timeline {
    padding-top: 0.4rem;
}

.ver-node {
    display: flex;
    gap: 0.75rem;
    position: relative;
}

.vn-rail {
    flex-shrink: 0;
    width: 36px;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

.vn-rail::before {
    content: '';
    position: absolute;
    top: 38px;
    bottom: -8px;
    width: 2px;
    background: var(--hcis-slate-200);
    border-radius: 2px;
}

.ver-node:last-child .vn-rail::before {
    display: none;
}

.vn-dot {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
    background: var(--hcis-white);
    border: 2px solid var(--hcis-slate-200);
    transition: all 0.16s ease;
}

.vn-dot.is-active {
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-cyan-500));
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.vn-body {
    flex: 1;
    min-width: 0;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.75rem 0.85rem;
    margin-bottom: 0.75rem;
    background: var(--hcis-white);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.ver-node.is-active .vn-body {
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 14px -4px rgba(99, 102, 241, 0.12);
}

.vn-top {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.vn-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.vn-meta-pill.no-sah {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-200);
}

/* ── KONTEN ATTACHMENT BOX ──────────────────────────────────────── */
.vn-konten {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-top: 0.55rem;
    padding: 0.45rem 0.65rem;
    background: var(--hcis-slate-50);
    border: 1px solid #eef2f7;
    border-radius: 10px;
}

.vn-konten-icon {
    font-size: 1.15rem;
    flex-shrink: 0;
}

.vn-change {
    margin-top: 0.5rem;
    font-size: 0.76rem;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-50);
    border-left: 3px solid var(--hcis-indigo-500);
    padding: 0.35rem 0.6rem;
    border-radius: 0 6px 6px 0;
    line-height: 1.4;
}

.vn-jejak {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    flex-wrap: wrap;
}

.vn-jejak-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* ── PENGESAHAN GRID ────────────────────────────────────────────── */
.vn-sah {
    margin-top: 0.65rem;
    padding-top: 0.55rem;
    border-top: 1px dashed var(--hcis-slate-200);
}

.vn-sah-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
    margin-bottom: 0.35rem;
}

.vn-sah-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.45rem;
}

.vn-sah-card {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    padding: 0.4rem 0.55rem;
}

.vn-sah-role {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-indigo-500);
    margin-bottom: 0.1rem;
}

.vn-sah-nama-row {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.vn-sah-nama {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}

.vn-sah-jab {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
}

.ver-lagi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.65rem 0 0.2rem;
}

.ver-lagi-ket {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
</style>
