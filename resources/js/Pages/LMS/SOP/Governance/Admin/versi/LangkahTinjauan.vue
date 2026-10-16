<template>
    <div class="d-flex flex-column gap-3">
        <!-- ══ 1. CONTEXT NOTIFICATION BANNER ══ -->
        <HcisAlert
            :variant="sudahTerbit ? 'success' : (kurang.length ? 'warning' : 'brand')"
            appearance="soft"
            :icon="sudahTerbit ? 'check-circle-fill' : (kurang.length ? 'exclamation-triangle-fill' : 'patch-check-fill')"
            :title="sudahTerbit ? 'Versi Resmi Telah Diterbitkan' : (kurang.length ? 'Kelengkapan Belum Lengkap' : 'Siap Ditinjau &amp; Diterbitkan')"
            :badge="sudahTerbit ? 'Terbit & Mengikat' : (kurang.length ? `${kurang.length} Butir Belum Lengkap` : 'Validasi Lolos')"
            size="sm"
            glow
            pattern="dots"
        >
            {{ kalimatKeadaan }}
        </HcisAlert>

        <!-- ══ 2. SECTION RECAP CARDS ══ -->
        <div class="d-flex flex-column gap-3">
            <HcisCard
                v-for="b in blok"
                :key="b.nomor"
                :title="b.judul"
                :description="b.keterangan"
                :icon="b.ikon"
                variant="outlined"
                sentiment="primary"
                size="sm"
            >
                <template #actions>
                    <div class="d-flex align-items-center gap-1.5">
                        <HcisButton
                            v-if="b.nomor === 4 && !isKuis && nilai.Id_Master_Konten"
                            variant="primary"
                            size="xs"
                            leading-icon="eye-fill"
                            label="Lihat Berkas"
                            title="Pratinjau berkas final lampiran"
                            :loading="memuatBerkas"
                            @click="bukaPratinjau"
                        />
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="pencil-square"
                            label="Sunting"
                            title="Sunting bagian ini"
                            @click="$emit('sunting', b.nomor)"
                        />
                    </div>
                </template>

                <dl class="tinjau__daftar">
                    <div v-for="(r, i) in b.baris" :key="i" class="tinjau__baris">
                        <dt>{{ r.label }}</dt>
                        <dd :class="{ 'tinjau__kosong': !r.nilai }">
                            <template v-if="r.isBerkas && r.nilai">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-0.5">
                                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                        <i class="bi bi-file-earmark-pdf-fill text-primary fs-5"></i>
                                        <span class="fw-bold text-dark text-truncate">{{ r.nilai }}</span>
                                        <HcisBadge label="Terlampir" variant="brand" size="xs" />
                                    </div>
                                    <HcisButton
                                        v-if="nilai.Id_Master_Konten"
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="eye-fill"
                                        label="Pratinjau Berkas"
                                        title="Buka pratinjau berkas dokumen"
                                        :loading="memuatBerkas"
                                        @click="bukaPratinjau"
                                    />
                                </div>
                            </template>
                            <!--
                                Dulu v-html="r.html ? r.nilai : escape(r.nilai)". Tidak ada satu pun
                                entri yang pernah menyetel r.html, jadi cabang mentahnya kode mati dan
                                semuanya lewat escape() -- tetapi begitu ada yang menambah html: true,
                                nilainya terender mentah tanpa sanitasi, berbeda dari komponen SOP
                                sebelah yang memakai sanitasiSop. Interpolasi biasa memberi hasil yang
                                sama persis tanpa menyisakan sinknya.
                            -->
                            <span v-else-if="r.nilai">{{ r.nilai }}</span>
                            <span v-else class="badge bg-light text-muted border text-xs">belum diisi</span>
                        </dd>
                    </div>
                </dl>
            </HcisCard>
        </div>

        <!-- ══ 3. HCIS FILE VIEWER UNTUK PRATINJAU DOKUMEN ══ -->
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
import HcisAlert from '@/components/ui/HcisAlert.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisFileViewer from '@/components/ui/HcisFileViewer.vue';

/**
 * Langkah 5 — Tinjauan Akhir Rekapitulasi Versi.
 */
export default {
    name: 'LangkahTinjauan',
    components: {
        HcisCard,
        HcisAlert,
        HcisButton,
        HcisBadge,
        HcisFileViewer,
    },
    props: {
        nilai: { type: Object, required: true },
        review: { type: Array, default: () => [] },
        dokumen: { type: Object, default: () => ({}) },
        isKuis: { type: Boolean, default: false },
        pakaiPengesahan: { type: Boolean, default: true },
        kurang: { type: Array, default: () => [] },
        sudahTerbit: { type: Boolean, default: false },
        versiSebelumnya: { type: Object, default: null },
    },
    emits: ['sunting'],
    data() {
        return {
            viewerBuka: false,
            memuatBerkas: false,
            berkas: null,
        };
    },
    computed: {
        kalimatKeadaan() {
            if (this.sudahTerbit) {
                return 'Versi ini sudah resmi terbit dan mengikat seluruh karyawan pembacanya. '
                    + 'Selama belum ada yang menerimanya, versi ini masih bisa dibatalkan dari Master Dokumen.';
            }
            if (this.kurang.length) {
                return `Versi ini belum siap diterbitkan karena [ ${this.kurang.join(', ')} ] masih kosong. Silakan gunakan tombol Sunting pada kartu terkait untuk melengkapinya.`;
            }

            /* Dulu kalimat ini memperingatkan bahwa penerbitan TAK BISA DITARIK.
               Sejak DML-2026-064 itu tak lagi benar, dan peringatan yang keliru
               lebih buruk daripada tak ada: ia membuat admin menerbitkan dengan
               gentar, lalu menemukan sendiri bahwa syaratnya lain. */
            return 'Seluruh data versi telah lengkap dan tervalidasi. Periksa sekali lagi ringkasan di bawah — '
                + 'setelah terbit, versi ini hanya bisa dibatalkan selama belum ada karyawan yang menerimanya.';
        },

        blok() {
            const n = this.nilai || {};

            const nomorVersiLabel = n.Versi_Ke === null || n.Versi_Ke === undefined
                ? 'Versi Baru'
                : `v${String(n.Versi_Ke).padStart(2, '0')}${this.versiSebelumnya && !this.sudahTerbit ? ` (Meneruskan dari v${String(this.versiSebelumnya.Versi_Ke).padStart(2, '0')})` : ''}`;

            const pengajuan = {
                nomor: 1,
                judul: 'Identitas & Pengajuan',
                keterangan: 'Identitas nomor versi dan ringkasan perubahan prosedur',
                ikon: 'pencil-square',
                baris: [
                    { label: 'Nomor Versi Target', nilai: nomorVersiLabel },
                    { label: 'Ringkasan Perubahan', nilai: n.Ringkasan_Perubahan },
                    { label: 'Tanggal Permintaan Review', nilai: this.tanggal(n.Tanggal_Permintaan) },
                    { label: 'Target / Tanggal Respon', nilai: this.tanggal(n.Tanggal_Respon) },
                    { label: 'Tanggal Tuntas Approval', nilai: this.tanggal(n.Tanggal_Akhir_Approval) },
                ],
            };

            const review = {
                nomor: 2,
                judul: 'Catatan Review Mutu',
                keterangan: 'Putaran review mutu yang tercatat — boleh kosong jika tanpa temuan',
                ikon: 'chat-square-text',
                baris: this.review.length
                    ? this.review.map((r) => ({
                        label: `Review #${r.Review_Ke}${r.Tanggal_Review ? ' · ' + this.tanggal(r.Tanggal_Review) : ''}`,
                        nilai: this.teksSaja(r.Hasil_Review)
                            + (r.Lampiran?.length ? ` (${r.Lampiran.length} lampiran gambar)` : ''),
                    }))
                    : [{ label: 'Catatan Review', nilai: 'Tanpa catatan temuan — proses review mutu tuntas.' }],
            };

            const pengesahan = {
                nomor: 3,
                judul: 'Matriks Pengesahan Pejabat',
                keterangan: 'Daftar pejabat struktural yang menandatangani & mengesahkan versi ini',
                ikon: 'people-fill',
                baris: (n.Pengesah || []).length
                    ? n.Pengesah.map((p) => ({
                        label: p.Peran || 'Pengesah',
                        nilai: [p.Nama, p.Jabatan].filter(Boolean).join(' — '),
                    }))
                    : [{ label: 'Pengesah', nilai: '' }],
            };

            const pemberkasan = {
                nomor: 4,
                judul: 'Pemberkasan & Masa Berlaku',
                keterangan: 'Berkas materi final terkendali dan jadwal masa keberlakuannya',
                ikon: 'file-earmark-check-fill',
                baris: [
                    this.isKuis
                        ? { label: 'Paket Ujian HC Learn', nilai: n.Nama_Ujian || (n.Id_Master_Ujian ? 'Ujian terpilih' : '') }
                        : { label: 'Berkas Final Dokumen', nilai: n.Konten_Nama, isBerkas: true },
                    { label: 'Mulai Berlaku', nilai: this.tanggal(n.Tanggal_Berlaku) },
                    { label: 'Tanggal Kedaluwarsa', nilai: this.tanggal(n.Tanggal_Kadaluarsa) },
                    { label: 'Jadwal Review Berikutnya', nilai: this.tanggal(n.Tanggal_Review_Berikutnya) },
                ],
            };

            return [pengajuan, review, ...(this.pakaiPengesahan ? [pengesahan] : []), pemberkasan];
        },
    },
    methods: {
        async bukaPratinjau() {
            if (!this.nilai?.Id_Master_Konten) return;

            this.memuatBerkas = true;
            try {
                const r = await axios.get(
                    `/api/v1/sop/admin/dokumen/konten/${this.nilai.Id_Master_Konten}/berkas`);
                const k = r.data?.result || null;
                if (!k?.url) throw new Error();

                this.berkas = {
                    url: k.url,
                    name: k.nama_berkas || this.nilai.Konten_Nama || 'Berkas Dokumen',
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
        tanggal(v) {
            if (!v) return '';
            const d = new Date(`${String(v).slice(0, 10)}T00:00:00`);
            if (Number.isNaN(d.getTime())) return String(v);

            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        },
        teksSaja(html) {
            if (!html) return '';
            const t = String(html).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

            return t.length > 220 ? `${t.slice(0, 220)}…` : t;
        },
    },
};
</script>

<style scoped>
.tinjau__daftar {
    margin: 0;
}

.tinjau__baris {
    display: grid;
    grid-template-columns: minmax(11rem, 16rem) 1fr;
    gap: 0.5rem 1.25rem;
    padding: 0.65rem 0;
    border-bottom: 1px dashed var(--hcis-slate-200);
    align-items: center;
}

.tinjau__baris:last-child {
    border-bottom: 0;
}

@media (max-width: 48rem) {
    .tinjau__baris {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
}

.tinjau__baris dt {
    margin: 0;
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
}

.tinjau__baris dd {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--hcis-slate-900);
}
</style>
