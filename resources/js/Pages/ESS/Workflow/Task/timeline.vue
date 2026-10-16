<template>
    <section class="section">
        <div class="row" id="basic-table">
            <div class="col-12 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Master Data Task</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div
                                class="d-flex justify-content-start gap-2 align-items-center mb-3"
                            >
                                <a :href="createUrl">
                                    <button class="btn btn-primary">
                                        Tambah
                                    </button>
                                </a>
                                <button
                                    class="btn btn-success"
                                    @click="openGanttModal"
                                >
                                    Show Timeline
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table
                                    id="taskTable"
                                    ref="taskTable"
                                    class="table table-bordered table-hover"
                                >
                                    <thead>
                                        <tr>
                                            <th>NO</th>
                                            <th>Task</th>
                                            <th>Project</th>
                                            <th>Division</th>
                                            <th>User</th>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(
                                                task, index
                                            ) in dataGantDisplay"
                                            :key="task.id"
                                            :data-task-id="task.id"
                                        >
                                            <td>{{ index + 1 }}</td>
                                            <td>{{ task.name }}</td>
                                            <td>{{ task.project }}</td>
                                            <td>{{ task.division }}</td>
                                            <td>{{ task.user }}</td>
                                            <td>
                                                {{ formatDate(task.start) }}
                                            </td>
                                            <td>
                                                {{ formatDate(task.end) }}
                                            </td>
                                            <td>
                                                <span
                                                    v-if="task.status == 1"
                                                    class="badge bg-success"
                                                    >Low</span
                                                >
                                                <span
                                                    v-else-if="task.status == 2"
                                                    class="badge bg-warning text-dark"
                                                    >Medium</span
                                                >
                                                <span
                                                    v-else
                                                    class="badge bg-danger"
                                                    >High</span
                                                >
                                            </td>
                                            <td>
                                                <select
                                                    class="form-select form-select-sm"
                                                    :value="task.id_progress"
                                                    @change="
                                                        updateProgress(
                                                            task,
                                                            $event.target.value
                                                        )
                                                    "
                                                >
                                                    <option
                                                        v-for="progress in dataProgress"
                                                        :key="
                                                            progress.Id_Progress
                                                        "
                                                        :value="
                                                            progress.Id_Progress
                                                        "
                                                    >
                                                        {{ progress.Progress }}
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gantt Modal -->
    <div
        class="modal fade"
        id="ganttModal"
        tabindex="-1"
        aria-hidden="true"
        data-bs-backdrop="static"
    >
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Task Timeline</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div class="modal-body" v-if="ganttDataPrepared">
                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >
                        <button
                            class="btn btn-outline-primary"
                            @click="goToPreviousMonth"
                        >
                            ← Previous
                        </button>
                        <h5 class="mb-0">{{ formatHeaderMonth(startDate) }}</h5>
                        <button
                            class="btn btn-outline-primary"
                            @click="goToNextMonth"
                        >
                            Next →
                        </button>
                    </div>
                    <div class="table-responsive table-res-modal">
                        <table
                            class="table table-bordered text-center align-middle table-sm table-modal"
                        >
                            <thead class="table-primary thead-modal">
                                <tr>
                                    <th colspan="4"></th>
                                    <th
                                        v-for="(week, index) in groupedDates"
                                        :key="'w-' + index"
                                        :colspan="week.length"
                                    >
                                        Week {{ getWeekNumber(week[0]) }}
                                    </th>
                                </tr>
                                <tr>
                                    <th colspan="4"></th>
                                    <th
                                        v-for="date in dates"
                                        :key="
                                            'd1-' +
                                            date.toISOString().slice(0, 10)
                                        "
                                        style="writing-mode: vertical-rl"
                                    >
                                        {{ formatDate(date) }}
                                    </th>
                                </tr>
                                <tr>
                                    <th>Task</th>
                                    <th>Project</th>
                                    <th>Division</th>
                                    <th>User</th>
                                    <th
                                        v-for="date in dates"
                                        :key="
                                            'd2-' +
                                            date.toISOString().slice(0, 10)
                                        "
                                        :class="dayClass(date)"
                                    >
                                        {{ formatDay(date) }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="tbody-modal">
                                <tr
                                    v-for="task in visibleGanttTasks"
                                    :key="task.id"
                                    :title="`Task: ${task.name}\nUser: ${task.user}\n${task.start} → ${task.end}`"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                >
                                    <td>{{ task.name }}</td>
                                    <td>{{ task.project }}</td>
                                    <td>{{ task.division }}</td>
                                    <td>{{ task.user }}</td>
                                    <td
                                        v-for="date in dates"
                                        :key="
                                            'd3-' +
                                            date.toISOString().slice(0, 10)
                                        "
                                        :class="[
                                            'gantt-cell',
                                            getCellClass(date, task),
                                        ]"
                                    >
                                        <span
                                            v-if="
                                                isInRange(
                                                    date,
                                                    task.start,
                                                    task.end
                                                )
                                            "
                                            class="d-block w-100 h-100"
                                            >•</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center" v-if="displayPagination">
                        <ul class="pagination justify-content-center">
                            <li
                                :class="[
                                    'page-item',
                                    currentPage === 1 ? 'disabled' : '',
                                ]"
                            >
                                <button
                                    class="page-link"
                                    @click="changePage(currentPage - 1)"
                                >
                                    Previous
                                </button>
                            </li>
                            <li
                                v-for="page in totalPages"
                                :key="page"
                                :class="[
                                    'page-item',
                                    currentPage === page ? 'active' : '',
                                ]"
                            >
                                <button
                                    class="page-link"
                                    @click="changePage(page)"
                                >
                                    {{ page }}
                                </button>
                            </li>
                            <li
                                :class="[
                                    'page-item',
                                    currentPage === totalPages
                                        ? 'disabled'
                                        : '',
                                ]"
                            >
                                <button
                                    class="page-link"
                                    @click="changePage(currentPage + 1)"
                                >
                                    Next
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="modal-body" v-else>
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat data timeline...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Swal from "sweetalert2";
import axios from "axios";

export default {
    props: {
        dataGant: Array,
        dataProgress: Array,
    },
    data() {
        const now = new Date();
        return {
            startDate: new Date(now.getFullYear(), now.getMonth(), 1),
            endDate: new Date(now.getFullYear(), now.getMonth() + 1, 0),
            createUrl: "/task/create",
            filteredTaskIds: [], // New property to store filtered task IDs
            dataTable: null,
            dataGantDisplay: [],
            visibleGanttTasks: [],
            currentPage: 1,
            itemsPerPage: 50,
            tooltipsInitialized: false,
            ganttDataPrepared: false,
            ganttModalInitialized: false,
        };
    },
    computed: {
        filteredTasks() {
            // Return filtered tasks based on the current DataTable state
            if (!this.filteredTaskIds.length) {
                return this.dataGant; // Return all if no filtering
            }

            // Only return tasks that are in the filtered IDs array
            return this.dataGant.filter((task) =>
                this.filteredTaskIds.includes(Number(task.id))
            );
        },
        sortedGanttTasks() {
            // Use the filtered tasks instead of all tasks
            return this.filteredTasks;
        },
        totalPages() {
            return Math.ceil(this.sortedGanttTasks.length / this.itemsPerPage);
        },
        displayPagination() {
            return this.totalPages > 1;
        },
        dates() {
            const arr = [];
            const d = new Date(this.startDate);
            while (d <= this.endDate) {
                arr.push(new Date(d));
                d.setDate(d.getDate() + 1);
            }
            return arr;
        },
        groupedDates() {
            const weeks = [];
            let current = [];
            this.dates.forEach((d, i) => {
                current.push(d);
                if (d.getDay() === 6 || i === this.dates.length - 1) {
                    weeks.push(current);
                    current = [];
                }
            });
            return weeks;
        },
    },
    methods: {
        changePage(page) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
            console.log(page);
            this.updateVisibleGanttTasks();
            this.$nextTick(() => {
                this.activateTooltips();
            });
        },
        updateVisibleGanttTasks() {
            try {
                if (
                    !this.sortedGanttTasks ||
                    this.sortedGanttTasks.length === 0
                ) {
                    console.log("No Gantt tasks available to display");
                    this.visibleGanttTasks = [];
                    return;
                }

                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;

                // Create a copy of the tasks to avoid reference issues
                this.visibleGanttTasks = this.sortedGanttTasks
                    .slice(start, end)
                    .map((task) => ({
                        ...task,
                        // Ensure dates are correctly formatted
                        start: this.ensureDate(task.start),
                        end: this.ensureDate(task.end),
                    }));

                console.log(
                    `Showing tasks ${start + 1}-${Math.min(
                        end,
                        this.sortedGanttTasks.length
                    )} of ${this.sortedGanttTasks.length}`
                );
            } catch (error) {
                console.error("Error updating visible Gantt tasks:", error);
                this.visibleGanttTasks = [];
            }
        },

        ensureDate(dateStr) {
            if (!dateStr) return null;
            try {
                // Try to parse the date string into a proper date
                const date = new Date(dateStr);
                if (isNaN(date.getTime())) {
                    console.warn("Invalid date:", dateStr);
                    return null;
                }
                return date;
            } catch (e) {
                console.error("Error parsing date:", dateStr, e);
                return null;
            }
        },
        prepareGanttData() {
            // Make sure we have the latest filtered data before showing the Gantt chart
            this.updateFilteredTaskIds();

            // Process and prepare the data for the Gantt chart
            this.currentPage = 1;
            this.updateVisibleGanttTasks();
            this.ganttDataPrepared = true;

            this.$nextTick(() => {
                // Force a redraw of the Gantt view to ensure it's visible
                this.$forceUpdate();

                // Make sure tooltips are activated after the DOM is updated
                setTimeout(() => {
                    this.activateTooltips();
                }, 100);
            });

            return true;
        },
        updateProgress(task, newProgressId) {
            Swal.fire({
                title: "Yakin ubah progress?",
                text: "Perubahan akan disimpan.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya",
                cancelButtonText: "Batal",
            }).then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post("/task/updateProgress", {
                            Progress_Id: newProgressId,
                            Task_Id: task.id,
                        })
                        .then(() => {
                            Swal.fire(
                                "Berhasil",
                                "Progress diperbarui.",
                                "success"
                            ).then(() => location.reload());
                        })
                        .catch(() =>
                            Swal.fire("Gagal", "Terjadi kesalahan", "error")
                        );
                }
            });
        },
        injectDataTablesScript() {
            return new Promise((resolve, reject) => {
                const load = (src) => {
                    return new Promise((res, rej) => {
                        // Check if script is already loaded
                        const existingScript = document.querySelector(
                            `script[src="${src}"]`
                        );
                        if (existingScript) {
                            res();
                            return;
                        }

                        const s = document.createElement("script");
                        s.src = src;
                        s.onload = () => {
                            console.log(`Loaded script: ${src}`);
                            res();
                        };
                        s.onerror = (err) => {
                            console.error(`Failed to load script: ${src}`, err);
                            rej(err);
                        };
                        document.head.appendChild(s);
                    });
                };

                // Check if CSS is already loaded
                const existingLink = document.querySelector(
                    'link[href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css"]'
                );
                if (!existingLink) {
                    const link = document.createElement("link");
                    link.rel = "stylesheet";
                    link.href =
                        "https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css";
                    document.head.appendChild(link);
                }

                // Check if jQuery and DataTables are already loaded
                if (window.jQuery) {
                    console.log("jQuery already loaded");
                    if ($.fn.dataTable) {
                        console.log("DataTables already loaded");
                        resolve();
                    } else {
                        console.log("Loading DataTables scripts");
                        load(
                            "https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"
                        )
                            .then(() =>
                                load(
                                    "https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"
                                )
                            )
                            .then(resolve)
                            .catch(reject);
                    }
                } else {
                    console.log("Loading jQuery and DataTables scripts");
                    load("https://code.jquery.com/jquery-3.6.0.min.js")
                        .then(() =>
                            load(
                                "https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"
                            )
                        )
                        .then(() =>
                            load(
                                "https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"
                            )
                        )
                        .then(resolve)
                        .catch(reject);
                }
            });
        },
        initDataTable() {
            this.dataGantDisplay = [...this.dataGant];
            this.$nextTick(() => {
                if ($.fn.DataTable.isDataTable("#taskTable")) {
                    $(this.$refs.taskTable).DataTable().destroy();
                }

                this.dataTable = $(this.$refs.taskTable).DataTable({
                    pageLength: 50,
                    lengthMenu: [50, 100, 200],
                    deferRender: true,
                    processing: true,
                    language: {
                        processing:
                            '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
                    },
                    initComplete: () => {
                        this.updateFilteredTaskIds();
                    },
                    drawCallback: () => {
                        this.updateFilteredTaskIds();
                    },
                });

                // Listen for search, sort, and other events that change the table display
                this.dataTable.on("search.dt draw.dt order.dt page.dt", () => {
                    this.updateFilteredTaskIds();
                });
            });
        },
        updateFilteredTaskIds() {
            if (!this.dataTable) return;

            const filteredIds = [];
            this.dataTable
                .rows({ search: "applied", order: "applied" })
                .every(function () {
                    const id = $(this.node()).data("task-id");
                    if (id) filteredIds.push(Number(id));
                });

            this.filteredTaskIds = filteredIds;

            // If Gantt modal is open, update the Gantt display too
            if ($("#ganttModal").hasClass("show")) {
                this.updateVisibleGanttTasks();
            }
        },
        dayClass(date) {
            return date.getDay() === 0 || date.getDay() === 6
                ? "gantt-weekend"
                : "";
        },
        normalizeDate(d) {
            if (!d) return null;
            const date = new Date(d);
            date.setHours(0, 0, 0, 0);
            return date;
        },
        isInRange(date, start, end) {
            const d = this.normalizeDate(date);
            const s = this.normalizeDate(start);
            const e = this.normalizeDate(end);
            if (!d || !s || !e) return false;
            return d >= s && d <= e;
        },
        getCellClass(date, task) {
            return this.isInRange(date, task.start, task.end)
                ? "gantt-cell-in-range"
                : "";
        },
        formatDate(date) {
            const d = new Date(date); // pastikan diubah jadi Date object
            return d.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short", // ganti ke "long" untuk "Mei" bukan "Mei"
                year: "numeric", // "numeric" agar hasilnya "2025" bukan "25"
            });
        },

        formatDay(date) {
            return date.toLocaleDateString("en-US", { weekday: "short" });
        },
        formatHeaderMonth(date) {
            return date.toLocaleDateString("en-US", {
                month: "long",
                year: "numeric",
            });
        },
        getWeekNumber(date) {
            const firstJan = new Date(date.getFullYear(), 0, 1);
            const diff = date - firstJan;
            return Math.ceil(
                (diff / (1000 * 60 * 60 * 24) + firstJan.getDay() + 1) / 7
            );
        },
        goToPreviousMonth() {
            const newStart = new Date(
                this.startDate.getFullYear(),
                this.startDate.getMonth() - 1,
                1
            );
            this.startDate = newStart;
            this.endDate = new Date(
                newStart.getFullYear(),
                newStart.getMonth() + 1,
                0
            );
            this.$nextTick(() => {
                this.activateTooltips();
            });
        },
        goToNextMonth() {
            const newStart = new Date(
                this.startDate.getFullYear(),
                this.startDate.getMonth() + 1,
                1
            );
            this.startDate = newStart;
            this.endDate = new Date(
                newStart.getFullYear(),
                newStart.getMonth() + 1,
                0
            );
            this.$nextTick(() => {
                this.activateTooltips();
            });
        },
        activateTooltips() {
            try {
                if (typeof bootstrap !== "undefined" && bootstrap.Tooltip) {
                    // Destroy existing tooltips first
                    const tooltipList = document.querySelectorAll(
                        '[data-bs-toggle="tooltip"]'
                    );
                    tooltipList.forEach((el) => {
                        const tooltip = bootstrap.Tooltip.getInstance(el);
                        if (tooltip) {
                            tooltip.dispose();
                        }
                    });

                    // Create new tooltips
                    const tooltipTriggerList = [].slice.call(
                        document.querySelectorAll('[data-bs-toggle="tooltip"]')
                    );
                    tooltipTriggerList.forEach((el) => {
                        new bootstrap.Tooltip(el);
                    });
                    this.tooltipsInitialized = true;
                } else {
                    // If bootstrap is not loaded yet, try again after a short delay
                    setTimeout(() => {
                        this.activateTooltips();
                    }, 500);
                }
            } catch (error) {
                console.error("Error activating tooltips:", error);
            }
        },

        openGanttModal() {
            // Make sure we have the latest filtered data
            this.updateFilteredTaskIds();

            // Then prepare the data for Gantt chart
            this.prepareGanttData();

            // Then open the modal using jQuery to ensure it works
            if (window.jQuery && $("#ganttModal").length) {
                $("#ganttModal").modal("show");
            } else {
                // Fallback to bootstrap native if jQuery is not available
                const modal = document.getElementById("ganttModal");
                if (
                    modal &&
                    typeof bootstrap !== "undefined" &&
                    bootstrap.Modal
                ) {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }
            }

            // Force the Gantt view to update after modal is shown
            setTimeout(() => {
                this.updateVisibleGanttTasks();
                this.$nextTick(() => {
                    this.activateTooltips();
                });
            }, 300);
        },
    },
    watch: {
        dataGant: {
            immediate: true,
            handler(newVal) {
                if (newVal && newVal.length > 0) {
                    this.$nextTick(() => {
                        this.injectDataTablesScript().then(() => {
                            this.initDataTable();
                        });
                    });
                }
            },
        },
        // Watch for changes to filteredTaskIds and update the Gantt view if needed
        filteredTaskIds: {
            handler() {
                if (this.ganttDataPrepared) {
                    this.currentPage = 1; // Reset to first page when filter changes
                    this.updateVisibleGanttTasks();
                }
            },
        },
    },
    mounted() {
        this.injectDataTablesScript().then(() => {
            this.initDataTable();

            // Initialize the bootstrap modal events
            if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
                const ganttModal = document.getElementById("ganttModal");
                if (ganttModal) {
                    ganttModal.addEventListener("shown.bs.modal", () => {
                        // When modal is fully shown, re-render the Gantt data with latest filters
                        this.updateFilteredTaskIds();
                        this.updateVisibleGanttTasks();
                        this.$nextTick(() => {
                            this.activateTooltips();
                        });
                    });
                }
            }
        });
    },
};
</script>

<style scoped>
.gantt-cell {
    height: 20px;
    width: 20px;
    padding: 0;
    border: 1px solid #ccc;
    font-size: 10px;
}
.gantt-cell-in-range {
    background-color: #1e90ff;
    color: white;
}
.gantt-weekend {
    background-color: #f4f4f4;
}
.table-sm th,
.table-sm td {
    padding: 0;
    font-size: 10px;
    white-space: nowrap;
}
th[style*="writing-mode"] {
    font-size: 9px;
    line-height: 1;
}
.modal-body h5 {
    font-size: 1rem;
}
.modal-body .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
.table-responsive {
    overflow-x: auto;
    min-height: 200px;
}

/* Membuat wrapper dengan overflow scroll */
.table-res-modal {
    max-height: 60vh; /* atur tinggi sesuai kebutuhan */
    overflow: auto;
}

/* Sticky header 1, 2, 3 pada thead */
.table-modal thead th {
    position: sticky;
    top: 0;
    background-color: var(--hcis-legacy-bootstrap-gray); /* sama dengan .table-primary */
    z-index: 5;
    border-bottom: 1px solid #dee2e6;
}

/* Sticky baris ke-2 (tanggal vertikal) */
.table-modal thead tr:nth-child(2) th {
    top: 38px; /* tergantung tinggi baris pertama */
    height: 100px;
}

/* Sticky baris ke-3 (hari) */
.table-modal thead tr:nth-child(3) th {
    top: 76px; /* tergantung total tinggi dua baris di atas */
}

/* Atur tinggi baris header agar offset tepat */
.table-modal thead tr th {
    height: 38px;
    vertical-align: middle;
}
</style>
