<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisCollapse Accordion Studio</h3>
            </div>
            <!-- Stage Background Switcher -->
            <div class="ds-stage-switcher" role="group" aria-label="Latar Panggung">
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'light' }"
                    @click="studioStageBg = 'light'"
                >
                    Light
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'dark' }"
                    @click="studioStageBg = 'dark'"
                >
                    Dark 🌑
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'mesh' }"
                    @click="studioStageBg = 'mesh'"
                >
                    Mesh 🔮
                </button>
            </div>
        </div>

        <div class="ds-studio-body">
            <!-- Preview Stage Canvas -->
            <div
                class="ds-preview-stage"
                :class="[
                    `ds-stage-bg--${studioStageBg}`,
                    { 'ds-stage-dark-mode': studioStageBg === 'dark' }
                ]"
            >
                <div class="p-3 bg-white rounded-xl shadow-sm border border-slate-200" style="width: 100%; max-width: 580px;">
                    <HcisCollapse
                        v-model="studioActiveNames"
                        :accordion="studioAccordion"
                        :variant="studioVariant"
                    >
                        <HcisCollapseItem
                            name="riwayat"
                            title="Riwayat Persetujuan Pengajuan Cuti"
                            description="Detail alur verifikasi bertingkat atasan & HR"
                            icon="clock-history"
                            badge="3 Langkah"
                        >
                            <div class="pt-2">
                                <HcisSteps :current="2" direction="vertical" :steps="alur" />
                            </div>
                        </HcisCollapseItem>

                        <HcisCollapseItem
                            name="lampiran"
                            title="Lampiran Dokumen Medis / Surat Dokter"
                            description="Verifikasi bukti autentik surat keterangan sakit"
                            icon="file-earmark-medical"
                            badge="PDF Terlampir"
                        >
                            <div class="d-flex align-items-center gap-3 p-2 bg-slate-50 border border-slate-200 rounded-lg">
                                <HcisIcon name="file-earmark-pdf-fill" size="lg" class="text-rose-600" />
                                <div>
                                    <div class="text-xs font-bold text-slate-800">surat-keterangan-dokter-agustus-2026.pdf</div>
                                    <div class="text-2xs text-slate-500 font-semibold">1.4 MB · Diunggah 12 Agu 2026</div>
                                </div>
                            </div>
                        </HcisCollapseItem>

                        <HcisCollapseItem
                            name="faq"
                            title="Ketentuan Kebijakan Cuti Tahunan"
                            description="Regulasi kompensasi & sisa saldo hak cuti"
                            icon="shield-check"
                        >
                            <p class="text-xs text-slate-600 mb-0 leading-relaxed">
                                Pengajuan cuti tahunan diajukan minimal H-3 sebelum tanggal pelaksanaan melalui portal HCIS. Sisa hak cuti tahunan yang tidak terpakai akan hangus pada akhir kuartal pertama tahun berikutnya.
                            </p>
                        </HcisCollapseItem>
                    </HcisCollapse>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Varian Tampilan</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Kartu Terpisah Luxury)</option>
                        <option value="bordered">Bordered (Panel Terhubung)</option>
                        <option value="ghost">Ghost (Minimal Tanpa Garis Luar)</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Mode Perilaku</label>
                    <div class="d-flex align-items-center gap-3 pt-2">
                        <div class="form-check form-switch m-0">
                            <input id="acc-mode" v-model="studioAccordion" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="acc-mode">Accordion (1 Panel Buka)</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Generator -->
            <div class="ds-code-generator">
                <div class="ds-code-header">
                    <span class="ds-code-lang">Vue 3 SFC Template</span>
                    <button type="button" class="ds-copy-btn" @click="copySnippet">
                        <HcisIcon :name="copied ? 'check2' : 'clipboard'" size="xs" />
                        <span>{{ copied ? 'Tersalin!' : 'Salin Kode' }}</span>
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ generatedSnippet }}</code></pre>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. ENTERPRISE SCENARIOS & REAL-WORLD WORKFLOWS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Bordered (Konten Terhubung FAQ Kebijakan HC):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <HcisCollapse v-model="borderedActive" variant="bordered">
                    <HcisCollapseItem
                        name="faq1"
                        title="Bagaimana Prosedur Pengajuan Cuti Tahunan?"
                        icon="question-circle"
                    >
                        <p class="text-xs text-slate-600 mb-0">
                            Pengajuan cuti dilakukan melalui portal HCIS minimal 3 hari sebelum tanggal pelaksanaan.
                        </p>
                    </HcisCollapseItem>
                    <HcisCollapseItem
                        name="faq2"
                        title="Kapan Batas Waktu Pengisian Skor Penilaian KPI?"
                        icon="question-circle"
                    >
                        <p class="text-xs text-slate-600 mb-0">
                            Batas waktu penilaian KPI adalah tanggal 25 setiap bulannya.
                        </p>
                    </HcisCollapseItem>
                </HcisCollapse>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <HcisAlert
            variant="warning"
            title="Prinsip Hirarki Accordion"
            description="Informasi utama yang dibutuhkan pengambil keputusan tidak boleh dilipat di dalam accordion. Konten terlipat khusus untuk detail sekunder seperti riwayat audit, lampiran file, atau FAQ."
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisAlert, HcisSteps, HcisCollapse, HcisCollapseItem, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioActiveNames = ref(['riwayat']);
const studioAccordion = ref(false);
const studioVariant = ref('card');
const borderedActive = ref(['faq1']);
const copied = ref(false);

const alur = [
    { title: 'Diajukan Karyawan', description: 'Budi Santoso (12 Agu 2026, 08:30)' },
    { title: 'Disetujui Supervisor', description: 'Siti Aminah (12 Agu 2026, 10:15)' },
    { title: 'Verifikasi HRD', description: 'Menunggu proses HRD Operations' },
];

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="activeNames"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioAccordion.value) props.push(`accordion`);

    return `<HcisCollapse ${props.join(' ')}>\n    <HcisCollapseItem name="panel1" title="Judul Panel" icon="clock-history">\n        Konten panel...\n    </HcisCollapseItem>\n</HcisCollapse>`;
});

function copySnippet() {
    navigator.clipboard.writeText(generatedSnippet.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}
</script>

<style scoped>
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.5rem; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }
</style>
