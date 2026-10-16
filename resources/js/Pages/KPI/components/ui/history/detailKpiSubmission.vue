<template>
    <div class="kpi-submission-details">
        <div v-for="(kpi, index) in kpiData" :key="index" class="kpi-item-card">
            <div class="kpi-header">
                <span class="kpi-number">{{ index + 1 }}</span>
                <h5 class="kpi-parameter-title">{{ kpi.nama_parameter }}</h5>
            </div>
            <div class="kpi-body">
                <div class="kpi-info-grid">
                    <div class="info-item">
                        <span>Kategori BSC</span><strong>{{ kpi.bsc }}</strong>
                    </div>
                    <div class="info-item">
                        <span>Bobot</span><strong>{{ kpi.bobot }}%</strong>
                    </div>
                    <div class="info-item">
                        <span>Satuan</span><strong>{{ kpi.satuan }}</strong>
                    </div>
                    <div class="info-item full-width">
                        <span>Formula</span><strong>{{ kpi.formula }}</strong>
                    </div>
                    <div class="info-item full-width">
                        <span>Deskripsi</span>
                        <p>{{ kpi.deskripsi }}</p>
                    </div>
                </div>
                <div class="kpi-target-section">
                    <h6>Target Kinerja</h6>
                    <div
                        v-if="kpi.periodicity === 'yearly'"
                        class="target-table-container"
                    >
                        <el-table
                            :data="[kpi.target]"
                            stripe
                            border
                            size="small"
                        >
                            <el-table-column
                                v-for="month in months"
                                :key="month"
                                :prop="month"
                                :label="
                                    month.charAt(0).toUpperCase() +
                                    month.slice(1)
                                "
                            />
                        </el-table>
                    </div>
                    <div
                        v-if="kpi.periodicity === 'quarterly'"
                        class="target-grid"
                    >
                        <div class="target-item">
                            <span>Q1</span><strong>{{ kpi.target.q1 }}</strong>
                        </div>
                        <div class="target-item">
                            <span>Q2</span><strong>{{ kpi.target.q2 }}</strong>
                        </div>
                        <div class="target-item">
                            <span>Q3</span><strong>{{ kpi.target.q3 }}</strong>
                        </div>
                        <div class="target-item">
                            <span>Q4</span><strong>{{ kpi.target.q4 }}</strong>
                        </div>
                    </div>
                    <div
                        v-if="kpi.periodicity === 'monthly'"
                        class="target-grid"
                    >
                        <div class="target-item single">
                            <span>Target Bulan Ini</span
                            ><strong>{{ kpi.target }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ElTable, ElTableColumn } from "element-plus";
export default {
    name: "DetailKpiSubmission",
    components: { ElTable, ElTableColumn },
    props: {
        kpiData: { type: Array, required: true },
    },
    data() {
        return {
            months: [
                "jan",
                "feb",
                "mar",
                "apr",
                "mei",
                "jun",
                "jul",
                "agu",
                "sep",
                "okt",
                "nov",
                "des",
            ],
        };
    },
};
</script>

<style scoped>
.kpi-submission-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.kpi-item-card {
    background-color: #fcfcfc;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    overflow: hidden;
}
.kpi-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background-color: #f5f7fa;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e4e7ed;
}
.kpi-number {
    background-color: var(--color-primary);
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: grid;
    place-items: center;
    font-size: 0.8rem;
    font-weight: bold;
    flex-shrink: 0; /* Mencegah ikon mengecil */
}
.kpi-parameter-title {
    margin: 0;
    font-size: 1rem;
    color: #303133;
}
.kpi-body {
    padding: 1rem;
}
.kpi-info-grid {
    display: grid;
    /* Default 2 kolom, akan diubah di mobile */
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.info-item span {
    display: block;
    font-size: 0.8rem;
    color: #909399;
    margin-bottom: 4px;
}
.info-item strong,
.info-item p {
    margin: 0;
    color: #606266;
    word-break: break-word; /* Mencegah teks panjang meluber */
}
.info-item.full-width {
    grid-column: 1 / -1;
}
.kpi-target-section h6 {
    margin: 0 0 0.75rem 0;
    font-size: 0.9rem;
    color: #303133;
    font-weight: 600;
}
.target-grid {
    display: grid;
    /* Default 4 kolom, akan diubah di mobile */
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}
.target-item {
    background: #f0f2f5;
    text-align: center;
    padding: 0.5rem;
    border-radius: 4px;
}
.target-item span {
    display: block;
    font-size: 0.75rem;
    color: #909399;
}
.target-item strong {
    font-size: 1rem;
    color: #303133;
}
.target-item.single {
    grid-column: 1 / -1;
}
:deep(.el-table) {
    font-size: 0.8rem !important;
}

/* --- [BARU] CSS KHUSUS MOBILE --- */
@media (max-width: 576px) {
    .kpi-body {
        padding: 0.75rem;
    }
    .kpi-info-grid {
        /* Ubah jadi 1 kolom di mobile */
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    .target-grid {
        /* Ubah jadi 2 kolom agar tidak terlalu panjang ke bawah */
        grid-template-columns: 1fr 1fr;
    }
    .kpi-parameter-title {
        font-size: 0.95rem; /* Sedikit perkecil font judul */
    }
    /* Membuat tabel tahunan bisa di-scroll horizontal jika tidak muat */
    .target-table-container {
        overflow-x: auto;
    }
}
</style>
