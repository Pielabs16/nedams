<?php
// ============================================================
// views/rba_challenge.php  — Risk-Based Auth extra verification
// ============================================================
require_once __DIR__ . '/../config/app.php';
startSession();

// Must have a pending RBA challenge in session
$pending = $_SESSION['rba_pending'] ?? null;
if (!$pending || time() > ($pending['expires'] ?? 0)) {
    unset($_SESSION['rba_pending']);
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Verification session expired. Please sign in again.'];
    header('Location: ' . appUrl() . '/views/login.php'); exit;
}

$email   = htmlspecialchars($pending['email'] ?? '');
$reasons = $pending['risk_reasons'] ?? [];
$error   = $_SESSION['flash']['message'] ?? null;
$etype   = $_SESSION['flash']['type']    ?? 'danger';
unset($_SESSION['flash']);

// Mask email for display: jo***@example.com
function maskEmail(string $e): string {
    [$local, $domain] = explode('@', $e, 2) + ['', ''];
    return substr($local, 0, 2) . str_repeat('*', max(2, strlen($local) - 2)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification — <?= htmlspecialchars(appName()) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
    <link rel="icon" href="<?= appUrl() ?>/assets/img/favicon.svg" type="image/svg+xml">
    <style>
        html, body {
            height: 100%; margin: 0; padding: 0;
            background: var(--body-bg);
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: var(--font);
        }
        .rba-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: var(--r-lg);
            box-shadow: var(--card-shadow-md);
            overflow: hidden;
            margin: 20px;
        }
        .rba-header {
            background: var(--c-darkest);
            padding: 28px 32px 24px;
            text-align: center;
        }
        .rba-shield {
            width: 56px; height: 56px;
            background: rgba(49,93,119,.3);
            border: 2px solid rgba(49,93,119,.5);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
        }
        .rba-shield i { color: #7ec8e3; font-size: 22px; }
        .rba-header h2 {
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .rba-header p {
            color: rgba(255,255,255,.5);
            font-size: .82rem;
            line-height: 1.5;
            margin: 0;
        }
        .rba-body { padding: 28px 32px; }

        /* Reason pills */
        .risk-reasons {
            background: var(--warning-bg);
            border: 1px solid rgba(160,92,0,.2);
            border-radius: var(--r-sm);
            padding: 10px 14px;
            margin-bottom: 20px;
        }
        .risk-reasons .label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--warning);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .risk-reasons ul {
            margin: 0; padding: 0 0 0 18px;
            font-size: .79rem;
            color: #7a4600;
            line-height: 1.6;
        }

        /* OTP input */
        .otp-wrap {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 20px 0;
        }
        .otp-digit {
            width: 46px; height: 54px;
            border: 2px solid var(--border);
            border-radius: var(--r-sm);
            text-align: center;
            font-family: var(--font-mono);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--c-darkest);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            background: #fff;
        }
        .otp-digit:focus {
            border-color: var(--c-mid);
            box-shadow: 0 0 0 3px rgba(49,93,119,.12);
        }
        .otp-digit.filled { border-color: var(--c-mid); }

        .rba-btn {
            width: 100%;
            padding: 11px;
            background: var(--c-dark);
            color: #fff;
            border: none;
            border-radius: var(--r-sm);
            font-family: var(--font);
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .rba-btn:hover    { background: var(--c-darkest); }
        .rba-btn:disabled { opacity: .45; pointer-events: none; }

        .rba-footer {
            padding: 14px 32px;
            border-top: 1px solid var(--card-border);
            background: #fafcfd;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .78rem;
        }
        .rba-footer a {
            color: var(--text-muted);
            text-decoration: none;
        }
        .rba-footer a:hover { color: var(--c-mid); }

        /* Timer */
        .timer-wrap {
            display: flex; align-items: center; gap: 6px;
            font-size: .76rem; color: var(--text-muted);
            margin-top: 12px; justify-content: center;
        }
        .timer-val { font-weight: 700; color: var(--c-mid); font-variant-numeric: tabular-nums; }
        .timer-val.urgent { color: var(--danger); }
    </style>
</head>
<body>

<div class="rba-card">

    <!-- Header -->
    <div class="rba-header">
        <div class="rba-shield">
            <i class="fas fa-shield-halved"></i>
        </div>
        <h2>Security Verification Required</h2>
        <p>
            We detected something unusual about this login.<br>
            A 6-digit code was sent to <strong style="color:rgba(255,255,255,.8)"><?= maskEmail($email) ?></strong>
        </p>
    </div>

    <div class="rba-body">

        <!-- Error -->
        <?php if ($error): ?>
        <div class="alert alert-<?= htmlspecialchars($etype) ?>" style="margin-bottom:16px">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <!-- Risk reasons -->
        <?php if ($reasons): ?>
        <div class="risk-reasons">
            <div class="label">
                <i class="fas fa-triangle-exclamation"></i>
                Why we're asking
            </div>
            <ul>
                <?php foreach ($reasons as $r): ?>
                <li><?= htmlspecialchars($r) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- OTP form -->
        <form action="<?= appUrl() ?>/controllers/auth.php" method="POST" id="rba-form">
            <input type="hidden" name="action"   value="rba_verify">
            <input type="hidden" name="rba_code" id="rba-code-hidden">

            <div style="text-align:center;font-size:.84rem;color:var(--text-secondary);margin-bottom:4px">
                Enter the 6-digit code
            </div>

            <!-- 6 individual digit inputs for a polished OTP UI -->
            <div class="otp-wrap" id="otp-inputs">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="text" inputmode="numeric" pattern="[0-9]"
                       maxlength="1" class="otp-digit"
                       autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                       id="otp-<?= $i ?>">
                <?php endfor; ?>
            </div>

            <!-- Countdown timer -->
            <div class="timer-wrap">
                <i class="fas fa-clock" style="font-size:11px"></i>
                Code expires in <span class="timer-val" id="timer-val">15:00</span>
            </div>

            <div style="margin-top:20px">
                <button type="submit" class="rba-btn" id="rba-btn" disabled>
                    <i class="fas fa-shield-check"></i> Verify &amp; Sign In
                </button>
            </div>
        </form>

        <!-- Resend -->
        <p style="text-align:center;margin-top:14px;font-size:.78rem;color:var(--text-muted)">
            Didn't receive the code?
            <a href="<?= appUrl() ?>/controllers/auth.php?action=rba_resend"
               style="color:var(--c-mid);font-weight:600;text-decoration:none"
               id="resend-link">
                Resend
            </a>
        </p>
    </div>

    <div class="rba-footer">
        <a href="<?= appUrl() ?>/views/login.php">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
        <span style="color:var(--text-muted)">
            <i class="fas fa-lock" style="font-size:10px"></i>
            <?= htmlspecialchars(appName()) ?> Security
        </span>
    </div>
</div>

<script>
// ---- OTP digit input behaviour ----
const inputs  = Array.from(document.querySelectorAll('.otp-digit'));
const hidden  = document.getElementById('rba-code-hidden');
const btn     = document.getElementById('rba-btn');

inputs.forEach((inp, i) => {
    inp.addEventListener('input', e => {
        // Strip non-digits
        inp.value = inp.value.replace(/[^0-9]/g, '').slice(-1);
        inp.classList.toggle('filled', inp.value !== '');

        // Auto-advance
        if (inp.value && i < inputs.length - 1) inputs[i + 1].focus();

        syncHidden();
    });

    inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && i > 0) {
            inputs[i - 1].value = '';
            inputs[i - 1].classList.remove('filled');
            inputs[i - 1].focus();
            syncHidden();
        }
        // Allow paste
        if (e.key === 'v' && (e.ctrlKey || e.metaKey)) return;
    });

    inp.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
            .getData('text').replace(/[^0-9]/g, '').slice(0, 6);
        pasted.split('').forEach((ch, j) => {
            if (inputs[j]) {
                inputs[j].value = ch;
                inputs[j].classList.add('filled');
            }
        });
        const next = Math.min(pasted.length, inputs.length - 1);
        inputs[next].focus();
        syncHidden();
    });
});

function syncHidden() {
    const code = inputs.map(i => i.value).join('');
    hidden.value = code;
    btn.disabled = code.length < 6;
}

// Auto-focus first input on load
inputs[0]?.focus();

// ---- Countdown timer ----
const expiry = <?= (int)($pending['expires'] ?? (time() + 900)) ?> * 1000;
function updateTimer() {
    const remaining = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
    const m = String(Math.floor(remaining / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    const el = document.getElementById('timer-val');
    el.textContent = m + ':' + s;
    el.className = 'timer-val' + (remaining < 120 ? ' urgent' : '');
    if (remaining === 0) {
        el.textContent = 'Expired';
        btn.disabled = true;
        document.getElementById('rba-form').innerHTML =
            '<div class="alert alert-danger"><i class="fas fa-clock"></i>' +
            '<div>Verification code expired. <a href="' + <?= json_encode(appUrl() . '/views/login.php') ?> +
            '">Sign in again</a> to receive a new code.</div></div>';
    }
}
updateTimer();
setInterval(updateTimer, 1000);

// ---- Submit spinner ----
document.getElementById('rba-form').addEventListener('submit', function() {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';
});
</script>
</body>
</html>
