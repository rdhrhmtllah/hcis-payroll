<template>
    <div class="formula-builder">
        <!-- Header -->
        <div class="formula-header">
            <div class="formula-header-title">
                <i class="fas fa-calculator"></i>
                <span>Kalkulator Formula KPI</span>
            </div>
            <span class="formula-header-hint">Klik tombol untuk menyusun formula</span>
        </div>

        <!-- Display Formula -->
        <div class="formula-display-container">
            <div
                class="formula-preview"
                :class="{ 'is-placeholder': isPlaceholder }"
            >
                <span v-if="isPlaceholder" class="placeholder-text">
                    <i class="fas fa-edit me-1"></i> Formula akan tampil di sini...
                </span>
                <span v-else>{{ formulaText }}</span>
            </div>
            <div class="formula-raw-label" v-if="!isPlaceholder">
                <small class="text-muted"><i class="fas fa-code me-1"></i>Raw: <code>{{ rawFormula }}</code></small>
            </div>
        </div>

        <div class="formula-body">

            <!-- Bagian Template Preset -->
            <div class="section-block">
                <div class="section-label">
                    <i class="fas fa-magic"></i> Template Formula Siap Pakai
                </div>
                <div class="template-grid">
                    <button
                        v-for="tmpl in templates"
                        :key="tmpl.id"
                        class="btn-template"
                        :title="tmpl.description"
                        @click="$emit('use-template', tmpl)"
                    >
                        <div class="template-icon" :class="tmpl.colorClass">
                            <i :class="tmpl.icon"></i>
                        </div>
                        <div class="template-content">
                            <span class="template-name">{{ tmpl.name }}</span>
                            <span class="template-formula">{{ tmpl.display }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Operator Perbandingan -->
            <div class="section-block">
                <div class="section-label">
                    <i class="fas fa-not-equal"></i> Operator Perbandingan
                    <span class="section-hint">Digunakan di dalam IF(kondisi, ...)</span>
                </div>
                <div class="btn-row">
                    <button
                        v-for="op in comparisonOperators"
                        :key="op.value"
                        class="btn-control btn-comparison"
                        :title="op.description"
                        @click="$emit('add-operator', op)"
                    >{{ op.display }}</button>
                </div>
            </div>

            <!-- Operator Logika -->
            <div class="section-block">
                <div class="section-label">
                    <i class="fas fa-code-branch"></i> Operator Logika
                    <span class="section-hint">Gabungkan beberapa kondisi</span>
                </div>
                <div class="btn-row">
                    <button
                        v-for="op in logicalOperators"
                        :key="op.value"
                        class="btn-control btn-logical"
                        :title="op.description"
                        @click="$emit('add-operator', op)"
                    >{{ op.display }}</button>
                </div>
            </div>

            <!-- Operator Matematika -->
            <div class="section-block">
                <div class="section-label">
                    <i class="fas fa-plus-minus"></i> Operator Matematika
                </div>
                <div class="btn-row">
                    <button
                        v-for="op in mathOperators"
                        :key="op.value"
                        class="btn-control btn-operator"
                        @click="$emit('add-operator', op)"
                    >{{ op.display }}</button>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Fungsi -->
            <div class="section-block">
                <div class="section-label">
                    <i class="fas fa-function"></i> Fungsi Kalkulator
                    <span class="section-hint">Klik fungsi → isi argumen → tutup dengan )</span>
                </div>
                <div class="function-grid">
                    <button
                        v-for="fn in functions"
                        :key="fn.name"
                        class="btn-control btn-function"
                        :class="fn.highlight ? 'btn-function-if' : ''"
                        :title="fn.description"
                        @click="$emit('add-function', fn.name)"
                    >
                        <span class="fn-name">{{ fn.name }}</span>
                        <span class="fn-args">{{ fn.args }}</span>
                    </button>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Angka + Simbol dalam tata letak kalkulator -->
            <div class="calculator-layout">
                <!-- Simbol -->
                <div class="section-block">
                    <div class="section-label">
                        <i class="fas fa-brackets-curly"></i> Simbol & Pemisah
                    </div>
                    <div class="symbol-row">
                        <button class="btn-control btn-symbol" @click="$emit('add-symbol', '(')">(</button>
                        <button class="btn-control btn-symbol" @click="$emit('add-symbol', ')')">)</button>
                        <button class="btn-control btn-comma" @click="$emit('add-comma')" title="Pemisah argumen fungsi IF / MIN / MAX">
                            <span>, Koma</span>
                        </button>
                        <button class="btn-control btn-symbol" @click="$emit('add-symbol', '.')">.</button>
                    </div>
                </div>

                <!-- Grid Angka -->
                <div class="section-block">
                    <div class="section-label">
                        <i class="fas fa-sort-numeric-up"></i> Angka
                    </div>
                    <div class="number-grid">
                        <!-- Baris 1 -->
                        <button class="btn-control btn-number" @click="$emit('add-number', 7)">7</button>
                        <button class="btn-control btn-number" @click="$emit('add-number', 8)">8</button>
                        <button class="btn-control btn-number" @click="$emit('add-number', 9)">9</button>
                        <!-- Baris 2 -->
                        <button class="btn-control btn-number" @click="$emit('add-number', 4)">4</button>
                        <button class="btn-control btn-number" @click="$emit('add-number', 5)">5</button>
                        <button class="btn-control btn-number" @click="$emit('add-number', 6)">6</button>
                        <!-- Baris 3 -->
                        <button class="btn-control btn-number" @click="$emit('add-number', 1)">1</button>
                        <button class="btn-control btn-number" @click="$emit('add-number', 2)">2</button>
                        <button class="btn-control btn-number" @click="$emit('add-number', 3)">3</button>
                        <!-- Baris 4 -->
                        <button class="btn-control btn-number btn-zero" @click="$emit('add-number', 0)">0</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="formula-actions">
            <button class="btn-action btn-backspace" @click="$emit('backspace')">
                <i class="fas fa-backspace"></i> Hapus Satu
            </button>
            <button class="btn-action btn-clear" @click="$emit('clear')">
                <i class="fas fa-eraser"></i> Bersihkan Semua
            </button>
        </div>

        <!-- Panduan -->
        <div class="formula-guide">
            <div class="guide-title"><i class="fas fa-lightbulb"></i> Panduan Cepat</div>
            <div class="guide-items">
                <div class="guide-item">
                    <span class="guide-badge badge-blue">Semakin Besar Lebih Baik</span>
                    <code>Actual ÷ Target</code>
                </div>
                <div class="guide-item">
                    <span class="guide-badge badge-orange">Semakin Kecil Lebih Baik</span>
                    <code>Target ÷ Actual</code>
                </div>
                <div class="guide-item">
                    <span class="guide-badge badge-red">Target = 0 (Zero Defect)</span>
                    <code>IF(Actual == 0, 1, 0)</code>
                </div>
                <div class="guide-item">
                    <span class="guide-badge badge-green">Capaian Maks 100%</span>
                    <code>MIN(1, Actual ÷ Target)</code>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "formulaBuilder",
    props: {
        formulaText: { type: String, required: true },
        rawFormula: { type: String, default: "" },
    },
    emits: [
        "add-operator",
        "add-symbol",
        "add-number",
        "add-function",
        "add-comma",
        "use-template",
        "clear",
        "backspace",
    ],
    computed: {
        isPlaceholder() {
            return (
                !this.formulaText ||
                this.formulaText === "[Formula akan muncul di sini]"
            );
        },
    },
    data() {
        return {
            comparisonOperators: [
                { display: "==", value: "==", description: "Sama dengan" },
                { display: "!=", value: "!=", description: "Tidak sama dengan" },
                { display: ">=", value: ">=", description: "Lebih besar atau sama" },
                { display: "<=", value: "<=", description: "Lebih kecil atau sama" },
                { display: ">",  value: ">",  description: "Lebih besar dari" },
                { display: "<",  value: "<",  description: "Lebih kecil dari" },
            ],
            logicalOperators: [
                { display: "DAN (&&)",  value: "&&", description: "Kedua kondisi harus terpenuhi" },
                { display: "ATAU (||)", value: "||", description: "Salah satu kondisi terpenuhi" },
            ],
            mathOperators: [
                { display: "+", value: "+" },
                { display: "−", value: "-" },
                { display: "×", value: "*" },
                { display: "÷", value: "/" },
                { display: "%", value: "%" },
            ],
            functions: [
                {
                    name: "IF",
                    args: "(kondisi, nilai_benar, nilai_salah)",
                    description: "Logika percabangan. Contoh: IF(Actual==0, 1, 0)",
                    highlight: true,
                },
                {
                    name: "MIN",
                    args: "(a, b)",
                    description: "Nilai minimum dari dua angka. Contoh: MIN(1, Actual/Target)",
                    highlight: false,
                },
                {
                    name: "MAX",
                    args: "(a, b)",
                    description: "Nilai maksimum dari dua angka. Contoh: MAX(0, Actual/Target)",
                    highlight: false,
                },
                {
                    name: "ABS",
                    args: "(nilai)",
                    description: "Nilai absolut (tanpa tanda negatif)",
                    highlight: false,
                },
                {
                    name: "ROUND",
                    args: "(nilai, desimal)",
                    description: "Bulatkan angka ke sejumlah desimal",
                    highlight: false,
                },
                {
                    name: "SQRT",
                    args: "(nilai)",
                    description: "Akar kuadrat",
                    highlight: false,
                },
            ],
            templates: [
                {
                    id: "higher_better",
                    name: "Semakin Besar = Semakin Baik",
                    display: "Actual ÷ Target",
                    rawCode: "[actualKpiEvo]/[targetKpiEvo]",
                    description: "Target harus > 0. Contoh: penjualan, produksi, omzet",
                    icon: "fas fa-arrow-trend-up",
                    colorClass: "tmpl-blue",
                },
                {
                    id: "lower_better_nonzero",
                    name: "Semakin Kecil = Semakin Baik",
                    display: "Target ÷ Actual",
                    rawCode: "[targetKpiEvo]/[actualKpiEvo]",
                    description: "Target harus > 0. Contoh: keluhan pelanggan, waktu proses",
                    icon: "fas fa-arrow-trend-down",
                    colorClass: "tmpl-orange",
                },
                {
                    id: "zero_defect",
                    name: "Zero Defect (Target = 0)",
                    display: "IF(Actual == 0, 1, 0)",
                    rawCode: "IF([actualKpiEvo]==0,1,0)",
                    description: "Sempurna jika actual = 0, gagal jika actual > 0. Contoh: kecelakaan kerja, error kritis",
                    icon: "fas fa-shield-check",
                    colorClass: "tmpl-red",
                },
                {
                    id: "lower_zero_gradual",
                    name: "Target = 0, Nilai Gradual",
                    display: "IF(Actual == 0, 1, MAX(0, 1 − Actual ÷ Batas))",
                    rawCode: "IF([actualKpiEvo]==0,1,MAX(0,1-[actualKpiEvo]/[targetKpiEvo]))",
                    description: "Target = 0 tapi ada batas deviasi toleransi. Contoh: komplain maks 10",
                    icon: "fas fa-gauge",
                    colorClass: "tmpl-purple",
                },
                {
                    id: "capped_100",
                    name: "Capaian Maksimal 100%",
                    display: "MIN(1, Actual ÷ Target)",
                    rawCode: "MIN(1,[actualKpiEvo]/[targetKpiEvo])",
                    description: "Capaian tidak bisa melebihi 100%, meski actual > target",
                    icon: "fas fa-percent",
                    colorClass: "tmpl-green",
                },
                {
                    id: "capped_both",
                    name: "Capaian 0% – 100%",
                    display: "MIN(1, MAX(0, Actual ÷ Target))",
                    rawCode: "MIN(1,MAX(0,[actualKpiEvo]/[targetKpiEvo]))",
                    description: "Capaian dibatasi antara 0% (minimum) dan 100% (maksimum)",
                    icon: "fas fa-sliders",
                    colorClass: "tmpl-teal",
                },
            ],
        };
    },
};
</script>

<style scoped>
.formula-builder {
    background: var(--hcis-white);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e8ecf0;
    font-family: "Segoe UI", sans-serif;
}

/* ===== HEADER ===== */
.formula-header {
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}
.formula-header-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
}
.formula-header-hint {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.8);
}

/* ===== DISPLAY ===== */
.formula-display-container {
    background: #f7f8fb;
    border-bottom: 2px solid var(--hcis-slate-200);
    padding: 14px 20px 10px;
}
.formula-preview {
    font-size: 1.15rem;
    font-weight: 600;
    min-height: 44px;
    padding: 10px 14px;
    background: white;
    border: 2px solid var(--hcis-indigo-500);
    border-radius: 10px;
    color: var(--hcis-slate-800);
    word-break: break-all;
    line-height: 1.6;
    transition: border-color 0.2s;
}
.formula-preview.is-placeholder {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}
.placeholder-text {
    color: var(--hcis-slate-400);
    font-weight: 400;
    font-size: 0.95rem;
}
.formula-raw-label {
    margin-top: 6px;
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    word-break: break-all;
}
.formula-raw-label code {
    background: var(--hcis-slate-100);
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 0.75rem;
    color: #5b6ef1;
}

/* ===== BODY ===== */
.formula-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 560px;
    overflow-y: auto;
}
.formula-body::-webkit-scrollbar { width: 5px; }
.formula-body::-webkit-scrollbar-track { background: var(--hcis-slate-100); }
.formula-body::-webkit-scrollbar-thumb { background: var(--hcis-slate-300); border-radius: 10px; }

.divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--hcis-slate-200), transparent);
    margin: 4px 0;
}

/* ===== SECTION ===== */
.section-block {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.section-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    display: flex;
    align-items: center;
    gap: 6px;
}
.section-hint {
    font-weight: 400;
    color: var(--hcis-slate-400);
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.72rem;
}

/* ===== TEMPLATE GRID ===== */
.template-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}
.btn-template {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1.5px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
}
.btn-template:hover {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-violet-50);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
}
.template-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.9rem;
}
.tmpl-blue   { background: #dbeafe; color: #1d4ed8; }
.tmpl-orange { background: var(--hcis-orange-100); color: #c2410c; }
.tmpl-red    { background: var(--hcis-red-100); color: var(--hcis-red-700); }
.tmpl-purple { background: #ede9fe; color: var(--hcis-violet-600); }
.tmpl-green  { background: var(--hcis-green-100); color: var(--hcis-green-700); }
.tmpl-teal   { background: #ccfbf1; color: #0f766e; }

.template-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.template-name {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.template-formula {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    font-family: monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ===== BUTTON ROW ===== */
.btn-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

/* ===== FUNCTION GRID ===== */
.function-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.btn-function {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 6px;
    border-radius: 8px;
    background: var(--hcis-slate-50);
    border: 1.5px solid var(--hcis-slate-200);
    cursor: pointer;
    transition: all 0.2s;
}
.btn-function:hover {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-400);
}
.btn-function-if {
    background: #ede9fe !important;
    border-color: var(--hcis-violet-400) !important;
    color: var(--hcis-violet-700);
}
.btn-function-if:hover {
    background: #ddd6fe !important;
    border-color: var(--hcis-violet-600) !important;
}
.fn-name {
    font-weight: 700;
    font-size: 0.88rem;
    color: inherit;
}
.fn-args {
    font-size: 0.6rem;
    color: var(--hcis-slate-400);
    text-align: center;
    margin-top: 2px;
    line-height: 1.2;
}

/* ===== SYMBOL ROW ===== */
.symbol-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.btn-symbol {
    min-width: 44px;
    padding: 8px;
    border-radius: 8px;
    background: #f0f0f0;
    border: 1px solid #d1d5db;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-symbol:hover { background: #e5e7eb; }
.btn-comma {
    padding: 8px 14px;
    border-radius: 8px;
    background: var(--hcis-amber-100);
    border: 1.5px solid var(--hcis-amber-400);
    color: var(--hcis-amber-800);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-comma:hover { background: var(--hcis-amber-200); }

/* ===== NUMBER GRID ===== */
.calculator-layout {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 16px;
    align-items: start;
}
.number-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.btn-zero {
    grid-column: 1 / -1;
}
.btn-number {
    padding: 12px 8px;
    border-radius: 8px;
    background: #f0f0f0;
    border: 1px solid #d1d5db;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
}
.btn-number:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
}

/* ===== COMMON BUTTON STYLES ===== */
.btn-control {
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}
.btn-control:active { transform: scale(0.95); }

.btn-operator {
    padding: 8px 14px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--hcis-legacy-violet-400), var(--hcis-legacy-violet-700));
    color: white;
    font-weight: 700;
    font-size: 1rem;
    border: none;
}
.btn-operator:hover { filter: brightness(1.1); }

.btn-comparison {
    padding: 7px 12px;
    border-radius: 8px;
    background: var(--hcis-cyan-50);
    border: 1.5px solid var(--hcis-cyan-200) !important;
    color: var(--hcis-cyan-700);
    font-weight: 700;
    font-family: monospace;
    font-size: 0.88rem;
}
.btn-comparison:hover { background: var(--hcis-cyan-100); }

.btn-logical {
    padding: 7px 14px;
    border-radius: 8px;
    background: var(--hcis-green-50);
    border: 1.5px solid #86efac !important;
    color: var(--hcis-green-700);
    font-weight: 600;
    font-size: 0.82rem;
}
.btn-logical:hover { background: var(--hcis-green-100); }

/* ===== ACTIONS ===== */
.formula-actions {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 16px;
    background: var(--hcis-slate-50);
    border-top: 1px solid var(--hcis-slate-200);
}
.btn-action {
    flex: 1;
    border: none;
    border-radius: 8px;
    padding: 9px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-clear {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}
.btn-clear:hover { background: var(--hcis-red-200); }
.btn-backspace {
    background: #fff7ed;
    color: #c2410c;
}
.btn-backspace:hover { background: #fed7aa; }

/* ===== PANDUAN ===== */
.formula-guide {
    margin: 0 16px 16px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    padding: 12px 14px;
}
.guide-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.guide-items {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.guide-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
}
.guide-item code {
    font-size: 0.75rem;
    background: white;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-700);
}
.guide-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 20px;
    white-space: nowrap;
}
.badge-blue   { background: #dbeafe; color: #1e40af; }
.badge-orange { background: var(--hcis-orange-100); color: #9a3412; }
.badge-red    { background: var(--hcis-red-100); color: var(--hcis-red-800); }
.badge-green  { background: var(--hcis-green-100); color: #166534; }

/* Responsive */
@media (max-width: 600px) {
    .template-grid { grid-template-columns: 1fr; }
    .function-grid { grid-template-columns: repeat(2, 1fr); }
    .calculator-layout { grid-template-columns: 1fr; }
}
</style>
