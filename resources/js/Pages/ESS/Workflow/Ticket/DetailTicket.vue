<template>
    <div class="container mt-5">
        <div class="card">
            <!-- <div class="card-header">
        <div v-if="from == 'display_ticket'">
          <a href="/displayTicket" class="btn btn-danger btn-sm">Back</a>
        </div>
        <div v-if="from == 'finish_ticket'">
          <a href="/finishTicket" class="btn btn-danger btn-sm">Back</a>
        </div>
      </div> -->
            <div class="card-body">
                <div></div>
                <div class="mb-3 d-flex justify-content-between">
                    <h5>Tracking:</h5>
                    <!-- <h5>No Ticket : TK-02/25-0001</h5> -->
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div
                            class="d-flex flex-column position-relative"
                            style="
                                margin-left: 20px;
                                border-left: 4px solid #28a745;
                            "
                        >
                            <div
                                v-for="(step, index) in data"
                                :key="index"
                                class="d-flex align-items-start mb-4 position-relative"
                            >
                                <div
                                    class="position-absolute start-0 translate-middle rounded-circle bg-success"
                                    :class="{
                                        'bg-success': index < currentStep,
                                        'bg-white border border-success':
                                            index === currentStep,
                                        'bg-secondary': index > currentStep,
                                    }"
                                    style="
                                        width: 20px;
                                        height: 20px;
                                        top: 0;
                                        left: -10px;
                                    "
                                ></div>
                                <div class="ms-4 w-100">
                                    <h5
                                        class="fw-bold"
                                        :class="{
                                            'text-muted': index > currentStep,
                                        }"
                                    >
                                        {{ step.title }}
                                    </h5>
                                    <p class="text-muted">{{ step.title2 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div
                            v-for="(data, index) in datainformation"
                            v-bind:key="index"
                        >
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <th
                                            scope="row"
                                            class="text-right"
                                            style="width: 250px"
                                        >
                                            No Ticket
                                        </th>
                                        <td style="width: 10px">:</td>
                                        <td>{{ data.no_ticket }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="text-right">
                                            Divisi
                                        </th>
                                        <td>:</td>
                                        <td>{{ data.divisi }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="text-right">
                                            Case
                                        </th>
                                        <td>:</td>
                                        <td>{{ data.keterangan_case }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="text-right">
                                            Tanggal Pengajuan
                                        </th>
                                        <td>:</td>
                                        <td>{{ data.tanggal_pengajuan }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="text-right">
                                            Pengaju
                                        </th>
                                        <td>:</td>
                                        <td>{{ data.requester }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="text-right">
                                            Penerima
                                        </th>
                                        <td>:</td>
                                        <td>{{ data.reciepent }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="text-right">
                                            Estimasi Tanggal Selesai
                                        </th>
                                        <td>:</td>
                                        <td>{{ data.tanggal_estimasi }}</td>
                                    </tr>
                                    <tr>
                                        <div
                                            class="row"
                                            v-for="(file, index) in data.files"
                                            v-bind:key="index"
                                        >
                                            <div
                                                class="col-md-12"
                                                v-if="file.mime !== ''"
                                            >
                                                <div
                                                    v-if="
                                                        file.mime.startsWith(
                                                            'image/'
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="file.path"
                                                        style="
                                                            width: 250px;
                                                            height: 200px;
                                                        "
                                                        class="rounded shadow"
                                                    />
                                                </div>
                                                <div
                                                    v-else-if="
                                                        file.mime_type ===
                                                        'application/pdf'
                                                    "
                                                >
                                                    <iframe
                                                        :src="file.path"
                                                        width="100%"
                                                        height="400px"
                                                    />
                                                </div>
                                                <div v-else>
                                                    📄
                                                    <a
                                                        :href="file.path"
                                                        target="_blank"
                                                        >{{ file.nama_file }}</a
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <!-- <ul>
                      <li
                        v-for="(file, index) in data.files"
                        v-bind:key="index"
                      >
                        <div v-if="file.mime.startsWith('image/')">
                          <img
                            :src="file.path"
                            style="width: 250px; height: 200px;"
                            class="rounded shadow"
                          />
                        </div>
                        <div v-else-if="file.mime_type === 'application/pdf'">
                          <iframe
                            :src="file.path"
                            width="100%"
                            height="400px"
                          />
                        </div>
                        <div v-else>
                          📄
                          <a :href="file.path" target="_blank">{{
                            file.nama_file
                          }}</a>
                        </div>
                      </li>
                    </ul> -->
                                    </tr>
                                </tbody>
                            </table>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>PIC</th>
                                        <th>Keterangan</th>
                                        <th>Tanggal</th>
                                        <th>Jam</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item2, index2) in data.log"
                                        v-bind:key="index2"
                                    >
                                        <td>{{ item2.pic }}</td>
                                        <td>{{ item2.keterangan }}</td>
                                        <td>{{ item2.date }}</td>
                                        <td>{{ item2.time }}</td>
                                        <td>{{ item2.status_note }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end">
            <div v-if="from == 'display_ticket'">
                <a href="/displayTicket" class="btn btn-danger btn-sm">Back</a>
            </div>
            <div v-if="from == 'finish_ticket'">
                <a href="/finishTicket" class="btn btn-danger btn-sm">Back</a>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "OrderTracking",
    props: {
        data: Array,
        datainformation: Array,
        from: String,
    },
    data() {
        return {
            flagSelesaiReciepent: "Y",
            flagSelesaiRequester: null,
            // Contoh status, bisa diganti berdasarkan data dari backend
            currentStep: this.data.length, // 0-based index: 0=placed, 1=preparing, 2=on the way, 3=delivered
            // steps: [
            //   {
            //     title: "Waiting List",
            //     description: "20 March 2025",
            //   },
            //   {
            //     title: "Approval By Reza",
            //     description: "21 March 2025",
            //   },
            //   {
            //     title: "On Prosess By Reza",
            //     description: "21 March 2025",
            //   },

            //   {
            //     title: "Finished By Reza",
            //     description: "22 March 2025",
            //   },
            //   {
            //     title: "Closed Ticket by Welly",
            //     description: "22 March 2025",
            //   },
            //   {
            //     title: "FINISH",
            //     description: "22 March 2025",
            //   },
            //   // {
            //   //   title: "Delivered",
            //   //   description: "Your order has been delivered",
            //   // },
            // ],
        };
    },
    computed: {},
    created() {},
    mounted() {},
    beforeDestroy() {},
    methods: {
        goBack() {
            if (this.from === "display_ticket") return "/displayTicket";
            if (this.from === "finish_ticket") return "/finishTicket";
            return "/"; // fallback
        },
        validateDates() {},
        toggleSection(index) {},
        handleTableScroll(e) {},
        calculateTotalRatings() {},
        updateCompletion(userId, criteriaId) {},
        getScoreClass(score) {},
    },
};
</script>

<style scoped>
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 0.25rem;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    font-size: 1.5rem;
    color: #ddd;
    transition: all 0.2s ease;
}

.rating label:hover,
.rating label:hover ~ label,
.rating input:checked ~ label {
    color: #ffd700;
    transform: scale(1.1);
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
}

.card {
    border: none;
    border-radius: 1.25rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    background: linear-gradient(to bottom, var(--hcis-white), #fafbfc);
    transition: all 0.3s ease;
}

.card-header {
    background: transparent;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1.5rem 2rem;
}

.card-title {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.5rem;
    margin: 0;
}

.card-body {
    padding: 2rem;
}

/* Section Header */
.section-header {
    background: linear-gradient(to right, #f8f9fa, #f1f3f5);
    border: 1px solid var(--hcis-legacy-bootstrap-gray);
    border-radius: 0.75rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.section-header:hover {
    background: linear-gradient(to right, #f1f3f5, var(--hcis-legacy-bootstrap-gray));
    transform: translateY(-1px);
}

.section-header h5 {
    color: #345;
    font-weight: 600;
}

/* Badge Styling untuk Nilai */
.score-badge {
    display: inline-block;
    background-color: #3498db;
    color: white;
    border-radius: 4px;
    padding: 3px 8px;
    font-weight: bold;
    margin-left: 6px;
}

.ticket-count {
    display: inline-block;
    font-weight: bold;
}

.review-badge {
    display: inline-block;
    background-color: #27ae60;
    color: white;
    border-radius: 4px;
    padding: 3px 12px;
    font-weight: bold;
}

.avg-score-badge {
    display: inline-block;
    color: white;
    border-radius: 4px;
    padding: 3px 10px;
    font-weight: bold;
}

.avg-score-badge.excellent {
    background-color: #27ae60;
}

.avg-score-badge.good {
    background-color: #3498db;
}

.avg-score-badge.average {
    background-color: #f39c12;
}

.avg-score-badge.poor {
    background-color: #e74c3c;
}

.is-invalid {
    border: 1px solid red;
}
.fixed-column {
    position: absolute;
    width: 200px;
    left: 0;
    background: #fff;
    border-right: 2px solid var(--hcis-legacy-bootstrap-gray);
    z-index: 1;
    padding: 1rem;
    font-weight: 500;
    color: #345;
}

@media (max-width: 768px) {
    .table-wrapper {
        margin-left: 150px;
    }

    .fixed-column {
        width: 150px;
    }

    .rating label {
        font-size: 1.25rem;
    }
}
</style>
