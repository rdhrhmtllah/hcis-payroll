import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Lebar laci samping: penuh di layar kecil, ukuran tetap di layar lebar. */
export function useLebarLaci(lebar = '46rem') {
    const nilai = ref(lebar);
    const ukur = () => {
        nilai.value = window.innerWidth < 768 ? '100%' : lebar;
    };
    onMounted(() => {
        ukur();
        window.addEventListener('resize', ukur);
    });
    onBeforeUnmount(() => window.removeEventListener('resize', ukur));
    return nilai;
}
