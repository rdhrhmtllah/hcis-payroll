/**
 * pageMeta — mixin kecil untuk header SOP.
 * Menyediakan `dayNight` & `formattedDate` yang sebelumnya diduplikasi di 8 halaman.
 * Pakai: `import pageMeta from "../../support/pageMeta"` lalu `mixins: [pageMeta]`.
 */
export default {
    computed: {
        dayNight() {
            const j = new Date().getHours();
            return j >= 6 && j < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            return new Intl.DateTimeFormat('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }).format(new Date());
        },
    },
};
