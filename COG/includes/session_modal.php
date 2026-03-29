<!-- includes/session_modal.php  –  include this just before </body> -->
<div class="modal fade" id="sessionWarningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clock-history me-2"></i>Session About to Expire
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-hourglass-split display-3 text-warning mb-3 d-block"></i>
                <p class="fs-5">Your session will expire in</p>
                <h2 class="fw-bold text-danger"><span id="sessionCountdown">120</span> seconds</h2>
                <p class="text-muted mt-2">Click "Stay Logged In" to continue your session.</p>
            </div>
            <div class="modal-footer justify-content-center border-0">
                <button id="stayLoggedInBtn" class="btn btn-success px-4">
                    <i class="bi bi-check-circle me-2"></i>Stay Logged In
                </button>
                <a href="/logout.php" class="btn btn-outline-danger px-4">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout Now
                </a>
            </div>
        </div>
    </div>
</div>