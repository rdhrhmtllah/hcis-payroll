<template>
    <div class="dframe" :class="mode">
        <!-- Desktop: Chrome Browser Mockup -->
        <template v-if="mode === 'desktop'">
            <div class="df-desktop">
                <div class="df-bar">
                    <div class="d-flex align-items-center gap-1.5 ms-1">
                        <span class="df-dot r"></span>
                        <span class="df-dot y"></span>
                        <span class="df-dot g"></span>
                    </div>
                    <div class="df-url ms-3">
                        <i class="bi bi-shield-lock-fill text-success"></i>
                        <span class="url-domain">https://hcis.internal</span
                        ><span class="url-path">/sop/reader/pratinjau</span>
                    </div>
                    <div class="ms-auto d-none d-sm-flex align-items-center gap-2 text-muted small pe-2">
                        <span class="badge-role"><i class="bi bi-person-check-fill me-1"></i> Tampilan Karyawan</span>
                    </div>
                </div>
                <div class="df-screen">
                    <slot />
                </div>
            </div>
        </template>

        <!-- Mobile: Mockup Smartphone Modern -->
        <template v-else>
            <div class="df-phone">
                <div class="df-notch"></div>
                <div class="df-phone-screen">
                    <slot />
                </div>
                <div class="df-home"></div>
            </div>
        </template>
    </div>
</template>

<script>
/** SopDeviceFrame — Frame Mockup Desktop Browser & Mobile HP untuk Tahap Tinjau & Publish. */
export default {
    name: 'SopDeviceFrame',
    props: { mode: { type: String, default: 'desktop' } },
};
</script>

<style scoped>
.dframe {
    display: flex;
    justify-content: center;
    width: 100%;
}

/* Desktop Mockup Browser */
.df-desktop {
    width: 100%;
    max-width: 1180px;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 20px;
    overflow: hidden;
    background: var(--hcis-white);
    box-shadow: 0 20px 60px -20px rgba(15, 23, 42, 0.22);
}
.df-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0.65rem 1.1rem;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid var(--border-soft, #eef2f7);
}
.df-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    display: inline-block;
}
.df-dot.r {
    background: #ff5f56;
}
.df-dot.y {
    background: #ffbd2e;
}
.df-dot.g {
    background: #27c93f;
}
.df-url {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-white);
    border: 1px solid var(--border, var(--hcis-slate-300));
    border-radius: 999px;
    padding: 0.2rem 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}
.url-domain {
    font-weight: 800;
    color: var(--hcis-slate-800);
}
.url-path {
    color: var(--hcis-slate-500);
}
.badge-role {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
}
.df-screen {
    padding: 1.2rem;
    background: var(--hcis-slate-50);
    max-height: 80vh;
    overflow-y: auto;
}

/* Mobile Mockup Smartphone */
.df-phone {
    position: relative;
    width: 410px;
    max-width: 100%;
    border: 12px solid var(--hcis-slate-900);
    border-radius: 44px;
    background: var(--hcis-slate-900);
    box-shadow: 0 25px 70px -25px rgba(15, 23, 42, 0.45);
    overflow: hidden;
}
.df-notch {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 38%;
    height: 24px;
    background: var(--hcis-slate-900);
    border-radius: 0 0 16px 16px;
    z-index: 20;
}
.df-phone-screen {
    background: var(--hcis-white);
    border-radius: 32px;
    overflow-y: auto;
    max-height: 75vh;
    padding: 1.4rem 0.4rem 0.6rem;
}
.df-home {
    height: 5px;
    width: 35%;
    background: rgba(255, 255, 255, 0.4);
    border-radius: 999px;
    margin: 0.35rem auto 0.15rem;
}

@media (max-width: 575px) {
    .df-phone {
        border-width: 8px;
        border-radius: 32px;
    }
}
</style>
