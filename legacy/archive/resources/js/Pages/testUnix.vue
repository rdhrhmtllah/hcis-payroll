<template>
    <p>ID Laptop Unik: {{ fingerprint }}</p>
</template>

<script>
import FingerprintJS from "@fingerprintjs/fingerprintjs";
export default {
    mounted() {
        this.initFingerprint();
    },

    methods: {
        async initFingerprint() {
            // Cek localStorage dulu
            const saved = localStorage.getItem("deviceFingerprint");
            if (saved) {
                this.fingerprint = saved;
                console.log("Fingerprint dari localStorage:", saved);
            } else {
                // Load FingerprintJS agent
                const fp = await FingerprintJS.load();
                // Dapatkan visitorId
                const result = await fp.get();
                this.fingerprint = result.visitorId;
                localStorage.setItem("deviceFingerprint", result.visitorId);
                console.log("Fingerprint baru disimpan:", result.visitorId);
            }
        },
        async mounted() {
            await this.initFingerprint();
        },
    },
};
</script>

<style></style>
