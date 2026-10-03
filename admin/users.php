<?php
require_once dirname(__DIR__) . '/includes/data.php';
require_super_admin();

$currentUser = current_user();

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf)) {
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'editor';
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($username) || empty($password)) {
            header('Location: users.php?error=' . urlencode('Name, username, and password are required.'));
            exit;
        }

        $res = save_user([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'password' => $password
        ]);

        if ($res['success']) {
            header('Location: users.php?msg=' . urlencode('User account ' . htmlspecialchars($username) . ' created successfully!'));
            exit;
        } else {
            header('Location: users.php?error=' . urlencode($res['message']));
            exit;
        }
    } elseif ($action === 'edit_user') {
        $id = $_POST['user_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'editor';
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        // Safeguard: cannot deactivate self
        if ($currentUser['id'] === $id && $status === 'inactive') {
            header('Location: users.php?error=' . urlencode('You cannot deactivate your own account.'));
            exit;
        }

        $data = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ];
        if (!empty($password)) {
            $data['password'] = $password;
        }

        $res = save_user($data);
        if ($res['success']) {
            header('Location: users.php?msg=' . urlencode('User account updated successfully!'));
            exit;
        } else {
            header('Location: users.php?error=' . urlencode($res['message']));
            exit;
        }
    } elseif ($action === 'delete_user') {
        $id = $_POST['user_id'] ?? '';
        $res = delete_user($id);
        if ($res['success']) {
            header('Location: users.php?msg=' . urlencode('User deleted successfully.'));
            exit;
        } else {
            header('Location: users.php?error=' . urlencode($res['message']));
            exit;
        }
    }
}

$pageTitle = 'Access Control & User Management';
require_once __DIR__ . '/header.php';

$users = get_all_users();
?>

<!-- User Management Table Card -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3>System Administrators & Editors (<?= count($users) ?>)</h3>
            <span style="font-size: 13px; color: var(--admin-text-muted);">Manage user roles, access permissions, and account passwords</span>
        </div>
        <div>
            <button type="button" class="btn-adm btn-adm-primary" id="openAddUserModalBtn">
                <span>+ Add New User</span>
            </button>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Avatar</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $isSelf = ($currentUser['id'] === $u['id']);
                ?>
                    <tr>
                        <td>
                            <div class="user-avatar" style="width: 34px; height: 34px; font-size: 13px; background: <?= $u['role'] === 'super_admin' ? '#07274d' : '#00b4d8' ?>;">
                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                            </div>
                        </td>
                        <td>
                            <strong style="color: var(--admin-navy); font-size: 14px;"><?= htmlspecialchars($u['name']) ?></strong>
                            <?php if ($isSelf): ?>
                                <span class="badge-adm badge-adm-blue" style="font-size: 10px; margin-left: 6px;">You</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars($u['username']) ?></code>
                        </td>
                        <td style="color: #475569;">
                            <?= htmlspecialchars($u['email']) ?>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'super_admin'): ?>
                                <span class="badge-adm badge-adm-blue">Super Admin</span>
                            <?php else: ?>
                                <span class="badge-adm" style="background: #f1f5f9; color: #475569;">Content Editor</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($u['status'] ?? 'active') === 'active'): ?>
                                <span class="badge-adm badge-adm-success">Active</span>
                            <?php else: ?>
                                <span class="badge-adm badge-adm-warning">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: #64748b; font-size: 12px;">
                            <?= format_date($u['created_at']) ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 6px;">
                                <button type="button" class="btn-adm btn-adm-outline edit-user-btn" 
                                        style="padding: 5px 9px; font-size: 12px;"
                                        data-id="<?= htmlspecialchars($u['id']) ?>"
                                        data-name="<?= htmlspecialchars($u['name']) ?>"
                                        data-username="<?= htmlspecialchars($u['username']) ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>"
                                        data-role="<?= htmlspecialchars($u['role']) ?>"
                                        data-status="<?= htmlspecialchars($u['status'] ?? 'active') ?>"
                                        title="Edit User">
                                    ✏️ Edit
                                </button>

                                <?php if (!$isSelf): ?>
                                    <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete user <?= htmlspecialchars($u['username']) ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                        <button type="submit" class="btn-adm btn-adm-outline" style="padding: 5px 9px; font-size: 12px; color: #ef4444;" title="Delete User">
                                            🗑️
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New User -->
<div class="modal-overlay" id="addUserModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #fff; width: 100%; max-width: 480px; border-radius: 16px; padding: 32px; box-shadow: var(--admin-shadow-lg); position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 19px; color: var(--admin-navy);">+ Add New User Account</h3>
            <button type="button" class="close-modal-btn" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>

        <form method="POST" action="users.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="create_user">

            <div class="form-group">
                <label class="form-label" for="addName">Full Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="addName" name="name" class="form-control-adm" placeholder="John Tan" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="addUsername">Username <span style="color: #ef4444;">*</span></label>
                <input type="text" id="addUsername" name="username" class="form-control-adm" placeholder="johntan" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="addEmail">Email Address</label>
                <input type="email" id="addEmail" name="email" class="form-control-adm" placeholder="johntan@tabeebgroup.com">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" for="addRole">Role:</label>
                    <select id="addRole" name="role" class="form-control-adm">
                        <option value="editor">Content Editor</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="addStatus">Status:</label>
                    <select id="addStatus" name="status" class="form-control-adm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="form-label" for="addPassword" style="margin-bottom: 0;">Password <span style="color: #ef4444;">*</span></label>
                    <button type="button" id="genPwBtn" style="background: none; border: none; color: #0284c7; font-size: 11px; cursor: pointer; font-weight: 600;">⚡ Generate Strong Password</button>
                </div>
                <input type="text" id="addPassword" name="password" class="form-control-adm" placeholder="••••••••••••" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                <button type="button" class="btn-adm btn-adm-outline close-modal-btn">Cancel</button>
                <button type="submit" class="btn-adm btn-adm-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Existing User -->
<div class="modal-overlay" id="editUserModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #fff; width: 100%; max-width: 480px; border-radius: 16px; padding: 32px; box-shadow: var(--admin-shadow-lg); position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 19px; color: var(--admin-navy);">✏️ Edit User Account</h3>
            <button type="button" class="close-modal-btn" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>

        <form method="POST" action="users.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="form-group">
                <label class="form-label">Username:</label>
                <input type="text" id="editUsername" class="form-control-adm" readonly style="background: #f8fafc; color: #64748b;">
            </div>

            <div class="form-group">
                <label class="form-label" for="editName">Full Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="editName" name="name" class="form-control-adm" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="editEmail">Email Address</label>
                <input type="email" id="editEmail" name="email" class="form-control-adm">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" for="editRole">Role:</label>
                    <select id="editRole" name="role" class="form-control-adm">
                        <option value="editor">Content Editor</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="editStatus">Status:</label>
                    <select id="editStatus" name="status" class="form-control-adm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="editPassword">Reset Password (leave empty to keep current):</label>
                <input type="text" id="editPassword" name="password" class="form-control-adm" placeholder="Enter new password if changing">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                <button type="button" class="btn-adm btn-adm-outline close-modal-btn">Cancel</button>
                <button type="submit" class="btn-adm btn-adm-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const addModal = document.getElementById('addUserModal');
        const editModal = document.getElementById('editUserModal');
        const openAddBtn = document.getElementById('openAddUserModalBtn');
        const closeBtns = document.querySelectorAll('.close-modal-btn');
        const genPwBtn = document.getElementById('genPwBtn');
        const addPwInput = document.getElementById('addPassword');

        const editBtns = document.querySelectorAll('.edit-user-btn');
        const editUserId = document.getElementById('editUserId');
        const editUsername = document.getElementById('editUsername');
        const editName = document.getElementById('editName');
        const editEmail = document.getElementById('editEmail');
        const editRole = document.getElementById('editRole');
        const editStatus = document.getElementById('editStatus');
        const editPassword = document.getElementById('editPassword');

        if (openAddBtn && addModal) {
            openAddBtn.addEventListener('click', () => {
                addModal.style.display = 'flex';
            });
        }

        closeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (addModal) addModal.style.display = 'none';
                if (editModal) editModal.style.display = 'none';
            });
        });

        // Close on backdrop click
        [addModal, editModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.style.display = 'none';
                });
            }
        });

        // Password Generator
        if (genPwBtn && addPwInput) {
            genPwBtn.addEventListener('click', () => {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%&*';
                let pass = '';
                for (let i = 0; i < 14; i++) {
                    pass += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                addPwInput.value = pass;
                addPwInput.type = 'text';
            });
        }

        // Edit user button click
        editBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                editUserId.value = btn.getAttribute('data-id');
                editUsername.value = btn.getAttribute('data-username');
                editName.value = btn.getAttribute('data-name');
                editEmail.value = btn.getAttribute('data-email');
                editRole.value = btn.getAttribute('data-role');
                editStatus.value = btn.getAttribute('data-status');
                editPassword.value = '';
                editModal.style.display = 'flex';
            });
        });
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
