</div> <!-- Close .page-content -->
</main>
    <footer class="text-center py-4 text-slate-500 text-sm">
        <p>Copyright © <?php echo date("Y"); ?> <a href="https://itsupport.com.bd" target="_blank" class="text-cyan-400 hover:underline">IT Support BD</a>. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="assets/js/shared.js"></script>
    
    <!-- Modular Map Scripts -->
    <script src="assets/js/map/config.js"></script>
    <script src="assets/js/map/state.js"></script>
    <script src="assets/js/map/api.js"></script>
    <script src="assets/js/map/utils.js"></script>
    <script src="assets/js/map/ui.js"></script>
    <script src="assets/js/soundManager.js"></script>
    <script src="assets/js/map/deviceManager.js"></script>
    <script src="assets/js/map/mapManager.js"></script>
    <script src="assets/js/map/network.js"></script>
    <script src="assets/js/map.js"></script>
    
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/devices.js"></script>
    <script src="assets/js/history.js"></script>
    <script src="assets/js/users.js"></script>
    <script src="assets/js/status_logs.js"></script>
    <script src="assets/js/email_notifications.js"></script>
    <script src="assets/js/licenses.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Notyf for toast notifications
        window.notyf = new Notyf({
            duration: 3000,
            position: { x: 'right', y: 'top' },
            types: [
                { type: 'success', backgroundColor: '#22c5e', icon: { className: 'fas fa-check-circle', tagName: 'i', color: 'white' } },
                { type: 'error', backgroundColor: '#ef4444', icon: { className: 'fas fa-times-circle', tagName: 'i', color: 'white' } },
                { type: 'info', backgroundColor: '#3b82f6', icon: { className: 'fas fa-info-circle', tagName: 'i', color: 'white' } }
            ]
        });

        // The rest of the initialization is now handled by the React application in src/main.tsx
    });
    </script>
</body>
</html>