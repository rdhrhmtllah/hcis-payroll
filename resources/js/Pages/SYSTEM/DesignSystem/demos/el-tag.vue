<template>
    <div v-if="section === 'anatomy'">
        <HcisAlert
            variant="danger"
            title="Dipensiunkan — jangan dipakai untuk kode baru"
            description="Kita punya HcisBadge dengan pemetaan status yang sudah disepakati. Memakai el-tag membuat dua bahasa status berdampingan di aplikasi yang sama."
        />
    </div>

    <div v-else-if="section === 'variants'" class="hcis-grid hcis-grid--2">
        <div class="ds-dont">
            <p class="ds-dont__label">Jangan — el-tag</p>
            <div class="hcis-row">
                <el-tag type="success">Disetujui</el-tag>
                <el-tag type="warning">Menunggu</el-tag>
                <el-tag type="danger">Ditolak</el-tag>
            </div>
        </div>
        <div class="ds-do">
            <p class="ds-do__label">Lakukan — HcisBadge</p>
            <div class="hcis-row">
                <HcisBadge label="Disetujui" variant="success" />
                <HcisBadge label="Menunggu" variant="warning" />
                <HcisBadge label="Ditolak" variant="danger" />
            </div>
        </div>
    </div>

    <div v-else-if="section === 'states'">
        <p class="ds-hint">
            Masih terpakai di <strong>23 berkas</strong>. Diganti saat halaman yang memakainya kebetulan
            disentuh — bukan sebagai pekerjaan tersendiri.
        </p>
    </div>
</template>

<script setup>
import { HcisAlert, HcisBadge } from '@/components/ui';

defineProps({ section: { type: String, required: true } });
</script>

<style scoped>
.ds-hint { margin: 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); line-height: var(--hcis-line-normal); }
.ds-do, .ds-dont { border-radius: var(--hcis-radius-md); padding: var(--hcis-space-4); }
.ds-do { background: var(--hcis-success-50); }
.ds-dont { background: var(--hcis-danger-50); }
.ds-do__label, .ds-dont__label { margin: 0 0 var(--hcis-space-3); font-size: var(--hcis-font-size-xs); font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; }
.ds-do__label { color: var(--hcis-success-700); }
.ds-dont__label { color: var(--hcis-danger-700); }
</style>
