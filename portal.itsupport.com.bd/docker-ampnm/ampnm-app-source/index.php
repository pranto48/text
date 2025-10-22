<?php
require_once __DIR__ . '/includes/auth_check.php';
include __DIR__ . '/header.php';
?>

<main id="app">
    <div id="root">
        <!-- React application will be mounted here -->
        <div class="text-center py-16"><div class="loader mx-auto"></div><p class="mt-2 text-slate-400">Loading Application...</p></div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>