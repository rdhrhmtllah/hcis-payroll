<template>
    <form @submit.prevent autocomplete="off" novalidate>
        <div class="row g-4">
            <div
                v-for="field in fields"
                :key="field.name"
                :class="field.col || 'col-12'"
            >
                <label class="form-label">{{ field.label }}</label>

                <!-- Input biasa -->
                <div
                    class="input-group"
                    v-if="field.type !== 'select' && field.type !== 'textarea'"
                >
                    <span v-if="field.icon" class="input-group-text">
                        <i :class="field.icon"></i>
                    </span>
                    <input
                        :value="modelValue[field.name]"
                        @input="
                            (e) => {
                                let val = e.target.value;

                                // kalau ada filter, langsung proses
                                if (typeof field.filter === 'function') {
                                    val = field.filter(val);
                                    e.target.value = val; // update tampilan input
                                }

                                $emit('update:modelValue', {
                                    ...modelValue,
                                    [field.name]: val,
                                });
                            }
                        "
                        :type="field.type"
                        class="form-control"
                        :placeholder="field.placeholder"
                        :required="field.required ?? true"
                    />
                </div>

                <!-- Select -->
                <div v-else-if="field.type === 'select'">
                    <v-select
                        :options="field.options"
                        :reduce="(opt) => opt.value"
                        label="label"
                        :placeholder="field.placeholder"
                        :modelValue="modelValue[field.name]"
                        @update:modelValue="
                            $emit('update:modelValue', {
                                ...modelValue,
                                [field.name]: $event,
                            })
                        "
                    />
                </div>

                <!-- Textarea -->
                <div v-else-if="field.type === 'textarea'" class="input-group">
                    <span v-if="field.icon" class="input-group-text">
                        <i :class="field.icon"></i>
                    </span>
                    <textarea
                        class="form-control"
                        :rows="field.rows || 3"
                        :cols="field.cols || null"
                        :placeholder="field.placeholder"
                        :required="field.required ?? true"
                        :value="modelValue[field.name]"
                        @input="
                            $emit('update:modelValue', {
                                ...modelValue,
                                [field.name]: $event.target.value,
                            })
                        "
                    ></textarea>
                </div>
            </div>
        </div>
    </form>
</template>

<script>
import vSelect from "vue-select";
export default {
    name: "FormV1",
    components: { vSelect },
    props: {
        modelValue: {
            type: Object,
            required: true,
        },
        fields: {
            type: Array,
            required: true,
        },
    },
    emits: ["update:modelValue"],
};
</script>

<style>
textarea.form-control {
    height: auto !important;
}

/* Switch styling */
.form-check-input:checked {
    background-color: var(--success);
    border-color: var(--success);
}

.form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
    border-color: var(--success);
}

.form-switch .form-check-input {
    width: 2.5em;
    height: 1.5em;
    cursor: pointer;
}

/* Gaya Form Input dengan Ikon */
.form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text);
}
.input-group .form-control {
    height: 48px;
    border-left: 0;
}
.input-group .input-group-text {
    background-color: var(--surface);
    border-right: 0;
    color: var(--text-muted);
}
.input-group .form-control:focus {
    box-shadow: none;
}
.input-group:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
    border-radius: 0.375rem;
}

/* Panel Interaktif untuk Opsi Toggle */
.form-group-interactive {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
}
.form-group-interactive:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary-light);
}
.form-group-interactive.is-active {
    background-color: #f0fdf4; /* Lighter green */
    border-color: var(--success);
}
.form-group-interactive .icon {
    font-size: 1.5rem;
    color: var(--secondary);
    margin-right: 1rem;
    width: 40px;
    text-align: center;
}
.form-group-interactive.is-active .icon {
    color: var(--success);
}
.form-group-interactive .description {
    flex-grow: 1;
}
.form-group-interactive .description .label-title {
    font-weight: 600;
    color: var(--dark);
}
.form-group-interactive .description .label-info {
    font-size: 0.875rem;
    color: var(--text-muted);
}
.form-group-interactive .form-switch {
    padding-left: 0; /* Hapus padding default */
}
.form-group-interactive .form-check-input {
    margin-left: 1rem;
    width: 3em;
    height: 1.5em;
}
</style>
