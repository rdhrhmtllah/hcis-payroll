<template>
    <div class="container">
        <div class="card p-3 mb-4">
            <h2 class="mb-1">Form Lembur</h2>
            <p class="text-muted mb-0">
                Tambahkan Form Lembur sesuai kebutuhan dengan cara klik tambah
                form lembur
            </p>
        </div>

        <!-- Form Loop -->
        <div class="card mb-3" v-for="(entry, idx) in entries" :key="idx">
            <div
                class="card-header p-2 d-flex justify-content-between align-items-center"
                @click="toggleOpen(idx)"
                style="cursor: pointer"
            >
                <div>
                    <strong>Form Entry #{{ idx + 1 }}</strong>
                </div>
                <div class="d-flex align-items-center">
                    <template
                        v-if="
                            !entry.isOpen &&
                            entry.date &&
                            entry.start &&
                            entry.end
                        "
                    >
                        <br />
                        <small class="text-muted mx-3"
                            >{{ entry.date }} | {{ entry.start }} -
                            {{ entry.end }}</small
                        >
                    </template>
                    <span
                        :class="[
                            'bi',
                            entry.isOpen ? 'bi-chevron-up' : 'bi-chevron-down',
                        ]"
                    ></span>
                </div>
            </div>

            <div class="card-body" v-show="entry.isOpen">
                <div class="mb-3">
                    <label class="form-label">Alasan</label>
                    <textarea
                        class="form-control"
                        :class="{ 'is-invalid': submitted && !entry.alasan }"
                        v-model="entry.alasan"
                        placeholder="Masukkan alasan..."
                    ></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Time</label>
                        <input
                            type="time"
                            class="form-control"
                            :class="{ 'is-invalid': submitted && !entry.start }"
                            v-model="entry.start"
                        />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">End Time</label>
                        <input
                            type="time"
                            class="form-control"
                            :class="{
                                'is-invalid':
                                    submitted &&
                                    (!entry.end ||
                                        !isEndAfterStart(
                                            entry.start,
                                            entry.end
                                        )),
                            }"
                            v-model="entry.end"
                        />
                        <div
                            v-if="
                                submitted &&
                                !isEndAfterStart(entry.start, entry.end)
                            "
                            class="invalid-feedback"
                        >
                            End Time harus lebih besar dari Start Time.
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tanggal</label>
                    <input
                        type="date"
                        class="form-control"
                        :class="{ 'is-invalid': submitted && !entry.date }"
                        v-model="entry.date"
                    />
                </div>

                <button
                    class="btn btn-link text-danger p-0"
                    @click="deleteForm(idx)"
                >
                    <i class="bi bi-trash me-1"></i> Delete Entry
                </button>
            </div>
        </div>

        <!-- Form Entry Baru -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Form Entry Baru</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Alasan</label>
                    <textarea
                        class="form-control"
                        :class="{ 'is-invalid': submitted && !newEntry.alasan }"
                        v-model="newEntry.alasan"
                        placeholder="Masukkan alasan..."
                    ></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Time</label>
                        <input
                            type="time"
                            class="form-control"
                            :class="{
                                'is-invalid': submitted && !newEntry.start,
                            }"
                            v-model="newEntry.start"
                        />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">End Time</label>
                        <input
                            type="time"
                            class="form-control"
                            :class="{
                                'is-invalid':
                                    submitted &&
                                    (!newEntry.end ||
                                        !isEndAfterStart(
                                            newEntry.start,
                                            newEntry.end
                                        )),
                            }"
                            v-model="newEntry.end"
                        />
                        <div
                            v-if="
                                submitted &&
                                !isEndAfterStart(newEntry.start, newEntry.end)
                            "
                            class="invalid-feedback"
                        >
                            End Time harus lebih besar dari Start Time.
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tanggal</label>
                    <input
                        type="date"
                        class="form-control"
                        :class="{ 'is-invalid': submitted && !newEntry.date }"
                        v-model="newEntry.date"
                    />
                </div>
            </div>
        </div>

        <!-- Tombol -->
        <div class="d-grid gap-2 mb-3">
            <button class="btn btn-dark" @click="pushToData">
                <i class="bi bi-plus-circle me-1"></i> Tambah Form Lembur
            </button>
            <button class="btn btn-secondary" @click="submitForm">
                Submit Forms
            </button>
        </div>
    </div>
</template>

<script>
import Swal from "sweetalert2";
import axios from "axios";
export default {
    data() {
        return {
            entries: [],
            submitted: false,
            newEntry: {
                alasan: "",
                start: "",
                end: "",
                date: "",
                isOpen: true,
            },
        };
    },
    methods: {
        toggleOpen(idx) {
            this.entries = this.entries.map((e, i) => ({
                ...e,
                isOpen: i === idx ? !e.isOpen : false,
            }));
        },
        deleteForm(idx) {
            this.entries.splice(idx, 1);
        },
        isEndAfterStart(start, end) {
            return start && end && start < end;
        },
        pushToData() {
            this.submitted = true;
            if (this.isNewEntryValid()) {
                this.entries = this.entries.map((e) => ({
                    ...e,
                    isOpen: false,
                }));
                const newItem = { ...this.newEntry, isOpen: true };
                this.entries.push(newItem);
                this.resetNewEntry();
                this.submitted = false;
            } else {
                alert("Lengkapi form sebelum menambah.");
            }
        },
        isNewEntryValid() {
            const e = this.newEntry;
            return (
                e.alasan &&
                e.start &&
                e.end &&
                e.date &&
                this.isEndAfterStart(e.start, e.end)
            );
        },
        resetNewEntry() {
            this.newEntry = {
                alasan: "",
                start: "",
                end: "",
                date: "",
                isOpen: true,
            };
        },
        submitForm() {
            this.submitted = true;

            const inputFields = ["alasan", "start", "end", "date"];
            const newBeingFilled = inputFields.some((field) => {
                const val = this.newEntry[field];
                return typeof val === "string" && val.trim() !== "";
            });

            if (newBeingFilled) {
                // Jika sedang diisi, harus valid
                if (this.isNewEntryValid()) {
                    this.entries = this.entries.map((e) => ({
                        ...e,
                        isOpen: false,
                    }));

                    this.entries.push({ ...this.newEntry });
                    this.resetNewEntry();
                } else {
                    alert(
                        "Form baru belum lengkap. Silakan lengkapi semua field atau kosongkan form baru."
                    );
                    return;
                }
            }

            // Validasi semua entry
            const isValid = this.entries.every((e) => {
                return (
                    e.alasan &&
                    e.start &&
                    e.end &&
                    e.date &&
                    this.isEndAfterStart(e.start, e.end)
                );
            });

            if (!isValid) {
                alert(
                    "Semua form harus diisi lengkap dan End Time harus lebih besar dari Start Time, atau hapus form yang tidak ingin diisi."
                );
                return;
            }

            // Kirim data
            axios
                .post("/addLembur", {
                    data: this.entries,
                })
                .then(() => {
                    // Reset semua state agar form bersih & validasi hilang
                    this.resetNewEntry();
                    this.entries = [];
                    this.submitted = false;

                    Swal.fire(
                        "Berhasil",
                        "Pengajuan Lembur Berhasil Ditambahkan.",
                        "success"
                    );
                });
        },
    },
};
</script>
