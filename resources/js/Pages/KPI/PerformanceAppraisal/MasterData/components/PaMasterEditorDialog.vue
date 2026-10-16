<template>
    <HcisDialog
        :model-value="modelValue"
        :title="title"
        kicker="MASTER DATA"
        :description="description"
        icon="pencil-square"
        sentiment="brand"
        variant="glass"
        :size="isMobile ? 'full' : 'lg'"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <form class="pa-editor" @submit.prevent="$emit('save', values)">
            <div class="pa-editor__notice">
                <HcisIcon name="info-circle" size="xs" /><span
                    >Menyunting master tidak mengubah salinan pada cycle yang sudah disiapkan.</span
                >
            </div>
            <div class="pa-editor__grid">
                <template v-for="field in fields" :key="field.key">
                    <label
                        v-if="field.type === 'select'"
                        class="pa-select-field"
                        :class="{ 'pa-field--wide': field.wide }"
                    >
                        <span>{{ field.label }}<em v-if="field.required">*</em></span>
                        <select v-model="values[field.key]" :disabled="field.disabled" :required="field.required">
                            <option value="">Pilih {{ field.label.toLowerCase() }}</option>
                            <option
                                v-for="option in field.options || []"
                                :key="String(option.value)"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <small v-if="field.hint">{{ field.hint }}</small>
                    </label>
                    <div v-else-if="field.type === 'textarea'" :class="{ 'pa-field--wide': field.wide }">
                        <HcisTextarea
                            :id="`pa-master-${field.key}`"
                            v-model="values[field.key]"
                            :label="field.label"
                            :placeholder="field.placeholder || ''"
                            :hint="field.hint || ''"
                            :rows="field.rows || 4"
                            :required="field.required"
                            :maxlength="field.maxLength || undefined"
                            :disabled="field.disabled"
                        />
                        <div v-if="field.preview === 'perilaku' && values[field.key]" class="pa-field-pratinjau">
                            <PaDaftarPerilaku :teks="values[field.key]" judul="Pratinjau yang dibaca penilai" />
                        </div>
                    </div>
                    <HcisField
                        v-else
                        :id="`pa-master-${field.key}`"
                        v-model="values[field.key]"
                        :label="field.label"
                        :type="field.type || 'text'"
                        :placeholder="field.placeholder || ''"
                        :required="field.required"
                        :disabled="field.disabled"
                        :class="{ 'pa-field--wide': field.wide }"
                    />
                </template>
            </div>
            <slot name="after-fields" />
            <HcisResultState
                v-if="error"
                status="danger"
                variant="flat"
                layout="inline"
                title="Perubahan ditolak"
                :description="error"
                :show-retry="false"
            />
        </form>
        <template #footer>
            <div class="pa-editor-footer-actions">
                <HcisButton variant="secondary" label="Batal" size="sm" @click="$emit('update:modelValue', false)" />
                <HcisButton
                    variant="primary"
                    label="Simpan"
                    size="sm"
                    leading-icon="check2-circle"
                    :loading="saving"
                    :disabled="saving"
                    @click="$emit('save', values)"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { HcisButton, HcisDialog, HcisField, HcisIcon, HcisResultState, HcisTextarea } from '@/components/ui';
import PaDaftarPerilaku from '../../components/PaDaftarPerilaku.vue';

defineOptions({ name: 'PaMasterEditorDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    fields: { type: Array, default: () => [] },
    record: { type: Object, default: null },
    error: { type: String, default: '' },
    saving: { type: Boolean, default: false },
});

const isMobile = ref(false);

function cekUkuran() {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 768;
}

onMounted(() => {
    cekUkuran();
    window.addEventListener('resize', cekUkuran, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('resize', cekUkuran);
});

const values = reactive({});

function syncValues() {
    Object.keys(values).forEach((key) => delete values[key]);
    props.fields.forEach((field) => {
        const raw = props.record?.[field.key];
        values[field.key] = raw === null || raw === undefined ? (field.default ?? '') : String(raw);
    });
}

watch(() => [props.modelValue, props.record, props.fields], syncValues, { deep: true, immediate: true });
</script>

<style scoped>
.pa-field-pratinjau { margin-top: 0.5rem; padding: 0.6rem 0.75rem; border: 1px dashed #99f6e4; border-radius: 10px; background: #f0fdfa; }

.pa-editor {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.pa-editor__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.7rem 0.8rem;
    color: #4338ca;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(238, 242, 255, 0.65);
    font-size: 0.75rem;
    line-height: 1.4;
}
.pa-editor__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
}
.pa-field--wide {
    grid-column: 1 / -1;
}
.pa-select-field {
    display: flex;
    flex-direction: column;
    gap: 0.32rem;
    color: var(--hcis-text-strong, #334155);
    font-size: 0.72rem;
    font-weight: 650;
}
.pa-select-field span em {
    margin-left: 0.15rem;
    color: #b91c1c;
    font-style: normal;
}
.pa-select-field select {
    min-height: 2.375rem;
    padding: 0.45rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(255, 255, 255, 0.88);
    font: inherit;
}
.pa-select-field select:disabled {
    opacity: 0.62;
}
.pa-select-field small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
    font-weight: 400;
}
.pa-editor-footer-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}
@media (max-width: 640px) {
    .pa-editor__grid {
        grid-template-columns: 1fr;
    }
    .pa-field--wide {
        grid-column: auto;
    }
    .pa-editor-footer-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    .pa-editor-footer-actions .hcis-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
