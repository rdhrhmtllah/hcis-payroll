<template>
    <div class="result-container" :class="`state-${status}`">
        <!-- Loading State -->
        <div v-if="status === 'loading'" class="state-view loading-view">
            <div class="loading-spinner mb-4"></div>
            <h3 class="result-title">Memproses Absensi...</h3>
            <p class="result-desc">Mohon tunggu sebentar, sedang memverifikasi data Anda.</p>
        </div>

        <!-- Success State -->
        <div v-else-if="status === 'success'" class="state-view success-view">
            <div class="success-animation-wrapper mb-4">
                <div class="success-circle">
                    <i class="bi bi-check-lg check-icon"></i>
                </div>
            </div>

            <h3 class="result-title text-success mb-2">Absensi Berhasil!</h3>

            <div class="capture-preview-wrapper mb-4">
                <img :src="photoUrl" class="capture-preview" alt="Captured Face" v-if="photoUrl" />
                <div class="preview-placeholder" v-else>
                    <i class="bi bi-person-bounding-box"></i>
                </div>
            </div>

            <div class="result-details">
                <div class="detail-row">
                    <span class="detail-label">Tipe</span>
                    <span class="detail-value fw-bold" :class="type === 'In' ? 'text-primary' : 'text-warning'">
                        Check-{{ type === 'In' ? 'in' : 'out' }}
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Waktu</span>
                    <span class="detail-value fw-medium">{{ formattedTime }}</span>
                </div>
            </div>

            <button class="btn btn-sm btn-primary rounded-pill px-3 fw-medium transition-all shadow-sm mt-4 w-100 close-btn" @click="$emit('close')">Tutup</button>
        </div>

        <!-- Failed State -->
        <div v-else-if="status === 'failed'" class="state-view failed-view">
            <div class="error-animation-wrapper mb-4">
                <div class="error-circle">
                    <i class="bi bi-x-lg error-icon"></i>
                </div>
            </div>

            <h3 class="result-title text-danger mb-2">Absensi Gagal</h3>
            <p class="result-desc text-muted mb-4">
                {{ message || 'Terjadi kesalahan saat memproses absensi Anda. Silakan coba lagi.' }}
            </p>

            <div class="d-flex gap-3 w-100 action-buttons">
                <button class="btn btn-sm btn-light border rounded-pill px-3 fw-medium transition-all flex-1" @click="$emit('close')">Batal</button>
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-medium transition-all shadow-sm flex-1" @click="$emit('retry')">
                    <i class="bi bi-arrow-clockwise me-2"></i> Coba Lagi
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'AbsensiResultScreen',
    props: {
        status: {
            type: String,
            required: true,
            validator: (value) => ['loading', 'success', 'failed'].includes(value),
        },
        type: {
            type: String,
            default: 'In',
        },
        timestamp: {
            type: [String, Date],
            default: null,
        },
        message: {
            type: String,
            default: '',
        },
        photoUrl: {
            type: String,
            default: null,
        },
    },
    computed: {
        formattedTime() {
            if (!this.timestamp) {
                const now = new Date();
                return now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            }
            try {
                const date = new Date(this.timestamp);
                return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                return this.timestamp;
            }
        },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table;
}
.result-container {
    width: 100%;
    min-height: 400px;
    display: flex;
    flex-direction: column;
    background: var(--surface, #fff);
    border-radius: 16px;
    overflow: hidden;
}

.state-view {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    animation: fadeIn 0.4s ease-out forwards;
}

.result-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--dark, var(--hcis-slate-800));
}

.result-desc {
    color: var(--text-muted, var(--hcis-slate-500));
    font-size: 0.95rem;
    line-height: 1.5;
    max-width: 80%;
    margin: 0 auto;
}

/* Loading */
.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid var(--border, var(--hcis-slate-200));
    border-top: 4px solid var(--primary, var(--hcis-indigo-500));
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Success Animation */
.success-animation-wrapper {
    animation: bounceIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

.success-circle {
    width: 80px;
    height: 80px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.success-circle::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 3px solid var(--success, var(--hcis-emerald-500));
    animation: ringScale 1.5s infinite;
}

.check-icon {
    font-size: 2.5rem;
    color: var(--success, var(--hcis-emerald-500));
    animation: tickDraw 0.5s 0.3s ease-out forwards;
    opacity: 0;
    transform: scale(0.5);
}

/* Error Animation */
.error-animation-wrapper {
    animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}

.error-circle {
    width: 80px;
    height: 80px;
    background: rgba(239, 68, 68, 0.1);
    border: 3px solid var(--danger, var(--hcis-red-500));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.error-icon {
    font-size: 2.5rem;
    color: var(--danger, var(--hcis-red-500));
}

/* Preview Image */
.capture-preview-wrapper {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    box-shadow:
        0 10px 25px -5px rgba(0, 0, 0, 0.1),
        0 0 0 4px rgba(255, 255, 255, 1),
        0 0 0 6px var(--border, var(--hcis-slate-200));
    overflow: hidden;
    position: relative;
    background: var(--surface-soft, var(--hcis-slate-50));
}

.capture-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1); /* Mirror just like camera */
}

.preview-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--text-muted, var(--hcis-slate-500));
}

/* Details Box */
.result-details {
    width: 100%;
    background: var(--surface-soft, var(--hcis-slate-50));
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid var(--border, var(--hcis-slate-200));
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.detail-row:not(:last-child) {
    border-bottom: 1px dashed var(--border, var(--hcis-slate-200));
}

.detail-label {
    color: var(--text-muted, var(--hcis-slate-500));
    font-size: 0.9rem;
}

.detail-value {
    color: var(--dark, var(--hcis-slate-800));
    font-size: 0.95rem;
}

/* Buttons */
.close-btn {
    font-size: 0.95rem;
}

.flex-1 {
    flex: 1;
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-2px);
}

/* Keyframes */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes bounceIn {
    0% {
        transform: scale(0.3);
        opacity: 0;
    }
    50% {
        transform: scale(1.05);
        opacity: 1;
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes tickDraw {
    from {
        opacity: 0;
        transform: scale(0.5) rotate(-15deg);
    }
    to {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

@keyframes shake {
    10%,
    90% {
        transform: translate3d(-1px, 0, 0);
    }
    20%,
    80% {
        transform: translate3d(2px, 0, 0);
    }
    30%,
    50%,
    70% {
        transform: translate3d(-4px, 0, 0);
    }
    40%,
    60% {
        transform: translate3d(4px, 0, 0);
    }
}

@keyframes ringScale {
    0% {
        transform: scale(1);
        opacity: 0.8;
    }
    100% {
        transform: scale(1.5);
        opacity: 0;
    }
}
</style>
