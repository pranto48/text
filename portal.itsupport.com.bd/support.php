<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

$customer_id = $_SESSION['customer_id'];
$message = '';
$ticket_id = $_GET['ticket_id'] ?? null;

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message_content'] ?? '');

    if (empty($subject) || empty($message_content)) {
        $message = '<div class="alert-glass-error mb-4">Subject and message cannot be empty.</div>';
    } else {
        if (createSupportTicket($customer_id, $subject, $message_content)) {
            $message = '<div class="alert-glass-success mb-4">Your support ticket has been submitted successfully!</div>';
            // Redirect to prevent resubmission and show updated list
            header('Location: support.php?message=' . urlencode('Your support ticket has been submitted successfully!'));
            exit;
        } else {
            $message = '<div class="alert-glass-error mb-4">Failed to submit ticket. Please try again.</div>';
        }
    }
}

// Handle adding reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply']) && $ticket_id) {
    $reply_content = trim($_POST['reply_content'] ?? '');

    if (empty($reply_content)) {
        $message = '<div class="alert-glass-error mb-4">Reply message cannot be empty.</div>';
    } else {
        if (addTicketReply($ticket_id, $customer_id, 'customer', $reply_content)) {
            $message = '<div class="alert-glass-success mb-4">Your reply has been added.</div>';
            // Redirect to prevent resubmission and show updated replies
            header('Location: support.php?ticket_id=' . $ticket_id . '&message=' . urlencode('Your reply has been added.'));
            exit;
        } else {
            $message = '<div class="alert-glass-error mb-4">Failed to add reply. Please try again.</div>';
        }
    }
}

// Display messages from redirects
if (isset($_GET['message'])) {
    $message = '<div class="alert-glass-success mb-4">' . htmlspecialchars($_GET['message']) . '</div>';
}


portal_header("Support - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">Support Tickets</h1>

<?= $message ?>

<?php if ($ticket_id): 
    $ticket = getTicketDetails($ticket_id, $customer_id);
    if (!$ticket): ?>
        <div class="glass-card text-center py-8">
            <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
            <p class="text-xl text-gray-200">Ticket not found or you do not have permission to view it.</p>
            <a href="support.php" class="btn-glass-secondary mt-4">Back to All Tickets</a>
        </div>
    <?php else: 
        $replies = getTicketReplies($ticket_id);
    ?>
        <div class="max-w-3xl mx-auto glass-card p-8 mb-8">
            <div class="flex justify-between items-center mb-4 border-b border-gray-600 pb-4">
                <h2 class="text-2xl font-semibold text-white">Ticket #<?= htmlspecialchars($ticket['id']) ?>: <?= htmlspecialchars($ticket['subject']) ?></h2>
                <span class="px-3 py-1 rounded-full text-sm font-semibold 
                    <?= $ticket['status'] === 'open' ? 'bg-blue-500' : 
                       ($ticket['status'] === 'in progress' ? 'bg-yellow-500' : 'bg-green-500') ?>">
                    <?= htmlspecialchars(ucfirst($ticket['status'])) ?>
                </span>
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
                    <div class="p-4 rounded-lg <?= $reply['sender_type'] === 'customer' ? 'bg-blue-900/30 ml-8' : 'bg-gray-700/50 mr-8' ?>">
                        <p class="text-gray-300 text-sm mb-2">
                            <i class="fas <?= $reply['sender_type'] === 'customer' ? 'fa-user-circle' : 'fa-user-shield' ?> mr-2"></i>
                            <strong><?= htmlspecialchars($reply['sender_type'] === 'customer' ? $_SESSION['customer_name'] : 'Admin') ?></strong> 
                            <span class="text-gray-400 text-xs ml-2"><?= date('Y-m-d H:i', strtotime($reply['created_at'])) ?></span>
                        </p>
                        <p class="text-white"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($ticket['status'] !== 'closed'): ?>
                <div class="glass-card p-6">
                    <h3 class="text-xl font-semibold text-white mb-4">Add a Reply</h3>
                    <form action="support.php?ticket_id=<?= htmlspecialchars($ticket_id) ?>" method="POST" class="space-y-4">
                        <div>
                            <label for="reply_content" class="block text-gray-200 text-sm font-bold mb-2">Your Message:</label>
                            <textarea id="reply_content" name="reply_content" rows="5" class="form-glass-input" required></textarea>
                        </div>
                        <button type="submit" name="add_reply" class="btn-glass-primary w-full">
                            <i class="fas fa-reply mr-2"></i>Send Reply
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert-glass-warning text-center">
                    <i class="fas fa-info-circle mr-2"></i>This ticket is closed. You cannot add more replies.
                </div>
            <?php endif; ?>
            <div class="text-center mt-8">
                <a href="support.php" class="btn-glass-secondary">Back to All Tickets</a>
            </div>
        </div>
    <?php endif; ?>
<?php else: // Display all tickets and new ticket form ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 glass-card p-6 h-fit">
            <h2 class="text-2xl font-semibold text-white mb-4">Submit New Ticket</h2>
            <form action="support.php" method="POST" class="space-y-4">
                <input type="hidden" name="new_ticket" value="1">
                <div>
                    <label for="subject" class="block text-gray-200 text-sm font-bold mb-2">Subject:</label>
                    <input type="text" id="subject" name="subject" class="form-glass-input" required>
                </div>
                <div>
                    <label for="message_content" class="block text-gray-200 text-sm font-bold mb-2">Message:</label>
                    <textarea id="message_content" name="message_content" rows="5" class="form-glass-input" required></textarea>
                </div>
                <button type="submit" class="btn-glass-primary w-full">
                    <i class="fas fa-paper-plane mr-2"></i>Submit Ticket
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 glass-card p-6">
            <h2 class="text-2xl font-semibold text-white mb-4">My Open Tickets</h2>
            <?php $tickets = getCustomerTickets($customer_id); ?>
            <?php if (empty($tickets)): ?>
                <div class="text-center py-8 text-gray-200">
                    <i class="fas fa-ticket-alt text-6xl text-gray-400 mb-4"></i>
                    <p class="text-xl">You have no support tickets yet.</p>
                    <p class="text-gray-300 mt-2">Submit a new ticket using the form on the left.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($tickets as $ticket): ?>
                        <a href="support.php?ticket_id=<?= htmlspecialchars($ticket['id']) ?>" class="block glass-card p-4 hover:bg-white hover:bg-opacity-20 transition-colors">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-xl font-semibold text-white">#<?= htmlspecialchars($ticket['id']) ?>: <?= htmlspecialchars($ticket['subject']) ?></h3>
                                <span class="px-3 py-1 rounded-full text-sm font-semibold 
                                    <?= $ticket['status'] === 'open' ? 'bg-blue-500' : 
                                       ($ticket['status'] === 'in progress' ? 'bg-yellow-500' : 'bg-green-500') ?>">
                                    <?= htmlspecialchars(ucfirst($ticket['status'])) ?>
                                </span>
                            </div>
                            <p class="text-gray-300 text-sm">Last Updated: <?= date('Y-m-d H:i', strtotime($ticket['updated_at'])) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php portal_footer(); ?>