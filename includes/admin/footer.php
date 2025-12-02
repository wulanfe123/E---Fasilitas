        </main>
        <br><br><br><br><br><br><br>
        <footer class="py-3 bg-light mt-auto text-center">
            <div class="small text-muted">
                © <?= date('Y') ?> E-Fasilitas Kampus — Politeknik Negeri Bengkalis
            </div>
        </footer>
    </div> 
</div> <!-- END layoutSidenav -->

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>

<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.body.classList.toggle('sb-sidenav-toggled');
});
</script>

</body>
</html>
