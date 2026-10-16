<template>
    <!-- Section: Anatomy & Interactive Studio -->
    <div v-if="section === 'anatomy'" class="ds-badge-demo">
        <!-- Interactive Live Studio & Code Generator -->
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="sliders" size="sm" class="text-indigo-600 me-2" />
                    Interactive Badge Studio & Code Generator
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span class="ds-meta-tag d-none d-sm-inline">Latar Belakang:</span>
                    <div class="btn-group btn-group-sm">
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="studioStageBg === 'light' ? 'btn-primary' : 'btn-outline-secondary'"
                            @click="studioStageBg = 'light'"
                        >
                            Light
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="studioStageBg === 'dark' ? 'btn-primary' : 'btn-outline-secondary'"
                            @click="studioStageBg = 'dark'"
                        >
                            Dark 🌑
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="studioStageBg === 'mesh' ? 'btn-primary' : 'btn-outline-secondary'"
                            @click="studioStageBg = 'mesh'"
                        >
                            Mesh 🔮
                        </button>
                    </div>
                </div>
            </div>
            <div class="ds-card__body">
                <!-- Live Preview Stage -->
                <div class="ds-studio-stage mb-4" :class="`ds-stage-bg--${studioStageBg}`">
                    <HcisBadge
                        :status="studioMode === 'status' ? studioStatus : ''"
                        :label="studioMode === 'custom' ? studioLabel : ''"
                        :variant="studioMode === 'custom' ? studioVariant : ''"
                        :appearance="studioAppearance"
                        :size="studioSize"
                        :icon="studioMode === 'custom' ? studioIcon : undefined"
                        :icon-animation="studioIconAnimation"
                        :dot="studioDot"
                        :pulse="studioPulse"
                        :removable="studioRemovable"
                        @remove="handleTagRemove"
                    />
                </div>

                <!-- Controls Grid -->
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Mode Penggunaan</label>
                        <select v-model="studioMode" class="form-select form-select-sm">
                            <option value="status">Smart Status (Auto-Mapped)</option>
                            <option value="custom">Custom Configurator</option>
                        </select>
                    </div>

                    <!-- Mode Status -->
                    <div v-if="studioMode === 'status'" class="col-12 col-md-3">
                        <label class="ds-control-label">Status Bisnis HCIS</label>
                        <select v-model="studioStatus" class="form-select form-select-sm">
                            <optgroup label="Disetujui & Sukses">
                                <option value="APPROVED">APPROVED (Disetujui)</option>
                                <option value="COMPLETED">COMPLETED (Selesai)</option>
                                <option value="ACTIVE">ACTIVE (Aktif)</option>
                                <option value="PASSED">PASSED (Lolos)</option>
                                <option value="VERIFIED">VERIFIED (Terverifikasi)</option>
                                <option value="SYNCED">SYNCED (Tersinkron)</option>
                            </optgroup>
                            <optgroup label="Menunggu & Pemrosesan">
                                <option value="PENDING">PENDING (Menunggu Persetujuan)</option>
                                <option value="PENDING_MANAGER">PENDING_MANAGER (Menunggu Manajer)</option>
                                <option value="PENDING_HR">PENDING_HR (Menunggu HR)</option>
                                <option value="IN_REVIEW">IN_REVIEW (Dalam Peninjauan)</option>
                                <option value="WAITING">WAITING (Menunggu Antrean)</option>
                                <option value="IN_PROGRESS">IN_PROGRESS (Sedang Diproses)</option>
                                <option value="PROCESSING">PROCESSING (Memproses Data)</option>
                                <option value="SYNCING">SYNCING (Menyinkronkan)</option>
                            </optgroup>
                            <optgroup label="Ditolak & Gagal">
                                <option value="REJECTED">REJECTED (Ditolak)</option>
                                <option value="CANCELLED">CANCELLED (Dibatalkan)</option>
                                <option value="EXPIRED">EXPIRED (Kadaluarsa)</option>
                                <option value="FAILED">FAILED (Gagal)</option>
                                <option value="SUSPENDED">SUSPENDED (Ditangguhkan)</option>
                            </optgroup>
                            <optgroup label="Draf & Netral">
                                <option value="DRAFT">DRAFT (Draf)</option>
                                <option value="INACTIVE">INACTIVE (Nonaktif)</option>
                                <option value="ARCHIVED">ARCHIVED (Diarsipkan)</option>
                                <option value="CLOSED">CLOSED (Ditutup)</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Mode Custom -->
                    <template v-else>
                        <div class="col-12 col-md-3">
                            <label class="ds-control-label">Custom Label</label>
                            <input v-model="studioLabel" type="text" class="form-control form-control-sm" placeholder="Teks Lencana..." />
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="ds-control-label">Variant Warna</label>
                            <select v-model="studioVariant" class="form-select form-select-sm">
                                <option value="primary">primary (Sapphire)</option>
                                <option value="purple">purple (Violet)</option>
                                <option value="teal">teal (Toska)</option>
                                <option value="success">success (Zamrud)</option>
                                <option value="warning">warning (Amber)</option>
                                <option value="danger">danger (Crimson)</option>
                                <option value="info">info (Sky)</option>
                                <option value="obsidian">obsidian (Dark)</option>
                                <option value="neutral">neutral (Slate)</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="ds-control-label">Ikon (Opsional)</label>
                            <input v-model="studioIcon" type="text" class="form-control form-control-sm" placeholder="e.g. stars, gem, check-lg" />
                        </div>
                    </template>

                    <!-- Appearance & Size -->
                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Tampilan (Appearance)</label>
                        <select v-model="studioAppearance" class="form-select form-select-sm">
                            <option value="subtle">subtle (Crystal Glass Tint - Default)</option>
                            <option value="solid">solid (Gemstone Solid)</option>
                            <option value="glass">glass (Frosted Glass Blur)</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Ukuran (Size Scale)</label>
                        <select v-model="studioSize" class="form-select form-select-sm">
                            <option value="xs">xs (Micro Tag 18px)</option>
                            <option value="sm">sm (Dense Table 22px)</option>
                            <option value="md">md (Standard 26px)</option>
                            <option value="lg">lg (Hero Badge 30px)</option>
                        </select>
                    </div>

                    <!-- Toggles -->
                    <div class="col-12 d-flex flex-wrap align-items-center gap-3 mt-3 pt-2 border-top">
                        <label class="ds-toggle-inline">
                            <input v-model="studioDot" type="checkbox" />
                            <span>Dot Indicator (`dot`)</span>
                        </label>
                        <label class="ds-toggle-inline text-warning">
                            <input v-model="studioPulse" type="checkbox" />
                            <span>Radar Glowing Pulse (`pulse`)</span>
                        </label>
                        <label class="ds-toggle-inline text-primary">
                            <input v-model="studioRemovable" type="checkbox" />
                            <span>Removable Tag Chip (`removable`)</span>
                        </label>
                    </div>
                </div>

                <!-- Reactive Code Generator Output -->
                <div class="ds-code-generator mt-4">
                    <div class="ds-code-generator__header">
                        <span>
                            <HcisIcon name="code-slash" size="sm" class="me-1 text-indigo-400" />
                            Vue Template Code Snippet (Live Reactive Export)
                        </span>
                        <button
                            type="button"
                            class="btn btn-sm ds-copy-btn"
                            :class="copiedCode ? 'btn-success' : 'btn-dark'"
                            @click="copyGeneratedCode"
                        >
                            <HcisIcon :name="copiedCode ? 'check-lg' : 'clipboard'" size="xs" class="me-1" />
                            {{ copiedCode ? 'Tersalin ke Clipboard!' : 'Salin Kode' }}
                        </button>
                    </div>
                    <pre class="ds-code-pre"><code>{{ generatedCode }}</code></pre>
                </div>
            </div>
        </div>

        <!-- Anatomy Architecture Card -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="layers" size="sm" class="text-indigo-600 me-2" />
                    Anatomi & Fitur Cerdas Lencana HCIS
                </span>
                <span class="ds-card__tag">Signature Anatomy</span>
            </div>
            <div class="ds-card__body">
                <ul class="ds-anatomy-list">
                    <li>
                        <strong>1. Auto-Mapped Smart Status:</strong> Cukup berikan `:status="item.status"` (misal `APPROVED`, `PENDING_MANAGER`, `REJECTED`), lencana akan otomatis memetakan teks terjemahan bahasa Indonesia yang baku, warna palet sentiment, ikon yang sesuai, dan status radar pulse.
                    </li>
                    <li>
                        <strong>2. Crystal Glassmorphism Capsule:</strong> Kapsul translusen bergradasi halus 135° dengan border kristal 1px, pembiasan `backdrop-filter: blur(12px)`, dan kilau atas (*inset crystal highlight* `inset 0 1px 0 rgba(255,255,255,0.85)`).
                    </li>
                    <li>
                        <strong>3. Radar Glowing Pulse:</strong> Animasi lingkaran radar kosentris (`@keyframes hcis-badge-pulse`) yang aktif otomatis pada status peninjauan dan antrean aktif.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Section: Variants & Appearances -->
    <div v-else-if="section === 'variants'" class="ds-badge-demo d-flex flex-column gap-4">
        <!-- 3 Appearance Tiers Matrix -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="gem" size="sm" class="text-indigo-600 me-2" />
                    Matriks Gaya Tampilan (Subtle · Solid · Glass)
                </span>
                <span class="ds-card__tag">3 Appearance Tiers</span>
            </div>
            <div class="ds-card__body d-flex flex-column gap-4">
                <div>
                    <span class="ds-demo-label">1. Subtle Crystal Glass Tint (Default):</span>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <HcisBadge label="Disetujui" variant="success" icon="check-circle-fill" />
                        <HcisBadge label="Menunggu" variant="warning" icon="hourglass-split" pulse />
                        <HcisBadge label="Diproses" variant="brand" icon="gear-fill" />
                        <HcisBadge label="Ditolak" variant="danger" icon="x-circle-fill" />
                        <HcisBadge label="Informasi" variant="info" icon="info-circle-fill" />
                        <HcisBadge label="Obsidian" variant="obsidian" icon="moon-stars-fill" />
                        <HcisBadge label="Draf" variant="neutral" icon="file-earmark-text" />
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">2. Gemstone Solid (`appearance="solid"`):</span>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <HcisBadge label="Disetujui" appearance="solid" variant="success" icon="check-circle-fill" />
                        <HcisBadge label="Menunggu" appearance="solid" variant="warning" icon="hourglass-split" />
                        <HcisBadge label="Diproses" appearance="solid" variant="primary" icon="gear-fill" />
                        <HcisBadge label="Ditolak" appearance="solid" variant="danger" icon="x-circle-fill" />
                        <HcisBadge label="Draf Arsip" appearance="solid" variant="neutral" icon="archive" />
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">3. Frosted Crystal Glass (`appearance="glass"`):</span>
                    <div class="d-flex flex-wrap gap-2 mt-2 p-3 rounded" style="background: radial-gradient(circle, #312e81 0%, #0f172a 100%)">
                        <HcisBadge label="Disetujui" appearance="glass" variant="success" icon="check-circle-fill" />
                        <HcisBadge label="Menunggu" appearance="glass" variant="warning" icon="hourglass-split" pulse />
                        <HcisBadge label="Diproses" appearance="glass" variant="brand" icon="gear-fill" />
                        <HcisBadge label="Frosted Tag" appearance="glass" variant="primary" icon="gem" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Size Matrix Scale -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="aspect-ratio" size="sm" class="text-indigo-600 me-2" />
                    Matriks Skala Ukuran (4 Size Scales)
                </span>
                <span class="ds-card__tag">xs · sm · md · lg</span>
            </div>
            <div class="ds-card__body">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="xs" (18px - Micro)</span>
                        <HcisBadge label="Micro Tag" size="xs" variant="success" icon="check-lg" />
                    </div>
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="sm" (22px - Dense Table)</span>
                        <HcisBadge label="Dense Table" size="sm" variant="success" icon="check-circle-fill" />
                    </div>
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="md" (26px - Standard)</span>
                        <HcisBadge label="Standard Badge" size="md" variant="success" icon="check-circle-fill" />
                    </div>
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="lg" (30px - Hero)</span>
                        <HcisBadge label="Hero Status" size="lg" variant="success" icon="check-circle-fill" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-world Transaction Table Integration -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="table" size="sm" class="text-indigo-600 me-2" />
                    Integrasi Tabel Transaksi (Real-World Usage)
                </span>
                <span class="ds-card__tag">Smart Auto-Resolution</span>
            </div>
            <div class="ds-card__body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.8125rem">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 py-2">No. Transaksi</th>
                                <th class="py-2">Jenis Pengajuan</th>
                                <th class="py-2">Nama Pegawai</th>
                                <th class="py-2 text-end pe-3">Status Otorisasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-primary">CT-2026-08-0041</td>
                                <td>Cuti Tahunan (3 Hari)</td>
                                <td>Budi Santoso (NIP: 10482)</td>
                                <td class="text-end pe-3"><HcisBadge status="PENDING_MANAGER" size="sm" /></td>
                            </tr>
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-primary">CT-2026-08-0038</td>
                                <td>Cuti Melahirkan</td>
                                <td>Siti Rahmawati (NIP: 10921)</td>
                                <td class="text-end pe-3"><HcisBadge status="APPROVED" size="sm" /></td>
                            </tr>
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-primary">IZ-2026-08-0107</td>
                                <td>Izin Dispensasi Dinas Luar</td>
                                <td>Ahmad Hidayat (NIP: 10543)</td>
                                <td class="text-end pe-3"><HcisBadge status="IN_PROGRESS" size="sm" /></td>
                            </tr>
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-primary">LK-2026-08-0012</td>
                                <td>Lembur Khusus Akhir Bulan</td>
                                <td>Dewi Sartika (NIP: 10672)</td>
                                <td class="text-end pe-3"><HcisBadge status="REJECTED" size="sm" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: States & Removable Chips -->
    <div v-else-if="section === 'states'" class="ds-badge-demo d-flex flex-column gap-4">
        <!-- Live Concentric Dual-Wave Radar Pulse & Active Statuses -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="broadcast" size="sm" class="text-indigo-600 me-2" />
                    Radar Glowing Pulse Presisi (100% Dead-Center Dual-Wave Concentric)
                </span>
                <span class="ds-card__tag">Dual-Wave Pulse</span>
            </div>
            <div class="ds-card__body d-flex flex-column gap-3">
                <div>
                    <span class="ds-demo-label">1. Radar Pulse pada Varian Subtle (Kaca Kristal):</span>
                    <div class="d-flex flex-wrap gap-3 mt-2">
                        <HcisBadge label="Menunggu Konfirmasi Manajer" variant="warning" pulse />
                        <HcisBadge label="Sinkronisasi Data Real-Time" variant="brand" pulse />
                        <HcisBadge label="Server Cluster Online" variant="success" pulse />
                        <HcisBadge label="Antrean Tugas Darurat" variant="danger" pulse />
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">2. Radar Pulse pada Varian Gemstone Solid (`appearance="solid"`):</span>
                    <div class="d-flex flex-wrap gap-3 mt-2">
                        <HcisBadge label="Antrean Approval" appearance="solid" variant="warning" pulse />
                        <HcisBadge label="Worker Aktif" appearance="solid" variant="primary" pulse />
                        <HcisBadge label="Live Pipeline" appearance="solid" variant="success" pulse />
                        <HcisBadge label="Critical Alert" appearance="solid" variant="danger" pulse />
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">3. Gemstone Bead Dot Statis (Tanpa Pulse):</span>
                    <div class="d-flex flex-wrap gap-3 mt-2">
                        <HcisBadge label="Basis Data Terkoneksi" variant="success" dot />
                        <HcisBadge label="Mode Siaga Malam" variant="obsidian" dot />
                        <HcisBadge label="Koneksi VPN Aktif" variant="info" dot />
                        <HcisBadge label="Cadangan Draf Tersimpan" variant="neutral" dot />
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactive Removable Filter Chips Cloud -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="tags" size="sm" class="text-indigo-600 me-2" />
                    Tag Chip Filter Interaktif (Ultra-Crisp Removable Micro-Button)
                </span>
                <span class="ds-card__tag">Klik 'x' Berotasi 90°</span>
            </div>
            <div class="ds-card__body">
                <p class="text-muted small mb-3">
                    Gunakan prop <code>removable</code> dan tangkap event <code>@remove</code> untuk membuat tag filter yang dapat dihapus secara dinamis oleh pengguna dengan tombol mikro kristal berotasi pegas 90°:
                </p>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <HcisBadge
                        v-for="chip in filterChips"
                        :key="chip.id"
                        :label="chip.name"
                        :variant="chip.variant"
                        removable
                        @remove="removeFilterChip(chip.id)"
                    />
                    <button
                        v-if="filterChips.length === 0"
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        @click="resetFilterChips"
                    >
                        <HcisIcon name="arrow-counterclockwise" size="xs" class="me-1" />
                        Reset Tag Filter
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn btn-link btn-sm text-decoration-none text-muted ps-2"
                        @click="resetFilterChips"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisBadge, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio Configurator State
const studioMode = ref('status'); // status | custom
const studioStatus = ref('PENDING_MANAGER');
const studioLabel = ref('Disetujui Direksi');
const studioVariant = ref('success');
const studioAppearance = ref('subtle');
const studioSize = ref('md');
const studioIcon = ref('stars');
const studioIconAnimation = ref('');
const studioDot = ref(false);
const studioPulse = ref(false);
const studioRemovable = ref(false);
const studioStageBg = ref('light');
const copiedCode = ref(false);

function handleTagRemove() {
    alert('Event @remove terpicu!');
}

// Reactive Code Generator Output
const generatedCode = computed(() => {
    const lines = ['<HcisBadge'];
    if (studioMode.value === 'status') {
        lines.push(`    status="${studioStatus.value}"`);
    } else {
        if (studioLabel.value) lines.push(`    label="${studioLabel.value}"`);
        if (studioVariant.value !== 'neutral') lines.push(`    variant="${studioVariant.value}"`);
        if (studioIcon.value) lines.push(`    icon="${studioIcon.value}"`);
    }
    if (studioAppearance.value !== 'subtle') {
        lines.push(`    appearance="${studioAppearance.value}"`);
    }
    if (studioSize.value !== 'md') {
        lines.push(`    size="${studioSize.value}"`);
    }
    if (studioDot.value) {
        lines.push('    dot');
    }
    if (studioPulse.value) {
        lines.push('    pulse');
    }
    if (studioRemovable.value) {
        lines.push('    removable');
        lines.push('    @remove="handleRemove"');
    }
    lines.push('/>');
    return lines.join('\n');
});

function copyGeneratedCode() {
    navigator.clipboard.writeText(generatedCode.value);
    copiedCode.value = true;
    setTimeout(() => {
        copiedCode.value = false;
    }, 2200);
}

// Removable Tag Chips Cloud State
const initialChips = [
    { id: 1, name: 'Divisi IT & Digital', variant: 'brand' },
    { id: 2, name: 'Kantor Pusat Jakarta', variant: 'purple' },
    { id: 3, name: 'Status: Aktif Bekerja', variant: 'success' },
    { id: 4, name: 'Periode 2026', variant: 'info' },
    { id: 5, name: 'Tingkat Eksekutif', variant: 'obsidian' },
];

const filterChips = ref([...initialChips]);

function removeFilterChip(id) {
    filterChips.value = filterChips.value.filter((c) => c.id !== id);
}

function resetFilterChips() {
    filterChips.value = [...initialChips];
}
</script>

<style scoped>
.ds-badge-demo {
    width: 100%;
}

.ds-card {
    border-radius: var(--hcis-radius-xl, 12px);
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    box-shadow: var(--hcis-shadow-sm);
    overflow: hidden;
}

.ds-card__header {
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--hcis-border-subtle);
    background: var(--hcis-surface-soft);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ds-card__title {
    font-size: 0.9rem;
    font-weight: 750;
    color: var(--hcis-text-strong);
    display: flex;
    align-items: center;
}

.ds-card__tag {
    font-size: 0.65rem;
    font-family: ui-monospace, SFMono-Regular, monospace;
    padding: 0.15rem 0.45rem;
    border-radius: var(--hcis-radius-xs);
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    color: var(--hcis-text-muted);
}

.ds-card__body {
    padding: 1.25rem;
}

/* Stage Canvas Backgrounds */
.ds-studio-stage {
    min-height: 90px;
    padding: 1.5rem;
    border-radius: var(--hcis-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: 250ms;
    transition-timing-function: var(--hcis-ease);
    border: 1px dashed var(--hcis-border-medium);
}

.ds-stage-bg--light {
    background: radial-gradient(circle at 50% 50%, rgb(var(--hcis-brand-500-rgb) / 0.06) 0%, transparent 80%),
                var(--hcis-surface-soft);
}

.ds-stage-bg--dark {
    background: radial-gradient(circle at 50% 50%, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%);
    border-color: var(--hcis-slate-700);
}

.ds-stage-bg--mesh {
    background: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%),
                var(--hcis-slate-900);
    border-color: var(--hcis-indigo-700);
}

.ds-control-label {
    font-size: 0.725rem;
    font-weight: 700;
    color: var(--hcis-text-muted);
    margin-bottom: 0.25rem;
    display: block;
}

.ds-toggle-inline {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-strong);
    cursor: pointer;
}

.ds-demo-label {
    font-size: 0.75rem;
    font-weight: 750;
    color: var(--hcis-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: block;
    margin-bottom: 0.35rem;
}

.ds-meta-tag {
    font-size: 0.65rem;
    font-family: ui-monospace, SFMono-Regular, monospace;
    color: var(--hcis-text-muted);
}

/* Code Generator Box */
.ds-code-generator {
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-slate-900);
    border: 1px solid var(--hcis-slate-800);
    overflow: hidden;
}

.ds-code-generator__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0.85rem;
    background: var(--hcis-slate-800);
    color: var(--hcis-slate-400);
    font-size: 0.725rem;
    font-family: ui-monospace, SFMono-Regular, monospace;
    border-bottom: 1px solid var(--hcis-slate-700);
}

.ds-copy-btn {
    font-size: 0.6875rem;
    padding: 0.15rem 0.6rem;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-weight: 600;
}

.ds-code-pre {
    margin: 0;
    padding: 0.85rem 1rem;
    color: var(--hcis-sky-400);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.775rem;
    line-height: 1.5;
    overflow-x: auto;
}

.ds-anatomy-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.ds-anatomy-list li {
    font-size: 0.8125rem;
    color: var(--hcis-slate-600);
    line-height: 1.45;
}

.ds-anatomy-list strong {
    color: var(--hcis-slate-900);
    font-weight: 750;
}

@media (max-width: 640px) {
    .ds-card__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
    }

    .ds-card__body {
        padding: 0.85rem 0.65rem;
    }
}
</style>
