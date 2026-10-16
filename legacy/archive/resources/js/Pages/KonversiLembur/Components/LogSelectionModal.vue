<template>
    <Modal
        v-if="show"
        :show="show"
        title="Pilih Log Absensi"
        size="medium"
        @close="$emit('close')"
    >
        <template #body>
            <p class="text-muted mb-3">
                Pilih check-in dan check-out dari log absensi hari OFF.
            </p>

            <div class="log-modal-filter mb-3">
                <label for="log-date" class="form-label">Tanggal Log</label>
                <input
                    id="log-date"
                    type="date"
                    v-model="logDate"
                    @change="fetchLogs"
                    class="form-control"
                />
            </div>

            <div class="log-modal-body-content">
                <div v-if="loading" class="text-center py-4">
                    <div
                        class="spinner-border text-primary"
                        role="status"
                    ></div>
                </div>

                <div v-else-if="rawLogs.length === 0" class="empty-state">
                    <i class="bi bi-clock-history"></i>
                    <p class="mb-0">Tidak ada checktime pada tanggal ini.</p>
                </div>

                <div v-else class="log-picker-form">
                    <div class="mb-3">
                        <label class="form-label">Check-in</label>
                        <select v-model="selectedCheckIn" class="form-select">
                            <option value="">Pilih check-in</option>
                            <option
                                v-for="item in rawLogs"
                                :key="`in-${item.datetime}`"
                                :value="item.datetime"
                            >
                                {{ item.time }}
                                {{ item.type ? `(${item.type})` : "" }}
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Check-out</label>
                        <select
                            v-model="selectedCheckOut"
                            class="form-select"
                            :disabled="!selectedCheckIn"
                        >
                            <option value="">Pilih check-out</option>
                            <option
                                v-for="item in checkoutCandidates"
                                :key="`out-${item.datetime}`"
                                :value="item.datetime"
                            >
                                {{ item.time }}
                                {{ item.type ? `(${item.type})` : "" }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="log-summary"
                        v-if="selectedCheckIn && selectedCheckOut"
                    >
                        <small class="d-block text-muted mb-1">
                            Durasi terhitung:
                            <strong>{{ durationLabel }}</strong>
                        </small>
                        <span
                            class="badge"
                            :class="
                                isEligible
                                    ? 'text-bg-success'
                                    : 'text-bg-secondary'
                            "
                        >
                            {{
                                isEligible
                                    ? `Eligible (>= ${minimumHours} jam)`
                                    : `Non Eligible (< ${minimumHours} jam)`
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <div
                class="w-100 d-flex justify-content-between align-items-center"
            >
                <small class="text-muted"
                    >Pilih check-in/out dulu, lalu lanjut jika eligible.</small
                >
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    :disabled="!canContinue"
                    @click="continueWithSelected"
                >
                    Lanjut
                </button>
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import moment from "moment";
import Modal from "@/components/ui/modalV2-noir.vue";

const props = defineProps({
    show: Boolean,
    targetDate: String,
});

const emit = defineEmits(["close", "select"]);

const logDate = ref("");
const rawLogs = ref([]);
const loading = ref(false);
const selectedCheckIn = ref("");
const selectedCheckOut = ref("");
const minimumHours = ref(6);

// Default logDate to 1-2 days before targetDate, or imply user logic
// The user usually claims overtime for a PAST date to convert to a FUTURE (or today) OFF day.
// So logPicker should default to maybe yesterday or let user pick.
// User requirement: "Pilih tanggal lembur" inside modal? Or "Timeline absensi mesin" implies fetching logs.

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            logDate.value =
                props.targetDate ||
                moment().subtract(1, "days").format("YYYY-MM-DD");
            selectedCheckIn.value = "";
            selectedCheckOut.value = "";
            fetchLogs();
        }
    },
);

const checkoutCandidates = computed(() => {
    if (!selectedCheckIn.value) {
        return [];
    }

    const inMoment = moment(selectedCheckIn.value);
    return rawLogs.value.filter((item) =>
        moment(item.datetime).isAfter(inMoment),
    );
});

const durationMinutes = computed(() => {
    if (!selectedCheckIn.value || !selectedCheckOut.value) {
        return 0;
    }

    const inMoment = moment(selectedCheckIn.value);
    const outMoment = moment(selectedCheckOut.value);
    if (!outMoment.isAfter(inMoment)) {
        return 0;
    }

    return outMoment.diff(inMoment, "minutes");
});

const durationLabel = computed(() => {
    const hours = durationMinutes.value / 60;
    return `${hours.toFixed(1)} Jam`;
});

const isEligible = computed(
    () => durationMinutes.value >= minimumHours.value * 60,
);

const canContinue = computed(
    () =>
        !!selectedCheckIn.value && !!selectedCheckOut.value && isEligible.value,
);

const fetchLogs = async () => {
    if (!logDate.value) return;
    loading.value = true;
    try {
        const response = await axios.get(route("konversi-lembur.logs"), {
            params: { date: logDate.value },
        });

        const payload = response?.data?.data || {};
        rawLogs.value = Array.isArray(payload?.raw_logs)
            ? payload.raw_logs
            : [];

        const min = Number(response?.data?.meta?.rule?.minimal_hours ?? 6);
        minimumHours.value = Number.isFinite(min) ? min : 6;

        selectedCheckIn.value = "";
        selectedCheckOut.value = "";
    } catch (error) {
        console.error("Failed logs", error);
        rawLogs.value = [];
    } finally {
        loading.value = false;
    }
};

const continueWithSelected = () => {
    if (!canContinue.value) {
        return;
    }

    emit("select", {
        date: logDate.value,
        check_in: selectedCheckIn.value,
        check_out: selectedCheckOut.value,
        durasi: durationLabel.value,
        durasi_menit: durationMinutes.value,
        eligible: isEligible.value,
        label: `${moment(selectedCheckIn.value).format("HH:mm")} - ${moment(selectedCheckOut.value).format("HH:mm")}`,
    });
};
</script>

<style scoped>
.log-item-disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.log-item-disabled:hover {
    border-color: #dbe3ef;
    box-shadow: none;
}

.log-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1055;
    display: grid;
    place-items: center;
    background: rgba(15, 23, 42, 0.45);
    padding: 1rem;
}

.log-modal-card {
    width: min(680px, 100%);
    max-height: 90vh;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.2);
    display: flex;
    flex-direction: column;
}

.log-modal-header,
.log-modal-filter,
.log-modal-footer {
    padding: 1rem 1.2rem;
}

.log-modal-header {
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 0.75rem;
}

.log-modal-header h3 {
    font-size: 1.1rem;
    color: #1f2937;
    font-weight: 700;
}

.log-modal-header p,
.log-modal-footer small {
    color: #64748b;
}

.log-modal-filter {
    border-bottom: 1px solid #e5e7eb;
}

.log-modal-filter .form-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
}

.log-modal-body {
    padding: 0.9rem 1.2rem;
    overflow-y: auto;
    background: #f8fafc;
    display: grid;
    gap: 0.7rem;
}

.log-item {
    width: 100%;
    border: 1px solid #dbe3ef;
    background: #fff;
    border-radius: 12px;
    padding: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
    text-align: left;
}

.log-item:hover {
    border-color: #6366f1;
    box-shadow: 0 10px 20px rgba(99, 102, 241, 0.12);
}

.empty-state {
    text-align: center;
    color: #94a3b8;
    padding: 2rem 0;
}

.empty-state i {
    font-size: 2rem;
}

.log-modal-footer {
    border-top: 1px solid #e5e7eb;
}
</style>
