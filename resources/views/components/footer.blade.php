<!-- resources/views/partials/footer.blade.php -->
<footer>
    <div class="footer clearfix mb-0 text-muted">
        <div class="float-start">

        </div>
        <div class="float-end">
           <p>2026 &copy; Evo Group</p>
        </div>
    </div>
</footer>
<script src="/assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="/assets/compiled/js/app.js"></script>
@vite(['resources/js/shell.js'])
@stack('shellScripts')
{{-- <script src="/assets/extensions/apexcharts/apexcharts.min.js"></script> --}}
{{-- <script src="/assets/static/js/pages/dashboard.js"></script> --}}
{{-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> --}}
