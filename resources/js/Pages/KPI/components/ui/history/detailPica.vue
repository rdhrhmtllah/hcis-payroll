<template>
    <div class="pica-box">
        <strong class="detail-label"
            ><i class="bi bi-file-earmark-medical-fill"></i> PICA untuk "{{
                picaData.parameter
            }}"</strong
        >
        <div class="pica-content">
            <strong>Masalah:</strong>
            <p>{{ picaData.problem }}</p>
            <strong>Tindakan Perbaikan:</strong>
            <p>{{ picaData.action }}</p>
        </div>
    </div>
</template>
<script>
export default {
    name: "DetailPica",
    props: { picaData: { type: Object, required: true } },
};
</script>
<style scoped>
.pica-box {
    background-color: #fdf6ec;
    border: 1px solid #faecd8;
    border-radius: 8px;
    padding: 15px;
}
.detail-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #e6a23c;
    margin-bottom: 10px;
}
.pica-content strong {
    display: block;
    margin-top: 10px;
    font-size: 0.85rem;
}
.pica-content p {
    margin: 4px 0 0 0;
}
</style>
