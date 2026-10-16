<template>
    <div class="row d-flex justify-content-center align-items-center">
        <div class="attendance-dashboard col-11">
            <!-- Improved Header -->
            <div class="dashboard-header">
                <div class="header-content">
                    <div class="header-info">
                        <div class="header-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div>
                            <h1>Manajemen Absensi Sales</h1>
                            <p>Kelola Absensi Sales</p>
                        </div>
                    </div>
                    <button class="primary-button" @click="openAttendanceModal">
                        <i class="bi bi-plus-circle"></i>
                        Tambah Absensi
                    </button>
                </div>
            </div>

            <!-- Current Date & Schedule -->
            <div class="current-date-section">
                <div class="current-date">
                    <span class="date-day">27</span>
                    <div class="date-info">
                        <span class="date-weekday">RABU</span>
                        <span class="date-month">NOVEMBER 2025</span>
                    </div>
                </div>
                <div class="current-schedule">
                    <h3>JADWAL ANDA HARI INI</h3>
                    <div class="schedule-times">
                        <span>08:00</span>
                        <i class="bi bi-three-dots"></i>
                        <span>17:00</span>
                    </div>
                    <button class="checkout-button" v-if="!hasCheckedOut">
                        <i class="bi bi-clock-history"></i>
                        PRESENSI KELUAR 17:00
                    </button>
                </div>
            </div>

            <!-- Week View Navigation -->
            <div class="week-navigation">
                <button class="nav-button" @click="prevWeek">
                    <i class="bi bi-chevron-left"></i>
                </button>

                <div class="week-days">
                    <div
                        v-for="day in weekDays"
                        :key="day.date"
                        class="day-card"
                        :class="{ 'active-day': isToday(day.date) }"
                    >
                        <span class="day-date">{{
                            formatDayDate(day.date)
                        }}</span>
                        <div class="day-content">
                            <span class="day-name">{{ day.dayName }}</span>
                            <div class="day-schedule">
                                <span>16:00</span>
                                <span>-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="nav-button" @click="nextWeek">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <!-- Attendance History -->
            <div class="attendance-history">
                <h2>Riwayat Absensi</h2>
                <div class="history-cards">
                    <div
                        class="history-card"
                        v-for="(item, idx) in Data"
                        :key="idx"
                    >
                        <div class="card-image">
                            <img
                                v-if="item.filePath"
                                :src="item.filePath"
                                alt="Foto Absensi"
                            />
                        </div>
                        <div class="card-details">
                            <h3>{{ formatDate(item.Tanggal_Absensi) }}</h3>
                            <span class="checkin-time">
                                {{ formatTime(item.Tanggal_Absensi) }}
                            </span>
                        </div>
                    </div>

                    <div class="empty-card" v-if="!hasCheckedOut">
                        <i class="bi bi-clock"></i>
                        <span>Belum Absen Keluar</span>
                    </div>
                </div>
            </div>
            <!-- modal -->
            <div
                v-if="isAttendanceModalOpen"
                class="modal-backdrop fade-in"
                @click="closeAttendanceModal"
            ></div>
            <div v-if="isAttendanceModalOpen" class="attendance-modal zoom-in">
                <div class="attendance-modal-content">
                    <h3 class="mb-4">Formulir Absensi</h3>
                    <form @submit.prevent="submitAttendance">
                        <div class="row">
                            <div class="col-md-6 mb-4 mb-md-0">
                                <label class="form-label"
                                    >Foto Bukti Kehadiran</label
                                >
                                <div class="photo-preview-container">
                                    <img
                                        v-if="capturedImage"
                                        :src="capturedImage"
                                        alt="Foto Absen"
                                    />
                                    <div
                                        v-else
                                        class="text-center d-flex flex-column justify-content-center align-items-center"
                                    >
                                        <i
                                            class="bi bi-image d-flex justify-content-center align-items-center mb-3"
                                            style="font-size: 2.5rem"
                                        ></i>
                                        <p>Belum ada foto</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="openCamera"
                                    class="btn btn-primary btn-modern w-100"
                                >
                                    <i
                                        class="bi bi-camera-fill me-2 d-flex justify-content-center align-items-center"
                                    ></i
                                    >Buka Kamera
                                </button>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="reason" class="form-label"
                                        >Alasan</label
                                    ><textarea
                                        class="form-control"
                                        id="reason"
                                        rows="4"
                                        v-model="form.reason"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <button
                                type="button"
                                class="btn btn-secondary btn-modern"
                                @click="closeAttendanceModal"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="btn btn-success btn-modern"
                                :disabled="!isFormValid"
                            >
                                <i
                                    class="bi bi-send-check-fill me-2 d-flex justify-content-center align-items-center"
                                ></i
                                >Kirim Absensi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="camera-modal fade-in" v-if="isCameraModalOpen">
                <video
                    ref="cameraFeed"
                    class="camera-modal-feed"
                    autoplay
                    playsinline
                ></video>
                <canvas ref="canvas" style="display: none"></canvas>
                <button class="camera-close-btn" @click="closeCamera">
                    <i
                        class="bi bi-x-lg d-flex justify-content-center align-items-center"
                    ></i>
                </button>
                <div
                    class="camera-modal-controls d-flex justify-content-center"
                >
                    <button
                        class="capture-btn"
                        @click="capturePhoto"
                        :disabled="isLoadingLocation"
                    >
                        <span
                            v-if="isLoadingLocation"
                            class="spinner-border text-primary"
                            role="status"
                        ></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
export default {
    props: {
        Data: Array,
        userLogin: Array,
    },
    data() {
        return {
            latitude: null,
            longitude: null,
            accuracy: null,
            loadingLocation: false,
            locationError: null,

            stream: null,
            capturedImage: null,
            isLoadingLocation: false,

            isAttendanceModalOpen: false,
            isCameraModalOpen: false,

            form: {
                employeeName: this.userName,
                reason: "",
            },

            isSubmitting: false,

            calendarDate: new Date(),
            attendanceHistory: [
                {
                    id: 1,
                    date: "2025-06-25",
                    status: "Hadir",
                    timestamp: "25 Juni 2025, 08:00 WIB",
                    photo_url: "...",
                },
                {
                    id: 2,
                    date: "2025-06-24",
                    status: "Izin",
                    timestamp: "24 Juni 2025, 09:00 WIB",
                    photo_url: "...",
                },
            ],
            historyCardRefs: {},
        };
    },
    // Your existing script with optimized methods
    methods: {
        isToday(dateString) {
            const today = new Date();
            const date = new Date(dateString);

            return (
                date.getDate() === today.getDate() &&
                date.getMonth() === today.getMonth() &&
                date.getFullYear() === today.getFullYear()
            );
        },

        formatDayDate(dateString) {
            const date = new Date(dateString);
            return date.getDate();
        },

        // Helper function to generate week days
        generateWeekDays() {
            const today = new Date();
            const currentDay = today.getDay(); // 0 (Sunday) to 6 (Saturday)
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - currentDay); // Start from Sunday

            return Array.from({ length: 7 }).map((_, index) => {
                const date = new Date(startOfWeek);
                date.setDate(startOfWeek.getDate() + index);

                return {
                    date: date.toISOString(),
                    dayName: date
                        .toLocaleDateString("id-ID", { weekday: "short" })
                        .toUpperCase(),
                    isToday: this.isToday(date),
                };
            });
        },

        formatDate(dateString) {
            const options = {
                weekday: "long",
                day: "2-digit",
                month: "short",
                year: "numeric",
            };
            return new Date(dateString).toLocaleDateString("id-ID", options);
        },
        formatTime(dateString) {
            return new Date(dateString)
                .toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                })
                .replace(".", ":");
        },
        getGeoLocation() {
            if (!navigator.geolocation) {
                this.locationError =
                    "Geolocation tidak didukung oleh browser ini.";
                return;
            }
            this.loadingLocation = true;
            this.locationError = null;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.latitude = position.coords.latitude;
                    this.longitude = position.coords.longitude;
                    this.accuracy = position.coords.accuracy;
                    this.loadingLocation = false;
                },
                (error) => {
                    this.loadingLocation = false;
                    this.latitude = null;
                    this.longitude = null;
                    this.accuracy = null;
                    this.locationError =
                        "Gagal mendapatkan lokasi: " + error.message;
                    console.error("Error getting geolocation:", error);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },

        async openCamera() {
            this.isCameraModalOpen = true;
            this.capturedImage = null;
            await this.$nextTick();
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: "user" },
                });
                if (this.$refs.cameraFeed)
                    this.$refs.cameraFeed.srcObject = this.stream;
            } catch (e) {
                alert("Kamera tidak dapat diakses.");
                this.isCameraModalOpen = false;
            }
        },
        closeCamera() {
            if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
            this.isCameraModalOpen = false;
        },
        capturePhoto() {
            this.isLoadingLocation = true;
            if (!this.$refs.cameraFeed || !this.$refs.cameraFeed.srcObject) {
                alert("Kamera belum aktif.");
                this.isLoadingLocation = false;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.latitude = pos.coords.latitude;
                    this.longitude = pos.coords.longitude;
                    this.accuracy = pos.coords.accuracy;
                    this.drawCanvasWithWatermark(
                        `Lok: ${this.latitude.toFixed(
                            4
                        )}, ${this.longitude.toFixed(4)}`,
                        new Date().toLocaleString("id-ID", {
                            timeZone: "Asia/Jakarta",
                            dateStyle: "short",
                            timeStyle: "short",
                        })
                    );
                },
                (error) => {
                    this.latitude = null;
                    this.longitude = null;
                    this.accuracy = null;
                    this.drawCanvasWithWatermark(
                        "Lokasi tidak terdeteksi",
                        new Date().toLocaleString("id-ID", {
                            timeZone: "Asia/Jakarta",
                            dateStyle: "short",
                            timeStyle: "short",
                        })
                    );
                    console.error(
                        "Error mengambil lokasi untuk watermark:",
                        error
                    );
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        },
        drawCanvasWithWatermark(locationText, timeText) {
            const video = this.$refs.cameraFeed;
            const canvas = this.$refs.canvas;
            const ctx = canvas.getContext("2d");

            if (!video.videoWidth || !video.videoHeight) {
                this.isLoadingLocation = false;
                this.closeCamera();
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            ctx.setTransform(1, 0, 0, 1, 0, 0);

            const padding = 15;
            const fontSize = 24;
            const lineHeight = fontSize * 1.5;

            ctx.font = `bold ${fontSize}px Inter, Arial, sans-serif`;
            ctx.textAlign = "left";

            const timeMetrics = ctx.measureText(timeText);
            const locationMetrics = ctx.measureText(locationText);
            const maxWidth = Math.max(timeMetrics.width, locationMetrics.width);

            const bgHeight = lineHeight * 2 + padding * 2;
            const bgWidth = maxWidth + padding * 2;
            const bgX = 0;
            const bgY = canvas.height - bgHeight;

            ctx.fillStyle = "rgba(0, 0, 0, 0.5)";
            ctx.fillRect(bgX, bgY, bgWidth, bgHeight);

            ctx.fillStyle = "rgba(255, 255, 255, 0.9)";
            ctx.shadowColor = "black";
            ctx.shadowBlur = 5;

            ctx.fillText(
                timeText,
                padding,
                canvas.height - padding - lineHeight
            );
            ctx.fillText(locationText, padding, canvas.height - padding);

            this.capturedImage = canvas.toDataURL("image/png", 0.8);
            this.isLoadingLocation = false;
            this.closeCamera();
        },

        openAttendanceModal() {
            this.form.employeeName = this.userName;
            this.isAttendanceModalOpen = true;
            this.getGeoLocation();
        },
        closeAttendanceModal() {
            this.isAttendanceModalOpen = false;
            this.form.reason = "";
            this.capturedImage = null;
            this.latitude = null;
            this.longitude = null;
            this.accuracy = null;
            this.locationError = null;
            this.loadingLocation = false;
            this.isSubmitting = false;
        },

        async submitAttendance() {
            if (!this.isFormValid) {
                alert("Silakan lengkapi form dan ambil foto absensi.");
                return;
            }

            if (!this.capturedImage) {
                alert("Silakan ambil foto absensi terlebih dahulu.");
                return;
            }

            if (
                this.latitude === null &&
                this.longitude === null &&
                this.locationError === null
            ) {
                alert(
                    "Sedang mencoba mendapatkan lokasi Anda. Mohon tunggu atau coba lagi."
                );
                return;
            }

            const blob = this.dataURLtoBlob(this.capturedImage);
            const fileName = `absensi_foto_${Date.now()}.png`;
            const file = new File([blob], fileName, { type: "image/png" });

            const formData = new FormData();
            formData.append("photo", file);
            formData.append("alasan", this.form.reason || "");
            formData.append("employee_id", this.employeeId);
            formData.append("location_latitude", this.latitude || "0");
            formData.append("location_longitude", this.longitude || "0");
            formData.append("accuracy", this.accuracy || "0");
            formData.append("frontend_timestamp", new Date().toISOString());
            formData.append(
                "Kode_Karyawan",
                this.userLogin.Kode_Karyawan || null
            );
            formData.append("Jenis", "keluar");
            formData.append("UserID", this.userLogin.Id_Users || null);

            this.isSubmitting = true;

            try {
                const response = await axios.post("/submitAbsen", formData, {
                    headers: { "Content-Type": "multipart/form-data" },
                });

                if (response.data.status === 200) {
                    alert(
                        response.data.message || "Absensi berhasil diupload!"
                    );
                } else {
                    alert(
                        response.data.message ||
                            "Terjadi kesalahan saat submit absensi."
                    );
                }
            } catch (error) {
                console.error(
                    "Error saat submit absensi:",
                    error.response ? error.response.data : error
                );
                let errorMessage =
                    "Terjadi kesalahan tidak diketahui saat submit absensi.";

                if (error.response) {
                    if (
                        error.response.status === 422 &&
                        error.response.data.errors
                    ) {
                        errorMessage =
                            "Validasi Gagal:\n" +
                            Object.values(error.response.data.errors)
                                .flat()
                                .join("\n");
                    } else if (
                        error.response.data &&
                        error.response.data.message
                    ) {
                        errorMessage = error.response.data.message;
                    } else {
                        errorMessage = `Server Error: ${error.response.status} ${error.response.statusText}`;
                    }
                } else if (error.request) {
                    errorMessage =
                        "Tidak ada respons dari server. Periksa koneksi internet Anda.";
                } else {
                    errorMessage = error.message;
                }
                alert(errorMessage);
            } finally {
                this.isSubmitting = false;
                this.closeAttendanceModal();
            }
        },

        dataURLtoBlob(dataurl) {
            const arr = dataurl.split(",");
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new Blob([u8arr], { type: mime });
        },

        getStatusClass(status) {
            if (status === "Hadir") return "bg-success";
            if (status === "Izin") return "bg-warning text-dark";
            if (status === "Sakit") return "bg-danger";
            return "bg-secondary";
        },
        previousMonth() {
            this.calendarDate = new Date(
                this.calendarDate.setMonth(this.calendarDate.getMonth() - 1)
            );
        },
        nextMonth() {
            this.calendarDate = new Date(
                this.calendarDate.setMonth(this.calendarDate.getMonth() + 1)
            );
        },
        scrollToRecord(id) {
            const el = this.historyCardRefs[id];
            if (el) {
                el.scrollIntoView({ behavior: "smooth", block: "center" });
                el.classList.add("highlighted");
                setTimeout(() => el.classList.remove("highlighted"), 1500);
            }
        },
    },
    computed: {
        weekDays() {
            return [...Array(7)].map((_, i) => {
                const date = new Date();
                date.setDate(date.getDate() + i);
                return {
                    date: date.toISOString(),
                    dayName: date
                        .toLocaleDateString("id-ID", { weekday: "short" })
                        .toUpperCase(),
                };
            });
        },
        hasCheckedOut() {
            // Logic to determine if user has checked out
            return false;
        },
        isFormValid() {
            return (
                !!this.capturedImage &&
                ((this.latitude !== null && this.longitude !== null) ||
                    this.locationError !== null) &&
                !this.isSubmitting
            );
        },
    },
    beforeUpdate() {
        this.historyCardRefs = {};
    },
    mounted() {
        this.clockInterval = setInterval(() => {
            this.liveClock = new Date().toLocaleTimeString("id-ID");
        }, 1000);

        console.log(this.userLogin);
    },
    beforeUnmount() {
        clearInterval(this.clockInterval);
    },
};
</script>

<style scoped>
/* Optimized CSS with your color theme */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --text: #334155;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --success: #10b981;
}

/* Base Styles */
.attendance-dashboard {
    margin: 0 auto;
    padding: 1rem;
}

/* Header Styles */
.dashboard-header {
    background: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );
    border-radius: 1.5rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    color: white;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.header-info h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
}

.header-info p {
    margin: 0.25rem 0 0;
    opacity: 0.9;
}

/* Button Styles */
.primary-button {
    background: white;
    color: var(--primary);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.primary-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
}

/* Current Date Section */
.current-date-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.current-date {
    background: var(--surface);
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.date-day {
    font-size: 4rem;
    font-weight: 800;
    line-height: 1;
    color: var(--primary);
}

.date-info {
    display: flex;
    flex-direction: column;
}

.date-weekday {
    font-size: 1.5rem;
    font-weight: 700;
}

.date-month {
    font-weight: 500;
    color: var(--text-muted);
}

.current-schedule {
    background: var(--surface);
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.current-schedule h3 {
    margin: 0 0 1rem;
    font-size: 1rem;
    color: var(--text-muted);
}

.schedule-times {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.schedule-times span {
    font-size: 2.5rem;
    font-weight: 700;
}

.checkout-button {
    background: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );
    color: white;
    border: none;
    width: 100%;
    padding: 0.75rem;
    border-radius: 0.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
}

/* Week Navigation */
.week-navigation {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.nav-button {
    background: var(--surface);
    border: 1px solid var(--border);
    width: 40px;
    height: 40px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.nav-button:hover {
    background: var(--surface-soft);
}

.week-days {
    display: flex;
    flex: 1;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.day-card {
    min-width: 80px;
    background: var(--surface);
    border-radius: 0.75rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.day-card.active-day {
    background: linear-gradient(
        135deg,
        var(--primary-light) 0%,
        var(--primary) 100%
    );
    color: white;
}

.day-date {
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.day-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.day-name {
    font-size: 0.875rem;
    font-weight: 500;
}

.day-schedule {
    display: flex;
    flex-direction: column;
    align-items: center;
    font-size: 0.75rem;
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    color: var(--text);
}

/* Attendance History */
.attendance-history {
    background: var(--surface);
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.attendance-history h2 {
    margin: 0 0 1.5rem;
    font-size: 1.25rem;
}

.history-cards {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.history-card {
    background: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-dark) 100%
    );
    border-radius: 0.75rem;
    padding: 1rem;
    display: flex;
    gap: 1rem;
    color: white;
}

.card-image {
    width: 120px;
    height: 120px;
    border-radius: 0.5rem;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.card-details h3 {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
}

.checkin-time {
    background: white;
    color: var(--text);
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    width: fit-content;
}

.empty-card {
    background: var(--surface-soft);
    border: 2px dashed var(--border);
    border-radius: 0.75rem;
    padding: 2rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    gap: 0.5rem;
}

.empty-card i {
    font-size: 1.5rem;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .current-date-section {
        grid-template-columns: 1fr;
    }

    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        align-items: flex-start;
    }

    .primary-button {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .dashboard-header {
        padding: 1rem;
    }

    .header-info {
        gap: 1rem;
    }

    .header-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }

    .header-info h1 {
        font-size: 1.4rem;
    }

    .date-day {
        font-size: 3rem;
    }

    .date-weekday {
        font-size: 1.2rem;
    }
}
</style>
