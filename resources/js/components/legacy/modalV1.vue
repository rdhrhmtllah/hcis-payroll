<template>
    <HcisDialog
        :model-value="show"
        :title="title"
        :size="size"
        :close-on-overlay="closeOnOverlay"
        @close="close"
    >
        <slot />

        <template #footer>
            <HcisButton variant="ghost" @click="close">
                {{ isReadOnly ? "Tutup" : "Batal" }}
            </HcisButton>

            <HcisButton
                v-if="!isReadOnly"
                variant="primary"
                :loading="loading"
                @click="submit"
            >
                {{ submitText }}
            </HcisButton>
        </template>
    </HcisDialog>
</template>

<script>
/*
 * Adapter tipis di atas HcisDialog.
 *
 * API publiknya dipertahankan persis: prop `show`, `title`, `submitText`,
 * `loading`, `isReadOnly`, emit `close` dan `submit`, serta satu slot bawaan
 * untuk isi. Lima belas pemanggil memakai bentuk itu dan tidak satu pun perlu
 * diubah.
 *
 * Yang berpindah hanya isinya: kerangka modal, warna, dan jaraknya kini berasal
 * dari design system, bukan dari markup Bootstrap dengan gaya inline.
 *
 * Dua prop DITAMBAHKAN, bukan diganti: `size` dan `closeOnOverlay`. Keduanya
 * punya nilai bawaan yang menyamai perilaku lama, jadi pemanggil lama tidak
 * berubah. Keduanya ada untuk menggantikan satu-satunya pemanggil yang dulu
 * mengaturnya lewat CSS — masterPenjadwalan.vue menembak `.modal-dialog` milik
 * komponen ini dengan `:deep()` untuk melebarkannya dan mematikan penutupan
 * lewat backdrop. Kelas internal itu tidak ada lagi di sini, jadi caranya
 * dipindahkan ke prop yang memang dimaksudkan untuk itu.
 */
import { HcisDialog, HcisButton } from '@/components/ui';

export default {
    name: "ModalV1",
    components: { HcisDialog, HcisButton },
    props: {
        show: {
            type: Boolean,
            required: true,
        },
        title: {
            type: String,
            default: "",
        },
        submitText: {
            type: String,
            default: "Simpan",
        },
        loading: {
            type: Boolean,
            default: false,
        },
        isReadOnly: {
            type: Boolean,
            default: false,
        },

        // Bawaan 'lg' menyamai `modal-lg` pada versi Bootstrap-nya.
        size: {
            type: String,
            default: "lg",
        },
        closeOnOverlay: {
            type: Boolean,
            default: true,
        },
    },
    emits: ["close", "submit"],
    methods: {
        close() {
            this.$emit("close");
        },
        submit() {
            this.$emit("submit");
        },
    },
};
</script>
