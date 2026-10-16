<template>
    <div v-if="section === 'anatomy'" class="hcis-stack">
        <HcisAlert
            :variant="modeTenang ? 'warning' : 'info'"
            :title="modeTenang ? 'Mode gerak tenang sedang menyala' : 'Mode gerak penuh'"
            :description="
                modeTenang
                    ? 'Perpindahan dan denyut sedang dibuang; pudar dan spinner dipertahankan. Ini saklar HCIS sendiri — setelan sistem operasi tidak lagi memengaruhinya.'
                    : 'Semua animasi berjalan penuh. Ini yang diterima setiap pengguna secara baku, tanpa perlu mengubah setelan apa pun di komputernya.'
            "
        >
            <template #actions>
                <HcisButton
                    size="sm"
                    variant="outline"
                    :label="modeTenang ? 'Kembalikan gerak penuh' : 'Coba mode tenang'"
                    @click="tukarMode"
                />
            </template>
        </HcisAlert>
        <p class="ds-hint">Arahkan kursor dan tekan — tiap elemen di bawah punya reaksi hover, fokus, dan tekan.</p>
        <div class="hcis-row">
            <HcisButton label="Tombol" />
            <HcisButton label="Sekunder" variant="secondary" />
            <HcisButton label="Ghost" variant="ghost" />
        </div>
        <HcisCard title="Kartu interactive" description="Naik 2px saat hover, kembali saat ditekan." interactive />
    </div>

    <div v-else-if="section === 'variants'" class="hcis-stack">
        <div>
            <p class="ds-hint">Dua durasi. Sebelah kiri 160ms, kanan 240ms — arahkan kursor ke keduanya.</p>
            <div class="hcis-row">
                <span class="ds-swatch ds-swatch--fast">fast · 160ms</span>
                <span class="ds-swatch ds-swatch--normal">normal · 240ms</span>
            </div>
        </div>
        <div>
            <p class="ds-hint">Satu kurva untuk semuanya: <code>cubic-bezier(0.22, 1, 0.36, 1)</code> — cepat di awal, mendarat halus.</p>
            <div class="ds-track" @mouseenter="jalan = true" @mouseleave="jalan = false">
                <span class="ds-dot" :class="{ 'is-end': jalan }"></span>
            </div>
        </div>
    </div>

    <div v-else-if="section === 'states'" class="hcis-stack">
        <p class="ds-hint">
            <strong>Keluar-masuk.</strong> Tiga transisi bernama, dipilih berdasarkan <em>asal</em> elemen,
            bukan selera. Klik untuk melihat masuk dan keluarnya.
        </p>

        <div class="hcis-row" style="flex-wrap: wrap; gap: var(--hcis-space-2)">
            <HcisButton label="fade" variant="secondary" size="sm" @click="pilihPreset('fade')" />
            <HcisButton label="slide-up" variant="secondary" size="sm" @click="pilihPreset('slide-up')" />
            <HcisButton label="slide-down" variant="secondary" size="sm" @click="pilihPreset('slide-down')" />
            <HcisButton label="slide-left" variant="secondary" size="sm" @click="pilihPreset('slide-left')" />
            <HcisButton label="slide-right" variant="secondary" size="sm" @click="pilihPreset('slide-right')" />
            <HcisButton label="zoom-in (spring)" variant="secondary" size="sm" @click="pilihPreset('zoom-in')" />
            <HcisButton label="flip 3D" variant="secondary" size="sm" @click="pilihPreset('flip')" />
            <HcisButton label="daftar (TransitionGroup)" variant="primary" size="sm" @click="tambahPesan" />
        </div>

        <div class="ds-stage">
            <Gerak :name="presetAktif" key="demo-preset">
                <HcisAlert
                    :key="presetAktif + '-' + count"
                    :variant="getVariant(presetAktif)"
                    :title="`Animasi Preset: <Gerak name='${presetAktif}'>`"
                    :description="`Setiap pergantian preset meluncur secara presisi dengan kurva cubic-bezier standar HCIS.`"
                    closable
                    @close="count++"
                />
            </Gerak>
        </div>

        <Gerak name="slide-up" group tag="div" class="hcis-stack hcis-stack--sm">
            <HcisAlert
                v-for="b in pesan"
                :key="b"
                variant="info"
                :description="`Baris ${b} — yang tersisa bergeser halus, bukan melompat.`"
                closable
                @close="pesan = pesan.filter((x) => x !== b)"
            />
        </Gerak>

        <HcisDivider label="Properti yang boleh dianimasikan" />

        <p class="ds-hint">
            Properti di bawah dipilih karena tidak memaksa browser menghitung ulang tata letak
            tiap frame. <code>width</code> dan <code>height</code> memaksa itu — karena itu dilarang.
        </p>
        <HcisTable :columns="kolom" :rows="baris" row-key="prop">
            <template #cell-status="{ value }">
                <HcisBadge :label="value" :variant="value === 'Boleh' ? 'success' : 'danger'" size="sm" />
            </template>
        </HcisTable>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { HcisAlert, HcisBadge, HcisButton, HcisCard, HcisDivider, HcisTable, Gerak } from '@/components/ui';
import { MODE_TENANG, balikModeGerak, modeGerakAktif } from '@/motionMode';

defineProps({ section: { type: String, required: true } });

const jalan = ref(false);
const presetAktif = ref('slide-up');
const count = ref(0);
const pesan = ref([1, 2, 3]);
let urut = 3;

function pilihPreset(name) {
    presetAktif.value = name;
    count.value++;
}

function getVariant(name) {
    if (name === 'fade') return 'info';
    if (name === 'slide-up' || name === 'slide-down') return 'success';
    if (name === 'slide-left' || name === 'slide-right') return 'brand';
    if (name === 'zoom-in') return 'warning';
    if (name === 'flip') return 'danger';
    return 'info';
}

function tambahPesan() {
    urut += 1;
    pesan.value = [urut, ...pesan.value];
}
/*
 * Sengaja TIDAK membaca matchMedia. Setelan sistem operasi bukan lagi pemicu
 * mode tenang di HCIS, jadi membacanya di sini akan melaporkan keadaan yang
 * tidak menggambarkan apa pun yang sedang berlaku di layar.
 *
 * Dibaca dari sumber bersama, bukan disalin ke ref lokal: penanda mode tenang
 * di pojok halaman membaca sumber yang sama, dan dua salinan yang bisa
 * berbeda pasti akan berbeda.
 */
const modeTenang = computed(() => modeGerakAktif.value === MODE_TENANG);

function tukarMode() {
    balikModeGerak();
}

const kolom = [
    { key: 'prop', label: 'Properti' },
    { key: 'status', label: 'Status' },
    { key: 'alasan', label: 'Alasan' },
];

const baris = [
    { prop: 'transform', status: 'Boleh', alasan: 'Dikerjakan compositor, tidak menyentuh tata letak' },
    { prop: 'opacity', status: 'Boleh', alasan: 'Sama — paling murah' },
    { prop: 'color · background-color · border-color', status: 'Boleh', alasan: 'Hanya menggambar ulang, tanpa menghitung ulang posisi' },
    { prop: 'box-shadow', status: 'Boleh', alasan: 'Menggambar ulang; hindari pada elemen yang sangat besar' },
    { prop: 'width · height', status: 'Dilarang', alasan: 'Memaksa perhitungan tata letak ulang tiap frame. Pengecualian: HcisProgress — lebarnya adalah isi informasinya, elemennya tunggal, dan berubah saat data datang, bukan saat kursor bergerak' },
    { prop: 'top · left · margin · padding', status: 'Dilarang', alasan: 'Sama — pakai transform: translate()' },
    { prop: 'filter · backdrop-filter', status: 'Dilarang', alasan: 'Mahal pada elemen besar, terutama di perangkat lapangan' },
    { prop: 'all', status: 'Dilarang', alasan: 'Ikut menganimasikan properti yang tidak kamu duga' },
];
</script>

<style scoped>
.ds-hint { margin: 0 0 var(--hcis-space-2); color: var(--hcis-text-muted); font-size: var(--hcis-font-size-xs); line-height: var(--hcis-line-normal); }
code { background: var(--hcis-surface-soft); border-radius: var(--hcis-space-1); padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.85em; }

.ds-swatch { display: inline-flex; align-items: center; padding: var(--hcis-space-3) var(--hcis-space-4); border-radius: var(--hcis-radius-md); background: var(--hcis-brand-50); color: var(--hcis-brand-700); font-size: var(--hcis-font-size-xs); font-weight: 800; cursor: default; }
.ds-swatch--fast { transition: background-color var(--hcis-duration-fast) var(--hcis-ease), transform var(--hcis-duration-fast) var(--hcis-ease); }
.ds-swatch--normal { transition: background-color var(--hcis-duration-fast) var(--hcis-ease), transform var(--hcis-duration-fast) var(--hcis-ease); }
.ds-swatch:hover { background: var(--hcis-brand-600); color: var(--hcis-white); transform: translateY(-2px); }

.ds-stage { display: flex; flex-direction: column; gap: var(--hcis-space-2); min-height: 12rem; }
.ds-track { position: relative; height: 2.5rem; border-radius: var(--hcis-radius-md); background: var(--hcis-surface-soft); border: 1px dashed var(--hcis-border-strong); }
.ds-dot { position: absolute; top: 50%; left: var(--hcis-space-2); width: 1.25rem; height: 1.25rem; margin-top: -0.625rem; border-radius: var(--hcis-radius-pill); background: var(--hcis-brand-600); transition: transform var(--hcis-duration-fast) var(--hcis-ease); }
/* translate, bukan left — supaya tidak memicu perhitungan tata letak ulang. */
.ds-dot.is-end { transform: translateX(14rem); }
</style>
