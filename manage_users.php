<?php
require_once 'auth.php';
require_once 'config.php';

if (!$auth->isLoggedIn() || $auth->getUserRole() != 'admin') {
    header('Location: index.php');
    exit;
}

// Handle add user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_POST['email'];
        $role = $_POST['role'];
        $manager_id = $_POST['manager_id'] ?: null;
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, manager_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $email, $role, $manager_id]);
        
        header('Location: manage_users.php');
        exit;
    } elseif (isset($_POST['edit_user'])) {
        $user_id = $_POST['user_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $manager_id = $_POST['manager_id'] ?: null;
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, email = ?, role = ?, manager_id = ? WHERE id = ?");
            $stmt->execute([$username, $password, $email, $role, $manager_id, $user_id]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, manager_id = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $manager_id, $user_id]);
        }
        
        header('Location: manage_users.php');
        exit;
    } elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        // Prevent deleting self
        if ($user_id != $auth->getUserId()) {
            // First, update any users who have this user as their manager
            $stmt = $pdo->prepare("UPDATE users SET manager_id = NULL WHERE manager_id = ?");
            $stmt->execute([$user_id]);
            
            // Then delete related leave requests
            $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Finally delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        
        header('Location: manage_users.php');
        exit;
    }
}

// Get all users and managers
$users = $pdo->query("SELECT u.*, m.username as manager_name FROM users u LEFT JOIN users m ON u.manager_id = m.id ORDER BY u.role, u.username")->fetchAll();
$managers = $pdo->query("SELECT * FROM users WHERE role = 'manager'")->fetchAll();

require_once 'header.php';
?>

<div class="card" style="margin-bottom: 2rem;">
    <h2>Manage Users</h2>
    
    <h3>Add New User</h3>
    <form method="POST">
        <div class="grid grid-2">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="username" required placeholder="Username">
                <small id="username-feedback" style="display: block; margin-top: 5px; font-size: 0.875rem;"></small>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Password">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="Email address">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="employee">Employee</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Manager (for employees)</label>
                <select name="manager_id">
                    <option value="">Select Manager</option>
                    <?php foreach ($managers as $manager): ?>
                        <option value="<?php echo $manager['id']; ?>"><?php echo $manager['username']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
    </form>
</div>

<div class="card">
    <h3>Existing Users</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Manager</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $user['role'] == 'admin' ? 'rejected' : ($user['role'] == 'manager' ? 'pending' : 'approved'); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user['manager_name'] ?? '-'); ?></td>
                        <td><?php echo $user['created_at']; ?></td>
                        <td>
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="btn btn-sm btn-primary" style="margin-right: 0.5rem;">Edit</button>
                            <?php if ($user['id'] != $auth->getUserId()): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>Edit User</h2>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" required placeholder="Username">
                    <small id="edit-username-feedback" style="display: block; margin-top: 5px; font-size: 0.875rem;"></small>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required placeholder="Email address">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role" required>
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Manager (for employees)</label>
                    <select name="manager_id" id="edit_manager_id">
                        <option value="">Select Manager</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?php echo $manager['id']; ?>"><?php echo $manager['username']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>New Password (leave blank to keep current password)</label>
                    <input type="password" name="password" id="edit_password" placeholder="Enter new password or leave blank">
                    <small style="display: block; margin-top: 5px; color: #666;">Only fill this if you want to change the password</small>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="submit" name="edit_user" class="btn btn-primary">Update User</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s;
}

.close:hover,
.close:focus {
    color: #000;
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
}
</style>

<script>
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_manager_id').value = user.manager_id || '';
    document.getElementById('edit_password').value = '';
    
    // Clear any previous validation feedback
    const editFeedback = document.getElementById('edit-username-feedback');
    if (editFeedback) {
        editFeedback.textContent = '';
        editFeedback.style.color = '';
    }
    const editUsernameInput = document.getElementById('edit_username');
    if (editUsernameInput) {
        editUsernameInput.style.borderColor = '';
    }
    const editSubmitBtn = document.querySelector('button[name="edit_user"]');
    if (editSubmitBtn) {
        editSubmitBtn.disabled = false;
    }
    
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}

// Username validation for add user form
document.addEventListener('DOMContentLoaded', function() {
    const usernameInput = document.getElementById('username');
    const feedback = document.getElementById('username-feedback');
    const submitBtn = document.querySelector('button[name="add_user"]');

    let timeout = null;

    usernameInput.addEventListener('input', function() {
        const username = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(timeout);

        if (username.length > 0) {
            // Set a small delay to avoid too many requests while typing
            timeout = setTimeout(() => {
                const formData = new FormData();
                formData.append('username', username);

                fetch('check_username.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        feedback.textContent = 'Username already exists';
                        feedback.style.color = '#dc2626'; // Red color
                        submitBtn.disabled = true;
                        usernameInput.style.borderColor = '#dc2626';
                    } else {
                        feedback.textContent = 'Username available';
                        feedback.style.color = '#16a34a'; // Green color
                        submitBtn.disabled = false;
                        usernameInput.style.borderColor = '#16a34a';
                    }
                })
                .catch(error => console.error('Error:', error));
            }, 300); // 300ms delay
        } else {
            feedback.textContent = '';
            submitBtn.disabled = false;
            usernameInput.style.borderColor = '';
        }
    });
    
    // Username validation for edit user form
    const editUsernameInput = document.getElementById('edit_username');
    const editFeedback = document.getElementById('edit-username-feedback');
    const editSubmitBtn = document.querySelector('button[name="edit_user"]');
    
    let editTimeout = null;
    
    editUsernameInput.addEventListener('input', function() {
        const username = this.value.trim();
        const userId = document.getElementById('edit_user_id').value;
        
        // Clear previous timeout
        clearTimeout(editTimeout);

        if (username.length > 0) {
            // Set a small delay to avoid too many requests while typing
            editTimeout = setTimeout(() => {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('user_id', userId); // Pass user_id to exclude current user's username

                fetch('check_username.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        editFeedback.textContent = 'Username already exists';
                        editFeedback.style.color = '#dc2626'; // Red color
                        editSubmitBtn.disabled = true;
                        editUsernameInput.style.borderColor = '#dc2626';
                    } else {
                        editFeedback.textContent = 'Username available';
                        editFeedback.style.color = '#16a34a'; // Green color
                        editSubmitBtn.disabled = false;
                        editUsernameInput.style.borderColor = '#16a34a';
                    }
                })
                .catch(error => console.error('Error:', error));
            }, 300); // 300ms delay
        } else {
            editFeedback.textContent = '';
            editSubmitBtn.disabled = false;
            editUsernameInput.style.borderColor = '';
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>