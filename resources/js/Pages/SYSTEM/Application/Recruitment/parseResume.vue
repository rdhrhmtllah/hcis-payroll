<template>
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                    <!-- Enhanced Header -->
                    <div
                        class="card-header bg-gradient text-white p-4 position-relative"
                    >
                        <div class="header-overlay"></div>
                        <div
                            class="d-flex align-items-center justify-content-between position-relative"
                        >
                            <div class="header-text">
                                <h2 class="fw-bold mb-2 fs-3 generateText">
                                    Generate Resume with AI
                                </h2>
                                <p class="mb-0 opacity-75">
                                    Pt. Evo Nusa Bersaudara
                                </p>
                            </div>
                            <div
                                class="ai-animation bg-white bg-opacity-10 rounded-circle p-2 backdrop-blur"
                            >
                                <DotLottieVue
                                    style="height: 70px; width: 70px"
                                    autoplay
                                    loop
                                    src="/animation/AI.json"
                                />
                            </div>
                        </div>

                        <!-- Progress Indicator -->
                        <div
                            class="d-flex align-items-center justify-content-center mt-4 position-relative"
                        >
                            <div class="d-flex align-items-center">
                                <div
                                    class="progress-step"
                                    :class="{ active: !parsedData }"
                                >
                                    <div
                                        class="step-circle bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center"
                                    >
                                        <span class="fw-semibold">1</span>
                                    </div>
                                    <small class="mt-2 fw-medium">Upload</small>
                                </div>
                                <div
                                    class="progress-line bg-white bg-opacity-30 mx-4"
                                ></div>
                                <div
                                    class="progress-step"
                                    :class="{ active: parsedData }"
                                >
                                    <div
                                        class="step-circle bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center"
                                    >
                                        <span class="fw-semibold">2</span>
                                    </div>
                                    <small class="mt-2 fw-medium">Review</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Section -->
                    <div v-if="!parsedData" class="card-body p-4 p-md-5">
                        <div class="upload-container">
                            <div
                                @dragover.prevent="handleDragOver"
                                @dragleave.prevent="handleDragLeave"
                                @drop.prevent="handleDrop"
                                @click="triggerFileInput"
                                :class="{
                                    'border-primary bg-primary bg-opacity-10':
                                        isDragOver,
                                    'border-secondary':
                                        !isDragOver && !uploading,
                                    'border-muted': uploading,
                                }"
                                class="dropzone border border-2 border-dashed rounded-4 p-5 text-center position-relative transition-all"
                                style="cursor: pointer; min-height: 300px"
                            >
                                <input
                                    :disabled="uploading"
                                    type="file"
                                    ref="fileInput"
                                    class="d-none"
                                    accept=".pdf"
                                    @change="handleFileSelect"
                                />

                                <div
                                    v-if="uploading"
                                    class="upload-state flex-column text-center"
                                    style="
                                        display: flex;

                                        justify-content: center;
                                        align-items: center;
                                    "
                                >
                                    <DotLottieVue
                                        style="height: 80px; width: 80px"
                                        autoplay
                                        loop
                                        src="/animation/loading2.lottie"
                                    />
                                    <h4 class="mt-3 fw-semibold text-muted">
                                        Processing your CV...
                                    </h4>
                                    <p class="text-muted mb-0">
                                        This may take a few moments
                                    </p>
                                </div>

                                <div v-else-if="file" class="file-selected">
                                    <div class="file-icon text-danger mb-3">
                                        <i
                                            class="bi bi-file-earmark-pdf-fill"
                                            style="font-size: 3rem"
                                        ></i>
                                    </div>
                                    <h4 class="fw-semibold text-dark mb-2">
                                        {{ file.name }}
                                    </h4>
                                    <p class="text-muted mb-3">
                                        {{ formatFileSize(file.size) }} • Ready
                                        to process
                                    </p>
                                    <button
                                        @click.stop="removeFile"
                                        class="btn btn-danger btn-sm rounded-circle trashButton"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <div v-else class="upload-prompt">
                                    <div class="upload-icon text-primary mb-3">
                                        <i
                                            class="bi bi-cloud-upload"
                                            style="font-size: 3rem"
                                        ></i>
                                    </div>
                                    <h4 class="fw-semibold text-dark mb-2">
                                        Drop your CV here
                                    </h4>
                                    <p class="text-muted mb-3">
                                        or
                                        <span class="text-primary fw-medium"
                                            >click to browse</span
                                        >
                                    </p>
                                    <div
                                        class="d-flex gap-2 justify-content-center"
                                    >
                                        <span class="badge bg-primary rounded"
                                            >PDF</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 text-center">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Maximum file size: 30MB
                                </small>
                            </div>
                        </div>

                        <!-- File Preview -->
                        <div v-if="file && previewUrl" class="mt-4">
                            <div class="card border">
                                <div
                                    class="card-header bg-light d-flex align-items-center justify-content-between py-3"
                                >
                                    <h5
                                        class="mb-0 d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi m-0 bi-eye me-2 d-flex align-items-center"
                                        ></i
                                        >File Preview
                                    </h5>
                                    <button
                                        @click="togglePreview"
                                        class="btn btn-outline-secondary btn-sm"
                                    >
                                        <i
                                            :class="
                                                showPreview
                                                    ? 'bi-chevron-up'
                                                    : 'bi-chevron-down'
                                            "
                                        ></i>
                                    </button>
                                </div>
                                <div v-show="showPreview" class="card-body p-3">
                                    <iframe
                                        :src="previewUrl"
                                        class="w-100 border rounded"
                                        style="height: 400px"
                                        type="application/pdf"
                                    ></iframe>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div
                            class="d-flex gap-3 justify-content-end mt-4 pt-3 border-top"
                        >
                            <button
                                @click="cancelUpload"
                                class="btn btn-outline-secondary"
                                :disabled="uploading"
                            >
                                <i class="bi bi-arrow-left me-2"></i>
                                Cancel
                            </button>
                            <button
                                :disabled="uploading || !file"
                                @click="submitFile"
                                class="btn btn-primary px-4"
                            >
                                <i class="bi bi-magic me-2"></i>
                                Parse Resume
                            </button>
                        </div>
                    </div>

                    <!-- Form Section -->
                    <div v-else class="card-body p-4 p-md-5">
                        <div class="text-center mb-5">
                            <h3
                                class="fw-bold d-flex align-items-center justify-content-center"
                            >
                                <i
                                    class="bi bi-person-check me-2 text-success m-0 d-flex justify-content-center align-items-center me-2"
                                ></i>
                                Review & Edit Information
                            </h3>
                            <p class="text-muted mb-0">
                                Please verify and edit the extracted information
                                before generating your resume
                            </p>
                        </div>

                        <form @submit.prevent="submitParsedData">
                            <input type="hidden" v-model="tempPath" />

                            <!-- Personal Information -->
                            <div class="card mb-4 border-0 bg-light">
                                <div class="card-body p-4">
                                    <h4
                                        class="card-title fw-bold mb-4 pb-2 border-bottom border-primary border-2"
                                    >
                                        <i
                                            class="bi bi-person-circle me-2 text-primary"
                                        ></i>
                                        Personal Information
                                    </h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label
                                                class="form-label fw-semibold"
                                                >Full Name</label
                                            >
                                            <input
                                                required
                                                v-model="form.nama"
                                                type="text"
                                                class="form-control form-control-lg"
                                                placeholder="Enter your full name"
                                            />
                                        </div>
                                        <div class="col-md-6">
                                            <label
                                                class="form-label fw-semibold"
                                                >Email Address</label
                                            >
                                            <input
                                                required
                                                v-model="form.email"
                                                type="email"
                                                class="form-control form-control-lg"
                                                placeholder="your.email@example.com"
                                            />
                                        </div>
                                        <div class="col-md-6">
                                            <label
                                                class="form-label fw-semibold"
                                                >Phone Number</label
                                            >
                                            <input
                                                required
                                                v-model="form.telepon"
                                                type="tel"
                                                class="form-control form-control-lg"
                                                placeholder="+62 812 3456 7890"
                                            />
                                        </div>
                                        <div class="col-md-6">
                                            <label
                                                class="form-label fw-semibold"
                                                >Address</label
                                            >
                                            <input
                                                required
                                                v-model="form.alamat"
                                                type="text"
                                                class="form-control form-control-lg"
                                                placeholder="Your address"
                                            />
                                        </div>
                                        <div class="col-md-6">
                                            <label
                                                class="form-label fw-semibold"
                                                >Education</label
                                            >
                                            <input
                                                required
                                                v-model="form.pendidikan"
                                                type="text"
                                                class="form-control form-control-lg"
                                                placeholder="Your latest education"
                                            />
                                        </div>
                                        <div class="col-md-6">
                                            <label
                                                class="form-label fw-semibold"
                                                >Gender</label
                                            >
                                            <select
                                                required
                                                v-model="form.gender"
                                                class="form-select form-select-lg"
                                            >
                                                <option value="">
                                                    Select gender
                                                </option>
                                                <option value="Male">
                                                    Male
                                                </option>
                                                <option value="Female">
                                                    Female
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Skills & Experience -->
                            <div class="card mb-4 border-0 bg-light">
                                <div class="card-body p-4">
                                    <h4
                                        class="card-title fw-bold mb-4 pb-2 border-bottom border-primary border-2"
                                    >
                                        <i
                                            class="bi bi-gear-fill me-2 text-primary"
                                        ></i>
                                        Skills & Experience
                                    </h4>

                                    <!-- Experience Tags -->
                                    <div class="mb-4">
                                        <div class="card bg-white border">
                                            <div class="card-body">
                                                <label
                                                    class="form-label fw-semibold d-flex align-items-center mb-3"
                                                >
                                                    <i
                                                        class="bi bi-briefcase me-2 text-info d-flex justify-content-center align-items-center me-2"
                                                    ></i>
                                                    Experience
                                                </label>
                                                <div
                                                    class="tags-container d-flex flex-wrap gap-2 justify-content-start"
                                                >
                                                    <span
                                                        v-for="(
                                                            item, idx
                                                        ) in form.pengalaman"
                                                        :key="
                                                            'pengalaman-' + idx
                                                        "
                                                        class="badge bg-info bg-gradient px-2 py-2 rounded d-flex align-items-center gap-2 fs-6"
                                                    >
                                                        {{ item }}
                                                        <button
                                                            type="button"
                                                            @click="
                                                                handleItem(
                                                                    'pengalaman',
                                                                    idx
                                                                )
                                                            "
                                                            class="btn-close btn-close-white me-2"
                                                            style="
                                                                font-size: 0.7em;
                                                            "
                                                        ></button>
                                                    </span>
                                                    <div
                                                        v-if="
                                                            !form.pengalaman
                                                                .length
                                                        "
                                                        class="alert alert-light border border-dashed w-100 text-center py-4 mb-0"
                                                    >
                                                        <i
                                                            class="bi bi-plus-circle me-2 text-muted"
                                                        ></i>
                                                        <span class="text-muted"
                                                            >No experience
                                                            items</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hard Skills -->
                                    <div class="mb-4">
                                        <div class="card bg-white border">
                                            <div class="card-body">
                                                <label
                                                    class="form-label fw-semibold d-flex align-items-center mb-3"
                                                >
                                                    <i
                                                        class="bi bi-code-slash me-2 text-success d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    Hard Skills
                                                </label>
                                                <div
                                                    class="tags-container d-flex flex-wrap gap-2 justify-content-start"
                                                >
                                                    <span
                                                        v-for="(
                                                            item, idx
                                                        ) in form.hardSkill"
                                                        :key="
                                                            'hardSkill-' + idx
                                                        "
                                                        class="badge bg-success bg-gradient px-2 py-2 rounded d-flex align-items-center gap-2 fs-6"
                                                    >
                                                        {{ item }}
                                                        <button
                                                            type="button"
                                                            @click="
                                                                handleItem(
                                                                    'hardSkill',
                                                                    idx
                                                                )
                                                            "
                                                            class="btn-close btn-close-white me-2"
                                                            style="
                                                                font-size: 0.7em;
                                                            "
                                                        ></button>
                                                    </span>
                                                    <div
                                                        v-if="
                                                            !form.hardSkill
                                                                .length
                                                        "
                                                        class="alert alert-light border border-dashed w-100 text-center py-4 mb-0"
                                                    >
                                                        <i
                                                            class="bi bi-plus-circle me-2 text-muted"
                                                        ></i>
                                                        <span class="text-muted"
                                                            >No hard
                                                            skills</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Soft Skills -->
                                    <div class="mb-4">
                                        <div class="card bg-white border">
                                            <div class="card-body">
                                                <label
                                                    class="form-label fw-semibold d-flex align-items-center mb-3"
                                                >
                                                    <i
                                                        class="bi bi-people me-2 text-warning d-flex justify-content-center align-items-center me-2"
                                                    ></i>
                                                    Soft Skills
                                                </label>
                                                <div
                                                    class="tags-container d-flex flex-wrap gap-2 justify-content-start"
                                                >
                                                    <span
                                                        v-for="(
                                                            item, idx
                                                        ) in form.softSkill"
                                                        :key="
                                                            'softSkill-' + idx
                                                        "
                                                        class="badge bg-warning bg-gradient px-2 py-2 rounded d-flex align-items-center gap-2 fs-6"
                                                    >
                                                        {{ item }}
                                                        <button
                                                            type="button"
                                                            @click="
                                                                handleItem(
                                                                    'softSkill',
                                                                    idx
                                                                )
                                                            "
                                                            class="btn-close btn-close-white me-2"
                                                            style="
                                                                font-size: 0.7em;
                                                            "
                                                        ></button>
                                                    </span>
                                                    <div
                                                        v-if="
                                                            !form.softSkill
                                                                .length
                                                        "
                                                        class="alert alert-light border border-dashed w-100 text-center py-4 mb-0"
                                                    >
                                                        <i
                                                            class="bi bi-plus-circle me-2 text-muted"
                                                        ></i>
                                                        <span class="text-muted"
                                                            >No soft
                                                            skills</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Certifications -->
                                    <div class="mb-4">
                                        <div class="card bg-white border">
                                            <div class="card-body">
                                                <label
                                                    class="form-label fw-semibold d-flex align-items-center mb-3"
                                                >
                                                    <i
                                                        class="bi bi-award me-2 text-primary d-flex justify-content-center align-items-center me-2"
                                                    ></i>
                                                    Certifications
                                                </label>
                                                <div
                                                    class="tags-container d-flex flex-wrap gap-2 justify-content-start"
                                                >
                                                    <span
                                                        v-for="(
                                                            item, idx
                                                        ) in form.sertifikasi"
                                                        :key="
                                                            'sertifikasi-' + idx
                                                        "
                                                        class="badge bg-primary bg-gradient px-2 py-2 rounded d-flex align-items-center gap-2 fs-6"
                                                    >
                                                        {{ item }}
                                                        <button
                                                            type="button"
                                                            @click="
                                                                handleItem(
                                                                    'sertifikasi',
                                                                    idx
                                                                )
                                                            "
                                                            class="btn-close btn-close-white me-2"
                                                            style="
                                                                font-size: 0.7em;
                                                            "
                                                        ></button>
                                                    </span>
                                                    <div
                                                        v-if="
                                                            !form.sertifikasi
                                                                .length
                                                        "
                                                        class="alert alert-light border border-dashed w-100 text-center py-4 mb-0"
                                                    >
                                                        <i
                                                            class="bi bi-plus-circle me-2 text-muted"
                                                        ></i>
                                                        <span class="text-muted"
                                                            >No
                                                            certifications</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Awards -->
                                    <div class="mb-4">
                                        <div class="card bg-white border">
                                            <div class="card-body">
                                                <label
                                                    class="form-label fw-semibold d-flex align-items-center mb-3"
                                                >
                                                    <i
                                                        class="bi bi-trophy me-2 text-danger d-flex justify-content-center align-items-center me-2"
                                                    ></i>
                                                    Awards
                                                </label>
                                                <div
                                                    class="tags-container d-flex flex-wrap gap-2 justify-content-start"
                                                >
                                                    <span
                                                        v-for="(
                                                            item, idx
                                                        ) in form.penghargaan"
                                                        :key="
                                                            'penghargaan-' + idx
                                                        "
                                                        class="badge bg-danger bg-gradient px-2 py-2 rounded d-flex align-items-center gap-2 fs-6"
                                                    >
                                                        {{ item }}
                                                        <button
                                                            type="button"
                                                            @click="
                                                                handleItem(
                                                                    'penghargaan',
                                                                    idx
                                                                )
                                                            "
                                                            class="btn-close btn-close-white me-2"
                                                            style="
                                                                font-size: 0.7em;
                                                            "
                                                        ></button>
                                                    </span>
                                                    <div
                                                        v-if="
                                                            !form.penghargaan
                                                                .length
                                                        "
                                                        class="alert alert-light border border-dashed w-100 text-center py-4 mb-0"
                                                    >
                                                        <i
                                                            class="bi bi-plus-circle me-2 text-muted"
                                                        ></i>
                                                        <span class="text-muted"
                                                            >No awards</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Actions -->
                            <div
                                class="d-flex gap-3 justify-content-end pt-3 border-top"
                            >
                                <button
                                    type="button"
                                    @click="cancelUpload"
                                    class="btn btn-outline-secondary btn-lg"
                                >
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Back to Upload
                                </button>
                                <button
                                    type="submit"
                                    class="btn btn-success btn-lg px-4"
                                    :disabled="isSubmitting"
                                >
                                    <div
                                        v-if="isSubmitting"
                                        class="d-flex align-items-center"
                                    >
                                        <div
                                            class="spinner-border spinner-border-sm me-2"
                                            role="status"
                                        >
                                            <span class="visually-hidden"
                                                >Loading...</span
                                            >
                                        </div>
                                        Processing...
                                    </div>
                                    <div
                                        v-else
                                        class="d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-check-circle me-2 d-flex justify-content-center align-items-center me-2"
                                        ></i>
                                        Submit
                                    </div>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

export default {
    components: {
        DotLottieVue,
    },
    data() {
        return {
            file: null,
            parsedData: null,
            uploading: false,
            isSubmitting: false,
            tempPath: "",
            previewUrl: null,
            isDragOver: false,
            showPreview: false,
            form: {
                nama: "",
                email: "",
                telepon: "",
                alamat: "",
                pendidikan: "",
                gender: "",
                pengalaman: [],
                hardSkill: [],
                softSkill: [],
                sertifikasi: [],
                penghargaan: [],
            },
        };
    },
    methods: {
        handleItem(source, idx) {
            this.form[source].splice(idx, 1);
            console.log(this.tempPath);
        },
        formatFileSize(bytes) {
            if (bytes === 0) return "0 Bytes";
            const k = 1024;
            const sizes = ["Bytes", "KB", "MB", "GB"];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (
                parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
            );
        },
        handleDragOver(event) {
            event.preventDefault();
            this.isDragOver = true;
        },
        handleDragLeave(event) {
            event.preventDefault();
            this.isDragOver = false;
        },
        triggerFileInput() {
            if (!this.uploading) {
                this.$refs.fileInput.click();
            }
        },
        handleFileSelect(event) {
            const selectedFile = event.target.files[0];
            this.processFile(selectedFile);
        },
        handleDrop(event) {
            event.preventDefault();
            this.isDragOver = false;
            const droppedFile = event.dataTransfer.files[0];
            this.processFile(droppedFile);
        },
        processFile(selectedFile) {
            if (!selectedFile) return;

            const allowedTypes = ["application/pdf"];
            if (!allowedTypes.includes(selectedFile.type)) {
                this.showAlert(
                    "Please select a valid file format (PDF)",
                    "error"
                );
                return;
            }

            if (selectedFile.size > 30 * 1024 * 1024) {
                this.showAlert(
                    "File size too large! Maximum 30MB allowed.",
                    "error"
                );
                return;
            }

            this.file = selectedFile;
            this.previewUrl = URL.createObjectURL(selectedFile);
            this.showPreview = true;
        },
        removeFile() {
            this.file = null;
            this.previewUrl = null;
            this.showPreview = false;
            this.$refs.fileInput.value = null;
        },
        togglePreview() {
            this.showPreview = !this.showPreview;
        },
        showAlert(message, type = "info") {
            alert(message);
        },
        cancelUpload() {
            this.file = null;
            this.previewUrl = null;
            this.uploading = false;
            this.parsedData = null;
            this.tempPath = "";
            this.showPreview = false;
            this.isDragOver = false;
            this.form = {
                nama: "",
                email: "",
                telepon: "",
                alamat: "",
                pendidikan: "",
                gender: "",
                pengalaman: [],
                hardSkill: [],
                softSkill: [],
                sertifikasi: [],
                penghargaan: [],
            };
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = null;
            }
        },
        async submitFile() {
            if (!this.file) return;

            this.uploading = true;
            const formData = new FormData();
            formData.append("cv", this.file);

            try {
                const response = await axios.post(
                    "/parseResume/preview",
                    formData,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                    }
                );

                const { parsed, tempPath } = response.data;
                this.tempPath = tempPath;

                // Ensure arrays are properly handled
                this.form = {
                    nama: parsed.nama || "",
                    email: parsed.email || "",
                    telepon: parsed.telepon || "",
                    alamat: parsed.alamat || "",
                    pendidikan: parsed.pendidikan || "",
                    gender: parsed.gender || "",
                    pengalaman: Array.isArray(parsed.pengalaman)
                        ? parsed.pengalaman
                        : parsed.pengalaman
                        ? parsed.pengalaman.split(",").map((s) => s.trim())
                        : [],
                    hardSkill: Array.isArray(parsed.hardSkill)
                        ? parsed.hardSkill
                        : parsed.hardSkill
                        ? parsed.hardSkill.split(",").map((s) => s.trim())
                        : [],
                    softSkill: Array.isArray(parsed.softSkill)
                        ? parsed.softSkill
                        : parsed.softSkill
                        ? parsed.softSkill.split(",").map((s) => s.trim())
                        : [],
                    sertifikasi: Array.isArray(parsed.sertifikasi)
                        ? parsed.sertifikasi
                        : parsed.sertifikasi
                        ? parsed.sertifikasi.split(",").map((s) => s.trim())
                        : [],
                    penghargaan: Array.isArray(parsed.penghargaan)
                        ? parsed.penghargaan
                        : parsed.penghargaan
                        ? parsed.penghargaan.split(",").map((s) => s.trim())
                        : [],
                };

                this.parsedData = parsed;
            } catch (error) {
                console.error(error);
                this.showAlert(
                    "Failed to process the file. Please try again.",
                    "error"
                );
            } finally {
                this.uploading = false;
            }
        },
        async submitParsedData() {
            this.isSubmitting = true;

            try {
                const response = await axios.post("/parseResume/submit", {
                    temp_path: this.tempPath,
                    nama: this.form.nama,
                    email: this.form.email,
                    telepon: this.form.telepon,
                    alamat: this.form.alamat,
                    pendidikan: this.form.pendidikan,
                    gender: this.form.gender,
                    pengalaman: this.form.pengalaman,
                    sertifikasi: this.form.sertifikasi,
                    hardSkill: this.form.hardSkill,
                    softSkill: this.form.softSkill,
                    penghargaan: this.form.penghargaan,
                });

                this.showAlert("Resume generated successfully!", "success");
                this.cancelUpload();
            } catch (error) {
                console.error(error);
                this.showAlert(
                    "Failed to Submit Data. Please try again.",
                    "error"
                );
            } finally {
                this.isSubmitting = false;
            }
        },
    },
};
</script>

<style scoped>
/* Custom CSS using Bootstrap utilities where possible */
.bg-gradient {
    /* background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important; */
    background: linear-gradient(135deg, #29569f 0%, #3a7bd5 100%) !important;
}
.generateText {
    color: beige;
}
/*
.header-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    pointer-events: none;
} */

.backdrop-blur {
    backdrop-filter: blur(10px);
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.progress-step.active {
    opacity: 1;
}

.progress-step.active .step-circle {
    background: white !important;
    color: #0d6efd !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.step-circle {
    width: 2.5rem;
    height: 2.5rem;
    transition: all 0.3s ease;
}

.progress-line {
    width: 4rem;
    height: 2px;
    border-radius: 1px;
}

.transition-all {
    transition: all 0.3s ease;
}

.dropzone:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.dropzone.border-primary {
    transform: scale(1.02);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.tags-container .badge {
    font-size: 0.85rem;
    font-weight: 500;
    white-space: pre-line;
    text-align: start;
    word-wrap: break-word;
    transition: all 0.2s ease;
}

.tags-container .badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.btn-close:hover {
    transform: scale(1.1);
}

.form-control:focus,
.form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.btn {
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn:active {
    transform: translateY(0);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .progress-step {
        font-size: 0.875rem;
    }

    .step-circle {
        width: 2rem;
        height: 2rem;
    }

    .progress-line {
        width: 2rem;
        margin: 0 1rem;
    }

    .dropzone {
        padding: 2rem 1rem;
        min-height: 250px;
    }

    .upload-icon i,
    .file-icon i {
        font-size: 2rem !important;
    }

    .card-body {
        padding: 1.5rem !important;
    }

    .d-flex.gap-3.justify-content-end {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .tags-container {
        justify-content: center;
    }
}

/* Animation keyframes */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.card-body {
    animation: fadeIn 0.5s ease-out;
}

.badge {
    animation: slideIn 0.3s ease-out;
}

/* Focus states for accessibility */
.btn:focus,
.form-control:focus,
.form-select:focus,
.btn-close:focus {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

@media screen and(max-width: 576px) {
    .trashButton {
        width: 20%;
        background-color: #0d6efd;
        color: #0a58ca;
    }
}
</style>
