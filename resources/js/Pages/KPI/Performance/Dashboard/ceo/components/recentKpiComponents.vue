<template>
    <div
        class="kpi-item-card"
        :class="{
            'disabled-card': !item.is_input_period,
            'kpi-item-card--dropdown-open': isDropdownOpen
        }"
    >
        <div v-if="!item.is_input_period" class="card-overlay">
            <div
                class="d-flex flex-column align-items-center justify-content-center text-center w-100"
            >
                <div class="d-flex flex-column align-items-center">
                    <span class="fw-bold text-break" style="max-width: 250px">
                        <i class="bi bi-calendar-x fs-2 mb-4"></i> Periode Belum
                        Aktif
                    </span>
                </div>
            </div>
        </div>

        <div class="kpi-header">
            <h6 class="kpi-parameter">{{ item.parameter }}</h6>
            <span class="kpi-bobot">Bobot: {{ item.Bobot }}%</span>
        </div>

        <div v-if="deskripsi" class="kpi-deskripsi-wrap">
            <p
                ref="elDeskripsi"
                class="kpi-deskripsi"
                :class="{ 'kpi-deskripsi--ringkas': !deskripsiTerbuka }"
            >
                {{ deskripsi }}
            </p>
            <button
                v-if="deskripsiTerpotong"
                type="button"
                class="kpi-deskripsi-toggle"
                @click="deskripsiTerbuka = !deskripsiTerbuka"
            >
                {{ deskripsiTerbuka ? "Ringkas" : "Selengkapnya" }}
            </button>
        </div>

        <div class="kpi-progress-wrapper">
            <div class="progress">
                <div
                    class="progress-bar"
                    role="progressbar"
                    :style="{
                        width: (item.achievement || 0) + '%',
                        backgroundColor: item.statusWarna || barColor,
                    }"
                    :aria-valuenow="item.achievement || 0"
                    aria-valuemin="0"
                    aria-valuemax="100"
                ></div>
            </div>
            <span class="progress-percentage"
                >{{ formatScore(item.achievement) }}%
            </span>
        </div>

        <div class="kpi-stats">
            <div class="stat-item">
                <span class="stat-label">Target</span>
                <span class="stat-value">{{ formattedTarget }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Actual</span>
                <span class="stat-value">{{ formattedActual }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Score</span>
                <span class="stat-value text-primary fw-bold">{{
                    formatScore(item.score)
                }}</span>
            </div>
        </div>

        <div v-if="item.formula" class="kpi-formula-section mt-2">
            <small class="text-muted">
                <i class="bi bi-calculator me-1"></i>
                Rumus: {{ item.formula }}
            </small>
        </div>

        <div v-if="picaList.length > 0" class="kpi-pica-section">
            <hr class="pica-divider" />
            <h6 class="pica-main-title">
                PICA (Problem Identification & Corrective Action)
                <span class="badge bg-danger ms-2">{{ picaList.length }}</span>
            </h6>

            <div
                class="accordion accordion-flush"
                :id="`picaAccordion-${item.id}`"
            >
                <div
                    class="accordion-item"
                    v-for="(pica, index) in picaList"
                    :key="index"
                >
                    <h2
                        class="accordion-header"
                        :id="`pica-heading-${item.id}-${index}`"
                    >
                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            :data-bs-target="`#pica-collapse-${item.id}-${index}`"
                            aria-expanded="false"
                            :aria-controls="`pica-collapse-${item.id}-${index}`"
                        >
                            <span class="pica-accordion-title">
                                {{
                                    pica.periode
                                        ? `Periode ${pica.periode}`
                                        : "PICA"
                                }}
                                <small class="pica-accordion-date">
                                    (Due Date:
                                    {{ formatDate(pica.targetDate) }})
                                </small>
                            </span>
                        </button>
                    </h2>

                    <div
                        :id="`pica-collapse-${item.id}-${index}`"
                        class="accordion-collapse collapse"
                        :aria-labelledby="`pica-heading-${item.id}-${index}`"
                        :data-bs-parent="`#picaAccordion-${item.id}`"
                    >
                        <div class="accordion-body">
                            <div class="pica-detail-item pica-detail-item--problem">
                                <label class="pica-label">
                                    <i class="bi bi-exclamation-circle-fill me-1"></i>Problem / Akar Masalah:
                                </label>
                                <p class="pica-text">
                                    {{ pica.problem || "-" }}
                                </p>
                            </div>
                            <div class="pica-detail-item pica-detail-item--action">
                                <label class="pica-label">
                                    <i class="bi bi-check-circle-fill me-1"></i>Action / Rencana Tindakan:
                                </label>
                                <p class="pica-text">
                                    {{ pica.action || "-" }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="kpi-footer">
            <span
                class="status-badge text-white"
                :style="{ backgroundColor: item.statusWarna }"
            >
                {{ item.statusKeterangan || "N/A" }}
            </span>

            <!-- No file state -->
            <span
                v-if="parsedFiles.length === 0"
                class="file-link disabled"
            >
                <i class="bi bi-ban"></i>
                <span>No File</span>
            </span>

            <!-- Single file attachment button -->
            <button
                v-else-if="parsedFiles.length === 1"
                type="button"
                class="file-link file-link--btn"
                :title="`Lihat ${parsedFiles[0].name}`"
                @click="openFileViewer(0)"
            >
                <i class="bi bi-paperclip"></i>
                <span>{{ parsedFiles[0].name }}</span>
            </button>

            <!-- Multiple files / YTD attachment dropdown & direct viewer button -->
            <div
                v-else
                ref="dropdownEl"
                class="dropdown file-dropdown"
            >
                <div class="btn-group btn-group-sm">
                    <button
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5"
                        type="button"
                        :title="`Lihat ${parsedFiles.length} Lampiran Bukti`"
                        @click="openFileViewer(0)"
                    >
                        <i class="bi bi-folder2-open"></i>
                        <span>Lampiran ({{ parsedFiles.length }})</span>
                    </button>
                    <button
                        class="btn btn-sm btn-outline-primary dropdown-toggle dropdown-toggle-split px-2"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        title="Pilih Berkas Tertentu"
                    >
                        <span class="visually-hidden">Pilih Berkas</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="dropdown-header small text-muted">
                            Pilih untuk buka langsung (atau geser di viewer):
                        </li>
                        <li
                            v-for="(fileItem, index) in parsedFiles"
                            :key="index"
                        >
                            <button
                                type="button"
                                class="dropdown-item d-flex align-items-center gap-2"
                                :title="fileItem.name"
                                @click="openFileViewer(index)"
                            >
                                <i class="bi bi-file-earmark-text text-primary"></i>
                                <span class="text-truncate">{{ fileItem.name }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- HcisFileViewer Component -->
        <HcisFileViewer
            v-model="isFileViewerOpen"
            :files="parsedFiles"
            :initial-index="viewerActiveIndex"
            :title="item.parameter || 'Lampiran Bukti KPI'"
        />
    </div>
</template>

<script>
import { HcisFileViewer } from "@/components/ui";

export default {
    name: "KpiDetailItem",
    components: {
        HcisFileViewer,
    },
    props: {
        item: {
            type: Object,
            required: true,
        },
        barColor: {
            type: String,
            default: "rgba(52, 152, 219, 1)",
        },
        isFullYear: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            deskripsiTerbuka: false,
            // Tombol "Selengkapnya" hanya muncul bila teksnya BENAR-BENAR
            // terpotong. Diukur dari elemennya, bukan ditebak dari jumlah
            // huruf: lebar kartu berubah mengikuti layar, jadi tebakan panjang
            // akan menawarkan tombol yang tidak membuka apa-apa.
            deskripsiTerpotong: false,
            isFileViewerOpen: false,
            viewerActiveIndex: 0,
            isDropdownOpen: false,
        };
    },
    computed: {
        deskripsi() {
            return (this.item?.deskripsi || "").trim();
        },
        parsedFiles() {
            const raw = this.item.File_Berkas_Url;
            if (!raw) return [];

            // Case 1: String URL
            if (typeof raw === "string" && raw.trim() !== "") {
                let fileName = "Lampiran Bukti";
                if (this.item.dataSourceFile && this.item.dataSourceFile.name) {
                    fileName = this.getCleanFileName(this.item.dataSourceFile.name);
                } else {
                    const clean = this.getCleanFileName(raw);
                    if (clean && clean !== "View Data Source") fileName = clean;
                }
                return [
                    {
                        url: raw,
                        name: fileName,
                        title: `${this.item.parameter || "KPI"} - ${fileName}`,
                    },
                ];
            }

            // Case 2: Array of files (e.g. YTD or multiple months)
            if (Array.isArray(raw) && raw.length > 0) {
                return raw
                    .map((f, idx) => {
                        const fileUrl = typeof f === "string" ? f : f?.url;
                        if (!fileUrl) return null;

                        let fileName = this.getDropdownItemText(f, idx);
                        if (
                            this.item.dataSourceFile &&
                            Array.isArray(this.item.dataSourceFile) &&
                            this.item.dataSourceFile[idx]?.name
                        ) {
                            fileName = this.getCleanFileName(this.item.dataSourceFile[idx].name);
                        }

                        const periodeTag =
                            typeof f === "object" && (f.periode || f.month)
                                ? ` (${f.periode || f.month})`
                                : "";

                        return {
                            url: fileUrl,
                            name: `${fileName}${periodeTag}`,
                            title: `${this.item.parameter || "KPI"} - ${fileName}${periodeTag}`,
                        };
                    })
                    .filter(Boolean);
            }

            return [];
        },
        formattedTarget() {
            return this.formatValue(
                this.item.targetValue,
                this.item.targetUnit
            );
        },
        formattedActual() {
            return this.formatValue(this.item.actual, this.item.targetUnit);
        },
        progressBarWidth() {
            const achievement = this.item.achievement || 0;
            const width = Math.min(achievement, 100);
            return width + "%";
        },
        picaList() {
            const picaData = this.item.pica;

            if (!picaData) {
                return [];
            }

            if (Array.isArray(picaData)) {
                return picaData;
            }

            if (
                typeof picaData === "object" &&
                (picaData.problem || picaData.action)
            ) {
                return [picaData];
            }

            return [];
        },
    },
    watch: {
        deskripsi() {
            this.deskripsiTerbuka = false;
            this.$nextTick(this.ukurDeskripsi);
        },
    },
    mounted() {
        this.$nextTick(() => {
            this.ukurDeskripsi();
            if (this.$refs.dropdownEl) {
                this.$refs.dropdownEl.addEventListener("show.bs.dropdown", this.onDropdownShow);
                this.$refs.dropdownEl.addEventListener("hide.bs.dropdown", this.onDropdownHide);
            }
        });
        window.addEventListener("resize", this.ukurDeskripsi);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.ukurDeskripsi);
        if (this.$refs.dropdownEl) {
            this.$refs.dropdownEl.removeEventListener("show.bs.dropdown", this.onDropdownShow);
            this.$refs.dropdownEl.removeEventListener("hide.bs.dropdown", this.onDropdownHide);
        }
    },
    methods: {
        onDropdownShow() {
            this.isDropdownOpen = true;
        },
        onDropdownHide() {
            this.isDropdownOpen = false;
        },
        ukurDeskripsi() {
            const el = this.$refs.elDeskripsi;

            if (!el) {
                this.deskripsiTerpotong = false;

                return;
            }

            // Saat sedang terbuka, klemnya dilepas sehingga tingginya sama —
            // ukur ulang cuma bikin tombolnya hilang di tengah bacaan.
            if (this.deskripsiTerbuka) {
                return;
            }

            this.deskripsiTerpotong = el.scrollHeight > el.clientHeight + 1;
        },
        openFileViewer(index = 0) {
            this.viewerActiveIndex = index;
            this.isFileViewerOpen = true;
        },
        getCleanFileName(originalName) {
            if (!originalName || typeof originalName !== "string") {
                return "View Data Source";
            }
            const prefix = "KPI/data-source/";
            if (originalName.startsWith(prefix)) {
                return originalName.substring(prefix.length);
            }
            return originalName;
        },
        getDropdownItemText(fileUrl, index) {
            if (
                this.item.dataSourceFile &&
                Array.isArray(this.item.dataSourceFile) &&
                this.item.dataSourceFile[index]
            ) {
                if (this.item.dataSourceFile[index].name) {
                    return this.getCleanFileName(
                        this.item.dataSourceFile[index].name
                    );
                }
            }
            if (fileUrl.periode) {
                return `Bukti ${fileUrl.periode}`;
            }
            if (fileUrl.month) {
                return `Bukti ${fileUrl.month}`;
            }
            return `Bukti File ${index + 1}`;
        },

        isString(value) {
            return typeof value === "string" || value instanceof String;
        },
        isArray(value) {
            return Array.isArray(value);
        },

        formatScore(value) {
            if (typeof value !== "number" || !isFinite(value)) {
                return 0;
            }
            if (Number.isInteger(value)) {
                return value;
            }

            let rounded = parseFloat(value.toFixed(1));

            if (Number.isInteger(rounded)) {
                return rounded;
            }

            return rounded;
        },
        formatValue(value, unit) {
            if (value === null || value === undefined) return "-";
            const number = parseFloat(value);
            if (isNaN(number)) return "-";

            const formattedNumber = number.toLocaleString("id-ID", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            if (unit === "Rp") {
                return `Rp ${formattedNumber}`;
            }
            return `${formattedNumber} ${unit || ""}`;
        },

        // --- METHOD BARU UNTUK PICA ---
        formatDate(dateString) {
            if (!dateString) return "N/A";
            const options = {
                year: "numeric",
                month: "short",
                day: "numeric",
            };
            try {
                // Mengganti 'Okt' ke 'Oct' agar konsisten bisa diparsing
                const parsableDate = dateString.replace("Okt", "Oct");
                const date = new Date(parsableDate);

                if (isNaN(date.getTime())) {
                    return dateString; // Kembalikan string asli jika parsing gagal
                }
                return date.toLocaleDateString("id-ID", options);
            } catch (e) {
                return dateString; // Fallback
            }
        },
    },
};
</script>

<style scoped>
.kpi-item-card {
    background-color: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 0.875rem);
    padding: 1.15rem 1.25rem;
    box-shadow:
        0 1px 3px 0 rgba(15, 23, 42, 0.03),
        0 1px 2px -1px rgba(15, 23, 42, 0.02);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
}

.kpi-item-card:hover {
    box-shadow: 0 6px 18px -3px rgba(15, 23, 42, 0.08);
    border-color: rgba(199, 210, 254, 0.95);
    transform: translateY(-1px);
    z-index: 5;
}

.kpi-item-card:focus-within {
    z-index: 30;
}

.kpi-item-card:has(.show),
.kpi-item-card:has(.dropdown-menu.show),
.kpi-item-card--dropdown-open {
    z-index: 100 !important;
}

.disabled-card .kpi-header,
.disabled-card .kpi-deskripsi-wrap,
.disabled-card .kpi-progress-wrapper,
.disabled-card .kpi-stats,
.disabled-card .kpi-formula-section,
.disabled-card .kpi-pica-section,
.disabled-card .kpi-footer {
    opacity: 0.45;
    pointer-events: none;
    transition: opacity 0.3s ease;
}

.card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(248, 250, 252, 0.92);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    border-radius: var(--hcis-radius-xl, 0.875rem);
    z-index: 10;
    color: #475569;
    text-align: center;
    padding: 1rem;
}

.card-overlay i {
    color: #64748b;
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    gap: 0.75rem;
}

.kpi-parameter {
    font-size: 0.9375rem;
    font-weight: 750;
    color: #0f172a;
    margin: 0;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    letter-spacing: -0.01em;
}

.kpi-bobot {
    font-size: 0.75rem;
    font-weight: 700;
    color: #4f46e5;
    background-color: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 0.2rem 0.6rem;
    border-radius: var(--hcis-radius-full, 9999px);
    white-space: nowrap;
    flex-shrink: 0;
}

.kpi-deskripsi-wrap {
    margin: -0.25rem 0 0.875rem;
}

.kpi-deskripsi {
    font-size: 0.8125rem;
    line-height: 1.45;
    color: #64748b;
    margin: 0;
    white-space: pre-line;
}

.kpi-deskripsi--ringkas {
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
}

.kpi-deskripsi-toggle {
    background: none;
    border: 0;
    padding: 0;
    margin-top: 0.2rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-primary, #4f46e5);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
}

.kpi-deskripsi-toggle:hover {
    text-decoration: underline;
}

.kpi-progress-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.875rem;
}

.progress {
    flex-grow: 1;
    height: 7px;
    background-color: #f1f5f9;
    border-radius: var(--hcis-radius-full, 9999px);
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    border-radius: var(--hcis-radius-full, 9999px);
    transition: width 0.4s ease, background-color 0.4s ease;
}

.progress-percentage {
    font-size: 0.8125rem;
    font-weight: 800;
    color: #1e293b;
    white-space: nowrap;
    font-family: var(--hcis-font-sans, sans-serif);
}

.kpi-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: var(--hcis-radius-md, 0.625rem);
    padding: 0.55rem 0.8rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.stat-label {
    font-size: 0.625rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.15rem;
}

.stat-value {
    font-size: 0.875rem;
    font-weight: 750;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
}

.kpi-formula-section {
    font-size: 0.75rem;
    color: #64748b;
    background: rgba(241, 245, 249, 0.65);
    padding: 0.35rem 0.65rem;
    border-radius: var(--hcis-radius-md, 0.45rem);
    border: 1px solid #f1f5f9;
    margin-bottom: 0.5rem;
}

.kpi-pica-section {
    margin-top: 0.75rem;
}

.pica-divider {
    border-top: 1px dashed #e2e8f0;
    margin: 0.75rem 0;
}

.pica-main-title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: #1e293b;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.pica-main-title .badge {
    font-size: 0.7rem;
    line-height: 1.2;
    padding: 0.2rem 0.45rem;
    border-radius: var(--hcis-radius-full, 9999px);
    font-weight: 700;
}

.accordion-item {
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 0.5rem) !important;
    margin-bottom: 0.5rem;
    overflow: hidden;
    background-color: #ffffff;
}

.accordion-item:last-child {
    margin-bottom: 0;
}

.accordion-header {
    border-bottom: 0;
}

.accordion-button {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #1e293b;
    background-color: #f8fafc;
    padding: 0.5rem 0.75rem;
}

.accordion-button:not(.collapsed) {
    background-color: #eef2ff;
    color: #4338ca;
    box-shadow: none;
}

.accordion-button:focus {
    box-shadow: none;
}

.pica-accordion-title {
    display: flex;
    flex-direction: column;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding-right: 0.5rem;
}

.pica-accordion-date {
    font-size: 0.72rem;
    font-weight: 500;
    color: #64748b;
}

.accordion-body {
    padding: 0.65rem 0.75rem;
    background-color: #ffffff;
    border-top: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pica-detail-item {
    padding: 0.5rem 0.65rem;
    border-radius: var(--hcis-radius-md, 0.45rem);
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}

.pica-detail-item--problem {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    border-left: 3.5px solid #ef4444;
}

.pica-detail-item--problem .pica-label {
    color: #dc2626;
}

.pica-detail-item--action {
    background: #ecfdf5;
    border: 1px solid #d1fae5;
    border-left: 3.5px solid #10b981;
}

.pica-detail-item--action .pica-label {
    color: #047857;
}

.pica-label {
    font-size: 0.625rem;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.2rem;
    display: flex;
    align-items: center;
}

.pica-text {
    font-size: 0.8125rem;
    color: #1e293b;
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
    line-height: 1.4;
}

.kpi-footer {
    margin-top: auto;
    padding-top: 0.875rem;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}

.status-badge {
    font-size: 0.75rem;
    font-weight: 750;
    padding: 0.25rem 0.65rem;
    border-radius: var(--hcis-radius-full, 9999px);
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.file-link {
    font-size: 0.78rem;
    color: #4f46e5;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.15s ease;
    display: inline-flex;
    align-items: center;
    max-width: 180px;
}

.file-link--btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    text-align: left;
}

.file-link i {
    margin-right: 0.35rem;
    flex-shrink: 0;
}

.file-link span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-link:hover {
    color: #3730a3;
    text-decoration: underline;
}

.file-link.disabled {
    color: #94a3b8;
    cursor: not-allowed;
    text-decoration: none;
}

.file-dropdown {
    position: relative;
}

.file-dropdown:has(.show) {
    z-index: 100;
}

.file-dropdown .btn-sm {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: var(--hcis-radius-md, 0.375rem);
    border-color: #cbd5e1;
    color: #4f46e5;
}

.file-dropdown .btn-sm:hover {
    background-color: #eef2ff;
    border-color: #a5b4fc;
}

.file-dropdown .dropdown-menu {
    max-height: 250px;
    overflow-y: auto;
    font-size: 0.8125rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.18), 0 4px 12px -2px rgba(15, 23, 42, 0.08);
    border: 1px solid #cbd5e1;
    z-index: 1060 !important;
    background-color: #ffffff;
}

.file-dropdown .dropdown-item {
    font-size: 0.8125rem;
    display: flex;
    align-items: center;
    max-width: 350px;
    padding: 0.4rem 0.75rem;
}

.file-dropdown .dropdown-item i {
    color: #64748b;
    flex-shrink: 0;
}

.file-dropdown .dropdown-item span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-grow: 1;
    min-width: 0;
}

@media (max-width: 768px) {
    .kpi-item-card {
        padding: 1rem;
    }
}
</style>
