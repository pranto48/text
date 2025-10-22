<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$admin_id = $_SESSION['admin_id'];
$message = '';
$ticket_id = $_GET['ticket_id'] ?? null;
$filter_status = $_GET['status'] ?? 'all';

// Handle adding reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply']) && $ticket_id) {
    $reply_content = trim($_POST['reply_content'] ?? '');

    if (empty($reply_content)) {
        $message = '<div class="alert-admin-error mb-4">Reply message cannot be empty.</div>';
    } else {
        if (addTicketReply($ticket_id, $admin_id, 'admin', $reply_content)) {
            $message = '<div class="alert-admin-success mb-4">Your reply has been added.</div>';
            header('Location: tickets.php?ticket_id=' . $ticket_id . '&message=' . urlencode('Your reply has been added.'));
            exit;
        } else {
            $message = '<div class="alert-admin-error mb-4">Failed to add reply. Please try again.</div>';
        }
    }
}

// Handle updating ticket status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $ticket_id) {
    $new_status = $_POST['new_status'] ?? 'open';

    if (updateTicketStatus($ticket_id, $new_status)) {
        $message = '<div class="alert-admin-success mb-4">Ticket status updated to ' . htmlspecialchars(ucfirst($new_status)) . '.</div>';
        header('Location: tickets.php?ticket_id=' . $ticket_id . '&message=' . urlencode('Ticket status updated.'));
        exit;
    } else {
        $message = '<div class="alert-admin-error mb-4">Failed to update ticket status.</div>';
    }
}

// Display messages from redirects
if (isset($_GET['message'])) {
    $message = '<div class="alert-admin-success mb-4">' . htmlspecialchars($_GET['message']) . '</div>';
}

admin_header("Manage Tickets");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Support Tickets</h1>

<?= $message ?>

<?php if ($ticket_id): 
    $ticket = getTicketDetails($ticket_id);
    if (!$ticket): ?>
        <div class="admin-card text-center py-8">
            <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
            <p class="text-xl text-gray-200">Ticket not found.</p>
            <a href="tickets.php" class="btn-admin-secondary mt-4">Back to All Tickets</a>
        </div>
    <?php else: 
        $replies = getTicketReplies($ticket_id);
    ?>
        <div class="max-w-3xl mx-auto admin-card p-8 mb-8">
            <div class="flex justify-between items-center mb-4 border-b border-gray-600 pb-4">
                <h2 class="text-2xl font-semibold text-blue-400">Ticket #<?= htmlspecialchars($ticket['id']) ?>: <?= htmlspecialchars($ticket['subject']) ?></h2>
                <span class="px-3 py-1 rounded-full text-sm font-semibold 
                    <?= $ticket['status'] === 'open' ? 'bg-blue-500' : 
                       ($ticket['status'] === 'in progress' ? 'bg-yellow-500' : 'bg-green-500') ?>">
                    <?= htmlspecialchars(ucfirst($ticket['status'])) ?>
                </span>
            </div>

            <div class="text-gray-300 text-sm mb-4">
                <p><strong>Customer:</strong> <?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?> (<?= htmlspecialchars($ticket['email']) ?>)</p>
                <p><strong>Created:</strong> <?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?></p>
                <p><strong>Last Updated:</strong> <?= date('Y-m-d H:i', strtotime($ticket['updated_at'])) ?></p>
            </div>

            <div class="space-y-4 mb-6">
                <div class="bg-gray-800 p-4 rounded-lg">
                    <p class="text-gray-300 text-sm mb-2">
                        <i class="fas fa-user-circle mr-2"></i>
                        <strong><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></strong> 
                        <span class="text-gray-400 text-xs ml-2"><?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?></span>
                    </p>
                    <p class="text-white"><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>
                </div>

                <?php foreach ($replies as $reply): ?>
                    <div class="p-4 rounded-lg <?= $reply['sender_type'] === 'customer' ? 'bg-gray-800/50 ml-8' : 'bg-blue-900/30 mr-8' ?>">
                        <p class="text-gray-300 text-sm mb-2">
                            <i class="fas <?= $reply['sender_type'] === 'customer' ? 'fa-user-circle' : 'fa-user-shield' ?> mr-2"></i>
                            <strong><?= htmlspecialchars($reply['sender_type'] === 'customer' ? $ticket['first_name'] . ' (Customer)' : 'Admin') ?></strong> 
                            <span class="text-gray-400 text-xs ml-2"><?= date('Y-m-d H:i', strtotime($reply['created_at'])) ?></span>
                        </p>
                        <p class="text-white"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="admin-card p-6 mb-6">
                <h3 class="text-xl font-semibold text-blue-400 mb-4">Add a Reply</h3>
                <form action="tickets.php?ticket_id=<?= htmlspecialchars($ticket_id) ?>" method="POST" class="space-y-4">
                    <div>
                        <label for="reply_content" class="block text-gray-300 text-sm font-bold mb-2">Your Message:</label>
                        <textarea id="reply_content" name="reply_content" rows="5" class="form-admin-input" required></textarea>
                    </div>
                    <button type="submit" name="add_reply" class="btn-admin-primary w-full">
                        <i class="fas fa-reply mr-2"></i>Send Reply
                    </button>
                </form>
            </div>

            <div class="admin-card p-6">
                <h3 class="text-xl font-semibold text-blue-400 mb-4">Update Ticket Status</h3>
                <form action="tickets.php?ticket_id=<?= htmlspecialchars($ticket_id) ?>" method="POST" class="space-y-4">
                    <div>
                        <label for="new_status" class="block text-gray-300 text-sm font-bold mb-2">Status:</label>
                        <select id="new_status" name="new_status" class="form-admin-input">
                            <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="in progress" <?= $ticket['status'] === 'in progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn-admin-primary w-full">
                        <i class="fas fa-sync-alt mr-2"></i>Update Status
                    </button>
                </form>
            </div>
            <div class="text-center mt-8">
                <a href="tickets.php" class="btn-admin-secondary">Back to All Tickets</a>
            </div>
        </div>
    <?php endif; ?>
<?php else: // Display all tickets for admin ?>
    <div class="admin-card p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-semibold text-blue-400">All Support Tickets</h2>
            <div class="flex space-x-2">
                <a href="tickets.php?status=all" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'all' ? 'bg-blue-700' : '' ?>">All</a>
                <a href="tickets.php?status=open" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'open' ? 'bg-blue-700' : '' ?>">Open</a>
                <a href="tickets.php?status=in progress" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'in progress' ? 'bg-blue-700' : '' ?>">In Progress</a>
                <a href="tickets.php?status=closed" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'closed' ? 'bg-blue-700' : '' ?>">Closed</a>
            </div>
        </div>
        <?php $tickets = getAllTickets($filter_status); ?>
        <?php if (empty($tickets)): ?>
            <p class="text-center text-gray-400 py-8">No tickets found with status "<?= htmlspecialchars($filter_status) ?>".</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-gray-700 rounded-lg">
                    <thead>
                        <tr class="bg-gray-600 text-gray-200 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">ID</th>
                            <th class="py-3 px-6 text-left">Subject</th>
                            <th class="py-3 px-6 text-left">Customer</th>
                            <th class="py-3 px-6 text-left">Status</th>
                            <th class="py-3 px-6 text-left">Last Updated</th>
                            <th class="py-3 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-300 text-sm font-light">
                        <?php foreach ($tickets as $ticket): ?>
                            <tr class="border-b border-gray-600 hover:bg-gray-600">
                                <td class="py-3 px-6 text-left whitespace-nowrap"><?= htmlspecialchars($ticket['id']) ?></td>
                                <td class="py-3 px-6 text-left"><?= htmlspecialchars($ticket['subject']) ?></td>
                                <td class="py-3 px-6 text-left"><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?> (<?= htmlspecialchars($ticket['email']) ?>)</td>
                                <td class="py-3 px-6 text-left">
                                    <span class="py-1 px-3 rounded-full text-xs font-semibold 
                                        <?= $ticket['status'] === 'open' ? 'bg-blue-500' : 
                                           ($ticket['status'] === 'in progress' ? 'bg-yellow-500' : 'bg-green-500') ?>">
                                        <?= htmlspecialchars(ucfirst($ticket['status'])) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-left"><?= date('Y-m-d H:i', strtotime($ticket['updated_at'])) ?></td>
                                <td class="py-3 px-6 text-center">
                                    <a href="tickets.php?ticket_id=<?= htmlspecialchars($ticket['id']) ?>" class="btn-admin-primary text-xs px-3 py-1">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php admin_footer(); ?>