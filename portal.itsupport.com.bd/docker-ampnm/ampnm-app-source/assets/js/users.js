function initUsers() {
    const API_URL = 'api.php';
    const usersTableBody = document.getElementById('usersTableBody');
    const usersLoader = document.getElementById('usersLoader');
    const createUserForm = document.getElementById('createUserForm');

    const api = {
        get: (action) => fetch(`${API_URL}?action=${action}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(res => res.json())
    };

    const loadUsers = async () => {
        usersLoader.classList.remove('hidden');
        usersTableBody.innerHTML = '';
        try {
            const users = await api.get('get_users');
            usersTableBody.innerHTML = users.map(user => `
                <tr class="border-b border-slate-700">
                    <td class="px-6 py-4 whitespace-nowrap text-white">${user.username}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-slate-400">${new Date(user.created_at).toLocaleString()}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        ${user.username !== 'admin' ? `<button class="delete-user-btn text-red-500 hover:text-red-400" data-id="${user.id}" data-username="${user.username}"><i class="fas fa-trash mr-2"></i>Delete</button>` : '<span class="text-slate-500">Cannot delete admin</span>'}
                    </td>
                </tr>
            `).join('');
        } catch (error) {
            console.error('Failed to load users:', error);
        } finally {
            usersLoader.classList.add('hidden');
        }
    };

    createUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = e.target.username.value;
        const password = e.target.password.value;
        if (!username || !password) return;

        const button = createUserForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';

        try {
            const result = await api.post('create_user', { username, password });
            if (result.success) {
                window.notyf.success('User created successfully.');
                createUserForm.reset();
                await loadUsers();
            } else {
                window.notyf.error(`Error: ${result.error}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred.');
            console.error(error);
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-user-plus mr-2"></i>Create User';
        }
    });

    usersTableBody.addEventListener('click', async (e) => {
        const button = e.target.closest('.delete-user-btn');
        if (button) {
            const { id, username } = button.dataset;
            if (confirm(`Are you sure you want to delete user "${username}"?`)) {
                const result = await api.post('delete_user', { id });
                if (result.success) {
                    window.notyf.success(`User "${username}" deleted.`);
                    await loadUsers();
                } else {
                    window.notyf.error(`Error: ${result.error}`);
                }
            }
        }
    });

    loadUsers();
}