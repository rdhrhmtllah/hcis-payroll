<template>
    <div class="input-group input-group-lg shadow-sm">
        <input 
            type="text" 
            class="form-control bg-light text-center border-end-0 fw-bold" 
            style="max-width: 80px; text-transform: uppercase;" 
            v-model="kodeWilayah" 
            placeholder="B" 
            maxlength="2"
            @input="updatePlat"
            :disabled="disabled"
        >
        <span class="input-group-text bg-light border-start-0 border-end-0">-</span>
        <input 
            type="text" 
            class="form-control bg-light text-center border-start-0 border-end-0 fw-bold" 
            v-model="nomor" 
            placeholder="1234" 
            maxlength="4"
            @input="updatePlat"
            @keypress="onlyNumbers"
            :disabled="disabled"
        >
        <span class="input-group-text bg-light border-start-0 border-end-0">-</span>
        <input 
            type="text" 
            class="form-control bg-light text-center border-start-0 fw-bold" 
            style="max-width: 90px; text-transform: uppercase;" 
            v-model="seriHuruf" 
            placeholder="KAA" 
            maxlength="3"
            @input="updatePlat"
            :disabled="disabled"
        >
    </div>
</template>

<script>
export default {
    name: 'PlatNomor',
    props: {
        modelValue: {
            type: String,
            default: ''
        },
        disabled: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            kodeWilayah: '',
            nomor: '',
            seriHuruf: ''
        };
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(newVal) {
                if (!newVal) {
                    this.kodeWilayah = '';
                    this.nomor = '';
                    this.seriHuruf = '';
                    return;
                }
                const parts = newVal.split('-');
                if (parts.length === 3) {
                    this.kodeWilayah = parts[0];
                    this.nomor = parts[1];
                    this.seriHuruf = parts[2];
                } else if (newVal.split(' ').length === 3) {
                    const spaces = newVal.split(' ');
                    this.kodeWilayah = spaces[0];
                    this.nomor = spaces[1];
                    this.seriHuruf = spaces[2];
                } else {
                    // Try to guess format or leave as is if invalid format
                }
            }
        }
    },
    methods: {
        updatePlat() {
            this.kodeWilayah = this.kodeWilayah.replace(/[^a-zA-Z]/g, '').toUpperCase();
            this.nomor = this.nomor.replace(/[^0-9]/g, '');
            this.seriHuruf = this.seriHuruf.replace(/[^a-zA-Z]/g, '').toUpperCase();
            
            const constructed = `${this.kodeWilayah}-${this.nomor}-${this.seriHuruf}`;
            if (this.kodeWilayah || this.nomor || this.seriHuruf) {
                this.$emit('update:modelValue', constructed);
            } else {
                this.$emit('update:modelValue', '');
            }
        },
        onlyNumbers(event) {
            const charCode = (event.which) ? event.which : event.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) {
                event.preventDefault();
            }
        }
    }
};
</script>
