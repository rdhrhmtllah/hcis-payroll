<template>
    <div class="row g-3">
        <div class="col-lg-12">
            <div class="mb-1">
                <label class="form-label">Nama Formula</label>
                <input
                    :value="modelValue.Nama_Formula"
                    @input="updateField('Nama_Formula', $event.target.value)"
                    type="text"
                    class="form-control"
                    placeholder="Masukkan nama formula..."
                />
            </div>
        </div>

        <div class="col-lg-12">
            <div class="mb-1">
                <label class="form-label">Deskripsi Formula</label>
                <textarea
                    :value="modelValue.Deskripsi"
                    @input="updateField('Deskripsi', $event.target.value)"
                    class="form-control"
                    rows="2"
                    placeholder="Masukkan deskripsi formula..."
                ></textarea>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "formulaInfoForm",
    props: {
        modelValue: { type: Object, required: true },
    },
    emits: ["update:modelValue"],
    methods: {
        updateField(field, value) {
            this.$emit("update:modelValue", { ...this.modelValue, [field]: value });
        },
    },
};
</script>

<style scoped>
.form-label { font-weight: 600; font-size: 0.875rem; }
</style>
