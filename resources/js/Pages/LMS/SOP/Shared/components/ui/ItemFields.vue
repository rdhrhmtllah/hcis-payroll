<template>
    <div class="item-fields">
        <!-- Tipe tanpa dokumen (PARAGRAF/TAUTAN/ABSENSI) membawa judulnya sendiri -->
        <div v-if="!dariDokumen" class="mb-3">
            <label class="sop-label">Judul Item <span class="text-danger">*</span></label>
            <input v-model="form.Judul_Item" class="sop-field" placeholder="Judul item" />
        </div>

        <!-- Tipe ber-dokumen (DOKUMEN, FORM, VIDEO, KUIS) -->
        <div v-else class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="sop-label mb-0">Dokumen Terpilih <span class="text-danger">*</span></label>
                <!-- TANPA gerbang isNew. Petunjuk ini justru paling dibutuhkan pada item
                     yang SUDAH ADA: di situlah judul tampil sebagai teks mati dan admin
                     bertanya "kenapa tak bisa saya ubah, dan di mana mengubahnya".
                     Sempat tergantung isNew pada 19b8fc39, dan penjelasannya hilang untuk
                     seluruh item lama. -->
                <span class="badge-master-hint">
                    <i class="bi bi-info-circle me-1"></i>Dikelola di Master Dokumen
                </span>
            </div>

            <!-- Overlay revisi paket terbit belum sanggup menyimpan tautan dokumen -->
            <div v-if="overlayBelumSiap" class="field-hint fh-danger mb-2">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <span>
                    Paket ini sudah terbit dan tabel revisinya belum bisa menyimpan tautan Dokumen. Item bertipe
                    {{ form.Tipe_Item }} baru bisa ditambahkan setelah <strong>DDL-2026-033</strong> dijalankan.
                </span>
            </div>

            <!-- Dokumen SUDAH TERPILIH (Rich Document Card) -->
            <div v-else-if="form.Id_SOP_Dokumen" class="dok-card dok-rich-card" :class="{ 'dok-terkunci': gantiTerkunci }">
                <div class="dok-card-header">
                    <div class="dok-badges">
                        <span class="dok-badge-tipe">{{ form.Tipe_Item }}</span>
                        <span v-if="form.Nomor_SOP_Resmi" class="dok-badge-nomor">
                            {{ form.Nomor_SOP_Resmi }}
                        </span>
                        <span v-else class="dok-badge-nomor text-muted">
                            Tanpa nomor dokumen
                        </span>
                        <span v-if="versiAktifLabel" class="dok-badge-versi">
                            <i class="bi bi-check-circle-fill me-1 text-success"></i>{{ versiAktifLabel }}
                        </span>
                    </div>

                    <!-- Tombol Ganti / Kunci -->
                    <div class="dok-actions">
                        <button
                            v-if="!gantiTerkunci"
                            type="button"
                            class="dok-ganti-btn dok-ganti"
                            title="Ganti dokumen yang ditautkan"
                            @click="dokPickerOpen = true"
                        >
                            <i class="bi bi-arrow-left-right me-1"></i> Ganti
                        </button>
                        <span v-else class="dok-kunci dok-kunci-pill" title="Tak bisa diganti pada paket yang sudah terbit">
                            <i class="bi bi-lock-fill me-1"></i> Terkunci
                        </span>
                    </div>
                </div>

                <div class="dok-card-body">
                    <h4 class="dok-judul dok-rich-title" :title="form.Judul_Item">
                        {{ form.Judul_Item || 'Dokumen terpilih' }}
                    </h4>

                    <div class="dok-meta-row">
                        <span v-if="form.Nama_Departemen" class="dok-meta-chip">
                            <i class="bi bi-building"></i> {{ form.Nama_Departemen }}
                        </span>
                        <span v-if="form.Nama_Jenis" class="dok-meta-chip">
                            <i class="bi bi-journal-text"></i> {{ form.Nama_Jenis }}
                        </span>
                        <span class="dok-meta-chip" :class="{ 'is-shared': paketLainPemakai > 0 }">
                            <i class="bi" :class="paketLainPemakai > 0 ? 'bi-diagram-3-fill text-warning' : 'bi-box-seam'"></i>
                            {{ paketLainPemakai > 0 ? `Dipakai di ${paketLainPemakai} paket lain` : 'Baru dipakai paket ini' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Dokumen TERKUNCI tanpa Id_SOP_Dokumen aktif -->
            <div v-else-if="gantiTerkunci" class="dok-card dok-terkunci dok-rich-card">
                <div class="dok-card-header">
                    <div class="dok-badges">
                        <span class="dok-badge-tipe">{{ form.Tipe_Item }}</span>
                        <span v-if="form.Nomor_SOP_Resmi" class="dok-badge-nomor">{{ form.Nomor_SOP_Resmi }}</span>
                        <span v-else class="dok-badge-nomor text-muted">Tanpa nomor dokumen</span>
                    </div>
                    <span class="dok-kunci dok-kunci-pill" title="Tak bisa diganti pada paket yang sudah terbit">
                        <i class="bi bi-lock-fill me-1"></i> Terkunci
                    </span>
                </div>
                <div class="dok-card-body">
                    <h4 class="dok-judul dok-rich-title">{{ form.Judul_Item || 'Dokumen item ini' }}</h4>
                </div>
            </div>

            <!-- BELUM PILIH DOKUMEN (Modern Empty Picker CTA) -->
            <button v-else type="button" class="dok-pilih dok-empty-card" @click="dokPickerOpen = true">
                <div class="dok-empty-icon">
                    <i class="bi bi-journal-plus"></i>
                </div>
                <div class="dok-empty-text">
                    <span class="dok-empty-title">Pilih Dokumen dari Master</span>
                    <span class="dok-empty-subtitle">Pilih dokumen resmi yang sudah aktif dan terbit</span>
                </div>
                <span class="dok-empty-btn">
                    <i class="bi bi-search me-1"></i> Cari Dokumen
                </span>
            </button>

            <!-- Peringatan Kesiapan / Validasi Dokumen -->
            <template v-if="!overlayBelumSiap">
                <div v-if="dokumenTanpaVersi" class="field-hint fh-danger mt-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Dokumen ini <strong>belum punya versi berlaku</strong>, sehingga isi dokumen belum dapat disajikan.
                </div>
                <div v-if="gantiTerkunci" class="field-hint mt-2">
                    <i class="bi bi-lock-fill"></i>
                    Dokumen item ini terkunci pada paket terbit demi menjaga integritas riwayat baca.
                </div>
            </template>

            <SopDokumenPickerModal
                :show="dokPickerOpen"
                :tipe="form.Tipe_Item"
                :dipakai="dokumenDipakai"
                :sekarang="form.Id_SOP_Dokumen || ''"
                @close="dokPickerOpen = false"
                @pilih="onDokumenDipilih"
            />
        </div>

        <!-- Deskripsi Singkat Item -->
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="sop-label mb-0">Deskripsi Tambahan Item</label>
                <span class="text-muted small">Opsional</span>
            </div>
            <input
                v-model="form.Deskripsi_Item"
                class="sop-field"
                placeholder="Catatan atau instruksi khusus untuk item di paket ini…"
            />
            <p v-if="berdokumen" class="field-micro-hint">
                <i class="bi bi-info-circle"></i>
                Berlaku khusus untuk paket ini. Deskripsi inti dokumen tetap mengacu pada data di Master Dokumen.
            </p>
        </div>

        <!-- PARAGRAF -->
        <div v-if="form.Tipe_Item === 'PARAGRAF'" class="mb-3">
            <label class="sop-label">Konten Teks</label>
            <SopRichText v-model="form.Konten_Teks" />
        </div>

        <!-- TAUTAN -->
        <template v-if="form.Tipe_Item === 'TAUTAN'">
            <div class="mb-3">
                <label class="sop-label">URL Tautan <span class="text-danger">*</span></label>
                <input v-model="form.URL_Tautan" class="sop-field" placeholder="https://…" />
            </div>
            <div class="mb-3">
                <label class="sop-label">Label Tombol</label>
                <input v-model="form.Label_Tautan" class="sop-field" placeholder="mis. Buka Portal Referensi" />
            </div>
        </template>

        <!-- DOKUMEN / FORM: HAK AKSES & KEAMANAN.
             FORM ikut sejak 10 Okt 2026: izinnya untuk CONTOH PENGISIAN (PDF).
             Form kosong (lampiran) selalu bisa diunduh, dan contoh non-PDF
             warisan tetap boleh diunduh — lihat SopAksesBerkas::bolehUnduh. -->
        <template v-if="aturAkses">
            <div class="sop-field-group mb-3">
                <div class="sop-field-group-title">
                    <i class="bi bi-shield-check"></i>
                    {{ isForm ? 'Hak Akses Contoh Pengisian' : 'Hak Akses & Keamanan Dokumen' }}
                </div>
                <div class="sop-field-group-body">
                    <!-- Hak Akses Berkas -->
                    <div class="mb-3">
                        <label class="sop-sublabel mb-1">{{ isForm ? 'Izin Contoh Pengisian' : 'Izin Berkas' }}</label>
                        <div class="acc-seg">
                            <button
                                type="button"
                                class="acc-seg-btn"
                                :class="{ active: form.Mode_Akses === 'VIEW_ONLY' || !form.Mode_Akses }"
                                @click="form.Mode_Akses = 'VIEW_ONLY'"
                            >
                                <i class="bi bi-eye"></i>
                                <div class="acc-seg-text">
                                    <span class="acc-seg-title">Hanya Lihat</span>
                                    <span class="acc-seg-desc">Membaca langsung di viewer</span>
                                </div>
                            </button>
                            <button
                                type="button"
                                class="acc-seg-btn"
                                :class="{ active: form.Mode_Akses === 'VIEW_UNDUH' }"
                                @click="pilihBolehUnduh()"
                            >
                                <i class="bi bi-download"></i>
                                <div class="acc-seg-text">
                                    <span class="acc-seg-title">Boleh Unduh</span>
                                    <span class="acc-seg-desc">Dapat mengunduh berkas PDF</span>
                                </div>
                            </button>
                        </div>
                        <p v-if="isForm" class="field-micro-hint mb-0">
                            <i class="bi bi-info-circle"></i>
                            Form kosong (lampiran) selalu bisa diunduh karyawan, apa pun izin di atas.
                        </p>
                    </div>

                    <!-- Watermark Switch -->
                    <div class="cam-toggle" :class="{ 'is-terkunci': !watermarkBolehMenyala }">
                        <div class="cam-toggle-txt">
                            <span class="cam-toggle-title">
                                <i class="bi bi-fingerprint"></i> Watermark Keamanan
                            </span>
                            <!-- Keterangannya ikut berubah, bukan cuma sakelarnya dimatikan:
                                 sakelar mati tanpa sebab terbaca sebagai kerusakan. -->
                            <span class="field-hint">
                                <template v-if="watermarkBolehMenyala">
                                    Sematkan watermark nama pembaca, IP, dan tanggal di lembar PDF.
                                </template>
                                <template v-else>
                                    Tidak tersedia untuk berkas yang boleh diunduh — watermark
                                    hanya disematkan pembaca aman, dan berkas yang diunduh
                                    melewatinya.
                                </template>
                            </span>
                        </div>
                        <el-switch
                            v-model="form.Flag_Watermark"
                            :disabled="!watermarkBolehMenyala"
                            active-text="Aktif"
                            inactive-text="Nonaktif"
                            inline-prompt
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- KUIS -->
        <template v-if="form.Tipe_Item === 'KUIS'">
            <div class="sop-field-group mb-3">
                <div class="sop-field-group-title">
                    <i class="bi bi-patch-question"></i> Konfigurasi Kuis & Ujian
                </div>
                <div class="sop-field-group-body">
                    <p v-if="isNew && dariDokumen" class="field-hint mb-3">
                        <i class="bi bi-link-45deg"></i>
                        Ujian mengikuti versi aktif Dokumen yang dipilih di atas.
                    </p>
                    <div v-else class="mb-3">
                        <label class="sop-label">Paket Ujian (HC Learn) <span class="text-danger">*</span></label>
                        <el-select
                            v-model="form.Id_Master_Ujian"
                            filterable
                            clearable
                            :loading="ujianLoading"
                            placeholder="Pilih paket ujian…"
                            style="width: 100%"
                            @change="onPilihUjian"
                            @visible-change="onOpenUjian"
                        >
                            <el-option v-for="u in ujianOptions" :key="u.value" :label="u.label" :value="u.value" />
                        </el-select>
                        <p v-if="ujianError" class="field-hint fh-danger">
                            <i class="bi bi-exclamation-triangle"></i> {{ ujianErrMsg || 'Gagal memuat daftar ujian.' }}
                            <a href="#" @click.prevent="fetchUjian">Coba lagi</a>
                        </p>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="sop-label">Tipe Ujian</label>
                            <el-select v-model="form.Jenis_Quiz" filterable clearable placeholder="—" style="width: 100%">
                                <el-option label="MICRO" value="MICRO" />
                                <el-option label="PRETEST" value="PRETEST" />
                                <el-option label="POSTTEST" value="POSTTEST" />
                            </el-select>
                        </div>
                        <div class="col-5">
                            <label class="sop-label">
                                Min Lulus <span v-if="form.Id_Master_Ujian" class="text-danger">*</span>
                            </label>
                            <input
                                v-model.number="form.Nilai_Minimum"
                                type="number"
                                min="0"
                                max="100"
                                class="sop-field"
                                :class="{ 'is-invalid': ambangKosong }"
                                placeholder="mis. 70"
                            />
                        </div>
                    </div>
                    <p v-if="ambangKosong" class="field-hint fh-danger mb-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        Batas nilai lulus wajib diisi jika ujian sudah dipilih.
                    </p>

                    <div class="cam-toggle mb-1">
                        <div class="cam-toggle-txt">
                            <span class="cam-toggle-title"><i class="bi bi-camera-video"></i> Pengawasan Kamera</span>
                            <span class="field-hint">Kamera peserta wajib aktif selama pengerjaan kuis.</span>
                        </div>
                        <el-switch
                            v-model="form.Flag_Camera"
                            active-value="Y"
                            inactive-value="T"
                            active-text="Aktif"
                            inactive-text="Nonaktif"
                            inline-prompt
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- Modal Konfirmasi Ganti Dokumen -->
        <SopKonfirmasi
            :show="gantiKonf.buka"
            judul="Ganti Dokumen"
            subjudul="Item ini akan menyajikan dokumen yang berbeda"
            :pesan="gantiKonf.pesan"
            :statistik="gantiKonf.statistik"
            :catatan="gantiKonf.catatan"
            :memuat-statistik="gantiKonf.memuat"
            teks-setuju="Ganti Dokumen"
            ikon-tombol="bi-arrow-left-right"
            ragam="danger"
            @update:show="gantiKonf.buka = $event"
            @setuju="terapkanGantiDokumen"
        />

        <!-- Aturan Kepatuhan & Tampilan (Checkboxes) -->
        <div class="sop-checkbox-card mt-3">
            <HcisCheckbox v-model="form.Flag_Wajib" :label="wajibLabel" size="sm" />
            <HcisCheckbox v-model="form.Flag_Highlight" label="Sorot di ringkasan paket" size="sm" />
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { HcisCheckbox } from '@/components/ui';
import SopRichText from './SopRichText.vue';
import SopDokumenPickerModal from './SopDokumenPickerModal.vue';
import SopKonfirmasi from './SopKonfirmasi.vue';
import { petakanOpsiUjian } from '@/Pages/LMS/SOP/Shared/support/ujian';

/**
 * ItemFields — field editor per tipe item (dipakai di mode tambah & edit).
 * `form` di-mutate langsung (object prop) agar selaras dengan itemForm parent.
 */
export default {
    name: 'ItemFields',
    components: {
        HcisCheckbox,
        SopRichText,
        SopDokumenPickerModal,
        SopKonfirmasi,
    },
    props: {
        form: { type: Object, required: true },
        isNew: { type: Boolean, default: false },
        paketPublished: { type: Boolean, default: false },
        dokumenDipakai: { type: Array, default: () => [] },
        dukungDokumenDraft: { type: Boolean, default: false },
    },
    data() {
        return {
            dokPickerOpen: false,
            gantiKonf: { buka: false, pesan: '', statistik: [], catatan: '', memuat: false, calon: null },
            ujianList: [],
            ujianLoading: false,
            ujianError: false,
            ujianErrMsg: '',
            ujianFetched: false,
        };
    },
    computed: {
        /**
         * Watermark hanya berarti bila berkasnya TIDAK boleh diunduh.
         *
         * Cerminan SopAksesBerkas::watermarkBolehMenyala() di server. Yang
         * mengikat tetap server — layar hanya mencegah admin menyusun keadaan
         * yang nanti diam-diam dinormalkan di penyimpanan, lalu bertanya-tanya
         * ke mana perginya setelan yang barusan ia nyalakan.
         */
        watermarkBolehMenyala() {
            return this.form.Mode_Akses !== 'VIEW_UNDUH';
        },
        isForm() {
            return this.form.Tipe_Item === 'FORM';
        },
        /** Tipe yang izin unduhnya dipilih admin per paket. */
        aturAkses() {
            return ['DOKUMEN', 'FORM'].includes(this.form.Tipe_Item);
        },
        berdokumen() {
            return ['DOKUMEN', 'FORM', 'VIDEO', 'KUIS'].includes(this.form.Tipe_Item);
        },
        dariDokumen() {
            return this.berdokumen;
        },
        overlayBelumSiap() {
            return this.berdokumen && this.isNew && this.paketPublished && !this.dukungDokumenDraft;
        },
        dokumenTanpaVersi() {
            if (!this.berdokumen || this.isNew || !this.form.Id_SOP_Dokumen) return false;
            if (this.form.Dokumen_Punya_Versi != null) return !this.form.Dokumen_Punya_Versi;
            return !this.form.Id_Item_Versi_Aktif;
        },
        gantiTerkunci() {
            return this.berdokumen && !this.isNew && this.paketPublished && this.form.Sudah_Pernah_Terbit !== false;
        },
        paketLainPemakai() {
            return Number(this.form.Paket_Lain_Pemakai || this.form.Jumlah_Paket_Pemakai || 0);
        },
        versiAktifLabel() {
            if (this.form.Versi_String) return `v${this.form.Versi_String}`;
            if (this.form.Dokumen_Versi_Aktif) return `v${this.form.Dokumen_Versi_Aktif}`;
            return '';
        },
        ambangKosong() {
            return (
                this.form.Tipe_Item === 'KUIS' &&
                !!this.form.Id_Master_Ujian &&
                (this.form.Nilai_Minimum === null ||
                    this.form.Nilai_Minimum === undefined ||
                    this.form.Nilai_Minimum === '')
            );
        },
        ujianOptions() {
            const list = this.ujianList.slice();
            if (this.form.Id_Master_Ujian && !list.some((u) => u.value === this.form.Id_Master_Ujian)) {
                list.unshift({
                    value: this.form.Id_Master_Ujian,
                    label: this.form.Nama_Ujian || this.form.Id_Master_Ujian,
                });
            }
            return list;
        },
        wajibLabel() {
            return (
                {
                    KUIS: 'Wajib lulus kuis',
                    PARAGRAF: 'Wajib dibaca',
                    TAUTAN: 'Wajib dibuka',
                }[this.form.Tipe_Item] || 'Wajib diselesaikan karyawan'
            );
        },
    },
    created() {
        if (this.form.Tipe_Item === 'KUIS') this.fetchUjian();
    },
    watch: {
        'form.Tipe_Item'(t) {
            if (t === 'KUIS' && !this.ujianFetched) this.fetchUjian();
        },
    },
    methods: {
        /**
         * Memilih "Boleh Unduh" sekaligus memadamkan watermark.
         *
         * Tanpa ini, item yang tadinya ber-watermark tetap membawa
         * Flag_Watermark menyala di formulir sementara sakelarnya sudah
         * terkunci — admin melihat "Aktif" yang tak bisa ia ubah, lalu server
         * menyimpannya mati. Yang tampil harus sama dengan yang tersimpan.
         */
        pilihBolehUnduh() {
            this.form.Mode_Akses = 'VIEW_UNDUH';
            this.form.Flag_Watermark = false;
        },
        onDokumenDipilih(d) {
            const berganti =
                !!this.form.id && !!this.form.Id_SOP_Dokumen && d.Id_SOP_Dokumen !== this.form.Id_SOP_Dokumen;

            if (!berganti) {
                this.pasangDokumen(d);
                return;
            }

            this.gantiKonf = {
                buka: true,
                calon: d,
                memuat: true,
                statistik: [],
                catatan: '',
                pesan:
                    `Item ini berhenti menyajikan "${this.form.Judul_Item}" dan beralih ke "${d.Judul}". ` +
                    'Penggantiannya berlaku setelah item disimpan.',
            };
            this.hitungDampakGanti(d);
        },
        async hitungDampakGanti(d) {
            const stat = [];
            try {
                const r = await axios.get(`/api/v1/sop/admin/builder/item/${this.form.id}/dampak`);
                const x = r.data?.result || {};
                if (x.progres) {
                    stat.push(
                        { nilai: x.progres, label: 'karyawan ditugaskan' },
                        { nilai: x.selesai, label: 'sudah menyelesaikan' },
                    );
                    this.gantiKonf.catatan =
                        'Penyelesaian mereka menempel pada dokumen yang lama — ' +
                        'setelah diganti, dokumen barulah yang harus mereka baca.';
                }
            } catch (e) {
                this.gantiKonf.catatan = 'Dampak penggantian tak bisa dihitung — lanjutkan dengan hati-hati.';
            }

            const paket = Number(d.Jumlah_Paket_Pemakai || 0);
            if (paket > 0) stat.push({ nilai: paket, label: 'paket lain memakai dokumen baru' });

            this.gantiKonf.statistik = stat;
            this.gantiKonf.memuat = false;
        },
        terapkanGantiDokumen() {
            const d = this.gantiKonf.calon;
            this.gantiKonf.buka = false;
            this.gantiKonf.calon = null;
            if (d) this.pasangDokumen(d);
        },
        pasangDokumen(d) {
            this.form.Id_SOP_Dokumen = d.Id_SOP_Dokumen;
            this.form.Judul_Item = d.Judul;
            this.form.Nomor_SOP_Resmi = d.Nomor_SOP_Resmi;
            this.form.Nama_Departemen = d.Nama_Departemen || '';
            this.form.Nama_Jenis = d.Nama_Jenis || '';
            this.form.Versi_String = d.Versi_String || '';
            this.form.Paket_Lain_Pemakai = Number(d.Jumlah_Paket_Pemakai || 0);

            const punya = d.Punya_Versi_Berlaku ?? d.Id_Versi_Aktif != null;
            this.form.Dokumen_Punya_Versi = !!punya;
        },
        async fetchUjian() {
            if (this.ujianLoading) return;
            this.ujianLoading = true;
            this.ujianError = false;
            this.ujianErrMsg = '';
            try {
                const res = await axios.get('/api/v1/sop/admin/builder/options/ujian');
                /* Pemetaannya milik Shared/support/ujian.js, bukan berkas ini.
                   Salinan lokalnya dulu benar sendirian — dan justru itu yang
                   menyembunyikan bahwa pintu kedua (Wizard Versi) memetakannya
                   dengan cara lain, sampai dropdownnya terisi opsi kosong. */
                this.ujianList = petakanOpsiUjian(res.data?.result ?? res.data?.data);
                this.ujianFetched = true;
            } catch (e) {
                this.ujianError = true;
                this.ujianErrMsg = e.response?.data?.message || 'Gagal memuat daftar ujian.';
            } finally {
                this.ujianLoading = false;
            }
        },
        onOpenUjian(visible) {
            if (visible && !this.ujianFetched) this.fetchUjian();
        },
        onPilihUjian(val) {
            const found = this.ujianList.find((u) => u.value === val);
            this.form.Nama_Ujian = found ? found.label : val ? this.form.Nama_Ujian : null;
            if (!val) this.form.Nama_Ujian = null;
        },
    },
};
</script>

<style scoped>
.item-fields {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

/* ── Rich Document Card ────────────────────────────────────────── */
.dok-rich-card {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    background: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 150ms ease;
}
.dok-rich-card:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
}
.dok-rich-card.dok-terkunci {
    border-style: dashed;
    background: var(--hcis-slate-50);
}

.dok-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.dok-badges {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.dok-badge-tipe {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.dok-badge-nomor {
    display: inline-flex;
    align-items: center;
    font-size: 0.73rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
}
.dok-badge-versi {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: #166534;
    background: var(--hcis-green-50);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    border: 1px solid var(--hcis-green-200);
}

.dok-ganti-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.55rem;
    border: 1px solid var(--border, var(--hcis-slate-300));
    background: var(--hcis-white);
    color: var(--text, var(--hcis-slate-700));
    font-size: 0.74rem;
    font-weight: 600;
    border-radius: 7px;
    cursor: pointer;
    transition: all 120ms ease;
}
.dok-ganti-btn:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}
.dok-kunci-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-100);
    padding: 0.2rem 0.45rem;
    border-radius: 6px;
}

.dok-rich-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text, var(--hcis-slate-900));
    margin: 0 0 0.4rem 0;
    line-height: 1.35;
}

.dok-meta-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.dok-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    padding: 0.12rem 0.4rem;
    border-radius: 5px;
}
.dok-meta-chip.is-shared {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}

/* ── Modern Empty Document Selector CTA ───────────────────────── */
.dok-empty-card {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border: 1.5px dashed var(--hcis-slate-300);
    border-radius: 12px;
    background: var(--hcis-slate-50);
    cursor: pointer;
    text-align: left;
    transition: all 150ms ease;
}
.dok-empty-card:hover {
    border-color: var(--hcis-indigo-500);
    background: #f5f7ff;
}
.dok-empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-size: 1.1rem;
    flex-shrink: 0;
}
.dok-empty-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex-grow: 1;
    min-width: 0;
}
.dok-empty-title {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}
.dok-empty-subtitle {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
.dok-empty-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    font-size: 0.75rem;
    font-weight: 600;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2);
}

/* ── Structured Field Groups ───────────────────────────────────── */
.sop-field-group {
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 12px;
    background: var(--hcis-white);
    overflow: hidden;
}
.sop-field-group-title {
    padding: 0.55rem 0.85rem;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid var(--border-soft, var(--hcis-slate-200));
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.sop-field-group-title .bi {
    color: var(--hcis-indigo-500);
}
.sop-field-group-body {
    padding: 0.75rem 0.85rem;
}
.sop-sublabel {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    display: block;
}

/* ── Segmented Access Button ──────────────────────────────────── */
.acc-seg {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
.acc-seg-btn {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.75rem;
    border: 1.5px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 10px;
    background: var(--hcis-white);
    cursor: pointer;
    text-align: left;
    transition: all 140ms cubic-bezier(0.4, 0, 0.2, 1);
}
.acc-seg-btn .bi {
    font-size: 1.15rem;
    color: var(--hcis-slate-400);
    flex-shrink: 0;
}
.acc-seg-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.acc-seg-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
}
.acc-seg-desc {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
}
.acc-seg-btn:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}
.acc-seg-btn.active {
    border-color: var(--hcis-indigo-500);
    background: #f5f7ff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.acc-seg-btn.active .bi {
    color: var(--hcis-indigo-600);
}
.acc-seg-btn.active .acc-seg-title {
    color: var(--hcis-indigo-700);
}

/* ── Toggle Cards (Watermark & Camera) ────────────────────────── */
.cam-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 10px;
    background: var(--hcis-slate-50);
}
/* Terkunci karena izin unduh menyala — diredupkan, bukan disembunyikan, supaya
   admin tetap melihat setelan itu ada beserta alasan ia tak bisa diubah. */
.cam-toggle.is-terkunci {
    background: var(--hcis-slate-100);
    border-style: dashed;
}
.cam-toggle.is-terkunci .cam-toggle-title {
    opacity: 0.65;
}
.cam-toggle-txt {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.cam-toggle-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.cam-toggle-title .bi {
    color: var(--hcis-indigo-500);
}
.cam-toggle .field-hint {
    margin: 0.1rem 0 0 0;
    font-size: 0.7rem;
}

/* ── Checkbox Container Card ──────────────────────────────────── */
.sop-checkbox-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--border-soft, var(--hcis-slate-200));
    border-radius: 10px;
    background: var(--hcis-slate-50);
    flex-wrap: wrap;
}

/* ── Badges & Micro Hints ─────────────────────────────────────── */
.badge-master-hint {
    font-size: 0.7rem;
    color: var(--hcis-indigo-500);
    font-weight: 600;
}
.field-micro-hint {
    font-size: 0.71rem;
    color: var(--hcis-slate-400);
    margin: 0.25rem 0 0 0;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.field-micro-hint .bi {
    color: var(--hcis-indigo-500);
}

.field-hint {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    margin: 0.35rem 0 0;
}
.field-hint.fh-danger {
    color: var(--hcis-red-700);
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 8px;
    padding: 0.45rem 0.65rem;
}
.field-hint.fh-danger .bi {
    color: var(--hcis-red-500);
}
.field-hint.fh-danger a {
    color: var(--hcis-indigo-600);
    font-weight: 700;
}
</style>
