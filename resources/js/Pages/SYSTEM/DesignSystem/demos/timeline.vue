<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisTimeline Audit Trail Studio</h3>
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
                <div style="width: 100%; max-width: 780px;">
                    <HcisTimeline
                        :card="studioCard"
                        :title="studioTitle"
                        :kicker="studioKicker"
                        :description="studioSubtitle"
                        :sentiment="studioSentiment"
                        :pattern="studioPattern"
                        :variant="studioVariant"
                        :direction="studioDirection"
                        :active-step="studioActiveStep"
                        :items="auditTrailDemo"
                        interactive
                        @item-click="handleItemClick"
                        @action-click="handleActionClick"
                    />

                    <div v-if="lastEventLog" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Log Event:</strong> {{ lastEventLog }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Arah Alur (Direction)</label>
                    <select v-model="studioDirection" class="form-select form-select-sm">
                        <option value="vertical">Vertical (Tegak Bawah)</option>
                        <option value="horizontal">Horizontal (Mendatar Stream)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Varian Visual (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Surface Solid)</option>
                        <option value="glass">Glass (Frosted Glass)</option>
                        <option value="flat">Flat (Minimalis)</option>
                        <option value="compact">Compact (Padat)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Sentimen Warna</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="brand">Brand (Royal Indigo)</option>
                        <option value="success">Success (Emerald)</option>
                        <option value="purple">Purple / Violet</option>
                        <option value="teal">Teal / Cyan</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Pola Latar (Pattern)</label>
                    <select v-model="studioPattern" class="form-select form-select-sm" :disabled="!studioCard">
                        <option value="none">None</option>
                        <option value="grid">Grid Pattern</option>
                        <option value="dots">Dots Pattern</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch m-0 pt-1">
                        <input id="tl-crd" v-model="studioCard" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="tl-crd">Bungkus dalam HcisCard Suite Eksekutif (Card Mode)</label>
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
        <!-- Showcase 1: Multi-level Approval Audit Trail with Metadata -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Jejak Otorisasi & Riwayat Mutasi (HcisCard Suite):</span>
            <div class="d-flex flex-column gap-3">
                <HcisTimeline
                    card
                    kicker="AUDIT TRAIL OTORISASI"
                    title="Riwayat Mutasi & Kenaikan Golongan Karyawan"
                    sentiment="brand"
                    pattern="grid"
                    variant="glass"
                    :items="mutationTrailDemo"
                    interactive
                    @action-click="handleActionClick"
                />
            </div>
        </div>

        <!-- Showcase 2: Horizontal Milestone Stream -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Pipeline Rekrutmen (Horizontal Stream):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <HcisTimeline
                    direction="horizontal"
                    variant="card"
                    :items="pipelineDemo"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">State Penolakan / Error (Rejected Event Timeline):</span>
            <HcisTimeline
                variant="flat"
                title="Riwayat Pengajuan Klaim Medis Rawat Jalan"
                :items="rejectedClaimDemo"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisTimeline, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioCard = ref(true);
const studioTitle = ref('Audit Trail Pengajuan Fasilitas Kerja');
const studioKicker = ref('LOG AKTIVITAS PERSETUJUAN');
const studioSubtitle = ref('Kronologi persetujuan pengadaan laptop & lisensi perangkat lunak');
const studioSentiment = ref('brand');
const studioPattern = ref('grid');
const studioVariant = ref('card');
const studioDirection = ref('vertical');
const studioActiveStep = ref(2);
const lastEventLog = ref('');
const copied = ref(false);

const auditTrailDemo = ref([
    {
        id: 1,
        title: 'Formulir Pengajuan Fasilitas Disampaikan',
        name: 'Rian Hidayat (NIK: 91024)',
        department: 'Divisi Rekayasa',
        role: 'Staff Pemohon',
        status: 'done',
        time: '12 Agu 2026, 09:14 WIB',
        content: 'Mengajukan workstation MacBook Pro 16" M3 Max untuk kebutuhan development kecerdasan buatan.',
        meta: [
            { label: 'Nominal Anggaran', value: 'Rp 45.000.000' },
            { label: 'Prioritas', value: 'Tinggi (Tier 1)' }
        ]
    },
    {
        id: 2,
        title: 'Persetujuan Section Head & VP IT',
        name: 'Budi Santoso, M.Kom',
        department: 'IT Infrastructure',
        role: 'Engineering Lead',
        status: 'done',
        time: '12 Agu 2026, 14:30 WIB',
        content: 'Disetujui. Perangkat masuk dalam plafon alokasi anggaran belanja modal IT tahun 2026.',
        meta: [
            { label: 'SLA Verifikasi', value: '5 Jam 16 Menit' },
            { label: 'Kode Alokasi CAPEX', value: 'CPX-2026-ENG-08' }
        ]
    },
    {
        id: 3,
        title: 'Verifikasi & Penerbitan PO Procurement',
        name: 'Clarissa Maharani',
        department: 'General Affair & Procurement',
        role: 'Procurement Specialist',
        status: 'active',
        time: 'Sedang Berlangsung',
        content: 'Pemeriksaan stok vendor rekanan dan proses pembuatan Purchase Order resmi.',
        actions: [
            { key: 'view-po', label: 'Tinjau Draft PO', icon: 'file-earmark-text', variant: 'primary' },
            { key: 'contact-vendor', label: 'Hubungi Vendor', icon: 'telephone', variant: 'secondary' }
        ]
    }
]);

const mutationTrailDemo = ref([
    {
        id: 101,
        title: 'Pengangkatan Karyawan Tetap',
        name: 'PT HCIS Solusi Nusantara',
        role: 'Junior Engineer',
        status: 'done',
        time: '01 Jan 2024',
        content: 'Lolos masa percobaan probation 3 bulan dengan predikat memuaskan (Skor 95/100).',
        meta: [{ label: 'Golongan', value: 'III-A' }]
    },
    {
        id: 102,
        title: 'Promosi Tingkat Jabatan & Golongan',
        name: 'Divisi Teknologi',
        role: 'Senior Software Engineer',
        status: 'done',
        time: '01 Jul 2025',
        content: 'Berdasarkan penilaian performa KPI kuartal 4 dengan predikat Istimewa (Top Talent Box 9).',
        meta: [
            { label: 'Golongan Baru', value: 'IV-A' },
            { label: 'Kenaikan Insentif', value: '+25%' }
        ],
        actions: [
            { key: 'download-sk', label: 'Unduh Salinan SK Promosi', icon: 'download', variant: 'secondary' }
        ]
    },
    {
        id: 103,
        title: 'Penugasan Proyek Strategis HCIS Enterprise',
        name: 'Project Management Office',
        role: 'Lead Architect',
        status: 'active',
        time: 'Hari ini, 08:30 WIB',
        content: 'Ditunjuk sebagai penanggung jawab arsitektur desain sistem HCIS Enterprise Suite.'
    }
]);

const pipelineDemo = ref([
    { title: 'Screening CV', status: 'done', time: '10 Agu', content: 'Lolos seleksi berkas' },
    { title: 'Psikotes & Coding', status: 'done', time: '12 Agu', content: 'Skor 92/100' },
    { title: 'User Interview', status: 'active', time: 'Hari ini', content: 'Wawancara Direksi' },
    { title: 'Offering & Kontrak', status: 'pending', time: 'Menunggu', content: 'Penetapan gaji' }
]);

const rejectedClaimDemo = ref([
    {
        id: 201,
        title: 'Klaim Medis Rawat Jalan Diajukan',
        name: 'Dewi Sartika',
        role: 'Staff Pemohon',
        status: 'done',
        time: '10 Agu 2026',
        content: 'Pengajuan kuitansi rawat jalan poli gigi senilai Rp 1.750.000.',
    },
    {
        id: 202,
        title: 'Klaim Ditolak Verifikator Medis',
        name: 'Tim Administrasi Klaim',
        role: 'HC Benefits Specialist',
        status: 'rejected',
        time: '11 Agu 2026',
        content: 'Penolakan: Kuitansi pembayaran belum dilengkapi cap basah klinik dan rincian obat resep.',
    },
]);

function handleItemClick(payload) {
    lastEventLog.value = `Item diklik: ${payload.item.title}`;
}

function handleActionClick(payload) {
    lastEventLog.value = `Aksi tombol diklik: ${payload.action.label} (${payload.item.title})`;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioCard.value) {
        props.push(`card`);
        props.push(`title="${studioTitle.value}"`);
        props.push(`kicker="${studioKicker.value}"`);
        if (studioSentiment.value !== 'brand') props.push(`sentiment="${studioSentiment.value}"`);
        if (studioPattern.value !== 'none') props.push(`pattern="${studioPattern.value}"`);
    } else {
        props.push(`title="${studioTitle.value}"`);
    }
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioDirection.value !== 'vertical') props.push(`direction="${studioDirection.value}"`);
    props.push(`:items="timelineItems"`);

    return `<HcisTimeline\n    ${props.join('\n    ')}\n    @item-click="handleItemClick"\n    @action-click="handleActionClick"\n/>`;
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
