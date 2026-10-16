<template>
    <span class="badge rounded-pill border sop-pill" :class="gaya ? '' : cls" :style="gaya">
        <span class="dot" v-if="dot"></span>{{ text }}
    </span>
</template>

<script>
import { PILL_VARIANT } from '../../support/constants.js';

/**
 * StatusPill — badge status generik.
 * Pakai: <StatusPill :variant="'ok'" text="Selesai" />
 * variant: ok | warn | bad | upd | idle | acc
 *
 * WARNA BEBAS (hex) diterima lewat prop `warna`, dan saat ada ia MENGGANTIKAN
 * variant sepenuhnya. Dibutuhkan sejak tingkat prioritas jadi master data:
 * warnanya ditentukan admin di database, jadi tak mungkin lagi dipetakan ke
 * segelintir kelas yang ditulis lebih dulu di kode.
 *
 * Latar & garisnya diturunkan dari hex yang sama lewat alfa — satu nilai dari
 * admin, bukan tiga yang harus ia serasikan sendiri.
 *
 * Nilai yang BUKAN hex sah — mis. sisa token lama 'bad' dari data sebelum
 * peralihan — diabaikan dan pil jatuh ke `variant`. Tanpa ini pil kehilangan
 * kelas SEKALIGUS gaya inline-nya, lalu tampil sebagai teks telanjang.
 */
export default {
    name: 'StatusPill',
    props: {
        text: { type: String, required: true },
        variant: { type: String, default: 'idle' },
        dot: { type: Boolean, default: true },
        /** Hex 6 digit, mis. #EF4444. Kosong = pakai variant. */
        warna: { type: String, default: '' },
    },
    computed: {
        cls() {
            return PILL_VARIANT[this.variant] || PILL_VARIANT.idle;
        },
        gaya() {
            if (!/^#[0-9a-f]{6}$/i.test(this.warna)) return null;

            return {
                color: this.warna,
                background: this.warna + '1f',
                borderColor: this.warna + '4d',
            };
        },
    },
};
</script>

<style scoped>
.sop-pill {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.28rem 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    display: inline-block;
}
</style>
