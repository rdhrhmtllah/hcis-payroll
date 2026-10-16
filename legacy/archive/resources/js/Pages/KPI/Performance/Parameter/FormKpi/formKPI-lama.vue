<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <div
            class="row d-flex align-items-center justify-content-center mb-3 fade-in"
        >
            <div class="mb-1">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-sm-8">
                                <div class="header-info">
                                    <div
                                        class="icon-container d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-collection-fill d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Form KPI
                                        </h2>
                                        <p class="subtitle">
                                            Manajemen form KPI
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div
                                    class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                >
                                    <div
                                        class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3"
                                    >
                                        <div class="date-item">
                                            <i
                                                v-if="dayNight == 'Siang'"
                                                class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <i
                                                v-else
                                                class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <span class="fs-md-6 text-header">{{
                                                formatDateString(currentDate)
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact Dashboard -->
        <div class="row justify-content-center mb-0 fade-in">
            <div class="col-12">
                <div class="compact-dashboard">
                    <!-- Quick Stats Bar -->
                    <div class="stats-bar-simple">
                        <div class="stat-item-simple">
                            <i
                                class="d-flex jusfity-content-center align-items-center bi bi-layers"
                            ></i>
                            <span class="stat-number">{{ kpis.length }}</span>
                            <span class="stat-label">Total KPI</span>
                        </div>

                        <div class="stat-item-simple">
                            <i
                                class="d-flex jusfity-content-center align-items-center bi bi-check-circle"
                            ></i>
                            <span class="stat-number">{{
                                completedForms
                            }}</span>
                            <span class="stat-label">Completed</span>
                        </div>

                        <div class="stat-item-simple">
                            <i
                                class="d-flex jusfity-content-center align-items-center bi bi-speedometer2"
                            ></i>
                            <span class="stat-number">{{ totalWeight }}%</span>
                            <span class="stat-label">Total Weight</span>
                        </div>
                    </div>

                    <!-- Horizontal Scroll Cards -->
                    <div class="cards-scroll-container">
                        <div class="cards-scroll-wrapper">
                            <!-- Add New Card -->
                            <div
                                class="compact-card add-new"
                                @click="addNewKpi"
                            >
                                <div class="card-main">
                                    <div class="add-icon">
                                        <i
                                            class="d-flex jusfity-content-center align-items-center bi bi-plus-lg"
                                        ></i>
                                    </div>
                                    <span class="card-title">New KPI</span>
                                </div>
                                <div class="card-hint">Click to add</div>
                            </div>

                            <!-- KPI Cards -->
                            <div
                                v-for="(kpi, index) in kpis"
                                :key="index"
                                class="compact-card"
                                :class="{
                                    active: activeFormIndex === index,
                                    completed: isFormCompleted(kpi),
                                }"
                                @click="switchToForm(index)"
                            >
                                <div class="card-header">
                                    <!-- <span class="card-badge"
                                        >#{{ index + 1 }}</span
                                    > -->
                                    <!-- <div class="card-actions">
                                        <button
                                            v-if="kpis.length > 1"
                                            @click.stop="removeKpi(index)"
                                            class="btn-remove"
                                            title="Remove KPI"
                                        >
                                            <i
                                                class="d-flex jusfity-content-center align-items-center bi bi-trash"
                                            ></i>
                                        </button>
                                    </div> -->
                                </div>

                                <div class="card-main">
                                    <div class="kpi-icon">
                                        <i
                                            class="d-flex justify-content-center align-items-center"
                                            :class="getKpiIcon(kpi.bscCategory)"
                                        ></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div
                                            class="d-flex justify-content-between align-items-center"
                                        >
                                            <h4 class="card-title">
                                                {{
                                                    truncateText(
                                                        kpi.parameter,
                                                        15
                                                    ) || "New KPI"
                                                }}
                                            </h4>
                                            <div class="card-actions">
                                                <button
                                                    v-if="kpis.length > 1"
                                                    @click.stop="
                                                        removeKpi(index)
                                                    "
                                                    class="btn-remove"
                                                    title="Remove KPI"
                                                >
                                                    <i
                                                        class="d-flex jusfity-content-center align-items-center bi bi-trash"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="kpi-meta">
                                            <span class="category">{{
                                                formatBscCategory(
                                                    kpi.bscCategory
                                                ) || "No Category"
                                            }}</span>
                                            <span
                                                class="weight"
                                                v-if="kpi.weight > 0"
                                                >{{ kpi.weight }}%</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress & Status -->
                                <div class="card-footer">
                                    <div class="progress-container">
                                        <div class="progress-bar">
                                            <div
                                                class="progress-fill"
                                                :style="{
                                                    width:
                                                        calculateCompletion(
                                                            kpi
                                                        ) + '%',
                                                }"
                                            ></div>
                                        </div>
                                        <span class="progress-text"
                                            >{{
                                                calculateCompletion(kpi)
                                            }}%</span
                                        >
                                    </div>
                                    <!-- <div
                                        class="status-indicator"
                                        :class="getStatusClass(kpi)"
                                    >
                                        <i
                                            class="d-flex justify-content-center align-items-center"
                                            :class="getStatusIcon(kpi)"
                                        ></i>
                                    </div> -->
                                    <span class="card-badge"
                                        >#{{ index + 1 }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact Form Section -->
        <div
            v-if="activeFormIndex !== null"
            class="row justify-content-center fade-in"
        >
            <div class="col-12">
                <div class="compact-form-section">
                    <!-- Form Header - Fixed for Mobile -->
                    <div class="form-header-compact">
                        <div class="form-title-section">
                            <h3 class="form-title d-none d-md-flex">
                                <i
                                    class="d-flex jusfity-content-center align-items-center bi bi-pencil-square me-2"
                                ></i>
                                {{ getFormTitle(activeFormIndex) }}
                            </h3>
                            <h3 class="form-title d-md-none d-flex">
                                <i
                                    class="d-flex jusfity-content-center align-items-center bi bi-pencil-square me-2"
                                ></i>
                                {{
                                    truncateText(
                                        getFormTitle(activeFormIndex),
                                        10
                                    )
                                }}
                            </h3>
                            <div class="form-actions-compact">
                                <button
                                    @click="saveAndClose"
                                    class="btn-compact btn-save"
                                >
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-check-lg"
                                    ></i>
                                    Save
                                </button>
                                <button
                                    @click="closeForm"
                                    class="btn-compact btn-close"
                                >
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-x-lg"
                                    ></i>
                                </button>
                            </div>
                        </div>

                        <!-- Navigation - Fixed for Mobile -->
                        <div class="form-navigation-compact">
                            <button
                                @click="switchToForm(activeFormIndex - 1)"
                                class="nav-btn prev"
                                :disabled="activeFormIndex === 0"
                            >
                                <i
                                    class="d-flex jusfity-content-center align-items-center bi bi-chevron-left"
                                ></i>
                                <span class="nav-text">Previous</span>
                            </button>

                            <span class="nav-info"
                                >KPI {{ activeFormIndex + 1 }} of
                                {{ kpis.length }}</span
                            >

                            <button
                                @click="switchToForm(activeFormIndex + 1)"
                                class="nav-btn next"
                                :disabled="activeFormIndex === kpis.length - 1"
                            >
                                <span class="nav-text">Next</span>
                                <i
                                    class="d-flex jusfity-content-center align-items-center bi bi-chevron-right"
                                ></i>
                            </button>
                        </div>
                    </div>

                    <!-- Optimized Form Grid -->
                    <div class="optimized-form-grid">
                        <!-- Row 1: Parameter & BSC Category -->
                        <div class="form-row">
                            <div class="form-group-optimized">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-tag"
                                    ></i>
                                    Parameter *
                                </label>
                                <input
                                    type="text"
                                    v-model="activeKpi.parameter"
                                    placeholder="Sales Revenue, Customer Satisfaction..."
                                    class="form-input-optimized"
                                />
                            </div>

                            <div class="form-group-optimized">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-grid-3x3"
                                    ></i>
                                    BSC Category *
                                </label>
                                <select
                                    v-model="activeKpi.bscCategory"
                                    class="form-select-optimized"
                                >
                                    <option value="" disabled>
                                        Select Category
                                    </option>
                                    <option value="financial">Financial</option>
                                    <option value="customer">Customer</option>
                                    <option value="internal">
                                        Internal Process
                                    </option>
                                    <option value="learning">
                                        Learning & Growth
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2: Definition -->
                        <div class="form-row">
                            <div class="form-group-optimized full-width">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-card-text"
                                    ></i>
                                    Definition *
                                </label>
                                <textarea
                                    v-model="activeKpi.definition"
                                    placeholder="Detail deskripsi dari poin KPI..."
                                    class="form-textarea-optimized"
                                    rows="3"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Row 3: Formula & Unit (Aligned) -->
                        <div class="form-row">
                            <div class="form-group-optimized">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-calculator"
                                    ></i>
                                    Formula
                                </label>
                                <select
                                    v-model="activeKpi.formula"
                                    class="form-select-optimized"
                                    @change="onFormulaChange"
                                    :disabled="loading.formulas"
                                >
                                    <option value="" disabled>
                                        Select Formula
                                    </option>
                                    <option
                                        v-for="formula in formulas"
                                        :key="formula.id"
                                        :value="formula.id"
                                    >
                                        {{ formula.name }}
                                    </option>
                                </select>
                                <div
                                    v-if="loading.formulas"
                                    class="loading-text"
                                >
                                    Loading formulas...
                                </div>
                            </div>

                            <div class="form-group-optimized">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-rulers"
                                    ></i>
                                    Unit of Measure
                                </label>
                                <select
                                    v-model="activeKpi.unit"
                                    class="form-select-optimized"
                                    :disabled="
                                        !activeKpi.formula || loading.units
                                    "
                                >
                                    <option value="" disabled>
                                        Select Unit
                                    </option>
                                    <option
                                        v-for="unit in availableUnits"
                                        :key="unit"
                                        :value="unit"
                                    >
                                        {{ unit }}
                                    </option>
                                </select>
                                <div v-if="loading.units" class="loading-text">
                                    Loading units...
                                </div>
                            </div>
                        </div>

                        <!-- Row 4: Weight & Target -->
                        <div class="form-row">
                            <div class="form-group-optimized">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-speedometer2"
                                    ></i>
                                    Weight *
                                </label>
                                <div class="weight-control-optimized">
                                    <input
                                        type="range"
                                        v-model.number="activeKpi.weight"
                                        min="0"
                                        max="100"
                                        class="weight-slider-optimized"
                                    />
                                    <span class="weight-value"
                                        >{{ activeKpi.weight }}%</span
                                    >
                                </div>
                            </div>

                            <div class="form-group-optimized">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex jusfity-content-center align-items-center bi bi-bullseye"
                                    ></i>
                                    Target
                                </label>
                                <select
                                    v-model="activeKpi.target"
                                    class="form-select-optimized"
                                >
                                    <option value="" disabled>
                                        Select Target
                                    </option>
                                    <option value="exceed">
                                        Exceed Expectations
                                    </option>
                                    <option value="meet">
                                        Meet Expectations
                                    </option>
                                    <option value="partial">
                                        Partially Meet
                                    </option>
                                    <option value="below">
                                        Below Expectations
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions - Removed Duplicate -->
                    <div class="quick-actions-optimized gap-2">
                        <button
                            v-if="kpis.length > 1"
                            @click="removeKpi(activeFormIndex)"
                            class="btn-quick-optimized danger"
                        >
                            <i
                                class="d-flex jusfity-content-center align-items-center bi bi-x-circle"
                            ></i>
                            delete
                        </button>
                        <button
                            @click="saveAndNew"
                            class="btn-quick-optimized primary"
                        >
                            <i
                                class="d-flex jusfity-content-center align-items-center bi bi-plus-circle"
                            ></i>
                            Save & New KPI
                        </button>
                    </div>

                    <!-- Form Validation -->
                    <div class="form-validation" v-if="showValidation">
                        <div
                            class="validation-item"
                            v-for="error in validationErrors"
                            :key="error"
                        >
                            <i
                                class="d-flex jusfity-content-center align-items-center bi bi-exclamation-triangle"
                            ></i>
                            {{ error }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Action Bar - Integrated with Mobile -->
        <div
            class="bottom-action-bar-integrated fade-in"
            v-if="kpis.length > 0"
        >
            <div class="action-content-integrated">
                <div class="action-info">
                    <span class="info-text">{{ kpis.length }} KPI Ready</span>
                    <span
                        class="weight-info"
                        :class="{ danger: totalWeight > 100 }"
                        >Total Weight: {{ totalWeight }}%</span
                    >
                </div>
                <div class="action-buttons-integrated">
                    <button
                        @click="validateAll"
                        class="btn-action-integrated validate"
                    >
                        <i
                            class="d-flex jusfity-content-center align-items-center bi bi-check-circle"
                        ></i>
                        Validate All
                    </button>
                    <button
                        @click="submitAllKpis"
                        class="btn-action-integrated submit"
                    >
                        <i
                            class="d-flex jusfity-content-center align-items-center bi bi-send"
                        ></i>
                        Submit All
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi -->
        <ModalV1
            v-if="showConfirmationModal"
            :show="showConfirmationModal"
            title="Confirm Submission"
            submitText="Submit All"
            :loading="loading.submitKpis"
            @close="closeConfirmationModal"
            @submit="confirmSubmit"
        >
            <div class="confirmation-content">
                <i
                    class="bi bi-check-circle text-success fs-1 d-block text-center mb-3"
                ></i>
                <p class="text-center mb-2">
                    Ready to submit <strong>{{ kpis.length }}</strong> KPI forms
                </p>
                <p class="text-center small text-muted">
                    Total weight distribution: {{ totalWeight }}%
                </p>
            </div>
        </ModalV1>
    </div>

    <el-dialog
        v-model="showAlertModal"
        title="Konfirmasi menyimpan data"
        width="400px"
        center
        :lock-scroll="true"
    >
        <div class="delete-modal-content">
            <i class="bi bi-exclamation-triangle warning-icon"></i>
            <p>
                {{ Pesan }}
            </p>
            <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        <template #footer>
            <div class="delete-modal-footer">
                <button
                    class="btn-modern btn-secondary"
                    @click="closeAlertModal"
                >
                    Batal
                </button>
                <button class="btn-modern btn-success" @click="fnc">
                    {{ buttonAlert }}
                </button>
            </div>
        </template>
    </el-dialog>
</template>

<script>
import { ElMessage, ElNotification, ElDialog } from "element-plus";
import "element-plus/dist/index.css";
import ModalV1 from "@/components/ui/modalV1.vue";
import axios from "axios";

export default {
    name: "KpiEntryFormOptimized",
    components: {
        ModalV1,
        ElMessage,
        ElNotification,
        ElDialog,
    },
    data() {
        return {
            kpis: [
                {
                    parameter: "",
                    bscCategory: "",
                    definition: "",
                    formula: "",
                    unit: "",
                    weight: 0,
                    target: "",
                },
            ],
            activeFormIndex: 0,
            currentDate: new Date(),
            showConfirmationModal: false,
            showValidation: false,
            validationErrors: [],
            loading: {
                submitKpis: false,
                formulas: false,
                units: false,
            },
            // State untuk dropdown
            formulas: [],
            availableUnits: [],
            // State untuk form management
            formStates: {},
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        activeKpi() {
            return this.activeFormIndex !== null
                ? this.kpis[this.activeFormIndex]
                : {};
        },
        completedForms() {
            return this.kpis.filter((kpi) => this.isFormCompleted(kpi)).length;
        },
        totalWeight() {
            return this.kpis.reduce(
                (sum, kpi) => sum + (parseFloat(kpi.weight) || 0),
                0
            );
        },
        isMobile() {
            return window.innerWidth < 768;
        },
    },
    methods: {
        async addNewKpi() {
            // Validasi form current sebelum tambah baru
            // if (
            //     this.activeFormIndex !== null &&
            //     !this.validateCurrentFormSilent()
            // ) {
            //     this.showSimpleAlert(
            //         "Tolong lengkapi KPI yang sekarang, sebelum menambahkan."
            //     );
            //     return;
            // }

            const newKpi = {
                parameter: "",
                bscCategory: "",
                definition: "",
                formula: "",
                unit: "",
                weight: 0,
                target: "",
            };

            this.kpis.push(newKpi);
            this.activeFormIndex = this.kpis.length - 1;
            this.scrollToNewCard();
        },

        scrollToNewCard() {
            this.$nextTick(() => {
                const container = document.querySelector(
                    ".cards-scroll-wrapper"
                );
                if (container) {
                    container.scrollLeft = container.scrollWidth;
                }
            });
        },

        switchToForm(index) {
            if (index >= 0 && index < this.kpis.length) {
                this.activeFormIndex = index;
                this.showValidation = false;
            }
        },
        truncateText(text, length) {
            return text.length > length
                ? text.substring(0, length) + "..."
                : text;
        },

        closeForm() {
            this.activeFormIndex = null;
            this.showValidation = false;
        },

        saveAndClose() {
            if (this.validateCurrentForm()) {
                this.closeForm();
                this.showSimpleAlert("KPI berhasil disimpan");
            }
        },

        saveAndNew() {
            if (this.validateCurrentForm()) {
                this.addNewKpi();
            }
        },

        removeKpi(index) {
            if (this.kpis.length > 1) {
                if (confirm("Kamu yakin untuk menghapus KPI ini?")) {
                    this.kpis.splice(index, 1);
                    if (this.activeFormIndex === index) {
                        this.activeFormIndex = Math.max(0, index - 1);
                    } else if (this.activeFormIndex > index) {
                        this.activeFormIndex--;
                    }
                    this.showSimpleAlert("KPI berhasil dihapus!");
                }
            }
        },

        validateCurrentForm() {
            this.validationErrors = [];
            const kpi = this.activeKpi;

            if (!kpi.parameter.trim()) {
                this.validationErrors.push("Nama parameter dibutuhkan");
            }
            if (!kpi.bscCategory) {
                this.validationErrors.push("BSC Category dibutuhkan");
            }
            if (!kpi.definition.trim()) {
                this.validationErrors.push("Definition dibutuhkan");
            }
            if (kpi.weight < 1 || kpi.weight > 100) {
                this.validationErrors.push("Weight harus dari 1 sampai 100");
            }

            this.showValidation = this.validationErrors.length > 0;

            if (this.validationErrors.length > 0) {
                this.showSimpleAlert("Tolong selesaikan error validasi!");
                return false;
            }

            return true;
        },

        validateCurrentFormSilent() {
            const kpi = this.activeKpi;
            return (
                kpi.parameter.trim() &&
                kpi.bscCategory &&
                kpi.definition.trim() &&
                kpi.weight >= 1 &&
                kpi.weight <= 100
            );
        },

        validateAll() {
            let allValid = true;
            const errors = [];

            this.kpis.forEach((kpi, index) => {
                if (!kpi.parameter.trim()) {
                    errors.push(`KPI ${index + 1}: Nama parameter dibutuhkan`);
                    allValid = false;
                }
                if (!kpi.bscCategory) {
                    errors.push(`KPI ${index + 1}: BSC Category dibutuhkan`);
                    allValid = false;
                }
                if (!kpi.definition.trim()) {
                    errors.push(`KPI ${index + 1}: Definition dibutuhkan`);
                    allValid = false;
                }
                if (kpi.weight < 1 || kpi.weight > 100) {
                    errors.push(
                        `KPI ${index + 1}: Weight harus dari 1 sampai 100`
                    );
                    allValid = false;
                }
            });

            if (this.totalWeight > 100) {
                errors.push("Total Weight tidak bisa lebih 100%");
                allValid = false;
            }

            if (allValid) {
                this.showSimpleAlert("Seluruh form valid!");
            } else {
                this.showSimpleAlert("Validasi gagal: " + errors.join(", "));
            }

            return allValid;
        },

        async onFormulaChange() {
            if (this.activeKpi.formula) {
                this.loading.units = true;
                this.activeKpi.unit = ""; // Reset unit ketika formula berubah

                try {
                    await new Promise((resolve) => setTimeout(resolve, 500));
                    // const res = res;
                    // Mock data - dalam real implementation, ini akan dari API
                    const formulaUnits = {
                        formula1: ["$", "%", "units"],
                        formula2: ["%", "points", "rating"],
                        formula3: ["days", "hours", "minutes"],
                        formula4: ["units", "items", "pieces"],
                    };

                    this.availableUnits =
                        formulaUnits[this.activeKpi.formula] || [];
                } catch (error) {
                    console.error("Error loading units:", error);
                    this.availableUnits = [];
                } finally {
                    this.loading.units = false;
                }
            }
        },

        async loadFormulas() {
            this.loading.formulas = true;
            try {
                // Simulasi API call
                await new Promise((resolve) => setTimeout(resolve, 300));

                // Mock data - dalam real implementation, ini akan dari API
                this.formulas = [
                    { id: "formula1", name: "(Actual / Target) * 100" },
                    { id: "formula2", name: "Actual - Target" },
                    {
                        id: "formula3",
                        name: "(Actual - Target) / Target * 100",
                    },
                    { id: "formula4", name: "Actual / Budget * 100" },
                ];
            } catch (error) {
                console.error("Error loading formulas:", error);
                this.formulas = [];
            } finally {
                this.loading.formulas = false;
            }
        },

        isFormCompleted(kpi) {
            return (
                kpi.parameter &&
                kpi.bscCategory &&
                kpi.definition &&
                kpi.weight > 0
            );
        },

        calculateCompletion(kpi) {
            let completedFields = 0;
            const totalFields = 4;
            if (kpi.parameter) completedFields++;
            if (kpi.bscCategory) completedFields++;
            if (kpi.definition) completedFields++;
            if (kpi.weight > 0) completedFields++;
            return Math.round((completedFields / totalFields) * 100);
        },

        getFormTitle(index) {
            const kpi = this.kpis[index];
            return kpi.parameter || `KPI ${index + 1}`;
        },

        formatBscCategory(category) {
            const categories = {
                financial: "Financial",
                customer: "Customer",
                internal: "Internal",
                learning: "Learning",
            };
            return categories[category] || category;
        },

        getKpiIcon(category) {
            const icons = {
                financial: "bi bi-currency-dollar",
                customer: "bi bi-people",
                internal: "bi bi-gear ",
                learning: "bi bi-graph-up",
            };
            return icons[category] || "bi bi-file-text";
        },

        getStatusClass(kpi) {
            return this.isFormCompleted(kpi) ? "completed" : "draft";
        },

        getStatusIcon(kpi) {
            return this.isFormCompleted(kpi)
                ? "bi bi-check-lg"
                : "bi bi-pencil";
        },

        showSimpleAlert(message, parameter = "warning") {
            ElMessage({
                type: parameter,
                message: message,
            });
        },
        submitAllKpis() {
            if (this.validateAll()) {
                this.showConfirmationModal = true;
            }
        },

        confirmSubmit() {
            this.loading.submitKpis = true;

            this.$inertia.post(
                "/kpis",
                { kpis: this.kpis },
                {
                    onSuccess: () => {
                        this.kpis = [
                            {
                                parameter: "",
                                bscCategory: "",
                                definition: "",
                                formula: "",
                                unit: "",
                                weight: 0,
                                target: "",
                            },
                        ];
                        this.activeFormIndex = 0;
                        this.closeConfirmationModal();
                        this.showSimpleAlert("Seluruh KPI berhasil disubmit!");
                    },
                    onError: (errors) => {
                        this.showSimpleAlert("Gagal submit, Tolong coba lagi.");
                    },
                    onFinish: () => {
                        this.loading.submitKpis = false;
                    },
                }
            );
        },

        closeConfirmationModal() {
            this.showConfirmationModal = false;
        },

        formatDateString(dateString) {
            if (!dateString) return "";
            try {
                const date = new Date(dateString);
                const options = {
                    day: "numeric",
                    month: "long",
                    year: "numeric",
                };
                return new Intl.DateTimeFormat("id-ID", options).format(date);
            } catch (error) {
                return dateString;
            }
        },

        handleResize() {
            // Force re-render untuk responsive layout
            this.$forceUpdate();
        },
    },
    async mounted() {
        await this.loadFormulas();
        window.addEventListener("resize", this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* General Buttons */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    color: white !important;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.5s;
}

.btn-modern:hover::before {
    left: 100%;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
    min-height: 140px;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gradient-primary);
    opacity: 0.95;
}

.header-background::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.1"><circle cx="30" cy="30" r="2"/></g></svg>');
    animation: float 20s ease-in-out infinite;
}

.header-content {
    position: relative;
    z-index: 2;
    padding: 2.5rem;
    color: white;
}

.header-info {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 1.5rem;
}

.icon-container {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.icon-container i {
    font-size: 2.2rem;
    z-index: 2;
}

.title {
    font-size: 2rem;
    font-weight: 400;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    letter-spacing: -0.025em;
}

.subtitle {
    text-align: start;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
    font-weight: 400;
}

.header-actions {
    height: 100%;
}

.date-range {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.date-item {
    text-align: center;
    color: white;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(20px);
}

.btn-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    color: var(--text);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Tombol Aksi di Tabel */
.btn-action {
    color: var(--secondary);
    background-color: transparent;
    border: 1px solid var(--border);
    transition: all 0.2s ease;
}
.btn-action:hover {
    background-color: var(--surface-soft);
    color: var(--primary);
}

/* Search Box */
.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-input {
    padding-left: 40px;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
}

/* Tombol di Modal (diasumsikan digunakan oleh komponen ModalV1) */
.btn-primary {
    background-color: var(--primary);
    border-color: var(--primary);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
}
.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
}
.btn-outline-secondary {
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border-color: var(--border);
    color: var(--text-muted);
}
.btn-outline-secondary:hover {
    background-color: var(--surface-soft);
    color: var(--dark);
}

/* Media Queries */
@media (max-width: 1200px) {
    .modern-header {
        min-height: 120px;
    }
}

@media (max-width: 992px) {
    .header-info {
        width: 100%;
        padding-bottom: 0.6rem;
        gap: 1rem;
    }

    .icon-container {
        width: 80px;
        height: 80px;
        padding: 1rem;
    }
    .title {
        font-size: 1.5rem;
    }
    .subtitle {
        font-size: 1rem;
    }
}

@media (max-width: 768px) {
    .modern-header {
        min-height: 100px;
    }
    .header-content {
        padding: 1rem;
    }
    .header-info {
        gap: 0.75rem;
    }
    .icon-container {
        width: 50px;
        height: 50px;
    }
    .icon-container i {
        font-size: 1.5rem;
    }
    .title {
        font-size: 1.25rem;
    }
    .subtitle {
        font-size: 0.9rem;
    }
    .header-actions {
        flex-direction: row;
        justify-content: center;
        flex-wrap: wrap;
    }
    .date-range {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    .btn-modern {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 576px) {
    .header-content {
        padding: 1rem;
    }
    .title {
        font-size: 1.1rem;
    }
    .subtitle {
        font-size: 0.85rem;
    }
    .header-actions {
        flex-direction: column;
        width: 100%;
    }
    .date-range,
    .btn-modern {
        width: 100%;
        justify-content: center;
    }
}
/* Simplified Stats Bar */
.stats-bar-simple {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    justify-content: center;
    flex-wrap: nowrap;
}

.stat-item-simple {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--surface-soft);
    border-radius: 10px;
    min-width: 120px;
    flex: 1;
    justify-content: center;
}

.stat-item-simple i {
    color: var(--primary);
    font-size: 1.1rem;
}

.stat-number {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
}
/* Compact Dashboard Styles */
.compact-dashboard {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.stats-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.stat-pill {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--surface-soft);
    border-radius: 12px;
    min-width: 140px;
    flex: 1;
}

.stat-icon {
    width: 36px;
    height: 36px;
    background: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.stat-icon.completed {
    background: var(--success);
}
.stat-icon.draft {
    background: var(--warning);
}
.stat-icon.weight {
    background: var(--info);
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Horizontal Scroll Cards */
.cards-scroll-container {
    position: relative;
    margin: 1rem 0;
}

.cards-scroll-wrapper {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding: 0.5rem 0;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.cards-scroll-wrapper::-webkit-scrollbar {
    display: none;
}

.compact-card {
    min-width: 260px;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    flex-shrink: 0;
}

.compact-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.compact-card.active {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--surface) 0%, #f8fafc 100%);
}

.compact-card.completed {
    border-left: 4px solid var(--success);
}

.compact-card.draft {
    border-left: 4px solid var(--warning);
}

.compact-card.add-new {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #f1f5f9 100%);
    border: 2px dashed var(--border);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100px;
}

.compact-card.add-new:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.card-badge {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.card-actions {
    opacity: 1;
    transition: opacity 0.3s ease;
}

/* .compact-card:hover .card-actions {
    opacity: 1;
} */

.btn-remove {
    background: var(--danger);
    color: white;
    border: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    cursor: pointer;
}

.card-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.add-icon {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.kpi-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.kpi-info {
    flex: 1;
    min-width: 0;
}

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.75rem;
}

.category {
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.2rem 0.4rem;
    border-radius: 6px;
}

.weight {
    color: var(--primary);
    font-weight: 600;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.progress-container {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar {
    flex: 1;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--success);
    border-radius: 2px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    min-width: 30px;
}

.status-indicator {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
}

.status-indicator.completed {
    background: var(--success);
    color: white;
}

.status-indicator.draft {
    background: var(--warning);
    color: white;
}

.status-indicator.empty {
    background: var(--border);
    color: var(--text-muted);
}

.card-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: center;
    margin-top: 0.5rem;
}

/* Scroll Controls */
.scroll-indicators {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
}

.scroll-btn {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    color: var(--text-muted);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.scroll-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.scroll-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.scroll-dots {
    display: flex;
    gap: 0.5rem;
}

.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--border);
    transition: all 0.3s ease;
}

.dot.active {
    background: var(--primary);
    transform: scale(1.2);
}

/* Compact Form Styles */
.compact-form-section {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-top: 0.1rem;
}

.form-header-compact {
    margin-bottom: 1.5rem;
}

.form-title-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.form-title {
    margin: 0;
    color: var(--dark);
    font-size: 1.25rem;
    display: flex;
    align-items: center;
}

.form-actions-compact {
    display: flex;
    gap: 0.5rem;
}

.btn-compact {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-save {
    background: var(--success);
    color: white;
}

.btn-close {
    background: var(--surface-soft);
    color: var(--text-muted);
    padding: 0.5rem;
}

.form-navigation {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--surface-soft);
    padding: 0.75rem 1rem;
    border-radius: 8px;
}

.nav-btn {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.nav-info {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
}

/* Compact Form Grid */
.compact-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.form-column {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-group-compact {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label-compact {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-input-compact,
.form-select-compact,
.form-textarea-compact {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: var(--surface);
    color: var(--text);
}

.form-input-compact:focus,
.form-select-compact:focus,
.form-textarea-compact:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.form-textarea-compact {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.weight-control {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.weight-slider-compact {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: var(--border);
    outline: none;
    -webkit-appearance: none;
}

.weight-slider-compact::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.weight-value {
    min-width: 50px;
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
}

.quick-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto;
}

.btn-quick {
    flex: 1;
    padding: 0.75rem;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

.btn-quick.primary {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-quick:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Validation */
.form-validation {
    margin-top: 1rem;
    padding: 1rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
}

.validation-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #dc2626;
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
}

.validation-item:last-child {
    margin-bottom: 0;
}

/* Floating Actions */
.floating-actions {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    z-index: 1000;
}

.fab-btn {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: none;
    background: var(--primary);
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    justify-content: center;
}

.fab-btn.primary {
    background: var(--success);
}

.fab-btn:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-lg);
}

/* Bottom Action Bar */
.bottom-action-bar {
    position: sticky;
    bottom: 0;
    background: var(--surface);
    border-top: 1px solid var(--border);
    padding: 1rem;
    margin-top: 2rem;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
}

.action-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
}

.action-info {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.info-text {
    font-weight: 600;
    color: var(--dark);
}

.weight-info {
    font-size: 0.85rem;
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}
.weight-info.danger {
    color: var(--surface);
    background: var(--danger);
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

.btn-action {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-action.validate {
    background: var(--warning);
    color: white;
}

.btn-action.submit {
    background: var(--success);
    color: white;
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .compact-form-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .compact-dashboard {
        padding: 1rem;
    }

    .stats-bar {
        gap: 0.5rem;
    }

    .stat-pill {
        min-width: calc(50% - 0.5rem);
        flex: none;
    }

    .compact-card {
        min-width: 220px;
    }

    .compact-form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .form-title-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .form-navigation {
        flex-direction: column;
        gap: 0.75rem;
    }

    .action-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .action-buttons {
        width: 100%;
        justify-content: center;
    }

    .btn-action {
        flex: 1;
        justify-content: center;
    }

    .floating-actions {
        bottom: 1rem;
        right: 1rem;
    }
}

@media (max-width: 480px) {
    .stat-pill {
        min-width: 100%;
    }

    .compact-card {
        min-width: 200px;
    }

    .cards-per-page {
        cardsperpage: 2;
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
.compact-card {
    opacity: 0; /* default sebelum animasi */
    animation: slideIn 0.4s ease forwards;
    will-change: transform, opacity;
}

.compact-card:nth-child(odd) {
    animation-delay: 0.1s;
}

.compact-card:nth-child(even) {
    animation-delay: 0.2s;
}

/* Optimized Form Grid */
.optimized-form-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    align-items: start;
}

.form-row .full-width {
    grid-column: 1 / -1;
}

.form-group-optimized {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label-optimized {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-input-optimized,
.form-select-optimized,
.form-textarea-optimized {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: var(--surface);
    color: var(--text);
    width: 100%;
}

.form-input-optimized:focus,
.form-select-optimized:focus,
.form-textarea-optimized:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.form-textarea-optimized {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.weight-control-optimized {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.weight-slider-optimized {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: var(--border);
    outline: none;
    -webkit-appearance: none;
}

.weight-slider-optimized::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.loading-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-style: italic;
    margin-top: 0.25rem;
}

/* Fixed Form Header for Mobile */
.form-header-compact {
    margin-bottom: 1.5rem;
}

.form-title-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: nowrap;
}

.form-title {
    margin: 0;
    color: var(--dark);
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    flex: 1;
}

.form-actions-compact {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

/* .btn-compact {
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    height: 40px;
} */

.btn-save {
    background: var(--success);
    color: white;
    min-width: 80px;
    justify-content: center;
}

.btn-close {
    background: var(--surface-soft);
    color: var(--text-muted);
    width: 40px;
    height: 40px;
    padding: 0;
    justify-content: center;
}

/* Fixed Navigation for Mobile */
.form-navigation-compact {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--surface-soft);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    flex-wrap: nowrap;
}

.nav-btn {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 0.6rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.nav-text {
    display: inline;
}

.nav-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.nav-info {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
    white-space: nowrap;
    margin: 0 1rem;
}

/* Quick Actions */
.quick-actions-optimized {
    margin-top: 1.5rem;
    display: flex;
    justify-content: end;
}

.btn-quick-optimized {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-quick-optimized.primary {
    background: var(--primary);
    color: white;
}
.btn-quick-optimized.danger {
    background: var(--danger);
    color: white;
}

.btn-quick-optimized:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Integrated Bottom Action Bar */
.bottom-action-bar-integrated {
    position: sticky;
    bottom: 0;
    border-radius: 12px;
    background: var(--surface);
    border-top: 1px solid var(--border);
    padding: 1rem;
    margin-top: 1rem;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    z-index: 100;
}

.action-content-integrated {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    flex-wrap: nowrap;
}

.action-info {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1;
}

.info-text {
    font-weight: 600;
    color: var(--dark);
    white-space: nowrap;
}

.weight-info {
    font-size: 0.85rem;
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    white-space: nowrap;
}

.action-buttons-integrated {
    display: flex;
    gap: 0.75rem;
    flex-wrap: nowrap;
}

.btn-action-integrated {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.btn-action-integrated.validate {
    background: var(--warning);
    color: white;
}

.btn-action-integrated.submit {
    background: var(--success);
    color: white;
}

.btn-action-integrated:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Enhanced Mobile Responsiveness */
@media (max-width: 768px) {
    .stats-bar-simple {
        flex-direction: row;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        justify-content: flex-start;
    }

    .stat-item-simple {
        min-width: 110px;
        flex: none;
        padding: 0.6rem 0.8rem;
    }

    .form-title-section {
        flex-direction: row;
        align-items: center;
        gap: 1rem;
    }

    .form-actions-compact {
        flex-shrink: 0;
    }

    .form-navigation-compact {
        flex-direction: row;
        gap: 0.5rem;
    }

    .nav-text {
        display: none;
    }

    .nav-info {
        margin: 0 0.5rem;
        font-size: 0.8rem;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .action-content-integrated {
        flex-direction: row;
        gap: 1rem;
    }

    .action-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .action-buttons-integrated {
        flex: 1;
        justify-content: flex-end;
    }

    .btn-action-integrated {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .stat-item-simple {
        min-width: 100px;
        padding: 0.5rem 0.6rem;
    }

    .stat-number {
        font-size: 1rem;
    }

    .stat-label {
        font-size: 0.7rem;
    }

    .form-title {
        font-size: 1.1rem;
    }

    /* .btn-compact {
        padding: 0.5rem 0.8rem;
        font-size: 0.8rem;
    } */

    .action-content-integrated {
        flex-direction: column;
        gap: 0.75rem;
        text-align: center;
    }

    .action-info {
        flex-direction: row;
        justify-content: center;
        width: 100%;
    }

    .action-buttons-integrated {
        width: 100%;
        justify-content: center;
    }

    .btn-action-integrated {
        flex: 1;
        justify-content: center;
    }
}

/* Compact Card Adjustments */
.compact-card {
    min-width: 250px;
}

.card-main {
    gap: 0.6rem;
}

.kpi-info {
    min-width: 0;
}

.card-title {
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-meta {
    gap: 0.3rem;
}

.category {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
}

.weight {
    font-size: 0.7rem;
}

/* Transitions */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}
.fade-switch-enter-active,
.fade-switch-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-switch-enter-from,
.fade-switch-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
@keyframes float {
    0%,
    100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
@keyframes bounce {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}
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
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
</style>
