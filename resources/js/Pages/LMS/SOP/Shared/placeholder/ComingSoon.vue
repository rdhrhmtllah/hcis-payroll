<template>
    <div class="container-fluid px-0">
        <div class="card border-0 shadow-sm text-center py-5 my-4">
            <div class="card-body">
                <i class="bi bi-cone-striped fs-1 text-warning"></i>
                <h4 class="mt-3 fw-bold">{{ fitur }}</h4>
                <p class="text-muted mb-0">
                    Halaman ini digarap oleh LEAD (bagian reuse). Placeholder agar tautan tidak 404 selama pengembangan.
                </p>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * ComingSoon — placeholder untuk halaman milik LEAD (Reader, Checkpoint).
 * Dipakai oleh route seam di routes/LMS/SOP/Employee/SopEmployee.php.
 */
export default {
    name: 'SopComingSoon',
    props: {
        fitur: { type: String, default: 'Fitur' },
    },
};
</script>
