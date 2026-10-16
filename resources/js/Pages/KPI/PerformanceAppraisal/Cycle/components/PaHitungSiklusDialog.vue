<template>
    <HcisDialog
        :model-value="modelValue"
        title="Hitung hasil siklus"
        kicker="ADMINISTRATOR PA · PERHITUNGAN MASSAL"
        :description="cycle ? `${cycle.Nama_Cycle} · ${cycle.Kode_Cycle}` : ''"
        icon="calculator-fill"
        icon-variant="glass"
        sentiment="brand"
        size="lg"
        variant="glass"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-hitung">
            <HcisResultState
                v-if="error"
                :status="errorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="errorStatus === 403 ? 'Perhitungan tidak tersedia' : 'Pratinjau perhitungan gagal dimuat'"
                :description="error"
                :show-retry="errorStatus !== 403"
                @retry="$emit('retry')"
            />

            <div v-else-if="loading" class="pa-hitung__loading" aria-live="polite">
                <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="brand" />
                <span>Menghitung berapa peserta yang akan tersentuh…</span>
            </div>

            <template v-else-if="preview">
                <!--
                    ANGKANYA LEBIH DULU, pilihannya sesudah. Dua pilihan di bawah hanya
                    bisa dipilih dengan sadar kalau orang tahu isi siklusnya; menaruh
                    pilihannya di atas memaksa memilih dulu lalu mencari alasannya.
                -->
                <section class="pa-hitung__isi" aria-label="Isi siklus saat ini">
                    <span class="pa-hitung__eyebrow">ISI SIKLUS SAAT INI</span>
                    <div class="pa-hitung__isi-grid">
                        <div>
                            <span>Belum dihitung · penilai lengkap</span>
                            <strong class="is-brand">{{ angka(preview.siap_hitung_pertama) }}</strong>
                        </div>
                        <div>
                            <span>Belum dihitung · penilai belum lengkap</span>
                            <strong>{{ angka(preview.belum_lengkap) }}</strong>
                        </div>
                        <div>
                            <span>Sudah dihitung</span>
                            <strong>{{ angka(preview.sudah_dihitung) }}</strong>
                        </div>
                        <div>
                            <span>Tertahan</span>
                            <strong class="is-warning">{{ angka(preview.tertahan) }}</strong>
                        </div>
                        <div>
                            <span>Sudah dikalibrasi admin</span>
                            <strong class="is-teal">{{ angka(preview.terkalibrasi) }}</strong>
                        </div>
                        <div>
                            <span>Sudah terbit</span>
                            <strong class="is-muted">{{ angka(preview.terbit) }}</strong>
                        </div>
                    </div>
                    <p class="pa-hitung__isi-total">
                        Total {{ angka(preview.total) }} peserta diikutkan pada siklus ini.
                    </p>
                </section>

                <section class="pa-hitung__pilihan" aria-label="Lingkup perhitungan">
                    <span class="pa-hitung__eyebrow">PILIH LINGKUPNYA</span>

                    <HcisRadio
                        v-model="lingkupTerpilih"
                        value="BELUM"
                        name="pa-lingkup-hitung"
                        variant="card"
                        color="brand"
                        :disabled="acting"
                    >
                        <span class="pa-hitung__opsi-judul">
                            Hanya yang belum dihitung
                            <HcisBadge :label="`${angka(preview.siap_hitung_pertama)} peserta`" variant="primary" appearance="subtle" size="xs" />
                        </span>
                        <template #description>
                            <span class="pa-hitung__opsi-teks">
                                Menyusulkan peserta yang penilainya sudah lengkap tetapi belum pernah
                                dihitung. Hasil yang sudah ditinjau, dikalibrasi, atau terbit
                                <strong>tidak disentuh sama sekali</strong>.
                            </span>
                        </template>
                    </HcisRadio>

                    <HcisRadio
                        v-model="lingkupTerpilih"
                        value="SEMUA"
                        name="pa-lingkup-hitung"
                        variant="card"
                        color="warning"
                        :disabled="acting"
                    >
                        <span class="pa-hitung__opsi-judul">
                            Hitung ulang semua peserta
                            <HcisBadge :label="`${angka(preview.total)} peserta`" variant="warning" appearance="subtle" size="xs" />
                        </span>
                        <template #description>
                            <span class="pa-hitung__opsi-teks">
                                Menghitung ulang seluruh siklus dari awal, termasuk yang sudah
                                dihitung dan dikalibrasi. Dipakai sesudah bobot, skala nilai, atau
                                data KPI berubah di tengah jalan.
                            </span>
                        </template>
                    </HcisRadio>
                </section>

                <!--
                    AKIBATNYA DISEBUTKAN APA ADANYA, bukan ditakut-takuti dan bukan
                    disembunyikan. Kalibrasi TIDAK hilang - sejak 7 September 2026 grade
                    hasil forum dipertahankan - dan menuliskan bahwa ia hilang akan membuat
                    admin menghindari tombol yang sebetulnya aman. Yang benar-benar terjadi
                    ada tiga, dan ketiganya di bawah ini.
                -->
                <section
                    v-if="lingkupTerpilih === 'SEMUA' && preview.terkalibrasi > 0"
                    class="pa-hitung__akibat"
                    aria-label="Akibat pada hasil yang sudah dikalibrasi"
                >
                    <div class="pa-hitung__akibat-icon">
                        <HcisIcon name="exclamation-triangle-fill" size="md" />
                    </div>
                    <div class="pa-hitung__akibat-isi">
                        <h4>{{ angka(preview.terkalibrasi) }} hasil sudah dikalibrasi admin</h4>
                        <ul>
                            <li>
                                <strong>Grade hasil kalibrasi tetap dipertahankan.</strong> Yang
                                diperbarui adalah grade sistem — angka mesinnya.
                            </li>
                            <li>
                                Bila angkanya bergeser, hasil itu ditandai
                                <strong>“kalibrasi perlu ditinjau”</strong> dan kembali menjadi
                                pekerjaan Anda.
                            </li>
                            <li>
                                Bila perhitungan ulang membuatnya <strong>TERTAHAN</strong> —
                                misalnya KPI-nya dicabut persetujuannya — kalibrasinya
                                <strong>dilepas</strong>, karena hasil tertahan tidak punya grade
                                sama sekali. Pelepasannya tercatat di riwayat kalibrasi berikut
                                sebabnya.
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-if="lingkupTerpilih === 'SEMUA' && preview.terbit > 0" class="pa-hitung__catatan">
                    <HcisIcon name="shield-lock-fill" size="sm" />
                    <span>
                        {{ angka(preview.terbit) }} hasil yang sudah <strong>terbit</strong> akan
                        dilewati — hasil yang sudah dibaca karyawan tidak berubah diam-diam. Untuk
                        mengubahnya, tarik dulu penerbitannya per orang.
                    </span>
                </section>

                <section v-if="lingkupTerpilih === 'BELUM' && preview.belum_lengkap > 0" class="pa-hitung__catatan">
                    <HcisIcon name="hourglass-split" size="sm" />
                    <span>
                        {{ angka(preview.belum_lengkap) }} peserta dilewati karena penilainya belum
                        lengkap atau seluruh penugasannya dibatalkan. Mereka tetap muncul di tab
                        Pengisian.
                    </span>
                </section>

                <section
                    v-if="lingkupTerpilih === 'SEMUA'"
                    class="pa-hitung__setuju"
                    aria-label="Konfirmasi hitung ulang semua"
                >
                    <HcisCheckbox
                        v-model="disadari"
                        color="warning"
                        :disabled="acting"
                        label="Saya mengerti seluruh peserta akan dihitung ulang"
                        :description="preview.terkalibrasi > 0
                            ? `Termasuk ${angka(preview.terkalibrasi)} hasil yang sudah saya kalibrasi, yang grade-nya dipertahankan tetapi ditandai perlu ditinjau.`
                            : 'Belum ada hasil yang dikalibrasi pada siklus ini.'"
                    />
                </section>

                <p v-if="lingkupTerpilih === 'SEMUA' && preview.total > 200" class="pa-hitung__durasi">
                    <HcisIcon name="clock-history" size="2xs" />
                    Perkiraan {{ perkiraanMenit }} menit untuk {{ angka(preview.total) }} peserta.
                    Jangan tutup tab ini selama berjalan; untuk siklus sebesar ini
                    <code>php artisan pa:hitung-siklus</code> lebih tepat.
                </p>
            </template>
        </div>

        <template #footer-start>
            <span class="pa-dialog-hint">
                <HcisIcon name="database-check" size="2xs" /> Angka pratinjau dibaca dari server
            </span>
        </template>

        <template #footer>
            <div class="pa-hitung__aksi">
                <HcisButton
                    label="Batal"
                    variant="ghost"
                    size="sm"
                    :disabled="acting"
                    @click="$emit('update:modelValue', false)"
                />
                <HcisButton
                    :label="labelTombol"
                    :variant="lingkupTerpilih === 'SEMUA' ? 'warning' : 'primary'"
                    size="sm"
                    leading-icon="calculator-fill"
                    :loading="acting"
                    :disabled="!bolehJalan"
                    @click="$emit('jalankan', lingkupTerpilih)"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
/*
 | KONFIRMASI YANG MENYEBUT ANGKA.
 |
 | Sampai 7 September 2026 tombol "Hitung" langsung berjalan. Ia satu-satunya tombol di modul
 | ini yang menjalankan pekerjaan menit-menit atas ratusan orang sekaligus DAN menyentuh hasil
 | yang sudah ditinjau admin, dan ia tidak menanyakan apa pun.
 |
 | Dialog konfirmasi yang hanya bertanya "yakin?" tidak memperbaiki itu. Orang belajar menekan
 | "ya" tanpa membaca dalam dua-tiga kali pemakaian, dan sesudahnya konfirmasinya justru lebih
 | buruk daripada tidak ada - ia memberi rasa aman tanpa memberi informasi. Yang membuat
 | keputusan bisa diambil adalah ANGKANYA: berapa yang tertinggal, berapa yang sudah
 | dikalibrasi, berapa yang akan dilewati.
 |
 | Karena itu urutannya: isi siklus dulu, pilihan sesudahnya, akibat khusus muncul hanya untuk
 | pilihan yang memang punya akibat itu.
 */
import { computed, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCheckbox,
    HcisDialog,
    HcisIcon,
    HcisRadio,
    HcisResultState,
} from '@/components/ui';

defineOptions({ name: 'PaHitungSiklusDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycle: { type: Object, default: null },
    preview: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    acting: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'retry', 'jalankan']);

/* Bakunya BELUM, bukan SEMUA. Pilihan yang lebih sempit dan tidak bisa merugikan siapa pun
   adalah yang lebih sering benar; yang menyentuh hasil kalibrasi harus dipilih sendiri. */
const lingkupTerpilih = ref('BELUM');

const disadari = ref(false);

/* Centangnya dilepas setiap dialog dibuka DAN setiap kali lingkupnya berpindah. Centang yang
   bertahan dari pembukaan sebelumnya berarti tombolnya sudah aktif sebelum orang membaca
   apa pun - persis kebalikan dari yang dituju konfirmasi ini. */
watch(() => props.modelValue, (terbuka) => {
    if (!terbuka) return;

    lingkupTerpilih.value = 'BELUM';
    disadari.value = false;
});

watch(lingkupTerpilih, () => {
    disadari.value = false;
});

const bolehJalan = computed(() => {
    if (props.acting || props.loading || !props.preview) return false;

    if (lingkupTerpilih.value === 'SEMUA') return disadari.value;

    // Menjalankan lingkup BELUM tanpa satu pun peserta di dalamnya hanya menghasilkan
    // laporan "0 diproses" - jawaban yang sudah terbaca di layar sebelum ditekan.
    return Number(props.preview.siap_hitung_pertama || 0) > 0;
});

const labelTombol = computed(() => {
    if (!props.preview) return 'Jalankan perhitungan';

    return lingkupTerpilih.value === 'SEMUA'
        ? `Hitung ulang ${angka(props.preview.total)} peserta`
        : `Hitung ${angka(props.preview.siap_hitung_pertama)} peserta`;
});

/* ±0,5 detik per peserta pada basis data pengembangan. Dibulatkan ke atas: perkiraan yang
   meleset ke arah "lebih cepat dari yang dijanjikan" tidak membuat siapa pun menutup tab. */
const perkiraanMenit = computed(
    () => Math.max(1, Math.ceil((Number(props.preview?.total || 0) * 0.5) / 60)),
);

function angka(nilai) {
    return Number(nilai || 0).toLocaleString('id-ID');
}
</script>

<style scoped>
.pa-hitung {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.pa-hitung__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 12rem;
    color: var(--hcis-text-muted, #64748b);
}
.pa-hitung__eyebrow {
    display: block;
    margin-bottom: 0.55rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
}

/* Isi siklus */
.pa-hitung__isi-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    min-width: 0;
}
.pa-hitung__isi-grid > div {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--hcis-border-subtle, rgba(148, 163, 184, 0.26));
    border-radius: var(--hcis-radius-md, 10px);
    background: var(--hcis-surface-subtle, rgba(248, 250, 252, 0.7));
}
.pa-hitung__isi-grid span {
    font-size: 0.68rem;
    line-height: 1.3;
    color: var(--hcis-text-muted, #64748b);
}
.pa-hitung__isi-grid strong {
    font-size: 1.15rem;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-text-strong, #0f172a);
}
.pa-hitung__isi-grid strong.is-brand { color: #1d4ed8; }
.pa-hitung__isi-grid strong.is-warning { color: #b45309; }
.pa-hitung__isi-grid strong.is-teal { color: #0f766e; }
.pa-hitung__isi-grid strong.is-muted { color: var(--hcis-text-muted, #64748b); }
.pa-hitung__isi-total {
    margin: 0.55rem 0 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Pilihan lingkup */
.pa-hitung__pilihan {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 0;
}
.pa-hitung__opsi-judul {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    font-weight: 600;
}
.pa-hitung__opsi-teks {
    display: block;
    line-height: 1.5;
}

/* Akibat pada hasil terkalibrasi */
.pa-hitung__akibat {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(255, 251, 235, 0.9), rgba(255, 255, 255, 0.8));
}
.pa-hitung__akibat-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    color: #b45309;
    border-radius: 0.7rem;
    background: rgba(245, 158, 11, 0.15);
}
.pa-hitung__akibat-isi {
    min-width: 0;
}
.pa-hitung__akibat-isi h4 {
    margin: 0 0 0.4rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #92400e;
}
.pa-hitung__akibat-isi ul {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    margin: 0;
    padding-left: 1.05rem;
}
.pa-hitung__akibat-isi li {
    font-size: 0.76rem;
    line-height: 1.5;
    color: var(--hcis-text-base, #334155);
}

/* Catatan tenang */
.pa-hitung__catatan {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    min-width: 0;
    padding: 0.65rem 0.8rem;
    font-size: 0.74rem;
    line-height: 1.5;
    color: var(--hcis-text-muted, #64748b);
    border: 1px dashed var(--hcis-border-subtle, rgba(148, 163, 184, 0.34));
    border-radius: var(--hcis-radius-md, 10px);
}

.pa-hitung__setuju {
    padding: 0.75rem 0.85rem;
    border: 1px solid rgba(245, 158, 11, 0.28);
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 251, 235, 0.6);
}

.pa-hitung__durasi {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}
.pa-hitung__durasi code {
    padding: 0.1rem 0.3rem;
    font-size: 0.68rem;
    border-radius: 0.3rem;
    background: rgba(148, 163, 184, 0.16);
}

.pa-hitung__aksi {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}

@media (max-width: 720px) {
    .pa-hitung__isi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .pa-hitung {
        gap: 0.75rem;
    }
    .pa-hitung__isi {
        padding: 0.65rem 0.75rem;
        border-radius: 10px;
    }
    .pa-hitung__isi-grid {
        gap: 0.35rem;
    }
    .pa-hitung__isi-grid div {
        padding: 0.45rem 0.55rem;
        border-radius: 6px;
    }
    .pa-hitung__isi-grid span {
        font-size: 0.64rem;
    }
    .pa-hitung__isi-grid strong {
        font-size: 1.05rem;
    }
    .pa-hitung__akibat {
        padding: 0.65rem 0.75rem;
        gap: 0.5rem;
        border-radius: 10px;
    }
    .pa-hitung__akibat-icon {
        width: 1.85rem;
        height: 1.85rem;
    }
    .pa-hitung__akibat-isi h4 {
        font-size: 0.8rem;
    }
    .pa-hitung__akibat-isi li {
        font-size: 0.72rem;
    }
    .pa-hitung__opsi-judul {
        font-size: 0.82rem;
    }
    .pa-hitung__opsi-teks {
        font-size: 0.72rem;
    }
    .pa-hitung__aksi {
        flex-direction: column;
        width: 100%;
    }
    .pa-hitung__aksi :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}
</style>
