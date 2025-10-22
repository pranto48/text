<?php
require_once 'includes/functions.php';
portal_header("Welcome to IT Support BD Portal");
?>

<div class="text-center py-16 glass-card mb-8">
    <h1 class="text-5xl font-extrabold text-white mb-4">Welcome to the AMPNM License Portal</h1>
    <p class="text-xl text-gray-200 mb-8">Your one-stop solution for network monitoring licenses.</p>
    <a href="products.php" class="btn-glass-primary text-lg">Browse Licenses</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
    <div class="glass-card text-center p-6">
        <i class="fas fa-shield-alt text-5xl text-blue-300 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-white">Secure Licensing</h2>
        <p class="text-gray-200">Get genuine and secure license keys for your AMPNM application.</p>
    </div>
    <div class="glass-card text-center p-6">
        <i class="fas fa-chart-line text-5xl text-green-300 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-white">Flexible Plans</h2>
        <p class="text-gray-200">Choose from various license tiers to fit your network monitoring needs.</p>
    </div>
    <div class="glass-card text-center p-6">
        <i class="fas fa-headset text-5xl text-purple-300 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-white">Dedicated Support</h2>
        <p class="text-gray-200">Access our dedicated support team for any licensing or product queries.</p>
    </div>
</div>

<?php portal_footer(); ?>