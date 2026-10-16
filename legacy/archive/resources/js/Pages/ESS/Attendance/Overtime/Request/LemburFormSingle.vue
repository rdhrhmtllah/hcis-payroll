<template>
    <div class="container my-4">
        <div class="card p-3 mb-4">
            <h2 class="mb-1">Form Lembur</h2>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Alasan</label>
                    <textarea
                        class="form-control"
                        :class="{ 'is-invalid': submitted && !form.alasan }"
                        v-model="form.alasan"
                        placeholder="Masukkan alasan..."
                    ></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Time</label>
                        <input
                            type="time"
                            class="form-control"
                            :class="{ 'is-invalid': submitted && !form.start }"
                            v-model="form.start"
                        />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">End Time</label>
                        <input
                            type="time"
                            class="form-control"
                            :class="{ 'is-invalid': submitted && !form.end }"
                            v-model="form.end"
                        />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tanggal</label>
                    <input
                        type="date"
                        class="form-control"
                        :class="{ 'is-invalid': submitted && !form.date }"
                        v-model="form.date"
                    />
                </div>

                <div class="d-grid">
                    <button class="btn btn-primary" @click="submitForm">
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            form: {
                alasan: "",
                start: "",
                end: "",
                date: "",
            },
            submitted: false,
        };
    },
    methods: {
        submitForm() {
            this.submitted = true;
            const { alasan, start, end, date } = this.form;
            if (alasan && start && end && date) {
                console.log("Form submitted:", this.form);
                alert("Form berhasil dikirim!");
                this.form = { alasan: "", start: "", end: "", date: "" };
                this.submitted = false;
            } else {
                alert("Lengkapi semua field sebelum submit.");
            }
        },
    },
};
</script>
