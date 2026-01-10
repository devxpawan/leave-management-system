<?php
require_once 'auth.php';
require_once 'config.php';

if (!$auth->isLoggedIn() || $auth->getUserRole() != 'employee') {
    header('Location: index.php');
    exit;
}

$user_id = $auth->getUserId();
$requests = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC");
$requests->execute([$user_id]);
$requests = $requests->fetchAll();

// Calculate leave balances
$policies = $pdo->query("SELECT * FROM leave_policies")->fetchAll();
$leave_balances = [];
foreach ($policies as $policy) {
    $stmt = $pdo->prepare("
        SELECT SUM(DATEDIFF(end_date, start_date) + 1) as used_days 
        FROM leave_requests 
        WHERE user_id = ? AND leave_type = ? AND status IN ('approved', 'pending')
    ");
    $stmt->execute([$user_id, $policy['leave_type']]);
    $used_days = $stmt->fetchColumn() ?: 0;
    
    $leave_balances[] = [
        'type' => $policy['leave_type'],
        'max_days' => $policy['max_days'],
        'used_days' => $used_days,
        'balance' => $policy['max_days'] - $used_days,
    ];
}

require_once 'header.php';
?>

<!-- Leave Balance Summary -->
<div class="card" style="margin-bottom: 2rem;">
    <h3 style="margin-bottom: 1rem;">📊 Leave Balance Summary</h3>
    <div class="grid grid-3">
        <?php foreach ($leave_balances as $balance): ?>
            <div class="balance-mini-card">
                <div class="balance-type-mini"><?php echo htmlspecialchars($balance['type']); ?></div>
                <div class="balance-numbers">
                    <span class="balance-big"><?php echo $balance['balance']; ?></span>
                    <span class="balance-small">/ <?php echo $balance['max_days']; ?> days</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2>My Leave Requests</h2>
        <a href="request_leave.php" class="btn btn-primary">New Request</a>
    </div>
    
    <?php if (empty($requests)): ?>
        <p>No leave requests found.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Manager Notes</th>
                        <th>Submitted On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                            <td>
                                <?php echo $request['start_date']; ?> to <?php echo $request['end_date']; ?>
                            </td>
                            <td><?php echo htmlspecialchars($request['reason']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($request['manager_notes'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.balance-mini-card {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    border-radius: 10px;
    padding: 1.25rem;
    color: white;
    text-align: center;
    box-shadow: 0 3px 10px rgba(79, 172, 254, 0.3);
    transition: transform 0.3s;
}

.balance-mini-card:hover {
    transform: translateY(-3px);
}

.balance-type-mini {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    opacity: 0.95;
}

.balance-numbers {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 0.25rem;
}

.balance-big {
    font-size: 2rem;
    font-weight: 700;
}

.balance-small {
    font-size: 1rem;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .grid-3 {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once 'footer.php'; ?>