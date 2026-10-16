<template>
    <transition name="kv">
        <!-- DOKUMEN → reader aman gambar per-halaman.
             FORM yang BOLEH diunduh tidak ikut ke sini: cukup ditampilkan apa
             adanya oleh peramban. FORM yang HANYA BOLEH DILIHAT (contoh
             pengisian PDF, 10 Okt 2026) masuk pembaca aman supaya tanda air
             berlaku dan tak ada URL mentah — dengan tanpaLacak: formulir tak
             dilacak per halaman (tanpa jeda baca, tanpa simpan halaman), tetapi
             penyelesaian di akhir tetap ada seperti dokumen. -->
        <!-- readonly juga dinyalakan saat sudahSelesai: reader sendiri memakai
             flag ini untuk menyembunyikan banner "tahan untuk selesai" (lihat
             canFinish di SopPdfReader) — tanpa ini, dokumen yang statusnya
             sudah SELESAI/DIKONFIRMASI akan minta konfirmasi selesai lagi
             setiap kali dibuka ulang. -->
        <SopPdfReader
            v-if="
                show &&
                (tipe !== 'FORM' || !bolehUnduh) &&
                konten &&
                konten.type === 'document' &&
                konten.berkas_id &&
                konten.total_halaman
            "
            :berkas-id="konten.berkas_id"
            :id-item="idItem"
            :judul="judul || konten.judul"
            :halaman-terakhir="halamanTerakhir"
            :readonly="readonly || sudahSelesai"
            :tanpa-lacak="tipe === 'FORM'"
            :marking="marking"
            :dwell-min="konten.dwell_min_dtk || 3"
            :id-paket="idPaket"
            :dokumen="dokumen"
            :versi-dibaca="versiDibaca"
            :admin="admin"
            :nav-sections="navSections"
            :nav-items="navItems"
            :boleh-unduh="bolehUnduh"
            :mengunduh="mengunduh"
            @close="$emit('close')"
            @complete="$emit('complete')"
            @select-item="$emit('select-item', $event)"
            @unduh="$emit('unduh')"
            @unduh-lampiran="$emit('unduh-lampiran', $event)"
            @unduh-semua-lampiran="$emit('unduh-semua-lampiran')"
        />

        <div
            v-else-if="show"
            :class="['konten-viewer', { 'is-video-theme': (konten && konten.type === 'video') || tipe === 'VIDEO' }]"
            @keydown.esc="$emit('close')"
        >
            <div class="kv-bar">
                <button class="kv-back" @click="$emit('close')"><i class="bi bi-arrow-left"></i></button>
                <div class="kv-titles min-w-0">
                    <div class="kv-eyebrow">{{ tipeLabel }}</div>
                    <div class="kv-title text-truncate">{{ judul || 'Konten' }}</div>
                </div>
                <div class="kv-spacer"></div>
                <span v-if="sudahSelesai" class="kv-done"><i class="bi bi-check-circle-fill"></i> Selesai</span>
            </div>

            <div class="kv-body">
                <!-- Cool Animated Document Loading Skeleton -->
                <div v-if="loading" class="kv-loader">
                    <div class="kv-loader-card">
                        <div class="kv-loader-icon">
                            <div class="kv-icon-pulse"></div>
                            <i
                                class="bi"
                                :class="
                                    (konten && konten.type === 'video') || tipe === 'VIDEO'
                                        ? 'bi-play-btn-fill'
                                        : 'bi-file-earmark-pdf-fill'
                                "
                            ></i>
                        </div>
                        <div class="kv-loader-text">
                            <div class="kv-loader-title">
                                {{
                                    (konten && konten.type === 'video') || tipe === 'VIDEO'
                                        ? 'Menyiapkan Video Dokumen...'
                                        : 'Menyiapkan Dokumen...'
                                }}
                            </div>
                            <div class="kv-loader-sub">Memuat berkas terkendali &amp; mengonfirmasi akses</div>
                        </div>
                        <div class="kv-loader-bar">
                            <div class="kv-loader-fill"></div>
                        </div>
                        <div class="kv-loader-skeletons">
                            <span class="sk-line w-80"></span>
                            <span class="sk-line w-100"></span>
                            <span class="sk-line w-60"></span>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="konten && konten.url"
                    class="kv-stage"
                    :class="konten.type === 'video' ? 'is-video' : 'is-doc'"
                >
                    <iframe :src="konten.url" class="kv-frame" allow="fullscreen" allowfullscreen></iframe>
                </div>
                <div v-else class="kv-msg empty">
                    <i class="bi bi-file-earmark-x"></i>
                    <span>Konten tak bisa ditampilkan.</span>
                </div>
            </div>

            <div class="kv-foot">
                <!-- Hanya untuk item ber-Mode_Akses VIEW_UNDUH. Server sudah tak
                     mengirim url mentah pada DOKUMEN VIEW_ONLY, jadi tombol ini
                     tak akan muncul di sana; syarat bolehUnduh menjaga agar niatnya
                     tetap terbaca dan tak tersalakan lagi tanpa sengaja. -->
                <a
                    v-if="bolehUnduh && konten && konten.type === 'document' && konten.url"
                    :href="konten.url"
                    target="_blank"
                    rel="noopener"
                    class="stitch-btn secondary sm"
                >
                    <i class="bi bi-box-arrow-up-right"></i> Tab baru
                </a>
                <!-- Formulir dibaca dulu, diisi belakangan — tombolnya harus ada
                     di sini juga, bukan cuma di kartu yang sudah tertutup layar. -->
                <button
                    v-if="tipe === 'FORM' && bolehUnduh"
                    class="stitch-btn secondary sm"
                    :disabled="mengunduh"
                    @click="$emit('unduh')"
                >
                    <i class="bi" :class="mengunduh ? 'bi-arrow-repeat kv-spin' : 'bi-download'"></i>
                    {{ mengunduh ? 'Menyiapkan…' : 'Unduh' }}
                </button>
                <span v-if="readonly" class="kv-ro"><i class="bi bi-eye"></i> Mode lihat (versi lama)</span>
                <div class="kv-spacer"></div>
                <template v-if="!readonly">
                    <!-- Gerbang tonton video: tombol terkunci sampai waktu-tonton
                         mencapai ambang % dari durasi (video_ambang_persen). Jujurnya:
                         yang diukur waktu viewer terbuka & tab terlihat, bukan posisi
                         playback di dalam iframe Bunny — kelas penegakan yang sama
                         dengan dwell per halaman di reader PDF. -->
                    <span v-if="!sudahSelesai && gerbangVideoAktif" class="kv-gate">
                        <i class="bi bi-hourglass-split"></i>
                        Tonton dulu — {{ persenTonton }}%
                    </span>
                    <button
                        v-if="!sudahSelesai"
                        class="stitch-btn primary"
                        :disabled="marking || gerbangVideoAktif"
                        @click="$emit('complete')"
                    >
                        <span v-if="marking" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-check2-all"></i> Tandai selesai
                    </button>
                    <span v-else class="kv-done-note"
                        ><i class="bi bi-check-circle-fill"></i> Sudah Anda selesaikan</span
                    >
                </template>
            </div>
        </div>
    </transition>
</template>

<script>
/**
 * SopKontenViewer — reader konten karyawan (dok PDF / video) layar penuh, responsif.
 * Parent yang fetch URL & panggil progress. Emit: close, complete.
 */
import SopPdfReader from './SopPdfReader.vue';

export default {
    name: 'SopKontenViewer',
    components: { SopPdfReader },
    props: {
        show: { type: Boolean, default: false },
        loading: { type: Boolean, default: false },
        konten: { type: Object, default: null }, // {type, url, judul, berkas_id}
        judul: { type: String, default: '' },
        tipe: { type: String, default: 'DOKUMEN' },
        status: { type: String, default: 'BELUM' }, // status baca item
        modeAkses: { type: String, default: null }, // VIEW_ONLY | VIEW_UNDUH — menyetir tombol unduh
        marking: { type: Boolean, default: false },
        readonly: { type: Boolean, default: false }, // true saat melihat versi lama (tanpa tandai selesai)
        idItem: { type: String, default: '' }, // hashid item (tracking halaman)
        halamanTerakhir: { type: Number, default: 0 }, // resume reader
        navSections: { type: Array, default: () => [] }, // Document structure for nav drawer
        navItems: { type: Array, default: () => [] },
        // Metadata dokumen diteruskan apa adanya ke pembaca PDF.
        idPaket: { type: String, default: '' },
        dokumen: { type: Object, default: null },
        versiDibaca: { type: Object, default: null },
        mengunduh: { type: Boolean, default: false }, // berkas FORM sedang disiapkan
        // Pratinjau dari layar admin: handshake lewat pintu admin, bukan pintu
        // karyawan (draft & versi cabutan memang ditolak lensa karyawan).
        admin: { type: Boolean, default: false },
    },
    emits: ['close', 'complete', 'select-item', 'unduh', 'unduh-lampiran', 'unduh-semua-lampiran'],
    data() {
        return { tontonDetik: 0, tontonTimer: null };
    },
    computed: {
        tipeLabel() {
            return { DOKUMEN: 'Dokumen', FORM: 'Formulir', VIDEO: 'Video' }[this.tipe] || 'Konten';
        },
        sudahSelesai() {
            return ['SELESAI', 'DIKONFIRMASI'].includes(this.status);
        },
        /** Detik yang wajib ditonton; 0 = tanpa gerbang (durasi tak diketahui). */
        wajibTontonDetik() {
            const k = this.konten || {};
            if (k.type !== 'video' || !k.durasi_detik) return 0;

            return Math.ceil(k.durasi_detik * ((k.ambang_persen || 90) / 100));
        },
        gerbangVideoAktif() {
            return this.wajibTontonDetik > 0 && this.tontonDetik < this.wajibTontonDetik;
        },
        persenTonton() {
            if (!this.wajibTontonDetik) return 100;

            return Math.min(100, Math.round((this.tontonDetik / this.wajibTontonDetik) * 100));
        },
        /**
         * Izin unduh untuk item paket datang dari server sebagai Boleh_Unduh —
         * keputusan SopAksesBerkas::bolehUnduh, yang sejak 10 Okt 2026 menahan
         * contoh pengisian FORM PDF ber-VIEW_ONLY.
         *
         * Tanpa item paket (pratinjau admin di Master Dokumen dan modal versi)
         * tak ada Mode_Akses yang bisa dibaca — izin itu milik paket — jadi
         * perilaku lamanya dipertahankan: VIEW_UNDUH, atau tipe FORM.
         * Penegakan sebenarnya tetap di server; ini semata agar tombolnya tidak
         * muncul dan menyesatkan.
         */
        bolehUnduh() {
            if (this.dokumen && typeof this.dokumen.Boleh_Unduh === 'boolean') return this.dokumen.Boleh_Unduh;

            return this.modeAkses === 'VIEW_UNDUH' || this.tipe === 'FORM';
        },
    },
    watch: {
        show(v) {
            if (typeof document !== 'undefined') document.body.style.overflow = v ? 'hidden' : '';
            if (v) {
                this.tontonDetik = 0;
                this.tontonTimer = setInterval(() => {
                    if (document.visibilityState === 'visible') this.tontonDetik += 1;
                }, 1000);
            } else if (this.tontonTimer) {
                clearInterval(this.tontonTimer);
                this.tontonTimer = null;
            }
        },
    },
    mounted() {
        window.addEventListener('keydown', this.onKey);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.onKey);
        if (this.tontonTimer) clearInterval(this.tontonTimer);
        if (typeof document !== 'undefined') document.body.style.overflow = '';
    },
    methods: {
        onKey(e) {
            if (e.key === 'Escape' && this.show) this.$emit('close');
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}
.konten-viewer {
    position: fixed;
    inset: 0;
    z-index: 1080;
    background: linear-gradient(180deg, var(--hcis-slate-50) 0%, #edf2f7 100%);
    display: flex;
    flex-direction: column;
    color: var(--hcis-slate-800);
    transition: background 0.3s ease;
}
.konten-viewer.is-video-theme {
    background: #0b1220;
    color: var(--hcis-white);
}

.kv-bar {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.6rem 0.9rem;
    background: rgba(255, 255, 255, 0.97);
    border-bottom: 1px solid var(--border, var(--hcis-slate-200));
    flex-shrink: 0;
    backdrop-filter: blur(12px);
}
.kv-back {
    border: 1px solid var(--border, var(--hcis-slate-200));
    background: #fff;
    color: var(--hcis-slate-700);
    width: 36px;
    height: 36px;
    border-radius: 10px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.16s ease;
}
.kv-back:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
    background: #f5f6ff;
}
.kv-titles {
    line-height: 1.15;
}
.kv-eyebrow {
    font-size: 0.64rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-indigo-500);
}
.kv-title {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.92rem;
    max-width: 60vw;
}
.konten-viewer.is-video-theme .kv-title {
    color: var(--hcis-slate-900);
}
.kv-spacer {
    flex: 1;
}
.kv-done {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.14);
    border-radius: 999px;
    padding: 0.24rem 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.kv-body {
    flex: 1;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    overflow: auto;
}
.kv-msg {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
}
.konten-viewer.is-video-theme .kv-msg {
    color: rgba(255, 255, 255, 0.85);
}
.kv-msg .bi {
    font-size: 2rem;
}
.kv-stage {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 30px 70px -30px rgba(0, 0, 0, 0.22);
}
.kv-stage.is-doc {
    width: 100%;
    max-width: 900px;
    height: 100%;
}
.kv-stage.is-video {
    width: 100%;
    max-width: 1100px;
    aspect-ratio: 16/9;
    height: auto;
    background: #000;
    box-shadow: 0 30px 70px -30px rgba(0, 0, 0, 0.7);
}
.kv-frame {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}

/* ── Cool Animated Skeleton Loader ── */
.kv-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    width: 100%;
    height: 100%;
}
.kv-loader-card {
    width: min(380px, 90vw);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(16px);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 20px;
    padding: 2rem 1.8rem;
    box-shadow: 0 20px 50px -15px rgba(15, 23, 42, 0.12);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
    animation: kvCardFade 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes kvCardFade {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.kv-loader-icon {
    position: relative;
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.18));
    color: var(--hcis-indigo-500);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}
.kv-icon-pulse {
    position: absolute;
    inset: -6px;
    border-radius: 22px;
    background: rgba(99, 102, 241, 0.15);
    animation: kvPulse 1.8s ease-in-out infinite;
}
@keyframes kvPulse {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.6;
    }
    50% {
        transform: scale(1.12);
        opacity: 0.15;
    }
}

.kv-loader-title {
    font-weight: 800;
    font-size: 1rem;
    color: var(--hcis-slate-900);
}
.kv-loader-sub {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    margin-top: 0.2rem;
}

.kv-loader-bar {
    width: 100%;
    height: 4px;
    background: #eef2f7;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 0.2rem;
}
.kv-loader-fill {
    height: 100%;
    width: 50%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6, var(--hcis-indigo-500));
    background-size: 200% 100%;
    animation: kvShimmer 1.5s linear infinite;
}
@keyframes kvShimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(200%);
    }
}

.kv-loader-skeletons {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    margin-top: 0.2rem;
}
.sk-line {
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--hcis-slate-100) 25%, var(--hcis-slate-300) 50%, var(--hcis-slate-100) 75%);
    background-size: 200% 100%;
    animation: skShimmer 1.5s infinite;
}
.sk-line.w-80 {
    width: 80%;
    margin: 0 auto;
}
.sk-line.w-100 {
    width: 100%;
}
.sk-line.w-60 {
    width: 60%;
    margin: 0 auto;
}
@keyframes skShimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

.kv-foot {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.7rem 1rem;
    background: rgba(255, 255, 255, 0.97);
    border-top: 1px solid var(--border, var(--hcis-slate-200));
    flex-shrink: 0;
    padding-bottom: max(0.7rem, env(safe-area-inset-bottom));
}
.kv-gate {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-amber-800);
    background: rgba(245, 158, 11, 0.14);
    border-radius: 999px;
    padding: 0.3rem 0.7rem;
}
.kv-done-note {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-emerald-700);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.kv-ro {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.kv-spin {
    display: inline-block;
    animation: kvSpin 0.9s linear infinite;
}
@keyframes kvSpin {
    to {
        transform: rotate(360deg);
    }
}

.kv-enter-active,
.kv-leave-active {
    transition: opacity 0.22s ease;
}
.kv-enter-from,
.kv-leave-to {
    opacity: 0;
}

@media (max-width: 575px) {
    .kv-stage.is-doc {
        max-width: 100%;
    }
    .kv-title {
        max-width: 46vw;
    }
}
</style>
