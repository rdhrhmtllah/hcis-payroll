<template>
    <div class="d-flex flex-column gap-3">
        <!-- ══ 1. CONTEXT NOTIFICATION BANNER ══ -->
        <HcisAlert
            v-if="!idVersi"
            variant="warning"
            appearance="soft"
            icon="exclamation-triangle-fill"
            title="Draft Versi Belum Disimpan"
            size="sm"
            glow
            pattern="dots"
        >
            Simpan formulir Pengajuan terlebih dahulu agar ID versi terdaftar. Setelah versi tersimpan, catatan review mutu dapat ditambahkan secara fleksibel.
        </HcisAlert>

        <HcisAlert
            v-else
            variant="purple"
            appearance="soft"
            icon="chat-square-quote-fill"
            title="Tahap Evaluasi &amp; Review Mutu"
            :badge="`${baris.length} Putaran Tercatat`"
            size="sm"
            pattern="dots"
        >
            Dokumentasikan setiap siklus review penjaminan mutu, catatan evaluasi prosedur, dan lampiran bukti pemeriksaan sebelum melanjutkan ke tahap pengesahan pejabat.
        </HcisAlert>

        <!-- ══ 2. MASTER REVIEW CARD ══ -->
        <HcisCard
            title="Catatan &amp; Riwayat Putaran Review Mutu"
            description="Dapat mencatat lebih dari satu putaran review lengkap dengan linimasa tanggal, catatan evaluasi, dan lampiran gambar."
            icon="chat-square-text"
            variant="outlined"
            sentiment="primary"
            size="sm"
        >
            <template #actions>
                <HcisButton
                    variant="primary"
                    size="xs"
                    leading-icon="plus-lg"
                    label="Tambah Putaran Review"
                    :disabled="menyimpan || terkunci || !idVersi"
                    @click="tambah"
                />
            </template>

            <!-- Loading State -->
            <div v-if="memuat" class="text-center py-5">
                <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                <div class="text-muted small fw-medium">Memuat riwayat review mutu…</div>
            </div>

            <!-- Empty State -->
            <HcisEmptyState
                v-else-if="!baris.length"
                icon="chat-square-text"
                title="Belum Ada Catatan Review"
                description="Tambahkan putaran review beserta tanggal dan hasil evaluasinya — atau lanjutkan saja ke Pengesahan jika review telah tuntas tanpa catatan."
                variant="flat"
                sentiment="neutral"
                size="sm"
                class="py-4"
            >
                <template #actions>
                    <HcisButton
                        v-if="idVersi && !terkunci"
                        variant="secondary"
                        size="xs"
                        leading-icon="plus-lg"
                        label="Tambah Putaran Review Pertama"
                        @click="tambah"
                    />
                </template>
            </HcisEmptyState>

            <!-- Review Rounds List -->
            <div v-else class="d-flex flex-column gap-3">
                <div
                    v-for="(r, i) in baris"
                    :key="r.kunci"
                    class="sop-review-card p-3 rounded-3 border"
                >
                    <!-- Round Header -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2.5 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="sop-round-badge">
                                <i class="bi bi-chat-left-dots-fill me-1"></i>
                                <span>Putaran #{{ r.Review_Ke }}</span>
                            </div>
                            <span v-if="r.Tanggal_Review" class="text-muted text-xs">
                                · {{ formatTanggalLabel(r.Tanggal_Review) }}
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center gap-1.5">
                                <span class="text-muted text-xs fw-bold d-none d-sm-inline">Tanggal Review:</span>
                                <HcisDatePicker
                                    :model-value="r.Tanggal_Review"
                                    placeholder="Pilih tanggal"
                                    size="sm"
                                    clearable
                                    :disabled="terkunci"
                                    style="width: 11.5rem;"
                                    @update:model-value="ubahBaris(i, 'Tanggal_Review', $event)"
                                />
                            </div>

                            <HcisButton
                                variant="danger-quiet"
                                size="xs"
                                leading-icon="trash"
                                label="Hapus"
                                title="Hapus putaran review ini"
                                :disabled="terkunci"
                                @click="hapus(i)"
                            />
                        </div>
                    </div>

                    <!-- Round Body: Rich Text Editor -->
                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                            <span>Hasil Evaluasi / Catatan Temuan Mutu</span>
                            <span class="text-muted text-xs">Tuliskan detail perbaikan atau catatan penting</span>
                        </label>
                        <!-- TERKUNCI: TEKSNYA SAJA, BUKAN EDITOR YANG DIMATIKAN.
                             Percobaan pertama mematikan Quill di tempat, dan itu
                             keliru — editor yang dilumpuhkan tetap membawa serta
                             toolbar dan lapisan penangkap klik miliknya, dan
                             lapisan itu menelan klik pada apa pun di sekitarnya.
                             Yang dibutuhkan pada arsip memang bukan editor mati,
                             melainkan bacaan. -->
                        <div v-if="terkunci" class="sop-review-baca" v-html="bacaan(r.Hasil_Review)"></div>

                        <SopRichText
                            v-else
                            :model-value="r.Hasil_Review"
                            placeholder="Tuliskan poin-poin evaluasi, temuan verifikasi lapangan, atau rekomendasi perbaikan prosedur di sini…"
                            @update:model-value="ubahBaris(i, 'Hasil_Review', $event)"
                        />
                    </div>

                    <!-- Round Footer: Attachments Gallery -->
                    <div class="pt-2 border-top">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <label class="sop-lampiran-trigger" :class="{ disabled: terkunci }">
                                    <i class="bi bi-cloud-arrow-up-fill me-1 text-primary"></i>
                                    <span>Unggah Lampiran Bukti</span>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                        multiple
                                        hidden
                                        :disabled="terkunci"
                                        @change="pilihLampiran($event, i)"
                                    />
                                </label>
                                <span class="text-muted text-xs">
                                    Format: PNG, JPG, WEBP maks. 2 MB per gambar (maks. 10 lampiran)
                                </span>
                            </div>

                            <span v-if="r.Lampiran.length" class="badge bg-light text-dark border text-xs">
                                <i class="bi bi-paperclip me-1"></i>{{ r.Lampiran.length }} Lampiran Terlampir
                            </span>
                        </div>

                        <!-- Gallery Grid with HcisFileViewer Triggers -->
                        <div v-if="r.Lampiran.length" class="d-flex flex-wrap gap-2 mt-2">
                            <div
                                v-for="(l, j) in r.Lampiran"
                                :key="j"
                                class="sop-lampiran-thumb"
                                :title="`Klik untuk melihat pratinjau ${l.Nama_Berkas || 'Lampiran Bukti'}`"
                                @click="bukaViewer(i, j)"
                            >
                                <img :src="l.Url || l.Berkas" :alt="l.Nama_Berkas || 'Lampiran'" />
                                <div class="sop-lampiran-overlay">
                                    <div class="d-flex align-items-center justify-content-between w-100">
                                        <span class="sop-lampiran-name text-truncate me-1">{{ l.Nama_Berkas || 'Bukti' }}</span>
                                        <!-- DISEMBUNYIKAN, bukan sekadar dimatikan.
                                             Tombol ini tak punya gaya :disabled sama
                                             sekali — ia tetap merah dan tetap menggelap
                                             saat di-hover walau tak bisa ditekan, jadi
                                             ia menjanjikan tindakan yang tak ada. Pada
                                             versi arsip memang tak ada yang perlu
                                             dihapus; yang tak bisa dilakukan lebih baik
                                             tak ditawarkan sama sekali daripada
                                             ditawarkan lalu diam saat ditekan. -->
                                        <button
                                            v-if="!terkunci"
                                            type="button"
                                            class="sop-lampiran-del"
                                            title="Hapus gambar ini"
                                            @click.stop="hapusLampiran(i, j)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="sop-lampiran-preview-hint">
                                        <i class="bi bi-arrows-fullscreen me-1"></i>Pratinjau
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="r.galat" class="text-danger mb-0 mt-2 small">
                            <i class="bi bi-exclamation-circle-fill me-1"></i>{{ r.galat }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer Toolbar -->
            <template #footer>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span v-if="galat" class="text-danger text-xs">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ galat }}
                    </span>
                    <span v-else-if="!adaPerubahan" class="text-success text-xs">
                        <i class="bi bi-check2-circle me-1"></i>Seluruh catatan review tersimpan aman.
                    </span>
                    <span v-else class="text-warning text-xs">
                        <i class="bi bi-pencil-fill me-1"></i>Terdapat catatan review yang belum disimpan.
                    </span>

                    <HcisButton
                        v-if="adaPerubahan"
                        variant="secondary"
                        size="sm"
                        leading-icon="bookmark-check"
                        :loading="menyimpan"
                        :disabled="!idVersi || terkunci"
                        title="Simpan sementara catatan review"
                        label="Simpan Review Sementara"
                        @click="$emit('simpan-sementara')"
                    />
                </div>
            </template>
        </HcisCard>

        <!-- ══ 3. HCIS FILE VIEWER COMPONENT (MODAL PRATINJAU DOKUMEN & GAMBAR) ══ -->
        <HcisFileViewer
            v-model="viewerBuka"
            :files="viewerFiles"
            :initial-index="viewerIndex"
            @close="viewerBuka = false"
        />
    </div>
</template>

<script>
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisAlert from '@/components/ui/HcisAlert.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisDatePicker from '@/components/ui/HcisDatePicker.vue';
import HcisEmptyState from '@/components/ui/HcisEmptyState.vue';
import HcisFileViewer from '@/components/ui/HcisFileViewer.vue';
import SopRichText from '@/Pages/LMS/SOP/Shared/components/ui/SopRichText.vue';
import { sanitasiSop, sanitasiSopAtau } from '@/utils/sopHtml';

const MAKS_BYTE = 2 * 1024 * 1024;
const MAKS_LAMPIRAN = 10;

/**
 * Langkah 2 — Review Mutu dengan HcisFileViewer.
 */
export default {
    name: 'LangkahReview',
    components: {
        HcisCard,
        HcisAlert,
        HcisButton,
        HcisDatePicker,
        HcisEmptyState,
        HcisFileViewer,
        SopRichText,
    },
    props: {
        idVersi: { type: String, default: null },
        terkunci: { type: Boolean, default: false },
        baris: { type: Array, default: () => [] },
        memuat: { type: Boolean, default: false },
        menyimpan: { type: Boolean, default: false },
        galat: { type: String, default: '' },
        adaPerubahan: { type: Boolean, default: false },
    },
    emits: ['update:baris', 'simpan-sementara'],
    data() {
        return {
            viewerBuka: false,
            viewerFiles: [],
            viewerIndex: 0,
        };
    },
    methods: {
        /**
         * Catatan review sebagai BACAAN, bukan isian.
         *
         * Disanitasi ulang saat ditampilkan meski sudah disanitasi saat
         * disimpan: yang tersimpan bisa berasal dari jalur lain, dan v-html
         * atas isi yang dipercaya begitu saja adalah cara paling murah
         * membuka celah. Daftar tag mengikuti SopPreviewItem — satu bentuk
         * bacaan untuk satu modul.
         */
        bacaan(html) {
            return sanitasiSopAtau(
                html,
                '<p class="sop-review-kosong">Tak ada catatan pada putaran ini.</p>',
            );
        },
        formatTanggalLabel(v) {
            if (!v) return '';
            const d = new Date(`${String(v).slice(0, 10)}T00:00:00`);
            if (Number.isNaN(d.getTime())) return String(v);

            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },
        bukaViewer(i, j) {
            const lampiranList = this.baris[i]?.Lampiran || [];
            this.viewerFiles = lampiranList.map((l) => ({
                url: l.Url || l.Berkas,
                name: l.Nama_Berkas || 'Lampiran Bukti Review',
                size: l.Ukuran_Byte || 0,
                type: l.Tipe_Berkas || 'image/jpeg',
            }));
            this.viewerIndex = j;
            this.viewerBuka = true;
        },
        salin() {
            return this.baris.map((r) => ({ ...r, Lampiran: [...(r.Lampiran || [])] }));
        },
        pancarkan(baris) {
            this.$emit('update:baris', baris);
        },
        ubahBaris(i, kunci, nilai) {
            const b = this.salin();
            b[i][kunci] = nilai;
            this.pancarkan(b);
        },
        tambah() {
            const b = this.salin();
            const tertinggi = b.reduce((m, r) => Math.max(m, Number(r.Review_Ke) || 0), 0);
            b.push({
                kunci: `baru-${Date.now()}-${b.length}`,
                Review_Ke: tertinggi + 1,
                Tanggal_Review: '',
                Hasil_Review: '',
                Lampiran: [],
                galat: '',
            });
            this.pancarkan(b);
        },
        hapus(i) {
            const b = this.salin();
            b.splice(i, 1);
            this.pancarkan(b);
        },
        hapusLampiran(i, j) {
            const b = this.salin();
            b[i].Lampiran.splice(j, 1);
            this.pancarkan(b);
        },
        pilihLampiran(e, i) {
            const berkas = Array.from(e.target.files || []);
            e.target.value = '';
            if (!berkas.length) return;

            const b = this.salin();
            b[i].galat = '';
            const sisa = [];

            for (const f of berkas) {
                if (f.size > MAKS_BYTE) {
                    b[i].galat = `"${f.name}" berukuran ${(f.size / 1024 / 1024).toFixed(1)} MB, melebihi batas 2 MB.`;
                    continue;
                }
                if (b[i].Lampiran.length + sisa.length >= MAKS_LAMPIRAN) {
                    b[i].galat = `Maksimal ${MAKS_LAMPIRAN} lampiran per review.`;
                    break;
                }
                sisa.push(f);
            }

            this.pancarkan(b);
            if (!sisa.length) return;

            for (const f of sisa) {
                const reader = new FileReader();
                reader.onload = () => {
                    const kini = this.salin();
                    if (!kini[i]) return;
                    kini[i].Lampiran.push({
                        Berkas: String(reader.result),
                        Url: String(reader.result),
                        Nama_Berkas: f.name,
                        Tipe_Berkas: f.type,
                        Ukuran_Byte: f.size,
                    });
                    this.pancarkan(kini);
                };
                reader.onerror = () => {
                    const kini = this.salin();
                    if (!kini[i]) return;
                    kini[i].galat = `"${f.name}" tidak bisa dibaca.`;
                    this.pancarkan(kini);
                };
                reader.readAsDataURL(f);
            }
        },
    },
};
</script>

<style scoped>
/* Bacaan catatan review pada versi terkunci — sengaja MIRIP kertas, bukan
   mirip isian: tak ada bingkai fokus, tak ada kursor teks, tak ada yang
   mengundang diketik. */
.sop-review-baca {
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-800);
    font-size: 0.8125rem;
    line-height: 1.6;
}

.sop-review-baca :deep(p:last-child) {
    margin-bottom: 0;
}

.sop-review-baca :deep(.sop-review-kosong) {
    margin: 0;
    color: var(--hcis-slate-500);
    font-style: italic;
}

/* ══ Review Card Block ══ */
.sop-review-card {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: border-color 0.15s ease;
}

.sop-review-card:hover {
    border-color: var(--hcis-slate-300) !important;
}

.sop-round-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-100);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.2rem 0.65rem;
}

/* ══ Lampiran Trigger & Gallery ══ */
.sop-lampiran-trigger {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: var(--hcis-radius-sm, 6px);
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-700);
    background: var(--hcis-white);
    border: 1.5px dashed var(--hcis-slate-300);
    cursor: pointer;
    transition: all 0.15s ease;
}

.sop-lampiran-trigger:hover:not(.disabled) {
    background: #eff6ff;
    border-color: var(--hcis-blue-500);
    color: #1d4ed8;
}

.sop-lampiran-trigger.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sop-lampiran-thumb {
    position: relative;
    width: 5.75rem;
    height: 5.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
    border: 1px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.sop-lampiran-thumb:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px -2px rgba(15, 23, 42, 0.12);
}

.sop-lampiran-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sop-lampiran-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.3) 0%, rgba(15, 23, 42, 0.85) 100%);
    opacity: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.3rem;
    transition: opacity 0.15s ease;
}

.sop-lampiran-thumb:hover .sop-lampiran-overlay {
    opacity: 1;
}

.sop-lampiran-name {
    font-size: 0.65rem;
    color: var(--hcis-white);
    font-weight: 600;
}

.sop-lampiran-del {
    width: 1.4rem;
    height: 1.4rem;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 4px;
    background: var(--hcis-red-500);
    color: var(--hcis-white);
    font-size: 0.75rem;
    cursor: pointer;
    transition: background-color 0.15s ease;
    flex-shrink: 0;
}

.sop-lampiran-del:hover {
    background: var(--hcis-red-600);
}

.sop-lampiran-preview-hint {
    color: var(--hcis-white);
    font-size: 0.675rem;
    font-weight: 700;
    text-align: center;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(4px);
    border-radius: 4px;
    padding: 0.15rem 0.35rem;
}
</style>
