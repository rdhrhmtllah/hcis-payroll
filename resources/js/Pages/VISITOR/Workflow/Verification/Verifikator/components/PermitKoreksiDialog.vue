<template>
    <HcisDialog
        :model-value="modelValue"
        title="Koreksi Data Pengajuan"
        description="Ubah data yang tidak sesuai hasil pemeriksaan. Setiap perubahan dicatat beserta alasannya."
        icon="pencil-square"
        footer-align="between"
        sticky-footer
        @update:model-value="!menyimpan && $emit('update:modelValue', $event)"
    >
        <HcisAlert v-if="galat" variant="danger" class="mb-3">{{ galat }}</HcisAlert>

        <!-- Kerangka form selama master dimuat: bentuknya sama dengan form aslinya,
             jadi dialog tidak melompat saat isinya datang. -->
        <div v-if="memuatMaster" class="koreksi-skeleton" aria-busy="true" aria-label="Memuat data koreksi">
            <div v-for="n in 3" :key="n" class="koreksi-skeleton__field">
                <HcisSkeleton width="30%" height="0.75rem" />
                <HcisSkeleton height="2.25rem" rounded="md" />
            </div>
            <div class="koreksi-skeleton__row">
                <div class="koreksi-skeleton__field">
                    <HcisSkeleton width="40%" height="0.75rem" />
                    <HcisSkeleton height="2.25rem" rounded="md" />
                </div>
                <div class="koreksi-skeleton__field">
                    <HcisSkeleton width="40%" height="0.75rem" />
                    <HcisSkeleton height="2.25rem" rounded="md" />
                </div>
            </div>
            <div class="koreksi-skeleton__field">
                <HcisSkeleton width="25%" height="0.75rem" />
                <HcisSkeleton height="4.5rem" rounded="md" />
            </div>
        </div>
        <div v-else class="koreksi-form" :class="{ 'is-menyimpan': menyimpan }">
            <!-- Selama request simpan berjalan: form dikunci dan diberi lapisan
                 berputar, supaya tidak ada isian yang berubah di tengah pengiriman. -->
            <div v-if="menyimpan" class="koreksi-overlay" role="status" aria-live="polite">
                <span class="koreksi-spinner" aria-hidden="true" />
                <span class="koreksi-overlay__text">Menyimpan koreksi…</span>
            </div>
            <fieldset :disabled="menyimpan" class="koreksi-fieldset d-flex flex-column gap-3">
            <HcisField id="koreksi-kategori" label="Jenis Permit" required :hint="petunjuk('id_kategori_permit')">
                <HcisSelect id="koreksi-kategori-select" v-model="form.id_kategori_permit" :clearable="false" @update:model-value="muatPratinjau">
                    <HcisSelectOption v-for="k in master.kategoris" :key="k.Id_Kategori_Permit" :value="String(k.Id_Kategori_Permit)" :label="k.Nama_Kategori" />
                </HcisSelect>
            </HcisField>
            <!-- Cakupan akses jenis permit terpilih: dasar verifikator memindahkan zona. -->
            <div v-if="kategoriTerpilih" class="koreksi-kategori-info">
                <span class="koreksi-kategori-info__dot" :style="{ backgroundColor: kategoriTerpilih.Kode_Warna_UI || '#6366f1' }" />
                <div>
                    <strong>{{ kategoriTerpilih.Nama_Kategori }}</strong>
                    <span class="text-slate-600">
                        — {{ kategoriTerpilih.Deskripsi_Akses || 'Belum ada deskripsi akses untuk jenis permit ini di master.' }}
                    </span>
                </div>
            </div>

            <HcisPlateInput id="koreksi-plat" v-model="form.no_plat" :prefix-options="master.plat_codes" label="Nomor Plat Kendaraan" :hint="petunjuk('no_plat')" required />

            <HcisField id="koreksi-model" label="Merek / Model" required :hint="petunjuk('id_vehicle_model') || 'Jenis kendaraan mengikuti model yang dipilih.'">
                <HcisTreeSelect
                    :model-value="form.id_vehicle_model || ''"
                    :data="pohon"
                    :clearable="false"
                    filterable
                    @update:model-value="form.id_vehicle_model = $event || null; muatPratinjau()"
                />
            </HcisField>

            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <HcisField id="koreksi-warna" label="Warna" required :hint="petunjuk('id_vehicle_color')">
                        <HcisSelect id="koreksi-warna-select" v-model="form.id_vehicle_color" :clearable="false" filterable>
                            <HcisSelectOption v-for="w in master.colors" :key="w.Id_Master_Color" :value="String(w.Id_Master_Color)" :label="w.Nama_Warna" />
                        </HcisSelect>
                    </HcisField>
                </div>
                <div class="col-12 col-sm-6">
                    <HcisField id="koreksi-tahun" label="Tahun Pembuatan" required :hint="petunjuk('tahun_kendaraan')">
                        <HcisSelect id="koreksi-tahun-select" v-model="form.tahun_kendaraan" :clearable="false" filterable>
                            <HcisSelectOption v-for="th in tahun" :key="th" :value="th" :label="String(th)" />
                        </HcisSelect>
                    </HcisField>
                </div>
            </div>

            <HcisTextarea id="koreksi-keterangan" v-model="form.keterangan_pengajuan" label="Keterangan Pengajuan" :maxlength="500" :hint="petunjuk('keterangan_pengajuan')" />

            <div v-if="memuatPratinjau" class="koreksi-pratinjau-loading" role="status">
                <span class="koreksi-spinner koreksi-spinner--sm" aria-hidden="true" />
                Memeriksa dampak ke checklist…
            </div>
            <HcisAlert v-else-if="jawabanTerbuang > 0 || pratinjau?.ditambah?.length" variant="warning">
                Checklist akan disesuaikan: {{ pratinjau.dibuang.length }} pertanyaan dihapus
                <template v-if="jawabanTerbuang">({{ jawabanTerbuang }} sudah dijawab)</template>,
                {{ pratinjau.ditambah.length }} pertanyaan baru ditambahkan.
            </HcisAlert>

            <HcisTextarea id="koreksi-alasan" v-model="form.alasan" label="Alasan Koreksi" :maxlength="500" required placeholder="Contoh: kendaraan hanya parkir, bukan masuk area boiler." />
            </fieldset>
        </div>

        <template #footer>
            <div class="koreksi-footer">
                <!-- Ringkasan di sisi kiri: verifikator tahu apa yang akan disimpan
                     dan kenapa tombolnya belum aktif, tanpa menggulir form. -->
                <p class="koreksi-footer__ringkas" :class="{ 'is-siap': bisaSimpan }">
                    <template v-if="memuatMaster">Memuat data…</template>
                    <template v-else-if="!perubahan.length">Belum ada data yang diubah</template>
                    <template v-else>
                        <strong>{{ perubahan.length }} perubahan</strong>
                        <span class="koreksi-footer__daftar">{{ perubahan.join(', ') }}</span>
                        <span v-if="!bisaSimpan" class="koreksi-footer__kurang">isi alasan</span>
                    </template>
                </p>
                <div class="koreksi-footer__aksi">
                    <HcisButton
                        variant="outline"
                        label="Batal"
                        :disabled="menyimpan"
                        @click="$emit('update:modelValue', false)"
                    />
                    <HcisButton
                        leading-icon="check2"
                        :label="menyimpan ? 'Menyimpan...' : 'Simpan Koreksi'"
                        :loading="menyimpan"
                        :disabled="!bisaSimpan || menyimpan || memuatMaster"
                        @click="simpan"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import { HcisAlert, HcisButton, HcisDialog, HcisField, HcisPlateInput, HcisSelect, HcisSelectOption, HcisSkeleton, HcisTextarea, HcisTreeSelect } from '@/components/ui';
import { daftarTahun, pohonModel } from './masterKendaraan';

const LABEL = {
    id_kategori_permit: 'Jenis permit',
    no_plat: 'Plat nomor',
    id_vehicle_model: 'Model kendaraan',
    id_vehicle_color: 'Warna',
    tahun_kendaraan: 'Tahun',
    keterangan_pengajuan: 'Keterangan',
};

const normal = (v) => String(v ?? '').trim().replace(/\s+/g, ' ').toUpperCase();

// Sama dengan server: keterangan hanya dipangkas tepinya, huruf besar/kecil tetap berarti.
const normalField = (kunci, v) => (kunci === 'keterangan_pengajuan' ? String(v ?? '').trim() : normal(v));

export default {
    name: 'PermitKoreksiDialog',
    components: { HcisAlert, HcisButton, HcisDialog, HcisField, HcisPlateInput, HcisSelect, HcisSelectOption, HcisSkeleton, HcisTextarea, HcisTreeSelect },
    props: {
        modelValue: { type: Boolean, default: false },
        permit: { type: Object, required: true },
    },
    emits: ['update:modelValue', 'tersimpan'],
    data() {
        return {
            master: { plat_codes: [], vehicle_types: [], models: [], colors: [], kategoris: [] },
            memuatMaster: false,
            awal: {},
            form: {},
            pratinjau: null,
            memuatPratinjau: false,
            menyimpan: false,
            galat: '',
        };
    },
    computed: {
        pohon() {
            return pohonModel(this.master.models, this.master.vehicle_types);
        },
        tahun() {
            return daftarTahun();
        },
        perubahan() {
            return Object.keys(LABEL)
                .filter((k) => normalField(k, this.form[k]) !== normalField(k, this.awal[k]))
                .map((k) => LABEL[k]);
        },
        bisaSimpan() {
            return this.perubahan.length > 0 && String(this.form.alasan ?? '').trim() !== '';
        },
        kategoriTerpilih() {
            return this.master.kategoris.find((k) => String(k.Id_Kategori_Permit) === String(this.form.id_kategori_permit)) || null;
        },
        jawabanTerbuang() {
            return (this.pratinjau?.dibuang || []).filter((d) => d.terjawab).length;
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(buka) {
                if (buka) this.mulai();
            },
        },
    },
    methods: {
        async mulai() {
            const p = this.permit;
            this.awal = {
                id_kategori_permit: p.Id_Master_Kategori_Permit ? String(p.Id_Master_Kategori_Permit) : null,
                no_plat: p.No_Plat_Kendaraan || '',
                id_vehicle_model: p.Id_Master_Model ? String(p.Id_Master_Model) : null,
                id_vehicle_color: p.Id_Master_Warna ? String(p.Id_Master_Warna) : null,
                tahun_kendaraan: p.Tahun_Kendaraan ? Number(p.Tahun_Kendaraan) : null,
                keterangan_pengajuan: p.Keterangan_Pengajuan || '',
            };
            this.form = { ...this.awal, alasan: '' };
            this.pratinjau = null;
            this.galat = '';
            this.memuatMaster = true;
            try {
                const { data } = await axios.get('/verifikator-permit-koreksi-master');
                if (data?.success) this.master = { ...this.master, ...data.result };
            } catch (e) {
                this.galat = e.response?.data?.message || 'Master data gagal dimuat.';
            } finally {
                this.memuatMaster = false;
            }
        },
        petunjuk(kunci) {
            if (normalField(kunci, this.form[kunci]) === normalField(kunci, this.awal[kunci])) return '';
            return `Sebelumnya: ${this.tampilanAwal(kunci)}`;
        },
        tampilanAwal(kunci) {
            const v = this.awal[kunci];
            const cari = (daftar, idKey, namaKey) => daftar.find((x) => String(x[idKey]) === String(v))?.[namaKey];
            if (kunci === 'id_kategori_permit') return cari(this.master.kategoris, 'Id_Kategori_Permit', 'Nama_Kategori') || this.permit.Nama_Kategori || '-';
            if (kunci === 'id_vehicle_model') return cari(this.master.models, 'Id_Vehicle_Model', 'Nama_Model_Kendaraan') || '-';
            if (kunci === 'id_vehicle_color') return cari(this.master.colors, 'Id_Master_Color', 'Nama_Warna') || '-';
            return v === null || v === '' ? '-' : String(v);
        },
        async muatPratinjau() {
            const berubah = normal(this.form.id_kategori_permit) !== normal(this.awal.id_kategori_permit)
                || normal(this.form.id_vehicle_model) !== normal(this.awal.id_vehicle_model);
            if (!berubah || !this.form.id_kategori_permit || !this.form.id_vehicle_model) {
                this.pratinjau = null;
                return;
            }
            this.memuatPratinjau = true;
            try {
                const { data } = await axios.get(`/verifikator-permit/${this.permit.No_Transaksi}/koreksi/pratinjau-checklist`, {
                    params: { id_kategori_permit: this.form.id_kategori_permit, id_vehicle_model: this.form.id_vehicle_model },
                });
                this.pratinjau = data?.success ? data.result : null;
            } catch (e) {
                this.pratinjau = null;
            } finally {
                this.memuatPratinjau = false;
            }
        },
        async simpan() {
            if (!this.bisaSimpan) return;
            this.menyimpan = true;
            this.galat = '';
            try {
                const { alasan, ...isian } = this.form;
                const { data } = await axios.post(`/verifikator-permit/${this.permit.No_Transaksi}/koreksi`, {
                    ...isian,
                    alasan: alasan.trim(),
                    // Dasar server mengabaikan field yang tidak disentuh dan menolak
                    // bila field yang disentuh sudah diubah verifikator lain.
                    nilai_awal: this.awal,
                });
                if (!data?.success) {
                    this.galat = data?.message || 'Koreksi gagal disimpan.';
                    return;
                }
                this.$emit('tersimpan', data.result);
                this.$emit('update:modelValue', false);
            } catch (e) {
                this.galat = e.response?.data?.message || 'Koreksi gagal disimpan. Periksa koneksi lalu coba lagi.';
            } finally {
                this.menyimpan = false;
            }
        },
    },
};
</script>

<style scoped>
.koreksi-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
}
.koreksi-footer__ringkas {
    margin: 0;
    min-width: 0;
    font-size: 0.75rem;
    line-height: 1.35;
    color: var(--hcis-slate-500, #64748b);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.koreksi-footer__ringkas strong { color: var(--hcis-slate-800, #1e293b); }
.koreksi-footer__ringkas.is-siap strong { color: var(--hcis-brand-700, #4338ca); }
.koreksi-footer__daftar::before,
.koreksi-footer__kurang::before { content: ' · '; color: var(--hcis-slate-400, #94a3b8); font-weight: 400; }
.koreksi-footer__kurang { color: var(--hcis-warning-700, #b45309); font-weight: 600; }
.koreksi-footer__aksi { display: flex; gap: 0.5rem; flex-shrink: 0; }

/* Ponsel: ringkasan di atas, dua tombol berbagi lebar sama. */
@media (max-width: 575.98px) {
    .koreksi-footer { flex-direction: column; align-items: stretch; gap: 0.5rem; }
    .koreksi-footer__ringkas { white-space: normal; text-align: center; }
    .koreksi-footer__aksi > * { flex: 1; }
}

.koreksi-skeleton { display: flex; flex-direction: column; gap: 1rem; }
.koreksi-skeleton__field { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; }
.koreksi-skeleton__row { display: flex; gap: 0.75rem; }

.koreksi-form { position: relative; }
.koreksi-fieldset { margin: 0; padding: 0; border: 0; min-width: 0; }
/* Kontrol bawaan ber-padding dengan content-box melebar keluar dialog tanpa ini. */
.koreksi-fieldset :deep(*) { box-sizing: border-box; }
.koreksi-form.is-menyimpan .koreksi-fieldset { opacity: 0.45; transition: opacity 0.15s ease; }

.koreksi-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.55);
    backdrop-filter: blur(1px);
}
.koreksi-overlay__text { font-size: 0.8125rem; font-weight: 600; color: var(--hcis-slate-700, #334155); }

.koreksi-spinner {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 3px solid var(--hcis-brand-100, #e0e7ff);
    border-top-color: var(--hcis-brand-600, #4f46e5);
    animation: koreksi-putar 0.7s linear infinite;
}
.koreksi-spinner--sm { width: 0.875rem; height: 0.875rem; border-width: 2px; }

.koreksi-pratinjau-loading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    background: var(--hcis-slate-50, #f8fafc);
    font-size: 0.8125rem;
    color: var(--hcis-slate-600, #475569);
}

@keyframes koreksi-putar { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
    .koreksi-spinner { animation-duration: 2s; }
}

.koreksi-kategori-info {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
    margin-top: -0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    background: var(--hcis-surface-muted, #f8fafc);
    font-size: 0.8125rem;
}
.koreksi-kategori-info__dot {
    flex-shrink: 0;
    width: 0.625rem;
    height: 0.625rem;
    margin-top: 0.3rem;
    border-radius: 999px;
}
</style>
