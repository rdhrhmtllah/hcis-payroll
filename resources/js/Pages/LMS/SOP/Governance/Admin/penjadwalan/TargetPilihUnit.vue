<template>
    <div class="tpu">
        <HcisContextBanner
            title="Penugasan Berdasarkan Unit Organisasi"
            description="Tugaskan berdasarkan kriteria unit kerja. Daftar karyawan diselesaikan di server secara otomatis — sangat ideal untuk penugasan skala besar (satu departemen, satu divisi, atau satu lokasi kerja)."
            sentiment="brand"
            variant="subtle"
            icon="diagram-3"
            class="mb-3"
        />

        <div class="tpu-grid">
            <div class="tpu-f">
                <label class="sop-label">Departemen</label>
                <FilterPilihSemua
                    v-model="lokal.dept"
                    :opsi="opsi.departemen || []"
                    placeholder="Semua Departemen"
                    @change="ubah"
                />
            </div>
            <div class="tpu-f">
                <label class="sop-label">Divisi</label>
                <FilterPilihSemua
                    v-model="lokal.divisi"
                    :opsi="opsi.divisi || []"
                    placeholder="Semua Divisi"
                    @change="ubah"
                />
            </div>
            <div class="tpu-f">
                <label class="sop-label">Lokasi Kerja</label>
                <FilterPilihSemua
                    v-model="lokal.lokasi"
                    :opsi="opsi.lokasi || []"
                    placeholder="Semua Lokasi"
                    @change="ubah"
                />
            </div>
            <div class="tpu-f">
                <label class="sop-label">Level</label>
                <FilterPilihSemua
                    v-model="lokal.level"
                    :opsi="opsi.level || []"
                    placeholder="Semua Level"
                    @change="ubah"
                />
            </div>
            <div class="tpu-f">
                <label class="sop-label">Jabatan</label>
                <FilterPilihSemua
                    v-model="lokal.jabatan"
                    :opsi="opsi.jabatan || []"
                    placeholder="Semua Jabatan"
                    @change="ubah"
                />
            </div>
            <div class="tpu-f">
                <label class="sop-label">Tipe</label>
                <el-select v-model="lokal.tipe" style="width: 100%" @change="ubah">
                    <el-option
                        v-for="t in opsi.tipe || ['Semua']"
                        :key="t"
                        :label="t === 'Semua' ? 'Semua tipe' : t"
                        :value="t"
                    />
                </el-select>
            </div>
        </div>

        <!-- Pratinjau dampak: satu-satunya cara admin tahu apa yang akan terjadi
             sebelum menekan kirim, karena daftar orangnya tak ditampilkan. -->
        <div class="tpu-preview" :class="{ kosong: !memuat && ringkas.total === 0 }">
            <div v-if="memuat" class="tpu-loading">
                <span class="spinner-border spinner-border-sm"></span> Menghitung…
            </div>
            <template v-else>
                <div class="tpu-stat">
                    <div class="tpu-big tnum">{{ ringkas.total }}</div>
                    <div class="tpu-lab">karyawan cocok</div>
                </div>
                <div class="tpu-split">
                    <div class="tpu-item ok">
                        <span class="tnum">{{ ringkas.baru }}</span>
                        <small>akan ditugaskan</small>
                    </div>
                    <div class="tpu-item warn">
                        <span class="tnum">{{ ringkas.sudah_assign }}</span>
                        <small>sudah punya, dilewati</small>
                    </div>
                </div>
                <div v-if="(ringkas.contoh_sudah || []).length" class="tpu-contoh">
                    Sudah ter-assign: {{ ringkas.contoh_sudah.join(', ')
                    }}{{ ringkas.sudah_assign > ringkas.contoh_sudah.length ? ', …' : '' }}
                </div>
                <div v-if="ringkas.total === 0" class="tpu-contoh">
                    Belum ada saringan — pilih minimal satu unit di atas.
                </div>
            </template>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import FilterPilihSemua from './FilterPilihSemua.vue';

const API = '/api/v1/sop/admin/penjadwalan';

/**
 * Mode target 2 — pilih unit organisasi.
 *
 * v-model = objek saringan ({dept[], divisi[], lokasi[], level[], jabatan[], tipe}),
 * BUKAN daftar kode: itulah intinya. Kode di-resolve sekali di server saat dispatch
 * lalu di-snapshot ke payload task, jadi ribuan kode tak pernah bolak-balik lewat
 * kabel dan retry job tak me-resolve ulang terhadap data organisasi yang berubah.
 */
import HcisContextBanner from '../../../../../../components/ui/HcisContextBanner.vue';

export default {
    name: 'TargetPilihUnit',
    components: { FilterPilihSemua, HcisContextBanner },
    props: {
        modelValue: { type: Object, default: () => ({}) },
        idPaket: { type: String, default: '' },
        opsi: { type: Object, default: () => ({}) },
    },
    emits: ['update:modelValue', 'ringkas'],
    data() {
        return {
            lokal: {
                dept: [],
                divisi: [],
                lokasi: [],
                level: [],
                jabatan: [],
                tipe: 'Semua',
                ...(this.modelValue || {}),
            },
            ringkas: { total: 0, baru: 0, sudah_assign: 0, contoh_sudah: [] },
            memuat: false,
            timer: null,
        };
    },
    computed: {
        adaSaringan() {
            const l = this.lokal;
            return !!(
                l.dept.length ||
                l.divisi.length ||
                l.lokasi.length ||
                l.level.length ||
                l.jabatan.length ||
                l.tipe !== 'Semua'
            );
        },
    },
    watch: {
        idPaket() {
            this.hitungDebounce();
        },
    },
    mounted() {
        if (this.adaSaringan) this.hitungDebounce();
    },
    beforeUnmount() {
        clearTimeout(this.timer);
    },
    methods: {
        ubah() {
            this.$emit('update:modelValue', { ...this.lokal });
            this.hitungDebounce();
        },
        hitungDebounce() {
            clearTimeout(this.timer);
            this.timer = setTimeout(this.hitung, 350);
        },
        async hitung() {
            if (!this.idPaket || !this.adaSaringan) {
                this.ringkas = { total: 0, baru: 0, sudah_assign: 0, contoh_sudah: [] };
                this.$emit('ringkas', this.ringkas);
                return;
            }
            this.memuat = true;
            try {
                const l = this.lokal;
                const r = await axios.get(`${API}/options/kandidat-ringkas`, {
                    params: {
                        paket: this.idPaket,
                        tipe: l.tipe,
                        dept: l.dept.length ? l.dept : undefined,
                        divisi: l.divisi.length ? l.divisi : undefined,
                        lokasi: l.lokasi.length ? l.lokasi : undefined,
                        level: l.level.length ? l.level : undefined,
                        jabatan: l.jabatan.length ? l.jabatan : undefined,
                    },
                });
                if (r.data.success) {
                    this.ringkas = r.data.result;
                    this.$emit('ringkas', this.ringkas);
                }
            } catch (e) {
                /* diam — pratinjau bukan jalur kritis */
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
.tpu-lead {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
    line-height: 1.5;
    margin-bottom: 0.9rem;
}
.tpu-lead i {
    color: var(--hcis-indigo-500);
    margin-right: 0.3rem;
}
.tpu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 0.7rem;
}
.tpu-f .sop-label {
    margin-bottom: 0.25rem;
}

.tpu-preview {
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(129, 140, 248, 0.04));
    border: 1px solid rgba(99, 102, 241, 0.18);
}
.tpu-preview.kosong {
    background: var(--surface-muted, var(--hcis-slate-50));
    border-color: var(--border-soft, #eef2f7);
}
.tpu-loading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--hcis-slate-500);
}
.tpu-stat {
    text-align: center;
}
.tpu-big {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    line-height: 1;
}
.tpu-lab {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
}
.tpu-split {
    display: flex;
    gap: 0.6rem;
    margin-top: 0.8rem;
}
.tpu-item {
    flex: 1;
    text-align: center;
    padding: 0.5rem;
    border-radius: 10px;
    background: #fff;
}
.tpu-item span {
    display: block;
    font-size: 1.15rem;
    font-weight: 800;
}
.tpu-item small {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
}
.tpu-item.ok span {
    color: var(--hcis-emerald-700);
}
.tpu-item.warn span {
    color: var(--hcis-amber-700);
}
.tpu-contoh {
    margin-top: 0.6rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    text-align: center;
}
</style>
