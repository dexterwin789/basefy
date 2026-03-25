<?php
/**
 * Premium Chat Widget — Shopee-style floating chat.
 * Include this partial on storefront pages (produto.php, loja.php).
 * 
 * Required vars:
 *   $chatVendorId  (int)  — vendor user ID
 *   $chatVendorName (string) — vendor/store display name
 *   $chatProductId (int|null) — optional product context
 *   $chatProductName (string|null) — optional product name
 *   $isLoggedIn (bool)
 *   $userId (int)
 */
$chatVendorId     = $chatVendorId ?? 0;
$chatVendorName   = $chatVendorName ?? 'Vendedor';
$chatVendorAvatar = $chatVendorAvatar ?? '';
$chatProductId    = $chatProductId ?? 0;
$chatProductName  = $chatProductName ?? '';
$chatProductImage = $chatProductImage ?? '';
$chatVendorLastSeen = $chatVendorLastSeen ?? null;

// Don't show chat button if vendor is the current user
if ($chatVendorId <= 0 || $chatVendorId === ($userId ?? 0)) return;
?>

<!-- Chat Widget CSS -->
<style>
/* ========== FLOATING CHAT BUTTON ========== */
.chat-fab {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 1000;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--t-accent) 0%, var(--t-accent-hover) 100%);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(var(--t-accent-rgb),0.35), 0 2px 8px rgba(0,0,0,0.2);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    animation: chatFabPulse 3s infinite;
}
.chat-fab:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 8px 32px rgba(var(--t-accent-rgb),0.45), 0 4px 12px rgba(0,0,0,0.25);
}
.chat-fab:active { transform: scale(0.95); }
.chat-fab svg { width: 26px; height: 26px; color: var(--t-text-primary); }
.chat-fab .chat-fab-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #ef4444;
    color: var(--t-text-primary);
    font-size: 11px;
    font-weight: 700;
    display: none;
    align-items: center;
    justify-content: center;
    line-height: 1;
    border: 2px solid var(--t-bg-body);
    animation: badgeBounce 0.4s ease-out;
}
.chat-fab .chat-fab-badge.show { display: flex; }

@keyframes chatFabPulse {
    0%, 100% { box-shadow: 0 4px 20px rgba(var(--t-accent-rgb),0.35), 0 2px 8px rgba(0,0,0,0.2); }
    50% { box-shadow: 0 4px 28px rgba(var(--t-accent-rgb),0.5), 0 2px 8px rgba(0,0,0,0.2), 0 0 0 8px rgba(var(--t-accent-rgb),0.08); }
}
@keyframes badgeBounce {
    0% { transform: scale(0); }
    60% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* ========== CHAT INLINE BUTTON (for product/vendor pages) ========== */
.chat-inline-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(var(--t-accent-rgb),0.12) 0%, rgba(var(--t-accent-rgb),0.08) 100%);
    border: 1px solid rgba(var(--t-accent-rgb),0.25);
    color: var(--t-accent);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    text-decoration: none;
}
.chat-inline-btn:hover {
    background: linear-gradient(135deg, rgba(var(--t-accent-rgb),0.2) 0%, rgba(var(--t-accent-rgb),0.15) 100%);
    border-color: rgba(var(--t-accent-rgb),0.45);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--t-accent-rgb),0.15);
}
.chat-inline-btn svg { width: 18px; height: 18px; flex-shrink: 0; }

/* ========== CHAT PANEL ========== */
.chat-panel {
    position: fixed;
    bottom: 96px;
    right: 28px;
    z-index: 1001;
    width: 380px;
    max-width: calc(100vw - 32px);
    height: 520px;
    max-height: calc(100vh - 140px);
    background: var(--t-bg-card);
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.04);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(16px) scale(0.96);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
}
.chat-panel.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* Panel header */
.chat-panel-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: linear-gradient(180deg, rgba(var(--t-accent-rgb),0.08) 0%, transparent 100%);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}
.chat-panel-header .chat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--t-accent), var(--t-accent-hover));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.chat-panel-header .chat-avatar svg { width: 20px; height: 20px; color: var(--t-text-primary); }
.chat-panel-header .chat-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
.chat-panel-header .chat-info { flex: 1; min-width: 0; }
.chat-panel-header .chat-info .chat-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--t-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-panel-header .chat-info .chat-status {
    font-size: 12px;
    color: var(--t-accent);
    display: flex;
    align-items: center;
    gap: 4px;
}
.chat-panel-header .chat-info .chat-status::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--t-accent);
    display: inline-block;
}
.chat-panel-close {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--t-text-muted);
    transition: all 0.2s;
    flex-shrink: 0;
}
.chat-panel-close:hover { background: rgba(255,255,255,0.1); color: var(--t-text-primary); }
.chat-panel-close svg { width: 16px; height: 16px; }

/* Product context bar */
.chat-product-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid rgba(255,255,255,0.04);
    flex-shrink: 0;
}
.chat-product-bar img {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    object-fit: cover;
    background: #1a1a1a;
    flex-shrink: 0;
}
.chat-product-bar .chat-product-info {
    flex: 1;
    min-width: 0;
}
.chat-product-bar .chat-product-info .chat-product-name {
    font-size: 12px;
    color: #ccc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-product-bar .chat-product-info .chat-product-label {
    font-size: 10px;
    color: var(--t-text-muted);
}

/* ========== CONVERSATIONS LIST ========== */
.chat-conv-list {
    flex: 1;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.chat-conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid rgba(255,255,255,0.03);
}
.chat-conv-item:hover { background: rgba(255,255,255,0.04); }
.chat-conv-item .conv-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.chat-conv-item .conv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.chat-conv-item .conv-avatar svg { width: 20px; height: 20px; color: var(--t-text-muted); }
.chat-conv-item .conv-info { flex: 1; min-width: 0; }
.chat-conv-item .conv-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--t-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-conv-item .conv-preview {
    font-size: 12px;
    color: var(--t-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}
.chat-conv-item .conv-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    flex-shrink: 0;
}
.chat-conv-item .conv-time {
    font-size: 11px;
    color: var(--t-text-muted);
}
.chat-conv-item .conv-unread {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--t-accent);
    color: var(--t-text-on-accent);
    font-size: 10px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ========== MESSAGES AREA ========== */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

/* Message bubbles */
.chat-msg {
    max-width: 82%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.5;
    word-wrap: break-word;
    position: relative;
    animation: msgSlideIn 0.25s ease-out;
}
.chat-msg.mine {
    align-self: flex-end;
    background: linear-gradient(135deg, var(--t-accent), var(--t-accent-hover));
    color: var(--t-text-on-accent);
    border-bottom-right-radius: 6px;
}
.chat-msg.theirs {
    align-self: flex-start;
    background: rgba(255,255,255,0.06);
    color: var(--t-text-primary);
    border-bottom-left-radius: 6px;
    border: 1px solid rgba(255,255,255,0.06);
}
.chat-msg .msg-time {
    font-size: 10px;
    margin-top: 4px;
    opacity: 0.6;
}
.chat-msg.mine .msg-time { text-align: right; color: rgba(0,0,0,0.5); }
.chat-msg.theirs .msg-time { color: var(--t-text-muted); }

.chat-msg-date {
    text-align: center;
    font-size: 11px;
    color: var(--t-text-muted);
    padding: 8px 0;
    font-weight: 500;
}

@keyframes msgSlideIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Typing indicator */
.chat-typing {
    display: none;
    align-self: flex-start;
    padding: 10px 16px;
    background: rgba(255,255,255,0.06);
    border-radius: 16px 16px 16px 6px;
    border: 1px solid rgba(255,255,255,0.06);
}
.chat-typing.show { display: flex; }
.chat-typing span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #555;
    margin: 0 2px;
    animation: typingDot 1.4s infinite;
}
.chat-typing span:nth-child(2) { animation-delay: 0.2s; }
.chat-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingDot {
    0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
    30% { opacity: 1; transform: translateY(-4px); }
}

/* ========== MESSAGE INPUT ========== */
.chat-input-bar {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.3);
    flex-shrink: 0;
}
.chat-input-bar textarea {
    flex: 1;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 10px 14px;
    color: var(--t-text-primary);
    font-size: 13px;
    line-height: 1.4;
    resize: none;
    outline: none;
    max-height: 80px;
    min-height: 40px;
    font-family: inherit;
    transition: border-color 0.2s;
}
.chat-input-bar textarea:focus {
    border-color: rgba(var(--t-accent-rgb),0.4);
}
.chat-input-bar textarea::placeholder { color: var(--t-text-muted); }
.chat-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--t-accent), var(--t-accent-hover));
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}
.chat-send-btn:hover { filter: brightness(1.1); transform: scale(1.05); }
.chat-send-btn:active { transform: scale(0.95); }
.chat-send-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.chat-send-btn svg { width: 18px; height: 18px; color: var(--t-text-primary); }

/* Panel back button */
.chat-back-btn {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--t-text-muted);
    transition: all 0.2s;
    flex-shrink: 0;
}
.chat-back-btn:hover { background: rgba(255,255,255,0.1); color: var(--t-text-primary); }
.chat-back-btn svg { width: 16px; height: 16px; }

/* Empty state */
.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px;
    text-align: center;
}
.chat-empty svg { width: 48px; height: 48px; color: #333; margin-bottom: 12px; }
.chat-empty p { font-size: 13px; color: var(--t-text-muted); line-height: 1.5; }

/* Login prompt */
.chat-login-prompt {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px;
    text-align: center;
}
.chat-login-prompt svg { width: 48px; height: 48px; color: #333; margin-bottom: 16px; }
.chat-login-prompt p { font-size: 14px; color: #888; margin-bottom: 16px; }
.chat-login-prompt a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 24px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--t-accent), var(--t-accent-hover));
    color: var(--t-text-on-accent);
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s;
}
.chat-login-prompt a:hover { filter: brightness(1.1); }

/* Login overlay for input area */
.chat-login-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 10;
    background: rgba(0,0,0,0.92);
    border-top: 1px solid rgba(var(--t-accent-rgb),0.15);
    padding: 20px;
    text-align: center;
    backdrop-filter: blur(8px);
    border-radius: 0 0 20px 20px;
}
.chat-login-overlay p {
    font-size: 13px;
    color: #888;
    margin-bottom: 12px;
}
.chat-login-overlay a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--t-accent), var(--t-accent-hover));
    color: var(--t-text-on-accent);
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s;
}
.chat-login-overlay a:hover { filter: brightness(1.1); }

/* Nav tabs inside panel */
.chat-panel-tabs {
    display: flex;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}
.chat-panel-tab {
    flex: 1;
    padding: 10px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    color: var(--t-text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.chat-panel-tab.active { color: var(--t-accent); border-bottom-color: var(--t-accent); }
.chat-panel-tab:hover { color: var(--t-text-muted); }

/* Mobile full screen */
@media (max-width: 480px) {
    .chat-panel {
        bottom: 0;
        right: 0;
        width: 100vw;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
        max-width: 100vw;
    }
    .chat-fab {
        bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        right: 20px;
    }
}
</style>

<!-- Chat FAB Button -->
<button class="chat-fab" id="chatFab" title="Conversar com <?= htmlspecialchars($chatVendorName, ENT_QUOTES, 'UTF-8') ?>">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
    </svg>
    <span class="chat-fab-badge" id="chatFabBadge">0</span>
</button>

<!-- Chat Panel -->
<div class="chat-panel" id="chatPanel">
    <!-- VIEW: Conversation (default — direct to vendor) -->
    <div id="chatViewConv" style="display:flex; flex-direction:column; height:100%;">
        <!-- Header -->
        <div class="chat-panel-header">
            <div class="chat-avatar">
                <?php if ($chatVendorAvatar): ?>
                <img src="<?= htmlspecialchars($chatVendorAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?php endif; ?>
            </div>
            <div class="chat-info">
                <div class="chat-name" id="chatHeaderName"><?= htmlspecialchars($chatVendorName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="chat-status" id="chatHeaderStatus"><?php
                    if ($chatVendorLastSeen) {
                        try {
                            $lsDt = new DateTime($chatVendorLastSeen);
                            $lsNow = new DateTime();
                            $lsDiff = $lsNow->getTimestamp() - $lsDt->getTimestamp();
                            if ($lsDiff < 300) echo 'Online';
                            elseif ($lsDiff < 3600) echo 'visto há ' . max(1, intdiv($lsDiff, 60)) . ' min';
                            elseif ($lsDiff < 86400) echo 'visto há ' . intdiv($lsDiff, 3600) . 'h';
                            elseif ($lsDiff < 172800) echo 'visto ontem';
                            else echo 'visto ' . $lsDt->format('d/m');
                        } catch (Throwable $e) { echo 'Online'; }
                    } else { echo 'Online'; }
                ?></div>
            </div>
            <button class="chat-panel-close" id="chatPanelClose" title="Fechar">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <?php if ($chatProductName): ?>
        <div class="chat-product-bar" id="chatProductBar">
            <?php if ($chatProductImage): ?>
            <img src="<?= htmlspecialchars($chatProductImage, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#1a1a1a">
            <?php else: ?>
            <div style="width:36px;height:36px;border-radius:8px;background:#1a1a1a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#555" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <?php endif; ?>
            <div class="chat-product-info">
                <div class="chat-product-label">Sobre o produto:</div>
                <div class="chat-product-name"><?= htmlspecialchars($chatProductName, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="chat-empty" id="chatEmpty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p>Envie uma mensagem para<br><strong style="color:var(--t-accent)"><?= htmlspecialchars($chatVendorName, ENT_QUOTES, 'UTF-8') ?></strong></p>
            </div>
        </div>

        <!-- Input -->
        <div class="chat-input-bar">
            <textarea id="chatInput" rows="1" placeholder="Digite uma mensagem..." maxlength="2000"></textarea>
            <button class="chat-send-btn" id="chatSendBtn" title="Enviar">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </div>
        <?php if (!($isLoggedIn ?? false)): ?>
        <!-- Login overlay for non-logged users -->
        <div class="chat-login-overlay" id="chatLoginOverlay" style="display:none;">
            <p>Faça login para enviar mensagens</p>
            <a href="#" onclick="location.href='<?= BASE_PATH ?>/login?return_to='+encodeURIComponent(location.pathname+location.search+'#chat-open');return false;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                Fazer login
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chat JS -->
<script>
(function(){
    const CHAT_API    = '<?= BASE_PATH ?>/api/chat';
    const IS_LOGGED_IN = <?= ($isLoggedIn ?? false) ? 'true' : 'false' ?>;
    const VENDOR_ID   = <?= (int)$chatVendorId ?>;
    const PRODUCT_ID  = <?= (int)$chatProductId ?>;
    const USER_ID     = <?= (int)($userId ?? 0) ?>;

    const fab       = document.getElementById('chatFab');
    const panel     = document.getElementById('chatPanel');
    const closeBtn  = document.getElementById('chatPanelClose');
    const input     = document.getElementById('chatInput');
    const sendBtn   = document.getElementById('chatSendBtn');
    const msgArea   = document.getElementById('chatMessages');
    const emptyEl   = document.getElementById('chatEmpty');
    const badge     = document.getElementById('chatFabBadge');
    const loginOverlay = document.getElementById('chatLoginOverlay');

    let convId      = 0;
    let lastMsgId   = 0;
    let pollTimer   = null;
    let isOpen      = false;
    let sending     = false;
    let lastSendTime = 0;

    // Toggle panel
    fab.addEventListener('click', () => {
        isOpen = !isOpen;
        panel.classList.toggle('open', isOpen);
        if (isOpen) {
            if (IS_LOGGED_IN) {
                startChat();
                if (input) input.focus();
            }
        } else {
            stopPolling();
            if (loginOverlay) loginOverlay.style.display = 'none';
        }
    });

    closeBtn.addEventListener('click', () => {
        isOpen = false;
        panel.classList.remove('open');
        stopPolling();
        if (loginOverlay) loginOverlay.style.display = 'none';
    });

    // Start conversation
    async function startChat() {
        if (convId > 0) {
            startPolling();
            return;
        }
        try {
            const fd = new FormData();
            fd.append('vendor_id', VENDOR_ID);
            if (PRODUCT_ID > 0) fd.append('product_id', PRODUCT_ID);
            const r = await fetch(CHAT_API + '?action=start', { method: 'POST', body: fd });
            const j = await r.json();
            if (j.ok) {
                convId = j.conversation_id;
                loadMessages();
                startPolling();
            }
        } catch(e) { console.error('Chat start error', e); }
    }

    // Load messages
    async function loadMessages() {
        if (!convId) return;
        try {
            const r = await fetch(CHAT_API + '?action=messages&conversation_id=' + convId);
            const j = await r.json();
            if (!j.ok) return;

            renderMessages(j.messages);
        } catch(e) { console.error('Load messages error', e); }
    }

    // Render messages
    function renderMessages(msgs) {
        if (!msgs.length) return;
        if (emptyEl) emptyEl.style.display = 'none';

        // Clear existing messages (except empty state)
        msgArea.querySelectorAll('.chat-msg, .chat-msg-date').forEach(el => el.remove());

        let lastDate = '';
        msgs.forEach(m => {
            const d = formatDate(m.criado_em);
            if (d !== lastDate) {
                lastDate = d;
                const dateEl = document.createElement('div');
                dateEl.className = 'chat-msg-date';
                dateEl.textContent = d;
                msgArea.appendChild(dateEl);
            }
            appendMessage(m);
        });

        scrollToBottom();
    }

    function appendMessage(m) {
        const el = document.createElement('div');
        el.className = 'chat-msg ' + (m.is_mine ? 'mine' : 'theirs');
        el.dataset.msgId = m.id;

        const text = document.createElement('div');
        text.textContent = m.message;
        el.appendChild(text);

        const time = document.createElement('div');
        time.className = 'msg-time';
        time.textContent = formatTime(m.criado_em);
        el.appendChild(time);

        msgArea.appendChild(el);
        if (m.id > lastMsgId) lastMsgId = m.id;
    }

    // Send message
    async function send() {
        if (!IS_LOGGED_IN) {
            if (loginOverlay) loginOverlay.style.display = '';
            return;
        }
        if (sending || !input) return;
        const text = input.value.trim();
        if (!text) return;

        // Debounce: prevent rapid double-sends (min 800ms between sends)
        const now = Date.now();
        if (now - lastSendTime < 800) return;
        lastSendTime = now;

        sending = true;
        sendBtn.disabled = true;

        if (!convId) await startChat();

        try {
            const fd = new FormData();
            fd.append('conversation_id', convId);
            fd.append('message', text);
            const r = await fetch(CHAT_API + '?action=send', { method: 'POST', body: fd });
            const j = await r.json();
            if (j.ok && j.msg) {
                if (emptyEl) emptyEl.style.display = 'none';
                appendMessage(j.msg);
                scrollToBottom();
                input.value = '';
                autoResize();
            }
        } catch(e) { console.error('Send error', e); }

        sending = false;
        sendBtn.disabled = false;
        input.focus();
    }

    if (sendBtn) sendBtn.addEventListener('click', send);
    if (input) {
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        });
        input.addEventListener('input', autoResize);
        if (!IS_LOGGED_IN) {
            input.addEventListener('focus', () => {
                if (loginOverlay) loginOverlay.style.display = '';
            });
        }
    }

    function autoResize() {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 80) + 'px';
    }

    // Polling for new messages
    function startPolling() {
        stopPolling();
        pollTimer = setInterval(poll, 3000);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    async function poll() {
        if (!convId || !isOpen || sending) return;
        try {
            const r = await fetch(CHAT_API + '?action=poll&conversation_id=' + convId + '&after_id=' + lastMsgId);
            const j = await r.json();
            if (j.ok && j.messages.length > 0) {
                if (emptyEl) emptyEl.style.display = 'none';
                let added = false;
                j.messages.forEach(m => {
                    // Prevent duplicate messages (race condition with send)
                    if (!msgArea.querySelector('[data-msg-id="' + m.id + '"]')) {
                        appendMessage(m);
                        added = true;
                    }
                });
                if (added) scrollToBottom();
            }
        } catch(e) {}
    }

    // Unread badge polling
    async function pollUnread() {
        if (!IS_LOGGED_IN) return;
        try {
            const r = await fetch(CHAT_API + '?action=unread_count');
            const j = await r.json();
            if (j.ok) {
                const n = j.count || 0;
                badge.textContent = n;
                badge.classList.toggle('show', n > 0);
            }
        } catch(e) {}
    }
    if (IS_LOGGED_IN) {
        pollUnread();
        setInterval(pollUnread, 15000);
    }

    // Helpers
    function scrollToBottom() {
        requestAnimationFrame(() => {
            msgArea.scrollTop = msgArea.scrollHeight;
        });
    }

    function formatDate(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        if (d.toDateString() === today.toDateString()) return 'Hoje';
        if (d.toDateString() === yesterday.toDateString()) return 'Ontem';
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
    }

    function formatTime(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    // Auto-open from hash (after login redirect)
    if (window.location.hash === '#chat-open') {
        setTimeout(() => {
            fab.click();
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }, 500);
    }
})();
</script>
