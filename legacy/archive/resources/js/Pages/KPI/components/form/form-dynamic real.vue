<template>
    <div
        v-if="activeFormIndex !== null"
        class="row justify-content-center fade-in"
    >
        <div class="col-12">
            <div class="compact-form-section">
                <!-- Form Header - Fixed for Mobile -->
                <div class="form-header-compact" v-if="showHeader">
                    <div class="form-title-section">
                        <h3 class="form-title d-none d-md-flex">
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-pencil-square me-2"
                            ></i>
                            {{
                                truncateText(getFormTitle(activeFormIndex), 50)
                            }}
                        </h3>
                        <h3 class="form-title d-md-none d-flex">
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-pencil-square me-2"
                            ></i>
                            {{
                                truncateText(getFormTitle(activeFormIndex), 10)
                            }}
                        </h3>
                        <div
                            class="form-actions-compact"
                            v-if="showHeaderActions"
                        >
                            <button
                                @click="saveAndClose"
                                class="btn-compact btn-save"
                            >
                                <i
                                    class="d-flex justify-content-center align-items-center bi bi-check-lg"
                                ></i>
                                Save
                            </button>
                            <button
                                @click="closeForm"
                                class="btn-compact btn-close"
                            >
                                <i
                                    class="d-flex justify-content-center align-items-center bi bi-x-lg"
                                ></i>
                            </button>
                        </div>
                    </div>

                    <!-- Navigation - Fixed for Mobile -->
                    <div
                        class="form-navigation-compact mb-4"
                        v-if="showNavigation && statusTransaksi != 'rejected'"
                    >
                        <button
                            @click="switchToForm(activeFormIndex - 1)"
                            class="nav-btn prev"
                            :disabled="activeFormIndex === 0"
                        >
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-chevron-left"
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
                                class="d-flex justify-content-center align-items-center bi bi-chevron-right"
                            ></i>
                        </button>
                    </div>
                    <notesCard
                        v-if="statusTransaksi == 'rejected'"
                        :approverRejected="ApprovalDataComponent.approver"
                        :revision-message="`KPI ${oldDataComponent[activeFormIndex]?.parameter} diperlukan revisi berikut :`"
                        :revision-details="[activeKpi.Alasan_Tolak]"
                        :show-revision-actions="false"
                        :show-revision-notes="true"
                    />
                </div>
                <!-- Optimized Form Grid -->
                <div class="optimized-form-grid">
                    <div
                        v-for="(row, rowIndex) in groupedFields"
                        :key="'row-' + rowIndex"
                        class="row gap-1 gap-md-0"
                        :class="row.class || ''"
                    >
                        <div
                            v-for="field in row.fields"
                            :key="field.name"
                            class="mb-2"
                            :class="field.size || 'col-12 col-md-6'"
                            v-show="field.enabled !== false"
                        >
                            <div class="form-group-optimized">
                                <label class="form-label-optimized">
                                    <i
                                        class="d-flex justify-content-center align-items-center"
                                        :class="field.icon || 'bi bi-tag'"
                                    ></i>
                                    {{ field.label }}
                                    <span
                                        v-if="field.required"
                                        class="text-danger"
                                        >*</span
                                    >
                                </label>

                                <!-- Text Input -->
                                <input
                                    v-if="field.type === 'text'"
                                    :type="field.type"
                                    v-model="activeKpi[field.name]"
                                    :placeholder="field.placeholder"
                                    :disabled="!field.enabled"
                                    class="form-input-optimized"
                                />

                                <!-- Input persen dengan format baru -->
                                <div
                                    v-else-if="field.type === 'persen'"
                                    class="relative w-full"
                                >
                                    <div class="input-group-optimized">
                                        <span
                                            class="input-group-text"
                                            :class="{
                                                disabled: !field.enabled,
                                            }"
                                        >
                                            %
                                        </span>
                                        <input
                                            type="text"
                                            v-model="formattedValue[field.name]"
                                            :placeholder="'0%'"
                                            :disabled="!field.enabled"
                                            inputmode="decimal"
                                            :class="[
                                                'form-input-optimized',
                                                {
                                                    'border-warning':
                                                        isWeightExceedingMax(
                                                            field.name
                                                        ),
                                                    'border-danger':
                                                        remainingWeight < 0,
                                                },
                                            ]"
                                            @input="
                                                (e) => handleInput(e, field)
                                            "
                                            @keypress="allowOnlyNumbers"
                                            @blur="(e) => handleBlur(e, field)"
                                            @focus="
                                                (e) => handleFocus(e, field)
                                            "
                                        />
                                    </div>

                                    <!-- Info weight (tetap sama) -->
                                    <div
                                        v-if="field.name === 'weight'"
                                        class="text-sm mt-2"
                                        style="font-size: 0.6rem"
                                    >
                                        <div
                                            class="d-flex justify-content-between text-1 align-items-center"
                                        >
                                            <span
                                                :class="
                                                    getRemainingWeightClass()
                                                "
                                            >
                                                <strong
                                                    >Sisa:
                                                    {{
                                                        remainingWeight.toFixed(
                                                            2
                                                        )
                                                    }}%</strong
                                                >
                                            </span>
                                            <span class="text-muted">
                                                Maks:
                                                {{
                                                    maxAllowedWeight.toFixed(2)
                                                }}%
                                            </span>
                                        </div>
                                        <div
                                            class="w-full bg-gray-200 rounded-full h-1.5 mt-1"
                                        >
                                            <div
                                                class="h-1.5 rounded-full transition-all"
                                                :class="getProgressBarClass()"
                                                :style="{
                                                    width:
                                                        Math.min(
                                                            100,
                                                            totalWeight
                                                        ) + '%',
                                                }"
                                            ></div>
                                        </div>

                                        <!-- Warning real-time -->
                                        <div
                                            v-if="
                                                isWeightExceedingMax(field.name)
                                            "
                                            class="mt-1"
                                        >
                                            <small class="text-warning">
                                                <i
                                                    class="bi bi-exclamation-triangle"
                                                ></i>
                                                Melebihi batas maksimum ({{
                                                    maxAllowedWeight
                                                }}%)
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Number Input dengan format -->
                                <div
                                    v-else-if="field.type === 'number'"
                                    class="input-group-optimized"
                                >
                                    <span
                                        class="input-group-text"
                                        :class="{ disabled: !field.enabled }"
                                    >
                                        {{ getUnitSymbol(field) }}
                                    </span>
                                    <input
                                        type="text"
                                        v-model="formattedValue[field.name]"
                                        :placeholder="field.placeholder"
                                        :disabled="!field.enabled"
                                        class="form-input-optimized"
                                        @input="(e) => handleInput(e, field)"
                                        @blur="(e) => handleBlur(e, field)"
                                        @focus="(e) => handleFocus(e, field)"
                                    />
                                </div>

                                <!-- Select Input -->
                                <v-select
                                    v-else-if="field.type === 'select'"
                                    :options="
                                        field.options ||
                                        getDynamicOptions(field.name)
                                    "
                                    label="label"
                                    :reduce="(opt) => opt.value"
                                    :placeholder="
                                        field.placeholder || 'Select Option'
                                    "
                                    :disabled="
                                        !field.enabled ||
                                        (field.loadingKey &&
                                            loading[field.loadingKey]) ||
                                        (field.name === 'unit' &&
                                            !activeKpi.formula)
                                    "
                                    :modelValue="activeKpi[field.name]"
                                    :searchable="false"
                                    :filterable="false"
                                    @update:modelValue="
                                        (val) => {
                                            activeKpi[field.name] = val;
                                            field.onChange
                                                ? field.onChange(val, activeKpi)
                                                : null;
                                        }
                                    "
                                    @option:selected="
                                        (val) => {
                                            if (field.name === 'target') {
                                                fillingTarget(val);
                                            }
                                        }
                                    "
                                />

                                <!-- Textarea Input -->
                                <textarea
                                    v-else-if="field.type === 'textarea'"
                                    v-model="activeKpi[field.name]"
                                    :placeholder="field.placeholder"
                                    :disabled="!field.enabled"
                                    class="form-textarea-optimized"
                                    :rows="field.rows || 3"
                                ></textarea>

                                <!-- popup Input -->
                                <div
                                    v-else-if="field.type === 'popup'"
                                    class="form-input-optimized pop-field d-flex justify-content-between align-items-center"
                                    :class="{ active: activeKpi[field.name] }"
                                    :disabled="!field.enabled"
                                    @click="
                                        (val) => {
                                            if (field.name === 'target') {
                                                triggerTarget();
                                            }
                                        }
                                    "
                                >
                                    {{ field.placeholder }}
                                    <i
                                        class="bi bi-box-arrow-down-left d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>

                                <!-- Range Input (Weight) -->
                                <div
                                    v-else-if="field.type === 'range'"
                                    class="weight-control-optimized"
                                >
                                    <input
                                        type="range"
                                        v-model.number="activeKpi[field.name]"
                                        :min="field.min || 0"
                                        :max="field.max || 100"
                                        :disabled="!field.enabled"
                                        class="weight-slider-optimized"
                                    />
                                    <span class="weight-value"
                                        >{{ activeKpi[field.name] }}%</span
                                    >
                                </div>

                                <!-- Loading Indicator -->
                                <div
                                    v-if="
                                        field.loading &&
                                        loading[field.loadingKey]
                                    "
                                    class="loading-text"
                                >
                                    Loading {{ field.loadingKey }}...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div
                    class="quick-actions-optimized gap-2"
                    v-if="showQuickActions"
                >
                    <button
                        v-if="kpis.length > 1 && statusTransaksi != 'rejected'"
                        @click="removeKpi(activeFormIndex)"
                        class="btn-quick-optimized danger"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-x-circle"
                        ></i>
                        Delete
                    </button>
                    <button
                        v-if="statusTransaksi != 'rejected'"
                        @click="saveAndNew"
                        class="btn-quick-optimized primary"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-plus-circle"
                        ></i>
                        Save & New KPI
                    </button>
                </div>

                <!-- Form Validation -->
                <div class="form-validation" v-if="showValidation">
                    <div
                        class="validation-item"
                        v-for="error in validationErrorsComponents"
                        :key="error"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-exclamation-triangle"
                        ></i>
                        {{ error }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import vSelect from "vue-select";
import notesCard from "../ui/RevisionNotes.vue";
import { ElMessage } from "element-plus";

const formatUtils = {
    currency: {
        format: (value, locale = "id-ID", currency = "IDR") => {
            if (!value && value !== 0) return "";
            const numericValue =
                parseFloat(value.toString().replace(/[^\d]/g, "")) || 0;
            return new Intl.NumberFormat(locale, {
                // HAPUS style: "currency" karena kita pakai input group
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }).format(numericValue);
        },
        parse: (value) => {
            return value.toString().replace(/[^\d]/g, "");
        },
    },

    percentage: {
        format: (value, decimals = 2) => {
            if (!value && value !== 0) return "";
            if (value === "" || value === "%") return "";

            const numericValue = parseFloat(value) || 0;
            const strValue = value.toString();

            const userInputDecimal =
                strValue.includes(".") || strValue.includes(",");

            if (!userInputDecimal && numericValue % 1 === 0) {
                return `${numericValue}`; // HAPUS "%"
            }

            return `${numericValue.toFixed(decimals)}`; // HAPUS "%"
        },
        parse: (value) => {
            if (!value) return "";
            return value
                .toString()
                .replace("%", "") // Tetap ada untuk parsing
                .replace(",", ".")
                .replace(/[^\d.,]/g, "");
        },
    },

    number: {
        format: (value, locale = "id-ID", decimals = 0) => {
            if (!value && value !== 0) return "";
            const numericValue = parseFloat(value) || 0;
            return new Intl.NumberFormat(locale, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            }).format(numericValue);
        },
        parse: (value) => {
            return value.toString().replace(/[^\d]/g, "");
        },
    },

    decimal: {
        format: (value, decimals = 2) => {
            if (!value && value !== 0) return "";
            const numericValue = parseFloat(value) || 0;

            const strValue = value.toString();
            const userInputDecimal =
                strValue.includes(".") || strValue.includes(",");

            if (!userInputDecimal && numericValue % 1 === 0) {
                return numericValue.toString();
            }

            return numericValue.toFixed(decimals);
        },
        parse: (value) => {
            return value.toString().replace(",", ".");
        },
    },
};
export default {
    name: "KpiForm",
    components: { vSelect, notesCard, ElMessage },
    props: {
        totalWeight: { type: Number, default: 0 },
        remainingWeight: { type: Number, default: 100 },
        maxAllowedWeight: { type: Number, default: 100 },
        statusTransaksi: { type: String, default: "pending" },
        isEditFormComponent: { type: Boolean, default: false },
        modelValue: { type: Object, required: true },
        fields: { type: Array, required: true },
        kpis: { type: Array, required: true },
        oldDataComponent: { type: Array, default: () => [] },
        activeFormIndex: { type: Number, default: null },
        formulas: { type: Array, default: () => [] },
        categories: { type: Array, default: () => [] },
        availableUnits: { type: Array, default: () => [] },
        loading: {
            type: Object,
            default: () => ({ listData: false, units: false, Bsc: false }),
        },
        showValidation: { type: Boolean, default: false },
        validationErrorsComponents: { type: Array, default: () => [] },
        showHeader: { type: Boolean, default: true },
        showNavigation: { type: Boolean, default: true },
        showQuickActions: { type: Boolean, default: true },
        showHeaderActions: { type: Boolean, default: true },
        ApprovalDataComponent: { type: Array, default: () => [] },
    },
    emits: [
        "update:modelValue",
        "weightExceeded",
        "saveAndClose",
        "closeForm",
        "switchToForm",
        "saveAndNew",
        "removeKpi",
        "triggerTarget",
    ],
    data() {
        return {
            formattedValue: {},
            rawValue: {},
            isFocused: {},
        };
    },
    computed: {
        unitFormats() {
            const formatMap = {};
            if (this.activeKpi.availableUnits && this.activeKpi.unit) {
                const currentUnit = this.activeKpi.availableUnits.find(
                    (unit) => unit.id === this.activeKpi.unit
                );
                if (currentUnit) {
                    const unitName = currentUnit.name?.toLowerCase();
                    if (unitName.includes("rp") || unitName.includes("idr")) {
                        formatMap.target = "currency";
                    } else if (
                        unitName.includes("%") ||
                        unitName.includes("persen")
                    ) {
                        formatMap.target = "percentage";
                    } else if (
                        unitName.includes("kg") ||
                        unitName.includes("ton")
                    ) {
                        formatMap.target = "number";
                    } else {
                        formatMap.target = "decimal";
                    }
                }
            }
            return formatMap;
        },
        activeKpi: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit("update:modelValue", value);
            },
        },
        groupedFields() {
            const rows = [];
            this.fields.forEach((field) => {
                const rowIndex = field.rowIndex || 0;
                if (!rows[rowIndex])
                    rows[rowIndex] = {
                        fields: [],
                        class: field.rowClass || "",
                    };
                rows[rowIndex].fields.push(field);
            });
            return rows.filter((row) => row.fields.length > 0);
        },
    },
    watch: {
        activeKpi: {
            handler(newVal) {
                this.initializeFormattedValues();
            },
            deep: true,
            immediate: true,
        },

        "activeKpi.unit"(newUnit) {
            this.initializeFormattedValues();
        },
    },
    methods: {
        // Di methods, tambahkan:
        log() {
            console.log(this.formulas);
        },
        getUnitSymbol(field) {
            if (field.type === "persen") return "%";
            // console.log(field);
            // Untuk field number, ambil symbol berdasarkan unit yang dipilih
            if (
                field.name === "target" &&
                this.activeKpi.unit &&
                this.activeKpi.availableUnits
            ) {
                const currentUnit = this.activeKpi.availableUnits.find(
                    (unit) => unit.id === this.activeKpi.unit
                );
                return currentUnit?.Simbol_Satuan || currentUnit?.name || "";
            }

            return field.unitSymbol || "";
        },

        // Method untuk toggle aktif/non-aktif field
        toggleField(fieldName) {
            const field = this.fields.find((f) => f.name === fieldName);
            if (field) {
                field.enabled = !field.enabled;
                // Jika dinonaktifkan, clear value
                if (!field.enabled) {
                    this.activeKpi[fieldName] = "";
                    this.formattedValue[fieldName] = "";
                }
            }
        },
        handleInput(event, field) {
            const input = event.target;
            const cursorPosition = input.selectionStart;
            const value = event.target.value;
            const formatConfig = this.getFormatConfig(field);

            if (!formatConfig) {
                this.updateFieldValue(field.name, value);
                return;
            }
            console.log(formatConfig);
            // Handle khusus untuk percentage fields
            if (field.type === "persen" || field.format === "percentage") {
                this.handlePercentageInput(event, field, cursorPosition);
                return;
            }

            // Handle kasus ketika user menghapus semua input untuk format lainnya
            if (
                value === "" ||
                value === "%" ||
                value === "Rp" ||
                value === "IDR"
            ) {
                this.formattedValue[field.name] = "";
                this.updateFieldValue(field.name, "");
                return;
            }

            // Parse value terlebih dahulu untuk format lainnya
            const parsedValue = this.parseValue(value, formatConfig);
            const formattedValue = this.formatValue(parsedValue, formatConfig);

            // Update tampilan
            this.formattedValue[field.name] = formattedValue;
            this.updateFieldValue(field.name, parsedValue);

            // Kembalikan posisi cursor setelah format
            if (field.type === "persen" || field.format === "percentage") {
                this.$nextTick(() => {
                    const newCursorPosition = this.calculateNewCursorPosition(
                        cursorPosition,
                        value,
                        formattedValue,
                        input,
                        field
                    );
                    input.setSelectionRange(
                        newCursorPosition,
                        newCursorPosition
                    );
                });
            }
        },

        handlePercentageInput(event, field, cursorPosition) {
            const input = event.target;
            let value = event.target.value;

            let cleanValue = value;

            const parsedValue = this.parseValue(cleanValue, "percentage");

            const formattedValue = this.formatValue(
                parsedValue,
                "percentage"
            ).replace("%", "");

            this.formattedValue[field.name] = formattedValue;
            this.updateFieldValue(field.name, parsedValue);
            // Validasi weight jika diperlukan
            if (field.name === "weight") {
                this.validateAndFormatWeight(parsedValue, field.name);
            }
        },

        // calculateNewCursorPosition(
        //     oldPosition,
        //     oldValue,
        //     newValue,
        //     input,
        //     field
        // ) {
        //     // Kasus khusus untuk field percentage/persen
        //     if (field.type === "persen" || field.format === "percentage") {
        //         return this.calculatePercentageCursorPosition(
        //             oldPosition,
        //             oldValue,
        //             newValue
        //         );
        //     }

        //     // Logika default untuk field lainnya
        //     if (oldValue.length > newValue.length) {
        //         return Math.max(0, oldPosition - 1);
        //     }

        //     if (oldValue.length < newValue.length) {
        //         return Math.min(newValue.length, oldPosition + 1);
        //     }

        //     return Math.min(oldPosition, newValue.length);
        // },

        // calculatePercentageCursorPosition(oldPosition, oldValue, newValue) {
        //     const hadPercentage = oldValue.endsWith("%");
        //     const hasPercentage = newValue.endsWith("%");

        //     // Jika sebelumnya ada % dan sekarang juga ada %, pertahankan posisi
        //     if (hadPercentage && hasPercentage) {
        //         return oldPosition;
        //     }

        //     // Jika sebelumnya tidak ada % tapi sekarang ada, geser cursor ke kiri
        //     if (!hadPercentage && hasPercentage) {
        //         return Math.max(0, oldPosition);
        //     }

        //     // Jika sebelumnya ada % tapi sekarang tidak ada, geser cursor ke kanan
        //     if (hadPercentage && !hasPercentage) {
        //         return Math.min(newValue.length, oldPosition);
        //     }

        //     return Math.min(oldPosition, newValue.length);
        // },

        handleFocus(event, field) {
            this.isFocused[field.name] = true;
            const formatConfig = this.getFormatConfig(field);

            if (formatConfig) {
                // Tampilkan raw value saat focus
                this.formattedValue[field.name] =
                    this.rawValue[field.name] || "";
            }
        },

        handleBlur(event, field) {
            this.isFocused[field.name] = false;
            const formatConfig = this.getFormatConfig(field);
            const value = event.target.value;

            if (formatConfig) {
                const parsedValue = this.parseValue(value, formatConfig);
                const formattedValue = this.formatValue(
                    parsedValue,
                    formatConfig
                );

                this.formattedValue[field.name] = formattedValue;

                if (field.name === "weight") {
                    this.validateAndFormatWeight(parsedValue, field.name);
                } else {
                    this.updateFieldValue(field.name, parsedValue);
                }
            } else {
                this.updateFieldValue(field.name, value);
            }
        },
        initializeFormattedValues() {
            this.fields.forEach((field) => {
                const value = this.activeKpi[field.name];

                // Determine format
                let formatConfig = field.format;

                // Jika field persen, gunakan format percentage
                if (field.type === "persen") {
                    formatConfig = "percentage";
                }

                // Jika field target, gunakan format berdasarkan unit
                if (field.name === "target" && this.unitFormats.target) {
                    formatConfig = this.unitFormats.target;
                }

                this.rawValue[field.name] = value;
                this.formattedValue[field.name] = this.formatValue(
                    value,
                    formatConfig
                );
                this.isFocused[field.name] = false;
            });
        },

        getFormatConfig(field) {
            // Priority: 1. Field format, 2. Type-based format, 3. Unit-based format, 4. Default
            if (field.format) {
                return field.format;
            }

            if (field.type === "persen") {
                return "percentage";
            }

            if (field.name === "target" && this.unitFormats.target) {
                return this.unitFormats.target;
            }

            return null;
        },

        formatValue(value, formatConfig) {
            if (!formatConfig) return value;

            if (typeof formatConfig === "string") {
                const formatter = formatUtils[formatConfig];
                return formatter ? formatter.format(value) : value;
            }

            if (typeof formatConfig === "object") {
                const formatter = formatUtils[formatConfig.type];
                return formatter
                    ? formatter.format(value, ...formatConfig.params)
                    : value;
            }

            return value;
        },

        parseValue(value, formatConfig) {
            if (!formatConfig) return value;

            if (typeof formatConfig === "string") {
                const formatter = formatUtils[formatConfig];
                return formatter ? formatter.parse(value) : value;
            }

            if (typeof formatConfig === "object") {
                const formatter = formatUtils[formatConfig.type];
                return formatter
                    ? formatter.parse(value, ...formatConfig.params)
                    : value;
            }

            return value;
        },

        handleFocus(event, field) {
            this.isFocused[field.name] = true;
            const formatConfig = this.getFormatConfig(field);

            if (formatConfig) {
                this.formattedValue[field.name] =
                    this.rawValue[field.name] || "";
            }
        },

        handleBlur(event, field) {
            this.isFocused[field.name] = false;
            const formatConfig = this.getFormatConfig(field);
            const value = event.target.value;

            if (formatConfig) {
                const parsedValue = this.parseValue(value, formatConfig);
                const formattedValue = this.formatValue(
                    parsedValue,
                    formatConfig
                );

                this.formattedValue[field.name] = formattedValue;

                // Untuk field weight, lakukan validasi tambahan
                if (field.name === "weight") {
                    this.validateAndFormatWeight(parsedValue, field.name);
                } else {
                    this.updateFieldValue(field.name, parsedValue);
                }
            } else {
                this.updateFieldValue(field.name, value);
            }
        },

        updateFieldValue(fieldName, value) {
            this.rawValue[fieldName] = value;
            this.activeKpi[fieldName] = value;
            this.$emit("update:modelValue", { ...this.activeKpi });
        },

        // Weight-specific methods
        allowOnlyNumbers(event) {
            const allowedKeys = [
                "Backspace",
                "Delete",
                "ArrowLeft",
                "ArrowRight",
                "ArrowUp",
                "ArrowDown",
                "Tab",
                "Home",
                "End",
            ];

            // PERBAIKAN: Selalu izinkan tombol editing meskipun remainingWeight <= 0
            const isEditingKey = [
                "Backspace",
                "Delete",
                "ArrowLeft",
                "ArrowRight",
                "Home",
                "End",
            ].includes(event.key);

            if (this.remainingWeight <= 0) {
                // Hanya blokir input angka baru, tapi izinkan editing
                if (!isEditingKey && /[0-9.]/.test(event.key)) {
                    event.preventDefault();
                    return;
                }
            }

            const isNumber = /[0-9]/.test(event.key);
            const isDot = event.key === ".";
            const isControlKey = allowedKeys.includes(event.key);

            // Izinkan semua control keys termasuk Backspace dan Delete
            if (isControlKey) {
                return; // Jangan preventDefault untuk control keys
            }

            if (!isNumber && !isDot) {
                event.preventDefault();
                return;
            }

            const currentValue = event.target.value;
            if (isDot && currentValue.includes(".")) {
                event.preventDefault();
            }
        },

        sanitizeWeightInput(event, fieldName) {
            let value = event.target.value;
            value = value.replace(/[^\d.]/g, "");

            const parts = value.split(".");
            if (parts.length > 2) {
                value = parts[0] + "." + parts.slice(1).join("");
            }

            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + "." + parts[1].substring(0, 2);
            }

            this.formattedValue[fieldName] = value;
        },

        validateAndFormatWeight(value, fieldName) {
            // Handle empty input
            if (value === "" || value === "%") {
                this.updateFieldValue(fieldName, "0");
                this.formattedValue[fieldName] = "0";
                return;
            }

            let numericValue = parseFloat(value) || 0;

            // Jika input kosong, set ke 0
            if (!value || value === ".") {
                numericValue = 0;
            }

            // Validasi terhadap maksimum yang diizinkan
            if (numericValue > this.maxAllowedWeight) {
                this.$emit("weightExceeded", numericValue);
                numericValue = this.maxAllowedWeight;
                ElMessage.error(
                    `Weight dibatasi hingga ${this.maxAllowedWeight}%`
                );
            }

            // Validasi minimum
            if (numericValue < 0) {
                numericValue = 0;
                ElMessage.warning("Weight tidak boleh kurang dari 0%");
            }

            // Update value
            this.updateFieldValue(fieldName, numericValue.toString());

            // Format ulang TANPA "%"
            this.formattedValue[fieldName] = this.formatValue(
                numericValue.toString(),
                "percentage"
            ).replace("%", ""); // PASTIKAN HAPUS "%"
        },

        isWeightExceedingMax(fieldName) {
            const value = this.activeKpi[fieldName];
            if (!value) return false;
            const numericValue = parseFloat(value) || 0;
            return numericValue > this.maxAllowedWeight;
        },

        getRemainingWeightClass() {
            if (this.remainingWeight <= 0) return "text-danger";
            if (this.remainingWeight < 20) return "text-warning";
            return "text-success";
        },

        getProgressBarClass() {
            if (this.totalWeight > 100) return "bg-danger";
            if (this.totalWeight >= 80) return "bg-warning";
            return "bg-success";
        },

        formatPercentage(value) {
            if (!value && value !== 0) return "";
            return value.toString().replace(".", ",");
        },
        getFormTitle(index) {
            return this.kpis[index]?.parameter || "KPI Form";
        },
        truncateText(text, length) {
            return text.length > length
                ? text.substring(0, length) + "..."
                : text;
        },
        getDynamicOptions(fieldName) {
            // console.log("getDynamicOptions called for:", fieldName);
            // console.log("Available formulas:", this.formulas);
            // console.log("Available categories:", this.categories);
            // console.log("Available units:", this.availableUnits);

            if (fieldName === "formula")
                return this.formulas.map((f) => ({
                    value: f.id,
                    label: f.name,
                }));
            if (fieldName === "unit")
                return this.availableUnits.map((u) => ({
                    value: u.id,
                    label: u.name,
                }));

            if (fieldName === "bscCategory")
                return this.categories.map((u) => ({
                    value: u.id,
                    label: u.name,
                }));
            // console.log(this.categories);
            return [];
        },
        saveAndClose() {
            this.$emit("saveAndClose");
        },
        closeForm() {
            this.$emit("closeForm");
        },
        switchToForm(index) {
            this.$emit("switchToForm", index);
        },
        saveAndNew() {
            this.$emit("saveAndNew");
        },
        removeKpi(index) {
            this.$emit("removeKpi", index);
        },
        triggerTarget() {
            this.$emit("triggerTarget");
        },
        onFormulaChange(event) {
            this.$emit("update:modelValue", {
                ...this.activeKpi,
                formula: event.target.id,
            });
        },
    },
};
</script>

<style scoped>
/* CSS Variables */
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
}

.pop-field {
    cursor: pointer;
    color: var(--text-muted) !important;
}

/* Compact Form Section */
.compact-form-section {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-top: 0.1rem;
}

.weight-info-text {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    line-height: 1.2;
}
.weight-info-text {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: var(--surface-soft);
    border-radius: 6px;
    border-left: 3px solid var(--primary);
}

.border-success {
    border-color: var(--success) !important;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1) !important;
}

.border-danger {
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1) !important;
}

.border-warning {
    border-color: var(--warning) !important;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.1) !important;
}

.text-warning {
    color: var(--warning) !important;
}

.text-success {
    color: var(--success) !important;
}

/* Progress bar styles */
.bg-success {
    background-color: var(--success);
}
.bg-warning {
    background-color: var(--warning);
}
.bg-danger {
    background-color: var(--danger);
}

/* Pastikan simbol % terlihat jelas */
/* Input Group Styles */
.input-group-optimized {
    display: flex;
    align-items: stretch;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    background: var(--surface);
}

.input-group-optimized:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.input-group-text {
    background: var(--surface-soft);
    color: var(--text-muted);
    padding: 0.75rem 0.75rem;
    font-size: 0.9rem;
    font-weight: 500;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 50px;
    transition: all 0.3s ease;
    border-right: 1px solid var(--border);
}

.input-group-text.disabled {
    background: #f8f9fa;
    color: #6c757d;
    opacity: 0.6;
}

.input-group-optimized .form-input-optimized {
    border: none !important;
    border-radius: 0 !important;
    flex: 1;
}

.input-group-optimized .form-input-optimized:focus {
    box-shadow: none !important;
}

/* Toggle button untuk aktif/non-aktif */
.field-toggle {
    margin-left: 0.5rem;
    padding: 0.25rem 0.5rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.field-toggle:hover {
    background: var(--primary-light);
    color: var(--primary);
}

.field-toggle.disabled {
    background: var(--danger-light);
    color: var(--danger);
}
.weight-info-text {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: var(--surface-soft);
    border-radius: 6px;
    border-left: 3px solid var(--primary);
}

/* Border states */
.border-success {
    border-color: var(--success) !important;
}

.border-warning {
    border-color: var(--warning) !important;
    animation: pulse-warning 2s infinite;
}

.border-danger {
    border-color: var(--danger) !important;
}

@keyframes pulse-warning {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
    }
    50% {
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
    }
}

/* Progress bar colors */
.bg-success {
    background-color: var(--success);
}
.bg-warning {
    background-color: var(--warning);
}
.bg-danger {
    background-color: var(--danger);
}

/* Text colors */
.text-warning {
    color: var(--warning);
}
.text-success {
    color: var(--success);
}
.text-danger {
    color: var(--danger);
}
.relative .absolute {
    pointer-events: none; /* Supaya tidak mengganggu input */
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

.btn-compact {
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
}

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

/* Navigation */
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

.optimized-form-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
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
/*
.vs__dropdown-toggle {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    font-size: 0.9rem !important;
    transition: all 0.3s ease !important;
    background: var(--surface) !important;
    color: var(--text) !important;
    width: 100%;
}
/*
.v-select .vs__dropdown-toggle {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
} */
:deep(.vs__dropdown-toggle) {
    padding: 0.62rem !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    font-size: 0.9rem !important;
}
:deep(.v-select .vs__dropdown-toggle),
:deep(.v-select .vs__selected),
:deep(.v-select .vs__search) {
    font-size: 14px !important;
    color: var(--text) !important;
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

.weight-value {
    min-width: 50px;
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
}

.loading-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-style: italic;
    margin-top: 0.25rem;
}

/* Quick Actions */
.quick-actions-optimized {
    margin-top: 1.5rem;
    display: flex;
    justify-content: end;
    gap: 0.5rem;
}

.btn-quick-optimized {
    /* padding: 0.75rem 1.5rem; */
    padding: 0.7rem 0.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.vs__dropdown-toggle {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    display: flex;
    padding: 0.53rem !important;
    background: none;
    border: var(--vs-border-width) var(--vs-border-style) var(--vs-border-color);
    border-radius: var(--vs-border-radius);
    white-space: normal;
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

/* Responsive Design */
@media (max-width: 768px) {
    .quick-actions-optimized {
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .btn-quick-optimized.danger {
        flex-grow: 0.5;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .btn-quick-optimized.primary {
        flex-grow: 0.5;
        display: flex;
        justify-content: center;
        align-items: center;
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
}

@media (max-width: 480px) {
    .form-title {
        font-size: 1.1rem;
    }

    .btn-compact {
        /* padding: 0.5rem 0.8rem; */
        font-size: 0.8rem;
    }
}

/* Animations */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
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
</style>
