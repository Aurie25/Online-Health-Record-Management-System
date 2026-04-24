<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "receptionist") {
    header("Location: login.php");
    exit();
}

/* ── MARK SINGLE AS READ ─────────────────────────── */
if (isset($_GET['mark_read'])) {
    $id = intval($_GET['mark_read']);
    $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?")->execute([$id]);
    header("Location: receptionistmessages.php");
    exit();
}

/* ── MARK ALL AS READ ────────────────────────────── */
if (isset($_GET['mark_all_read'])) {
    $conn->exec("UPDATE messages SET is_read = 1 WHERE is_read = 0");
    header("Location: receptionistmessages.php?msg=all_read");
    exit();
}

/* ── DELETE ──────────────────────────────────────── */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->prepare("DELETE FROM messages WHERE id = ?")->execute([$id]);
    header("Location: receptionistmessages.php?msg=deleted");
    exit();
}

/* ── SEND REPLY ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $toEmail    = trim($_POST['reply_to_email']);
    $toName     = trim($_POST['reply_to_name']);
    $subject    = trim($_POST['reply_subject']);
    $body       = trim($_POST['reply_body']);
    $msgId      = intval($_POST['reply_msg_id']);

    $replyError   = '';
    $replySuccess = '';

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $replyError = "Invalid recipient email address.";
    } elseif (empty($subject)) {
        $replyError = "Subject cannot be empty.";
    } elseif (empty($body)) {
        $replyError = "Message body cannot be empty.";
    } else {
        /* ── Build a clean HTML email ── */
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset='UTF-8'>
          <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background:#f4f7fb; margin:0; padding:0; }
            .wrap { max-width:600px; margin:32px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
            .header { background:linear-gradient(135deg,#0b2740,#1a9e9a); padding:28px 32px; }
            .header h2 { color:#fff; margin:0; font-size:20px; }
            .header p  { color:rgba(255,255,255,0.7); margin:4px 0 0; font-size:13px; }
            .body { padding:28px 32px; color:#3d5166; font-size:15px; line-height:1.7; }
            .body p { margin:0 0 16px; }
            .footer { background:#f7f9fc; border-top:1px solid #e0e8f0; padding:18px 32px; font-size:12px; color:#7a90a4; }
          </style>
        </head>
        <body>
          <div class='wrap'>
            <div class='header'>
              <h2>ApexCare Hospital</h2>
              <p>Reply from the reception team</p>
            </div>
            <div class='body'>
              <p>Dear " . htmlspecialchars($toName) . ",</p>
              " . nl2br(htmlspecialchars($body)) . "
              <p style='margin-top:24px;'>Warm regards,<br><strong>ApexCare Reception Team</strong></p>
            </div>
            <div class='footer'>
              &copy; " . date('Y') . " ApexCare Hospital &bull; This is an official reply to your contact message.
            </div>
          </div>
        </body>
        </html>";

        /* ── Mail headers ── */
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: ApexCare Reception <no-reply@apexcare.com>\r\n";
        $headers .= "Reply-To: reception@apexcare.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        if (mail($toEmail, $subject, $htmlBody, $headers)) {
            /* Auto-mark the original message as read after replying */
            if ($msgId > 0) {
                $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?")->execute([$msgId]);
            }
            header("Location: receptionistmessages.php?msg=replied&to=" . urlencode($toName));
            exit();
        } else {
            $replyError = "Mail could not be sent. Please check your server's mail configuration.";
        }
    }
}

/* ── FETCH MESSAGES ──────────────────────────────── */
$messages = $conn->query("
    SELECT id, name, email, message, created_at, is_read
    FROM messages
    ORDER BY is_read ASC, created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalMessages = count($messages);
$unreadCount   = 0;
foreach ($messages as $m) { if (!$m['is_read']) $unreadCount++; }
$readCount = $totalMessages - $unreadCount;

/* ── TOAST ───────────────────────────────────────── */
$toast      = '';
$toastType  = 'success';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted')  $toast = 'Message deleted successfully.';
    if ($_GET['msg'] === 'all_read') $toast = 'All messages marked as read.';
    if ($_GET['msg'] === 'replied')  $toast = 'Reply sent to ' . htmlspecialchars($_GET['to'] ?? 'sender') . ' successfully.';
}

/* ── RELATIVE DATE HELPER ────────────────────────── */
function relativeDate(string $dateStr): string {
    $date = new DateTime($dateStr);
    $now  = new DateTime();
    $diff = $now->diff($date);
    if ($diff->days === 0) return 'Today · ' . $date->format('g:i A');
    if ($diff->days === 1) return 'Yesterday · ' . $date->format('g:i A');
    return $date->format('d M Y · g:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/receptionist_sidebar.css">
    <link rel="stylesheet" href="../static/receptionist.css">
    <link rel="stylesheet" href="../static/receptionistmessages.css">
    <style>
        /* ── Reply Modal ─────────────────────────────────── */
        .reply-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 39, 64, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }
        .reply-modal-backdrop.open { display: flex; }

        .reply-modal {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 24px 64px rgba(11,39,64,0.22);
            width: 100%;
            max-width: 560px;
            overflow: hidden;
            animation: replySlideIn 0.28s cubic-bezier(0.34, 1.2, 0.64, 1);
        }

        @keyframes replySlideIn {
            from { transform: translateY(32px) scale(0.97); opacity: 0; }
            to   { transform: translateY(0) scale(1); opacity: 1; }
        }

        .reply-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px 16px;
            border-bottom: 1px solid #eef2f7;
            background: linear-gradient(135deg, #0b2740 0%, #0f3557 100%);
        }

        .reply-modal-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reply-modal-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            background: rgba(26,158,154,0.25);
            border: 1px solid rgba(26,158,154,0.4);
            display: flex; align-items: center; justify-content: center;
            color: #22c5c0;
            font-size: 15px;
        }

        .reply-modal-title {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
        }
        .reply-modal-sub {
            font-size: 11.5px;
            color: rgba(255,255,255,0.55);
            margin-top: 1px;
        }

        .reply-modal-close {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            width: 30px; height: 30px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            transition: all 0.18s;
        }
        .reply-modal-close:hover {
            background: rgba(220,38,38,0.25);
            border-color: rgba(220,38,38,0.4);
            color: #fca5a5;
        }

        /* Original message preview strip */
        .reply-quote {
            margin: 0;
            padding: 12px 24px;
            background: #f7f9fc;
            border-bottom: 1px solid #eef2f7;
            font-size: 12.5px;
            color: #7a90a4;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .reply-quote i { color: #1a9e9a; margin-top: 2px; flex-shrink: 0; }
        .reply-quote-text {
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.5;
        }

        .reply-modal-body { padding: 20px 24px; }

        /* Form fields */
        .reply-field {
            margin-bottom: 14px;
        }
        .reply-field label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            color: #4a5c72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .reply-field input,
        .reply-field textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f2;
            border-radius: 10px;
            font-size: 13.5px;
            color: #0b2740;
            background: #f8fafd;
            font-family: inherit;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
            box-sizing: border-box;
        }
        .reply-field input:focus,
        .reply-field textarea:focus {
            border-color: #1a9e9a;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(26,158,154,0.12);
        }
        .reply-field input[readonly] {
            background: #f0f3f8;
            color: #7a90a4;
            cursor: default;
        }
        .reply-field textarea {
            resize: vertical;
            min-height: 130px;
            line-height: 1.65;
        }
        .reply-char-hint {
            text-align: right;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
            font-family: 'DM Mono', monospace;
        }

        /* Alert inside modal */
        .reply-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 14px;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .reply-alert i { flex-shrink: 0; margin-top: 1px; }

        .reply-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px 20px;
            border-top: 1px solid #eef2f7;
            gap: 10px;
        }

        .reply-footer-note {
            font-size: 11.5px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .reply-footer-note i { color: #1a9e9a; font-size: 11px; }

        .reply-footer-btns { display: flex; gap: 8px; }

        .btn-reply-cancel {
            padding: 9px 18px;
            border-radius: 9px;
            border: 1.5px solid #e2e8f2;
            background: #fff;
            color: #4a5c72;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s;
            font-family: inherit;
        }
        .btn-reply-cancel:hover { background: #f4f6fa; }

        .btn-reply-send {
            padding: 9px 22px;
            border-radius: 9px;
            border: none;
            background: linear-gradient(135deg, #0b2740, #1a9e9a);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.18s, transform 0.15s;
            box-shadow: 0 4px 14px rgba(26,158,154,0.28);
            font-family: inherit;
        }
        .btn-reply-send:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn-reply-send:active { transform: translateY(0); }
        .btn-reply-send:disabled { opacity: 0.6; pointer-events: none; }

        /* Send spinner */
        .send-spinner {
            display: none;
            width: 13px; height: 13px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="layout">
    <?php include "../static/includes/receptionist_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="r-header-icon">
                        <i class="fas fa-envelope-open-text"></i>
                    </span>
                    Contact Messages
                </h1>
                <p class="page-header-sub">Messages sent from the ApexCare website contact form</p>
            </div>
            <div class="header-badge">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Toast ───────────────────────────────────── -->
        <?php if ($toast): ?>
            <div class="action-toast" id="actionToast">
                <i class="fas fa-circle-check"></i>
                <?php echo $toast; ?>
            </div>
        <?php endif; ?>

        <!-- ── Reply error (if mail failed, re-open modal) -->
        <?php if (!empty($replyError)): ?>
            <div class="action-toast" id="replyErrorToast" style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
                <i class="fas fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($replyError); ?>
            </div>
        <?php endif; ?>

        <!-- ── Stats Row ───────────────────────────────── -->
        <div class="stats-row">
            <div class="stat-card amber">
                <div class="stat-icon amber"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="stat-value"><?php echo $totalMessages; ?></div>
                    <div class="stat-label">Total Messages</div>
                </div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon red"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="stat-value"><?php echo $unreadCount; ?></div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green"><i class="fas fa-envelope-open"></i></div>
                <div>
                    <div class="stat-value"><?php echo $readCount; ?></div>
                    <div class="stat-label">Read</div>
                </div>
            </div>
        </div>

        <!-- ── Toolbar ─────────────────────────────────── -->
        <div class="toolbar">
            <div class="filter-tabs">
                <span class="filter-tab active" data-filter="all">
                    <i class="fas fa-inbox"></i>
                    All <span class="tab-count"><?php echo $totalMessages; ?></span>
                </span>
                <span class="filter-tab" data-filter="unread">
                    <i class="fas fa-envelope"></i>
                    Unread <span class="tab-count"><?php echo $unreadCount; ?></span>
                </span>
                <span class="filter-tab" data-filter="read">
                    <i class="fas fa-envelope-open"></i>
                    Read <span class="tab-count"><?php echo $readCount; ?></span>
                </span>
            </div>

            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text"
                       class="search-input"
                       id="searchInput"
                       placeholder="Search by name, email or message…"
                       oninput="filterMessages()">
            </div>

            <span class="result-count">
                <span id="visibleCount"><?php echo $totalMessages; ?></span> of <?php echo $totalMessages; ?>
            </span>

            <?php if ($unreadCount > 0): ?>
                <a href="?mark_all_read" class="btn-mark-all">
                    <i class="fas fa-envelope-open"></i>
                    Mark all read
                </a>
            <?php endif; ?>
        </div>

        <!-- ── Messages Card ───────────────────────────── -->
        <?php if ($totalMessages > 0): ?>

        <div class="messages-card">
            <div class="messages-card-head">
                <div class="messages-card-head-left">
                    <span class="msg-card-icon"><i class="fas fa-inbox"></i></span>
                    <div>
                        <div class="messages-card-title">Inbox</div>
                        <div class="messages-card-sub">messages · healthrecord_db</div>
                    </div>
                </div>
            </div>

            <div class="messages-list" id="messagesList">

                <?php foreach ($messages as $msg):
                    $isUnread = !$msg['is_read'];
                    $initial  = strtoupper(substr($msg['name'], 0, 1));
                    $dateStr  = relativeDate($msg['created_at']);
                    /* Escape for JS data attributes */
                    $jsName    = addslashes(htmlspecialchars($msg['name']));
                    $jsEmail   = addslashes(htmlspecialchars($msg['email']));
                    $jsMessage = addslashes(htmlspecialchars($msg['message']));
                ?>
                <div class="message-item <?php echo $isUnread ? 'unread' : 'read'; ?>"
                     data-status="<?php echo $isUnread ? 'unread' : 'read'; ?>"
                     data-search="<?php echo strtolower(htmlspecialchars($msg['name'] . ' ' . $msg['email'] . ' ' . $msg['message'])); ?>">

                    <!-- Avatar -->
                    <div class="msg-avatar <?php echo $isUnread ? 'unread-av' : 'read-av'; ?>">
                        <?php echo $initial; ?>
                        <?php if ($isUnread): ?>
                            <div class="unread-dot"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="msg-content">
                        <div class="msg-top-row">
                            <div class="msg-sender">
                                <?php echo htmlspecialchars($msg['name']); ?>
                                <?php if ($isUnread): ?>
                                    <span class="new-badge">New</span>
                                <?php endif; ?>
                            </div>
                            <div class="msg-date">
                                <i class="far fa-clock"></i>
                                <?php echo $dateStr; ?>
                            </div>
                        </div>

                        <div class="msg-email">
                            <i class="fas fa-at"></i>
                            <?php echo htmlspecialchars($msg['email']); ?>
                        </div>

                        <div class="msg-text" id="msgText-<?php echo $msg['id']; ?>">
                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        </div>

                        <!-- Actions -->
                        <div class="msg-actions">

                            <!-- Expand/collapse -->
                            <button class="btn-msg expand"
                                    id="expandBtn-<?php echo $msg['id']; ?>"
                                    onclick="toggleExpand(<?php echo $msg['id']; ?>)">
                                <i class="fas fa-chevron-down" id="expandIcon-<?php echo $msg['id']; ?>"></i>
                                Read more
                            </button>

                            <?php if ($isUnread): ?>
                            <a href="?mark_read=<?php echo $msg['id']; ?>"
                               class="btn-msg read">
                                <i class="fas fa-envelope-open"></i>
                                Mark as Read
                            </a>
                            <?php endif; ?>

                            <!-- Reply button — opens modal -->
                            <button class="btn-msg reply"
                                    onclick="openReplyModal(
                                        <?php echo $msg['id']; ?>,
                                        '<?php echo $jsName; ?>',
                                        '<?php echo $jsEmail; ?>',
                                        '<?php echo $jsMessage; ?>'
                                    )">
                                <i class="fas fa-reply"></i>
                                Reply
                            </button>

                            <button class="btn-msg del"
                                    onclick="confirmDelete(<?php echo $msg['id']; ?>, '<?php echo $jsName; ?>')">
                                <i class="fas fa-trash"></i>
                                Delete
                            </button>

                        </div>
                    </div>

                </div>
                <?php endforeach; ?>

                <!-- No filter results -->
                <div id="noFilterResults">
                    <div class="empty-icon" style="width:48px;height:48px;font-size:20px;margin:0 auto;">
                        <i class="fas fa-magnifying-glass"></i>
                    </div>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-muted);text-align:center;margin-top:10px;">
                        No messages match your search.
                    </p>
                </div>

            </div>

            <div class="messages-footer">
                <span class="messages-footer-info">
                    <i class="fas fa-envelope"></i>
                    <span id="footerCount"><?php echo $totalMessages; ?></span> message<?php echo $totalMessages !== 1 ? 's' : ''; ?>
                </span>
                <span class="messages-footer-info">
                    <i class="fas fa-circle-check"></i>
                    healthrecord_db · messages
                </span>
            </div>
        </div>

        <?php else: ?>

        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-inbox"></i></div>
            <h3>Inbox is empty</h3>
            <p>When patients or visitors send messages through the ApexCare website, they will appear here.</p>
        </div>

        <?php endif; ?>

    </main>
</div>

<!-- ══════════════════════════════════════════════════
     REPLY MODAL
══════════════════════════════════════════════════ -->
<div class="reply-modal-backdrop" id="replyModal">
    <div class="reply-modal">

        <!-- Header -->
        <div class="reply-modal-header">
            <div class="reply-modal-header-left">
                <div class="reply-modal-icon"><i class="fas fa-reply"></i></div>
                <div>
                    <div class="reply-modal-title">Reply to Message</div>
                    <div class="reply-modal-sub" id="replyModalSub">Composing reply…</div>
                </div>
            </div>
            <button class="reply-modal-close" onclick="closeReplyModal()" title="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <!-- Original message quote -->
        <div class="reply-quote">
            <i class="fas fa-quote-left"></i>
            <div class="reply-quote-text" id="replyQuoteText"></div>
        </div>

        <!-- Form -->
        <form method="POST" id="replyForm" onsubmit="handleReplySend()">
            <input type="hidden" name="send_reply" value="1">
            <input type="hidden" name="reply_msg_id" id="replyMsgId">

            <div class="reply-modal-body">

                <?php if (!empty($replyError)): ?>
                    <div class="reply-alert">
                        <i class="fas fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($replyError); ?>
                    </div>
                <?php endif; ?>

                <!-- To (readonly) -->
                <div class="reply-field">
                    <label><i class="fas fa-user" style="color:#1a9e9a;margin-right:5px;font-size:10px;"></i> To</label>
                    <input type="text" id="replyToDisplay" readonly>
                    <input type="hidden" name="reply_to_email" id="replyToEmail">
                    <input type="hidden" name="reply_to_name"  id="replyToName">
                </div>

                <!-- Subject -->
                <div class="reply-field">
                    <label><i class="fas fa-tag" style="color:#1a9e9a;margin-right:5px;font-size:10px;"></i> Subject</label>
                    <input type="text" name="reply_subject" id="replySubject"
                           placeholder="Re: Your enquiry to ApexCare" required maxlength="150">
                </div>

                <!-- Body -->
                <div class="reply-field">
                    <label><i class="fas fa-pen-to-square" style="color:#1a9e9a;margin-right:5px;font-size:10px;"></i> Message</label>
                    <textarea name="reply_body" id="replyBody"
                              placeholder="Type your reply here…"
                              required
                              maxlength="2000"
                              oninput="updateCharCount()"></textarea>
                    <div class="reply-char-hint"><span id="replyCharCount">0</span> / 2000</div>
                </div>

            </div>

            <div class="reply-modal-footer">
                <div class="reply-footer-note">
                    <i class="fas fa-shield-halved"></i>
                    Sent as ApexCare Reception
                </div>
                <div class="reply-footer-btns">
                    <button type="button" class="btn-reply-cancel" onclick="closeReplyModal()">Cancel</button>
                    <button type="submit" class="btn-reply-send" id="replySendBtn">
                        <div class="send-spinner" id="sendSpinner"></div>
                        <i class="fas fa-paper-plane" id="sendIcon"></i>
                        Send Reply
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

<!-- ── Delete Confirmation Modal ─────────────────── -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-trash"></i></div>
        <h3>Delete Message?</h3>
        <p id="deleteModalText">This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
            <a href="#" class="btn-modal-confirm" id="confirmDeleteLink">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>
</div>

<script>
    /* ── Expand / collapse message text ── */
    function toggleExpand(id) {
        const text = document.getElementById('msgText-' + id);
        const btn  = document.getElementById('expandBtn-' + id);
        text.classList.toggle('expanded');
        const isExpanded = text.classList.contains('expanded');
        btn.innerHTML = isExpanded
            ? '<i class="fas fa-chevron-up"></i> Show less'
            : '<i class="fas fa-chevron-down"></i> Read more';
    }

    /* ── Delete modal ── */
    function confirmDelete(id, name) {
        document.getElementById('deleteModalText').textContent =
            'Are you sure you want to delete the message from "' + name + '"?';
        document.getElementById('confirmDeleteLink').href = '?delete=' + id;
        document.getElementById('deleteModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('deleteModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    /* ── Reply modal ── */
    function openReplyModal(id, name, email, message) {
        /* Populate fields */
        document.getElementById('replyMsgId').value      = id;
        document.getElementById('replyToDisplay').value  = name + ' <' + email + '>';
        document.getElementById('replyToEmail').value    = email;
        document.getElementById('replyToName').value     = name;
        document.getElementById('replySubject').value    = 'Re: Your enquiry to ApexCare';
        document.getElementById('replyBody').value       = '';
        document.getElementById('replyCharCount').textContent = '0';
        document.getElementById('replyQuoteText').textContent = message;
        document.getElementById('replyModalSub').textContent  = 'To: ' + name + ' · ' + email;

        /* Reset button state */
        document.getElementById('sendSpinner').style.display = 'none';
        document.getElementById('sendIcon').style.display    = '';
        document.getElementById('replySendBtn').disabled     = false;

        /* Open */
        document.getElementById('replyModal').classList.add('open');
        document.body.style.overflow = 'hidden';

        /* Focus body */
        setTimeout(() => document.getElementById('replyBody').focus(), 120);
    }

    function closeReplyModal() {
        document.getElementById('replyModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    /* Close on backdrop click */
    document.getElementById('replyModal').addEventListener('click', function(e) {
        if (e.target === this) closeReplyModal();
    });

    /* Char counter */
    function updateCharCount() {
        const len = document.getElementById('replyBody').value.length;
        const el  = document.getElementById('replyCharCount');
        el.textContent = len;
        el.style.color = len > 1800 ? '#dc2626' : len > 1500 ? '#f59e0b' : '#94a3b8';
    }

    /* Spinner on send */
    function handleReplySend() {
        document.getElementById('sendSpinner').style.display = 'block';
        document.getElementById('sendIcon').style.display    = 'none';
        document.getElementById('replySendBtn').disabled     = true;
    }

    /* ── Shared Escape key handler ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeModal();
            closeReplyModal();
        }
    });

    /* ── Filter tabs + search ── */
    const items     = document.querySelectorAll('.message-item');
    const totalMsgs = items.length;
    let   activeFilter = 'all';

    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            activeFilter = this.dataset.filter;
            filterMessages();
        });
    });

    function filterMessages() {
        const search  = document.getElementById('searchInput').value.toLowerCase().trim();
        let   visible = 0;

        items.forEach(item => {
            const matchFilter = activeFilter === 'all' || item.dataset.status === activeFilter;
            const matchSearch = !search || item.dataset.search.includes(search);
            const show = matchFilter && matchSearch;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('visibleCount').textContent = visible;
        document.getElementById('footerCount').textContent  = visible;
        const noResults = document.getElementById('noFilterResults');
        noResults.classList.toggle('show', visible === 0 && totalMsgs > 0);
    }

    /* ── Auto-dismiss toasts ── */
    ['actionToast', 'replyErrorToast'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 500);
        }, 6000);
    });
</script>

</body>
</html>