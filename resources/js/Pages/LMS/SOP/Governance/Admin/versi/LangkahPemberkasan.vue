<template>
    <div class="d-flex flex-column gap-3">
        <!-- ══ 1. CONTEXT NOTIFICATION BANNER ══ -->
        <HcisAlert
            variant="warning"
            appearance="soft"
            icon="file-earmark-arrow-up-fill"
            title="Tahap Pemberkasan &amp; Masa Berlaku"
            :badge="isKuis ? (nilai.Id_Master_Ujian ? 'Kuis Terpilih' : 'Pilih Kuis') : (nilai.Konten_Nama ? 'Berkas Siap' : 'Menunggu Berkas')"
            size="sm"
            glow
            pattern="dots"
        >
            <template v-if="isKuis">
                Tentukan paket modul ujian final HC Learn yang wajib dikerjakan oleh karyawan penerima dokumen, lalu atur rentang masa aktif berlakunya.
            </template>
            <template v-else-if="pakaiLampiran">
                Pilih contoh pengisian berupa PDF dari Library Konten, pasang form kosongnya bila ada, lalu tentukan masa efektivitas berlaku dan jadwal peninjauan ulang berkala.
            </template>
            <template v-else>
                Pilih berkas dokumen operasional final (PDF/Word/Excel) dari Library Konten, lalu tentukan masa efektivitas berlaku dan jadwal peninjauan ulang berkala.
            </template>
        </HcisAlert>

        <!-- ══ 2. CARD LAMPIRAN BERKAS / PAKET KUIS ══ -->
        <HcisCard
            :title="isKuis ? 'Paket Ujian Final HC Learn' : 'Lampiran Berkas Prosedur'"
            description="Isi materi atau berkas operasional yang dibaca oleh karyawan. Dilampirkan setelah pengesahan tuntas."
            :icon="isKuis ? 'patch-question-fill' : 'file-earmark-arrow-up-fill'"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template v-if="ditandai('Id_Master_Konten', 'Id_Master_Ujian')" #actions>
                <HcisBadge variant="warning" size="xs" dot>
                    <i class="bi bi-pencil-fill me-1"></i>Belum Disimpan
                </HcisBadge>
            </template>

            <!-- Mode A: KUIS DENGAN HCIS SELECT COMPONENT -->
            <template v-if="isKuis">
                <HcisField
                    label="Pilih Paket Ujian HC Learn"
                    description="Pilih modul ujian kuis HC Learn yang harus dikerjakan oleh karyawan penerima dokumen."
                    required
                    size="sm"
                    :error="ujianError"
                >
                    <HcisSelect
                        :model-value="nilai.Id_Master_Ujian"
                        placeholder="— Pilih Paket Ujian HC Learn —"
                        size="sm"
                        leading-icon="patch-question-fill"
                        :loading="ujianLoading"
                        :disabled="ujianLoading || mati('Id_Master_Ujian')"
                        class="w-100"
                        @update:model-value="ubah('Id_Master_Ujian', $event)"
                    >
                        <HcisSelectOption
                            v-for="u in ujianList"
                            :key="u.value"
                            :value="u.value"
                            :label="u.label"
                            icon="journal-check"
                        />
                    </HcisSelect>
                </HcisField>
            </template>

            <!-- Mode B: DOKUMEN / PDF -->
            <template v-else>
                <!-- File Selected Widget -->
                <div
                    v-if="nilai.Konten_Nama"
                    class="sop-file-widget p-3 rounded-2 border d-flex align-items-center justify-content-between flex-wrap gap-2"
                >
                    <div class="d-flex align-items-center text-truncate me-2">
                        <div class="sop-file-icon-box me-3">
                            <i class="bi bi-file-earmark-pdf-fill text-primary fs-3"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="fw-bold text-dark text-truncate small fs-6">{{ nilai.Konten_Nama }}</div>
                            <div class="text-muted mt-1 d-flex align-items-center gap-1.5 flex-wrap" style="font-size: 0.75rem;">
                                <HcisBadge label="PDF / Berkas" variant="brand" size="xs" />
                                <span><i class="bi bi-check-circle-fill text-success me-1"></i>Terhubung ke Library Konten</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <HcisButton
                            v-if="nilai.Id_Master_Konten"
                            variant="primary"
                            size="sm"
                            leading-icon="eye-fill"
                            label="Pratinjau Berkas"
                            title="Buka pratinjau berkas dokumen"
                            :loading="memuatBerkas"
                            @click="bukaPratinjau"
                        />
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            leading-icon="folder2-open"
                            label="Ganti Berkas"
                            :disabled="mati('Id_Master_Konten')"
                            @click="$emit('pilih-berkas')"
                        />
                    </div>
                </div>

                <!-- Dropzone / Selector Trigger -->
                <HcisUpload
                    v-else
                    custom-trigger
                    layout="dropzone"
                    variant="outline"
                    sentiment="brand"
                    class="w-100"
                    @trigger="!mati('Id_Master_Konten') && $emit('pilih-berkas')"
                    @click="!mati('Id_Master_Konten') && $emit('pilih-berkas')"
                >
                    <template #dragger>
                        <div class="hcis-upload__drop-content py-4 text-center cursor-pointer">
                            <div class="hcis-upload__icon-badge mb-2">
                                <HcisIcon name="cloud-arrow-up-fill" size="md" />
                            </div>
                            <div class="hcis-upload__title fw-bold text-dark fs-6">Pilih Berkas Lampiran Prosedur</div>
                            <!-- Contoh pengisian FORM wajib PDF (10 Okt 2026); Word/Excel
                                 untuk diisi karyawan masuk kartu Form Kosong di bawah. -->
                            <div v-if="pakaiLampiran" class="hcis-upload__hint text-muted small mt-1">
                                Contoh pengisian wajib PDF, maks. 50MB. Form kosong (Word/Excel) dipasang di bawah.
                            </div>
                            <div v-else class="hcis-upload__hint text-muted small mt-1">
                                Format dokumen: PDF, Word (.docx), Excel (.xlsx) maks. 50MB
                            </div>
                            <div class="mt-3">
                                <HcisButton
                                    variant="primary"
                                    size="sm"
                                    leading-icon="collection"
                                    label="Buka Library Konten"
                                    :disabled="mati('Id_Master_Konten')"
                                    @click.stop="$emit('pilih-berkas')"
                                />
                            </div>
                        </div>
                    </template>
                </HcisUpload>

                <!-- PERINGATAN SAAT MEMILIH, BUKAN SAAT MEMBUKA.
                     Ia ada untuk satu keputusan: "berkas ini sudah dipakai
                     dokumen lain, yakin tetap memakainya?" Keputusan itu diambil
                     SEKALI, saat berkasnya dipilih. Menampilkannya lagi setiap
                     kali versi dibuka berarti menanyakan hal yang sudah dijawab
                     — dan pemberitahuan yang selalu ada berhenti dibaca, persis
                     yang komentar di komponennya sendiri ingin hindari.

                     Dua syaratnya: berkasnya baru saja DIGANTI di sesi ini
                     (ditandai), dan berkasnya memang masih boleh diganti. Pada
                     versi terbit keduanya gagal — dan menanyakan pilihan yang
                     tak bisa diambil hanya membuat admin kehilangan konteks. -->
                <SopKontenTerpakai
                    v-if="nilai.Id_Master_Konten && ditandai('Id_Master_Konten') && !mati('Id_Master_Konten')"
                    :konten="nilai.Id_Master_Konten"
                    :kecuali="idDokumen"
                    class="mt-2.5"
                />
            </template>
        </HcisCard>

        <!-- ══ 2b. FORM KOSONG (DOKUMEN FORM) ══
             Berkas di atas adalah contoh pengisian; yang ini diunduh karyawan
             untuk diisi sendiri. Hanya untuk tipe yang profilnya memakai blok
             lampiran — keputusan itu milik wizard (PROFIL_TIPE), bukan di sini. -->
        <KartuLampiranForm
            v-if="pakaiLampiran && !isKuis"
            :model-value="nilai.Lampiran || []"
            :terkunci="mati('Lampiran')"
            @update:model-value="ubah('Lampiran', $event)"
            @tambah="$emit('pilih-lampiran')"
        />

        <!-- ══ 3. CARD MASA BERLAKU & PENINJAUAN ULANG ══ -->
        <HcisCard
            title="Masa Berlaku &amp; Jadwal Peninjauan Ulang"
            description="Rentang tanggal efektivitas berlakunya versi ini, batas tanggal kedaluwarsa, serta jadwal peninjauan (review) berkala."
            icon="calendar-range"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template v-if="ditandai('Tanggal_Berlaku', 'Tanggal_Kadaluarsa', 'Tanggal_Review_Berikutnya')" #actions>
                <HcisBadge variant="warning" size="xs" dot>
                    <i class="bi bi-pencil-fill me-1"></i>Belum Disimpan
                </HcisBadge>
            </template>

            <div class="sop-masa-berlaku__grid">
                <div>
                    <label class="form-label fw-bold small text-dark mb-1">
                        Tanggal Mulai Berlaku <span class="text-danger">*</span>
                    </label>
                    <HcisDatePicker
                        :model-value="nilai.Tanggal_Berlaku"
                        placeholder="Pilih tanggal mulai berlaku"
                        size="sm"
                        clearable
                        :disabled="terkunci"
                        class="w-100"
                        @update:model-value="ubah('Tanggal_Berlaku', $event)"
                    />
                    <div class="sop-masa-berlaku__nota">
                        <i class="bi bi-info-circle me-1"></i>Dua tanggal di sebelah dihitung otomatis dari sini.
                    </div>
                </div>

                <div>
                    <label class="form-label fw-bold small text-dark mb-1">
                        Tanggal Kedaluwarsa <span class="text-danger">*</span>
                    </label>
                    <HcisDatePicker
                        :model-value="nilai.Tanggal_Kadaluarsa"
                        placeholder="Pilih tanggal kedaluwarsa"
                        size="sm"
                        clearable
                        :disabled="terkunci"
                        class="w-100"
                        @update:model-value="ubah('Tanggal_Kadaluarsa', $event)"
                    />
                    <div class="sop-masa-berlaku__nota">
                        <template v-if="disentuh.Tanggal_Kadaluarsa">
                            Diisi kustom —
                            <button
                                type="button"
                                class="sop-masa-berlaku__balik"
                                :disabled="terkunci || !nilai.Tanggal_Berlaku"
                                @click="ikutiBakuLagi('Tanggal_Kadaluarsa')"
                            >
                                ikuti baku lagi ({{ masaBerlakuBulan }} bln)
                            </button>
                        </template>
                        <template v-else>Baku {{ masaBerlakuBulan }} bulan — boleh diubah.</template>
                    </div>
                </div>

                <div>
                    <label class="form-label fw-bold small text-dark mb-1">
                        Jadwal Review Berikutnya <span class="text-danger">*</span>
                    </label>
                    <HcisDatePicker
                        :model-value="nilai.Tanggal_Review_Berikutnya"
                        placeholder="Pilih jadwal review"
                        size="sm"
                        clearable
                        :disabled="terkunci"
                        class="w-100"
                        @update:model-value="ubah('Tanggal_Review_Berikutnya', $event)"
                    />
                    <div class="sop-masa-berlaku__nota">
                        <template v-if="disentuh.Tanggal_Review_Berikutnya">
                            Diisi kustom —
                            <button
                                type="button"
                                class="sop-masa-berlaku__balik"
                                :disabled="terkunci || !nilai.Tanggal_Berlaku"
                                @click="ikutiBakuLagi('Tanggal_Review_Berikutnya')"
                            >
                                ikuti baku lagi ({{ siklusReviewBulan }} bln)
                            </button>
                        </template>
                        <template v-else>Baku {{ siklusReviewBulan }} bulan — boleh diubah.</template>
                    </div>
                </div>
            </div>

            <p v-if="galatUrutan" class="text-danger mb-0 mt-2.5" style="font-size: 0.75rem;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ galatUrutan }}
            </p>
        </HcisCard>

        <!-- ══ 4. HCIS FILE VIEWER UNTUK PRATINJAU DOKUMEN ══
             Berkasnya DIRESOLUSI SERVER, tak dirakit di sini. Alamat yang
             dikarang layar ("/api/v1/sop/konten/{id}/file") menunjuk rute yang
             tak pernah ada, dan berkas SOP memang tak bisa dialamati langsung:
             ia duduk di GCS dan hanya terbuka lewat signed URL bermasa 15 menit. -->
        <HcisFileViewer
            v-if="berkas"
            v-model="viewerBuka"
            :file="berkas"
            @close="tutupPratinjau"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { hcisMessage } from '@/components/ui';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisAlert from '@/components/ui/HcisAlert.vue';
import HcisDatePicker from '@/components/ui/HcisDatePicker.vue';
import HcisField from '@/components/ui/HcisField.vue';
import HcisSelect from '@/components/ui/HcisSelect.vue';
import HcisSelectOption from '@/components/ui/HcisSelectOption.vue';
import HcisUpload from '@/components/ui/HcisUpload.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import HcisFileViewer from '@/components/ui/HcisFileViewer.vue';
import SopKontenTerpakai from '@/Pages/LMS/SOP/Shared/components/ui/SopKontenTerpakai.vue';
import KartuLampiranForm from './KartuLampiranForm.vue';

/**
 * Langkah 4 — Pemberkasan dengan HcisSelect & HcisFileViewer.
 */
export default {
    name: 'LangkahPemberkasan',
    components: {
        HcisCard,
        HcisBadge,
        HcisAlert,
        HcisButton,
        HcisDatePicker,
        HcisField,
        HcisSelect,
        HcisSelectOption,
        HcisUpload,
        HcisIcon,
        HcisFileViewer,
        SopKontenTerpakai,
        KartuLampiranForm,
    },
    props: {
        modelValue: { type: Object, required: true },
        terkunci: { type: Boolean, default: false },
        /* Izin per field dari server. Baku "boleh" supaya langkah yang belum
           menerima muatannya tak mengunci apa pun — mengunci saat ragu membuat
           halaman yang lambat memuat terlihat rusak. */
        bolehUbah: { type: Function, default: () => true },
        berubah: { type: Array, default: () => [] },
        isKuis: { type: Boolean, default: false },
        /** Tipe dokumen memakai blok lampiran (form kosong) — FORM saja. */
        pakaiLampiran: { type: Boolean, default: false },
        idDokumen: { type: [String, Number], default: null },
        ujianList: { type: Array, default: () => [] },
        ujianLoading: { type: Boolean, default: false },
        ujianError: { type: String, default: '' },
        masaBerlakuBulan: { type: Number, default: 24 },
        siklusReviewBulan: { type: Number, default: 12 },
    },
    emits: ['update:modelValue', 'pilih-berkas', 'pilih-lampiran'],
    data() {
        const v = this.modelValue || {};

        return {
            viewerBuka: false,
            memuatBerkas: false,
            berkas: null,
            disentuh: {
                Tanggal_Kadaluarsa: Boolean(v.Tanggal_Kadaluarsa),
                Tanggal_Review_Berikutnya: Boolean(v.Tanggal_Review_Berikutnya),
            },
        };
    },
    computed: {
        nilai() {
            return this.modelValue || {};
        },
        galatUrutan() {
            const b = this.nilai.Tanggal_Berlaku;
            const k = this.nilai.Tanggal_Kadaluarsa;
            const r = this.nilai.Tanggal_Review_Berikutnya;
            if (!b) return '';
            if (k && k < b) return 'Tanggal kedaluwarsa tidak boleh lebih awal dari tanggal mulai berlaku.';
            if (r && r < b) return 'Jadwal review berikutnya tidak boleh lebih awal dari tanggal mulai berlaku.';
            if (r && k && r > k) return 'Jadwal review berikutnya umumnya sebelum tanggal kedaluwarsa.';
            return '';
        },
    },
    watch: {
        /* Berkas yang sudah diresolusi menempel pada SATU konten. Begitu admin
           menekan "Ganti Berkas", payload lama harus dibuang — kalau tidak,
           pratinjau berikutnya menampilkan berkas sebelumnya dengan penuh
           percaya diri. */
        'nilai.Id_Master_Konten'() {
            this.berkas = null;
            this.viewerBuka = false;
        },
    },
    methods: {
        /** Terkunci karena tahapnya, ATAU karena keadaan versi tak mengizinkan field ini. */
        mati(field) {
            return this.terkunci || !this.bolehUbah(field);
        },
        /* Signed URL GCS hanya berumur 15 menit, jadi ia diminta SAAT dibuka —
           bukan disiapkan saat langkah ini dirender. Wizard yang ditinggal
           setengah jam akan memegang tautan mati bila disiapkan di muka. */
        async bukaPratinjau() {
            if (!this.nilai.Id_Master_Konten) return;

            this.memuatBerkas = true;
            try {
                const r = await axios.get(
                    `/api/v1/sop/admin/dokumen/konten/${this.nilai.Id_Master_Konten}/berkas`);
                const k = r.data?.result || null;
                if (!k?.url) throw new Error();

                this.berkas = {
                    url: k.url,
                    name: k.nama_berkas || this.nilai.Konten_Nama || 'Berkas',
                    /* Tipe dari server, bukan 'application/pdf' yang dipaku:
                       berkas SOP juga bisa docx atau xlsx, dan penampil yang
                       disuruh membaca PDF atas berkas Word menampilkan sampah. */
                    type: k.mime || '',
                };
                this.viewerBuka = true;
            } catch (e) {
                this.berkas = null;
                hcisMessage.error(
                    e?.response?.data?.message || 'Gagal memuat berkas untuk pratinjau.');
            } finally {
                this.memuatBerkas = false;
            }
        },
        tutupPratinjau() {
            this.viewerBuka = false;
        },
        ditandai(...kunci) {
            return kunci.some((k) => this.berubah.includes(k));
        },
        ubah(kunci, v) {
            const baru = { ...this.nilai, [kunci]: v };

            if (kunci === 'Tanggal_Berlaku' && v) {
                if (!this.disentuh.Tanggal_Kadaluarsa) {
                    baru.Tanggal_Kadaluarsa = this.tambahBulan(v, this.masaBerlakuBulan);
                }
                if (!this.disentuh.Tanggal_Review_Berikutnya) {
                    baru.Tanggal_Review_Berikutnya = this.tambahBulan(v, this.siklusReviewBulan);
                }
            } else if (kunci === 'Tanggal_Kadaluarsa') {
                this.disentuh.Tanggal_Kadaluarsa = true;
            } else if (kunci === 'Tanggal_Review_Berikutnya') {
                this.disentuh.Tanggal_Review_Berikutnya = true;
            }

            this.$emit('update:modelValue', baru);
        },
        ikutiBakuLagi(kunci) {
            this.disentuh[kunci] = false;
            const b = this.nilai.Tanggal_Berlaku;
            if (!b) return;
            const bulan = kunci === 'Tanggal_Kadaluarsa' ? this.masaBerlakuBulan : this.siklusReviewBulan;
            this.ubah(kunci, this.tambahBulan(b, bulan));
        },
        /**
         * Tambah bulan tanpa melewati zona waktu.
         *
         * `new Date('2026-03-15T00:00:00')` dibaca sebagai waktu LOKAL, lalu
         * `toISOString()` mengubahnya ke UTC — di WIB (UTC+7) hasilnya mundur
         * tujuh jam dan tanggalnya jadi 14, sehari SEBELUM yang diminta. Masa
         * berlaku yang meleset sehari terlihat seperti salah ketik admin, dan
         * karena itu tak pernah dicurigai sebagai cacat.
         *
         * Dihitung dengan komponen tanggal apa adanya: tak ada jam, tak ada
         * zona, jadi tak ada yang bisa bergeser.
         */
        tambahBulan(iso, n) {
            if (!iso) return '';

            const [th, bl, hr] = String(iso).slice(0, 10).split('-').map(Number);
            if (!th || !bl || !hr) return '';

            /* Hari sengaja disetel 1 dulu: menambah bulan pada tanggal 31
               meluber ke bulan berikutnya (31 Jan + 1 bulan = 3 Mar). Setelah
               bulannya benar, harinya dijepit ke hari terakhir bulan itu. */
            const d = new Date(th, bl - 1, 1);
            d.setMonth(d.getMonth() + Number(n));

            const hariTerakhir = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
            d.setDate(Math.min(hr, hariTerakhir));

            const p2 = (x) => String(x).padStart(2, '0');

            return `${d.getFullYear()}-${p2(d.getMonth() + 1)}-${p2(d.getDate())}`;
        },
    },
};
</script>

<style scoped>
.sop-file-widget {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200) !important;
}

.sop-file-icon-box {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    border-radius: var(--hcis-radius-md, 8px);
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.sop-masa-berlaku__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 48rem) {
    .sop-masa-berlaku__grid {
        grid-template-columns: 1fr;
    }
}

.sop-masa-berlaku__nota {
    font-size: 0.725rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin-top: 0.35rem;
}

.sop-masa-berlaku__balik {
    background: none;
    border: 0;
    padding: 0;
    color: var(--hcis-primary, var(--hcis-blue-600));
    text-decoration: underline;
    font-size: inherit;
    cursor: pointer;
}

.sop-masa-berlaku__balik:hover:not(:disabled) {
    color: var(--hcis-primary-hover, #1d4ed8);
}
</style>
