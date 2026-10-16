<template>
    <form @submit.prevent autocomplete="off" novalidate>
        <div class="row g-4">
            <div
                v-for="field in fields"
                :key="field.name"
                :class="field.col || 'col-12'"
            >
                <label class="form-label">{{ field.label }}</label>

                <!-- datetime -->
                <!-- Date picker design system (5 Okt 2026). Di ponsel ia membuka
                     dialog kalendernya sendiri, jadi tak lagi terpotong di
                     dalam badan modal seperti el-date-picker sebelumnya. -->
                <div v-if="field.type === 'datetime-local'">
                    <HcisDatePicker
                        :model-value="teksTanggal(modelValue[field.name])"
                        @update:model-value="
                            (newValue) => handleDateUpdate(field.name, newValue)
                        "
                        @change="
                            (newValue) => handleDateChange(field.name, newValue)
                        "
                        type="datetime"
                        format="DD MMM YYYY HH:mm"
                        placeholder="Pilih tanggal dan waktu"
                        :show-presets="false"
                        :disabled="!!field.disabled"
                        :disabled-date="
                            (date) => getDisabledDate(field.name, date)
                        "
                    />
                </div>

                <!-- select -->
                <div v-else-if="field.type === 'select'">
                    <el-select
                        v-model="modelValue[field.name]"
                        :filterable="true"
                        :placeholder="field.placeholder"
                        :multiple="field.multiple || false"
                        clearable
                        style="width: 100%"
                        @change="
                            (val) => {
                                $emit('update:modelValue', {
                                    ...modelValue,
                                    [field.name]: val,
                                });
                            }
                        "
                    >
                        <el-option
                            v-for="opt in field.options"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </div>

                <!-- textarea -->
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

                <!-- color-picker -->
                <div v-else-if="field.type === 'color'">
                    <el-color-picker
                        :model-value="modelValue[field.name]"
                        @update:modelValue="
                            $emit('update:modelValue', {
                                ...modelValue,
                                [field.name]: $event,
                            })
                        "
                        show-alpha
                        :predefine="field.predefine || []"
                    />
                </div>

                <!-- input default -->
                <div class="input-group" v-else>
                    <span v-if="field.icon" class="input-group-text">
                        <i :class="field.icon"></i>
                    </span>
                    <input
                        :value="modelValue[field.name]"
                        @input="
                            (e) => {
                                let val = e.target.value;
                                if (typeof field.filter === 'function') {
                                    val = field.filter(val);
                                    e.target.value = val;
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
                        :disabled="field.disabled ?? false"
                    />
                </div>
            </div>
        </div>
    </form>
</template>

<script>
import {
    ElColorPicker,
    ElMessage,
    ElSelect,
    ElOption,
    ElInput,
} from "element-plus";
import "element-plus/dist/index.css";
import HcisDatePicker from "@/components/ui/HcisDatePicker.vue";
import { keTeksTanggalJam } from "@/Pages/KPI/utils/tanggalJam";

/**
 * Nilai tanggal apa pun -> Date, TANPA parser bawaan untuk teks.
 *
 * Safari menolak `new Date("2026-10-12 08:00:00")` (Invalid Date), padahal
 * itulah bentuk yang dikirim HcisDatePicker dan yang datang dari SQL Server.
 * Teksnya diurai sendiri supaya pembanding Jadwal_Mulai/Jadwal_Akhir tetap
 * bekerja di iPhone.
 */
function keDate(v) {
    const m = keTeksTanggalJam(v).match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);
    if (!m) return null;
    return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
}

export default {
    name: "FormV2",
    components: {
        HcisDatePicker,
        ElColorPicker,
        ElSelect,
        ElOption,
        ElInput,
    },
    props: {
        modelValue: { type: Object, required: true },
        fields: { type: Array, required: true },
    },
    emits: ["update:modelValue"],
    methods: {
        /**
         * Nilai yang dibaca HcisDatePicker: teks "YYYY-MM-DD HH:mm:ss".
         * Pemanggil lama masih bisa menyimpan objek Date di modelnya.
         */
        teksTanggal(v) {
            return keTeksTanggalJam(v);
        },
        getDisabledDate(fieldName, date) {
            const start = keDate(this.modelValue["Jadwal_Mulai"]);
            const end = keDate(this.modelValue["Jadwal_Akhir"]);

            if (fieldName === "Jadwal_Mulai" && end) {
                return date.getTime() > end.getTime();
            }

            if (fieldName === "Jadwal_Akhir" && start) {
                // Tidak boleh sebelum mulai
                if (date.setHours(0, 0, 0, 0) < start.setHours(0, 0, 0, 0)) {
                    return true;
                }
            }

            return false;
        },

        // update silent (tanpa error)
        handleDateUpdate(fieldName, newValue) {
            const updated = { ...this.modelValue, [fieldName]: newValue };
            this.$emit("update:modelValue", updated);
        },

        // validasi final setelah user selesai pilih
        handleDateChange(fieldName, newValue) {
            const updated = { ...this.modelValue, [fieldName]: newValue };
            const start = keDate(updated["Jadwal_Mulai"]);
            const end = keDate(updated["Jadwal_Akhir"]);

            if (start && end) {
                if (start.toDateString() === end.toDateString()) {
                    if (start.getTime() >= end.getTime()) {
                        ElMessage.error(
                            "Jika tanggal sama, Jam Mulai harus lebih kecil dari Jam Akhir"
                        );
                        return;
                    }
                }

                if (start.getTime() > end.getTime()) {
                    ElMessage.error(
                        "Jadwal Mulai tidak boleh lebih besar dari Jadwal Akhir"
                    );
                    return;
                }
            }

            this.$emit("update:modelValue", updated);
        },
    },
};
</script>

<style scoped>
.vs__dropdown-toggle {
    height: 48px;
    border-color: #ced4da;
}
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
    background-color: var(--hcis-green-50); /* Lighter green */
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
