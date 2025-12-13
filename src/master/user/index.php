<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_role(['master']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$db = (new Database())->connect();
$userModel = new User($db);
$users = $userModel->all();

$page_title = "Manage Users";
ob_start();
?>

<div class="bg-white p-4 rounded shadow">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">Manage Users</h2>
        <a href="create.php" class="bg-blue-600 text-white px-3 py-1 rounded">+ Add User</a>
    </div>

    <table class="min-w-full bg-white">
        <thead>
            <tr class="text-left border-b">
                <th class="p-2">ID</th>
                <th class="p-2">Name</th>
                <th class="p-2">Email</th>
                <th class="p-2">Role</th>
                <th class="p-2">Phone</th>
                <th class="p-2">Created</th>
                <th class="p-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($u['id']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($u['name']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($u['role']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                    <td class="p-2"><?= htmlspecialchars($u['created_at']) ?></td>
                    <td class="p-2 space-x-2">
                        <a href="edit.php?id=<?= $u['id'] ?>" class="text-blue-600">Edit</a>
                        <a href="delete.php?id=<?= $u['id'] ?>" onclick="return confirm('Hapus user ini?')" class="text-red-600">Delete</a>
                        <a href="reset_password.php?id=<?= $u['id'] ?>" onclick="return confirm('Reset password ke default?')" class="text-green-600">Reset Password</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout/main.php';
