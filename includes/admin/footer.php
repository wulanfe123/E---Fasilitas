    </div> 
</div> 
<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Admin Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== SIDEBAR TOGGLE =====
    const sidebarToggle = document.getElementById('sidebarToggle');
    const body = document.body;
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            body.classList.toggle('sb-sidenav-toggled');
            
            // Save state to localStorage
            if (body.classList.contains('sb-sidenav-toggled')) {
                localStorage.setItem('sb|sidebar-toggle', 'true');
            } else {
                localStorage.removeItem('sb|sidebar-toggle');
            }
        });
        
        // Load saved state
        if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
            body.classList.add('sb-sidenav-toggled');
        }
    }
    
    // ===== SIDEBAR SUBMENU ARROW ROTATION =====
    const submenuToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const arrow = this.querySelector('.sidebar-arrow');
            if (arrow) {
                arrow.style.transform = arrow.style.transform === 'rotate(180deg)' ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
    });
    
    // ===== TOOLTIPS INITIALIZATION =====
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // ===== SMOOTH SCROLL =====
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#!') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // ===== AUTO DISMISS ALERTS =====
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Console log for debugging
    console.log('%c✅ Pemfas Admin Dashboard Loaded', 'color: #16a34a; font-weight: bold; font-size: 14px;');
    console.log('User Role:', '<?= $role ?? "N/A"; ?>');
    console.log('Current Page:', '<?= basename($_SERVER["PHP_SELF"]); ?>');
});

// ===== GLOBAL FUNCTIONS =====

// Confirm Delete Function
function confirmDelete(message = 'Apakah Anda yakin ingin menghapus data ini?') {
    return confirm(message);
}

// Show Loading
function showLoading() {
    const loading = document.createElement('div');
    loading.id = 'globalLoading';
    loading.className = 'global-loading';
    loading.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    `;
    document.body.appendChild(loading);
}

// Hide Loading
function hideLoading() {
    const loading = document.getElementById('globalLoading');
    if (loading) {
        loading.remove();
    }
}

// Toast Notification
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}
</script>

<!-- Additional Page Scripts -->
<?php if (isset($additionalScripts) && is_array($additionalScripts)): ?>
    <?php foreach ($additionalScripts as $script): ?>
        <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
