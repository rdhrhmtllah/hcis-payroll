<template>
    <HcisDialog
        :model-value="show"
        :title="isEditing ? 'Ubah Proses APQC' : 'Tambah Proses APQC'"
        :kicker="isEditing ? 'Pembaruan Klasifikasi Proses' : 'Proses Baru'"
        :icon="isEditing ? 'pencil-square' : 'bounding-box'"
        size="md"
        sentiment="brand"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div class="sop-apqc-modal-body">
            <!-- Pratinjau tingkat: piramid jadi terlihat sebelum disimpan -->
            <div class="apqc-preview">
                <span class="apqc-preview__level">{{ (form.Kode_Proses || '??').toUpperCase() }}</span>
                <div class="min-w-0">
                    <div class="apqc-preview__name text-truncate">
                        {{ form.Nama_Proses.trim() || 'Pratinjau nama jenis' }}
                    </div>
                    <div class="apqc-preview__code font-monospace">
                        {{ (form.Kode_Proses || 'KODE').toUpperCase() }}
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label for="apqc-nama" class="sop-modal-label">
                        Nama Proses <span class="text-danger">*</span>
                    </label>
                    <HcisField
                        id="apqc-nama"
                        v-model="form.Nama_Proses"
                        type="text"
                        placeholder="Contoh: Develop and Manage Human Capital"
                        :error="errors.Nama_Proses"
                        clearable
                    />
                </div>

                <div class="col-12 col-md-5">
                    <label for="apqc-kode" class="sop-modal-label">
                        Nomor Proses <span class="text-danger">*</span>
                    </label>
                    <HcisField
                        id="apqc-kode"
                        v-model="form.Kode_Proses"
                        type="text"
                        placeholder="07"
                        :error="errors.Kode_Proses"
                        :disabled="isEditing"
                    />
                    <p class="apqc-hint">
                        {{ isEditing
                            ? 'Nomor terkunci setelah dibuat — ia rujukan tetap yang dikutip di audit.'
                            : 'Angka dua digit seperti 01, atau sub-level seperti 01.1.' }}
                    </p>
                </div>

                <div class="col-6 col-md-4">
                    <label for="apqc-urutan" class="sop-modal-label">Urutan</label>
                    <HcisField id="apqc-urutan" v-model="form.Urutan" type="number" min="0" max="999" />
                    <p class="apqc-hint">Urutan tampil di dropdown.</p>
                </div>
            </div>

            <!-- Tipe item sah: inilah yang membuat aturan pasangan jadi data -->
            <div class="mt-3">
                <label class="sop-modal-label d-block mb-1">Tipe Item yang Sah</label>
                <p class="apqc-hint mb-2">
                    Dokumen hanya boleh memakai proses ini bila tipenya ada di daftar berikut.
                    Kewajiban mengisi APQC pada dokumen JUGA diturunkan dari sini: tipe yang
                    tak punya satu pun proses sah tidak akan pernah diminta mengisinya.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button
                        v-for="t in tipeOpsi"
                        :key="t"
                        type="button"
                        class="apqc-tipe-chip"
                        :class="{ 'is-on': form.Tipe_Item.includes(t) }"
                        @click="toggleTipe(t)"
                    >
                        <i class="bi" :class="form.Tipe_Item.includes(t) ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                        {{ t }}
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <label for="apqc-deskripsi" class="sop-modal-label">
                    Deskripsi <span class="text-muted small">(Opsional)</span>
                </label>
                <textarea
                    id="apqc-deskripsi"
                    v-model="form.Deskripsi"
                    rows="3"
                    class="form-control sop-modal-textarea"
                    maxlength="500"
                    placeholder="Jelaskan cakupan proses ini bila namanya belum cukup menjelaskan…"
                ></textarea>
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
                    :label="saving ? 'Menyimpan…' : isEditing ? 'Simpan Perubahan' : 'Tambah Proses'"
                    @click="submit"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { HcisDialog, HcisField, HcisButton } from '@/components/ui';

defineOptions({ name: 'SopApqcModal' });

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    tipeOpsi: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);

const errors = ref({});

const form = reactive({
    Id: null,
    Kode_Proses: '',
    Nama_Proses: '',
    Urutan: 1,
    Deskripsi: '',
    Tipe_Item: [],
});

const isEditing = computed(() => Boolean(form.Id));

const bisaSimpan = computed(
    () => form.Nama_Proses.trim() !== '' && String(form.Kode_Proses).trim() !== '',
);

watch(
    () => [props.show, props.item],
    () => {
        if (!props.show) return;
        errors.value = {};
        const it = props.item;
        form.Id = it?.Id_SOP_APQC || null;
        form.Kode_Proses = it?.Kode_Proses || '';
        form.Nama_Proses = it?.Nama_Proses || '';
        form.Urutan = it?.Urutan ?? 0;
        form.Deskripsi = it?.Deskripsi || '';
        form.Tipe_Item = Array.isArray(it?.Tipe_Item) ? [...it.Tipe_Item] : [];
    },
    { immediate: true },
);

function toggleTipe(t) {
    const i = form.Tipe_Item.indexOf(t);
    if (i === -1) form.Tipe_Item.push(t);
    else form.Tipe_Item.splice(i, 1);
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
        Kode_Proses: String(form.Kode_Proses).trim().toUpperCase(),
        Nama_Proses: form.Nama_Proses.trim(),
        Urutan: Number(form.Urutan || 0),
        Deskripsi: form.Deskripsi?.trim() || null,
        Tipe_Item: [...form.Tipe_Item],
    });
}
</script>

<style scoped>
.apqc-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--hcis-border, #e5e7eb);
    border-radius: 0.75rem;
    background: var(--hcis-surface-muted, var(--hcis-slate-50));
    margin-bottom: 1rem;
}

.apqc-preview__level {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.625rem;
    font-weight: 700;
    font-size: 0.85rem;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-blue-600), var(--hcis-violet-600));
}

.apqc-preview__name {
    font-weight: 700;
    font-size: 0.95rem;
}

.apqc-preview__code {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #6b7280);
    letter-spacing: 0.02em;
}

.apqc-hint {
    margin: 0.25rem 0 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #6b7280);
}

.apqc-tipe-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-border, #e5e7eb);
    background: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #6b7280);
    transition: all 0.15s ease;
}

.apqc-tipe-chip.is-on {
    border-color: var(--hcis-blue-600);
    color: #1d4ed8;
    background: #eff6ff;
}
</style>
