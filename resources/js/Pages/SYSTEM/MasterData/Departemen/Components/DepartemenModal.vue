<template>
    <HcisDialog
        :model-value="show"
        :title="isEditing ? 'Ubah Departemen' : 'Tambah Departemen'"
        :kicker="isEditing ? 'Pembaruan Kode Departemen' : 'Kode Departemen Baru'"
        :icon="isEditing ? 'pencil-square' : 'diagram-2-fill'"
        size="md"
        sentiment="brand"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div class="dep-modal-body">
            <!-- Pratinjau segmen nomor: kode inilah yang akan tercetak di
                 nomor dokumen, jadi ia diperlihatkan sebelum disimpan. -->
            <div class="dep-preview">
                <span class="dep-preview__kode font-monospace">{{ kodePratinjau }}</span>
                <div class="min-w-0">
                    <div class="dep-preview__name text-truncate">
                        {{ form.Nama_Departemen.trim() || 'Pratinjau nama departemen' }}
                    </div>
                    <div class="dep-preview__hint font-monospace">
                        …/{{ kodePratinjau }}/… · segmen nomor dokumen
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label for="dep-nama" class="sop-modal-label">
                        Nama Departemen <span class="text-danger">*</span>
                    </label>
                    <HcisField
                        id="dep-nama"
                        v-model="form.Nama_Departemen"
                        type="text"
                        placeholder="Contoh: Information Technology"
                        clearable
                    />
                </div>

                <div class="col-8 col-md-3">
                    <label for="dep-kode" class="sop-modal-label">
                        Kode <span class="text-danger">*</span>
                    </label>
                    <HcisField id="dep-kode" v-model="form.Kode_Departemen" type="text" placeholder="IT" :disabled="isEditing" />
                </div>

                <div class="col-4 col-md-2">
                    <label for="dep-urutan" class="sop-modal-label">Urutan</label>
                    <HcisField id="dep-urutan" v-model="form.Urutan" type="number" min="0" max="999" />
                </div>

                <div class="col-12">
                    <p class="dep-hint mt-0">
                        {{ isEditing
                            ? 'Kode terkunci setelah dibuat. Ia sudah tercetak sebagai segmen nomor dokumen, dan memindahkannya berarti memindahkan arti nomor yang sudah beredar tanpa satu pun jejak.'
                            : 'Huruf, angka, garis bawah, dan strip saja. Maksimal 10 karakter — kode ini menjadi segmen nomor dokumen.' }}
                    </p>
                </div>

                <div class="col-12">
                    <label for="dep-sub" class="sop-modal-label">Binding ke Sub-Divisi HRIS</label>
                    <select id="dep-sub" v-model="form.Sub_Divisi_Id" class="form-select">
                        <option value="">— Belum terikat —</option>
                        <option v-for="o in opsi" :key="o.Id" :value="o.Id">
                            {{ o.Nama }}<span v-if="o.Divisi"> · {{ o.Divisi }}</span>
                        </option>
                    </select>
                    <p class="dep-hint">
                        Binding hanya dipakai untuk menyambungkan dokumen ke data karyawan lewat
                        struktur HRIS. Boleh dikosongkan, dan itu keadaan yang lazim: tidak semua
                        kode departemen punya baris sendiri di struktur yang tersimpan. Kosong lebih
                        baik daripada dipasangkan ke sub-divisi yang salah.
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
                    :label="saving ? 'Menyimpan…' : isEditing ? 'Simpan Perubahan' : 'Tambah Departemen'"
                    @click="submit"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { HcisDialog, HcisField, HcisButton } from '@/components/ui';

defineOptions({ name: 'DepartemenModal' });

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    opsi: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);

const form = reactive({
    Id: null,
    Kode_Departemen: '',
    Nama_Departemen: '',
    Sub_Divisi_Id: '',
    Urutan: 0,
});

const isEditing = computed(() => Boolean(form.Id));

const kodePratinjau = computed(
    () => String(form.Kode_Departemen || 'KODE').trim().toUpperCase() || 'KODE',
);

const bisaSimpan = computed(
    () => form.Nama_Departemen.trim() !== '' && String(form.Kode_Departemen).trim() !== '',
);

watch(
    () => [props.show, props.item],
    () => {
        if (!props.show) return;
        const it = props.item;
        form.Id = it?.Id_Departemen || null;
        form.Kode_Departemen = it?.Kode_Departemen || '';
        form.Nama_Departemen = it?.Nama_Departemen || '';
        /* Nilai select adalah number, jadi id yang datang sebagai string tak
           akan pernah cocok dengan option mana pun dan bindingnya tampak hilang
           setiap kali form dibuka. */
        form.Sub_Divisi_Id = it?.Sub_Divisi_Id != null ? Number(it.Sub_Divisi_Id) : '';
        form.Urutan = it?.Urutan ?? 0;
    },
    { immediate: true },
);

function close() {
    emit('close');
}

function onDialogUpdate(val) {
    if (!val) close();
}

function submit() {
    if (!bisaSimpan.value) return;

    emit('submit', {
        isEditing: isEditing.value,
        Id: form.Id,
        Kode_Departemen: String(form.Kode_Departemen).trim().toUpperCase(),
        Nama_Departemen: form.Nama_Departemen.trim(),
        Sub_Divisi_Id: form.Sub_Divisi_Id === '' ? null : Number(form.Sub_Divisi_Id),
        Urutan: Number(form.Urutan) || 0,
    });
}
</script>

<style scoped>
.dep-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    margin-bottom: 1rem;
    border: 1px solid var(--hcis-border, #e5e7eb);
    border-radius: 0.75rem;
    background: var(--hcis-surface-subtle, var(--hcis-slate-50));
}

.dep-preview__kode {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    min-width: 4rem;
    padding: 0.3rem 0.6rem;
    border-radius: 0.5rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #fff;
    background: var(--hcis-slate-700);
}

.dep-preview__name {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--hcis-text, var(--hcis-slate-900));
}

.dep-preview__hint {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.dep-hint {
    margin: 0.35rem 0 0;
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}
</style>
