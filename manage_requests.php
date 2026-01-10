<?php
require_once 'auth.php';
require_once 'config.php';

if (!$auth->isLoggedIn() || $auth->getUserRole() != 'manager') {
    header('Location: index.php');
    exit;
}

$manager_id = $auth->getUserId();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, manager_notes = ? WHERE id = ?");
    $stmt->execute([$status, $notes, $request_id]);
    
    header('Location: manage_requests.php');
    exit;
}

// Get pending requests for employees managed by this manager
$requests = $pdo->prepare("
    SELECT lr.*, u.username 
    FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.id 
    WHERE u.manager_id = ? AND lr.status = 'pending'
    ORDER BY lr.created_at DESC
");
$requests->execute([$manager_id]);
$requests = $requests->fetchAll();

require_once 'header.php';
?>

<div class="card">
    <h2>Manage Leave Requests</h2>
    
    <?php if (empty($requests)): ?>
        <p>No pending leave requests.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($request['username']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                            <td>
                                <?php echo $request['start_date']; ?> to <?php echo $request['end_date']; ?>
                            </td>
                            <td><?php echo htmlspecialchars($request['reason']); ?></td>
                            <td style="min-width: 250px;">
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <textarea name="notes" placeholder="Add notes (optional)" rows="2" style="width: 100%; margin-bottom: 0.5rem;"></textarea>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" name="action" value="approved" class="btn btn-sm btn-success">Approve</button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-sm btn-danger">Reject</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>