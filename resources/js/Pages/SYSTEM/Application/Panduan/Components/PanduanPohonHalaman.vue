<template>
    <div class="pohon" data-testid="panduan-pohon">
        <div class="pohon__pencarian-bar">
            <HcisField
                id="panduan-pohon-cari"
                v-model="cari"
                placeholder="Cari modul, fitur, atau kode halaman…"
                leading-icon="search"
                clearable
                size="sm"
            />
            <div class="pohon__toolbar">
                <div class="d-flex align-items-center gap-1">
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        :leading-icon="semuaTerbuka ? 'chevron-bar-contract' : 'chevron-bar-expand'"
                        :label="semuaTerbuka ? 'Tutup Semua' : 'Buka Semua'"
                        @click="alihSemuaCabang"
                    />
                    <HcisButton
                        v-if="semuaHalamanTersaring.length"
                        variant="ghost"
                        size="xs"
                        leading-icon="check-all"
                        label="Pilih Semua"
                        @click="pilihSemuaHasil"
                    />
                    <HcisButton
                        v-if="modelValue.length"
                        variant="ghost"
                        size="xs"
                        leading-icon="x-circle"
                        label="Lepas Semua"
                        @click="lepasSemua"
                    />
                </div>
                <div class="pohon__counter-pill">
                    <span class="pohon__counter-dot" :class="{ 'is-active': modelValue.length > 0 }" />
                    <span>{{ modelValue.length }} dipilih</span>
                </div>
            </div>
        </div>

        <div class="pohon__daftar" role="tree" aria-label="Modul, fitur, dan halaman">
            <div v-if="loading" class="pohon__info d-flex align-items-center justify-content-center gap-2 py-4">
                <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true" />
                <span>Memuat daftar halaman…</span>
            </div>
            <div v-else-if="!pohon.length" class="pohon__info py-4">
                <i class="bi bi-search text-muted fs-5 d-block mb-1" />
                <span>Tidak ada halaman yang cocok dengan pencarian "{{ cari }}".</span>
            </div>

            <div v-for="m in pohon" :key="m.key" class="pohon__simpul" role="treeitem" :aria-expanded="terbuka(m.key)">
                <div class="pohon__baris pohon__baris--modul" :class="{ 'is-selected': status(m.halaman) !== 'kosong' }">
                    <button type="button" class="pohon__panah" :aria-label="terbuka(m.key) ? 'Tutup' : 'Buka'" @click="alih(m.key)">
                        <i :class="['bi', terbuka(m.key) ? 'bi-chevron-down' : 'bi-chevron-right']" aria-hidden="true" />
                    </button>
                    <label class="pohon__label">
                        <input
                            type="checkbox"
                            :checked="status(m.halaman) === 'penuh'"
                            :indeterminate="status(m.halaman) === 'sebagian'"
                            @change="setSemua(m.halaman, $event.target.checked)"
                        />
                        <span class="pohon__modul-badge" :class="`pohon__modul-badge--${warnaModul(m.label)}`">
                            <i :class="['bi', ikonModul(m.label)]" class="me-1" aria-hidden="true" />
                            <span class="pohon__nama">{{ m.label }}</span>
                        </span>
                    </label>
                    <span class="pohon__hitung" :class="{ 'is-active': jumlahDipilih(m.halaman) > 0 }">
                        {{ jumlahDipilih(m.halaman) }}/{{ m.halaman.length }}
                    </span>
                </div>

                <div v-if="terbuka(m.key)" class="pohon__anak" role="group">
                    <div v-for="f in m.fitur" :key="f.key" class="pohon__simpul" role="treeitem" :aria-expanded="terbuka(f.key)">
                        <div class="pohon__baris pohon__baris--fitur" :class="{ 'is-selected': status(f.halaman) !== 'kosong' }">
                            <button type="button" class="pohon__panah" :aria-label="terbuka(f.key) ? 'Tutup' : 'Buka'" @click="alih(f.key)">
                                <i :class="['bi', terbuka(f.key) ? 'bi-chevron-down' : 'bi-chevron-right']" aria-hidden="true" />
                            </button>
                            <label class="pohon__label">
                                <input
                                    type="checkbox"
                                    :checked="status(f.halaman) === 'penuh'"
                                    :indeterminate="status(f.halaman) === 'sebagian'"
                                    @change="setSemua(f.halaman, $event.target.checked)"
                                />
                                <i class="bi bi-folder2 text-slate-400 me-1" aria-hidden="true" />
                                <span class="pohon__nama">{{ f.label }}</span>
                            </label>
                            <span class="pohon__hitung" :class="{ 'is-active': jumlahDipilih(f.halaman) > 0 }">
                                {{ jumlahDipilih(f.halaman) }}/{{ f.halaman.length }}
                            </span>
                        </div>

                        <div v-if="terbuka(f.key)" class="pohon__anak" role="group">
                            <label
                                v-for="h in f.halaman"
                                :key="h.value"
                                class="pohon__baris pohon__baris--halaman"
                                :class="{ 'is-selected': dipilih.has(h.value) }"
                                role="treeitem"
                                :data-halaman="h.value"
                            >
                                <input type="checkbox" :checked="dipilih.has(h.value)" @change="setSemua([h], $event.target.checked)" />
                                <i class="bi bi-file-earmark-text text-slate-400 me-1.5" aria-hidden="true" />
                                <span class="pohon__nama">{{ h.label }}</span>
                                <code class="pohon__kode">{{ h.value }}</code>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * Pemilih halaman berbentuk pohon Modul › Fitur › Halaman, bercentang.
 * Mencentang modul/fitur memilih seluruh halaman di bawahnya.
 * v-model: daftar Jenis_page yang dipilih.
 */
import { computed, reactive, ref } from 'vue';
import { HcisButton, HcisField } from '@/components/ui';

const props = defineProps({
    halaman: { type: Array, default: () => [] }, // [{ value, label, modul, fitur }]
    modelValue: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const URUTAN_MODUL = ['ESS', 'KPI', 'LMS', 'DMS', 'VISITOR', 'SYSTEM'];

const cari = ref('');
const dibuka = reactive(new Set());
const paksaBukaSemua = ref(false);
const dipilih = computed(() => new Set(props.modelValue));

const pohon = computed(() => {
    const q = cari.value.trim().toLowerCase();
    const modul = new Map();
    for (const h of props.halaman) {
        const kodeModul = h.modul && h.modul !== '-' ? h.modul : 'Tanpa modul';
        const namaFitur = h.fitur || 'Lainnya';
        if (q && !`${kodeModul} ${namaFitur} ${h.label} ${h.value}`.toLowerCase().includes(q)) continue;

        if (!modul.has(kodeModul)) modul.set(kodeModul, { key: `m:${kodeModul}`, label: kodeModul, fitur: new Map(), halaman: [] });
        const m = modul.get(kodeModul);
        if (!m.fitur.has(namaFitur)) m.fitur.set(namaFitur, { key: `f:${kodeModul}:${namaFitur}`, label: namaFitur, halaman: [] });
        m.fitur.get(namaFitur).halaman.push(h);
        m.halaman.push(h);
    }

    const peringkat = (k) => {
        const i = URUTAN_MODUL.indexOf(k);
        return i === -1 ? URUTAN_MODUL.length : i;
    };
    return [...modul.values()]
        .sort((a, b) => peringkat(a.label) - peringkat(b.label) || a.label.localeCompare(b.label))
        .map((m) => ({ ...m, fitur: [...m.fitur.values()] }));
});

const semuaHalamanTersaring = computed(() => {
    const list = [];
    pohon.value.forEach((m) => list.push(...m.halaman));
    return list;
});

const semuaTerbuka = computed(() => {
    if (cari.value.trim()) return true;
    return paksaBukaSemua.value;
});

function alihSemuaCabang() {
    if (semuaTerbuka.value) {
        paksaBukaSemua.value = false;
        dibuka.clear();
        pohon.value.forEach((m) => {
            dibuka.add(`tutup:${m.key}`);
            m.fitur.forEach((f) => dibuka.add(`tutup:${f.key}`));
        });
    } else {
        paksaBukaSemua.value = true;
        dibuka.clear();
        pohon.value.forEach((m) => {
            dibuka.add(m.key);
            m.fitur.forEach((f) => dibuka.add(f.key));
        });
    }
}

// Saat mencari semua simpul terbuka; selain itu yang dibuka pengguna, plus
// cabang yang memuat halaman terpilih (supaya pilihan lama langsung terlihat).
function terbuka(key) {
    if (cari.value.trim()) return true;
    if (paksaBukaSemua.value && !dibuka.has(`tutup:${key}`)) return true;
    if (dibuka.has(key)) return true;
    if (dibuka.has(`tutup:${key}`)) return false;
    return pohon.value.some((m) => (m.key === key && m.halaman.some((h) => dipilih.value.has(h.value)))
        || m.fitur.some((f) => f.key === key && f.halaman.some((h) => dipilih.value.has(h.value))));
}

function alih(key) {
    const kini = terbuka(key);
    dibuka.delete(key);
    dibuka.delete(`tutup:${key}`);
    dibuka.add(kini ? `tutup:${key}` : key);
}

function jumlahDipilih(daftar) {
    return daftar.reduce((n, h) => n + (dipilih.value.has(h.value) ? 1 : 0), 0);
}

function status(daftar) {
    const n = jumlahDipilih(daftar);
    if (!n) return 'kosong';
    return n === daftar.length ? 'penuh' : 'sebagian';
}

function setSemua(daftar, pilih) {
    const hasil = new Set(props.modelValue);
    daftar.forEach((h) => (pilih ? hasil.add(h.value) : hasil.delete(h.value)));
    // Urutan tetap: yang lama di depan, yang baru di belakang.
    emit('update:modelValue', [
        ...props.modelValue.filter((v) => hasil.has(v)),
        ...[...hasil].filter((v) => !props.modelValue.includes(v)),
    ]);
}

function pilihSemuaHasil() {
    setSemua(semuaHalamanTersaring.value, true);
}

function lepasSemua() {
    emit('update:modelValue', []);
}

function warnaModul(nama) {
    const n = String(nama || '').toUpperCase();
    if (n === 'ESS') return 'indigo';
    if (n === 'KPI' || n === 'PMS') return 'purple';
    if (n === 'LMS') return 'teal';
    if (n === 'DMS') return 'cyan';
    if (n === 'VISITOR' || n === 'VMS') return 'amber';
    if (n === 'SYSTEM') return 'slate';
    return 'neutral';
}

function ikonModul(nama) {
    const n = String(nama || '').toUpperCase();
    if (n === 'ESS') return 'bi-person-badge';
    if (n === 'KPI' || n === 'PMS') return 'bi-trophy';
    if (n === 'LMS') return 'bi-mortarboard';
    if (n === 'DMS') return 'bi-folder2-open';
    if (n === 'VISITOR' || n === 'VMS') return 'bi-shield-check';
    if (n === 'SYSTEM') return 'bi-gear';
    return 'bi-app';
}
</script>

<style scoped>
.pohon {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pohon__pencarian-bar {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pohon__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.15rem 0.25rem;
}

.pohon__counter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    border-radius: 9999px;
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-600, #475569);
}

.pohon__counter-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-slate-400, #94a3b8);
    transition: background-color 0.2s ease;
}

.pohon__counter-dot.is-active {
    background-color: var(--hcis-brand-500, #6366f1);
}

.pohon__daftar {
    max-height: 20rem;
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 10px;
    padding: 0.4rem;
    background: #ffffff;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.02);
}

.pohon__info {
    margin: 0;
    padding: 0.75rem;
    font-size: 0.82rem;
    color: var(--hcis-text-muted, #64748b);
    text-align: center;
}

.pohon__simpul {
    min-width: 0;
}

.pohon__anak {
    padding-left: 1.15rem;
    margin-left: 0.4rem;
    border-left: 1.5px solid var(--hcis-slate-200, #e2e8f0);
}

.pohon__baris {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 2.1rem;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    transition: background-color 0.15s ease;
    min-width: 0;
    box-sizing: border-box;
}

.pohon__baris:hover {
    background: var(--hcis-slate-50, #f8fafc);
}

.pohon__baris.is-selected {
    background: rgba(99, 102, 241, 0.04);
}

.pohon__baris--modul {
    font-weight: 700;
    margin-top: 0.25rem;
    border-bottom: 1px dashed var(--hcis-slate-100, #f1f5f9);
}

.pohon__baris--fitur {
    font-weight: 600;
    font-size: 0.84rem;
}

.pohon__baris--halaman {
    padding-left: 1.5rem;
    cursor: pointer;
    font-size: 0.82rem;
}

.pohon__baris--halaman:hover {
    background: rgba(99, 102, 241, 0.06);
}

.pohon__baris--halaman.is-selected {
    background: rgba(99, 102, 241, 0.09);
}

.pohon__panah {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: background-color 0.15s, color 0.15s;
}

.pohon__panah:hover {
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-800, #1e293b);
}

.pohon__label {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 1;
    min-width: 0;
    cursor: pointer;
    margin-bottom: 0;
}

.pohon__baris input[type='checkbox'] {
    width: 1.05rem;
    height: 1.05rem;
    flex-shrink: 0;
    accent-color: var(--hcis-brand-600, #4f46e5);
    cursor: pointer;
}

.pohon__modul-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.84rem;
    font-weight: 750;
    letter-spacing: -0.01em;
}

.pohon__modul-badge--indigo { color: #4338ca; }
.pohon__modul-badge--purple { color: #6d28d9; }
.pohon__modul-badge--teal { color: #0f766e; }
.pohon__modul-badge--cyan { color: #0369a1; }
.pohon__modul-badge--amber { color: #b45309; }
.pohon__modul-badge--slate { color: #334155; }
.pohon__modul-badge--neutral { color: #475569; }

.pohon__nama {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

.pohon__hitung {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    font-variant-numeric: tabular-nums;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-slate-100, #f1f5f9);
    flex-shrink: 0;
}

.pohon__hitung.is-active {
    color: var(--hcis-brand-700, #4338ca);
    background: rgba(99, 102, 241, 0.12);
    font-weight: 700;
}

.pohon__kode {
    margin-left: auto;
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #94a3b8);
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .pohon__kode {
        display: none;
    }
}
</style>
