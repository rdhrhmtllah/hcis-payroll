<template>
    <transition name="ctb">
        <div v-if="count" class="ctb">
            <div class="ctb-info">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>
                    <strong>{{ count }}</strong> transaksi dipilih
                    <template v-if="approved">
                        · <b class="ctb-approved">{{ approved }} sudah disetujui</b>
                    </template>
                    <!-- Pilihan bertahan lintas halaman & filter: katakan terus terang
                         berapa yang terpilih tapi sedang tidak tampil. -->
                    <template v-if="hidden">
                        · <span class="ctb-hidden">{{ hidden }} tidak tampil di halaman/filter ini</span>
                    </template>
                </span>
            </div>
            <div class="ctb-actions">
                <button class="ctb-btn ghost" @click="$emit('clear')">Bersihkan</button>
                <button class="ctb-btn danger" @click="$emit('cancel')">
                    <i class="bi bi-x-octagon-fill"></i> Batalkan Transaksi
                </button>
            </div>
        </div>
    </transition>
</template>

<script>
export default {
    name: 'CancelToolbar',
    props: {
        count: { type: Number, default: 0 },
        approved: { type: Number, default: 0 },
        hidden: { type: Number, default: 0 },
    },
    emits: ['clear', 'cancel'],
};
</script>

<style scoped>
/* <i> dijadikan flex container -> ::before jadi flex item, sehingga
   `vertical-align: -.125em` bawaan Bootstrap Icons (yang menggeser glyph turun ~2px)
   tidak lagi berlaku dan ikon benar-benar center. */
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.bi::before {
    display: block;
    line-height: 1;
}

.ctb {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
    padding: 0.6rem 0.85rem;
    border: 1px solid var(--hcis-red-300);
    border-radius: 12px;
    background: var(--hcis-red-50);
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.08);
}
.ctb-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    color: var(--hcis-red-800);
    font-size: 0.78rem;
}
.ctb-info i {
    font-size: 0.95rem;
    flex: none;
}
.ctb-approved {
    color: var(--hcis-red-600);
}
.ctb-hidden {
    color: var(--hcis-amber-700);
    font-weight: 600;
}
.ctb-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.ctb-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.42rem 0.8rem;
    border-radius: 9px;
    border: 1px solid transparent;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    transition: 0.15s;
    white-space: nowrap;
}
.ctb-btn.ghost {
    background: #fff;
    border-color: var(--hcis-red-200);
    color: var(--hcis-red-800);
}
.ctb-btn.ghost:hover {
    background: var(--hcis-red-100);
}
.ctb-btn.danger {
    background: var(--hcis-red-600);
    color: #fff;
}
.ctb-btn.danger:hover {
    background: var(--hcis-red-700);
}

.ctb-enter-active,
.ctb-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}
.ctb-enter-from,
.ctb-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

/* Di mobile toolbar dijadikan bar aksi tetap di bawah layar agar selalu terjangkau
   ibu jari dan tidak hilang saat men-scroll daftar panjang. */
@media (max-width: 767.98px) {
    .ctb {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1030;
        margin: 0;
        border-radius: 14px 14px 0 0;
        border-bottom: 0;
        box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.14);
    }
    .ctb-info,
    .ctb-actions {
        width: 100%;
    }
    .ctb-btn {
        flex: 1;
        justify-content: center;
    }
}
</style>
