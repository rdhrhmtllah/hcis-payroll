<template>
    <div class="reason-box">
        <strong class="detail-label"
            ><i class="bi bi-exclamation-octagon-fill"></i> Alasan
            Penolakan:</strong
        >
        <p class="reason-text">{{ reason }}</p>
    </div>
</template>
<script>
export default {
    name: "DetailRejection",
    props: { reason: { type: String, required: true } },
};
</script>
<style scoped>
.reason-box {
    background-color: #fef0f0;
    border: 1px solid #fde2e2;
    border-radius: 8px;
    padding: 15px;
}
.detail-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #f56c6c;
    margin-bottom: 5px;
}
.reason-text {
    margin: 0;
    line-height: 1.6;
    color: #c45656;
}
</style>
