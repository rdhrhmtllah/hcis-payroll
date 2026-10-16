<template>
    <div v-if="section === 'anatomy'" class="hcis-stack">
        <HcisPagination
            v-model:current-page="halaman"
            v-model:page-size="ukuran"
            :page-sizes="[10, 25, 50, 100]"
            :total="1284"
            layout="total, sizes, prev, pager, next, jumper"
            background
        />
        <p class="ds-hint">
            <code>total</code> selalu ditampilkan agar orang tahu sisa datanya. Paginasi dikerjakan di server —
            mengunduh 1.284 baris ke browser lalu memotongnya di klien adalah penyebab halaman berat yang sudah tercatat di audit CPU.
        </p>
    </div>

    <div v-else-if="section === 'variants'" class="hcis-stack">
        <div>
            <p class="ds-hint">Lengkap Dengan Total, Sizes, Pager, Next, & Jumper (Untuk Tabel Utama)</p>
            <HcisPagination v-model:current-page="halaman" :page-size="25" :total="1284" layout="total, sizes, prev, pager, next, jumper" background />
        </div>
        <div>
            <p class="ds-hint">Ringkas — Untuk Daftar Sekunder Di Dalam Kartu</p>
            <HcisPagination v-model:current-page="halaman" :page-size="25" :total="1284" layout="total, prev, pager, next" background />
        </div>
    </div>

    <div v-else-if="section === 'states'" class="hcis-stack">
        <div>
            <p class="ds-hint">Banyak Halaman</p>
            <HcisPagination v-model:current-page="halaman" :page-size="25" :total="1284" layout="total, prev, pager, next" background />
        </div>
        <div>
            <p class="ds-hint">Satu Halaman — Tetap Tampil Agar Posisi Tidak Berpindah</p>
            <HcisPagination :current-page="1" :page-size="25" :total="12" layout="total, prev, pager, next" background />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisPagination } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const halaman = ref(3);
const ukuran = ref(25);
</script>

<style scoped>
.ds-hint { margin: var(--hcis-space-2) 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
code { background: var(--hcis-surface-soft); border-radius: var(--hcis-space-1); padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.85em; }
</style>
