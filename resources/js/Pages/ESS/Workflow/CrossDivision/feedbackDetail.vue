<template>
    <div class="row d-flex justify-content-center">
        <div class="col-md-11">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="border-start border-primary border-4 ps-2">
                    Review {{ title }}
                </h3>
            </div>

            <!-- Card utama -->
            <div class="card shadow-sm border-0 overflow-hidden">
                <div
                    class="card-header bg-white d-flex justify-content-between align-items-center py-3"
                >
                    <h5 class="mb-0">Ringkasan Cross Review</h5>
                    <span class="badge bg-primary rounded-pill"
                        >Periode: {{ allscore.Kode_Periode }}</span
                    >
                </div>

                <div class="card-body">
                    <!-- Area Skor Total -->
                    <div class="row align-items-center">
                        <!-- Kolom Skor Total -->
                        <div class="col-md-4 text-center mb-4">
                            <h5 class="text-secondary mb-3">Total Score</h5>
                            <div
                                class="position-relative mb-3 mx-auto"
                                style="width: 180px; height: 180px"
                            >
                                <!-- Progress Circle -->
                                <svg
                                    class="position-absolute top-0 start-0"
                                    width="180"
                                    height="180"
                                    viewBox="0 0 180 180"
                                >
                                    <circle
                                        cx="90"
                                        cy="90"
                                        r="80"
                                        fill="none"
                                        stroke="#e9ecef"
                                        stroke-width="16"
                                    />
                                    <circle
                                        cx="90"
                                        cy="90"
                                        r="80"
                                        fill="none"
                                        :stroke="
                                            getTotalScoreColor(allscore.score)
                                        "
                                        stroke-width="16"
                                        stroke-dasharray="502"
                                        :stroke-dashoffset="
                                            502 - 502 * (allscore.score / 5)
                                        "
                                        transform="rotate(-90 90 90)"
                                        style="transition: all 1s ease-in-out"
                                    />
                                </svg>

                                <!-- Score Display -->
                                <div
                                    class="position-absolute top-50 start-50 translate-middle"
                                    style="z-index: 2"
                                >
                                    <h1 class="display-4 fw-bold mb-0">
                                        {{ Number(allscore.score).toFixed(1) }}
                                    </h1>
                                    <small class="text-muted"
                                        >dari 5 poin</small
                                    >
                                </div>
                            </div>

                            <!-- desk performa -->
                            <div
                                class="d-flex justify-content-center gap-3 mb-3"
                            >
                                <div class="d-flex align-items-center">
                                    <div
                                        class="rounded-circle me-1"
                                        style="
                                            width: 12px;
                                            height: 12px;
                                            background-color: #28a745;
                                        "
                                    ></div>
                                    <small>Sangat Baik (≥4.5)</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div
                                        class="rounded-circle me-1"
                                        style="
                                            width: 12px;
                                            height: 12px;
                                            background-color: #17a2b8;
                                        "
                                    ></div>
                                    <small>Baik (≥4.0)</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center gap-3">
                                <div class="d-flex align-items-center">
                                    <div
                                        class="rounded-circle me-1"
                                        style="
                                            width: 12px;
                                            height: 12px;
                                            background-color: #ffc107;
                                        "
                                    ></div>
                                    <small>Cukup (≥3.0)</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div
                                        class="rounded-circle me-1"
                                        style="
                                            width: 12px;
                                            height: 12px;
                                            background-color: #dc3545;
                                        "
                                    ></div>
                                    <small>Kurang (<3.0)</small>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Rangkuman Kategori -->
                        <div class="col-md-8">
                            <h5 class="text-secondary mb-3">
                                Kategori Penilaian
                            </h5>
                            <div class="row g-3">
                                <div
                                    v-for="(item, index) in dataScore"
                                    :key="index"
                                    class="col-md-6 col-sm-12"
                                >
                                    <div
                                        class="card h-100 border-0 shadow-sm hover-card"
                                    >
                                        <div class="card-body p-3">
                                            <div
                                                class="d-flex justify-content-between align-items-center mb-2"
                                            >
                                                <h6 class="mb-0 me-1">
                                                    {{ item.Name }}
                                                </h6>
                                                <div
                                                    class="score-badge"
                                                    :class="
                                                        getScoreBadgeClass(
                                                            item.score
                                                        )
                                                    "
                                                >
                                                    {{
                                                        Number(
                                                            item.score
                                                        ).toFixed(1)
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                class="progress"
                                                style="height: 8px"
                                            >
                                                <div
                                                    class="progress-bar"
                                                    :class="
                                                        getProgressBarClass(
                                                            item.score
                                                        )
                                                    "
                                                    role="progressbar"
                                                    :style="{
                                                        width:
                                                            (item.score / 5) *
                                                                100 +
                                                            '%',
                                                    }"
                                                    :aria-valuenow="item.score"
                                                    aria-valuemin="0"
                                                    aria-valuemax="5"
                                                ></div>
                                            </div>

                                            <!-- Icon dan deskripsi kategori -->
                                            <!-- <div
                                                class="d-flex align-items-center mt-3"
                                            >
                                                <div
                                                    class="category-icon me-2"
                                                    :class="
                                                        getCategoryIconClass(
                                                            index
                                                        )
                                                    "
                                                >
                                                    <i
                                                        :class="
                                                            getCategoryIcon(
                                                                index
                                                            )
                                                        "
                                                    ></i>
                                                </div>
                                                <small class="text-muted">{{
                                                    getCategoryDescription(
                                                        item.Name
                                                    )
                                                }}</small>
                                            </div> -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Komentar Review -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="text-secondary mb-3">Summary Review</h5>
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <textarea
                                        class="form-control bg-white"
                                        v-model="allscore.Keterangan"
                                        disabled
                                        rows="4"
                                        style="
                                            resize: none;
                                            border: 1px solid #dee2e6;
                                            border-radius: 0.375rem;
                                        "
                                    >
                                    </textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol aksi -->
                    <div class="d-flex justify-content-end mt-4">
                        <a href="/crossFeedbackReview" class="btn btn-danger">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { array, string } from "yup";

export default {
    props: {
        dataScore: Array,
        title: string,
        allscore: array,
    },
    methods: {
        getScoreClass(score) {
            if (score >= 4.5) return "border-success";
            if (score >= 4.0) return "border-info";
            if (score >= 3.0) return "border-warning";
            return "border-danger";
        },
        getScoreBadgeClass(score) {
            if (score >= 4.5) return "bg-success";
            if (score >= 4.0) return "bg-info";
            if (score >= 3.0) return "bg-warning";
            return "bg-danger";
        },
        getProgressBarClass(score) {
            if (score >= 4.5) return "bg-success";
            if (score >= 4.0) return "bg-info";
            if (score >= 3.0) return "bg-warning";
            return "bg-danger";
        },
        getTotalScoreColor(score) {
            if (score >= 4.5) return "#28a745";
            if (score >= 4.0) return "#17a2b8";
            if (score >= 3.0) return "#ffc107";
            return "#dc3545";
        },
        getCategoryIcon(index) {
            const icons = [
                "bi bi-lightning-charge-fill", // Responsivitas
                "bi bi-graph-up", // Kualitas Output
                "bi bi-people-fill", // Kolaborasi & Komunikasi
                "bi bi-kanban-fill", // Proses & Komunikasi Kerja
                "bi bi-tools", // Skills Professional
                "bi bi-diagram-3-fill", // Pemahaman Proses
            ];
            return icons[index % icons.length];
        },
        getCategoryIconClass(index) {
            const bgColors = [
                "bg-primary", // Biru
                "bg-success", // Hijau
                "bg-warning", // Kuning
                "bg-info", // Biru muda
                "bg-danger", // Merah
                "bg-secondary", // Abu-abu
            ];
            return bgColors[index % bgColors.length];
        },
        getCategoryDescription(categoryName) {
            const descriptions = {
                Responsivitas: "Kemampuan merespon dengan cepat & efektif",
                "Kualitas Output": "Hasil kerja berkualitas dan konsisten",
                "Kolaborasi & Komunikasi": "Kemampuan bekerja dalam tim",
                "Proses & Komunikasi Kerja":
                    "Mengoptimalkan proses kerja & komunikasi",
                "Skill Professional & Supportif":
                    "Keterampilan profesional & dukungan tim",
                "Pemahaman Terhadap Proses Divisi Lain":
                    "Pemahaman alur kerja divisi terkait",
            };

            return descriptions[categoryName] || categoryName;
        },
    },
};
</script>

<style scoped>
/* Custom styling untuk tambahan pada Bootstrap */
.hover-card {
    transition: all 0.3s ease;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}

.score-badge {
    font-weight: bold;
    color: white;
    min-width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

/* Animasi circle progress */
@keyframes progress {
    0% {
        stroke-dashoffset: 502;
    }
}

/* Customize breadcrumb */
.breadcrumb-item a {
    color: #6c757d;
}

.breadcrumb-item.active {
    font-weight: 500;
    color: #3182ce;
}

/* Custom styling untuk hover effects */
a:hover,
button:hover {
    opacity: 0.9;
    transition: all 0.2s ease;
}

textarea:disabled {
    background-color: #f8f9fa;
    opacity: 1;
}

/* Ganti warna scrollbar untuk tampilan modern */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>
