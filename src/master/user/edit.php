<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_role(['master']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$id = intval($_GET['id'] ?? 0);
$db = (new Database())->connect();
$userModel = new User($db);
$user = $userModel->find($id);

if (!$user) {
    header("Location: index.php");
    exit;
}

$page_title = "Edit User";
ob_start();
?>
<div class="bg-white p-6 rounded shadow">
    <h2 class="text-lg font-semibold mb-4">Edit User</h2>

    <form action="update.php" method="POST" class="space-y-3">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">
        <div>
            <label class="block text-sm">Name</label>
            <input name="name" value="<?= htmlspecialchars($user['name']) ?>" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-sm">Email</label>
            <input name="email" value="<?= htmlspecialchars($user['email']) ?>" type="email" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-sm">Phone</label>
            <input name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full border p-2 rounded">
        </div>
        <div>
            <label class="block text-sm">Role</label>
            <select name="role" class="w-full border p-2 rounded">
                <option value="user" <?= $user['role']==='user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $user['role']==='admin' ? 'selected' : '' ?>>Admin</option>
                <option value="master" <?= $user['role']==='master' ? 'selected' : '' ?>>Master</option>
            </select>
        </div>

        <div>
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            <a href="index.php" class="ml-2 text-gray-600">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout/main.php';
