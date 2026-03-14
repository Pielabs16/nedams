<?php
// ============================================================
// views/messages.php  — Chat-based messaging v2.1
// Users compose threads; admins reply in real-time via polling
// ============================================================
require_once __DIR__.'/../config/app.php';
requireLogin();

$pdo   = getDB();
$uid   = (int)$_SESSION['user_id'];
$uname = $_SESSION['user_name'] ?? 'User';
$isAdm = isAdmin();

// ---- AJAX: post a new message/reply -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // New thread (user or admin)
    if (isset($_POST['new_thread'])) {
        $subject = trim(strip_tags($_POST['subject'] ?? ''));
        $body    = trim(strip_tags($_POST['body']    ?? ''));
        if ($subject && $body) {
            $me = $pdo->prepare('SELECT full_name,email FROM users WHERE id=? LIMIT 1');
            $me->execute([$uid]);
            $u = $me->fetch();
            $pdo->prepare(
                'INSERT INTO messages(sender_id,sender_name,sender_email,subject,body,ip_address)
                 VALUES(?,?,?,?,?,?)'
            )->execute([$uid,$u['full_name'],$u['email'],$subject,$body,$_SERVER['REMOTE_ADDR']??null]);
            $tid = (int)$pdo->lastInsertId();
            // Notify admins
            if (!$isAdm) {
                $admins = $pdo->query('SELECT id FROM users WHERE role IN ("admin","super_admin") AND is_active=1')->fetchAll();
                foreach ($admins as $a) {
                    addNotification($a['id'], "New message: $subject",
                        "From {$u['full_name']}", 'info', appUrl().'/views/messages.php?t='.$tid);
                }
            }
            if (isset($_POST['_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success'=>true,'thread_id'=>$tid]);
                exit;
            }
            $_SESSION['flash'] = ['type'=>'success','message'=>'Message sent.'];
            header('Location: messages.php?t='.$tid); exit;
        }
    }

    // Reply to a thread
    if (isset($_POST['send_reply'])) {
        $tid  = (int)($_POST['thread_id'] ?? 0);
        $body = trim(strip_tags($_POST['body'] ?? ''));
        if ($tid && $body) {
            // Verify access: admin sees all, user sees their own
            $tStmt = $pdo->prepare('SELECT * FROM messages WHERE id=? LIMIT 1');
            $tStmt->execute([$tid]);
            $thread = $tStmt->fetch();
            if (!$thread || (!$isAdm && $thread['sender_id'] !== $uid)) {
                if (isset($_POST['_ajax'])) { echo json_encode(['success'=>false]); exit; }
                header('Location: messages.php'); exit;
            }
            // Append reply to reply chain (stored as JSON array in reply_body)
            $replies = json_decode($thread['reply_body'] ?? '[]', true) ?: [];
            $replies[] = [
                'by'      => $uid,
                'name'    => $uname,
                'role'    => $_SESSION['role'] ?? 'viewer',
                'body'    => $body,
                'ts'      => date('Y-m-d H:i:s'),
            ];
            $pdo->prepare(
                'UPDATE messages SET reply_body=?, status=?, replied_by=?, replied_at=NOW()
                 WHERE id=?'
            )->execute([json_encode($replies), 'replied', $uid, $tid]);
            // Notify the other party
            $notifyId = $isAdm ? (int)$thread['sender_id'] : null;
            if (!$notifyId) {
                // Notify admins of user reply
                $admins = $pdo->query('SELECT id FROM users WHERE role IN ("admin","super_admin") AND is_active=1')->fetchAll();
                foreach ($admins as $a) {
                    if ($a['id'] !== $uid)
                        addNotification($a['id'], "Reply: {$thread['subject']}", $body,
                            'info', appUrl().'/views/messages.php?t='.$tid);
                }
            } elseif ($notifyId !== $uid) {
                addNotification($notifyId, "Reply: {$thread['subject']}", $body,
                    'success', appUrl().'/views/messages.php?t='.$tid);
            }
            if (isset($_POST['_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success'=>true]);
                exit;
            }
            header('Location: messages.php?t='.$tid); exit;
        }
    }

    // Mark thread read (AJAX)
    if (isset($_POST['mark_read'])) {
        $tid = (int)($_POST['thread_id'] ?? 0);
        if ($tid && $isAdm)
            $pdo->prepare('UPDATE messages SET status="read" WHERE id=? AND status="unread"')
                ->execute([$tid]);
        header('Content-Type: application/json');
        echo json_encode(['success'=>true]);
        exit;
    }

    // Close thread (admin)
    if (isset($_POST['close_thread']) && $isAdm) {
        $pdo->prepare('UPDATE messages SET status="closed" WHERE id=?')
            ->execute([(int)$_POST['thread_id']]);
        if (isset($_POST['_ajax'])) { echo json_encode(['success'=>true]); exit; }
        header('Location: messages.php'); exit;
    }
}

// ---- AJAX: poll for new replies in a thread -----------------
if (isset($_GET['poll'])) {
    $tid   = (int)($_GET['poll'] ?? 0);
    $since = $_GET['since'] ?? '2000-01-01';
    $stmt  = $pdo->prepare('SELECT reply_body, status FROM messages WHERE id=? LIMIT 1');
    $stmt->execute([$tid]);
    $row = $stmt->fetch();
    header('Content-Type: application/json');
    if (!$row) { echo json_encode(['replies'=>[],'status'=>'']); exit; }
    $replies = json_decode($row['reply_body'] ?? '[]', true) ?: [];
    $new = array_filter($replies, fn($r) => ($r['ts'] ?? '') > $since);
    echo json_encode(['replies'=>array_values($new),'status'=>$row['status']]);
    exit;
}

// ---- Load thread list ---------------------------------------
if ($isAdm) {
    $threads = $pdo->query(
        'SELECT m.*, u.full_name AS sender_full
         FROM messages m
         LEFT JOIN users u ON u.id=m.sender_id
         ORDER BY
           CASE m.status WHEN "unread" THEN 0 WHEN "read" THEN 1 WHEN "replied" THEN 2 ELSE 3 END,
           m.created_at DESC
         LIMIT 100'
    )->fetchAll();
} else {
    $s = $pdo->prepare('SELECT * FROM messages WHERE sender_id=? ORDER BY created_at DESC');
    $s->execute([$uid]);
    $threads = $s->fetchAll();
}

// ---- Active thread ------------------------------------------
$activeId = (int)($_GET['t'] ?? ($threads[0]['id'] ?? 0));
$active   = null;
if ($activeId) {
    $stmt = $pdo->prepare('SELECT m.*, u.full_name AS sender_full FROM messages m LEFT JOIN users u ON u.id=m.sender_id WHERE m.id=? LIMIT 1');
    $stmt->execute([$activeId]);
    $active = $stmt->fetch() ?: null;
    if ($active && $isAdm && $active['status'] === 'unread') {
        $pdo->prepare('UPDATE messages SET status="read" WHERE id=?')->execute([$activeId]);
        $active['status'] = 'read';
    }
}

$pageTitle = 'Messages';
require_once __DIR__.'/partials/head.php';
?>
<div class="app-wrapper">
<?php require_once __DIR__.'/partials/sidebar.php'; ?>
<div class="main-content" id="main-content">
<?php require_once __DIR__.'/partials/topbar.php'; ?>
<div class="page-content" style="height:calc(100vh - 60px);display:flex;flex-direction:column;padding-bottom:0">

<div class="page-header" style="flex-shrink:0">
    <div class="page-header-left">
        <h1>Messages</h1>
        <p><?= $isAdm ? 'All user threads' : 'Chat with the NEDAMS admin team' ?></p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-accent" onclick="openModal('modal-new-thread')">
            <i class="fas fa-pen-to-square"></i> New Message
        </button>
    </div>
</div>

<div id="flash-zone" style="flex-shrink:0"></div>

<!-- Chat layout: thread list + chat window -->
<div style="flex:1;display:flex;gap:0;overflow:hidden;
            border:1px solid var(--card-border);border-radius:var(--radius-md);
            background:#fff;min-height:0">

    <!-- ===== Left: Thread list ===== -->
    <div style="width:280px;flex-shrink:0;border-right:1px solid var(--border);
                display:flex;flex-direction:column;overflow:hidden">
        <!-- Search threads -->
        <div style="padding:10px 12px;border-bottom:1px solid var(--border);flex-shrink:0">
            <div class="input-group">
                <span class="input-addon" style="font-size:11px"><i class="fas fa-magnifying-glass"></i></span>
                <input type="text" id="thread-search"
                       class="form-control" style="font-size:.82rem"
                       placeholder="Search messages…"
                       oninput="filterThreads(this.value)">
            </div>
        </div>
        <!-- Thread items -->
        <div style="flex:1;overflow-y:auto" id="thread-list">
            <?php if (empty($threads)): ?>
            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:.82rem">
                <i class="fas fa-comments" style="font-size:1.4rem;display:block;margin-bottom:8px"></i>
                No messages yet
            </div>
            <?php else: ?>
            <?php foreach ($threads as $t):
                $replies     = json_decode($t['reply_body'] ?? '[]', true) ?: [];
                $lastReply   = end($replies);
                $preview     = $lastReply ? $lastReply['body'] : $t['body'];
                $preview     = htmlspecialchars(substr($preview, 0, 55)).(strlen($preview)>55?'…':'');
                $isActive    = ($t['id'] == $activeId);
                $isUnread    = ($t['status'] === 'unread' && $isAdm);
                $replyCount  = count($replies);
            ?>
            <a href="messages.php?t=<?= $t['id'] ?>"
               class="thread-item <?= $isActive?'active':'' ?> <?= $isUnread?'unread':'' ?>"
               data-subject="<?= strtolower(htmlspecialchars($t['subject'])) ?>"
               data-name="<?= strtolower(htmlspecialchars($t['sender_full']??'')) ?>">
                <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;
                            border-bottom:1px solid #f5f8fa">
                    <div class="user-avatar"
                         style="width:34px;height:34px;font-size:12px;
                                background:<?= $isActive?'var(--c-dark)':'var(--c-mid)' ?>;
                                flex-shrink:0">
                        <?= strtoupper(substr($t['sender_full']??$t['sender_name'],0,1)) ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:4px">
                            <div style="font-size:.8rem;font-weight:<?= $isUnread?'700':'600' ?>;
                                        color:<?= $isActive?'var(--c-dark)':'var(--text-primary)' ?>;
                                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px">
                                <?= htmlspecialchars($t['sender_full']??$t['sender_name']) ?>
                            </div>
                            <div style="font-size:.68rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0">
                                <?= date('d M', strtotime($t['created_at'])) ?>
                            </div>
                        </div>
                        <div style="font-size:.78rem;font-weight:<?= $isUnread?'600':'500' ?>;
                                    color:var(--text-primary);white-space:nowrap;
                                    overflow:hidden;text-overflow:ellipsis;margin-top:1px">
                            <?= htmlspecialchars($t['subject']) ?>
                        </div>
                        <div style="font-size:.73rem;color:var(--text-muted);margin-top:1px;
                                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= $preview ?>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                            <?php
                            $sc = ['unread'=>'badge-danger','read'=>'badge-dark',
                                   'replied'=>'badge-success','closed'=>'badge-dark'];
                            ?>
                            <span class="badge <?= $sc[$t['status']] ?? 'badge-dark' ?>"
                                  style="font-size:.6rem">
                                <?= htmlspecialchars($t['status']) ?>
                            </span>
                            <?php if ($replyCount > 0): ?>
                            <span style="font-size:.68rem;color:var(--text-muted)">
                                <i class="fas fa-reply"></i> <?= $replyCount ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($isUnread): ?>
                            <span style="width:7px;height:7px;border-radius:50%;
                                         background:var(--danger);display:inline-block;
                                         margin-left:auto"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== Right: Chat window ===== -->
    <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0">
        <?php if (!$active): ?>
        <div style="flex:1;display:flex;align-items:center;justify-content:center;
                    color:var(--text-muted);flex-direction:column;gap:10px">
            <i class="fas fa-comments" style="font-size:2.5rem"></i>
            <p style="font-size:.9rem">Select a message thread to start chatting</p>
            <button class="btn btn-accent btn-sm" onclick="openModal('modal-new-thread')">
                <i class="fas fa-pen-to-square"></i> New Message
            </button>
        </div>
        <?php else:
            $replies = json_decode($active['reply_body'] ?? '[]', true) ?: [];
            $isClosed = $active['status'] === 'closed';
            $canReply = !$isClosed && ($isAdm || $active['sender_id'] === $uid);
            $lastTs   = end($replies)['ts'] ?? $active['created_at'];
        ?>

        <!-- Chat header -->
        <div style="padding:12px 18px;border-bottom:1px solid var(--border);
                    display:flex;align-items:center;justify-content:space-between;
                    flex-shrink:0;background:#fafcfd">
            <div>
                <div style="font-weight:700;font-size:.92rem;color:var(--text-primary)">
                    <?= htmlspecialchars($active['subject']) ?>
                </div>
                <div style="font-size:.76rem;color:var(--text-muted)">
                    <?= htmlspecialchars($active['sender_full'] ?? $active['sender_name']) ?>
                    &middot; <?= date('d M Y, H:i', strtotime($active['created_at'])) ?>
                    &middot; <span id="thread-status-badge">
                        <?php $sc = ['unread'=>'badge-danger','read'=>'badge-dark','replied'=>'badge-success','closed'=>'badge-dark']; ?>
                        <span class="badge <?= $sc[$active['status']]??'badge-dark' ?>" style="font-size:.65rem">
                            <?= htmlspecialchars($active['status']) ?>
                        </span>
                    </span>
                </div>
            </div>
            <?php if ($isAdm && !$isClosed): ?>
            <form method="POST" style="display:inline">
                <input type="hidden" name="thread_id" value="<?= $active['id'] ?>">
                <button type="submit" name="close_thread" class="btn btn-ghost btn-sm"
                        data-confirm="Close this thread?"
                        style="font-size:.78rem">
                    <i class="fas fa-xmark-circle"></i> Close
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Messages bubble area -->
        <div id="chat-body"
             style="flex:1;overflow-y:auto;padding:18px 20px;display:flex;
                    flex-direction:column;gap:12px;min-height:0;background:#f8fafc">

            <!-- Original message bubble -->
            <?php
            $isOwnMsg = ($active['sender_id'] === $uid);
            $initls   = strtoupper(substr($active['sender_full']??$active['sender_name'],0,1));
            ?>
            <div style="display:flex;align-items:flex-end;gap:8px;
                        flex-direction:<?= $isOwnMsg?'row-reverse':'row' ?>">
                <div class="user-avatar"
                     style="width:30px;height:30px;font-size:11px;
                            background:var(--c-mid);flex-shrink:0">
                    <?= $initls ?>
                </div>
                <div style="max-width:68%">
                    <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:4px;
                                text-align:<?= $isOwnMsg?'right':'left' ?>">
                        <?= htmlspecialchars($active['sender_full']??$active['sender_name']) ?>
                        &middot; <?= date('d M, H:i', strtotime($active['created_at'])) ?>
                    </div>
                    <div style="background:<?= $isOwnMsg?'var(--c-dark)':'#fff' ?>;
                                color:<?= $isOwnMsg?'#fff':'var(--text-primary)' ?>;
                                padding:10px 14px;border-radius:<?= $isOwnMsg?'12px 12px 2px 12px':'12px 12px 12px 2px' ?>;
                                font-size:.84rem;line-height:1.55;
                                border:1px solid <?= $isOwnMsg?'transparent':'var(--border)' ?>;
                                box-shadow:0 1px 3px rgba(0,0,0,.06)">
                        <?= nl2br(htmlspecialchars($active['body'])) ?>
                    </div>
                </div>
            </div>

            <!-- Reply bubbles -->
            <?php foreach ($replies as $r):
                $isOwn  = ((int)($r['by'] ?? 0) === $uid);
                $rInit  = strtoupper(substr($r['name']??'?', 0, 1));
                $rColor = in_array($r['role']??'',['admin','super_admin']) ? 'var(--c-darkest)' : 'var(--c-mid)';
            ?>
            <div class="chat-bubble"
                 data-ts="<?= htmlspecialchars($r['ts']??'') ?>"
                 style="display:flex;align-items:flex-end;gap:8px;
                        flex-direction:<?= $isOwn?'row-reverse':'row' ?>">
                <div class="user-avatar"
                     style="width:30px;height:30px;font-size:11px;
                            background:<?= $rColor ?>;flex-shrink:0">
                    <?= $rInit ?>
                </div>
                <div style="max-width:68%">
                    <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:4px;
                                text-align:<?= $isOwn?'right':'left' ?>">
                        <?= htmlspecialchars($r['name']??'Unknown') ?>
                        <?php if (in_array($r['role']??'', ['admin','super_admin'])): ?>
                        <span class="badge badge-super_admin" style="font-size:.58rem;margin-left:4px">Admin</span>
                        <?php endif; ?>
                        &middot; <?= date('d M, H:i', strtotime($r['ts']??'now')) ?>
                    </div>
                    <div style="background:<?= $isOwn?'var(--c-dark)':'#fff' ?>;
                                color:<?= $isOwn?'#fff':'var(--text-primary)' ?>;
                                padding:10px 14px;border-radius:<?= $isOwn?'12px 12px 2px 12px':'12px 12px 12px 2px' ?>;
                                font-size:.84rem;line-height:1.55;
                                border:1px solid <?= $isOwn?'transparent':'var(--border)' ?>;
                                box-shadow:0 1px 3px rgba(0,0,0,.06)">
                        <?= nl2br(htmlspecialchars($r['body']??'')) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($isClosed): ?>
            <div style="text-align:center;font-size:.78rem;color:var(--text-muted);
                        padding:8px;border-top:1px dashed var(--border);margin-top:4px">
                <i class="fas fa-lock"></i> This thread has been closed.
            </div>
            <?php endif; ?>
        </div>

        <!-- Reply input -->
        <?php if ($canReply): ?>
        <div style="padding:12px 16px;border-top:1px solid var(--border);
                    flex-shrink:0;background:#fff">
            <form id="reply-form" onsubmit="sendReply(event)">
                <input type="hidden" name="thread_id" value="<?= $active['id'] ?>">
                <div style="display:flex;gap:8px;align-items:flex-end">
                    <textarea name="body" id="reply-input" class="form-control"
                              rows="2" required
                              style="resize:none;font-size:.85rem;flex:1"
                              placeholder="Type a message… (Enter to send, Shift+Enter for newline)"
                              onkeydown="handleReplyKey(event)"></textarea>
                    <button type="submit" id="send-btn"
                            class="btn btn-accent"
                            style="height:62px;padding:0 18px;flex-shrink:0">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div style="font-size:.7rem;color:var(--text-muted);margin-top:4px">
                    <span id="typing-indicator" style="display:none">
                        <i class="fas fa-ellipsis fa-fade"></i> Sending…
                    </span>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

</div><!-- .page-content -->

<!-- New thread modal -->
<div class="modal-overlay" id="modal-new-thread">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-pen-to-square"></i> New Message</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control"
                           required maxlength="200"
                           placeholder="What is your message about?">
                </div>
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="body" class="form-control" rows="4"
                              required placeholder="Write your message here…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                <button type="submit" name="new_thread" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/partials/footer.php'; ?>

<style>
.thread-item {
    display: block;
    text-decoration: none !important;
    transition: background .12s;
}
.thread-item:hover { background: #f5f8fa; }
.thread-item.active { background: var(--info-bg); }
.thread-item.unread .user-avatar { background: var(--danger) !important; }
</style>

<script>
// ---- Thread search filter
function filterThreads(q) {
    const lq = q.toLowerCase();
    document.querySelectorAll('.thread-item').forEach(el => {
        const subj = el.dataset.subject || '';
        const name = el.dataset.name   || '';
        el.style.display = (!lq || subj.includes(lq) || name.includes(lq)) ? '' : 'none';
    });
}

// ---- Reply form — Shift+Enter = newline, Enter = submit
function handleReplyKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('reply-form').dispatchEvent(new Event('submit'));
    }
}

// ---- Send reply via fetch (no page reload)
async function sendReply(e) {
    e.preventDefault();
    const form  = e.target;
    const body  = document.getElementById('reply-input').value.trim();
    const tid   = form.querySelector('[name="thread_id"]').value;
    const ti    = document.getElementById('typing-indicator');
    const btn   = document.getElementById('send-btn');
    if (!body) return;

    ti.style.display = 'inline';
    btn.disabled = true;

    const fd = new FormData(form);
    fd.append('send_reply', '1');
    fd.append('_ajax', '1');

    try {
        const r = await fetch('messages.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            document.getElementById('reply-input').value = '';
            appendBubble({
                name: '<?= addslashes($uname) ?>',
                role: '<?= addslashes($_SESSION['role']??'viewer') ?>',
                body: body,
                ts:   new Date().toISOString().replace('T',' ').slice(0,19)
            }, true);
            scrollChat();
        }
    } catch(err) {}

    ti.style.display = 'none';
    btn.disabled = false;
    document.getElementById('reply-input').focus();
}

// ---- Append a bubble dynamically
function appendBubble(r, isOwn) {
    const rColor = ['admin','super_admin'].includes(r.role) ? 'var(--c-darkest)' : 'var(--c-mid)';
    const rInit  = (r.name || '?')[0].toUpperCase();
    const ts     = r.ts ? r.ts.slice(0,16).replace('T',' ') : '';
    const bdr    = isOwn ? '12px 12px 2px 12px' : '12px 12px 12px 2px';
    const txt    = r.body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    const adminBadge = ['admin','super_admin'].includes(r.role)
        ? '<span class="badge badge-super_admin" style="font-size:.58rem;margin-left:4px">Admin</span>' : '';
    const html = `
    <div class="chat-bubble" data-ts="${r.ts||''}"
         style="display:flex;align-items:flex-end;gap:8px;flex-direction:${isOwn?'row-reverse':'row'}">
        <div class="user-avatar" style="width:30px;height:30px;font-size:11px;background:${isOwn?'var(--c-dark)':rColor};flex-shrink:0">
            ${rInit}
        </div>
        <div style="max-width:68%">
            <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:4px;text-align:${isOwn?'right':'left'}">
                ${r.name||''}${adminBadge} &middot; ${ts}
            </div>
            <div style="background:${isOwn?'var(--c-dark)':'#fff'};color:${isOwn?'#fff':'var(--text-primary)'};
                        padding:10px 14px;border-radius:${bdr};font-size:.84rem;line-height:1.55;
                        border:1px solid ${isOwn?'transparent':'var(--border)'};
                        box-shadow:0 1px 3px rgba(0,0,0,.06)">
                ${txt}
            </div>
        </div>
    </div>`;
    document.getElementById('chat-body').insertAdjacentHTML('beforeend', html);
}

// ---- Auto-scroll chat to bottom
function scrollChat() {
    const cb = document.getElementById('chat-body');
    if (cb) cb.scrollTop = cb.scrollHeight;
}
scrollChat();

// ---- Long-poll for new replies (every 4 seconds)
<?php if ($active && !($active['status']==='closed')): ?>
(function poll() {
    const tid = <?= (int)$active['id'] ?>;
    const bubbles = document.querySelectorAll('.chat-bubble[data-ts]');
    const lastTs  = bubbles.length
        ? [...bubbles].map(b=>b.dataset.ts).filter(Boolean).sort().pop()
        : '<?= addslashes($lastTs) ?>';

    fetch(`messages.php?poll=${tid}&since=${encodeURIComponent(lastTs)}`)
        .then(r => r.json())
        .then(d => {
            if (d.replies && d.replies.length) {
                d.replies.forEach(r => {
                    const isOwn = parseInt(r.by) === <?= $uid ?>;
                    appendBubble(r, isOwn);
                    scrollChat();
                });
            }
        })
        .catch(() => {})
        .finally(() => setTimeout(poll, 4000));
})();
<?php endif; ?>
</script>
