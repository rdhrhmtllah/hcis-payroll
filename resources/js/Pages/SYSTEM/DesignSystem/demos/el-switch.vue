<template>
    <div v-if="section === 'anatomy'" class="hcis-stack">
        <p class="ds-hint">
            Sakelar Primitif <strong>HcisSwitch</strong> dengan Penanganan Sentuh Fisika Tactile & Transisi Pegas 3D:
        </p>

        <div class="hcis-stack" style="gap: 16px">
            <div class="hcis-row">
                <HcisSwitch v-model="aktif" active-text="Notifikasi WhatsApp Aktif" inactive-text="Notifikasi WhatsApp Nonaktif" />
            </div>

            <div class="hcis-row">
                <HcisSwitch
                    v-model="notifWa"
                    active-thumb-icon="bi bi-moon-stars-fill"
                    inactive-thumb-icon="bi bi-sun-fill"
                    active-thumb-icon-color="#6366f1"
                    inactive-thumb-icon-color="#f59e0b"
                    active-text="Mode Gelap Kustom (Ikon Animasi Snap)"
                    inactive-text="Mode Terang Kustom"
                />
            </div>

            <div class="hcis-row" style="align-items: center; gap: 12px">
                <HcisSwitch
                    v-model="inlinePromptDemo"
                    inline-prompt
                    active-text="ON"
                    inactive-text="OFF"
                    size="lg"
                />
                <span style="font-size: 13px; color: var(--hcis-text-muted)">Sakelar Prompt Inline (Teks di Dalam Rel Track)</span>
            </div>
        </div>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-stack">
        <div>
            <p class="ds-hint">1. Kustomisasi Warna Rel Track & Ikon Handling Bulat (HcisSwitch):</p>
            <div class="hcis-row" style="gap: 20px; flex-wrap: wrap">
                <HcisSwitch
                    v-model="custom1"
                    active-color="#8b5cf6"
                    inactive-color="#e2e8f0"
                    active-thumb-icon="bi bi-star-fill"
                    active-thumb-icon-color="#ffffff"
                    active-text="Track Purple Hex (#8b5cf6)"
                />
                <HcisSwitch
                    v-model="custom2"
                    active-color="linear-gradient(135deg, #ec4899, #8b5cf6)"
                    active-thumb-icon="bi bi-heart-fill"
                    active-thumb-icon-color="#ffffff"
                    active-text="Track Pink Gradient"
                />
                <HcisSwitch
                    v-model="custom3"
                    active-color="#059669"
                    active-thumb-icon="bi bi-shield-check"
                    active-thumb-icon-color="#ffffff"
                    active-text="Track Emerald Hex"
                />
            </div>
        </div>

        <div style="margin-top: 16px">
            <p class="ds-hint">2. Varian Sentimen Bawaan & Ukuran (SM · MD · LG):</p>
            <div class="hcis-row" style="gap: 24px; flex-wrap: wrap">
                <HcisSwitch v-model="vBrand" size="sm" variant="brand" active-text="Brand SM" />
                <HcisSwitch v-model="vSuccess" size="md" variant="success" active-text="Success MD" />
                <HcisSwitch v-model="vDanger" size="lg" variant="danger" active-text="Danger LG" />
            </div>
        </div>
    </div>

    <div v-else-if="section === 'states'" class="hcis-stack">
        <div>
            <p class="ds-hint">Keadaan Memuat (Loading) & Disabled:</p>
            <div class="hcis-row" style="gap: 24px">
                <HcisSwitch v-model="aktif" active-text="Berlaku Seketika" />
                <HcisSwitch v-model="aktif" loading active-text="Loading (Proses API)" />
                <HcisSwitch v-model="aktif" disabled active-text="Disabled (Terkunci)" />
            </div>
        </div>

        <HcisAlert
            variant="warning"
            title="Jangan pakai sakelar di dalam formulir bertombol Simpan"
            description="Sakelar menjanjikan perubahan seketika. Di dalam formulir yang baru berlaku setelah Simpan ditekan, ia berbohong — pakai HcisCheckbox."
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisAlert, HcisSwitch } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const aktif = ref(true);
const notifWa = ref(true);
const inlinePromptDemo = ref(true);

const custom1 = ref(true);
const custom2 = ref(true);
const custom3 = ref(true);

const vBrand = ref(true);
const vSuccess = ref(true);
const vDanger = ref(true);
</script>

<style scoped>
.ds-hint { margin: var(--hcis-space-2) 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
</style>

