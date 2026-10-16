<template>
    <Modal
        :show="show"
        title="Buat Kategori Baru"
        size="medium"
        :showFooter="true"
        cancelText="Batal"
        confirmText="Simpan"
        :showCancel="true"
        :showConfirm="true"
        :loading="formKategori.loading"
        :confirmDisabled="!formKategori.nama_kategori"
        @close="handleClose"
        @confirm="submitForm"
        @update:show="$emit('update:show', $event)"
    >
        <template #body>
            <div class="p-2">
                <div class="mb-4">
                    <label
                        for="nama_kategori"
                        class="form-label fw-bold text-dark"
                    >
                        Nama Kategori <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        id="nama_kategori"
                        v-model="formKategori.nama_kategori"
                        class="form-control form-control-lg custom-input"
                        placeholder="Misal: Onboarding 2026"
                        :class="{
                            'is-invalid': formKategori.errors?.nama_kategori,
                        }"
                        @keyup.enter="submitForm"
                    />
                    <div
                        v-if="formKategori.errors?.nama_kategori"
                        class="invalid-feedback"
                    >
                        {{ formKategori.errors.nama_kategori }}
                    </div>
                    <small class="form-text text-muted mt-2 d-block">
                        Nama kategori ini akan digunakan untuk mengelompokkan
                        konten pembelajaran.
                    </small>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from "../../../../components/ui/modal.vue";
import { ElMessage } from "element-plus";
import "element-plus/es/components/message/style/css";
import axios from "axios";

// Library konten bersama — base path dari URL (SOP di /sop/*, LMS di /lms/*).
const kontenBasePath = () =>
    (typeof window !== "undefined" && window.location.pathname.startsWith("/sop"))
        ? "/sop/admin/konten"
        : "/lms/konten-kategori";

export default {
    name: "CreateCategoryModal",
    components: {
        Modal,
    },
    props: {
        show: {
            type: Boolean,
            required: true,
        },
    },
    emits: ["close", "update:show", "success"],
    data() {
        return {
            formKategori: {
                nama_kategori: "",
                loading: false,
                errors: {},
            },
        };
    },
    watch: {
        show(newVal) {
            if (newVal) {
                this.formKategori.nama_kategori = "";
            }
        },
    },
    methods: {
        handleClose() {
            this.$emit("close");
            this.$emit("update:show", false);
        },
        async submitForm() {
            if (!this.formKategori.nama_kategori) return;

            this.formKategori.errors = {};
            try {
                this.formKategori.loading = true;
                await axios.post(kontenBasePath() + "/create-kategori", {
                    nama_kategori: this.formKategori.nama_kategori,
                });

                ElMessage.success("Kategori berhasil dibuat");
                this.$emit("success");
                this.handleClose();
            } catch (error) {
                console.error("Submit Error:", error);
                if (error.response?.data?.errors) {
                    const errorData = error.response.data.errors;
                    const parsedErrors = {};
                    for (const key in errorData) {
                        parsedErrors[key] = errorData[key][0];
                    }
                    this.formKategori.errors = parsedErrors;
                } else {
                    ElMessage.error(
                        error.response?.data?.metadata?.message ||
                            "Terjadi kesalahan sistem saat menyimpan kategori",
                    );
                }
            } finally {
                this.formKategori.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.custom-input {
    border-radius: 12px;
    border: 1px solid var(--hcis-slate-200);
    padding: 12px 16px;
    font-size: 15px;
    transition: all 0.3s ease;
    background-color: var(--hcis-slate-50);
}

.custom-input:focus {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    background-color: var(--hcis-white);
    outline: none;
}

.custom-input.is-invalid {
    border-color: var(--hcis-red-500);
    background-color: var(--hcis-red-50);
}

.custom-input.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}
</style>
