<template>
    <HcisDialog
        :model-value="show"
        :title="isEditing ? 'Ubah Jenis Dokumen' : 'Tambah Jenis Dokumen'"
        :kicker="isEditing ? 'Pembaruan Hirarki Mutu' : 'Tingkat Baru'"
        :icon="isEditing ? 'pencil-square' : 'diagram-3-fill'"
        size="md"
        sentiment="brand"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div class="sop-jd-modal-body">
            <!-- Pratinjau tingkat: piramid jadi terlihat sebelum disimpan -->
            <div class="jd-preview">
                <span class="jd-preview__level">L{{ form.Level_Hirarki || '?' }}</span>
                <div class="min-w-0">
                    <div class="jd-preview__name text-truncate">
                        {{ form.Nama_Jenis.trim() || 'Pratinjau nama jenis' }}
                    </div>
                    <div class="jd-preview__code font-monospace">
                        {{ (form.Kode_Jenis || 'KODE').toUpperCase() }}
                        <span class="jd-preview__nomor">
                            · {{ kodeDokumenPratinjau }}/…/…/…/…/…/…
                        </span>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label for="jd-nama" class="sop-modal-label">
                        Nama Jenis <span class="text-danger">*</span>
                    </label>
                    <HcisField
                        id="jd-nama"
                        v-model="form.Nama_Jenis"
                        type="text"
                        placeholder="Contoh: Instruksi Kerja"
                        :error="errors.Nama_Jenis"
                        clearable
                    />
                </div>

                <div class="col-12 col-md-5">
                    <label for="jd-kode" class="sop-modal-label">
                        Kode Jenis <span class="text-danger">*</span>
                    </label>
                    <HcisField
                        id="jd-kode"
                        v-model="form.Kode_Jenis"
                        type="text"
                        placeholder="INSTRUKSI_KERJA"
                        :error="errors.Kode_Jenis"
                        :disabled="isEditing"
                    />
                    <p class="jd-hint">
                        {{ isEditing
                            ? 'Kode terkunci setelah dibuat — ia rujukan tetap bagi seed dan integrasi.'
                            : 'Huruf, angka, dan garis bawah saja.' }}
                    </p>
                </div>

                <div class="col-12 col-md-6">
                    <label for="jd-kode-dokumen" class="sop-modal-label">Kode Cetak Dokumen</label>
                    <HcisField
                        id="jd-kode-dokumen"
                        v-model="form.Kode_Jenis_Dokumen"
                        type="text"
                        placeholder="Contoh: IK, SOP, FRM"
                        :error="errors.Kode_Jenis_Dokumen"
                    />
                    <p class="jd-hint">
                        Segmen PERTAMA nomor dokumen (mis. <code>IK</code> pada
                        <code>IK/ENB/PROD/EHG/09/03/01</code>). Bebas disunting.
                    </p>
                </div>

                <div class="col-12 col-md-6">
                    <label for="jd-level" class="sop-modal-label">
                        Tingkat Hirarki Piramid <span class="text-danger">*</span>
                    </label>
                    <HcisField
                        id="jd-level"
                        v-model="form.Level_Hirarki"
                        type="number"
                        min="1"
                        max="50"
                        :error="errors.Level_Hirarki"
                        @input="form.Urutan = Number(form.Level_Hirarki || 1)"
                    />
                    <p class="jd-hint">
                        1 = Puncak Piramid (Kebijakan). Urutan dapat ditata ulang secara visual kapan saja.
                    </p>
                </div>
            </div>

            <!-- Tipe item sah: inilah yang membuat aturan pasangan jadi data -->
            <div class="mt-3">
                <label class="sop-modal-label d-block mb-1">Tipe Item yang Sah</label>
                <p class="jd-hint mb-2">
                    Dokumen hanya boleh memakai jenis ini bila tipenya ada di daftar berikut.
                    Kosong berarti jenis ini belum bisa dipakai dokumen mana pun.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button
                        v-for="t in tipeOpsi"
                        :key="t"
                        type="button"
                        class="jd-tipe-chip"
                        :class="{ 'is-on': form.Tipe_Item.includes(t) }"
                        @click="toggleTipe(t)"
                    >
                        <i class="bi" :class="form.Tipe_Item.includes(t) ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                        {{ t }}
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <label for="jd-deskripsi" class="sop-modal-label">
                    Deskripsi <span class="text-muted small">(Opsional)</span>
                </label>
                <textarea
                    id="jd-deskripsi"
                    v-model="form.Deskripsi"
                    rows="3"
                    class="form-control sop-modal-textarea"
                    maxlength="500"
                    placeholder="Jelaskan apa yang membedakan tingkat ini dari tingkat di atas dan di bawahnya…"
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
                    :label="saving ? 'Menyimpan…' : isEditing ? 'Simpan Perubahan' : 'Tambah Jenis'"
                    @click="submit"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { HcisDialog, HcisField, HcisButton } from '@/components/ui';

defineOptions({ name: 'SopJenisDokumenModal' });

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    tipeOpsi: { type: Array, default: () => [] },
    suggestedLevel: { type: Number, default: 1 },
});

const emit = defineEmits(['close', 'submit']);

const errors = ref({});

const form = reactive({
    Id: null,
    Kode_Jenis: '',
    Kode_Jenis_Dokumen: '',
    Nama_Jenis: '',
    Level_Hirarki: 1,
    Urutan: 1,
    Deskripsi: '',
    Tipe_Item: [],
});

const isEditing = computed(() => Boolean(form.Id));

/* Segmen pertama nomor dokumen. Menampilkan tanda tanya saat kosong, BUKAN
   string kosong: "/ENB/PROD/…" terbaca seolah segmennya memang tak ada,
   padahal ia hanya belum diisi. */
const kodeDokumenPratinjau = computed(
    () => String(form.Kode_Jenis_Dokumen || '').trim().toUpperCase() || '??',
);

const bisaSimpan = computed(
    () => form.Nama_Jenis.trim() !== '' && String(form.Kode_Jenis).trim() !== '' && Number(form.Level_Hirarki) >= 1,
);

watch(
    () => [props.show, props.item],
    () => {
        if (!props.show) return;
        errors.value = {};
        const it = props.item;
        form.Id = it?.Id_SOP_Jenis_Dokumen || null;
        form.Kode_Jenis = it?.Kode_Jenis || '';
        form.Kode_Jenis_Dokumen = it?.Kode_Jenis_Dokumen || '';
        form.Nama_Jenis = it?.Nama_Jenis || '';
        form.Level_Hirarki = it?.Level_Hirarki ?? props.suggestedLevel ?? 1;
        form.Urutan = it?.Urutan ?? form.Level_Hirarki ?? 1;
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
        Kode_Jenis: String(form.Kode_Jenis).trim().toUpperCase(),
        Kode_Jenis_Dokumen: String(form.Kode_Jenis_Dokumen || '').trim().toUpperCase() || null,
        Nama_Jenis: form.Nama_Jenis.trim(),
        Level_Hirarki: Number(form.Level_Hirarki),
        Urutan: Number(form.Urutan || form.Level_Hirarki),
        Deskripsi: form.Deskripsi?.trim() || null,
        Tipe_Item: [...form.Tipe_Item],
    });
}
</script>

<style scoped>
.jd-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid var(--hcis-border, #e5e7eb);
    border-radius: 0.75rem;
    background: var(--hcis-surface-muted, var(--hcis-slate-50));
    margin-bottom: 1rem;
}

.jd-preview__level {
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

.jd-preview__name {
    font-weight: 700;
    font-size: 0.95rem;
}

.jd-preview__code {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #6b7280);
    letter-spacing: 0.02em;
}

.jd-hint {
    margin: 0.25rem 0 0;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #6b7280);
}

.jd-tipe-chip {
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

.jd-tipe-chip.is-on {
    border-color: var(--hcis-blue-600);
    color: #1d4ed8;
    background: #eff6ff;
}
</style>
