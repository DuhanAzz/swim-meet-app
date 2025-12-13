<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_role(['master']);

$page_title = "Create User";
ob_start();
?>
<div class="bg-white p-6 rounded shadow">
    <h2 class="text-lg font-semibold mb-4">Create User</h2>

    <form action="store.php" method="POST" class="space-y-3">
        <div>
            <label class="block text-sm">Name</label>
            <input name="name" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-sm">Email</label>
            <input name="email" type="email" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-sm">Phone</label>
            <input name="phone" class="w-full border p-2 rounded">
        </div>
        <div>
            <label class="block text-sm">Role</label>
            <select name="role" class="w-full border p-2 rounded">
                <option value="user">User</option>
                <option value="admin">Admin</option>
                <option value="master">Master</option>
            </select>
        </div>

        <div>
            <label class="block text-sm">Password (default)</label>
            <input name="password" type="text" value="12345678" class="w-full border p-2 rounded">
            <small class="text-gray-500">Default password akan di-hash saat disimpan.</small>
        </div>

        <div>
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Create</button>
            <a href="index.php" class="ml-2 text-gray-600">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout/main.php';
