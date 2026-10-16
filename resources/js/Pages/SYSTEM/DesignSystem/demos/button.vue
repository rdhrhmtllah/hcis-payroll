<template>
    <!-- Section: Anatomy & Interactive Studio -->
    <div v-if="section === 'anatomy'" class="ds-button-demo">
        <!-- Interactive Live Studio -->
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="sliders" size="sm" class="text-indigo-600 me-2" />
                    Interactive Button Studio & Code Generator
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span class="ds-meta-tag d-none d-sm-inline">Latar Belakang Stage:</span>
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
                <!-- Live Preview Stage with Dynamic Canvas -->
                <div class="ds-studio-stage mb-4" :class="`ds-stage-bg--${studioStageBg}`">
                    <HcisButton
                        :label="studioLabel"
                        :variant="studioVariant"
                        :size="studioSize"
                        :pill="studioPill"
                        :block="studioBlock"
                        :icon-only="studioIconOnly"
                        :leading-icon="studioLeadingIcon"
                        :leading-animation="studioLeadingAnimation"
                        :trailing-icon="studioTrailingIcon"
                        :trailing-animation="studioTrailingAnimation"
                        :loading="studioLoading"
                        :loading-icon="studioLoadingIcon"
                        :loading-animation="studioLoadingAnimation"
                        :disabled="studioDisabled"
                        :aria-label="studioIconOnly ? (studioLabel || 'Tombol Studio') : undefined"
                        @click="handleStudioClick"
                    />
                </div>

                <!-- Live Control Form Grid -->
                <div class="row g-3">
                    <!-- Row 1: Label, Variant, Size -->
                    <div class="col-12 col-md-4">
                        <label class="ds-control-label">Label Tombol</label>
                        <input v-model="studioLabel" type="text" class="form-control form-control-sm" placeholder="Teks Tombol..." />
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="ds-control-label">Variant Palet (9 Palettes)</label>
                        <select v-model="studioVariant" class="form-select form-select-sm">
                            <option value="primary">primary (Royal Sapphire)</option>
                            <option value="obsidian">obsidian (Dark Obsidian Glass)</option>
                            <option value="secondary">secondary (Slate Crystal)</option>
                            <option value="glass">glass (Frosted Crystal)</option>
                            <option value="ghost">ghost (Borderless Quiet)</option>
                            <option value="success">success (Zamrud Emerald)</option>
                            <option value="warning">warning (Amber Gold)</option>
                            <option value="danger">danger (Coral Crimson)</option>
                            <option value="danger-quiet">danger-quiet (Soft Red)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="ds-control-label">Ukuran (Size Scale)</label>
                        <select v-model="studioSize" class="form-select form-select-sm">
                            <option value="xs">xs (28px - Micro Compact)</option>
                            <option value="sm">sm (32px - Dense Tables)</option>
                            <option value="md">md (38px - Standard Enterprise)</option>
                            <option value="lg">lg (44px - Touch Action CTA)</option>
                        </select>
                    </div>

                    <!-- Row 2: Leading Icon & Motion -->
                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Leading Icon</label>
                        <input v-model="studioLeadingIcon" type="text" class="form-control form-control-sm" placeholder="e.g. stars, gear-fill, plus-lg" />
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Leading Animation</label>
                        <select v-model="studioLeadingAnimation" class="form-select form-select-sm">
                            <option value="">(None / Smart Hover)</option>
                            <option value="spin">spin (Laser Spinner 360°)</option>
                            <option value="gear">gear (Putaran Roda Gigi)</option>
                            <option value="flip">flip (Jam Pasir 180°)</option>
                            <option value="ring">ring (Dering Lonceng)</option>
                            <option value="sparkle">sparkle (Kilau Bintang)</option>
                            <option value="pulse">pulse (Radar Pulse)</option>
                            <option value="bounce">bounce (Micro Bounce)</option>
                            <option value="float">float (Anti-Gravitasi)</option>
                        </select>
                    </div>

                    <!-- Row 3: Trailing Icon & Motion -->
                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Trailing Icon</label>
                        <input v-model="studioTrailingIcon" type="text" class="form-control form-control-sm" placeholder="e.g. arrow-right, download" />
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Trailing Animation</label>
                        <select v-model="studioTrailingAnimation" class="form-select form-select-sm">
                            <option value="">(None / Smart Glide)</option>
                            <option value="stream">stream (Aliran Unduh/Unggah)</option>
                            <option value="pulse">pulse (Denyut Radar)</option>
                            <option value="sparkle">sparkle (Kilau Bintang)</option>
                        </select>
                    </div>

                    <!-- Row 4: Custom Loading Spinner Icon & Animation -->
                    <div class="col-6 col-md-6" :class="{ 'opacity-50': !studioLoading }">
                        <label class="ds-control-label">Custom Loading Icon (Opsional saat loading aktif)</label>
                        <select v-model="studioLoadingIcon" class="form-select form-select-sm" :disabled="!studioLoading">
                            <option value="">(Default Laser Spinner)</option>
                            <option value="hourglass-split">hourglass-split (Jam Pasir Realistis)</option>
                            <option value="gear-fill">gear-fill (Roda Gigi Mesin)</option>
                            <option value="cloud-arrow-up-fill">cloud-arrow-up-fill (Aliran Cloud)</option>
                            <option value="bell-fill">bell-fill (Dering Lonceng)</option>
                            <option value="arrow-repeat">arrow-repeat (Arrow Repeat)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-6" :class="{ 'opacity-50': !studioLoading }">
                        <label class="ds-control-label">Custom Loading Animation Type</label>
                        <select v-model="studioLoadingAnimation" class="form-select form-select-sm" :disabled="!studioLoading">
                            <option value="">(Default / Smart Context)</option>
                            <option value="spin">spin (Laser 360°)</option>
                            <option value="flip">flip (Hourglass 180° Flip & Jeda)</option>
                            <option value="gear">gear (Roda Gigi Kontinu)</option>
                            <option value="stream">stream (Aliran Vertikal)</option>
                            <option value="ring">ring (Goncangan Lonceng)</option>
                        </select>
                    </div>

                    <!-- Toggles -->
                    <div class="col-12 d-flex flex-wrap align-items-center gap-3 mt-3 pt-2 border-top">
                        <label class="ds-toggle-inline">
                            <input v-model="studioPill" type="checkbox" />
                            <span>Pill Capsule (`pill`)</span>
                        </label>
                        <label class="ds-toggle-inline">
                            <input v-model="studioIconOnly" type="checkbox" />
                            <span>Icon Only (`icon-only`)</span>
                        </label>
                        <label class="ds-toggle-inline">
                            <input v-model="studioBlock" type="checkbox" />
                            <span>Full Width (`block`)</span>
                        </label>
                        <label class="ds-toggle-inline text-primary">
                            <input v-model="studioLoading" type="checkbox" />
                            <span>Loading State (`loading`)</span>
                        </label>
                        <label class="ds-toggle-inline text-danger">
                            <input v-model="studioDisabled" type="checkbox" />
                            <span>Disabled State (`disabled`)</span>
                        </label>

                        <div class="ms-auto d-flex align-items-center gap-2">
                            <span v-if="clickCount > 0" class="badge bg-success-subtle text-success border border-success-subtle">
                                ✓ {{ clickCount }} Clicks
                            </span>
                            <button
                                v-if="clickCount > 0"
                                type="button"
                                class="btn btn-link btn-sm p-0 text-muted"
                                style="font-size: 0.725rem"
                                @click="clickCount = 0"
                            >
                                Reset Clicks
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reactive Code Generator Box -->
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
                    Anatomi Tombol & Lapisan Visual
                </span>
                <span class="ds-card__tag">Signature Anatomy</span>
            </div>
            <div class="ds-card__body">
                <div class="row g-4 align-items-center">
                    <div class="col-12 col-md-5 text-center">
                        <div class="ds-anatomy-preview">
                            <HcisButton
                                label="Simpan Perubahan"
                                variant="primary"
                                size="lg"
                                leading-icon="check2-circle"
                                trailing-icon="arrow-right"
                            />
                        </div>
                    </div>
                    <div class="col-12 col-md-7">
                        <ul class="ds-anatomy-list">
                            <li>
                                <strong>1. Micro-Lift Surface:</strong> Gradasi diagonal halus 135° dengan inset highlight kristal pada bagian atas (`inset 0 1px 0 rgba(255,255,255,0.28)`).
                            </li>
                            <li>
                                <strong>2. Leading Icon:</strong> Ikon awalan untuk konteks visual tindakan (`check2-circle`, `plus-lg`, `download`).
                            </li>
                            <li>
                                <strong>3. Typography:</strong> Huruf Obsidian berbobot `font-weight: 700` dengan keterbacaan tinggi.
                            </li>
                            <li>
                                <strong>4. Trailing Action Icon:</strong> Ikon akhiran yang otomatis meluncur halus (+4px) saat kursor diarahkan (*hover*).
                            </li>
                            <li>
                                <strong>5. Multi-Tier Shadow Bloom:</strong> Bayangan elevasi multi-lapis yang mekar lembut saat berinteraksi.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Variants & Sizes -->
    <div v-else-if="section === 'variants'" class="ds-button-demo d-flex flex-column gap-4">
        <!-- Palette Variants Card -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="palette" size="sm" class="text-indigo-600 me-2" />
                    Enterprise Variant Suite
                </span>
                <span class="ds-card__tag">9 Palette Options</span>
            </div>
            <div class="ds-card__body">
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <HcisButton label="Primary (Brand Sapphire)" variant="primary" leading-icon="stars" />
                    <HcisButton label="Obsidian Glass (Dark Crystal)" variant="obsidian" leading-icon="moon-stars-fill" />
                    <HcisButton label="Secondary (Slate Crystal)" variant="secondary" leading-icon="card-text" />
                    <HcisButton label="Glass (Frosted Crystal)" variant="glass" leading-icon="gem" />
                    <HcisButton label="Ghost (Borderless Quiet)" variant="ghost" leading-icon="arrow-right" />
                    <HcisButton label="Success (Zamrud)" variant="success" leading-icon="check-circle-fill" />
                    <HcisButton label="Warning (Amber Gold)" variant="warning" leading-icon="exclamation-triangle-fill" />
                    <HcisButton label="Danger (Coral Crimson)" variant="danger" leading-icon="slash-circle-fill" />
                    <HcisButton label="Danger Quiet (Soft)" variant="danger-quiet" leading-icon="trash3" />
                </div>
            </div>
        </div>

        <!-- Sizes Matrix Card -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="aspect-ratio" size="sm" class="text-indigo-600 me-2" />
                    Matriks Ukuran Tombol (Size Scale Matrix)
                </span>
                <span class="ds-card__tag">xs · sm · md · lg</span>
            </div>
            <div class="ds-card__body">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="xs" (28px)</span>
                        <HcisButton label="Micro Extra Small" size="xs" variant="primary" leading-icon="lightning-charge-fill" />
                    </div>
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="sm" (32px)</span>
                        <HcisButton label="Small Density" size="sm" variant="primary" leading-icon="lightning-charge-fill" />
                    </div>
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="md" (38px - Default)</span>
                        <HcisButton label="Medium Standard" size="md" variant="primary" leading-icon="lightning-charge-fill" />
                    </div>
                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ds-meta-tag">size="lg" (44px - Touch)</span>
                        <HcisButton label="Large CTA Action" size="lg" variant="primary" leading-icon="lightning-charge-fill" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Pill Capsule & Icon-Only Suite -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="capsule" size="sm" class="text-indigo-600 me-2" />
                    Pill Shapes & Icon-Only Matrix
                </span>
                <span class="ds-card__tag">pill · icon-only</span>
            </div>
            <div class="ds-card__body d-flex flex-column gap-3">
                <div>
                    <span class="ds-demo-label">Kapsul Bulat Penuh (`pill`):</span>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <HcisButton label="Pill Primary" variant="primary" pill leading-icon="plus-lg" />
                        <HcisButton label="Pill Secondary" variant="secondary" pill leading-icon="download" />
                        <HcisButton label="Pill Glass" variant="glass" pill leading-icon="gem" />
                        <HcisButton label="Pill Success" variant="success" pill leading-icon="check-lg" />
                        <HcisButton label="Pill Danger" variant="danger" pill leading-icon="x-lg" />
                    </div>
                </div>

                <div class="pt-2 border-top">
                    <span class="ds-demo-label">Tombol Ikon Murni (`icon-only`) 4 Skala Ukuran:</span>
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">xs:</span>
                            <HcisButton icon-only size="xs" variant="primary" leading-icon="plus-lg" aria-label="Tambah" />
                            <HcisButton icon-only size="xs" variant="secondary" leading-icon="pencil" aria-label="Ubah" />
                            <HcisButton icon-only size="xs" variant="danger-quiet" leading-icon="trash3" aria-label="Hapus" />
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">sm:</span>
                            <HcisButton icon-only size="sm" variant="primary" leading-icon="plus-lg" aria-label="Tambah" />
                            <HcisButton icon-only size="sm" variant="secondary" leading-icon="pencil" aria-label="Ubah" />
                            <HcisButton icon-only size="sm" variant="danger-quiet" leading-icon="trash3" aria-label="Hapus" />
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">md:</span>
                            <HcisButton icon-only size="md" variant="primary" leading-icon="plus-lg" aria-label="Tambah" />
                            <HcisButton icon-only size="md" variant="secondary" leading-icon="pencil" aria-label="Ubah" />
                            <HcisButton icon-only size="md" variant="glass" pill leading-icon="share" aria-label="Bagikan" />
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">lg:</span>
                            <HcisButton icon-only size="lg" variant="primary" pill leading-icon="arrow-right" aria-label="Lanjut" />
                            <HcisButton icon-only size="lg" variant="secondary" pill leading-icon="gear" aria-label="Pengaturan" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: States & Micro-Interactions -->
    <div v-else-if="section === 'states'" class="ds-button-demo d-flex flex-column gap-4">
        <!-- Interactive Async Simulation Card -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="lightning-charge" size="sm" class="text-indigo-600 me-2" />
                    Simulasi Aksi Asinkron (Async API Call Simulation)
                </span>
                <span class="ds-card__tag">Interactive Feedback</span>
            </div>
            <div class="ds-card__body">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <HcisButton
                        :label="asyncStatus === 'idle' ? 'Simpan Data Kepegawaian' : asyncStatus === 'loading' ? 'Menyimpan...' : 'Tersimpan Sukses!'"
                        :variant="asyncStatus === 'success' ? 'success' : 'primary'"
                        :loading="asyncStatus === 'loading'"
                        :leading-icon="asyncStatus === 'success' ? 'check-lg' : 'cloud-arrow-up-fill'"
                        :disabled="asyncStatus === 'loading'"
                        @click="triggerAsyncAction"
                    />

                    <span v-if="asyncStatus === 'success'" class="text-success small fw-bold">
                        ✓ Data berhasil disinkronisasi ke server!
                    </span>
                    <span v-else class="text-muted small">
                        Klik tombol di atas untuk melihat transisi Laser Spinner & resolusi sukses otomatis.
                    </span>
                </div>
            </div>
        </div>

        <!-- Static States & Realistic Loading Animations -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="hourglass-split" size="sm" class="text-indigo-600 me-2" />
                    Pilihan Loading & Spinner Realistis (Realistic Loading Animations)
                </span>
                <span class="ds-card__tag">Laser · Hourglass · Gear · Stream · Bell</span>
            </div>
            <div class="ds-card__body">
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <span class="ds-demo-label">1. Laser Micro-Spinner (Default):</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <HcisButton label="Menyimpan..." variant="primary" loading />
                            <HcisButton label="Memuat Data..." variant="secondary" loading />
                            <HcisButton icon-only variant="primary" size="sm" loading aria-label="Loading" />
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="ds-demo-label">2. Jam Pasir Realistis (`loading-icon="hourglass-split"`):</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <HcisButton label="Memproses Faktur..." variant="warning" loading loading-icon="hourglass-split" />
                            <HcisButton icon-only variant="secondary" loading loading-icon="hourglass-split" aria-label="Hourglass" />
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="ds-demo-label">3. Roda Gigi Mesin (`loading-icon="gear-fill"`):</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <HcisButton label="Kalkulasi Payroll..." variant="obsidian" loading loading-icon="gear-fill" loading-animation="gear" />
                            <HcisButton icon-only variant="secondary" loading loading-icon="gear-fill" loading-animation="gear" aria-label="Gear" />
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="ds-demo-label">4. Aliran Unggah Cloud (`loading-icon="cloud-arrow-up-fill"`):</span>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <HcisButton label="Mengunggah Berkas..." variant="primary" loading loading-icon="cloud-arrow-up-fill" loading-animation="stream" />
                            <HcisButton label="Dering Antrean..." variant="danger" loading loading-icon="bell-fill" loading-animation="ring" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contextual Hover Micro-Motions Showcase -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="stars" size="sm" class="text-indigo-600 me-2" />
                    Gerak Interaktif Saat Kursor Diarahkan (Contextual Hover Micro-Motions)
                </span>
                <span class="ds-card__tag">Arahkan Kursor (Hover)</span>
            </div>
            <div class="ds-card__body">
                <div class="d-flex flex-wrap gap-3">
                    <HcisButton label="Pengaturan Sistem" variant="secondary" leading-icon="gear-fill" />
                    <HcisButton label="Riwayat Tunggu" variant="secondary" leading-icon="hourglass-split" />
                    <HcisButton label="Pusat Notifikasi" variant="danger-quiet" leading-icon="bell-fill" />
                    <HcisButton label="Generasi AI Magis" variant="primary" leading-icon="stars" />
                    <HcisButton label="Unduh Laporan" variant="secondary" leading-icon="download" />
                    <HcisButton label="Lanjutkan Transaksi" variant="primary" trailing-icon="arrow-right" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio Configurator State
const studioLabel = ref('Simpan Perubahan');
const studioVariant = ref('primary');
const studioSize = ref('md');
const studioLeadingIcon = ref('stars');
const studioLeadingAnimation = ref('');
const studioTrailingIcon = ref('arrow-right');
const studioTrailingAnimation = ref('');
const studioLoading = ref(false);
const studioLoadingIcon = ref('');
const studioLoadingAnimation = ref('');
const studioPill = ref(false);
const studioBlock = ref(false);
const studioIconOnly = ref(false);
const studioDisabled = ref(false);
const studioStageBg = ref('light'); // light | dark | mesh
const clickCount = ref(0);
const copiedCode = ref(false);

function handleStudioClick() {
    clickCount.value += 1;
}

// Reactive Code Generator Output
const generatedCode = computed(() => {
    const lines = ['<HcisButton'];
    if (!studioIconOnly.value && studioLabel.value) {
        lines.push(`    label="${studioLabel.value}"`);
    }
    if (studioVariant.value !== 'primary') {
        lines.push(`    variant="${studioVariant.value}"`);
    }
    if (studioSize.value !== 'md') {
        lines.push(`    size="${studioSize.value}"`);
    }
    if (studioPill.value) {
        lines.push('    pill');
    }
    if (studioBlock.value) {
        lines.push('    block');
    }
    if (studioIconOnly.value) {
        lines.push('    icon-only');
        lines.push(`    aria-label="${studioLabel.value || 'Aksi'}"`);
    }
    if (studioLeadingIcon.value) {
        lines.push(`    leading-icon="${studioLeadingIcon.value}"`);
    }
    if (studioLeadingAnimation.value) {
        lines.push(`    leading-animation="${studioLeadingAnimation.value}"`);
    }
    if (studioTrailingIcon.value && !studioIconOnly.value) {
        lines.push(`    trailing-icon="${studioTrailingIcon.value}"`);
    }
    if (studioTrailingAnimation.value && !studioIconOnly.value) {
        lines.push(`    trailing-animation="${studioTrailingAnimation.value}"`);
    }
    if (studioLoading.value) {
        lines.push('    loading');
    }
    if (studioLoading.value && studioLoadingIcon.value) {
        lines.push(`    loading-icon="${studioLoadingIcon.value}"`);
    }
    if (studioLoading.value && studioLoadingAnimation.value) {
        lines.push(`    loading-animation="${studioLoadingAnimation.value}"`);
    }
    if (studioDisabled.value) {
        lines.push('    disabled');
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

// Async Action Simulation State
const asyncStatus = ref('idle'); // idle | loading | success

function triggerAsyncAction() {
    if (asyncStatus.value === 'loading') return;
    asyncStatus.value = 'loading';

    setTimeout(() => {
        asyncStatus.value = 'success';
        setTimeout(() => {
            asyncStatus.value = 'idle';
        }, 3000);
    }, 1500);
}
</script>

<style scoped>
.ds-button-demo {
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
    min-height: 110px;
    padding: 2rem 1.5rem;
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

.ds-anatomy-preview {
    padding: 1.5rem;
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-surface-soft);
    border: 1px dashed var(--hcis-border-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
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
