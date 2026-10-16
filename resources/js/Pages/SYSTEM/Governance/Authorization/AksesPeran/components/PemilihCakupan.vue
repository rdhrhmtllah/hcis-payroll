<template>
    <div class="pc">
        <HcisTabs v-if="bolehSemua" v-model="mode" :tabs="daftarMode" variant="segmented" size="sm" class="mb-3" />

        <div v-if="mode === 'SEMUA'" class="ap-card-subtitle">
            Tanpa pembatasan: pemegang peran berlaku untuk seluruh karyawan.
        </div>

        <template v-else>
            <div class="ap-card-subtitle mb-3">
                Karyawan tercakup bila cocok dengan <b>salah satu</b> pilihan di bawah. Butuh syarat gabungan (misalnya
                divisi <i>dan</i> lokasi)? Pakai template cakupan.
            </div>

            <!-- Pengelola bercakupan terbatas: hanya bagian dari cakupannya sendiri. -->
            <div v-if="!tanpaBatas" class="d-flex flex-column gap-2">
                <HcisCheckbox
                    v-for="(c, i) in opsiTerbatas"
                    :key="`${c.jenis}-${c.nilai}`"
                    v-model="pilihTerbatas"
                    :value="i"
                    :label="c.label || c.jenis"
                />
            </div>

            <template v-else>
                <!-- Template cakupan -->
                <section v-if="template.tersedia" class="pc-bagian">
                    <div class="pc-judul">
                        <span>Template cakupan</span>
                        <HcisButton
                            v-if="template.bisa_buat && !buatBaru"
                            variant="ghost"
                            size="xs"
                            leading-icon="plus-lg"
                            label="Buat template"
                            @click="mulaiBuat"
                        />
                    </div>
                    <HcisSelect
                        v-model="pilihTemplate"
                        multiple
                        collapse-tags
                        filterable
                        clearable
                        size="sm"
                        :placeholder="templateCocok.length ? 'Pilih template…' : 'Belum ada template untuk modul ini'"
                        aria-label="Template cakupan"
                    >
                        <HcisSelectOption
                            v-for="t in templateCocok"
                            :key="t.id"
                            :value="String(t.id)"
                            :label="t.nama"
                        />
                    </HcisSelect>
                    <div v-for="t in templateTerpilih" :key="t.id" class="pc-syarat">
                        <b>{{ t.nama }}:</b> {{ teksSyarat(t) }}
                    </div>

                    <!-- Pembuat template (syarat beda jenis = DAN). -->
                    <div v-if="buatBaru" class="pc-buat">
                        <div class="pc-judul"><span>Template baru</span></div>
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-md-5">
                                <HcisField
                                    v-model="baru.nama"
                                    label="Nama template"
                                    placeholder="Contoh: Payroll Jakarta"
                                    size="sm"
                                />
                            </div>
                            <div class="col-12 col-md-7">
                                <HcisField
                                    v-model="baru.keterangan"
                                    label="Keterangan (opsional)"
                                    placeholder="Untuk apa template ini dipakai"
                                    size="sm"
                                />
                            </div>
                        </div>
                        <div class="ap-card-subtitle mb-2">
                            Karyawan tercakup bila memenuhi <b>semua</b> jenis yang diisi; dalam satu jenis cukup salah
                            satu nilai.
                        </div>
                        <HcisCheckbox v-model="baru.phl" label="Hanya PHL" class="mb-2" />
                        <IsianNilaiCakupan v-model="baru.nilai" :opsi="akses.nilai_cakupan" />
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <HcisButton variant="secondary" size="xs" label="Batal" @click="buatBaru = false" />
                            <HcisButton
                                variant="primary"
                                size="xs"
                                label="Simpan template"
                                :loading="menyimpan"
                                :disabled="baru.nama.trim().length < 3 || !syaratBaru.length"
                                @click="simpanBaru"
                            />
                        </div>
                    </div>
                </section>

                <!-- Relatif terhadap pemegang -->
                <section v-if="opsiPemegang.length" class="pc-bagian">
                    <div class="pc-judul"><span>Mengikuti data pemegang</span></div>
                    <div class="d-flex flex-wrap gap-3">
                        <HcisCheckbox
                            v-for="c in opsiPemegang"
                            :key="c.jenis"
                            v-model="pilihPemegang"
                            :value="c.jenis"
                            :label="c.label || c.jenis"
                        />
                    </div>
                </section>

                <!-- Nilai tertentu -->
                <section class="pc-bagian">
                    <div class="pc-judul"><span>Nilai tertentu</span></div>
                    <IsianNilaiCakupan v-model="pilihNilai" :opsi="akses.nilai_cakupan" />
                </section>
            </template>

            <HcisAlert
                v-if="!hasil.length"
                variant="warning"
                appearance="subtle"
                class="mt-3"
                description="Belum ada cakupan dipilih. Pilih minimal satu, atau kembali ke Semua karyawan."
            />
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisButton,
    HcisCheckbox,
    HcisField,
    HcisSelect,
    HcisSelectOption,
    HcisTabs,
    hcisMessage,
} from '@/components/ui';
import { aksesPeranApi, pesanGalat } from '../api/aksesPeranApi.js';
import IsianNilaiCakupan from './IsianNilaiCakupan.vue';

/**
 * Cakupan penugasan: banyak baris {jenis, nilai} (cocok salah satu). Daftar kosong = semua karyawan.
 * Pilihan menyesuaikan wewenang: pengelola terbatas hanya memilih dari cakupannya sendiri.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    /** detail pengguna: cakupan_tersedia, nilai_cakupan, template_cakupan */
    akses: { type: Object, required: true },
    /** peran terpilih (untuk menyaring cakupan per modul) */
    peran: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'valid']);

const NILAI = ['DIVISI', 'DEPARTEMEN', 'LOKASI', 'LEVEL', 'JABATAN', 'KARYAWAN'];
const nilaiKosong = () => Object.fromEntries(NILAI.map((k) => [k, []]));

// Cakupan khusus modul hanya berlaku bila semua peran terpilih dari modul itu.
const cocokModul = (modul) => !modul || (props.peran.length > 0 && props.peran.every((p) => p.modul === modul));
const opsi = computed(() => (props.akses.cakupan_tersedia || []).filter((c) => cocokModul(c.modul)));
const bolehSemua = computed(() => opsi.value.some((c) => c.jenis === 'SEMUA'));
const tanpaBatas = computed(() => Object.keys(props.akses.nilai_cakupan || {}).length > 0 && bolehSemua.value);
const opsiTerbatas = computed(() => opsi.value.filter((c) => c.jenis !== 'SEMUA'));
const opsiPemegang = computed(() => opsi.value.filter((c) => !['SEMUA', 'KARYAWAN'].includes(c.jenis) && !c.nilai));

const daftarMode = [
    { key: 'SEMUA', label: 'Semua karyawan', icon: 'people' },
    { key: 'BATAS', label: 'Dibatasi', icon: 'funnel' },
];
const mode = ref('SEMUA');
const pilihTerbatas = ref([]);
const pilihPemegang = ref([]);
const pilihNilai = ref(nilaiKosong());
const pilihTemplate = ref([]);

/* ── Template ── */
const template = reactive({ tersedia: false, bisa_buat: false, data: [] });
const templateCocok = computed(() => template.data.filter((t) => cocokModul(t.modul)));
const templateTerpilih = computed(() => template.data.filter((t) => pilihTemplate.value.includes(String(t.id))));
const teksSyarat = (t) => {
    const per = {};
    for (const s of t.syarat || []) (per[s.jenis] ||= []).push(s.label || s.nilai || s.jenis);
    return Object.values(per)
        .map((l) => l.join(' atau '))
        .join(' — dan — ');
};
async function muatTemplate() {
    try {
        Object.assign(template, await aksesPeranApi.templateCakupan());
    } catch {
        /* template tidak wajib */
    }
}

const buatBaru = ref(false);
const menyimpan = ref(false);
const baru = reactive({ nama: '', keterangan: '', phl: false, nilai: nilaiKosong() });
const syaratBaru = computed(() => [
    ...(baru.phl ? [{ jenis: 'PHL', nilai: null }] : []),
    ...NILAI.flatMap((k) => (baru.nilai[k] || []).map((n) => ({ jenis: k, nilai: String(n) }))),
]);
function mulaiBuat() {
    Object.assign(baru, { nama: '', keterangan: '', phl: false, nilai: nilaiKosong() });
    buatBaru.value = true;
}
async function simpanBaru() {
    menyimpan.value = true;
    try {
        const r = await aksesPeranApi.simpanTemplateCakupan({
            nama: baru.nama.trim(),
            keterangan: baru.keterangan.trim() || null,
            syarat: syaratBaru.value,
        });
        await muatTemplate();
        pilihTemplate.value = [...pilihTemplate.value, String(r.id)];
        buatBaru.value = false;
        hcisMessage.success('Template dibuat dan dipilih.');
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Gagal menyimpan template.'));
    } finally {
        menyimpan.value = false;
    }
}

/* ── Hasil: baris cakupan untuk server ── */
const hasil = computed(() => {
    if (mode.value === 'SEMUA') return [];
    if (!tanpaBatas.value) return pilihTerbatas.value.map((i) => opsiTerbatas.value[i]).filter(Boolean);
    return [
        ...pilihTemplate.value.map((id) => ({ jenis: 'TEMPLATE', nilai: id })),
        ...pilihPemegang.value.map((j) => ({ jenis: j, nilai: null })),
        ...NILAI.flatMap((k) => (pilihNilai.value[k] || []).map((n) => ({ jenis: k, nilai: String(n) }))),
    ];
});
watch(
    hasil,
    (v) => {
        emit(
            'update:modelValue',
            v.map(({ jenis, nilai }) => ({ jenis, nilai })),
        );
        emit('valid', mode.value === 'SEMUA' || v.length > 0);
    },
    { immediate: true, deep: true },
);

// Pengelola terbatas tak punya pilihan "semua".
watch(
    bolehSemua,
    (b) => {
        if (!b) mode.value = 'BATAS';
    },
    { immediate: true },
);
watch(
    tanpaBatas,
    (t) => {
        if (t && !template.data.length) muatTemplate();
    },
    { immediate: true },
);
</script>

<style scoped>
.pc-bagian {
    padding-top: var(--hcis-space-3, 0.75rem);
    margin-top: var(--hcis-space-3, 0.75rem);
    border-top: 1px solid var(--hcis-border-subtle);
}
.pc-bagian:first-of-type {
    padding-top: 0;
    margin-top: 0;
    border-top: 0;
}
.pc-judul {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--hcis-space-2, 0.5rem);
    margin-bottom: var(--hcis-space-2, 0.5rem);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
}
.pc-syarat {
    margin-top: var(--hcis-space-1, 0.25rem);
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}
.pc-buat {
    margin-top: var(--hcis-space-3, 0.75rem);
    padding: var(--hcis-space-3, 0.75rem);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-lg, 10px);
    background: var(--hcis-surface-soft);
}
.ap-card-subtitle {
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
    line-height: 1.45;
}
</style>
