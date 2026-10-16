<template>
    <div>
        <transition name="fade">
            <div
                v-if="show && item"
                class="viewer-overlay"
                @click="$emit('close')"
            ></div>
        </transition>

        <transition name="viewer-slide">
            <div v-if="show && item" class="viewer-panel">
                <div class="viewer-header">
                    <div class="title-wrap">
                        <h4 class="viewer-title">Media Viewer</h4>
                        <p class="viewer-subtitle">{{ item?.name || "-" }}</p>
                    </div>
                    <button class="close-btn" @click="$emit('close')">X</button>
                </div>

                <div class="viewer-body">
                    <div v-if="viewType === 'video'" class="viewer-frame-wrap">
                        <iframe
                            v-if="videoSource"
                            :src="videoSource"
                            class="viewer-iframe"
                            frameborder="0"
                            allow="
                                accelerometer;
                                gyroscope;
                                autoplay;
                                encrypted-media;
                                picture-in-picture;
                            "
                            allowfullscreen
                        ></iframe>
                    </div>

                    <div
                        v-else-if="viewType === 'image'"
                        class="viewer-image-wrap"
                    >
                        <img
                            :src="imageSource"
                            :alt="item?.name || 'image'"
                            class="viewer-image"
                        />
                    </div>

                    <div
                        v-else-if="viewType === 'audio'"
                        class="viewer-audio-wrap"
                    >
                        <audio
                            controls
                            class="viewer-audio"
                            :src="audioSource"
                        ></audio>
                    </div>

                    <div v-else-if="viewType === 'pdf'" class="viewer-frame-wrap">
                        <iframe
                            v-if="documentSource"
                            :src="documentSource"
                            class="viewer-iframe"
                            frameborder="0"
                        ></iframe>
                        <div v-else class="viewer-empty">
                            Preview tidak tersedia untuk file ini.
                        </div>
                    </div>

                    <!--
                        Format tanpa penyaji. TIDAK ada iframe di sini dan tidak
                        boleh ada: satu-satunya cara menampilkan docx/xlsx/pptx di
                        peramban adalah menyerahkan URL-nya ke penampil pihak
                        ketiga, dan itu berarti mengirim dokumen internal ke luar.
                    -->
                    <div v-else class="viewer-unduh">
                        <div class="unduh-kartu">
                            <div class="unduh-ikon"><i class="bi bi-file-earmark-arrow-down"></i></div>
                            <p class="unduh-judul">{{ labelFormat }} tidak bisa dibuka di aplikasi</p>
                            <p class="unduh-teks">
                                Pembaca dokumen di sini hanya mengerti PDF. Berkas ini bisa diunduh lalu dibuka
                                dengan aplikasi di komputer Anda.
                            </p>
                            <button class="unduh-btn" :disabled="mengunduh" @click="$emit('unduh', item)">
                                <i class="bi" :class="mengunduh ? 'bi-hourglass-split' : 'bi-download'"></i>
                                {{ mengunduh ? 'Menyiapkan...' : 'Unduh Berkas' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
export default {
    name: "MediaViewerPanel",
    props: {
        show: { type: Boolean, default: false },
        item: { type: Object, default: null },
        mengunduh: { type: Boolean, default: false },
    },
    emits: ["close", "unduh"],
    computed: {
        normalizedType() {
            const raw = (
                this.item?.fileType ||
                this.item?.type ||
                ""
            ).toLowerCase();
            if (["video", "image", "audio", "pdf", "document"].includes(raw)) {
                return raw;
            }

            const mime = (this.item?.metadata?.typeFile || "").toLowerCase();
            if (mime.startsWith("video/")) return "video";
            if (mime.startsWith("image/")) return "image";
            if (mime.startsWith("audio/")) return "audio";
            if (mime.includes("pdf")) return "pdf";
            return "document";
        },
        /*
         * Yang menentukan penyaji adalah `tampilan` dari server, bukan tebakan
         * dari mime/ekstensi di sini. Tanpa nilai itu (data lama), jatuh ke
         * 'unduh' — pilihan paling tertutup, bukan paling permisif.
         */
        viewType() {
            const tampilan = (this.item?.tampilan || "").toLowerCase();
            if (tampilan === "putar") return "video";
            if (tampilan === "inline") return "image";
            if (tampilan === "viewer") return "pdf";
            if (tampilan === "unduh") return "unduh";

            if (this.normalizedType === "video") return "video";
            if (this.normalizedType === "image") return "image";
            if (this.normalizedType === "audio") return "audio";
            if (this.normalizedType === "pdf") return "pdf";
            return "unduh";
        },
        labelFormat() {
            return this.item?.extLabel || (this.item?.ext || "Format ini").toUpperCase();
        },
        videoSource() {
            return (
                this.item?.embedUrl ||
                this.item?.playbackUrl ||
                this.item?.fileUrl ||
                ""
            );
        },
        imageSource() {
            return (
                this.item?.previewUrl ||
                this.item?.thumbnailUrl ||
                this.item?.fileUrl ||
                ""
            );
        },
        audioSource() {
            return this.item?.fileUrl || this.item?.playbackUrl || "";
        },
        /*
         * Hanya PDF. Fallback ke Google Docs Viewer sengaja DIHAPUS: ia bekerja
         * dengan mengirim URL bertanda tangan ke docs.google.com, yang lalu
         * menarik sendiri berkasnya — artinya dokumen SOP internal berpindah ke
         * pihak ketiga hanya untuk sebuah pratinjau, tanpa admin pernah diberi
         * tahu bahwa itu terjadi.
         */
        documentSource() {
            if (this.viewType !== "pdf") return "";

            return this.item?.fileUrl || this.item?.previewUrl || "";
        },
    },
    watch: {
        show(newVal) {
            document.body.style.overflow = newVal ? "hidden" : "auto";
        },
    },
};
</script>

<style scoped>
.viewer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(2px);
    z-index: 1080;
}

.viewer-panel {
    position: fixed;
    z-index: 1090;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.viewer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 18px;
    border-bottom: 1px solid var(--hcis-slate-100);
    background: var(--hcis-slate-50);
}

.viewer-title {
    margin: 0;
    font-size: 1.1rem;
    color: var(--hcis-slate-900);
    font-weight: 800;
}

.viewer-subtitle {
    margin: 2px 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.82rem;
}

.close-btn {
    border: none;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    width: 34px;
    height: 34px;
    border-radius: 10px;
    font-weight: 800;
}

.viewer-body {
    flex: 1;
    background: var(--hcis-slate-900);
    min-height: 0;
}

.viewer-frame-wrap,
.viewer-image-wrap,
.viewer-audio-wrap {
    width: 100%;
    height: 100%;
    min-height: 300px;
}

.viewer-iframe,
.viewer-image {
    width: 100%;
    height: 100%;
    border: none;
    object-fit: contain;
    background: var(--hcis-slate-900);
}

.viewer-audio-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 24px;
    background: #111827;
}

.viewer-audio {
    width: 100%;
    max-width: 720px;
}

/* Panel untuk format yang tak punya penyaji */
.viewer-unduh {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.unduh-kartu {
    max-width: 420px;
    text-align: center;
    color: var(--hcis-slate-200);
}

.unduh-ikon {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #93c5fd;
    background: rgba(148, 163, 184, 0.12);
    border: 1px solid rgba(148, 163, 184, 0.25);
}

.unduh-judul {
    font-size: 1.02rem;
    font-weight: 700;
    margin: 0 0 8px;
    color: var(--hcis-slate-50);
}

.unduh-teks {
    font-size: 0.85rem;
    line-height: 1.6;
    color: var(--hcis-slate-400);
    margin: 0 0 22px;
}

.unduh-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    background: var(--hcis-slate-200);
    transition: background 0.2s ease;
}

.unduh-btn:hover:not(:disabled) {
    background: var(--hcis-white);
}

.unduh-btn:disabled {
    opacity: 0.6;
}

.viewer-empty {
    color: var(--hcis-slate-300);
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-weight: 600;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.viewer-slide-enter-active,
.viewer-slide-leave-active {
    transition:
        transform 0.25s ease,
        opacity 0.25s ease;
}

@media (min-width: 992px) {
    .viewer-panel {
        top: 4vh;
        left: 50%;
        transform: translateX(-50%);
        width: min(1100px, 94vw);
        height: 92vh;
        border-radius: 16px;
    }

    .viewer-slide-enter-from,
    .viewer-slide-leave-to {
        transform: translate(-50%, 20px);
        opacity: 0;
    }
}

@media (max-width: 991px) {
    .viewer-panel {
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 86vh;
        border-radius: 16px 16px 0 0;
    }

    .viewer-slide-enter-from,
    .viewer-slide-leave-to {
        transform: translateY(100%);
        opacity: 0;
    }
}
</style>
