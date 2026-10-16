<template>
    <ul class="change-log mb-2 mt-2">
        <li v-for="(change, idx) in changes" :key="idx" class="change-item">
            <span class="change-field">{{ change.field }}</span>
            <div class="change-values">
                <span class="value-old">{{ change.oldValue }}</span>
                <i class="bi bi-arrow-right-short mt-customeY"></i>
                <span class="value-new">{{ change.newValue }}</span>
            </div>
        </li>
    </ul>
</template>

<script>
export default {
    name: "DetailChanges",
    props: { changes: { type: Array, required: true } },
};
</script>

<style scoped>
.mt-customeY {
    margin-top: -25px;
}
.change-log {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.change-item {
    background-color: #f5f7fa;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #e4e7ed;
}
.change-field {
    font-weight: 600;
    display: block;
    margin-bottom: 8px;
    color: #303133;
}
.change-values {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.value-old,
.value-new {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    /* Mencegah teks yang sangat panjang merusak layout */
    word-break: break-all;
}
.value-old {
    text-decoration: line-through;
    color: #f56c6c;
    background-color: #fef0f0;
}
.value-new {
    font-weight: bold;
    color: #67c23a;
    background-color: #f0f9eb;
}
.change-values i {
    font-size: 1.5rem;
    color: #909399;
}

/* --- [BARU] CSS KHUSUS MOBILE --- */
@media (max-width: 576px) {
    .change-item {
        padding: 0.75rem;
    }
    .change-field {
        font-size: 0.9rem;
    }
    .value-old,
    .value-new {
        font-size: 0.8rem; /* Perkecil font value agar lebih pas */
    }
}
</style>
