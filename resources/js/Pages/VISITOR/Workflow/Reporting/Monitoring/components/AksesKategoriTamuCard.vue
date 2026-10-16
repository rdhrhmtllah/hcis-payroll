<template>
    <!-- ═══════════════════════════════════════════════════════════════════
         HAK KATEGORI TAMU — pindahan dari halaman Jadwal Kedatangan Tamu.

         Tanpa kartu ini, pemakai yang departemennya belum diberi hak melihat
         apa pun hanya menemukan tabel kosong tanpa sebab. Kosong yang tidak
         menjelaskan dirinya selalu terbaca sebagai "sistemnya rusak", dan
         yang dilaporkan ke tim bukan izin yang kurang melainkan fitur yang
         dikira mati.
         ═══════════════════════════════════════════════════════════════════ -->
    <HcisCard
        variant="glass"
        size="sm"
        icon="diagram-3-fill"
        :sentiment="kosong ? 'warning' : 'teal'"
        title="Akses Kategori Tamu Anda"
        :description="kosong
            ? 'Departemen Anda belum diberi hak melihat kategori tamu mana pun, sehingga daftar di bawah kosong.'
            : 'Kategori tamu yang boleh Anda lihat berasal dari departemen (role) Anda.'"
        :badge="kosong ? 'Belum ada hak' : ringkas"
        :badge-variant="kosong ? 'warning' : 'teal'"
        class="mb-2"
    >
        <div v-if="kosong" class="akses-kosong">
            <p class="akses-kosong__teks">
                Hubungi admin Visitor untuk menambahkan departemen Anda ke matriks tamu.
                Satu baris matriks cukup — tidak perlu perubahan aplikasi.
            </p>
        </div>

        <ul v-else class="akses-list">
            <li v-for="d in departemen" :key="d.nama" class="akses-row">
                <HcisIcon name="person-workspace" size="2xs" class="text-muted" />
                <span class="akses-row__nama">{{ d.nama }}</span>
                <span class="akses-row__hak">
                    <template v-if="d.lihat.length">
                        <HcisIcon name="eye" size="2xs" class="text-teal" />
                        {{ d.lihat.join(', ') }}
                    </template>
                    <template v-else>
                        <HcisIcon name="eye-slash" size="2xs" class="text-muted" />
                        tidak melihat kategori apa pun
                    </template>
                </span>
                <span v-if="d.ajukan.length" class="akses-row__ajukan">
                    <HcisIcon name="plus-circle" size="2xs" class="text-indigo-500" />
                    boleh mengajukan: {{ d.ajukan.join(', ') }}
                </span>
            </li>
        </ul>
    </HcisCard>
</template>

<script>
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';

export default {
    name: 'AksesKategoriTamuCard',
    components: { HcisCard, HcisIcon },
    props: {
        /** @type {{kode: string, label: string}[]} */
        kategori: { type: Array, default: () => [] },
        /** @type {{nama: string, lihat: string[], ajukan: string[]}[]} */
        departemen: { type: Array, default: () => [] },
    },
    computed: {
        kosong() {
            return this.kategori.length === 0;
        },
        ringkas() {
            // Label kategorinya, bukan cacahnya: "Regular, B2B Procurement"
            // menjawab pertanyaan yang sedang ada di kepala pemakai, sedangkan
            // "2 kategori" memaksanya membuka kartu untuk tahu yang mana.
            return this.kategori.map((k) => k.label).join(', ');
        },
    },
};
</script>

<style scoped>
.akses-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.akses-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.375rem;
    padding: 0.375rem 0.5rem;
    border-radius: var(--hcis-radius-sm, 0.5rem);
    background: var(--hcis-surface-1, rgba(255, 255, 255, 0.04));
    font-size: 0.75rem;
}

.akses-row__nama {
    font-weight: 600;
}

.akses-row__hak,
.akses-row__ajukan {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-text-muted, #94a3b8);
}

.akses-kosong__teks {
    margin: 0;
    padding: 0.5rem 0;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #94a3b8);
    max-width: 62ch;
}
</style>
