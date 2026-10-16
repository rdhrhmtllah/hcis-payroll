<template>
    <div v-if="riwayat.length" class="permit-koreksi-riwayat">
        <div v-for="r in riwayat" :key="r.kelompok" class="permit-koreksi-riwayat__item">
            <div class="d-flex justify-content-between gap-2 small">
                <strong class="text-slate-800">{{ r.nama }}</strong>
                <span class="text-muted font-tabular">{{ formatWaktu(r.waktu) }}</span>
            </div>
            <p class="small text-slate-600 mb-1">“{{ r.alasan }}”</p>
            <ul class="small mb-0 ps-3">
                <li v-for="p in r.perubahan" :key="p.field">
                    {{ label(p.field) }}:
                    <span class="text-decoration-line-through text-muted">{{ p.lama ?? '(kosong)' }}</span>
                    →
                    <strong>{{ p.baru ?? '(kosong)' }}</strong>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const LABEL = {
    KATEGORI_PERMIT: 'Jenis permit',
    PLAT_NOMOR: 'Plat nomor',
    MODEL_KENDARAAN: 'Model kendaraan',
    WARNA: 'Warna',
    TAHUN: 'Tahun',
    KETERANGAN: 'Keterangan',
};

export default {
    name: 'PermitKoreksiRiwayat',
    props: { riwayat: { type: Array, default: () => [] } },
    methods: {
        /** "2026-09-23 10:18:53.1060000" -> "23 Sep 2026, 10:18". Diurai sebagai teks: jam dinding server, tanpa geser zona waktu. */
        formatWaktu(nilai) {
            const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(nilai || ''));
            if (!m) return nilai || '-';
            return `${Number(m[3])} ${BULAN[Number(m[2]) - 1]} ${m[1]}, ${m[4]}:${m[5]}`;
        },
        label(field) {
            return LABEL[field] || field;
        },
    },
};
</script>

<style scoped>
.permit-koreksi-riwayat { display: flex; flex-direction: column; gap: 0.75rem; }
.permit-koreksi-riwayat__item { border-left: 3px solid var(--hcis-warning, #f59e0b); padding-left: 0.75rem; }
</style>
