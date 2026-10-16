<template>
    <HcisDialog
        v-model="terbuka"
        title="Cek Keaslian Kartu Permit"
        kicker="Visitor Management · Verifikasi Keamanan"
        :icon="dialogIcon"
        :sentiment="dialogSentiment"
        variant="glass"
        size="md"
        glow
        pattern="grid"
        @close="tutup"
    >
        <div class="cek-kartu">
            <!-- ══ 1. CAMERA / SCANNING STATE ══ -->
            <template v-if="!hasil && !memeriksa">
                <!-- Instruction Header Banner -->
                <div class="cek-kartu__intro">
                    <div class="cek-kartu__intro-icon">
                        <HcisIcon name="upc-scan" size="sm" class="text-indigo-600" />
                    </div>
                    <div class="cek-kartu__intro-copy">
                        <strong class="cek-kartu__intro-title">Pemindaian QR Presisi</strong>
                        <p class="cek-kartu__intro-desc">
                            Arahkan kamera perangkat ke QR Code pada kartu permit. Validitas kartu diverifikasi langsung terhadap server HCIS.
                        </p>
                    </div>
                </div>

                <!-- Kamera Alert Error (Jika Izin Ditolak / Tidak Tersedia) -->
                <HcisAlert
                    v-if="galatKamera"
                    variant="warning"
                    :title="galatKamera"
                    description="Gunakan kolom input kode manual di bawah jika kamera perangkat tidak dapat diakses."
                    class="mb-2"
                >
                    <template #actions>
                        <HcisButton size="sm" variant="secondary" leading-icon="arrow-clockwise" label="Coba Lagi" @click="mulaiKamera" />
                    </template>
                </HcisAlert>

                <!-- Lightweight Camera Viewport (Tanpa Animasi Berat) -->
                <div class="cek-kartu__viewport-wrap">
                    <div class="cek-kartu__viewport">
                        <video ref="video" class="cek-kartu__video" playsinline muted />

                        <!-- Live Status Pill -->
                        <div class="cek-kartu__live-badge">
                            <span class="cek-kartu__live-dot" />
                            <span>Kamera Aktif</span>
                        </div>

                        <!-- Static Framing Reticle (Lightweight, No Heavy Loops) -->
                        <div class="cek-kartu__reticle" aria-hidden="true">
                            <div class="cek-kartu__corner cek-kartu__corner--tl" />
                            <div class="cek-kartu__corner cek-kartu__corner--tr" />
                            <div class="cek-kartu__corner cek-kartu__corner--bl" />
                            <div class="cek-kartu__corner cek-kartu__corner--br" />

                            <div class="cek-kartu__target-hint">
                                <HcisIcon name="qr-code" size="xs" />
                                <span>Posisikan QR di dalam bingkai</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual Input Alternative -->
                <div class="cek-kartu__manual-section">
                    <div class="cek-kartu__manual-divider">
                        <span class="cek-kartu__manual-divider-line" />
                        <span class="cek-kartu__manual-divider-text">Atau Masukkan Kode Token</span>
                        <span class="cek-kartu__manual-divider-line" />
                    </div>

                    <div class="cek-kartu__manual-row">
                        <div class="cek-kartu__manual-input-wrap">
                            <HcisField
                                id="cek-kartu-token-manual"
                                v-model="tokenManual"
                                placeholder="Ketik kode di bawah QR (cth: HCIS-PASS-1.123.xxxx)"
                                leading-icon="upc-scan"
                                size="md"
                                clearable
                                class="cek-kartu__manual-field"
                                @keyup.enter="periksa(tokenManual)"
                            />
                        </div>
                        <div class="cek-kartu__manual-actions">
                            <HcisButton
                                type="button"
                                size="md"
                                variant="secondary"
                                leading-icon="clipboard"
                                title="Tempel dari Clipboard"
                                class="cek-kartu__btn-paste"
                                @click="tempelClipboard"
                            >
                                <span>Tempel</span>
                            </HcisButton>
                            <HcisButton
                                size="md"
                                variant="brand"
                                leading-icon="search"
                                label="Periksa"
                                :loading="memeriksa"
                                :disabled="!tokenManual.trim()"
                                @click="periksa(tokenManual)"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <!-- ══ 2. LOADING STATE (Menggunakan HcisResultState Loading) ══ -->
            <div v-else-if="memeriksa" class="cek-kartu__loading-wrap">
                <HcisResultState
                    status="loading"
                    title="Memverifikasi Keaslian Kartu..."
                    description="Memeriksa keaslian tanda tangan token kriptografis, efektivitas status, dan masa berlaku permit di basis data HCIS."
                    variant="transparent"
                    size="md"
                />
            </div>

            <!-- ══ 3. RESULT STATE: SAH / VALID ══ -->
            <div v-else-if="hasil && hasil.sah" class="cek-kartu__valid-container">
                <!-- Status Header Banner -->
                <div class="cek-kartu__valid-hero">
                    <div class="cek-kartu__valid-icon-wrap">
                        <HcisIcon name="patch-check-fill" class="cek-kartu__valid-icon" />
                    </div>
                    <div class="cek-kartu__valid-header-info">
                        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                            <h3 class="cek-kartu__valid-title">KARTU SAH & TERVERIFIKASI</h3>
                            <HcisBadge
                                :label="hasil.status || 'AKTIF'"
                                variant="success"
                                size="sm"
                                appearance="solid"
                            />
                        </div>
                        <p class="cek-kartu__valid-sub">
                            Kartu permit resmi terdaftar dan aktif dalam sistem otorisasi gerbang HCIS.
                        </p>
                    </div>
                </div>

                <!-- License Plate & Category Zone Hero -->
                <div
                    v-if="hasil.plat"
                    class="cek-kartu__plate-card"
                    :style="{
                        borderColor: resolvedCategoryPill.border,
                        boxShadow: resolvedCategoryPill.glow,
                    }"
                >
                    <div class="cek-kartu__plate-card-top">
                        <div class="cek-kartu__plate-brand">
                            <span class="cek-kartu__plate-icon-chip">
                                <HcisIcon name="car-front-fill" size="2xs" />
                            </span>
                            <span class="cek-kartu__plate-brand-text">KARTU PERMIT KENDARAAN</span>
                        </div>
                        <span
                            class="cek-kartu__zone-badge"
                            :style="{
                                backgroundColor: resolvedCategoryPill.bg,
                                color: resolvedCategoryPill.text,
                                borderColor: resolvedCategoryPill.badgeBorder,
                            }"
                        >
                            <span
                                class="cek-kartu__zone-dot"
                                :style="{
                                    backgroundColor: resolvedCategoryPill.dot,
                                    boxShadow: `0 0 6px ${resolvedCategoryPill.dot}`
                                }"
                            />
                            {{ hasil.kategori || 'PERMIT AKSES' }}
                        </span>
                    </div>

                    <div class="cek-kartu__plate-emboss">
                        <div class="cek-kartu__plate-bolt cek-kartu__plate-bolt--left" />
                        <div class="cek-kartu__plate-number-display">
                            {{ hasil.plat }}
                        </div>
                        <div class="cek-kartu__plate-bolt cek-kartu__plate-bolt--right" />
                    </div>
                </div>

                <!-- Structured Specification Matrix -->
                <div class="cek-kartu__detail-card">
                    <div class="cek-kartu__detail-row">
                        <div class="cek-kartu__detail-label">
                            <HcisIcon name="person-fill" size="2xs" class="text-indigo-500" />
                            <span>Nama Pemegang</span>
                        </div>
                        <div class="cek-kartu__detail-value font-semibold text-slate-900" :title="hasil.pemegang">
                            {{ hasil.pemegang || '-' }}
                        </div>
                    </div>

                    <div class="cek-kartu__detail-row">
                        <div class="cek-kartu__detail-label">
                            <HcisIcon name="building" size="2xs" class="text-indigo-500" />
                            <span>Departemen</span>
                        </div>
                        <div class="cek-kartu__detail-value font-semibold text-slate-800" :title="hasil.departemen">
                            {{ hasil.departemen || '-' }}
                        </div>
                    </div>

                    <div class="cek-kartu__detail-row">
                        <div class="cek-kartu__detail-label">
                            <HcisIcon name="calendar-check" size="2xs" class="text-indigo-500" />
                            <span>Masa Berlaku</span>
                        </div>
                        <div class="cek-kartu__detail-value font-tabular font-medium text-slate-800">
                            {{ hasil.sejak || '-' }} – {{ hasil.sampai || '-' }}
                        </div>
                    </div>

                    <div class="cek-kartu__detail-row">
                        <div class="cek-kartu__detail-label">
                            <HcisIcon name="receipt" size="2xs" class="text-indigo-500" />
                            <span>No. Transaksi</span>
                        </div>
                        <div class="cek-kartu__detail-value d-flex align-items-center gap-1.5 justify-content-end">
                            <span class="font-tabular font-semibold text-slate-900">#{{ hasil.no_transaksi || '-' }}</span>
                            <button
                                v-if="hasil.no_transaksi"
                                type="button"
                                class="cek-kartu__btn-copy-trx"
                                :title="trxCopied ? 'Tersalin!' : 'Salin Nomor Transaksi'"
                                @click="copyTrx(hasil.no_transaksi)"
                            >
                                <HcisIcon :name="trxCopied ? 'check2' : 'clipboard'" size="2xs" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Timestamp Security Badge -->
                <div class="cek-kartu__security-bar">
                    <div class="d-flex align-items-center gap-1.5 text-xs text-slate-500">
                        <HcisIcon name="shield-check" size="xs" class="text-emerald-600" />
                        <span>Diverifikasi pada: <strong class="text-slate-800">{{ hasil.dicek }} WIB</strong></span>
                    </div>
                    <span class="cek-kartu__seal-pill">
                        <HcisIcon name="check-circle-fill" size="2xs" />
                        <span>Enkripsi HCIS Valid</span>
                    </span>
                </div>
            </div>

            <!-- ══ 4. RESULT STATE: TIDAK SAH / GAGAL ══ -->
            <div v-else-if="hasil && !hasil.sah" class="cek-kartu__invalid-wrap">
                <HcisResultState
                    status="error"
                    :title="hasil.judul || 'KARTU TIDAK BERLAKU'"
                    :description="hasil.sebab || 'Kartu permit tidak terdaftar atau masa berlakunya sudah berakhir dalam basis data HCIS.'"
                    variant="transparent"
                    size="md"
                    primary-text="Pindai Kartu Lain"
                    primary-icon="arrow-repeat"
                    primary-variant="danger"
                    @primary-click="ulangi"
                />
            </div>
        </div>

        <!-- ══ FOOTER DIALOG ══ -->
        <template #footer>
            <div class="cek-kartu__footer">
                <div class="cek-kartu__footer-meta">
                    <template v-if="hasil">
                        <HcisIcon name="shield-lock" size="xs" class="text-indigo-600" />
                        <span class="font-tabular">Status: Terverifikasi oleh Sistem HCIS</span>
                    </template>
                    <template v-else>
                        <HcisIcon name="camera" size="xs" class="text-slate-400" />
                        <span>Arahkan kamera ke QR Code kartu permit</span>
                    </template>
                </div>

                <div class="cek-kartu__footer-actions">
                    <HcisButton
                        v-if="hasil"
                        variant="brand"
                        size="md"
                        leading-icon="arrow-repeat"
                        label="Pindai Kartu Lain"
                        @click="ulangi"
                    />
                    <HcisButton
                        variant="secondary"
                        size="md"
                        label="Tutup"
                        @click="terbuka = false"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import jsQR from 'jsqr';
import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisField, HcisIcon, HcisResultState } from '@/components/ui';

/**
 * Pemindai QR kartu permit — dipakai dari Pass Digital & pengajuan (karyawan),
 * Verifikator Permit, Agenda Inspeksi SHE, dan Monitoring Permit.
 *
 * Tidak ada halaman publik: pemeriksaan hanya bisa dari HCIS yang sudah login.
 * Yang dipindai adalah TOKEN bertanda tangan, dan keabsahannya diputus server
 * dari data terkini — komponen ini tidak pernah menilai sendiri.
 */
export default {
    name: 'VerifikasiKartuScanner',
    components: { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisField, HcisIcon, HcisResultState },
    props: {
        modelValue: { type: Boolean, default: false },
        /**
         * Pintu pemeriksaan. Bawaannya pintu KARYAWAN: hanya kartunya sendiri.
         * Layar admin (Verifikator, Agenda SHE, Monitoring) mengoper pintu
         * adminnya. Kemampuannya tetap diputus server lewat kunci halaman pada
         * rute — prop ini cuma memilih alamat, bukan memberi hak.
         */
        endpoint: { type: String, default: '/transaksipermit-verifikasi-kartu' },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            hasil: null,
            memeriksa: false,
            tokenManual: '',
            galatKamera: '',
            stream: null,
            frameId: null,
            trxCopied: false,
        };
    },
    computed: {
        terbuka: {
            get() {
                return this.modelValue;
            },
            set(v) {
                this.$emit('update:modelValue', v);
            },
        },
        dialogSentiment() {
            if (this.memeriksa) return 'purple';
            if (!this.hasil) return 'brand';
            return this.hasil.sah ? 'success' : 'danger';
        },
        dialogIcon() {
            if (this.memeriksa) return 'hourglass-split';
            if (!this.hasil) return 'upc-scan';
            return this.hasil.sah ? 'shield-fill-check' : 'shield-fill-x';
        },
        resolvedCategoryPill() {
            const raw = String(this.hasil?.kategori || '').toLowerCase();
            if (raw.includes('merah') || raw.includes('red') || raw.includes('vip')) {
                return {
                    border: '#ef4444',
                    glow: '0 8px 24px -4px rgba(239, 68, 68, 0.4), 0 0 0 1px rgba(239, 68, 68, 0.3) inset',
                    bg: 'rgba(239, 68, 68, 0.22)',
                    text: '#fca5a5',
                    badgeBorder: '#ef4444',
                    dot: '#ef4444',
                };
            }
            if (raw.includes('kuning') || raw.includes('yellow') || raw.includes('amber') || raw.includes('vendor')) {
                return {
                    border: '#f59e0b',
                    glow: '0 8px 24px -4px rgba(245, 158, 11, 0.4), 0 0 0 1px rgba(245, 158, 11, 0.3) inset',
                    bg: 'rgba(245, 158, 11, 0.22)',
                    text: '#fde68a',
                    badgeBorder: '#f59e0b',
                    dot: '#f59e0b',
                };
            }
            if (raw.includes('hijau') || raw.includes('green') || raw.includes('emerald')) {
                return {
                    border: '#10b981',
                    glow: '0 8px 24px -4px rgba(16, 185, 129, 0.4), 0 0 0 1px rgba(16, 185, 129, 0.3) inset',
                    bg: 'rgba(16, 185, 129, 0.22)',
                    text: '#a7f3d0',
                    badgeBorder: '#10b981',
                    dot: '#10b981',
                };
            }
            if (raw.includes('biru') || raw.includes('blue') || raw.includes('karyawan')) {
                return {
                    border: '#3b82f6',
                    glow: '0 8px 24px -4px rgba(59, 130, 246, 0.4), 0 0 0 1px rgba(59, 130, 246, 0.3) inset',
                    bg: 'rgba(59, 130, 246, 0.22)',
                    text: '#bfdbfe',
                    badgeBorder: '#3b82f6',
                    dot: '#3b82f6',
                };
            }
            if (raw.includes('coklat') || raw.includes('brown') || raw.includes('tamu')) {
                return {
                    border: '#ea580c',
                    glow: '0 8px 24px -4px rgba(234, 88, 12, 0.4), 0 0 0 1px rgba(234, 88, 12, 0.3) inset',
                    bg: 'rgba(234, 88, 12, 0.22)',
                    text: '#fed7aa',
                    badgeBorder: '#ea580c',
                    dot: '#ea580c',
                };
            }
            return {
                border: '#6366f1',
                glow: '0 8px 24px -4px rgba(99, 102, 241, 0.4), 0 0 0 1px rgba(99, 102, 241, 0.3) inset',
                bg: 'rgba(99, 102, 241, 0.22)',
                text: '#c7d2fe',
                badgeBorder: '#6366f1',
                dot: '#6366f1',
            };
        },
    },
    watch: {
        modelValue(baru) {
            if (baru) {
                this.ulangi();
            } else {
                this.hentikanKamera();
            }
        },
    },
    beforeUnmount() {
        this.hentikanKamera();
    },
    methods: {
        ulangi() {
            this.hasil = null;
            this.tokenManual = '';
            this.trxCopied = false;
            this.$nextTick(() => this.mulaiKamera());
        },
        async mulaiKamera() {
            this.galatKamera = '';
            this.hentikanKamera();

            if (!navigator.mediaDevices?.getUserMedia) {
                this.galatKamera = 'Peramban ini tidak mendukung akses kamera langsung. Silakan ketik kode token secara manual.';
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false,
                });

                const video = this.$refs.video;
                // Dialog ditutup selagi izin kamera masih diminta: stream baru
                // tiba sesudah hentikanKamera() berjalan, jadi dimatikan di sini —
                // tanpa ini lampu kamera menyala sampai halaman dimuat ulang.
                if (!video || !this.modelValue) {
                    this.hentikanKamera();
                    return;
                }

                video.srcObject = this.stream;
                await video.play();
                this.pindaiFrame();
            } catch (e) {
                this.galatKamera = e?.name === 'NotAllowedError'
                    ? 'Izin akses kamera ditolak oleh browser. Anda dapat mengetik kode permit manual di bawah.'
                    : 'Kamera tidak dapat diinisialisasi. Silakan masukkan kode token di bawah.';
            }
        },
        pindaiFrame() {
            const video = this.$refs.video;
            if (!video || !this.stream) return;

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                const kanvas = document.createElement('canvas');
                kanvas.width = video.videoWidth;
                kanvas.height = video.videoHeight;

                const ctx = kanvas.getContext('2d');
                ctx.drawImage(video, 0, 0, kanvas.width, kanvas.height);

                const gambar = ctx.getImageData(0, 0, kanvas.width, kanvas.height);
                const kode = jsQR(gambar.data, gambar.width, gambar.height, { inversionAttempts: 'dontInvert' });

                if (kode?.data) {
                    this.periksa(kode.data);
                    return;
                }
            }

            this.frameId = requestAnimationFrame(() => this.pindaiFrame());
        },
        async periksa(token) {
            const isi = String(token || '').trim();
            if (!isi || this.memeriksa) return;

            this.memeriksa = true;
            this.hentikanKamera();
            try {
                const { data } = await axios.post(this.endpoint, { token: isi });
                this.hasil = data?.result || null;
            } catch (e) {
                this.hasil = {
                    sah: false,
                    judul: 'GAGAL MEMERIKSA',
                    sebab: e?.response?.data?.message || 'Sambungan ke server HCIS terputus. Silakan coba lagi.',
                    dicek: '-',
                };
            } finally {
                this.memeriksa = false;
            }
        },
        hentikanKamera() {
            if (this.frameId) {
                cancelAnimationFrame(this.frameId);
                this.frameId = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
        async tempelClipboard() {
            try {
                const text = await navigator.clipboard.readText();
                if (text) {
                    this.tokenManual = text.trim();
                    this.periksa(this.tokenManual);
                }
            } catch (e) {
                // Ignore clipboard read error
            }
        },
        copyTrx(val) {
            if (!val) return;
            navigator.clipboard?.writeText(val);
            this.trxCopied = true;
            setTimeout(() => {
                this.trxCopied = false;
            }, 2000);
        },
        tutup() {
            this.hentikanKamera();
        },
    },
};
</script>

<style scoped>
.cek-kartu {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    box-sizing: border-box;
}

/* 1. Intro Header Banner */
.cek-kartu__intro {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.9) 0%, rgba(245, 243, 255, 0.7) 100%);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
}

.cek-kartu__intro-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid var(--hcis-brand-300, #a5b4fc);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.1);
}

.cek-kartu__intro-copy {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.cek-kartu__intro-title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
}

.cek-kartu__intro-desc {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-slate-600, #475569);
    line-height: 1.35;
}

/* 2. Lightweight Camera Viewport (No Heavy Loops) */
.cek-kartu__viewport-wrap {
    position: relative;
    width: 100%;
    border-radius: 14px;
    overflow: hidden;
    background: #090d16;
    border: 1px solid rgba(15, 23, 42, 0.15);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.cek-kartu__viewport {
    position: relative;
    width: 100%;
    height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cek-kartu__video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.cek-kartu__live-badge {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.6rem;
    background: rgba(15, 23, 42, 0.75);
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #f8fafc;
    z-index: 5;
    backdrop-filter: blur(4px);
}

.cek-kartu__live-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
}

/* Static Lightweight Target Reticle */
.cek-kartu__reticle {
    position: absolute;
    width: 190px;
    height: 190px;
    border: 1px dashed rgba(255, 255, 255, 0.35);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    padding-bottom: 0.65rem;
    box-shadow: 0 0 0 9999px rgba(2, 6, 23, 0.35);
    z-index: 4;
}

.cek-kartu__corner {
    position: absolute;
    width: 18px;
    height: 18px;
    border-color: #6366f1;
    border-style: solid;
}

.cek-kartu__corner--tl { top: -2px; left: -2px; border-width: 3px 0 0 3px; border-top-left-radius: 6px; }
.cek-kartu__corner--tr { top: -2px; right: -2px; border-width: 3px 3px 0 0; border-top-right-radius: 6px; }
.cek-kartu__corner--bl { bottom: -2px; left: -2px; border-width: 0 0 3px 3px; border-bottom-left-radius: 6px; }
.cek-kartu__corner--br { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; border-bottom-right-radius: 6px; }

.cek-kartu__target-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #ffffff;
    background: rgba(15, 23, 42, 0.75);
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
}

/* 3. Loading Wrap */
.cek-kartu__loading-wrap {
    padding: 1.5rem 0.5rem;
}

/* 4. Valid Result Card (Clean Executive Design) */
.cek-kartu__valid-container {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
}

.cek-kartu__valid-hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.45rem;
    padding: 0.75rem 0.5rem;
}

.cek-kartu__valid-icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 50%;
    background: #ecfdf5;
    border: 2px solid #a7f3d0;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.cek-kartu__valid-icon {
    font-size: 1.85rem;
    color: #059669;
}

.cek-kartu__valid-header-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.cek-kartu__valid-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #065f46;
    letter-spacing: -0.01em;
}

.cek-kartu__valid-sub {
    margin: 0;
    font-size: 0.78125rem;
    color: #047857;
    line-height: 1.35;
}

/* ══ Luxury Automotive License Plate Hero Box ══ */
.cek-kartu__plate-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    background: linear-gradient(145deg, #0b0f19 0%, #020617 100%);
    border-width: 2px;
    border-style: solid;
    border-radius: 14px;
    padding: 0.85rem 1rem;
    overflow: hidden;
    transition: all 0.25s ease;
}

.cek-kartu__plate-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 45%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.07) 0%, transparent 100%);
    pointer-events: none;
}

.cek-kartu__plate-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.cek-kartu__plate-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.cek-kartu__plate-icon-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
}

.cek-kartu__plate-brand-text {
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #94a3b8;
    text-transform: uppercase;
}

.cek-kartu__zone-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    border-width: 1px;
    border-style: solid;
    backdrop-filter: blur(4px);
}

.cek-kartu__zone-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.cek-kartu__plate-emboss {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.45);
    border: 1.5px solid rgba(255, 255, 255, 0.12);
    border-radius: 9px;
    padding: 0.6rem 1.25rem;
    box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.6);
}

.cek-kartu__plate-bolt {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: radial-gradient(circle, #e2e8f0 30%, #64748b 80%, #0f172a 100%);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.5);
}

.cek-kartu__plate-bolt--left {
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
}

.cek-kartu__plate-bolt--right {
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
}

.cek-kartu__plate-number-display {
    font-family: 'JetBrains Mono', 'Fira Code', 'DIN Next', 'Consolas', var(--hcis-font-mono, monospace);
    font-size: 1.95rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    color: #ffffff;
    text-align: center;
    line-height: 1.1;
    text-shadow: 0 3px 8px rgba(0, 0, 0, 0.9), 0 0 1px rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Structured Detail Rows */
.cek-kartu__detail-card {
    background: #f8fafc;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 12px;
    overflow: hidden;
}

.cek-kartu__detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0.85rem;
    font-size: 0.8125rem;
    border-bottom: 1px solid var(--hcis-slate-200, #f1f5f9);
    gap: 0.75rem;
}

.cek-kartu__detail-row:last-child {
    border-bottom: none;
}

.cek-kartu__detail-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-slate-500, #64748b);
    font-size: 0.75rem;
    font-weight: 600;
    flex-shrink: 0;
}

.cek-kartu__detail-value {
    text-align: right;
    min-width: 0;
    word-break: break-word;
}

.cek-kartu__btn-copy-trx {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 4px;
    border: 1px solid var(--hcis-slate-300, #cbd5e1);
    background: #ffffff;
    color: var(--hcis-slate-600, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.cek-kartu__btn-copy-trx:hover {
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
    border-color: var(--hcis-brand-300, #a5b4fc);
}

.cek-kartu__security-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.35rem 0.25rem;
    flex-wrap: wrap;
}

.cek-kartu__seal-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65625rem;
    font-weight: 700;
    color: #047857;
    background: #ecfdf5;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    border: 1px solid #a7f3d0;
}

/* 5. Invalid Wrap */
.cek-kartu__invalid-wrap {
    padding: 1rem 0;
}

/* 6. Manual Input Row */
.cek-kartu__manual-section {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    width: 100%;
}

.cek-kartu__manual-divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.cek-kartu__manual-divider-line {
    flex: 1;
    height: 1px;
    background: var(--hcis-slate-200, #e2e8f0);
}

.cek-kartu__manual-divider-text {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.cek-kartu__manual-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

.cek-kartu__manual-input-wrap {
    flex: 1;
    min-width: 0;
}

.cek-kartu__manual-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-shrink: 0;
}

.cek-kartu__btn-paste {
    border-radius: 8px;
}

/* 7. Footer Layout */
.cek-kartu__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.cek-kartu__footer-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500, #64748b);
}

.cek-kartu__footer-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 640px) {
    .cek-kartu__manual-row {
        flex-direction: column;
        align-items: stretch;
    }

    .cek-kartu__manual-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        width: 100%;
    }

    .cek-kartu__viewport {
        height: 220px;
    }

    .cek-kartu__reticle {
        width: 160px;
        height: 160px;
    }

    .cek-kartu__plate-number-display {
        font-size: 1.6rem;
        letter-spacing: 0.1em;
    }

    .cek-kartu__footer {
        flex-direction: column;
        align-items: stretch;
    }

    .cek-kartu__footer-meta {
        justify-content: center;
    }

    .cek-kartu__footer-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        width: 100%;
    }
}
</style>

