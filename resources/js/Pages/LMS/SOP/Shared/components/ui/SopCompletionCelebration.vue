<template>
    <transition name="cel-fade">
        <div v-if="show" class="cel-mask" @click="handleMaskClick">
            <!-- Canvas Confetti, Fireworks & Streamer Cannon System -->
            <canvas ref="cv" class="cel-cv" aria-hidden="true"></canvas>

            <!-- Floating Interactive Hint -->
            <div class="cel-click-hint" aria-hidden="true">
                <i class="bi bi-cursor-fill"></i> Klik di mana saja untuk kembang api!
            </div>

            <!-- Main Celebration Card -->
            <section
                class="cel-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cel-title"
                :style="[{ '--accent': accent }, cardTransformStyle]"
                @mousemove="handleMouseMove"
                @mouseleave="handleMouseLeave"
                @click.stop
            >
                <!-- Glowing Animated Background Grid & Multi-color Rays -->
                <div class="cel-bg-effects" aria-hidden="true">
                    <div class="cel-glow-orb cel-orb-1"></div>
                    <div class="cel-glow-orb cel-orb-2"></div>
                    <div class="cel-glow-orb cel-orb-3"></div>
                    <div class="cel-grid-pattern"></div>
                </div>

                <!-- CSS Fallback Floating Shimmer & Confetti -->
                <div class="cel-css-confetti" aria-hidden="true">
                    <span v-for="piece in 28" :key="piece" class="cel-confetti-piece"></span>
                </div>

                <div class="cel-content">
                    <!-- Top Bar -->
                    <div class="cel-topbar">
                        <div class="cel-status-line">
                            <span class="cel-badge-success">
                                <span class="cel-dot"></span>
                                <i class="bi bi-patch-check-fill"></i> DOKUMEN TUNTAS
                            </span>
                            <span class="cel-status-pct">100% SELESAI</span>
                        </div>
                        <button ref="closeBtn" class="cel-x" type="button" aria-label="Tutup" @click="$emit('close')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Progress Bar Animation -->
                    <div class="cel-progress" aria-hidden="true">
                        <span class="cel-progress-bar"></span>
                        <span class="cel-progress-glow"></span>
                    </div>

                    <!-- Hero Emblem Section (Super Animated Trophy & Badges) -->
                    <div class="cel-emblem-wrapper">
                        <!-- Animated Rays Background -->
                        <div class="cel-rays"></div>

                        <!-- Multi Ring Shockwaves -->
                        <span class="cel-ring cel-ring-1"></span>
                        <span class="cel-ring cel-ring-2"></span>
                        <span class="cel-ring cel-ring-3"></span>

                        <!-- Outer Glowing Halo Ring -->
                        <div class="cel-halo-ring"></div>

                        <!-- Main Core Badge -->
                        <div class="cel-emblem-core">
                            <i class="bi bi-trophy-fill cel-trophy-icon"></i>
                            <div class="cel-shine-sweep"></div>
                        </div>

                        <!-- Mini Checkmark Badge -->
                        <div class="cel-check">
                            <i class="bi bi-check-lg"></i>
                        </div>

                        <!-- Floating Animated Sparkles & Stars -->
                        <div class="cel-sparks-container">
                            <i class="bi bi-stars cel-spark s1"></i>
                            <i class="bi bi-stars cel-spark s2"></i>
                            <i class="bi bi-star-fill cel-spark s3"></i>
                            <i class="bi bi-star-fill cel-spark s4"></i>
                            <i class="bi bi-sparkles cel-spark s5"></i>
                            <i class="bi bi-star-fill cel-spark s6"></i>
                            <i class="bi bi-award-fill cel-spark s7"></i>
                        </div>
                    </div>

                    <!-- Header Titles -->
                    <div class="cel-header-text">
                        <div class="cel-eyebrow"><i class="bi bi-stars"></i> PENCAPAIAN HEBAT</div>
                        <h2 id="cel-title" class="cel-title">{{ title }}</h2>
                        <p class="cel-sub">
                            Seluruh langkah wajib pada <strong class="cel-highlight">{{ namaPaket }}</strong> telah
                            berhasil dituntaskan dengan sempurna! 🎉
                        </p>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="cel-metrics">
                        <div class="cel-metric">
                            <div class="cel-metric-icon">
                                <i class="bi bi-list-check"></i>
                            </div>
                            <div class="cel-metric-body">
                                <strong>{{ totalItem }}</strong>
                                <span>Langkah Wajib</span>
                            </div>
                        </div>

                        <div class="cel-metric">
                            <div class="cel-metric-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="cel-metric-body">
                                <strong>{{ completedLabel }}</strong>
                                <span>Waktu Selesai</span>
                            </div>
                        </div>

                        <div class="cel-metric" :class="{ caution: hasFailedQuiz, success: !hasFailedQuiz }">
                            <div class="cel-metric-icon">
                                <i class="bi" :class="hasFailedQuiz ? 'bi-clipboard-x' : 'bi-shield-check'"></i>
                            </div>
                            <div class="cel-metric-body">
                                <strong>{{ quizValue }}</strong>
                                <span>{{ quizCaption }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Inspiration Quote Banner -->
                    <div class="cel-note" :class="hasFailedQuiz ? 'caution' : 'success'">
                        <div class="cel-note-icon">
                            <i class="bi" :class="hasFailedQuiz ? 'bi-exclamation-triangle-fill' : 'bi-quote'"></i>
                        </div>
                        <div class="cel-note-text">
                            {{ quote }}
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="cel-actions">
                        <button class="cel-btn secondary" type="button" @click="$emit('review')">
                            <i class="bi bi-bar-chart-fill"></i>
                            <span>Tinjau Hasil</span>
                        </button>
                        <button class="cel-btn primary" type="button" @click="$emit('goto-saya')">
                            <span>Kembali ke Functional Document</span>
                            <i class="bi bi-arrow-right cel-arrow-icon"></i>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </transition>
</template>

<script>
const QUOTES = [
    'Satu prosedur tuntas, satu standar kualitas tinggi makin terjaga!',
    'Konsistensi hari ini adalah pondasi keunggulan kerja esok hari.',
    'Prosedur yang dipahami dengan baik melahirkan hasil luar biasa.',
    'Terima kasih telah berkomitmen menjaga standar terbaik tim!',
    'Langkah hebat! Setiap detail yang diselesaikan membawa dampak nyata.',
];

export default {
    name: 'SopCompletionCelebration',
    props: {
        show: { type: Boolean, default: false },
        namaPaket: { type: String, default: 'Paket Dokumen' },
        totalItem: { type: Number, default: 0 },
        accent: { type: String, default: '#4f46e5' },
        completedAt: { type: String, default: '' },
        quizTotal: { type: Number, default: 0 },
        quizPassed: { type: Number, default: 0 },
        quizFailed: { type: Number, default: 0 },
    },
    emits: ['close', 'review', 'goto-saya'],
    data() {
        return {
            _raf: 0,
            quote: QUOTES[0],
            tiltX: 0,
            tiltY: 0,
            _particles: [],
            _ctx: null,
            _w: 0,
            _h: 0,
        };
    },
    computed: {
        hasFailedQuiz() {
            return this.quizFailed > 0;
        },
        title() {
            return this.hasFailedQuiz ? 'Alur Dokumen Berhasil Dituntaskan' : 'Selamat! Dokumen Berhasil Dituntaskan';
        },
        completedLabel() {
            if (!this.completedAt) return 'Baru saja';
            const dt = new Date(String(this.completedAt).replace(' ', 'T'));
            if (Number.isNaN(dt.getTime())) return 'Baru saja';
            return dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },
        quizValue() {
            if (!this.quizTotal) return 'Tuntas';
            if (this.hasFailedQuiz) return `${this.quizPassed}/${this.quizTotal}`;
            return 'Lulus 100%';
        },
        quizCaption() {
            return this.quizTotal ? 'Status Kuis' : 'Status Alur';
        },
        cardTransformStyle() {
            if (window.innerWidth < 768) return {};
            return {
                transform: `perspective(1000px) rotateX(${this.tiltY}deg) rotateY(${this.tiltX}deg)`,
            };
        },
    },
    mounted() {
        if (this.show) {
            this.quote = QUOTES[Math.abs(this.totalItem + (this.namaPaket || '').length) % QUOTES.length];
            this.$nextTick(() => {
                this.initCanvasAndBurst();
            });
        }
    },
    watch: {
        show(v) {
            if (v) {
                const idx = Math.abs(this.totalItem + (this.namaPaket || '').length) % QUOTES.length;
                this.quote = QUOTES[idx];
                this.$nextTick(() => {
                    this.$refs.closeBtn?.focus();
                    this.initCanvasAndBurst();
                });
            } else {
                cancelAnimationFrame(this._raf);
            }
        },
    },
    beforeUnmount() {
        cancelAnimationFrame(this._raf);
    },
    methods: {
        handleMouseMove(e) {
            if (window.innerWidth < 768) return;
            const card = e.currentTarget;
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            this.tiltX = (x / (rect.width / 2)) * 4;
            this.tiltY = -(y / (rect.height / 2)) * 4;
        },
        handleMouseLeave() {
            this.tiltX = 0;
            this.tiltY = 0;
        },
        handleMaskClick(e) {
            // Interactive Fireworks on Click anywhere on mask
            this.spawnBurst(e.clientX, e.clientY, 70, 16, 4);
        },
        initCanvasAndBurst() {
            const canvas = this.$refs.cv;
            if (!canvas) return;

            const w = window.innerWidth || document.documentElement.clientWidth || 800;
            const h = window.innerHeight || document.documentElement.clientHeight || 600;

            canvas.width = w;
            canvas.height = h;

            this._w = w;
            this._h = h;
            this._ctx = canvas.getContext('2d');
            this._particles = [];

            // Grand Fireworks & Confetti Sequence
            // Center Burst
            this.spawnBurst(w * 0.5, h * 0.35, 120, 18, 6);

            // Side Cannons (Shooting up from bottom corners)
            this.spawnCannon(0, h, 70, 60);
            this.spawnCannon(w, h, 70, 120);

            // Staggered additional bursts
            setTimeout(() => {
                if (this.show) {
                    this.spawnBurst(w * 0.25, h * 0.45, 80, 15, 8);
                    this.spawnCannon(0, h, 50, 50);
                }
            }, 300);

            setTimeout(() => {
                if (this.show) {
                    this.spawnBurst(w * 0.75, h * 0.45, 80, 15, 8);
                    this.spawnCannon(w, h, 50, 130);
                }
            }, 600);

            // Start animation loop
            this.startLoop();
        },
        createParticle(x, y, vx, vy, type = 'rect') {
            const colors = [
                this.accent || '#6366f1',
                '#10b981',
                '#f59e0b',
                '#ec4899',
                '#3b82f6',
                '#8b5cf6',
                '#06b6d4',
                '#ef4444',
                '#ffffff',
                '#ffd700',
            ];
            return {
                x,
                y,
                vx,
                vy,
                type, // 'rect', 'circle', 'star', 'ribbon'
                size: type === 'ribbon' ? 7 + Math.random() * 7 : 5 + Math.random() * 9,
                length: type === 'ribbon' ? 15 + Math.random() * 15 : 0,
                color: colors[(Math.random() * colors.length) | 0],
                rotation: Math.random() * Math.PI * 2,
                rotationSpeed: (Math.random() - 0.5) * 0.3,
                gravity: 0.12 + Math.random() * 0.12,
                drag: 0.965 + Math.random() * 0.02,
                oscillation: Math.random() * Math.PI * 2,
                oscSpeed: 0.04 + Math.random() * 0.06,
                life: 0,
                maxLife: 150 + Math.random() * 100,
            };
        },
        spawnBurst(cx, cy, count, spread, lift) {
            const types = ['rect', 'circle', 'star', 'ribbon'];
            for (let i = 0; i < count; i++) {
                const angle = Math.random() * Math.PI * 2;
                const speed = 2 + Math.random() * spread;
                const vx = Math.cos(angle) * speed;
                const vy = Math.sin(angle) * speed - lift;
                const type = types[(Math.random() * types.length) | 0];
                this._particles.push(this.createParticle(cx, cy, vx, vy, type));
            }
            if (this.show) this.startLoop();
        },
        spawnCannon(cx, cy, count, baseAngleDeg) {
            const types = ['rect', 'star', 'ribbon'];
            for (let i = 0; i < count; i++) {
                const angleRad = ((baseAngleDeg + (Math.random() - 0.5) * 40) * Math.PI) / 180;
                const speed = 14 + Math.random() * 15;
                const vx = Math.cos(angleRad) * speed;
                const vy = -Math.abs(Math.sin(angleRad) * speed);
                const type = types[(Math.random() * types.length) | 0];
                this._particles.push(this.createParticle(cx, cy, vx, vy, type));
            }
            if (this.show) this.startLoop();
        },
        renderStar(ctx, size) {
            ctx.beginPath();
            for (let i = 0; i < 5; i++) {
                ctx.lineTo(
                    Math.cos(((18 + i * 72) * Math.PI) / 180) * size,
                    -Math.sin(((18 + i * 72) * Math.PI) / 180) * size,
                );
                ctx.lineTo(
                    Math.cos(((54 + i * 72) * Math.PI) / 180) * (size / 2),
                    -Math.sin(((54 + i * 72) * Math.PI) / 180) * (size / 2),
                );
            }
            ctx.closePath();
            ctx.fill();
        },
        startLoop() {
            const tick = () => {
                if (!this._ctx || !this.show) return;
                const ctx = this._ctx;
                const w = this._w;
                const h = this._h;

                ctx.clearRect(0, 0, w, h);

                let alive = 0;
                for (let i = 0; i < this._particles.length; i++) {
                    const p = this._particles[i];
                    if (p.life >= p.maxLife) continue;

                    p.life++;
                    alive++;

                    p.vy += p.gravity;
                    p.vx *= p.drag;
                    p.vy *= p.drag;
                    p.oscillation += p.oscSpeed;
                    p.x += p.vx + Math.sin(p.oscillation) * 1.2;
                    p.y += p.vy;
                    p.rotation += p.rotationSpeed;

                    const opacity = Math.max(0, 1 - p.life / p.maxLife);

                    ctx.save();
                    ctx.globalAlpha = opacity;
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rotation);
                    ctx.fillStyle = p.color;

                    if (p.type === 'circle') {
                        ctx.beginPath();
                        ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
                        ctx.fill();
                    } else if (p.type === 'star') {
                        this.renderStar(ctx, p.size);
                    } else if (p.type === 'ribbon') {
                        ctx.fillRect(-p.size / 2, -p.length / 2, p.size, p.length);
                    } else {
                        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 1.5);
                    }
                    ctx.restore();
                }

                if (alive > 0 && this.show) {
                    this._raf = requestAnimationFrame(tick);
                }
            };

            cancelAnimationFrame(this._raf);
            this._raf = requestAnimationFrame(tick);
        },
    },
};
</script>

<style scoped>
/* Backdrop Overlay */
.cel-mask {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    background: radial-gradient(circle at center, rgba(15, 23, 42, 0.86) 0%, rgba(2, 6, 23, 0.96) 100%);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    cursor: pointer;
}

/* Interactive Fireworks Click Hint */
.cel-click-hint {
    position: absolute;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 1rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 999px;
    backdrop-filter: blur(8px);
    pointer-events: none;
    animation: cel-bounce 2s ease-in-out infinite;
}

/* Canvas Confetti & Fireworks */
.cel-cv {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
}

/* Main Card Container */
.cel-card {
    position: relative;
    z-index: 2;
    width: min(100%, 580px);
    max-height: calc(100vh - 2.5rem);
    overflow-y: auto;
    background: var(--hcis-white);
    border-radius: 28px;
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow:
        0 30px 60px -15px rgba(0, 0, 0, 0.4),
        0 0 90px -10px color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 45%, transparent);
    transition: transform 0.18s cubic-bezier(0.2, 0, 0, 1);
    animation: cel-card-pop 0.65s cubic-bezier(0.16, 1, 0.3, 1) both;
    scrollbar-width: thin;
    cursor: default;
}

/* Glowing Background Effects inside Card */
.cel-bg-effects {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    border-radius: 28px;
}

.cel-glow-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(55px);
    opacity: 0.28;
}

.cel-orb-1 {
    top: -90px;
    left: 50%;
    transform: translateX(-50%);
    width: 300px;
    height: 300px;
    background: var(--accent, var(--hcis-indigo-500));
    animation: cel-orb-pulse 4s ease-in-out infinite alternate;
}

.cel-orb-2 {
    bottom: -60px;
    right: -40px;
    width: 220px;
    height: 220px;
    background: var(--hcis-emerald-500);
    animation: cel-orb-pulse 5s ease-in-out infinite alternate-reverse;
}

.cel-orb-3 {
    top: 40%;
    left: -60px;
    width: 180px;
    height: 180px;
    background: var(--hcis-amber-500);
    animation: cel-orb-pulse 6s ease-in-out infinite alternate;
}

.cel-grid-pattern {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(99, 102, 241, 0.09) 1px, transparent 1px);
    background-size: 22px 22px;
    opacity: 0.7;
}

/* Card Main Content */
.cel-content {
    position: relative;
    z-index: 2;
    padding: 1.6rem 1.85rem 1.85rem;
}

/* Top Bar */
.cel-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.cel-status-line {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.cel-badge-success {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.38rem 0.85rem;
    background: var(--hcis-emerald-50);
    border: 1.5px solid var(--hcis-emerald-500);
    color: var(--hcis-emerald-700);
    font-size: 0.73rem;
    font-weight: 850;
    letter-spacing: 0.07em;
    border-radius: 999px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.cel-dot {
    width: 7px;
    height: 7px;
    background: var(--hcis-emerald-500);
    border-radius: 50%;
    box-shadow: 0 0 10px var(--hcis-emerald-500);
    animation: cel-dot-blink 1.4s infinite;
}

.cel-status-pct {
    font-size: 0.73rem;
    font-weight: 850;
    color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-100);
    padding: 0.38rem 0.85rem;
    border-radius: 999px;
    border: 1.5px solid var(--hcis-indigo-200);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
}

.cel-x {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1.5px solid var(--hcis-slate-200);
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.cel-x:hover {
    color: var(--hcis-slate-900);
    background: var(--hcis-slate-100);
    transform: rotate(90deg) scale(1.1);
}

/* Progress bar header animation */
.cel-progress {
    position: relative;
    height: 6px;
    margin-top: 1rem;
    background: var(--hcis-slate-100);
    border-radius: 999px;
    overflow: hidden;
}

.cel-progress-bar {
    display: block;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--accent, var(--hcis-indigo-500)), var(--hcis-pink-500), var(--hcis-amber-500));
    background-size: 300% 100%;
    transform-origin: left;
    animation:
        cel-progress-grow 1s cubic-bezier(0.16, 1, 0.3, 1) 0.15s both,
        cel-gradient-shift 3s linear infinite;
}

.cel-progress-glow {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.7);
}

/* Emblem & Trophy Hero Section */
.cel-emblem-wrapper {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 1.5rem auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cel-rays {
    position: absolute;
    inset: -25px;
    background: conic-gradient(
        from 0deg,
        transparent 0deg 30deg,
        color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 20%, transparent) 30deg 60deg,
        transparent 60deg 90deg,
        color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 20%, transparent) 90deg 120deg,
        transparent 120deg 150deg,
        color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 20%, transparent) 150deg 180deg,
        transparent 180deg 210deg,
        color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 20%, transparent) 210deg 240deg,
        transparent 240deg 270deg,
        color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 20%, transparent) 270deg 300deg,
        transparent 300deg 330deg,
        color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 20%, transparent) 330deg 360deg
    );
    border-radius: 50%;
    animation: cel-spin-slow 18s linear infinite;
    pointer-events: none;
}

.cel-ring {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 2px solid color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 50%, transparent);
    animation: cel-ripple-expand 2.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
}

.cel-ring-2 {
    animation-delay: 0.8s;
}
.cel-ring-3 {
    animation-delay: 1.6s;
}

.cel-halo-ring {
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--accent, var(--hcis-indigo-500)), var(--hcis-amber-500));
    opacity: 0.4;
    filter: blur(8px);
    animation: cel-spin-slow 10s linear infinite;
}

.cel-emblem-core {
    position: relative;
    width: 104px;
    height: 104px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--accent, var(--hcis-indigo-500)) 0%, var(--hcis-indigo-800) 100%);
    border: 6px solid var(--hcis-white);
    border-radius: 50%;
    box-shadow:
        0 14px 32px color-mix(in srgb, var(--accent, var(--hcis-indigo-500)) 50%, transparent),
        inset 0 -4px 8px rgba(0, 0, 0, 0.25);
    animation: cel-badge-pop 0.75s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
    overflow: hidden;
}

.cel-trophy-icon {
    font-size: 3rem;
    color: var(--hcis-amber-400);
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.35));
    animation: cel-float 3s ease-in-out infinite alternate;
}

.cel-shine-sweep {
    position: absolute;
    top: -50%;
    left: -60%;
    width: 40%;
    height: 200%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
    transform: rotate(25deg);
    animation: cel-shine-anim 3.2s ease-in-out 1s infinite;
}

.cel-check {
    position: absolute;
    right: 10px;
    bottom: 10px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: var(--hcis-white);
    border: 3.5px solid var(--hcis-white);
    border-radius: 50%;
    font-size: 1.2rem;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.45);
    animation: cel-check-pop 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) 0.6s both;
}

/* Floating Sparkles */
.cel-sparks-container {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.cel-spark {
    position: absolute;
    animation: cel-sparkle 2.2s ease-in-out infinite;
}

.cel-spark.s1 {
    top: -2px;
    left: 8px;
    color: var(--hcis-amber-400);
    font-size: 1.35rem;
    animation-delay: 0.1s;
}
.cel-spark.s2 {
    right: 2px;
    bottom: 20px;
    color: var(--hcis-emerald-500);
    font-size: 1.45rem;
    animation-delay: 0.7s;
}
.cel-spark.s3 {
    left: -8px;
    bottom: 30px;
    color: var(--hcis-blue-500);
    font-size: 0.95rem;
    animation-delay: 1.2s;
}
.cel-spark.s4 {
    top: 8px;
    right: 5px;
    color: var(--hcis-rose-500);
    font-size: 0.85rem;
    animation-delay: 0.4s;
}
.cel-spark.s5 {
    top: 48%;
    left: -18px;
    color: var(--hcis-purple-500);
    font-size: 1.1rem;
    animation-delay: 0.9s;
}
.cel-spark.s6 {
    top: -12px;
    right: 38%;
    color: var(--hcis-sky-400);
    font-size: 1rem;
    animation-delay: 1.4s;
}
.cel-spark.s7 {
    bottom: -8px;
    left: 35%;
    color: var(--hcis-amber-500);
    font-size: 0.9rem;
    animation-delay: 1.7s;
}

/* Header Text Section */
.cel-header-text {
    text-align: center;
}

.cel-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--accent, var(--hcis-indigo-500));
    font-size: 0.75rem;
    font-weight: 850;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    animation: cel-rise 0.5s ease 0.3s both;
}

.cel-title {
    margin: 0.35rem 0 0.55rem;
    font-size: 1.75rem;
    font-weight: 900;
    color: var(--hcis-slate-900);
    line-height: 1.2;
    letter-spacing: -0.025em;
    animation: cel-rise 0.5s ease 0.38s both;
}

.cel-sub {
    max-width: 470px;
    margin: 0 auto;
    color: var(--hcis-slate-600);
    font-size: 0.94rem;
    line-height: 1.55;
    animation: cel-rise 0.5s ease 0.44s both;
}

.cel-highlight {
    color: var(--hcis-slate-900);
    font-weight: 700;
}

/* Metrics Grid */
.cel-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-top: 1.4rem;
    animation: cel-rise 0.5s ease 0.5s both;
}

.cel-metric {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 0.95rem;
    background: var(--hcis-slate-50);
    border: 1.5px solid var(--hcis-slate-200);
    border-radius: 18px;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.cel-metric:hover {
    transform: translateY(-4px);
    background: var(--hcis-white);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.08);
}

.cel-metric-icon {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-indigo-100);
    color: var(--accent, var(--hcis-indigo-500));
    font-size: 1.25rem;
    border-radius: 14px;
}

.cel-metric-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.cel-metric-body strong {
    font-size: 1.05rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.cel-metric-body span {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-top: 0.15rem;
}

.cel-metric.caution .cel-metric-icon {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
}

.cel-metric.caution strong {
    color: var(--hcis-amber-700);
}

.cel-metric.success .cel-metric-icon {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-600);
}

/* Quote Note Banner */
.cel-note {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-top: 1.1rem;
    padding: 0.9rem 1.1rem;
    border-radius: 18px;
    font-size: 0.84rem;
    line-height: 1.5;
    animation: cel-rise 0.5s ease 0.56s both;
}

.cel-note.success {
    background: linear-gradient(135deg, var(--hcis-emerald-50) 0%, var(--hcis-green-50) 100%);
    border: 1.5px solid var(--hcis-emerald-200);
    color: var(--hcis-emerald-800);
}

.cel-note.caution {
    background: linear-gradient(135deg, var(--hcis-amber-50) 0%, var(--hcis-amber-100) 100%);
    border: 1.5px solid var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}

.cel-note-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
    margin-top: -0.1rem;
}

.cel-note-text {
    font-weight: 600;
}

/* Action Buttons */
.cel-actions {
    display: grid;
    grid-template-columns: 1fr 1.35fr;
    gap: 0.75rem;
    margin-top: 1.4rem;
    animation: cel-rise 0.5s ease 0.62s both;
}

.cel-btn {
    min-height: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    padding: 0.8rem 1.25rem;
    border-radius: 16px;
    font-size: 0.92rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.cel-btn.primary {
    background: linear-gradient(135deg, var(--hcis-slate-900) 0%, var(--hcis-slate-800) 100%);
    color: var(--hcis-white);
    border: 1.5px solid var(--hcis-slate-700);
    box-shadow: 0 10px 24px -5px rgba(15, 23, 42, 0.38);
}

.cel-btn.primary:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, var(--hcis-slate-800) 0%, var(--hcis-slate-700) 100%);
    box-shadow: 0 14px 28px -4px rgba(15, 23, 42, 0.48);
}

.cel-btn.primary:hover .cel-arrow-icon {
    transform: translateX(5px);
}

.cel-arrow-icon {
    transition: transform 0.2s ease;
}

.cel-btn.secondary {
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
    border: 1.5px solid var(--hcis-slate-300);
}

.cel-btn.secondary:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-400);
    color: var(--hcis-slate-900);
    transform: translateY(-2px);
}

/* Transitions */
.cel-fade-enter-active,
.cel-fade-leave-active {
    transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.cel-fade-enter-from,
.cel-fade-leave-to {
    opacity: 0;
}

/* Keyframe Animations */
@keyframes cel-bounce {
    0%,
    100% {
        transform: translateX(-50%) translateY(0);
    }
    50% {
        transform: translateX(-50%) translateY(-6px);
    }
}

@keyframes cel-card-pop {
    0% {
        opacity: 0;
        transform: translateY(40px) scale(0.92);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes cel-orb-pulse {
    0% {
        transform: translateX(-50%) scale(0.9);
        opacity: 0.2;
    }
    100% {
        transform: translateX(-50%) scale(1.15);
        opacity: 0.4;
    }
}

@keyframes cel-dot-blink {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.35;
    }
}

@keyframes cel-progress-grow {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}

@keyframes cel-gradient-shift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

@keyframes cel-spin-slow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes cel-ripple-expand {
    0% {
        transform: scale(0.8);
        opacity: 0.85;
    }
    100% {
        transform: scale(1.5);
        opacity: 0;
    }
}

@keyframes cel-badge-pop {
    0% {
        transform: scale(0) rotate(-45deg);
    }
    100% {
        transform: scale(1) rotate(0deg);
    }
}

@keyframes cel-float {
    0% {
        transform: translateY(0px) rotate(0deg);
    }
    50% {
        transform: translateY(-6px) rotate(2deg);
    }
    100% {
        transform: translateY(0px) rotate(0deg);
    }
}

@keyframes cel-shine-anim {
    0%,
    50% {
        left: -60%;
    }
    80%,
    100% {
        left: 140%;
    }
}

@keyframes cel-check-pop {
    0% {
        opacity: 0;
        transform: scale(0) rotate(-30deg);
    }
    100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

@keyframes cel-sparkle {
    0%,
    100% {
        opacity: 0.3;
        transform: scale(0.8) rotate(0deg);
    }
    50% {
        opacity: 1;
        transform: scale(1.3) rotate(15deg);
    }
}

@keyframes cel-rise {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Fallback CSS Confetti */
.cel-css-confetti {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
    z-index: 1;
}

.cel-confetti-piece {
    position: absolute;
    top: -20px;
    width: 8px;
    height: 14px;
    border-radius: 3px;
    opacity: 0;
    animation: cel-fall 4s linear infinite;
}

.cel-confetti-piece:nth-child(2n) {
    background: var(--hcis-blue-500);
}
.cel-confetti-piece:nth-child(3n) {
    background: var(--hcis-amber-500);
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.cel-confetti-piece:nth-child(4n) {
    background: var(--hcis-emerald-500);
}
.cel-confetti-piece:nth-child(5n) {
    background: var(--hcis-pink-500);
}
.cel-confetti-piece:nth-child(6n) {
    background: var(--accent, var(--hcis-indigo-500));
}

.cel-confetti-piece:nth-child(1) {
    left: 6%;
    animation-delay: 0.2s;
}
.cel-confetti-piece:nth-child(2) {
    left: 14%;
    animation-delay: 1.4s;
}
.cel-confetti-piece:nth-child(3) {
    left: 22%;
    animation-delay: 0.8s;
}
.cel-confetti-piece:nth-child(4) {
    left: 30%;
    animation-delay: 2.1s;
}
.cel-confetti-piece:nth-child(5) {
    left: 38%;
    animation-delay: 0.5s;
}
.cel-confetti-piece:nth-child(6) {
    left: 46%;
    animation-delay: 1.9s;
}
.cel-confetti-piece:nth-child(7) {
    left: 54%;
    animation-delay: 1.1s;
}
.cel-confetti-piece:nth-child(8) {
    left: 62%;
    animation-delay: 2.7s;
}
.cel-confetti-piece:nth-child(9) {
    left: 70%;
    animation-delay: 0.4s;
}
.cel-confetti-piece:nth-child(10) {
    left: 78%;
    animation-delay: 1.7s;
}
.cel-confetti-piece:nth-child(11) {
    left: 86%;
    animation-delay: 2.4s;
}
.cel-confetti-piece:nth-child(12) {
    left: 94%;
    animation-delay: 0.9s;
}

@keyframes cel-fall {
    0% {
        opacity: 0;
        transform: translateY(-10px) rotate(0deg);
    }
    15% {
        opacity: 0.9;
    }
    100% {
        opacity: 0;
        transform: translateY(650px) rotate(720deg);
    }
}

/* Responsive Styles */
@media (max-width: 640px) {
    .cel-mask {
        align-items: flex-end;
        padding: 0;
    }

    .cel-click-hint {
        top: 16px;
        bottom: auto;
        font-size: 0.72rem;
    }

    .cel-card {
        width: 100%;
        max-height: 90vh;
        border-radius: 28px 28px 0 0;
        border-bottom: none;
        animation: cel-card-slide-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    .cel-content {
        padding: 1.25rem 1.25rem 1.75rem;
    }

    .cel-emblem-wrapper {
        width: 120px;
        height: 120px;
        margin: 1rem auto 0.75rem;
    }

    .cel-emblem-core {
        width: 84px;
        height: 84px;
    }

    .cel-trophy-icon {
        font-size: 2.4rem;
    }

    .cel-check {
        width: 30px;
        height: 30px;
        font-size: 1rem;
        right: 6px;
        bottom: 6px;
    }

    .cel-title {
        font-size: 1.4rem;
    }

    .cel-sub {
        font-size: 0.86rem;
    }

    .cel-metrics {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .cel-metric {
        padding: 0.75rem 0.85rem;
    }

    .cel-actions {
        grid-template-columns: 1fr;
        gap: 0.6rem;
    }

    .cel-btn.primary {
        order: -1;
    }
}

@keyframes cel-card-slide-up {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}
</style>
