<template>
    <!-- Section: Anatomy & Interactive Studio -->
    <div v-if="section === 'anatomy'" class="ds-icon-demo">
        <!-- Interactive Live Icon Studio -->
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="sliders" size="sm" class="text-indigo-600 me-2" />
                    Interactive Icon Studio
                </span>
                <span class="ds-card__tag">Live Configurator</span>
            </div>
            <div class="ds-card__body">
                <!-- Live Preview Stage -->
                <div class="ds-icon-stage mb-4">
                    <HcisIcon
                        :name="studioIcon"
                        :size="studioSize"
                        :color="studioColor"
                        :variant="studioVariant"
                        :animation="studioAnimation"
                        :label="studioLabel"
                    />
                </div>

                <!-- Controls Grid -->
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Nama Ikon (Bootstrap / FontAwesome)</label>
                        <input v-model="studioIcon" type="text" class="form-control form-control-sm" placeholder="e.g. arrow-repeat, stars, gem" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="ds-control-label">Ukuran (Size)</label>
                        <select v-model="studioSize" class="form-select form-select-sm">
                            <option value="2xs">2xs (10px)</option>
                            <option value="xs">xs (12px)</option>
                            <option value="sm">sm (14px)</option>
                            <option value="md">md (16px)</option>
                            <option value="lg">lg (20px)</option>
                            <option value="xl">xl (24px)</option>
                            <option value="2xl">2xl (32px)</option>
                            <option value="3xl">3xl (40px)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="ds-control-label">Warna (Color)</label>
                        <select v-model="studioColor" class="form-select form-select-sm">
                            <option value="">(Inherit / None)</option>
                            <option value="brand">brand (Sapphire)</option>
                            <option value="purple">purple (Violet)</option>
                            <option value="teal">teal (Toska)</option>
                            <option value="success">success (Zamrud)</option>
                            <option value="warning">warning (Amber)</option>
                            <option value="danger">danger (Crimson)</option>
                            <option value="info">info (Sky)</option>
                            <option value="obsidian">obsidian (Dark)</option>
                            <option value="muted">muted (Slate)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="ds-control-label">Wadah (Variant)</label>
                        <select v-model="studioVariant" class="form-select form-select-sm">
                            <option value="plain">plain (Ikon Bebas)</option>
                            <option value="boxed">boxed (Kotak Permata)</option>
                            <option value="circle">circle (Lingkaran Permata)</option>
                            <option value="glass">glass (Kaca Frosted)</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Animasi Proses (Animation)</label>
                        <select v-model="studioAnimation" class="form-select form-select-sm">
                            <option value="">(Tanpa Animasi)</option>
                            <option value="spin">spin (Laser Spinner 360°)</option>
                            <option value="flip">flip / hourglass (Putar 180° Jam Pasir & Jeda)</option>
                            <option value="gear">gear (Putaran Roda Gigi Mesin)</option>
                            <option value="ring">ring (Goncangan Dering Lonceng)</option>
                            <option value="compass">compass (Arah Jarum Kompas)</option>
                            <option value="stream">stream (Aliran Unggah/Unduh Awan)</option>
                            <option value="sparkle">sparkle (Kilau Bintang Bersinar)</option>
                            <option value="pulse">pulse (Denyut Radar Skala)</option>
                            <option value="glow">glow (Pendaran Aura Cahaya)</option>
                            <option value="bounce">bounce (Lompatan Vertikal)</option>
                            <option value="float">float (Melayang Anti-Gravitasi)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Anatomy Card -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="info-circle" size="sm" class="text-indigo-600 me-2" />
                    Standar Penggunaan & Aksesibilitas Ikon
                </span>
                <span class="ds-card__tag">A11y & Optical Alignment</span>
            </div>
            <div class="ds-card__body">
                <ul class="ds-anatomy-list">
                    <li>
                        <strong>Optical Centering Built-in:</strong> Seluruh ikon diatur otomatis dengan `display: inline-flex; align-items: center; justify-content: center; line-height: 1;` untuk mencegah offset vertikal saat digabungkan bersama teks atau tombol.
                    </li>
                    <li>
                        <strong>Aksesibilitas Cerdas:</strong> Jika tanpa `label`, ikon otomatis diberi atribut `aria-hidden="true"`. Jika diberi prop `:label="..."`, ikon otomatis memiliki peran gambar `role="img"` dan `aria-label`.
                    </li>
                    <li>
                        <strong>Animasi Cerdas Otomatis:</strong> Ikon jam pasir (`hourglass-split`), gear (`gear-fill`), dan lonceng (`bell-fill`) otomatis mengenali gerakan realistisnya saat diberi prop `spin`, `flip`, atau `ring`.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Section: Motion & Variants Showcase -->
    <div v-else-if="section === 'variants'" class="ds-icon-demo d-flex flex-column gap-4">
        <!-- Motion & Processing Animations Suite -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="lightning-charge" size="sm" class="text-indigo-600 me-2" />
                    Animasi Gerak & Pemrosesan Data Realistis (Realistic Motion Suite)
                </span>
                <span class="ds-card__tag">9 Motion Types</span>
            </div>
            <div class="ds-card__body">
                <div class="row g-4">
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="arrow-repeat" spin color="brand" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>1. Laser Spinner (`spin`)</strong>
                                <span>Rotasi 360° mulus linear 950ms untuk sinkronisasi data & proses aktif.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="hourglass-split" flip color="warning" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>2. Jam Pasir Realistis (`flip`)</strong>
                                <span>Berputar 180° dengan jeda waktu seperti butiran pasir nyata.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="gear-fill" animation="gear" color="obsidian" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>3. Putaran Roda Gigi (`gear`)</strong>
                                <span>Rotasi mekanis 3.2s berkesinambungan untuk proses kalkulasi background.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="bell-fill" ring color="danger" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>4. Dering Lonceng (`ring`)</strong>
                                <span>Goncangan lonceng realistis untuk peringatan mendesak / antrean tiket.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="compass" animation="compass" color="teal" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>5. Pencarian Kompas (`compass`)</strong>
                                <span>Osilasi jarum kompas untuk proses pencarian lokasi / routing API.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="cloud-arrow-up-fill" animation="stream" color="info" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>6. Aliran Unggah (`stream`)</strong>
                                <span>Gerakan mengalir vertikal untuk transfer berkas / sinkronisasi cloud.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="stars" sparkle color="purple" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>7. Kilau Bintang (`sparkle`)</strong>
                                <span>Pendaran kilau cahaya magis untuk fitur AI, reward, dan pencapaian.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="heart-pulse-fill" pulse color="danger" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>8. Radar Pulse (`pulse`)</strong>
                                <span>Denyutan skala (1.0 ➔ 1.18) untuk detak sinyal server / live status.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="ds-motion-card">
                            <HcisIcon name="gem" float color="brand" size="2xl" />
                            <div class="ds-motion-info">
                                <strong>9. Anti-Gravity Float (`float`)</strong>
                                <span>Melayang tenang naik-turun untuk elemen premium & hero.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jewel Box & Frosted Glass Suite -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="gem" size="sm" class="text-indigo-600 me-2" />
                    Wadah Permata & Kaca Kristal (Jewel Box & Frosted Glass)
                </span>
                <span class="ds-card__tag">boxed · circle · glass</span>
            </div>
            <div class="ds-card__body d-flex flex-column gap-3">
                <div>
                    <span class="ds-demo-label">Kotak Permata Bersudut Halus (`variant="boxed"`):</span>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <HcisIcon name="shield-lock-fill" variant="boxed" color="brand" size="xl" />
                        <HcisIcon name="journal-code" variant="boxed" color="purple" size="xl" />
                        <HcisIcon name="database-check" variant="boxed" color="teal" size="xl" />
                        <HcisIcon name="check-circle-fill" variant="boxed" color="success" size="xl" />
                        <HcisIcon name="exclamation-triangle-fill" variant="boxed" color="warning" size="xl" />
                        <HcisIcon name="slash-circle-fill" variant="boxed" color="danger" size="xl" />
                        <HcisIcon name="info-circle-fill" variant="boxed" color="info" size="xl" />
                        <HcisIcon name="moon-stars-fill" variant="boxed" color="obsidian" size="xl" />
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">Lingkaran Permata Bulat Penuh (`variant="circle"`):</span>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <HcisIcon name="stars" variant="circle" color="brand" size="xl" />
                        <HcisIcon name="award-fill" variant="circle" color="purple" size="xl" />
                        <HcisIcon name="patch-check-fill" variant="circle" color="teal" size="xl" />
                        <HcisIcon name="check-lg" variant="circle" color="success" size="xl" />
                        <HcisIcon name="lightning-charge-fill" variant="circle" color="warning" size="xl" />
                        <HcisIcon name="x-lg" variant="circle" color="danger" size="xl" />
                        <HcisIcon name="cloud-arrow-up-fill" variant="circle" color="info" size="xl" />
                        <HcisIcon name="person-badge-fill" variant="circle" color="obsidian" size="xl" />
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">Kaca Kristal Es Translusion (`variant="glass"`):</span>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <HcisIcon name="gem" variant="glass" color="brand" size="xl" />
                        <HcisIcon name="magic" variant="glass" color="purple" size="xl" />
                        <HcisIcon name="fingerprint" variant="glass" color="teal" size="xl" />
                        <HcisIcon name="award" variant="glass" color="success" size="xl" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Sizing Matrix Scale -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="aspect-ratio" size="sm" class="text-indigo-600 me-2" />
                    Matriks Skala Ukuran (8 Size Tiers)
                </span>
                <span class="ds-card__tag">2xs s/d 3xl</span>
            </div>
            <div class="ds-card__body">
                <div class="d-flex flex-wrap align-items-end gap-3">
                    <div v-for="s in ['2xs', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl']" :key="s" class="d-flex flex-column align-items-center gap-1">
                        <HcisIcon name="calendar-check" :size="s" color="brand" />
                        <span class="ds-meta-tag">{{ s }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: States -->
    <div v-else-if="section === 'states'" class="ds-icon-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="palette" size="sm" class="text-indigo-600 me-2" />
                    Pewarisan Warna Otomatis (`currentColor`)
                </span>
                <span class="ds-card__tag">Inherit Color</span>
            </div>
            <div class="ds-card__body">
                <p class="text-muted small mb-3">
                    Jika tanpa prop <code>color</code>, warna ikon secara alami mewarisi warna teks induknya (<code>currentColor</code>), sehingga harmonis dalam tombol, badge, maupun tabel.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <span class="badge bg-primary text-white p-2 d-inline-flex align-items-center gap-1">
                        <HcisIcon name="check-circle-fill" /> Badge Primary
                    </span>
                    <span class="badge bg-success text-white p-2 d-inline-flex align-items-center gap-1">
                        <HcisIcon name="shield-check" /> Badge Success
                    </span>
                    <span class="badge bg-danger text-white p-2 d-inline-flex align-items-center gap-1">
                        <HcisIcon name="exclamation-triangle-fill" /> Badge Danger
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio Configurator State
const studioIcon = ref('arrow-repeat');
const studioSize = ref('xl');
const studioColor = ref('brand');
const studioVariant = ref('boxed');
const studioAnimation = ref('spin');
const studioLabel = ref('Ikon Studio');
</script>

<style scoped>
.ds-icon-demo {
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

.ds-icon-stage {
    min-height: 100px;
    padding: 1.5rem;
    border-radius: var(--hcis-radius-lg);
    background: radial-gradient(circle at 50% 50%, rgb(var(--hcis-brand-500-rgb) / 0.08) 0%, transparent 80%),
                var(--hcis-surface-soft);
    border: 1px dashed var(--hcis-border-medium);
    display: flex;
    align-items: center;
    justify-content: center;
}

.ds-control-label {
    font-size: 0.725rem;
    font-weight: 700;
    color: var(--hcis-text-muted);
    margin-bottom: 0.25rem;
    display: block;
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

.ds-motion-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border-subtle);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: var(--hcis-ease);
}

.ds-motion-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--hcis-shadow-sm);
    border-color: var(--hcis-border-strong);
}

.ds-motion-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.ds-motion-info strong {
    font-size: 0.8125rem;
    color: var(--hcis-text-strong);
}

.ds-motion-info span {
    font-size: 0.725rem;
    color: var(--hcis-text-muted);
    line-height: 1.35;
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
