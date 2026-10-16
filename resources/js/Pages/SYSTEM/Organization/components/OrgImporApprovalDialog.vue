<template>
    <HcisDialog
        :model-value="modelValue"
        kicker="SINKRONISASI ATASAN"
        title="Impor Atasan (Approval KPI & HCIS)"
        description="Sinkronkan hierarki atasan langsung dari riwayat hierarki approval KPI & HCIS secara bertahap."
        icon="diagram-2"
        sentiment="brand"
        variant="glass"
        size="lg"
        header-divider
        sticky-footer
        @update:model-value="tutup"
    >
        <div class="org-apr">
            <!-- Langkah 1: Unduh usulan -->
            <section class="org-apr__card">
                <div class="org-apr__card-header">
                    <span class="org-apr__step-badge">1</span>
                    <h4>Unduh Usulan Atasan</h4>
                </div>
                <p class="org-apr__desc">
                    Berkas Excel memuat satu baris per karyawan aktif yang memiliki riwayat approval.
                    <b>Atasan 1</b> dan <b>Atasan 2</b> dikonsolidasikan dari gabungan hierarki approval
                    <b>KPI</b> lalu <b>HCIS</b> (urutan 1–2, tanpa nama ganda), beserta atasan aktif saat ini
                    dan status perbandingannya (<code>BARU</code> / <code>LENGKAPI</code> / <code>BEDA</code> / <code>SAMA</code>).
                </p>

                <div class="org-apr__legend-row">
                    <HcisBadge label="BARU" variant="success" size="xs" />
                    <span>Belum punya atasan (otomatis diimpor)</span>
                    <span class="mx-1 text-slate-300">·</span>
                    <HcisBadge label="LENGKAPI" variant="brand" size="xs" />
                    <span>Atasan 2 baru (otomatis diimpor)</span>
                    <span class="mx-1 text-slate-300">·</span>
                    <HcisBadge label="BEDA / SAMA" variant="neutral" size="xs" />
                    <span>Perlu ditinjau</span>
                </div>

                <div>
                    <HcisButton
                        label="Unduh Excel Usulan"
                        variant="secondary"
                        size="sm"
                        leading-icon="file-earmark-arrow-down"
                        :loading="mengunduh"
                        data-uji="org-apr-unduh"
                        @click="unduh"
                    />
                </div>
                <HcisAlert v-if="galatUnduh" variant="danger" appearance="soft" size="sm" class="mt-2" :title="galatUnduh" />
            </section>

            <!-- Langkah 2: Tinjau di Excel -->
            <section class="org-apr__card">
                <div class="org-apr__card-header">
                    <span class="org-apr__step-badge">2</span>
                    <h4>Tinjau &amp; Sesuaikan di Excel</h4>
                </div>
                <p class="org-apr__desc">
                    Buka berkas di Excel dan sesuaikan kolom berwarna oranye berikut bila ada perubahan.
                    Petunjuk detail kode status tersedia di lembar <em>"Petunjuk"</em>.
                </p>

                <div class="org-apr__col-grid">
                    <div class="org-apr__col-card">
                        <span class="org-apr__col-name">
                            <HcisIcon name="pencil-square" size="2xs" />
                            Atasan Dipakai
                        </span>
                        <span class="org-apr__col-desc">NIK/nama atasan utama yang akan disimpan</span>
                    </div>
                    <div class="org-apr__col-card">
                        <span class="org-apr__col-name">
                            <HcisIcon name="pencil-square" size="2xs" />
                            Atasan 2 Dipakai
                        </span>
                        <span class="org-apr__col-desc">Atasan kedua jika berlaku fungsional</span>
                    </div>
                    <div class="org-apr__col-card">
                        <span class="org-apr__col-name">
                            <HcisIcon name="check-circle" size="2xs" />
                            Impor (Y/T)
                        </span>
                        <span class="org-apr__col-desc">Setel Y untuk memproses baris karyawan ini</span>
                    </div>
                    <div class="org-apr__col-card">
                        <span class="org-apr__col-name">
                            <HcisIcon name="arrow-repeat" size="2xs" />
                            Ganti Atasan Lama (Y/T)
                        </span>
                        <span class="org-apr__col-desc">Setel Y untuk mencabut atasan lama di sistem</span>
                    </div>
                </div>
            </section>

            <!-- Langkah 3: Impor dan periksa -->
            <section class="org-apr__card">
                <div class="org-apr__card-header">
                    <span class="org-apr__step-badge">3</span>
                    <h4>Impor &amp; Validasi Integritas</h4>
                </div>
                <p class="org-apr__desc">
                    Unggah berkas Excel yang telah ditinjau. Sistem langsung memeriksa integritas data tanpa
                    menyimpan apa pun. Simpan hanya bila seluruh baris valid.
                </p>

                <HcisUpload
                    :model-value="berkas"
                    accept=".xlsx"
                    :max-size="10"
                    layout="bar"
                    data-uji="org-apr-berkas"
                    @update:model-value="gantiBerkas"
                />

                <div v-if="memeriksa" class="org-apr__memuat">
                    <HcisIcon name="arrow-repeat" size="xs" animation="spin" />
                    <span>Memeriksa validitas berkas...</span>
                </div>

                <template v-if="pratinjau && !memeriksa">
                    <div class="org-apr__angka" data-uji="org-apr-pratinjau">
                        <div v-for="a in angka" :key="a.label" class="org-apr__kotak" :class="`is-${a.nada}`">
                            <b>{{ a.nilai.toLocaleString('id-ID') }}</b>
                            <span>{{ a.label }}</span>
                        </div>
                    </div>
                    <HcisAlert
                        v-if="pratinjau.galat.length"
                        variant="danger"
                        appearance="soft"
                        size="sm"
                        class="mt-2"
                        data-uji="org-apr-galat"
                        :title="`${pratinjau.galat.length} baris bermasalah — tidak ada yang akan disimpan`"
                    >
                        <ul class="org-apr__galat">
                            <li v-for="g in pratinjau.galat.slice(0, 50)" :key="g">{{ g }}</li>
                            <li v-if="pratinjau.galat.length > 50">… dan {{ pratinjau.galat.length - 50 }} lainnya.</li>
                        </ul>
                        Perbaiki di Excel (atau ubah Impor = T untuk baris itu), lalu unggah ulang.
                    </HcisAlert>
                    <HcisAlert
                        v-else-if="!pratinjau.diimpor"
                        variant="info"
                        appearance="soft"
                        size="sm"
                        class="mt-2"
                        title="Tidak ada baris dengan Impor = Y"
                    />
                    <HcisAlert
                        v-else
                        variant="success"
                        appearance="soft"
                        size="sm"
                        class="mt-2"
                        :title="ringkasanSiap"
                    />
                </template>
                <HcisAlert v-if="galatImpor" variant="danger" appearance="soft" size="sm" class="mt-2" :title="galatImpor" />
            </section>
        </div>

        <template #footer>
            <HcisButton label="Tutup" variant="secondary" size="sm" :disabled="menyimpan" @click="tutup(false)" />
            <HcisButton
                label="Simpan ke Relasi Org"
                variant="primary"
                size="sm"
                leading-icon="check-lg"
                :loading="menyimpan"
                :disabled="!bolehSimpan"
                data-uji="org-apr-simpan"
                @click="simpan"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, ref } from 'vue';
import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisIcon, HcisUpload } from '@/components/ui';

defineOptions({ name: 'OrgImporApprovalDialog' });

const props = defineProps({ modelValue: { type: Boolean, default: false } });
const emit = defineEmits(['update:modelValue', 'tersimpan']);

const ALAMAT = '/system/organisasi/relasi/usulan-approval';

const mengunduh = ref(false);
const galatUnduh = ref('');
const berkas = ref([]);
const memeriksa = ref(false);
const menyimpan = ref(false);
const pratinjau = ref(null);
const galatImpor = ref('');

const berkasMentah = computed(() => berkas.value?.[0]?.raw || null);
const bolehSimpan = computed(() => Boolean(berkasMentah.value && pratinjau.value && !pratinjau.value.galat.length && pratinjau.value.diimpor > 0 && !memeriksa.value));
const angka = computed(() => {
    const p = pratinjau.value || {};
    return [
        { label: 'baris dibaca', nilai: p.dibaca || 0, nada: 'netral' },
        { label: 'akan diimpor', nilai: p.diimpor || 0, nada: 'utama' },
        { label: 'relasi baru', nilai: p.dibuat || 0, nada: 'hijau' },
        { label: 'sudah ada', nilai: p.sudah_ada || 0, nada: 'netral' },
        { label: 'ganti atasan', nilai: p.diganti || 0, nada: 'kuning' },
        { label: 'atasan lama dicabut', nilai: p.dicabut || 0, nada: 'kuning' },
        { label: 'dilewati (T)', nilai: p.dilewati || 0, nada: 'netral' },
    ];
});
const ringkasanSiap = computed(() => {
    const p = pratinjau.value;
    return `Siap disimpan: ${p.dibuat} relasi atasan baru${p.diganti ? `, ${p.diganti} karyawan berganti atasan (${p.dicabut} atasan lama dicabut)` : ''}. Pasangan BAWAHAN dibuat otomatis.`;
});

function tutup(v = false) {
    if (menyimpan.value) return;
    emit('update:modelValue', v);
    if (!v) {
        berkas.value = [];
        pratinjau.value = null;
        galatImpor.value = '';
        galatUnduh.value = '';
    }
}

async function unduh() {
    mengunduh.value = true;
    galatUnduh.value = '';
    try {
        const res = await fetch(`${ALAMAT}/ekspor`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) {
            const isi = await res.json().catch(() => ({}));
            throw new Error(isi?.message || `Unduh gagal (${res.status}).`);
        }
        const blob = await res.blob();
        const nama = (res.headers.get('Content-Disposition') || '').match(/filename="?([^"]+)"?/)?.[1] || 'Usulan-Atasan-dari-Approval.xlsx';
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nama;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 2000);
    } catch (e) {
        galatUnduh.value = e?.message || 'Unduh gagal.';
    } finally {
        mengunduh.value = false;
    }
}

async function kirim(jalankan) {
    const data = new FormData();
    data.append('berkas', berkasMentah.value);
    data.append('jalankan', jalankan ? '1' : '0');
    const res = await fetch(`${ALAMAT}/impor`, {
        method: 'POST',
        body: data,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    });
    const isi = await res.json().catch(() => ({}));
    if (!res.ok) {
        const daftar = Object.values(isi?.errors || {}).flat();
        throw new Error(daftar.length ? daftar.join(' ') : (isi?.message || `Permintaan gagal (${res.status}).`));
    }
    return isi?.result ?? isi?.data ?? isi;
}

/* Berkas yang diunggah ulang (setelah diperbaiki di Excel) MENGGANTIKAN yang lama — tanpa batas
   jumlah di komponen, supaya admin tidak perlu menghapus berkas lama lebih dulu (uji browser 9 Okt). */
function gantiBerkas(daftar) {
    const list = Array.isArray(daftar) ? daftar : [];
    berkas.value = list.length > 1 ? [list[list.length - 1]] : list;
    periksa();
}

async function periksa() {
    pratinjau.value = null;
    galatImpor.value = '';
    if (!berkasMentah.value) return;
    memeriksa.value = true;
    try {
        pratinjau.value = await kirim(false);
    } catch (e) {
        galatImpor.value = e?.message || 'Berkas gagal diperiksa.';
    } finally {
        memeriksa.value = false;
    }
}

async function simpan() {
    if (!bolehSimpan.value) return;
    menyimpan.value = true;
    galatImpor.value = '';
    try {
        const h = await kirim(true);
        if (!h?.dijalankan) {
            pratinjau.value = h;
            throw new Error('Tidak disimpan: data berubah sejak diperiksa. Periksa daftar galat di atas.');
        }
        emit('tersimpan', h);
        menyimpan.value = false;
        tutup(false);
    } catch (e) {
        galatImpor.value = e?.message || 'Gagal menyimpan.';
    } finally {
        menyimpan.value = false;
    }
}

defineExpose({ periksa });
</script>

<style scoped>
.org-apr {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    min-width: 0;
}

.org-apr__card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    transition: all 0.15s ease;
}

.org-apr__card:hover {
    border-color: #cbd5e1;
}

.org-apr__card-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.org-apr__step-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 999px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 700;
    flex-shrink: 0;
}

.org-apr__card-header h4 {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.org-apr__desc {
    margin: 0;
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.5;
}

.org-apr__legend-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.65rem 0.85rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.73rem;
    color: #334155;
}

.org-apr__col-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.6rem;
}

.org-apr__col-card {
    padding: 0.65rem 0.85rem;
    background: #ffffff;
    border: 1px solid #fed7aa;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.org-apr__col-name {
    font-size: 0.76rem;
    font-weight: 700;
    color: #c2410c;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.org-apr__col-desc {
    font-size: 0.7rem;
    color: #64748b;
    line-height: 1.35;
}

.org-apr__memuat {
    margin-top: 0.5rem;
    padding: 0.6rem 0.85rem;
    background: #f1f5f9;
    border-radius: 8px;
    font-size: 0.78rem;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.org-apr__angka {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.6rem;
    margin-top: 0.5rem;
}

.org-apr__kotak {
    padding: 0.65rem 0.8rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    transition: all 0.15s ease;
}

.org-apr__kotak b {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}

.org-apr__kotak span {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    text-transform: capitalize;
}

.org-apr__kotak.is-utama {
    border-color: #818cf8;
    background: #eef2ff;
}
.org-apr__kotak.is-utama b {
    color: #4338ca;
}

.org-apr__kotak.is-hijau {
    border-color: #86efac;
    background: #f0fdf4;
}
.org-apr__kotak.is-hijau b {
    color: #15803d;
}

.org-apr__kotak.is-kuning {
    border-color: #fde047;
    background: #fefce8;
}
.org-apr__kotak.is-kuning b {
    color: #a16207;
}

.org-apr__galat {
    margin: 0.35rem 0 0.25rem;
    padding-left: 1.15rem;
    max-height: 12rem;
    overflow-y: auto;
    font-size: 0.76rem;
    line-height: 1.45;
}
</style>
