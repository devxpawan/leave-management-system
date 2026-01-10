<?php
require_once 'auth.php';
require_once 'config.php';

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$role = $auth->getUserRole();
$user_id = $auth->getUserId();
$username = $_SESSION['username'];

// Fetch stats based on role
$stats = [];
if ($role == 'employee') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $stats['pending'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$user_id]);
    $stats['approved'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'rejected'");
    $stmt->execute([$user_id]);
    $stats['rejected'] = $stmt->fetchColumn();
} elseif ($role == 'manager') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.id 
        WHERE u.manager_id = ? AND lr.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $stats['pending_approvals'] = $stmt->fetchColumn();
} elseif ($role == 'admin') {
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['policies'] = $pdo->query("SELECT COUNT(*) FROM leave_policies")->fetchColumn();
}

require_once 'header.php';
?>

<div class="card" style="margin-bottom: 2rem;">
    <h2>Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
    <p>You are logged in as <span class="status-badge status-approved"><?php echo ucfirst($role); ?></span></p>
</div>

<div class="grid grid-3">
    <?php if ($role == 'employee'): ?>
        <div class="stat-card">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value" style="color: var(--warning-color)"><?php echo $stats['pending']; ?></div>
            <a href="my_requests.php" class="btn btn-sm btn-primary">View Details</a>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved Leaves</div>
            <div class="stat-value" style="color: var(--success-color)"><?php echo $stats['approved']; ?></div>
            <a href="my_requests.php" class="btn btn-sm btn-primary">View Details</a>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rejected Requests</div>
            <div class="stat-value" style="color: var(--danger-color)"><?php echo $stats['rejected']; ?></div>
            <a href="my_requests.php" class="btn btn-sm btn-primary">View Details</a>
        </div>
        <div class="stat-card">
            <div class="stat-label">New Request</div>
            <div class="stat-value">+</div>
            <a href="request_leave.php" class="btn btn-sm btn-primary">Apply Now</a>
        </div>
    <?php elseif ($role == 'manager'): ?>
        <div class="stat-card">
            <div class="stat-label">Pending Approvals</div>
            <div class="stat-value" style="color: var(--warning-color)"><?php echo $stats['pending_approvals']; ?></div>
            <a href="manage_requests.php" class="btn btn-sm btn-primary">Review Requests</a>
        </div>
    <?php elseif ($role == 'admin'): ?>
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?php echo $stats['users']; ?></div>
            <a href="manage_users.php" class="btn btn-sm btn-primary">Manage Users</a>
        </div>
        <div class="stat-card">
            <div class="stat-label">Leave Policies</div>
            <div class="stat-value"><?php echo $stats['policies']; ?></div>
            <a href="manage_policies.php" class="btn btn-sm btn-primary">Manage Policies</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>