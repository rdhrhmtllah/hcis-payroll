<template>
    <div class="status-page-wrapper">
        <div class="particles" ref="particlesContainer"></div>

        <div v-if="status === 'menunggu'" class="container fade-in">
            <div class="mb-3">
                <img src="/logo/logo.png" alt="Logo" width="100" />
            </div>
            <h1 class="main-title">{{ title }}</h1>
            <p class="description">
                {{ description }}
            </p>

            <div class="timer-container">
                <div class="time-unit" v-if="countdown.days > 0">
                    <div class="time-value" ref="daysDisplay">
                        {{ String(countdown.days).padStart(2, "0") }}
                    </div>
                    <div class="time-label">Hari</div>
                </div>
                <div class="time-unit">
                    <div class="time-value" ref="hoursDisplay">
                        {{ String(countdown.hours).padStart(2, "0") }}
                    </div>
                    <div class="time-label">Jam</div>
                </div>
                <div class="time-unit">
                    <div class="time-value" ref="minutesDisplay">
                        {{ String(countdown.minutes).padStart(2, "0") }}
                    </div>
                    <div class="time-label">Menit</div>
                </div>
                <div class="time-unit">
                    <div class="time-value" ref="secondsDisplay">
                        {{ String(countdown.seconds).padStart(2, "0") }}
                    </div>
                    <div class="time-label">Detik</div>
                </div>
            </div>
            <p v-if="jadwalMulai" class="schedule-info">
                Akan dibuka pada: {{ formatDetailTanggal(jadwalMulai) }}
            </p>
            <p>
                <a href="/uDash" class="btn btn-primary"
                    ><i class="bi bi-arrow-left-circle me-2"></i> Kembali</a
                >
            </p>
        </div>

        <div v-else-if="status === 'close'" class="container fade-in">
            <div class="mb-3">
                <img src="/logo/logo.png" alt="Logo" width="100" />
            </div>

            <h1 class="main-title-alt">{{ title }}</h1>
            <p class="description">
                {{ description }}
            </p>
            <div class="icon-wrapper">
                <div
                    class="d-flex flex-column align-items-center justify-content-center text-center"
                >
                    <DotLottieVue
                        style="height: 150px; width: 150px"
                        autoplay
                        loop
                        src="/animation/loading2.lottie"
                    />
                </div>
            </div>
            <p v-if="jadwalAkhir" class="schedule-info-alt">
                Jadwal terakhir ditutup pada:
                {{ formatDetailTanggal(jadwalAkhir) }}
            </p>
            <p>
                <a href="/uDash" class="btn btn-primary"
                    ><i class="bi bi-arrow-left-circle me-2"></i> Kembali</a
                >
            </p>
        </div>

        <div v-else-if="status === 'tanpa_target'" class="container fade-in">
            <div class="mb-3">
                <img src="/logo/logo.png" alt="Logo" width="100" />
            </div>

            <h1 class="main-title-alt">{{ title }}</h1>
            <p class="description">
                {{ description }}
            </p>
            <p v-if="jadwalAkhir" class="schedule-info-alt">
                Jadwal pengisian periode ini berakhir pada:
                {{ formatDetailTanggal(jadwalAkhir) }}
            </p>
            <p>
                <a href="/uDash" class="btn btn-primary"
                    ><i class="bi bi-arrow-left-circle me-2"></i> Kembali</a
                >
            </p>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

export default {
    name: "StatusPage",
    components: { DotLottieVue },
    data() {
        return {
            countdown: { days: 0, hours: 0, minutes: 0, seconds: 0 },
            countdownInterval: null,
            reloading: false,
        };
    },
    props: {
        status: { type: String, required: true },
        jadwalMulai: { type: String, default: null },
        jadwalAkhir: { type: String, default: null },
        title: String,
        description: String,
    },
    watch: {
        countdown: {
            handler(newValue, oldValue) {
                if (newValue.seconds !== oldValue.seconds)
                    this.animateTimeUnit("secondsDisplay");
                if (newValue.minutes !== oldValue.minutes)
                    this.animateTimeUnit("minutesDisplay");
                if (newValue.hours !== oldValue.hours)
                    this.animateTimeUnit("hoursDisplay");
                if (newValue.days !== oldValue.days)
                    this.animateTimeUnit("daysDisplay");
            },
            deep: true,
        },
    },
    mounted() {
        this.createParticles();
        if (this.status === "menunggu" && this.jadwalMulai) {
            this.startCountdown();
        }
    },
    beforeUnmount() {
        clearInterval(this.countdownInterval);
    },
    methods: {
        startCountdown() {
            const targetDate = new Date(this.jadwalMulai).getTime();
            this.countdownInterval = setInterval(() => {
                const now = new Date().getTime();
                const distance = targetDate - now;

                if (distance < 1000) {
                    clearInterval(this.countdownInterval);
                    if (!this.reloading) {
                        this.reloading = true;
                        window.location.reload();
                    }
                    return;
                }

                this.countdown.days = Math.floor(
                    distance / (1000 * 60 * 60 * 24),
                );
                this.countdown.hours = Math.floor(
                    (distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60),
                );
                this.countdown.minutes = Math.floor(
                    (distance % (1000 * 60 * 60)) / (1000 * 60),
                );
                this.countdown.seconds = Math.floor(
                    (distance % (1000 * 60)) / 1000,
                );
            }, 1000);
        },
        animateTimeUnit(refName) {
            const element = this.$refs[refName];
            if (element) {
                element.classList.add("animating");
                setTimeout(() => {
                    element.classList.remove("animating");
                }, 500);
            }
        },
        createParticles() {
            const container = this.$refs.particlesContainer;
            if (!container) return;

            // [DIUBAH] Jumlah partikel sesuai permintaan Anda
            const particleCount = 400;
            const colors = ["#a8edea", "#fed6e3", "#667eea", "#ffffff"];

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement("div");
                particle.classList.add("particle");

                const size = Math.random() * 15 + 3;
                const duration = Math.random() * 5 + 5;

                const delay = 0;

                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.bottom = `-${size}px`;
                particle.style.background =
                    colors[Math.floor(Math.random() * colors.length)];
                particle.style.opacity = Math.random() * 0.5 + 0.2;

                const animationName =
                    Math.random() > 0.5 ? "float" : "float-alt";
                particle.style.animation = `${animationName} ${duration}s linear ${delay}s infinite`;

                container.appendChild(particle);
            }
        },
        formatDetailTanggal(dateString) {
            if (!dateString) return "N/A";
            const date = new Date(dateString);
            return (
                new Intl.DateTimeFormat("id-ID", {
                    day: "numeric",
                    month: "long",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                }).format(date) + " WIB"
            );
        },
    },
};
</script>

<style scoped>
/* Menggunakan <style scoped> global, bukan scoped, agar partikel bisa ditambahkan */
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap");

.status-page-wrapper {
    --bg-gradient: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    background: var(--bg-gradient);
    /* Halaman ini dulu splash layar penuh (layouts.master-withoutnavbar), tetapi
       app.js kini membungkus setiap halaman dengan AppShell. Isi shell diberi
       padding 1rem dan tingginya sudah dikurangi topbar (4rem); tanpa dua baris
       ini latar ungunya berbingkai celah dan melampaui layar setinggi topbar.
       Angkanya mengikuti .shell-content di Layouts/AppShell.vue. */
    margin: -1rem;
    min-height: calc(100vh - 4rem);
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
    overflow: hidden;
    position: relative;
    font-family: "Poppins", sans-serif;
}

.fade-in {
    animation: fadeInAnimation 1s ease-in-out;
}

@keyframes fadeInAnimation {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.container {
    text-align: center;
    max-width: 800px;
    width: 90%;
    padding: 3rem;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 20px;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    z-index: 10;
    position: relative;
}

.main-title,
.main-title-alt,
.main-title-open {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    font-weight: 700;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}

.main-title {
    background-image: linear-gradient(to right, #a8edea, #fed6e3);
}
.main-title-alt {
    background-image: linear-gradient(to right, #bdc3c7, #92979c);
}
.main-title-open {
    background-image: linear-gradient(to right, #69f7be, #42f1a3);
}

.description {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    color: #e0e0e0;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.timer-container {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    margin: 2rem 0;
}

.time-unit {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.time-value {
    font-size: 4rem;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    border-radius: 15px;
    min-width: 120px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.time-value::before {
    content: "";
    position: absolute;
    top: 0;
    left: -150%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.7s;
}

.time-value.animating::before {
    left: 150%;
}

.time-label {
    margin-top: 0.75rem;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #a8edea;
    font-weight: 400;
}

.schedule-info {
    margin-top: 1.5rem;
    font-size: 1rem;
    color: #f0f0f0;
}
.schedule-info-alt {
    margin-top: 1rem;
    font-size: 0.9rem;
    color: #ccc;
    font-style: italic;
}
.icon-wrapper {
    margin: 1.5rem 0;
}

.btn-action {
    background: linear-gradient(to right, var(--hcis-emerald-500), var(--hcis-emerald-400));
    color: white;
    padding: 0.8rem 2rem;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px -5px rgba(52, 211, 153, 0.6);
    cursor: pointer;
}
.btn-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 7px 20px -5px rgba(52, 211, 153, 0.5);
}

.particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.particle {
    position: absolute;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 50%;
}

@keyframes float {
    0% {
        transform: translateY(100vh) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    50% {
        transform: translateY(50vh) translateX(50px) rotate(180deg);
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateY(-150px) translateX(-50px) rotate(360deg);
        opacity: 0;
    }
}

@keyframes float-alt {
    0% {
        transform: translateY(100vh) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    50% {
        transform: translateY(50vh) translateX(-50px) rotate(-180deg);
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateY(-150px) translateX(50px) rotate(-360deg);
        opacity: 0;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 2rem 1.5rem;
    }
    .timer-container {
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .time-value {
        font-size: 2.5rem;
        min-width: 90px;
        padding: 1rem;
    }
    .main-title,
    .main-title-alt,
    .main-title-open {
        font-size: 2rem;
    }
    .description {
        font-size: 1rem;
    }
}
</style>
