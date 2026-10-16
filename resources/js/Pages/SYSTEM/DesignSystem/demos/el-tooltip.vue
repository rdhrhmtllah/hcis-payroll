<template>
    <div v-if="section === 'anatomy'" style="max-width: 32rem">
        <HcisTooltip content="Skor dihitung dari rata-rata bulan berjalan, dinormalisasi lalu dibatasi 125." placement="top">
            <HcisButton label="Rata-rata Skor Kinerja" variant="secondary" size="sm" />
        </HcisTooltip>
        <p class="ds-hint">
            Petunjuk melayang HcisTooltip digunakan untuk menjelaskan kalkulasi atau istilah teknis yang tidak memerlukan interaksi kompleks.
        </p>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-inline-gap">
        <HcisTooltip content="Muncul di atas" placement="top">
            <HcisButton label="Atas" variant="secondary" size="sm" />
        </HcisTooltip>
        <HcisTooltip content="Muncul di kanan" placement="right">
            <HcisButton label="Kanan" variant="secondary" size="sm" />
        </HcisTooltip>
        <HcisTooltip content="Muncul di bawah" placement="bottom">
            <HcisButton label="Bawah" variant="secondary" size="sm" />
        </HcisTooltip>
        <HcisTooltip content="Muncul di kiri" placement="left">
            <HcisButton label="Kiri" variant="secondary" size="sm" />
        </HcisTooltip>
    </div>

    <div v-else-if="section === 'states'" style="max-width: 32rem">
        <p class="ds-hint">Opsi tema warna</p>
        <div class="hcis-inline-gap">
            <HcisTooltip content="Tema Gelap" theme="dark">
                <HcisButton label="Dark" variant="secondary" size="sm" />
            </HcisTooltip>
            <HcisTooltip content="Tema Terang" theme="light">
                <HcisButton label="Light" variant="secondary" size="sm" />
            </HcisTooltip>
        </div>
    </div>
</template>

<script setup>
import { HcisButton, HcisTooltip } from '@/components/ui';

defineProps({ section: { type: String, required: true } });
</script>

<style scoped>
.hcis-inline-gap {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.ds-hint { margin: var(--hcis-space-2) 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
</style>
