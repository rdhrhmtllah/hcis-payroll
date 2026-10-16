<template>
    <SopModal
        :show="show"
        :title="`Versi & Pengesahan — ${dokumen?.Judul || 'Dokumen'}`"
        subtitle="Kelola berkas konten, penetapan masa berlaku, dan pejabat pengesah dokumen"
        icon="bi-clock-history"
        size="lg"
        @close="$emit('close')"
    >
        <div class="sop-modal-container">
            <!-- SATU layar saja. Formulir versi pindah ke halaman wizard
                 /sop/admin/dokumen/{dokumen}/versi pada 2026-08-26; modal ini
                 kembali menjadi apa yang namanya janjikan. -->
            <section class="sop-modal-section">
                <div class="d-flex align-items-center justify-content-between pb-2 mb-3 border-bottom">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        <span class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.04em;">Riwayat Versi Dokumen</span>
                    </div>
                    <HcisBadge variant="neutral" size="sm">
                        {{ versi.length }} Versi
                    </HcisBadge>
                </div>

                <div v-if="memuat" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <p class="text-muted small mt-2 mb-0">Memuat riwayat versi…</p>
                </div>

                <HcisEmptyState
                    v-else-if="!versi.length"
                    icon="clock-history"
                    title="Belum Ada Versi"
                    description="dokumen baru bisa digunakan pada paket penugasan setelah memiliki minimal satu versi berlaku."
                    action-label="Buat Versi Pertama"
                    action-icon="plus-lg"
                    :action-loading="navigasiLoading"
                    @action="bukaVersiBaru"
                    class="py-4"
                />

                <div v-else class="sop-version-deck">
                    <div
                        v-for="item in versiTimelineItems"
                        :key="item.id"
                        class="sop-version-card"
                        :class="{
                            'sop-version-card--active': item.active,
                            'sop-version-card--draft': item.raw.Status === 'DRAFT',
                            'sop-version-card--obsolete': item.raw.Status === 'OBSOLETE'
                        }"
                    >
                        <!-- Top Row: Badge + Status + Actions -->
                        <div class="sop-version-card-head d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center flex-wrap my-1">
                                <span
                                    class="sop-version-badge me-2"
                                    :class="{
                                        'sop-version-badge--active': item.active,
                                        'sop-version-badge--draft': item.raw.Status === 'DRAFT',
                                        'sop-version-badge--obsolete': item.raw.Status === 'OBSOLETE'
                                    }"
                                >
                                    {{ item.initials }}
                                </span>

                                <HcisBadge
                                    :variant="item.raw.Dibatalkan ? 'danger' : (item.active ? 'success' : item.raw.Status === 'DRAFT' ? 'warning' : 'neutral')"
                                    size="sm"
                                    :dot="item.active"
                                    class="me-2"
                                >
                                    <!-- Pembatalan diperiksa PALING DULU. Versi batal
                                         tetap berstatus DRAFT, jadi tanpa cabang ini
                                         lencananya menyebutnya "Draft Revisi" — nama
                                         untuk pekerjaan yang masih berjalan, di kartu
                                         yang siklusnya sudah berakhir. -->
                                    {{ item.raw.Dibatalkan ? 'Dibatalkan (Arsip)' : (item.active ? 'Versi Berlaku (Aktif)' : item.raw.Status === 'DRAFT' ? 'Draft Revisi' : 'Versi Arsip') }}
                                </HcisBadge>

                                <!-- TAHAP PROSES: bahasa manusia di atas Status.
                                     "Draft Revisi" tak membedakan versi yang baru
                                     diketik dari yang sudah menunggu tanda tangan;
                                     tahap inilah yang menjawabnya. -->
                                <span
                                    v-if="item.raw.Tahap_Nama"
                                    class="sop-tahap-pill me-2"
                                    :style="{ '--tc': item.raw.Tahap_Warna || '#64748b' }"
                                    :title="`Tahap proses: ${item.raw.Tahap_Nama}`"
                                >
                                    <i class="bi bi-signpost-split-fill"></i>
                                    {{ item.raw.Tahap_Nama }}
                                </span>

                                <!-- Nadanya mengikuti TAHAP, bukan sekadar ada
                                     atau tidaknya berkas. Versi yang baru didraf
                                     memang belum punya berkas — menandainya merah
                                     mengajari admin mengabaikan warna merah.
                                     Yang benar-benar rusak adalah versi TERBIT
                                     tanpa berkas: karyawan membuka dokumen kosong. -->
                                <HcisBadge
                                    v-if="isiVersiKosong(item.raw)"
                                    :variant="item.raw.Status === 'PUBLISHED' ? 'danger' : 'neutral'"
                                    size="sm"
                                    class="me-2"
                                    :title="item.raw.Status === 'PUBLISHED'
                                        ? 'Versi ini berlaku tetapi tak berisi apa pun'
                                        : 'Belum dilampirkan — dituntut saat Pemberkasan'"
                                >
                                    <i
                                        class="bi me-1"
                                        :class="item.raw.Status === 'PUBLISHED' ? 'bi-exclamation-triangle-fill' : 'bi-paperclip'"
                                    ></i>
                                    {{ isKuis ? 'Tanpa Ujian' : 'Belum Ada Berkas' }}
                                </HcisBadge>
                            </div>

                            <!-- Action Buttons -->
                            <div class="sop-version-actions d-flex align-items-center flex-wrap ms-sm-auto my-1">
                                <HcisButton
                                    variant="primary"
                                    size="xs"
                                    leading-icon="info-circle"
                                    label="Lihat Detail"
                                    title="Buka detail lengkap versi, dokumen, pejabat pengesah & catatan review"
                                    @click="bukaDetailVersi(item.raw)"
                                />
                                <!-- VERSI BATAL TETAP BERSTATUS DRAFT — yang berpindah
                                     tahapnya. Ketiga tombol garapan di bawah karena itu
                                     dulu ikut tampil pada arsip: menyunting, menghapus,
                                     dan melanjutkan versi yang siklusnya sudah berakhir.
                                     Dibatalkan datang dari server, jawaban yang sama yang
                                     dipakai penjaga di SopDokumenService. -->
                                <HcisButton
                                    v-if="item.raw.Boleh_Disunting"
                                    variant="secondary"
                                    size="xs"
                                    icon-only
                                    leading-icon="pencil"
                                    title="Sunting draft revisi"
                                    aria-label="Sunting draft revisi"
                                    :loading="suntingLoading === item.raw.Id_SOP_Item_Versi"
                                    :disabled="navigasiLoading || (suntingLoading && suntingLoading !== item.raw.Id_SOP_Item_Versi)"
                                    @click="suntingVersi(item.raw)"
                                />
                                <HcisButton
                                    v-if="item.raw.Boleh_Disunting"
                                    variant="danger-quiet"
                                    size="xs"
                                    icon-only
                                    leading-icon="trash"
                                    title="Hapus draft revisi"
                                    aria-label="Hapus draft revisi"
                                    :loading="menghapus === item.raw.Id_SOP_Item_Versi"
                                    :disabled="navigasiLoading || Boolean(suntingLoading)"
                                    @click="hapusVersi(item.raw)"
                                />
                                <HcisButton
                                    v-if="item.raw.Status === 'OBSOLETE' && item.raw.Punya_Berkas"
                                    variant="warning"
                                    size="xs"
                                    leading-icon="arrow-counterclockwise"
                                    label="Kembalikan"
                                    title="Kembalikan & aktifkan kembali versi arsip ini"
                                    :disabled="navigasiLoading || Boolean(suntingLoading)"
                                    @click="kembalikan = item.raw"
                                />
                                <!-- Gerbang terbit dibaca dari Boleh_Terbit milik server —
                                     kembar persis dengan Master Dokumen. Nama tahap yang
                                     dihafal layar ("APPROVAL") menunjuk Pengesahan yang BELUM
                                     selesai, jadi tombolnya muncul di langkah yang server
                                     tolak sekaligus menyembunyikan "Lengkapi Versi" di
                                     baliknya. Keduanya kini berdiri sendiri. -->
                                <HcisButton
                                    v-if="item.raw.Boleh_Disunting && item.raw.Boleh_Terbit"
                                    variant="success"
                                    size="xs"
                                    leading-icon="broadcast"
                                    label="Pratinjau Terbit"
                                    title="Versi sudah di langkah Pemberkasan — buka pratinjau sebelum terbit"
                                    :disabled="navigasiLoading || Boolean(suntingLoading)"
                                    @click="bukaPratinjau(item.raw)"
                                />
                                <HcisButton
                                    v-if="item.raw.Boleh_Disunting"
                                    variant="ghost"
                                    size="xs"
                                    leading-icon="clipboard-check"
                                    label="Lengkapi Versi"
                                    title="Buka perjalanan versi dan lengkapi tahap yang belum selesai"
                                    :loading="suntingLoading === item.raw.Id_SOP_Item_Versi"
                                    :disabled="navigasiLoading || (suntingLoading && suntingLoading !== item.raw.Id_SOP_Item_Versi)"
                                    @click="lengkapiVersi(item.raw)"
                                />
                            </div>
                        </div>

                        <!-- Ringkasan Perubahan jika ada -->
                        <div v-if="item.raw.Ringkasan_Perubahan" class="sop-version-summary">
                            <i class="bi bi-chat-left-text text-primary me-1.5 opacity-75"></i>
                            <span>{{ item.raw.Ringkasan_Perubahan }}</span>
                        </div>

                        <!-- Lifecycle Timestamps Bar -->
                        <div class="sop-version-meta-bar">
                            <span class="sop-meta-item">
                                <i class="bi bi-calendar-plus text-primary"></i>
                                <span>Dibuat: <strong class="text-dark fw-semibold">{{ item.dibuat }}</strong></span>
                            </span>
                            <span v-if="item.terbit" class="sop-meta-item">
                                <i class="bi bi-broadcast text-success"></i>
                                <span>Tanggal Terbit: <strong class="text-success fw-semibold">{{ item.terbit }}</strong></span>
                            </span>
                            <span class="sop-meta-item">
                                <i class="bi bi-clock-history text-secondary"></i>
                                <span>Masa Berlaku: <strong class="text-dark fw-semibold">{{ item.rentangMasa }}</strong></span>
                            </span>
                            <span v-if="item.raw.Disahkan_Oleh" class="sop-meta-item ms-md-auto">
                                <i class="bi bi-person-check text-primary"></i>
                                <span>Pengesah: <strong class="text-dark fw-semibold">{{ item.raw.Disahkan_Oleh }}</strong></span>
                            </span>
                        </div>
                    </div>

                    <!-- Pagination Backend jika versiTotal > versiPerPage -->
                    <div v-if="versiTotal > versiPerPage" class="d-flex justify-content-center mt-3 pt-2 border-top">
                        <HcisPagination
                            :current-page="versiPage"
                            :page-size="versiPerPage"
                            :total="versiTotal"
                            size="sm"
                            layout="total, prev, pager, next"
                            @update:current-page="muat"
                        />
                    </div>
                </div>
            </section>
        </div>

        <!-- Modal Footer -->
        <template #footer>
            <div class="d-flex align-items-center justify-content-between w-100">
                <HcisButton
                    variant="secondary"
                    size="sm"
                    label="Tutup"
                    :disabled="navigasiLoading || Boolean(suntingLoading)"
                    @click="$emit('close')"
                />
                <HcisButton
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    :loading="navigasiLoading"
                    :disabled="navigasiLoading || Boolean(suntingLoading)"
                    :label="versi.length ? 'Tambah Versi Baru' : 'Buat Versi Pertama'"
                    @click="bukaVersiBaru"
                />
            </div>
        </template>

        <!-- Pratinjau terbit -->
        <SopTerbitPratinjau :versi="pratinjau" @close="pratinjau = null" @berhasil="setelahTerbit" />
        <SopTerbitPratinjau :versi="kembalikan" mode="kembali" @close="kembalikan = null" @berhasil="setelahTerbit" />


        <!-- Modal Rinci Detail Versi Dokumen -->
        <SopVersiDetailModal
            v-model="detailModalBuka"
            :versi="detailVersiTerpilih"
            :dokumen="dokumen"
            :id-dokumen="dokumen?.Id_SOP_Dokumen"
        />

        <!-- Viewer berkas -->
        <SopKontenViewer
            :show="berkasTerbuka"
            :konten="berkasVersi"
            :judul="dokumen?.Judul || ''"
            :tipe="dokumen?.Tipe || 'DOKUMEN'"
            @close="
                berkasTerbuka = false;
                berkasVersi = null;
            "
        />
    </SopModal>
</template>

<script>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import SopModal from './SopModal.vue';
import SopTerbitPratinjau from './SopTerbitPratinjau.vue';
import SopKontenViewer from './SopKontenViewer.vue';
import SopVersiDetailModal from './SopVersiDetailModal.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisTimeline from '@/components/ui/HcisTimeline.vue';
import HcisEmptyState from '@/components/ui/HcisEmptyState.vue';
import HcisPagination from '@/components/ui/HcisPagination.vue';
import HcisCard from '@/components/ui/HcisCard.vue';
import { hcisConfirm, hcisMessage } from '@/components/ui';
import { BLOK, blokBerlaku } from '@/Pages/LMS/SOP/Shared/support/constants';

const API = '/api/v1/sop/admin/dokumen';

/**
 * Versi & pengesahan dokumen — dikelola di MASTER, bukan di Builder.
 */
export default {
    name: 'SopDokumenVersiModal',
    components: {
        SopModal,
        SopTerbitPratinjau,
        SopKontenViewer,
        SopVersiDetailModal,
        HcisButton,
        HcisBadge,
        HcisTimeline,
        HcisEmptyState,
        HcisPagination,
        HcisCard,
    },
    props: {
        show: { type: Boolean, default: false },
        dokumen: { type: Object, default: null },
        suntingAwal: { type: String, default: null },
    },
    emits: ['close', 'berubah'],
    data() {
        return {
            versi: [],
            versiTotal: 0,
            versiPage: 1,
            versiPerPage: 5,
            versiTotalPage: 1,
            memuat: false,
            pratinjau: null,
            kembalikan: null,
            berkasSibuk: null,
            berkasTerbuka: false,
            berkasVersi: null,
            menghapus: null,
            navigasiLoading: false,
            suntingLoading: null,
            detailModalBuka: false,
            detailVersiTerpilih: null,
        };
    },
    computed: {
        isKuis() {
            return this.dokumen?.Tipe === 'KUIS';
        },
        isForm() {
            return this.dokumen?.Tipe === 'FORM';
        },
        /* Tiga tanggal yang dicap perpindahan tahap. Labelnya disebut di sini,
           tahapnya ikut ditampilkan supaya admin tahu apa yang mengisinya —
           bukan menemukan angka muncul sendiri tanpa sebab. */
        /* Langkah berikutnya yang WAJAR — dan "wajar" berarti MAJU.
           Tiga penyaring, masing-masing menutup kekeliruan yang berbeda:

           1. Hanya yang bermaksud MAJU. Pembatalan adalah keputusan sadar,
              bukan kelanjutan yang ditawarkan sebuah tombol simpan; dan dari
              Review, Drafting memang transisi sah untuk koreksi tetapi ia
              MUNDUR — menawarkannya sebagai "lanjut" salah arah.
           2. Syaratnya harus sudah terpenuhi — kalau belum, tombolnya tetap
              "Simpan" biasa dan admin melengkapi dulu.

           Ambigu (lebih dari satu tujuan maju) juga menghasilkan null: memilih
           sendiri di antara dua jalan yang sah bukan wewenang sebuah tombol. */
        versiTimelineItems() {
            if (!this.versi?.length) return [];
            const list = this.versi;
            return list.map((v, idx) => {
                const isPublished = v.Status === 'PUBLISHED';
                const isDraft = v.Status === 'DRAFT';
                const sentiment = isPublished ? 'success' : isDraft ? 'warning' : 'neutral';
                const icon = isPublished ? 'patch-check-fill' : isDraft ? 'pencil-square' : 'clock-history';
                const vLabel = this.$vlabel(v.Versi_Ke);

                const dibuatStr = this.waktuJejak(v.Created_At);
                const terbitStr = v.Tanggal_Publish
                    ? this.waktuJejak(v.Tanggal_Publish)
                    : (isPublished && v.Tanggal_Pengesahan ? this.waktuJejak(v.Tanggal_Pengesahan) : (isDraft ? null : (v.Tanggal_Berlaku ? this.waktuJejak(v.Tanggal_Berlaku) : null)));
                const rentangMasa = this.formatRentangMasa(v, idx, list);

                const meta = [
                    { label: 'Dibuat', value: dibuatStr !== '—' ? dibuatStr : '—' },
                    { label: 'Tanggal Terbit', value: terbitStr || (isDraft ? 'Belum Terbit (Draft)' : '—') },
                    { label: 'Masa Berlaku', value: rentangMasa },
                ];

                if (v.Disahkan_Oleh) {
                    meta.push({
                        label: 'Pengesah',
                        value: v.Jabatan_Pengesah ? `${v.Disahkan_Oleh} (${v.Jabatan_Pengesah})` : v.Disahkan_Oleh,
                    });
                }
                if (v.Nomor_Pengesahan) {
                    meta.push({ label: 'No. Sah', value: v.Nomor_Pengesahan });
                }
                /* Kembar dengan Master Dokumen: checklist tahap terbit dari
                   server menggantikan peringatan lama, yang hanya menyebut satu
                   dari beberapa hal yang menahan versi ini. */
                if (v.Kurang_Terbit?.length) {
                    meta.push({ label: 'Belum Lengkap', value: v.Kurang_Terbit.join(' · ') });
                } else if (this.isiVersiKosong(v)) {
                    meta.push({ label: 'Peringatan', value: this.isKuis ? 'Tanpa Ujian' : 'Tanpa Berkas' });
                }

                return {
                    id: v.Id_SOP_Item_Versi || idx,
                    title: `${vLabel} · ${isPublished ? 'Versi Berlaku' : isDraft ? 'Draft Revisi' : 'Versi Arsip'}`,
                    name: v.Disahkan_Oleh ? `Disahkan oleh ${v.Disahkan_Oleh}` : undefined,
                    role: isPublished ? 'Versi Aktif' : isDraft ? 'Draft' : 'Arsip',
                    time: isDraft ? 'Draft (Belum Terbit)' : (terbitStr ? `Terbit: ${terbitStr}` : (v.Tanggal_Berlaku ? `Berlaku: ${v.Tanggal_Berlaku}` : '')),
                    content: v.Ringkasan_Perubahan || (isPublished ? 'Versi aktif yang digunakan pada seluruh paket tata kelola operasional.' : ''),
                    status: isPublished ? 'Berlaku' : isDraft ? 'Draft' : 'Arsip',
                    sentiment,
                    icon,
                    initials: vLabel,
                    avatarText: vLabel,
                    active: isPublished,
                    dibuat: dibuatStr,
                    terbit: terbitStr,
                    rentangMasa,
                    meta,
                    raw: v,
                };
            });
        },
    },
    watch: {
        async show(v) {
            if (!v) return;
            this.versiPage = 1;
            await this.muat();

            /* Permintaan "buka versi ini" diteruskan ke halaman wizard, bukan
               dilayani di sini: sejak 2026-08-26 formulir versi tak lagi
               tinggal di dalam modal. */
            if (this.suntingAwal) this.suntingVersi({ Id_SOP_Item_Versi: this.suntingAwal });
        },

        /* suntingAwal juga diawasi SENDIRI, bukan hanya lewat `show`.
           Pemanggil menyetel suntingAwal + dokumen bersamaan; kalau modalnya
           sudah terbuka, `show` tak berubah sehingga watcher di atas tak
           menyala dan permintaan "buka versi ini" hilang tanpa suara. */
        async suntingAwal(v) {
            if (!v || !this.show) return;
            this.suntingVersi({ Id_SOP_Item_Versi: v });
        },

        async dokumen(newDok, oldDok) {
            if (!this.show || !newDok) return;
            if (newDok?.Id_SOP_Dokumen !== oldDok?.Id_SOP_Dokumen) {
                this.versiPage = 1;
                await this.muat();
            }
        },
    },
    methods: {
        bukaDetailVersi(v) {
            this.detailVersiTerpilih = v;
            this.detailModalBuka = true;
        },
        /* Arahkan ke wizard; langkah yang dibuka dihitung server. Bukan selalu
           langkah 4 — draft di Review harus kembali ke Review, bukan dipaksa
           melihat berkas final terlalu dini. */
        lengkapiVersi(v) {
            this.suntingVersi(v);
        },


        isiVersiKosong(v) {
            return this.isKuis ? !v.Punya_Ujian : !v.Punya_Berkas;
        },
        waktuJejak(t) {
            if (!t) return '—';
            const d = new Date(String(t).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return String(t);

            return d.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        formatRentangMasa(v, idx, list) {
            if (v.Status === 'DRAFT') {
                return 'Menunggu penerbitan';
            }
            const mulai = v.Tanggal_Berlaku || (v.Tanggal_Publish ? v.Tanggal_Publish.split(' ')[0] : '—');
            if (v.Status === 'PUBLISHED') {
                return `${mulai} s/d Sekarang (Aktif)`;
            }
            if (v.Status === 'OBSOLETE') {
                const versiBaru = list && idx > 0 ? list[idx - 1] : null;
                const akhir = versiBaru?.Tanggal_Berlaku || (versiBaru?.Tanggal_Publish ? versiBaru.Tanggal_Publish.split(' ')[0] : 'Digantikan');
                return `${mulai} s/d ${akhir}`;
            }
            return mulai;
        },
        /**
         * Buka wizard versi — HALAMAN sendiri, bukan layar kedua di modal ini.
         *
         * Formulir versi pindah ke /sop/admin/dokumen/{dokumen}/versi pada
         * 2026-08-26. Modal ini kembali menjadi apa yang namanya janjikan:
         * daftar riwayat versi beserta pintu masuknya.
         */
        bukaVersiBaru() {
            if (this.navigasiLoading) return;
            this.navigasiLoading = true;
            router.visit(`/sop/admin/dokumen/${this.dokumen.Id_SOP_Dokumen}/versi`, {
                onFinish: () => {
                    this.navigasiLoading = false;
                },
            });
        },

        suntingVersi(v) {
            if (this.suntingLoading) return;
            this.suntingLoading = v.Id_SOP_Item_Versi;
            router.visit(
                `/sop/admin/dokumen/${this.dokumen.Id_SOP_Dokumen}/versi/${v.Id_SOP_Item_Versi}`,
                {
                    onFinish: () => {
                        this.suntingLoading = null;
                    },
                },
            );
        },

        async hapusVersi(v) {
            const confirmed = await hcisConfirm({
                title: 'Hapus Draft Revisi?',
                description: `Hapus draft ${this.$vlabel(v.Versi_Ke)}? Tindakan ini tidak dapat dibatalkan.`,
                confirmText: 'Ya, Hapus',
                cancelText: 'Batal',
                sentiment: 'danger',
                icon: 'trash',
            });
            if (!confirmed) return;

            this.menghapus = v.Id_SOP_Item_Versi;
            try {
                await axios.delete(`${API}/versi/${v.Id_SOP_Item_Versi}`);
                hcisMessage.success('Versi draft berhasil dihapus.');
                /* Tak ada lagi formulir terbuka yang perlu ditutup: sejak
                   2026-08-26 penyuntingan versi tinggal di halaman wizard,
                   dan modal ini hanya menampilkan daftarnya. */
                this.muat();
                this.$emit('berubah');
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal menghapus versi.');
            } finally {
                this.menghapus = null;
            }
        },
        async bukaIsiVersi(v, unduh = this.isForm) {
            this.berkasSibuk = v.Id_SOP_Item_Versi;
            try {
                const r = await axios.get(`${API}/versi/${v.Id_SOP_Item_Versi}/konten`, {
                    params: unduh ? { unduh: 1 } : {},
                });
                const k = r.data?.result || null;
                if (!k) throw new Error();

                if (unduh) {
                    window.open(k.url, '_blank', 'noopener');
                    return;
                }
                this.berkasVersi = k;
                this.berkasTerbuka = true;
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal membuka isi versi ini.');
            } finally {
                this.berkasSibuk = null;
            }
        },
        async muat(page = this.versiPage) {
            if (!this.dokumen?.Id_SOP_Dokumen) return;
            const targetPage = typeof page === 'object' && page !== null ? (page.page || page.currentPage || 1) : Math.max(1, Number(page) || 1);
            this.versiPage = targetPage;
            this.memuat = true;
            try {
                const r = await axios.get(`${API}/${this.dokumen.Id_SOP_Dokumen}/versi`, {
                    params: {
                        page: targetPage,
                        limit: this.versiPerPage,
                    },
                });
                const res = r.data?.result || {};
                this.versi = res.rows || [];
                this.versiTotal = res.total || 0;
                this.versiTotalPage = res.total_page || 1;
            } catch (e) {
                this.versi = [];
                this.versiTotal = 0;
                this.versiTotalPage = 1;
            } finally {
                this.memuat = false;
            }
        },
        bukaPratinjau(v) {
            this.pratinjau = v;
        },
        setelahTerbit() {
            this.muat();
            this.$emit('berubah');
        },
    },
};
</script>

<style scoped>
.sop-langkah-deck {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.55rem;
}

.sop-langkah {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
    padding: 0.65rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.75rem;
    text-align: left;
    background: #fff;
    color: var(--hcis-slate-500);
    transition: border-color 0.15s ease, background 0.15s ease, transform 0.15s ease;
}

.sop-langkah:hover,
.sop-langkah.is-active {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-50);
    transform: translateY(-1px);
}

.sop-langkah__nomor {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    font-size: 0.75rem;
    font-weight: 800;
}

.sop-langkah.is-selesai .sop-langkah__nomor {
    background: var(--hcis-emerald-600);
    color: #fff;
}
.sop-langkah.is-berjalan .sop-langkah__nomor {
    background: var(--hcis-indigo-600);
    color: #fff;
}
.sop-langkah.is-cancel .sop-langkah__nomor {
    background: var(--hcis-red-500);
    color: #fff;
}

.sop-langkah__isi {
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.sop-langkah__isi strong {
    color: var(--hcis-slate-900);
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sop-langkah__isi small {
    font-size: 0.67rem;
    color: var(--hcis-slate-400);
}

.sop-langkah.is-memuat {
    cursor: progress;
    opacity: 0.55;
    border-style: dashed;
}
.sop-langkah.is-memuat .sop-langkah__isi strong,
.sop-langkah.is-memuat .sop-langkah__isi small {
    color: var(--hcis-slate-400);
}

.sop-tahap-opsi.is-terkunci {
    opacity: 0.5;
    cursor: not-allowed;
    border-style: dashed;
}

.sop-langkah-panel {
    animation: sopLangkahMasuk 0.16s ease-out;
}
@keyframes sopLangkahMasuk {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Blok syarat: teks melar, area aksi TIDAK.
   Sebelumnya keduanya berbagi satu flex-wrap, sehingga tombol turun baris dan
   berhenti tepat di atas textarea Ringkasan Perubahan yang tingginya berubah
   sendiri saat diketik. Tombolnya ikut bergeser di detik klik — terbaca sebagai
   "tombol tak merespons", padahal kliknya mendarat di textarea. */
.sop-syarat-blok {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}
.sop-syarat-teks {
    min-width: 0;
    flex: 1 1 auto;
}
.sop-syarat-aksi {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 0 0 auto;
    flex-wrap: nowrap;
    position: relative;
    z-index: 2;
}
@media (max-width: 640px) {
    .sop-syarat-blok { flex-direction: column; }
    .sop-syarat-aksi { align-self: flex-start; }
}

.sop-syarat-baris {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
    font-size: 0.73rem;
}
.sop-syarat-baris > span:first-child {
    font-weight: 700;
    color: var(--hcis-slate-700);
}
.sop-syarat-baris .is-ok { color: var(--hcis-emerald-700); }
.sop-syarat-baris .is-kurang { color: var(--hcis-red-700); }

@media (max-width: 760px) {
    .sop-langkah-deck { grid-template-columns: 1fr 1fr; }
}

.sop-modal-container {
    display: block;
}

.sop-modal-section {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-xl, 14px);
    padding: 1.125rem 1.25rem;
}

.sop-version-deck {
    display: block;
}

.sop-version-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.875rem 1rem;
    margin-bottom: 0.875rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.sop-version-card:last-child {
    margin-bottom: 0;
}

.sop-version-card:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 8px -2px rgba(0, 0, 0, 0.06), 0 2px 4px -2px rgba(0, 0, 0, 0.04);
}

.sop-version-card--active {
    border-color: var(--hcis-emerald-200) !important;
    background: linear-gradient(180deg, var(--hcis-green-50) 0%, var(--hcis-white) 65%);
    box-shadow: 0 2px 6px -1px rgba(16, 185, 129, 0.12), 0 1px 3px -1px rgba(16, 185, 129, 0.08);
}

.sop-version-card--draft {
    border-color: var(--hcis-amber-200) !important;
    background: linear-gradient(180deg, #fffdf2 0%, var(--hcis-white) 65%);
}

.sop-version-card--obsolete {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
}

.sop-version-actions > * {
    margin-left: 6px;
    margin-top: 2px;
    margin-bottom: 2px;
}

.sop-version-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 3px 10px;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8125rem;
    font-weight: 750;
    line-height: 1.2;
}

.sop-version-badge--active {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-500) 100%);
    color: var(--hcis-white);
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.25);
}

.sop-version-badge--draft {
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
    color: var(--hcis-white);
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.22);
}

.sop-version-badge--obsolete {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-300);
}

.sop-version-summary {
    margin-top: 0.75rem;
    background: rgba(241, 245, 249, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    color: var(--hcis-slate-600);
    line-height: 1.45;
}

.sop-version-meta-bar {
    margin-top: 0.75rem;
    padding-top: 0.625rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    font-size: 0.775rem;
    color: var(--hcis-slate-500);
}

.sop-meta-item {
    display: inline-flex;
    align-items: center;
    margin-right: 1.25rem;
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
}

.sop-meta-item i {
    margin-right: 0.35rem;
}

.sop-file-preview-box {
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-lg, 10px);
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
}

.sop-numbering-box {
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-lg, 10px);
    border: 1px solid var(--hcis-slate-200);
}

/* ── Layar 2 Form Styling ── */
.sop-form-container {
    display: block;
}

.sop-form-header-bar {
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}

.sop-form-icon-pill {
    width: 28px;
    height: 28px;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-slate-100);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}

.sop-form-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 1rem 1.15rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
}

.sop-card-step-badge {
    width: 22px;
    height: 22px;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
    font-size: 0.75rem;
    font-weight: 750;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.sop-form-flow {
    display: flex;
    flex-direction: column;
}

.sop-form-flow > * + * {
    margin-top: 1.15rem;
}

.sop-file-widget {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200) !important;
}

.sop-file-icon-box {
    width: 40px;
    height: 40px;
    border-radius: var(--hcis-radius-md, 8px);
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sop-file-dropzone {
    border-color: var(--hcis-slate-300) !important;
    background: var(--hcis-slate-50);
    cursor: pointer;
    transition: all 0.15s ease;
}

.sop-file-dropzone:hover {
    border-color: var(--hcis-brand-500, var(--hcis-indigo-500)) !important;
    background: #f5f7ff;
}

.sop-choice-card {
    background: var(--hcis-white);
    cursor: pointer;
    transition: all 0.15s ease;
}

.sop-choice-card:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}

.sop-choice-card--active {
    border-color: var(--hcis-brand-500, var(--hcis-indigo-500)) !important;
    background: #eff6ff;
    box-shadow: 0 0 0 1px var(--hcis-brand-500, var(--hcis-indigo-500));
}

.sop-form-context-banner {
    background: linear-gradient(180deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border-color: var(--hcis-slate-200);
}

.cursor-pointer {
    cursor: pointer;
}

.sop-tahap-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
    background: var(--tc);
}

.sop-tahap-opsi {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    width: 100%;
    padding: 0.6rem 0.8rem;
    text-align: left;
    border-radius: 0.6rem;
    border: 1px solid var(--hcis-border, #e5e7eb);
    border-left: 4px solid var(--tc);
    background: #fff;
    transition: background 0.15s ease, transform 0.15s ease;
}

.sop-tahap-opsi:hover:not(:disabled) {
    background: var(--hcis-surface-subtle, var(--hcis-slate-50));
    transform: translateX(2px);
}

.sop-tahap-opsi:disabled {
    opacity: 0.6;
}

.sop-tahap-opsi__nama {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--hcis-text, var(--hcis-slate-900));
}

.sop-tahap-opsi__ket {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.sop-tahap-opsi__syarat {
    font-size: 0.7rem;
    color: var(--hcis-amber-700);
}

.sop-review-blok {
    padding: 0.8rem;
    border-radius: 0.6rem;
    border: 1px solid var(--hcis-border, #e5e7eb);
    background: var(--hcis-surface-subtle, var(--hcis-slate-50));
}

.sop-review-blok__judul {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-text, var(--hcis-slate-900));
}

.sop-review-lampiran-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.6rem;
    border-radius: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text, var(--hcis-slate-900));
    background: #fff;
    border: 1px dashed var(--hcis-border, var(--hcis-slate-300));
    cursor: pointer;
}

.sop-review-lampiran-btn:hover {
    background: var(--hcis-surface-subtle, var(--hcis-slate-100));
}

.sop-review-lampiran {
    position: relative;
    width: 5rem;
    height: 5rem;
    border-radius: 0.5rem;
    overflow: hidden;
    border: 1px solid var(--hcis-border, #e5e7eb);
    background: #fff;
}

.sop-review-lampiran img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sop-review-lampiran__x {
    position: absolute;
    top: 0.15rem;
    right: 0.15rem;
    width: 1.25rem;
    height: 1.25rem;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 999px;
    font-size: 0.7rem;
    line-height: 1;
    color: #fff;
    background: rgba(15, 23, 42, 0.7);
}
</style>
