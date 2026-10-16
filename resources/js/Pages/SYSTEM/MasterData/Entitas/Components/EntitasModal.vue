<template>
    <HcisDialog
        :model-value="show"
        :title="isEditing ? 'Ubah Entitas' : 'Tambah Entitas'"
        :kicker="isEditing ? 'Pembaruan Badan Hukum' : 'Badan Hukum Baru'"
        :icon="isEditing ? 'pencil-square' : 'buildings-fill'"
        size="md"
        sentiment="brand"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div class="ent-modal-body">
            <!-- Pratinjau badge: warna terlihat sebelum disimpan -->
            <div class="ent-preview" :style="{ '--ec': form.Warna || '#64748b' }">
                <img v-if="logoPratinjau" :src="logoPratinjau" class="ent-preview__logo" alt="Logo entitas" />
                <span v-else class="ent-preview__badge">{{ (form.Nama_Singkat || form.Kode_Entitas || '??').toUpperCase() }}</span>
                <div class="min-w-0">
                    <div class="ent-preview__name text-truncate">
                        {{ form.Nama_Entitas.trim() || 'Pratinjau nama entitas' }}
                    </div>
                    <div class="ent-preview__code font-monospace">
                        {{ (form.Kode_Entitas || 'KODE').toUpperCase() }}
                        <span v-if="indukKode"> · di bawah {{ indukKode }}</span>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label for="ent-nama" class="sop-modal-label">
                        Nama Entitas <span class="text-danger">*</span>
                    </label>
                    <HcisField
                        id="ent-nama"
                        v-model="form.Nama_Entitas"
                        type="text"
                        placeholder="Contoh: PT Evo Nusa Bersaudara"
                        clearable
                    />
                </div>

                <div class="col-12 col-md-5">
                    <label for="ent-kode" class="sop-modal-label">
                        Kode <span class="text-danger">*</span>
                    </label>
                    <HcisField id="ent-kode" v-model="form.Kode_Entitas" type="text" placeholder="ENB" :disabled="isEditing" />
                    <p class="ent-hint">
                        {{ isEditing
                            ? 'Kode terkunci setelah dibuat — ia rujukan tetap bagi seed dan integrasi.'
                            : 'Huruf, angka, dan garis bawah saja. Maksimal 10 karakter.' }}
                    </p>
                </div>

                <div class="col-12 col-md-5">
                    <label for="ent-singkat" class="sop-modal-label">Nama Singkat</label>
                    <HcisField id="ent-singkat" v-model="form.Nama_Singkat" type="text" placeholder="Evo Nusa" />
                    <p class="ent-hint">Dipakai badge sempit. Kosong berarti memakai kodenya.</p>
                </div>

                <!-- Kode penomoran sebagai PEMILIK. Terpisah dari Kode karena
                     nomor dokumen memuat entitas di dua posisi dengan arti yang
                     berbeda; keterangan di bawahnya menjelaskan itu di tempat
                     admin mengisinya, bukan di dokumentasi yang tak dibuka. -->
                <div class="col-12 col-md-5">
                    <label for="ent-kode-pemilik" class="sop-modal-label">Kode di Nomor Dokumen</label>
                    <HcisField
                        id="ent-kode-pemilik"
                        v-model="form.Kode_Sebagai_Pemilik"
                        type="text"
                        :placeholder="form.Kode_Entitas || 'sama dengan Kode'"
                    />
                    <p class="ent-hint">
                        Dicetak pada segmen <strong>PT Pemilik</strong> nomor dokumen. Kosongkan bila sama dengan
                        Kode. Segmen <strong>Ruang Lingkup</strong> selalu memakai Kode, bukan ini.
                    </p>
                </div>

                <div class="col-6 col-md-4">
                    <label for="ent-warna" class="sop-modal-label">Warna Merek</label>
                    <div class="d-flex align-items-center gap-2">
                        <input id="ent-warna" v-model="form.Warna" type="color" class="form-control form-control-color" />
                        <HcisField id="ent-warna-hex" v-model="form.Warna" type="text" placeholder="#1D4ED8" />
                    </div>
                    <p class="ent-hint">Kosongkan bila warna resminya belum diketahui.</p>
                </div>

                <div class="col-6 col-md-3">
                    <label for="ent-urutan" class="sop-modal-label">Urutan</label>
                    <HcisField id="ent-urutan" v-model="form.Urutan" type="number" min="0" max="999" />
                </div>

                <div class="col-12">
                    <label for="ent-logo" class="sop-modal-label">Logo</label>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input
                            id="ent-logo"
                            ref="berkasLogo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="form-control"
                            style="max-width: 20rem"
                            @change="pilihLogo"
                        />
                        <HcisButton
                            v-if="logoPratinjau"
                            variant="danger-quiet"
                            size="sm"
                            leading-icon="x-lg"
                            label="Hapus logo"
                            @click="hapusLogo"
                        />
                    </div>
                    <p class="ent-hint">
                        PNG, JPG, WEBP, atau SVG, maksimal 2 MB. Kosong berarti badge memakai
                        singkatan di atas warna merek. Boleh juga diisi URL langsung bila
                        logonya sudah ada di tempat lain.
                    </p>
                    <p v-if="logoGalat" class="text-danger small mt-1 mb-0">{{ logoGalat }}</p>
                </div>

                <div class="col-12">
                    <label for="ent-induk" class="sop-modal-label">Induk</label>
                    <select id="ent-induk" v-model="form.Induk_Entitas_Id" class="form-select">
                        <option value="">— Tanpa induk (payung tertinggi) —</option>
                        <option v-for="o in indukKandidat" :key="o.Id" :value="o.Id">
                            {{ o.Kode_Entitas }} — {{ o.Nama_Entitas }}
                        </option>
                    </select>
                    <p class="ent-hint">
                        Entitas berinduk dianggap tercakup oleh induknya. Dirinya sendiri dan
                        keturunannya tidak muncul di daftar ini — memilihnya akan membuat lingkaran.
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="d-flex justify-content-end align-items-center gap-2 w-100">
                <HcisButton variant="secondary" size="md" :disabled="saving" label="Batal" @click="close" />
                <HcisButton
                    variant="primary"
                    size="md"
                    :loading="saving"
                    :disabled="!bisaSimpan"
                    :label="saving ? 'Menyimpan…' : isEditing ? 'Simpan Perubahan' : 'Tambah Entitas'"
                    @click="submit"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { HcisDialog, HcisField, HcisButton } from '@/components/ui';

defineOptions({ name: 'EntitasModal' });

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    opsi: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);

const form = reactive({
    Id: null,
    Kode_Entitas: '',
    /* Kosong berarti "pakai Kode_Entitas"; server yang memulangkannya ke NULL. */
    Kode_Sebagai_Pemilik: '',
    Nama_Entitas: '',
    Nama_Singkat: '',
    Warna: '',
    Induk_Entitas_Id: '',
    Urutan: 0,
    /* Menampung path lama, data URL hasil unggah, atau null bila dihapus.
       BUKAN Logo_Url: itu URL bertanda tangan yang kedaluwarsa, dan
       menyimpannya kembali sebagai path akan membuat logo mati sendiri. */
    Logo: null,
});

const berkasLogo = ref(null);
const logoBaru = ref(null);
const logoGalat = ref('');

const MAKS_BYTE = 2 * 1024 * 1024;

const isEditing = computed(() => Boolean(form.Id));

/**
 * Kandidat induk tanpa dirinya sendiri.
 *
 * Server tetap menolak lingkaran sampai ke leluhur; penyaringan di sini hanya
 * mencegah kesalahan yang paling jelas terlihat, bukan menggantikan penjaganya.
 */
const indukKandidat = computed(() => props.opsi.filter((o) => o.Id !== form.Id));

const indukKode = computed(() => {
    const o = props.opsi.find((x) => x.Id === form.Induk_Entitas_Id);

    return o ? o.Kode_Entitas : '';
});

const logoPratinjau = computed(() => logoBaru.value || props.item?.Logo_Url || null);

const bisaSimpan = computed(
    () => form.Nama_Entitas.trim() !== '' && String(form.Kode_Entitas).trim() !== '',
);

watch(
    () => [props.show, props.item],
    () => {
        if (!props.show) return;
        const it = props.item;
        form.Id = it?.Id_Entitas || null;
        form.Kode_Entitas = it?.Kode_Entitas || '';
        form.Kode_Sebagai_Pemilik = it?.Kode_Sebagai_Pemilik || '';
        form.Nama_Entitas = it?.Nama_Entitas || '';
        form.Nama_Singkat = it?.Nama_Singkat || '';
        form.Warna = it?.Warna || '';
        form.Induk_Entitas_Id = it?.Induk_Entitas_Id || '';
        form.Urutan = it?.Urutan ?? 0;
        form.Logo = it?.Logo_Path || null;
        logoBaru.value = null;
        logoGalat.value = '';
        if (berkasLogo.value) berkasLogo.value.value = '';
    },
    { immediate: true },
);

function pilihLogo(e) {
    const f = e.target.files?.[0];
    logoGalat.value = '';
    if (!f) return;

    if (f.size > MAKS_BYTE) {
        logoGalat.value = `Berkas ${(f.size / 1024 / 1024).toFixed(1)} MB melebihi batas 2 MB.`;
        e.target.value = '';

        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        logoBaru.value = String(reader.result);
        form.Logo = logoBaru.value;
    };
    reader.onerror = () => {
        logoGalat.value = 'Berkas tidak bisa dibaca.';
    };
    reader.readAsDataURL(f);
}

function hapusLogo() {
    logoBaru.value = null;
    form.Logo = null;
    logoGalat.value = '';
    if (berkasLogo.value) berkasLogo.value.value = '';
}

function close() {
    emit('close');
}

function onDialogUpdate(v) {
    if (!v) close();
}

function submit() {
    if (!bisaSimpan.value) return;
    emit('submit', {
        isEditing: isEditing.value,
        Id: form.Id,
        Kode_Entitas: String(form.Kode_Entitas).trim().toUpperCase(),
        Nama_Entitas: form.Nama_Entitas.trim(),
        Nama_Singkat: form.Nama_Singkat?.trim() || null,
        Warna: form.Warna?.trim() || null,
        Induk_Entitas_Id: form.Induk_Entitas_Id || null,
        Urutan: Number(form.Urutan || 0),
        Logo: form.Logo || null,
    });
}
</script>

<style scoped>
.ent-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--hcis-border, #e5e7eb);
    border-radius: 0.75rem;
    background: var(--hcis-surface-muted, var(--hcis-slate-50));
    margin-bottom: 1rem;
}

.ent-preview__badge {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    min-width: 3.25rem;
    padding: 0.35rem 0.6rem;
    border-radius: 0.625rem;
    font-weight: 700;
    font-size: 0.75rem;
    color: #fff;
    background: var(--ec);
}

.ent-preview__logo {
    flex: 0 0 auto;
    width: 3.25rem;
    height: 3.25rem;
    object-fit: contain;
    border-radius: 0.625rem;
    background: #fff;
    border: 1px solid var(--hcis-border, #e5e7eb);
    padding: 0.25rem;
}

.ent-preview__name {
    font-weight: 700;
    font-size: 0.95rem;
}

.ent-preview__code {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #6b7280);
}

.ent-hint {
    margin: 0.25rem 0 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #6b7280);
}

.form-control-color {
    width: 2.6rem;
    padding: 0.2rem;
    flex: 0 0 auto;
}
</style>
