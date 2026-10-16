<template>
    <div class="greeting-section mb-4">
        <div class="stitch-card p-4 border-0 shadow-sm rounded-4 overflow-hidden position-relative"
            :style="{ background: greetingGradient }">
            <div class="position-relative z-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-white-50 mb-1 small fw-semibold text-uppercase" style="letter-spacing: 0.05em;">
                            {{ greetingLabel }}
                        </p>
                        <h3 class="text-white fw-bold mb-2">
                            {{ greeting.nama || 'User' }} 👋
                        </h3>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span v-if="greeting.jabatan" class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-2">
                                <i class="bi bi-briefcase me-1"></i>{{ greeting.jabatan }}
                            </span>
                            <span v-if="greeting.divisi" class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-2">
                                <i class="bi bi-building me-1"></i>{{ greeting.divisi }}
                            </span>
                            <span v-if="greeting.is_leader" class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                <i class="bi bi-people-fill me-1"></i>Leader · {{ greeting.team_count }} anggota
                            </span>
                        </div>
                    </div>
                    <div class="text-end d-none d-md-block">
                        <div class="text-white-50 small fw-semibold">{{ formattedDate }}</div>
                        <div class="text-white mt-1">
                            <i class="bi bi-clock me-1"></i>
                            <span class="fw-bold">{{ currentTime }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Decorative elements -->
            <div class="position-absolute top-0 end-0 opacity-10" style="font-size: 8rem; margin-top: -1rem; margin-right: -1rem;">
                <i :class="greetingIcon"></i>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HomepageGreeting',
    props: {
        greeting: {
            type: Object,
            default: () => ({
                nama: 'User',
                jabatan: '',
                divisi: '',
                is_leader: false,
                team_count: 0,
            }),
        },
    },
    data() {
        return {
            currentTime: '',
            timeInterval: null,
        };
    },
    computed: {
        hour() {
            return new Date().getHours();
        },
        greetingLabel() {
            if (this.hour >= 5 && this.hour < 12) return 'Selamat Pagi';
            if (this.hour >= 12 && this.hour < 15) return 'Selamat Siang';
            if (this.hour >= 15 && this.hour < 18) return 'Selamat Sore';
            return 'Selamat Malam';
        },
        greetingGradient() {
            if (this.hour >= 5 && this.hour < 12) return 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            if (this.hour >= 12 && this.hour < 15) return 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';
            if (this.hour >= 15 && this.hour < 18) return 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)';
            return 'linear-gradient(135deg, #0c3483 0%, #a2b6df 100%)';
        },
        greetingIcon() {
            if (this.hour >= 5 && this.hour < 12) return 'bi bi-brightness-high';
            if (this.hour >= 12 && this.hour < 15) return 'bi bi-sun';
            if (this.hour >= 15 && this.hour < 18) return 'bi bi-sunset';
            return 'bi bi-moon-stars';
        },
        formattedDate() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return new Date().toLocaleDateString('id-ID', options);
        },
    },
    mounted() {
        this.updateTime();
        this.timeInterval = setInterval(this.updateTime, 1000);
    },
    beforeUnmount() {
        clearInterval(this.timeInterval);
    },
    methods: {
        updateTime() {
            this.currentTime = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        },
    },
};
</script>

<style scoped>
.greeting-section .stitch-card {
    transition: transform 0.3s ease;
    min-height: 120px;
}

.z-1 {
    z-index: 1;
}

@media (max-width: 576px) {
    .greeting-section h3 {
        font-size: 1.25rem;
    }
}
</style>
