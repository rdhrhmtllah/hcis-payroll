<template>
    <div class="space-y-4">
        <HcisAlert variant="info">
            Komponen palet perintah global untuk pencarian cepat, navigasi menu, dan tindakan pintas.
            Dapat dipicu melalui tombol atau pintasan keyboard.
        </HcisAlert>

        <div class="flex items-center gap-3">
            <HcisButton variant="primary" @click="isOpen = true">
                Buka Palet Perintah (Command Palette)
            </HcisButton>
        </div>

        <HcisCommandPalette
            :show="isOpen"
            :sources="sumberPerintah"
            placeholder="Ketik nama menu, aksi, atau karyawan..."
            @close="isOpen = false"
            @jalankan="onJalankan"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisAlert, HcisButton, HcisCommandPalette, hcisMessage } from '@/components/ui';

const isOpen = ref(false);

const dataDemo = [
    { id: '1', judul: 'Dashboard Payroll', sub: 'Ringkasan penggajian & statistik', jenis: 'menu', icon: 'bi-speedometer2', tone: 'primary' },
    { id: '2', judul: 'Proses Payroll Bulanan', sub: 'Hitung gaji & generate slip', jenis: 'aksi', icon: 'bi-cash-coin', tone: 'success' },
    { id: '3', judul: 'Rekap PPh 21', sub: 'Laporan pemotongan pajak', jenis: 'laporan', icon: 'bi-file-earmark-spreadsheet', tone: 'warning' },
    { id: '4', judul: 'Komponen Gaji & Tunjangan', sub: 'Master formula pendapatan', jenis: 'menu', icon: 'bi-sliders', tone: 'neutral' },
];

const sumberPerintah = [
    {
        jenis: 'menu',
        label: 'Menu Navigasi',
        cari: async (q) => dataDemo.filter(d => d.jenis === 'menu' && (!q || d.judul.toLowerCase().includes(q.toLowerCase()))),
    },
    {
        jenis: 'aksi',
        label: 'Aksi Cepat',
        cari: async (q) => dataDemo.filter(d => d.jenis === 'aksi' && (!q || d.judul.toLowerCase().includes(q.toLowerCase()))),
    },
    {
        jenis: 'laporan',
        label: 'Laporan & Dokumen',
        cari: async (q) => dataDemo.filter(d => d.jenis === 'laporan' && (!q || d.judul.toLowerCase().includes(q.toLowerCase()))),
    },
];

function onJalankan(item) {
    isOpen.value = false;
    hcisMessage.success(`Menjalankan: ${item.judul}`);
}
</script>
