<?php
require_once 'auth.php';
require_once 'config.php';

if (!$auth->isLoggedIn() || $auth->getUserRole() != 'admin') {
    header('Location: index.php');
    exit;
}

// Handle add/update policy
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_policy'])) {
        $leave_type = $_POST['leave_type'];
        $max_days = $_POST['max_days'];
        $description = $_POST['description'];
        
        $stmt = $pdo->prepare("INSERT INTO leave_policies (leave_type, max_days, description) VALUES (?, ?, ?)");
        $stmt->execute([$leave_type, $max_days, $description]);
    } elseif (isset($_POST['update_policy'])) {
        $id = $_POST['policy_id'];
        $max_days = $_POST['max_days'];
        $description = $_POST['description'];
        
        $stmt = $pdo->prepare("UPDATE leave_policies SET max_days = ?, description = ? WHERE id = ?");
        $stmt->execute([$max_days, $description, $id]);
    }
    
    header('Location: manage_policies.php');
    exit;
}

$policies = $pdo->query("SELECT * FROM leave_policies ORDER BY leave_type")->fetchAll();

require_once 'header.php';
?>

<div class="card" style="margin-bottom: 2rem;">
    <h2>Manage Leave Policies</h2>
    
    <h3>Add New Policy</h3>
    <form method="POST">
        <div class="grid grid-2">
            <div class="form-group">
                <label>Leave Type</label>
                <input type="text" name="leave_type" required placeholder="e.g. Sick Leave">
            </div>
            <div class="form-group">
                <label>Maximum Days</label>
                <input type="number" name="max_days" required placeholder="e.g. 12">
            </div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Policy description..."></textarea>
        </div>
        <button type="submit" name="add_policy" class="btn btn-primary">Add Policy</button>
    </form>
</div>

<div class="card">
    <h3>Existing Policies</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Max Days</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($policies as $policy): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($policy['leave_type']); ?></div>
                        </td>
                        <td><?php echo $policy['max_days']; ?></td>
                        <td><?php echo htmlspecialchars($policy['description']); ?></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                <input type="number" name="max_days" value="<?php echo $policy['max_days']; ?>" style="width: 80px;" class="form-control">
                                <button type="submit" name="update_policy" class="btn btn-sm btn-primary">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'footer.php'; ?>     