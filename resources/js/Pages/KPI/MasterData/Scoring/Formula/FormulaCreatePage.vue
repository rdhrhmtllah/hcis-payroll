<template>
    <div class="container-fluid px-0">
        <div class="row d-flex align-items-center justify-content-center mb-3 fade-in">
            <div class="mb-3">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-sm-8">
                                <div class="header-info">
                                    <div class="icon-container d-flex align-items-center justify-content-center">
                                        <i class="bi bi-calculator d-flex justify-content-center align-items-center"></i>
                                    </div>
                                    <div class="text-content">
                                        <h2 class="title fw-bold text-white text-start">Tambah Formula KPI</h2>
                                        <p class="subtitle">Atur dan kelola rumus perhitungan KPI untuk evaluasi kinerja</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end">
                                    <div class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3">
                                        <div class="date-item">
                                            <i v-if="dayNight == 'Siang'" class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"></i>
                                            <i v-else class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"></i>
                                            <span class="fs-md-6 text-header">{{ formatDateString(currentDate) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-n4 mb-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="row g-4">
                        <!-- Info Form -->
                        <div class="col-lg-12">
                            <FormulaInfoForm v-model="form" />
                        </div>

                        <!-- Parameter KPI + Range -->
                        <div class="col-lg-5 d-flex flex-column gap-3">
                            <KpiParameterList
                                :parameters="hardcodedParameters"
                                :loading="false"
                                @parameter-selected="handleParameterSelected"
                            />
                            <!-- Range Section -->
                            <div class="range-section" :class="needsRange ? 'range-active' : 'range-disabled'">
                                <div class="range-header d-flex align-items-center gap-2 mb-2">
                                    <i class="bi" :class="needsRange ? 'bi-sliders text-primary' : 'bi-slash-circle text-muted'"></i>
                                    <span class="fw-semibold" :class="needsRange ? 'text-primary' : 'text-muted'">Batas Range Aktual</span>
                                    <span v-if="needsRange" class="badge bg-primary-subtle text-primary ms-1" style="font-size:0.7rem">Wajib diisi</span>
                                    <span v-else class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:0.7rem">Tidak diperlukan</span>
                                </div>
                                <div v-if="!needsRange" class="range-empty-note">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Formula tidak membutuhkan batas range. Input aktual bebas.
                                </div>
                                <div v-else>
                                    <div class="range-note mb-2">
                                        <i class="bi bi-lightbulb me-1 text-warning"></i>
                                        Formula menggunakan <strong>{{ detectedFunctions }}</strong>.
                                        <span v-if="isZeroDefect" class="ms-1 text-danger fw-semibold">(Zero Defect: isi 0 dan 0)</span>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="range-label">Min Aktual <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="bi bi-arrow-down-circle"></i></span>
                                                <input
                                                    type="number" step="any" class="form-control"
                                                    :class="{ 'is-invalid': needsRange && form.Min_Range === null && showValidation }"
                                                    :value="form.Min_Range"
                                                    @input="form.Min_Range = $event.target.value === '' ? null : parseFloat($event.target.value)"
                                                    placeholder="Mis: 0"
                                                />
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label class="range-label">Max Aktual <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="bi bi-arrow-up-circle"></i></span>
                                                <input
                                                    type="number" step="any" class="form-control"
                                                    :class="{ 'is-invalid': needsRange && form.Max_Range === null && showValidation }"
                                                    :value="form.Max_Range"
                                                    @input="form.Max_Range = $event.target.value === '' ? null : parseFloat($event.target.value)"
                                                    placeholder="Mis: 100"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="range-preview mt-2" v-if="form.Min_Range !== null && form.Max_Range !== null">
                                        <i class="bi bi-bar-chart me-1 text-success"></i>
                                        Range: <strong>{{ form.Min_Range }}</strong> — <strong>{{ form.Max_Range }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formula Builder -->
                        <div class="col-lg-7">
                            <FormulaBuilder
                                :formula-text="formulaText"
                                :raw-formula="rawFormula"
                                @add-operator="handleAddOperator"
                                @add-symbol="handleAddSymbol"
                                @add-number="addNumber"
                                @add-function="addFunction"
                                @add-comma="addComma"
                                @use-template="useTemplate"
                                @clear="clear"
                                @backspace="backspace"
                            />
                        </div>

                        <!-- Tombol Simpan -->
                        <div class="col-lg-12">
                            <div class="d-grid mt-2">
                                <button
                                    class="btn btn-primary btn-lg"
                                    :disabled="loading.saveToDatabase"
                                    @click="saveToDatabase"
                                >
                                    <i v-if="loading.saveToDatabase" class="fas fa-spinner fa-spin me-2"></i>
                                    <i v-else class="fas fa-save me-2"></i>
                                    Simpan Formula
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { ElMessage, ElNotification } from "element-plus";
import FormulaInfoForm from "./components/formComponents.vue";
import KpiParameterList from "./components/kpiParameterList.vue";
import FormulaBuilder from "./components/formulaBuilder.vue";

export default {
    components: { FormulaInfoForm, KpiParameterList, FormulaBuilder },
    data() {
        return {
            currentDate: new Date(),
            formulaTokens: [],
            loading: { saveToDatabase: false },
            showValidation: false,
            hardcodedParameters: [
                { id: "actualKpiEvo", label: "Actual", icon: "fa-check-circle" },
                { id: "targetKpiEvo", label: "Target", icon: "fa-bullseye" },
            ],
            form: { Nama_Formula: "", Deskripsi: "", Min_Range: null, Max_Range: null },
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        formulaText() {
            if (this.formulaTokens.length === 0) return "[Formula akan muncul di sini]";
            let text = "";
            this.formulaTokens.forEach((token, i) => {
                const prev = i > 0 ? this.formulaTokens[i - 1] : null;
                const noSpaceBefore =
                    token.type === ")" || token.type === "comma";
                const prevNoSpaceAfter =
                    prev && (prev.type === "(" || prev.type === "function(");
                if (i > 0 && !noSpaceBefore && !prevNoSpaceAfter) {
                    text += " ";
                }
                text += token.display;
            });
            return text;
        },
        rawFormula() {
            return this.formulaTokens.map((t) => t.code).join("");
        },
        needsRange() {
            const f = (this.rawFormula || "").toUpperCase();
            return f.includes("MIN(") || f.includes("MAX(") || f.includes("IF(");
        },
        isZeroDefect() {
            const f = (this.rawFormula || "").toUpperCase();
            return f.includes("IF(") && !f.includes("MAX(") && !f.includes("MIN(");
        },
        detectedFunctions() {
            const f = (this.rawFormula || "").toUpperCase();
            const fns = [];
            if (f.includes("IF("))  fns.push("IF");
            if (f.includes("MIN(")) fns.push("MIN");
            if (f.includes("MAX(")) fns.push("MAX");
            return fns.join(", ") || "-";
        },
    },
    watch: {
        needsRange(val) {
            if (!val) {
                this.form.Min_Range = null;
                this.form.Max_Range = null;
            }
        },
    },
    methods: {
        formatDateString(dateString) {
            if (!dateString) return "";
            try {
                const date = new Date(dateString);
                return new Intl.DateTimeFormat("id-ID", {
                    day: "numeric", month: "long", year: "numeric",
                }).format(date);
            } catch { return dateString; }
        },

        formulaNeedsRange() {
            const f = (this.rawFormula || "").toUpperCase();
            return f.includes("MIN(") || f.includes("MAX(") || f.includes("IF(");
        },
        async saveToDatabase() {
            if (!this.rawFormula || !this.form.Nama_Formula) {
                ElNotification({ title: "Validasi", message: "Nama formula dan isi formula tidak boleh kosong.", type: "warning" });
                return;
            }
            if (this.formulaNeedsRange() && (this.form.Min_Range === null || this.form.Max_Range === null)) {
                this.showValidation = true;
                ElNotification({ title: "Validasi", message: "Formula ini membutuhkan batas Min dan Max aktual.", type: "warning" });
                return;
            }
            this.loading.saveToDatabase = true;
            try {
                const response = await axios.post("/api/v1/formula-kpi/store", {
                    Rumus: this.rawFormula,
                    Nama_Formula: this.form.Nama_Formula,
                    Deskripsi: this.form.Deskripsi,
                    Min_Range: this.formulaNeedsRange() ? this.form.Min_Range : null,
                    Max_Range: this.formulaNeedsRange() ? this.form.Max_Range : null,
                });
                if (response.status === 201) {
                    ElNotification({ title: "Sukses", message: "Data berhasil disimpan!", type: "success" });
                    window.location.href = "/formula-kpi";
                }
            } catch (error) {
                ElNotification({
                    title: "Error",
                    message: error.response?.data?.message || "Terjadi kesalahan saat menyimpan.",
                    type: "error",
                });
            } finally {
                this.loading.saveToDatabase = false;
            }
        },

        // ─── Token helpers ────────────────────────────────────────────
        getLastToken() {
            return this.formulaTokens.length > 0
                ? this.formulaTokens[this.formulaTokens.length - 1]
                : null;
        },
        isOperand(token) {
            if (!token) return false;
            return ["parameter", "number", ")"].includes(token.type);
        },
        canInsertValue(token) {
            // Nilai boleh ditambahkan setelah: awal, operator, (, function(, comma
            if (!token) return true;
            return ["operator", "(", "function(", "comma"].includes(token.type);
        },

        // ─── Event handlers dari FormulaBuilder ──────────────────────
        handleAddOperator(op) {
            const last = this.getLastToken();
            if (!this.isOperand(last)) {
                ElMessage.warning("Pilih parameter atau angka dulu sebelum operator!");
                return;
            }
            this.formulaTokens.push({ display: op.display, code: op.value, type: "operator" });
        },
        handleAddSymbol(symbol) {
            if (symbol === ".") {
                this.addDot();
            } else {
                this.addParenthesis(symbol);
            }
        },
        handleParameterSelected(parameter) {
            this.addParameter(parameter.id, parameter.label);
        },

        addNumber(num) {
            const last = this.getLastToken();
            if (last && last.type === "number" && !last.code.includes(".")) {
                last.display += String(num);
                last.code += String(num);
            } else {
                if (this.isOperand(last)) {
                    ElMessage.warning("Masukkan operator terlebih dahulu!");
                    return;
                }
                this.formulaTokens.push({ display: String(num), code: String(num), type: "number" });
            }
        },
        addDot() {
            const last = this.getLastToken();
            if (last && last.type === "number" && !last.code.includes(".")) {
                last.display += ".";
                last.code += ".";
            } else if (!last || !this.isOperand(last)) {
                this.formulaTokens.push({ display: "0.", code: "0.", type: "number" });
            } else {
                ElMessage.warning("Angka sudah memiliki titik desimal.");
            }
        },
        addParameter(id, name) {
            const last = this.getLastToken();
            if (this.isOperand(last)) {
                ElMessage.warning("Masukkan operator terlebih dahulu!");
                return;
            }
            this.formulaTokens.push({ display: name, code: `[${id}]`, type: "parameter" });
        },
        addFunction(functionName) {
            const last = this.getLastToken();
            if (this.isOperand(last)) {
                ElMessage.warning("Masukkan operator terlebih dahulu!");
                return;
            }
            this.formulaTokens.push({
                display: `${functionName}(`,
                code: `${functionName}(`,
                type: "function(",
            });
        },
        addComma() {
            const last = this.getLastToken();
            if (!this.isOperand(last)) {
                ElMessage.warning("Koma hanya boleh ditempatkan setelah nilai atau penutup kurung!");
                return;
            }
            this.formulaTokens.push({ display: ",", code: ",", type: "comma" });
        },
        addParenthesis(paren) {
            this.formulaTokens.push({ display: paren, code: paren, type: paren });
        },
        useTemplate(tmpl) {
            this.parseRawFormulaToTokens(tmpl.rawCode);
        },
        clear() {
            this.formulaTokens = [];
        },
        backspace() {
            this.formulaTokens.pop();
        },

        // ─── Parser formula raw → tokens (untuk template & edit) ─────
        parseRawFormulaToTokens(rawFormula) {
            const tokens = [];
            let i = 0;
            const str = rawFormula.trim();
            const paramMap = {};
            this.hardcodedParameters.forEach((p) => { paramMap[p.id] = p.label; });

            while (i < str.length) {
                if (str[i] === " ") { i++; continue; }

                // [paramId]
                if (str[i] === "[") {
                    const end = str.indexOf("]", i);
                    if (end !== -1) {
                        const paramId = str.substring(i + 1, end);
                        const label = paramMap[paramId] || paramId;
                        tokens.push({ display: label, code: `[${paramId}]`, type: "parameter" });
                        i = end + 1;
                        continue;
                    }
                }

                // Fungsi: IF, MIN, MAX, ABS, ROUND, SQRT, POW, CEIL, FLOOR
                const fnMatch = str.substring(i).match(/^(IF|MIN|MAX|ABS|ROUND|SQRT|POW|CEIL|FLOOR)\s*\(/i);
                if (fnMatch) {
                    const name = fnMatch[1].toUpperCase();
                    tokens.push({ display: `${name}(`, code: `${name}(`, type: "function(" });
                    i += fnMatch[0].length;
                    continue;
                }

                // Angka (termasuk desimal)
                const numMatch = str.substring(i).match(/^(\d+\.?\d*)/);
                if (numMatch) {
                    tokens.push({ display: numMatch[1], code: numMatch[1], type: "number" });
                    i += numMatch[0].length;
                    continue;
                }

                // Dua-karakter operator
                const two = str.substring(i, i + 2);
                const twoMap = {
                    "==": { display: "==",     type: "operator" },
                    "!=": { display: "!=",     type: "operator" },
                    ">=": { display: ">=",     type: "operator" },
                    "<=": { display: "<=",     type: "operator" },
                    "&&": { display: "DAN (&&)",  type: "operator" },
                    "||": { display: "ATAU (||)", type: "operator" },
                };
                if (twoMap[two]) {
                    tokens.push({ display: twoMap[two].display, code: two, type: twoMap[two].type });
                    i += 2;
                    continue;
                }

                // Satu-karakter
                const ch = str[i];
                const oneMap = {
                    "+": { display: "+", type: "operator" },
                    "-": { display: "−", type: "operator" },
                    "*": { display: "×", type: "operator" },
                    "/": { display: "÷", type: "operator" },
                    "%": { display: "%", type: "operator" },
                    ">": { display: ">", type: "operator" },
                    "<": { display: "<", type: "operator" },
                    "(": { display: "(", type: "(" },
                    ")": { display: ")", type: ")" },
                    ",": { display: ",", type: "comma" },
                };
                if (oneMap[ch]) {
                    tokens.push({ display: oneMap[ch].display, code: ch, type: oneMap[ch].type });
                }
                i++;
            }
            this.formulaTokens = tokens;
        },
    },
};
</script>

<style scoped>
*,*::before,*::after {
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}
.modern-header {
    position: relative; border-radius: 24px; overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); min-height: 140px;
}
.header-background {
    position: absolute; inset: 0;
    background: var(--gradient-primary); opacity: .95;
}
.header-background::before {
    content: ""; position: absolute; inset: 0;
    background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.1"><circle cx="30" cy="30" r="2"/></g></svg>');
    animation: float 20s ease-in-out infinite;
}
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-20px)} }
.header-content { position: relative; z-index: 2; padding: 2.5rem; color: white; }
.header-info { display: flex; align-items: center; justify-content: start; gap: 1.5rem; }
.icon-container {
    width: 80px; height: 80px; background: rgba(255,255,255,.2); border-radius: 20px;
    backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.3);
}
.icon-container i { font-size: 2.2rem; }
.title { font-size: 2rem; font-weight: 400; margin: 0; }
.subtitle { margin: .5rem 0 0; opacity: .9; font-size: 1.1rem; }
.header-actions { height: 100%; }
.date-range {
    display: flex; align-items: center;
    background: rgba(255,255,255,.15); border-radius: 12px;
    backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.2);
}
.date-item { text-align: center; color: white; }
.card { border-radius: 1rem; }
.range-section { border-radius: 10px; padding: 14px 16px; transition: all 0.2s ease; }
.range-active  { background: #f0f4ff; border: 1.5px solid #c7d7fd; }
.range-disabled{ background: #f8f9fa; border: 1.5px dashed #dee2e6; }
.range-header  { font-size: 0.875rem; }
.range-empty-note { font-size: 0.8rem; color: #6c757d; padding: 6px 0; }
.range-note    { font-size: 0.8rem; color: #495057; background: #fff8e1; padding: 6px 10px; border-radius: 6px; border-left: 3px solid #ffc107; }
.range-preview { font-size: 0.8rem; color: #198754; padding: 4px 8px; background: #f0fff4; border-radius: 6px; }
.range-label   { font-weight: 600; font-size: 0.8rem; color: #495057; }
@media (max-width: 768px) {
    .header-content { padding: 1rem; }
    .header-info { gap: .75rem; }
    .icon-container { width: 50px; height: 50px; }
    .icon-container i { font-size: 1.5rem; }
    .title { font-size: 1.25rem; }
    .subtitle { font-size: .9rem; }
}
</style>
