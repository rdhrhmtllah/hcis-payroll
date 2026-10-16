<template>
    <HcisAlert
        class="mb-3"
        :variant="modulIniBaru ? 'warning' : 'info'"
        appearance="subtle"
        :title="modulIniBaru ? 'Modul ini memakai aturan BARU' : 'Kini dikelola di Akses & Peran'"
    >
        <template v-if="modulIniBaru">
            Akses lewat peran di modul ini diatur oleh matriks hak di Akses &amp; Peran; hibah modul tidak lagi memberi
            akses. Penetapan menu per karyawan di layar ini tetap berlaku sebagai grant langsung — akses penuh ke
            halaman itu.
        </template>
        <template v-else>
            Penugasan peran, grant, dan matriks hak kini tersedia di Akses &amp; Peran. Layar ini tetap berjalan selama
            masa peralihan.
            <span v-if="modulBaru.length">Modul yang sudah memakai aturan BARU: {{ modulBaru.join(', ') }}.</span>
        </template>
        <template v-if="tautan" #actions>
            <HcisButton size="sm" variant="secondary" label="Buka Akses & Peran" @click="buka" />
        </template>
    </HcisAlert>
</template>

<script setup>
/** Banner layar lama selama masa pensiun (RBAC Fase 5, spec §7). */
import { router } from '@inertiajs/vue3';
import { HcisAlert, HcisButton } from '@/components/ui';

const props = defineProps({
    modulBaru: { type: Array, default: () => [] },
    modulIniBaru: { type: Boolean, default: false },
    // Tujuan tombol: Akses & Peran yang memang bisa dibuka (SYSTEM bagi System Admin,
    // halaman modul bagi admin modul); null = tanpa tombol.
    tautan: { type: String, default: null },
});

const buka = () => router.visit(props.tautan);
</script>
