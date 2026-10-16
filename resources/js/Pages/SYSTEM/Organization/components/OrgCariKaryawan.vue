<template>
    <div class="ock">
        <!-- Sudah terpilih: tampilkan orangnya dengan kartu yang rapi -->
        <div v-if="modelValue" class="ock-terpilih">
            <div class="ock-terpilih-info">
                <HcisAvatar
                    :name="modelValue.nama || modelValue.kode_karyawan"
                    size="sm"
                    shape="circle"
                />
                <div class="ock-details">
                    <strong class="ock-nama">{{ modelValue.nama }}</strong>
                    <div class="ock-meta-line">
                        <span class="ock-nik-pill">{{ modelValue.kode_karyawan }}</span>
                        <span v-if="modelValue.nama_level" class="ock-level-pill">{{ modelValue.nama_level }}</span>
                        <span v-if="modelValue.kode_perusahaan" class="ock-pt-pill">PT: {{ modelValue.kode_perusahaan }}</span>
                        <span v-if="modelValue.divisi" class="ock-divisi-pill">{{ modelValue.divisi }}</span>
                    </div>
                </div>
            </div>
            <HcisButton
                label="Ganti"
                variant="ghost"
                size="xs"
                leading-icon="arrow-repeat"
                class="ock-ganti-btn"
                @click="ganti"
            />
        </div>

        <template v-else>
            <div class="ock-input-wrap">
                <HcisField
                    v-model="kata"
                    size="sm"
                    :placeholder="placeholder"
                    leading-icon="search"
                    clearable
                    @update:model-value="cariTertunda"
                    @keydown.down.prevent="fokusHasil(0)"
                />
            </div>

            <p v-if="kata.trim().length === 1" class="ock-petunjuk">
                <HcisIcon name="info-circle" size="2xs" />
                <span>Ketik minimal 2 huruf untuk mulai mencari karyawan.</span>
            </p>

            <div v-else-if="mencari" class="ock-muat">
                <HcisSkeleton v-for="n in 2" :key="n" height="2.8rem" rounded="md" />
            </div>

            <div v-else-if="kata.trim().length >= 2 && !hasil.length" class="ock-kosong">
                <HcisIcon name="person-x" size="xs" />
                <span>Tidak ada karyawan aktif yang cocok dengan <em>"{{ kata }}"</em>.</span>
            </div>

            <p v-if="!mencari && dipangkas && hasil.length" class="ock-dipangkas">
                <HcisIcon name="funnel-fill" size="2xs" />
                <span>
                    Menampilkan {{ hasil.length }} dari <strong>{{ totalCocok.toLocaleString('id-ID') }}</strong>
                    yang cocok. Persempit kata pencariannya kalau orang yang dicari belum tampak.
                </span>
            </p>

            <div v-if="!mencari && hasil.length" class="ock-hasil" role="listbox">
                <button
                    v-for="(k, idx) in hasil"
                    :key="k.kode_perusahaan + '|' + k.kode_karyawan"
                    :id="`ock-opt-${idx}`"
                    type="button"
                    class="ock-baris"
                    role="option"
                    @click="pilih(k)"
                    @keydown.down.prevent="fokusHasil(idx + 1)"
                    @keydown.up.prevent="fokusHasil(idx - 1)"
                >
                    <HcisAvatar
                        :name="k.nama || k.kode_karyawan"
                        size="xs"
                        shape="circle"
                    />
                    <div class="ock-baris-content">
                        <span class="ock-baris-nama">{{ k.nama }}</span>
                        <div class="ock-baris-meta">
                            <span class="ock-nik-pill">{{ k.kode_karyawan }}</span>
                            <span v-if="k.nama_level" class="ock-level-pill">{{ k.nama_level }}</span>
                            <span v-if="k.kode_perusahaan" class="ock-pt-pill">PT: {{ k.kode_perusahaan }}</span>
                            <span v-if="k.divisi" class="ock-divisi-pill">{{ k.divisi }}</span>
                        </div>
                    </div>
                    <HcisIcon name="plus" size="xs" class="ock-select-icon" />
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisAvatar, HcisButton, HcisField, HcisIcon, HcisSkeleton } from '@/components/ui';

defineOptions({ name: 'OrgCariKaryawan' });

defineProps({
    modelValue: { type: Object, default: null },
    placeholder: { type: String, default: 'Cari nama atau kode / NIK karyawan...' },
});

const emit = defineEmits(['update:modelValue']);

const kata = ref('');
const hasil = ref([]);
const mencari = ref(false);

/* BATAS_TAMPIL adalah plafon, dan plafon wajib dilaporkan.
   Endpointnya sudah memulangkan `total` dan `dipangkas` justru untuk ini, tetapi
   komponen ini membuangnya: mencari nama umum memberi 20 hasil tanpa satu pun tanda
   bahwa yang cocok 60, dan orang yang dicari bisa saja bukan salah satu dari 20 itu. */
const BATAS_TAMPIL = 20;
const totalCocok = ref(0);
const dipangkas = ref(false);

let jeda = null;

function cariTertunda() {
    if (jeda) clearTimeout(jeda);

    const q = kata.value.trim();

    if (q.length < 2) {
        hasil.value = [];
        totalCocok.value = 0;
        dipangkas.value = false;
        return;
    }

    jeda = setTimeout(() => void jalankan(q), 300);
}

async function jalankan(q) {
    mencari.value = true;

    try {
        const res = await fetch(
            `/system/organisasi/relasi/cari-karyawan?q=${encodeURIComponent(q)}&limit=${BATAS_TAMPIL}`,
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        );
        const isi = await res.json().catch(() => ({}));

        if (kata.value.trim() === q) {
            const hasilData = (isi?.result ?? isi?.data ?? isi) ?? {};
            hasil.value = hasilData?.baris ?? [];
            totalCocok.value = Number(hasilData?.total ?? hasil.value.length);
            dipangkas.value = Boolean(hasilData?.dipangkas);
        }
    } catch {
        hasil.value = [];
        totalCocok.value = 0;
        dipangkas.value = false;
    } finally {
        mencari.value = false;
    }
}

function pilih(k) {
    emit('update:modelValue', k);
    kata.value = '';
    hasil.value = [];
}

function ganti() {
    emit('update:modelValue', null);
    kata.value = '';
    hasil.value = [];
}

function fokusHasil(idx) {
    if (idx < 0 || idx >= hasil.value.length) return;
    const el = document.getElementById(`ock-opt-${idx}`);
    if (el) el.focus();
}
</script>

<style scoped>
.ock {
    position: relative;
    width: 100%;
}

.ock-terpilih {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.ock-terpilih-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.ock-details {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.ock-nama {
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ock-meta-line {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.ock-nik-pill {
    font-size: 0.68rem;
    font-weight: 600;
    color: #475569;
    background: #e2e8f0;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.ock-level-pill {
    font-size: 0.68rem;
    font-weight: 600;
    color: #0369a1;
    background: #e0f2fe;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.ock-pt-pill {
    font-size: 0.65rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
}

.ock-divisi-pill {
    font-size: 0.65rem;
    color: #475569;
    background: #f1f5f9;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
}

.ock-input-wrap {
    width: 100%;
}

.ock-petunjuk {
    margin: 0.35rem 0 0;
    font-size: 0.75rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Plafon hasil, dilaporkan. Daftar yang dipangkas tanpa angka terbaca sebagai lengkap. */
.ock-dipangkas {
    margin: 0.4rem 0 0;
    padding: 0.45rem 0.6rem;
    border-radius: 8px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
    font-size: 0.72rem;
    line-height: 1.35;
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
}

.ock-muat {
    margin-top: 0.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.ock-kosong {
    margin-top: 0.4rem;
    padding: 0.6rem 0.75rem;
    font-size: 0.78rem;
    color: #64748b;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.ock-hasil {
    margin-top: 0.4rem;
    max-height: 14rem;
    overflow-y: auto;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.ock-baris {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 0;
    border-bottom: 1px solid #f1f5f9;
    background: transparent;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
}

.ock-baris:last-child {
    border-bottom: 0;
}

.ock-baris:hover,
.ock-baris:focus {
    background: #f0fdf4;
    outline: none;
}

.ock-baris:hover .ock-select-icon,
.ock-baris:focus .ock-select-icon {
    color: #16a34a;
    transform: scale(1.2);
}

.ock-baris-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.ock-baris-nama {
    font-size: 0.82rem;
    font-weight: 700;
    color: #1e293b;
}

.ock-baris-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.ock-select-icon {
    color: #94a3b8;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
</style>
