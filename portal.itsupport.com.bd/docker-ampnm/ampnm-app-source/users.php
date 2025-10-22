<?php
require_once __DIR__ . '/includes/auth_check.php';
include __DIR__ . '/header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">User Management</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Create User Form -->
            <div class="md:col-span-1">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Create New User</h2>
                    <form id="createUserForm" class="space-y-4">
                        <div>
                            <label for="new_username" class="block text-sm font-medium text-slate-300 mb-1">Username</label>
                            <input type="text" id="new_username" name="username" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                        </div>
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                            <input type="password" id="new_password" name="password" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                        </div>
                        <button type="submit" class="w-full px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                            <i class="fas fa-user-plus mr-2"></i>Create User
                        </button>
                    </form>
                </div>
            </div>

            <!-- User List -->
            <div class="md:col-span-2">
                <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Existing Users</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="border-b border-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Created At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- User rows will be inserted here by JavaScript -->
                            </tbody>
                        </table>
                        <div id="usersLoader" class="text-center py-8"><div class="loader mx-auto"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>