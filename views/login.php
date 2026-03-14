<?php
// ============================================================
// views/login.php  — Clean, minimal, 100% viewport
// ============================================================
require_once __DIR__ . '/../config/app.php';
startSession();
if (!empty($_SESSION['user_id'])) {
    header('Location: '.appUrl().'/views/dashboard.php'); exit;
}
$error = $_SESSION['flash']['message'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= htmlspecialchars(appName()) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= appUrl() ?>/assets/css/style.css">
    <link rel="icon" href="<?= appUrl() ?>/assets/img/favicon.svg" type="image/svg+xml">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }

        .login-root {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* ---- Left panel: form only ---- */
        .lp-left {
            width: 420px;
            flex-shrink: 0;
            background: var(--c-darkest);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .lp-inner {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 44px;
            overflow-y: auto;
        }

        /* brand */
        .lp-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 40px;
        }
        .lp-brand-icon {
            width: 36px; height: 36px;
            background: var(--c-mid);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .lp-brand-icon i { color: #fff; font-size: 15px; }
        .lp-brand-name  { color: #fff; font-size: 1rem; font-weight: 700; }
        .lp-brand-tag   { color: rgba(255,255,255,.38); font-size: .7rem; margin-top: 1px; }

        /* form elements */
        .lp-label {
            display: block;
            color: rgba(255,255,255,.5);
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .lp-input {
            width: 100%;
            padding: 9px 12px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 5px;
            color: #fff;
            font-family: var(--font);
            font-size: .88rem;
            outline: none;
            box-sizing: border-box;
            transition: border-color .15s, background .15s;
            margin-bottom: 16px;
        }
        .lp-input:focus {
            border-color: var(--c-mid);
            background: rgba(255,255,255,.09);
        }
        .lp-input::placeholder { color: rgba(255,255,255,.18); }

        .lp-pwd-wrap {
            position: relative;
            margin-bottom: 16px;
        }
        .lp-pwd-wrap .lp-input { margin-bottom: 0; padding-right: 40px; }
        .lp-pwd-toggle {
            position: absolute; right: 11px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: rgba(255,255,255,.35);
            cursor: pointer; padding: 0;
            font-size: 13px;
        }

        .lp-btn {
            width: 100%;
            padding: 10px;
            background: var(--c-mid);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-family: var(--font);
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 6px;
        }
        .lp-btn:hover    { background: var(--c-dark); }
        .lp-btn:disabled { opacity: .45; pointer-events: none; }

        .lp-error {
            background: rgba(185,28,28,.18);
            border: 1px solid rgba(185,28,28,.3);
            border-radius: 5px;
            padding: 9px 12px;
            color: #fca5a5;
            font-size: .82rem;
            margin-bottom: 16px;
            display: flex; align-items: flex-start; gap: 8px;
        }

        /* ---- Right panel: illustration ---- */
        .lp-right {
            flex: 1;
            background: #112e42;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            padding: 40px 32px 32px;
            gap: 0;
        }

        /* Subtle radial glow in background */
        .lp-right::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 480px; height: 480px;
            background: radial-gradient(circle, rgba(49,93,119,.2) 0%, transparent 70%);
            pointer-events: none;
        }

        .lp-illustration {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            justify-content: center;
            width: 100%;
            max-width: 460px;
        }

        .lp-svg {
            width: 100%;
            max-width: 420px;
            height: auto;
            display: block;
        }

        .lp-caption {
            margin-top: 20px;
            color: rgba(255,255,255,.55);
            font-size: .82rem;
            text-align: center;
            letter-spacing: .01em;
            line-height: 1.5;
        }

        /* Powered by Pie Labs */
        .lp-powered {
            display: flex;
            align-items: center;
            gap: 6px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,.1);
            width: 100%;
            max-width: 420px;
            justify-content: center;
            font-size: .76rem;
            color: rgba(255,255,255,.4);
            letter-spacing: .01em;
            flex-shrink: 0;
        }
        .lp-powered strong {
            color: rgba(255,255,255,.65);
            font-weight: 600;
            letter-spacing: .04em;
        }

        @media (max-width: 768px) {
            .lp-right  { display: none; }
            .lp-left   { width: 100%; }
            .lp-inner  { padding: 0 28px; }
        }
    </style>
</head>
<body>
<div class="login-root">

    <!-- ===== LEFT: Login form only ===== -->
    <div class="lp-left">
        <div class="lp-inner">

            <div class="lp-brand">
                <div class="lp-brand-icon"><i class="fas fa-map-pin"></i></div>
                <div>
                    <div class="lp-brand-name"><?= htmlspecialchars(appName()) ?></div>
                    <div class="lp-brand-tag"><?= htmlspecialchars(setting('general.app_tagline','Digital Addressing System')) ?></div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="lp-error">
                <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:1px"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form action="<?= appUrl() ?>/controllers/auth.php" method="POST" id="lp-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="_csrf"  value="<?= csrfToken() ?>">

                <label class="lp-label">Email</label>
                <input type="email" name="email" class="lp-input"
                       placeholder="you@example.com" required autocomplete="email">

                <label class="lp-label">Password</label>
                <div class="lp-pwd-wrap">
                    <input type="password" name="password" id="lp-pwd" class="lp-input"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="lp-pwd-toggle" onclick="togglePwd()">
                        <i class="fas fa-eye" id="lp-eye"></i>
                    </button>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;
                            margin-bottom:22px">
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer">
                        <input type="checkbox" name="remember" style="accent-color:var(--c-mid)">
                        <span style="color:rgba(255,255,255,.4);font-size:.79rem">Remember me</span>
                    </label>
                    <a href="<?= appUrl() ?>/views/forgot_password.php"
                       style="color:rgba(255,255,255,.38);font-size:.78rem;text-decoration:none;
                              border-bottom:1px solid rgba(255,255,255,.12)">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="lp-btn" id="lp-btn">
                    <i class="fas fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <?php if (settingBool('workflow.allow_self_register', true)): ?>
            <p style="text-align:center;margin-top:22px;font-size:.8rem;
                      color:rgba(255,255,255,.3)">
                New user?
                <a href="<?= appUrl() ?>/views/register_user.php"
                   style="color:rgba(255,255,255,.55);font-weight:600;text-decoration:none">
                    Create account
                </a>
            </p>
            <?php endif; ?>

            <!-- Minimal system footer -->
            <div style="margin-top:36px;padding-top:18px;
                        border-top:1px solid rgba(255,255,255,.07);
                        display:flex;align-items:center;gap:8px">
                <span style="width:6px;height:6px;border-radius:50%;
                             background:#0d7c4a;display:inline-block;flex-shrink:0"></span>
                <span style="color:rgba(255,255,255,.22);font-size:.7rem">
                    <?= htmlspecialchars(appName()) ?> v<?= NEDAMS_VERSION ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT: Animated illustration ===== -->
    <div class="lp-right">

        <!-- Illustration container -->
        <div class="lp-illustration">

            <!-- Animated SVG navigation/mapping scene -->
            <svg viewBox="0 0 420 360" xmlns="http://www.w3.org/2000/svg"
                 class="lp-svg" aria-hidden="true">

                <!-- Background grid lines (map grid) -->
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none"
                              stroke="rgba(49,93,119,.32)" stroke-width=".8"/>
                    </pattern>
                    <!-- Glow filter for pulsing pins -->
                    <filter id="glow">
                        <feGaussianBlur stdDeviation="3" result="blur"/>
                        <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                    </filter>
                    <!-- Dash route path -->
                    <marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5"
                            markerWidth="5" markerHeight="5" orient="auto">
                        <path d="M 0 0 L 10 5 L 0 10 z" fill="#315d77"/>
                    </marker>
                </defs>

                <!-- Grid background -->
                <rect width="420" height="360" fill="url(#grid)"/>

                <!-- Road / street lines -->
                <line x1="0" y1="180" x2="420" y2="180"
                      stroke="rgba(49,93,119,.45)" stroke-width="2"/>
                <line x1="210" y1="0" x2="210" y2="360"
                      stroke="rgba(49,93,119,.45)" stroke-width="2"/>
                <line x1="0" y1="90"  x2="420" y2="90"
                      stroke="rgba(49,93,119,.22)" stroke-width="1"/>
                <line x1="0" y1="270" x2="420" y2="270"
                      stroke="rgba(49,93,119,.22)" stroke-width="1"/>
                <line x1="105" y1="0" x2="105" y2="360"
                      stroke="rgba(49,93,119,.22)" stroke-width="1"/>
                <line x1="315" y1="0" x2="315" y2="360"
                      stroke="rgba(49,93,119,.22)" stroke-width="1"/>

                <!-- Dashed route path -->
                <path d="M 80 280 Q 140 280 140 220 Q 140 180 200 180 Q 260 180 260 130 Q 260 90 320 90"
                      fill="none" stroke="#315d77" stroke-width="2"
                      stroke-dasharray="8 5" marker-end="url(#arrow)"
                      opacity=".7">
                    <animate attributeName="stroke-dashoffset"
                             from="100" to="0" dur="3s"
                             repeatCount="indefinite"/>
                </path>

                <!-- Building blocks (simplified structures) -->
                <!-- Block A -->
                <rect x="50" y="200" width="44" height="54" rx="3"
                      fill="#1a4a68" opacity=".95"/>
                <rect x="56" y="206" width="10" height="10" rx="1" fill="#4a8aa8" opacity=".9"/>
                <rect x="72" y="206" width="10" height="10" rx="1" fill="#4a8aa8" opacity=".9"/>
                <rect x="56" y="222" width="10" height="10" rx="1" fill="#4a8aa8" opacity=".9"/>
                <rect x="72" y="222" width="10" height="10" rx="1" fill="#4a8aa8" opacity=".9"/>
                <rect x="60" y="238" width="24" height="16" rx="2" fill="#0d2d42"/>

                <!-- Block B -->
                <rect x="155" y="190" width="38" height="46" rx="3"
                      fill="#1a4a68" opacity=".9"/>
                <rect x="161" y="196" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="175" y="196" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="161" y="210" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="175" y="210" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="164" y="222" width="20" height="14" rx="2" fill="#0d2d42"/>

                <!-- Block C (taller) -->
                <rect x="240" y="100" width="30" height="70" rx="3"
                      fill="#163d5a" opacity=".95"/>
                <rect x="245" y="106" width="7" height="7" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="257" y="106" width="7" height="7" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="245" y="118" width="7" height="7" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="257" y="118" width="7" height="7" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="245" y="130" width="7" height="7" rx="1" fill="#4a8aa8" opacity=".7"/>
                <rect x="257" y="130" width="7" height="7" rx="1" fill="#4a8aa8" opacity=".7"/>
                <rect x="248" y="142" width="14" height="12" rx="2" fill="#0d2d42"/>

                <!-- Block D -->
                <rect x="310" y="50" width="42" height="52" rx="3"
                      fill="#1a4a68" opacity=".9"/>
                <rect x="316" y="56" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="330" y="56" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="316" y="70" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="330" y="70" width="9" height="9" rx="1" fill="#4a8aa8" opacity=".85"/>
                <rect x="319" y="84" width="20" height="12" rx="2" fill="#0d2d42"/>

                <!-- Trees / parks -->
                <circle cx="130" cy="260" r="12" fill="#0d3d28" opacity=".7"/>
                <circle cx="130" cy="260" r="8"  fill="#0d5a38" opacity=".6"/>
                <line x1="130" y1="270" x2="130" y2="280" stroke="#071c2c" stroke-width="2"/>

                <circle cx="290" cy="155" r="10" fill="#0d3d28" opacity=".6"/>
                <circle cx="290" cy="155" r="7"  fill="#0d5a38" opacity=".5"/>
                <line x1="290" y1="163" x2="290" y2="172" stroke="#071c2c" stroke-width="2"/>

                <!-- Map pin — primary (animated pulse) -->
                <g filter="url(#glow)">
                    <!-- Pulse ring -->
                    <circle cx="80" cy="280" r="18" fill="none"
                            stroke="#315d77" stroke-width="1.5" opacity="0">
                        <animate attributeName="r"
                                 from="10" to="26" dur="2s"
                                 repeatCount="indefinite"/>
                        <animate attributeName="opacity"
                                 from=".7" to="0" dur="2s"
                                 repeatCount="indefinite"/>
                    </circle>
                    <!-- Pin body -->
                    <path d="M80 260 C74 260 68 266 68 272 C68 280 80 294 80 294 C80 294 92 280 92 272 C92 266 86 260 80 260 Z"
                          fill="#315d77"/>
                    <circle cx="80" cy="272" r="4" fill="#fff"/>
                </g>

                <!-- Map pin — destination (animated, delayed) -->
                <g filter="url(#glow)">
                    <circle cx="320" cy="90" r="18" fill="none"
                            stroke="#4a8aa8" stroke-width="1.5" opacity="0">
                        <animate attributeName="r"
                                 from="8" to="22" dur="2s" begin="1s"
                                 repeatCount="indefinite"/>
                        <animate attributeName="opacity"
                                 from=".6" to="0" dur="2s" begin="1s"
                                 repeatCount="indefinite"/>
                    </circle>
                    <path d="M320 72 C315 72 310 77 310 82 C310 89 320 100 320 100 C320 100 330 89 330 82 C330 77 325 72 320 72 Z"
                          fill="#4a8aa8"/>
                    <circle cx="320" cy="82" r="3.5" fill="#fff"/>
                </g>

                <!-- Moving dot along route (delivery/navigation) -->
                <circle r="5" fill="#fff" opacity=".9">
                    <animateMotion
                        path="M 80 280 Q 140 280 140 220 Q 140 180 200 180 Q 260 180 260 130 Q 260 90 320 90"
                        dur="4s" repeatCount="indefinite"
                        calcMode="spline"
                        keySplines="0.4 0 0.6 1; 0.4 0 0.6 1; 0.4 0 0.6 1; 0.4 0 0.6 1"/>
                </circle>
                <circle r="3" fill="#315d77">
                    <animateMotion
                        path="M 80 280 Q 140 280 140 220 Q 140 180 200 180 Q 260 180 260 130 Q 260 90 320 90"
                        dur="4s" repeatCount="indefinite"
                        calcMode="spline"
                        keySplines="0.4 0 0.6 1; 0.4 0 0.6 1; 0.4 0 0.6 1; 0.4 0 0.6 1"/>
                </circle>

                <!-- Small floating address code labels -->
                <g opacity="0">
                    <rect x="88" y="262" width="46" height="16" rx="3" fill="#071c2c"/>
                    <text x="111" y="274" text-anchor="middle"
                          font-family="'IBM Plex Mono',monospace" font-size="7.5"
                          fill="#4a8aa8" letter-spacing="1">NE4K7X</text>
                    <animate attributeName="opacity"
                             values="0;1;1;0" dur="4s" begin="0.5s"
                             repeatCount="indefinite"/>
                </g>
                <g opacity="0">
                    <rect x="328" y="73" width="46" height="16" rx="3" fill="#071c2c"/>
                    <text x="351" y="85" text-anchor="middle"
                          font-family="'IBM Plex Mono',monospace" font-size="7.5"
                          fill="#4a8aa8" letter-spacing="1">NE9R2M</text>
                    <animate attributeName="opacity"
                             values="0;0;1;1;0" dur="4s" begin="2s"
                             repeatCount="indefinite"/>
                </g>

            </svg>

            <!-- Caption below illustration -->
            <p class="lp-caption">
                Giving every structure a unique digital identity
            </p>
        </div>

        <!-- Powered by Pie Labs -->
        <div class="lp-powered">
            <span>Powered by</span>
            <strong>Pie Labs</strong>
        </div>
    </div>
</div>

<script>
function togglePwd() {
    const i = document.getElementById('lp-pwd');
    const e = document.getElementById('lp-eye');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.className = i.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
}
document.getElementById('lp-form').addEventListener('submit', function() {
    const b = document.getElementById('lp-btn');
    b.disabled = true;
    b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';
});
</script>
</body>
</html>
