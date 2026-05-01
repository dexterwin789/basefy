<?php
/**
 * Vendor Floating Chat Widget — Shopee-style.
 * Include in vendor panel pages via vendor_layout_end.php.
 * 
 * Required session: vendor must be logged in.
 * Uses the existing chat API endpoints with role=vendedor.
 */
$chatWidgetUserId = (int)($_SESSION['user_id'] ?? 0);
if ($chatWidgetUserId <= 0) return;
?>

<!-- Vendor Chat Widget CSS -->
<style>
/* ========== VENDOR FLOATING CHAT BUTTON ========== */
.vchat-fab {
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
    animation: vchatFabPulse 3s infinite;
}
.vchat-fab:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 8px 32px rgba(var(--t-accent-rgb),0.45), 0 4px 12px rgba(0,0,0,0.25);
}
.vchat-fab:active { transform: scale(0.95); }
.vchat-fab svg { width: 26px; height: 26px; color: var(--t-text-primary); }
.vchat-fab .vchat-fab-badge {
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
    animation: vchatBadgeBounce 0.4s ease-out;
}
.vchat-fab .vchat-fab-badge.show { display: flex; }

@keyframes vchatFabPulse {
    0%, 100% { box-shadow: 0 4px 20px rgba(var(--t-accent-rgb),0.35), 0 2px 8px rgba(0,0,0,0.2); }
    50% { box-shadow: 0 4px 28px rgba(var(--t-accent-rgb),0.5), 0 2px 8px rgba(0,0,0,0.2), 0 0 0 8px rgba(var(--t-accent-rgb),0.08); }
}
@keyframes vchatBadgeBounce {
    0% { transform: scale(0); }
    60% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* ========== VENDOR CHAT PANEL ========== */
.vchat-panel {
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
.vchat-panel.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* Panel header */
.vchat-panel-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: linear-gradient(180deg, rgba(var(--t-accent-rgb),0.08) 0%, transparent 100%);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
}
.vchat-panel-header .vchat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--t-accent), var(--t-accent-hover));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.vchat-panel-header .vchat-avatar svg { width: 20px; height: 20px; color: var(--t-text-primary); }
.vchat-panel-header .vchat-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
.vchat-panel-header .vchat-info { flex: 1; min-width: 0; }
.vchat-panel-header .vchat-info .vchat-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--t-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vchat-panel-header .vchat-info .vchat-status {
    font-size: 12px;
    color: var(--t-accent);
    display: flex;
    align-items: center;
    gap: 4px;
}
.vchat-panel-header .vchat-info .vchat-status::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--t-accent);
    display: inline-block;
}
.vchat-panel-close {
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
.vchat-panel-close:hover { background: rgba(255,255,255,0.1); color: var(--t-text-primary); }
.vchat-panel-close svg { width: 16px; height: 16px; }

/* Back button */
.vchat-back-btn {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--t-text-muted);
    transition: all 0.2s;
    flex-shrink: 0;
}
.vchat-back-btn:hover { background: rgba(255,255,255,0.1); color: var(--t-text-primary); }
.vchat-back-btn svg { width: 16px; height: 16px; }

/* ========== CONVERSATIONS LIST ========== */
.vchat-conv-list {
    flex: 1;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.vchat-conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid rgba(255,255,255,0.03);
}
.vchat-conv-item:hover { background: rgba(255,255,255,0.04); }
.vchat-conv-item .vconv-avatar {
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
.vchat-conv-item .vconv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.vchat-conv-item .vconv-avatar svg { width: 20px; height: 20px; color: var(--t-text-muted); }
.vchat-conv-item .vconv-info { flex: 1; min-width: 0; }
.vchat-conv-item .vconv-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--t-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vchat-conv-item .vconv-preview {
    font-size: 12px;
    color: var(--t-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}
.vchat-conv-item .vconv-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    flex-shrink: 0;
}
.vchat-conv-item .vconv-time {
    font-size: 11px;
    color: var(--t-text-muted);
}
.vchat-conv-item .vconv-unread {
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
.vchat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: none;
    flex-direction: column;
    gap: 6px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
}
.vchat-messages::-webkit-scrollbar { width: 4px; }
.vchat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

/* Message bubbles */
.vchat-msg {
    max-width: 82%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.5;
    word-wrap: break-word;
    position: relative;
    animation: vchatMsgSlideIn 0.25s ease-out;
}
.vchat-msg.mine {
    align-self: flex-end;
    background: linear-gradient(135deg, var(--t-accent), var(--t-accent-hover));
    color: var(--t-text-on-accent);
    border-bottom-right-radius: 6px;
}
.vchat-msg.theirs {
    align-self: flex-start;
    background: rgba(255,255,255,0.06);
    color: var(--t-text-primary);
    border-bottom-left-radius: 6px;
    border: 1px solid rgba(255,255,255,0.06);
}
.vchat-msg .vmsg-time {
    font-size: 10px;
    margin-top: 4px;
    opacity: 0.6;
}
.vchat-msg.mine .vmsg-time { text-align: right; color: rgba(0,0,0,0.5); }
.vchat-msg.theirs .vmsg-time { color: var(--t-text-muted); }

.vchat-msg-date {
    text-align: center;
    font-size: 11px;
    color: var(--t-text-muted);
    padding: 8px 0;
    font-weight: 500;
}

@keyframes vchatMsgSlideIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ========== MESSAGE INPUT ========== */
.vchat-input-bar {
    display: none;
    align-items: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.3);
    flex-shrink: 0;
}
.vchat-input-bar textarea {
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
.vchat-input-bar textarea:focus {
    border-color: rgba(var(--t-accent-rgb),0.4);
}
.vchat-input-bar textarea::placeholder { color: var(--t-text-muted); }
.vchat-send-btn {
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
.vchat-send-btn:hover { filter: brightness(1.1); transform: scale(1.05); }
.vchat-send-btn:active { transform: scale(0.95); }
.vchat-send-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.vchat-send-btn svg { width: 18px; height: 18px; color: var(--t-text-primary); }

/* Empty state */
.vchat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px;
    text-align: center;
}
.vchat-empty svg { width: 48px; height: 48px; color: #333; margin-bottom: 12px; }
.vchat-empty p { font-size: 13px; color: var(--t-text-muted); line-height: 1.5; }

/* ── System messages ── */
.vchat-msg.sys-msg{max-width:92%;align-self:flex-start;background:linear-gradient(135deg,rgba(136,0,228,.08),rgba(136,0,228,.03))!important;border:1px solid rgba(136,0,228,.2)!important;border-radius:14px!important;padding:12px 14px!important;color:var(--t-text-primary,#e5e5e5)!important}
.vchat-msg.sys-msg .sys-hd{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.06)}
.vchat-msg.sys-msg.t-inst .sys-hd{color:#8800E4}
.vchat-msg.sys-msg.t-dlvr .sys-hd{color:#8800E4}
.vchat-msg.sys-msg.t-dlvr{background:linear-gradient(135deg,rgba(136,0,228,.08),rgba(136,0,228,.03))!important;border-color:rgba(136,0,228,.2)!important}
.vchat-msg.sys-msg.t-sys .sys-hd{color:#f59e0b}
.vchat-msg.sys-msg.t-sys{background:linear-gradient(135deg,rgba(245,158,11,.08),rgba(245,158,11,.03))!important;border-color:rgba(245,158,11,.2)!important}
.vchat-msg.sys-msg .sys-ct{white-space:pre-wrap;font-size:12px;line-height:1.55}
.vchat-msg.sys-msg .sys-bx{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:10px;margin:6px 0;font-family:'Courier New',monospace;font-size:11px;color:#d1d5db;white-space:pre-wrap;word-break:break-all;position:relative}
.vchat-msg.sys-msg .sys-cp{position:absolute;top:4px;right:4px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:5px;padding:3px 6px;font-size:9px;color:#a3a3a3;cursor:pointer;transition:all .2s}
.vchat-msg.sys-msg .sys-cp:hover{background:rgba(255,255,255,.15);color:#fff}

/* ── Delivery code bar ── */
.vchat-delivery-bar{display:none;padding:8px 12px;border-top:1px solid rgba(255,255,255,.06);background:linear-gradient(180deg,rgba(136,0,228,.04),rgba(0,0,0,.15));flex-shrink:0}
.vchat-delivery-bar .vd-hd{display:flex;align-items:center;gap:6px;margin-bottom:6px}
.vchat-delivery-bar .vd-icon{display:none}
.vchat-delivery-bar .vd-title{font-size:11px;font-weight:700;color:var(--t-text-primary,#fff)}
.vchat-delivery-bar .vd-sub{font-size:9px;color:var(--t-accent);margin-top:0}
.vchat-delivery-bar .vd-boxes{display:flex;gap:4px;justify-content:center;margin-bottom:6px}
.vchat-delivery-bar .vd-box{width:30px;height:34px;text-align:center;font-size:14px;font-family:'Courier New',monospace;font-weight:700;background:rgba(255,255,255,.04);border:1.5px solid rgba(255,255,255,.1);border-radius:8px;color:var(--t-text-primary,#fff);outline:none;text-transform:uppercase;transition:border-color .2s,box-shadow .2s;caret-color:var(--t-accent)}
.vchat-delivery-bar .vd-box:focus{border-color:rgba(136,0,228,.6);box-shadow:0 0 0 2px rgba(136,0,228,.12)}
.vchat-delivery-bar .vd-box.filled{border-color:rgba(136,0,228,.4);background:rgba(136,0,228,.06)}
.vchat-delivery-bar .vd-btn{width:100%;padding:7px;border-radius:10px;background:linear-gradient(135deg,#8800E4,#7200C0);border:none;color:#fff;font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:4px}
.vchat-delivery-bar .vd-btn:hover{filter:brightness(1.1);transform:translateY(-1px);box-shadow:0 3px 12px rgba(136,0,228,.25)}
.vchat-delivery-bar .vd-btn:active{transform:translateY(0)}
.vchat-delivery-bar .vd-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}
.vchat-delivery-bar .vd-btn svg{width:13px;height:13px}
.vchat-delivery-bar .vd-msg{font-size:10px;margin-top:5px;padding:4px 8px;border-radius:6px;text-align:center}
.vchat-delivery-bar .vd-msg.ok{color:#8800E4;background:rgba(136,0,228,.1);border:1px solid rgba(136,0,228,.2)}
.vchat-delivery-bar .vd-msg.err{color:#ef4444;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)}

/* ── Delivery confirmed animation (vendor) ── */
.vchat-dlv-done{display:none;padding:12px;border-top:1px solid rgba(136,0,228,.15);background:linear-gradient(180deg,rgba(136,0,228,.06),rgba(0,0,0,.1));flex-shrink:0;text-align:center}
.vchat-dlv-done .vd-check{width:48px;height:48px;margin:0 auto 8px;border-radius:50%;background:linear-gradient(135deg,#8800E4,#6200AA);display:flex;align-items:center;justify-content:center;animation:vdCheckPop .5s cubic-bezier(.175,.885,.32,1.275)}
.vchat-dlv-done .vd-check svg{width:24px;height:24px;color:#fff;stroke-dasharray:40;stroke-dashoffset:40;animation:vdCheckDraw .6s .3s ease-out forwards}
.vchat-dlv-done .vd-done-title{font-size:13px;font-weight:700;color:#8800E4;margin-bottom:2px}
.vchat-dlv-done .vd-done-sub{font-size:10px;color:var(--t-text-muted,#666)}
@keyframes vdCheckPop{0%{transform:scale(0);opacity:0}60%{transform:scale(1.15)}100%{transform:scale(1);opacity:1}}
@keyframes vdCheckDraw{to{stroke-dashoffset:0}}

/* Mobile full screen */
@media (max-width: 480px) {
    .vchat-panel {
        bottom: 0;
        right: 0;
        width: 100vw;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
        max-width: 100vw;
    }
    .vchat-fab {
        bottom: calc(20px + env(safe-area-inset-bottom, 0px));
        right: 20px;
    }
}
</style>

<!-- Vendor Chat FAB -->
<button class="vchat-fab" id="vchatFab" title="Chat com compradores">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
    </svg>
    <span class="vchat-fab-badge" id="vchatFabBadge">0</span>
</button>

<!-- Vendor Chat Panel -->
<div class="vchat-panel" id="vchatPanel">
    <!-- Header -->
    <div class="vchat-panel-header">
        <button class="vchat-back-btn" id="vchatBackBtn" title="Voltar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <div class="vchat-avatar" id="vchatHeaderAvatar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        </div>
        <div class="vchat-info">
            <div class="vchat-name" id="vchatHeaderName">Conversas</div>
            <div class="vchat-status" id="vchatHeaderStatus">Online</div>
        </div>
        <button class="vchat-panel-close" id="vchatPanelClose" title="Fechar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Conversations list -->
    <div class="vchat-conv-list" id="vchatConvList">
        <div class="vchat-empty" id="vchatEmptyConv">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <p>Nenhuma conversa ainda.<br>As mensagens dos compradores aparecerão aqui.</p>
        </div>
    </div>

    <!-- Messages area (hidden by default) -->
    <div class="vchat-messages" id="vchatMessages"></div>

    <!-- Delivery code confirmation bar (hidden by default, shown in thread) -->
    <div class="vchat-delivery-bar" id="vchatDeliveryBar">
        <div class="vd-hd">
            <div>
                <div class="vd-title">🔑 Confirmar entrega</div>
                <div class="vd-sub">Código de 6 dígitos do comprador</div>
            </div>
        </div>
        <div class="vd-boxes" id="vchatCodeBoxes">
            <input type="text" maxlength="1" class="vd-box" data-idx="0" autocomplete="off" spellcheck="false" inputmode="text">
            <input type="text" maxlength="1" class="vd-box" data-idx="1" autocomplete="off" spellcheck="false" inputmode="text">
            <input type="text" maxlength="1" class="vd-box" data-idx="2" autocomplete="off" spellcheck="false" inputmode="text">
            <input type="text" maxlength="1" class="vd-box" data-idx="3" autocomplete="off" spellcheck="false" inputmode="text">
            <input type="text" maxlength="1" class="vd-box" data-idx="4" autocomplete="off" spellcheck="false" inputmode="text">
            <input type="text" maxlength="1" class="vd-box" data-idx="5" autocomplete="off" spellcheck="false" inputmode="text">
        </div>
        <button class="vd-btn" id="vchatDeliveryBtn" onclick="vchatConfirmDelivery()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            Verificar código e liberar pagamento
        </button>
        <div class="vd-msg" id="vchatDeliveryMsg" style="display:none"></div>
    </div>

    <!-- Delivery confirmed animation (vendor) -->
    <div class="vchat-dlv-done" id="vchatDlvDone">
        <div class="vd-check">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="vd-done-title">Pedido concluído!</div>
        <div class="vd-done-sub">Entrega confirmada com sucesso</div>
    </div>

    <!-- Input bar (hidden by default) -->
    <div class="vchat-input-bar" id="vchatInputBar">
        <textarea id="vchatInput" rows="1" placeholder="Digite uma mensagem..." maxlength="2000"></textarea>
        <button class="vchat-send-btn" id="vchatSendBtn" title="Enviar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
        </button>
    </div>
</div>

<!-- Vendor Chat JS -->
<script>
(function(){
    const CHAT_API = '<?= BASE_PATH ?>/api/chat';
    const USER_ID  = <?= $chatWidgetUserId ?>;

    const fab      = document.getElementById('vchatFab');
    const panel    = document.getElementById('vchatPanel');
    const closeBtn = document.getElementById('vchatPanelClose');
    const backBtn  = document.getElementById('vchatBackBtn');
    const convList = document.getElementById('vchatConvList');
    const emptyConv = document.getElementById('vchatEmptyConv');
    const msgArea  = document.getElementById('vchatMessages');
    const inputBar = document.getElementById('vchatInputBar');
    const input    = document.getElementById('vchatInput');
    const sendBtn  = document.getElementById('vchatSendBtn');
    const badge    = document.getElementById('vchatFabBadge');
    const headerName = document.getElementById('vchatHeaderName');
    const headerAvatar = document.getElementById('vchatHeaderAvatar');
    const deliveryBar = document.getElementById('vchatDeliveryBar');
    const codeBoxes = document.querySelectorAll('#vchatCodeBoxes .vd-box');
    const deliveryBtn = document.getElementById('vchatDeliveryBtn');
    const deliveryMsg = document.getElementById('vchatDeliveryMsg');
    const dlvDone     = document.getElementById('vchatDlvDone');

    let isOpen     = false;
    let currentView = 'list'; // 'list' or 'thread'
    let currentConvId = 0;
    let lastMsgId  = 0;
    let pollTimer  = null;
    let sending    = false;

    // ── Toggle panel ──
    fab.addEventListener('click', () => {
        isOpen = !isOpen;
        panel.classList.toggle('open', isOpen);
        if (isOpen) {
            showConvList();
        } else {
            stopPolling();
        }
    });

    closeBtn.addEventListener('click', () => {
        isOpen = false;
        panel.classList.remove('open');
        stopPolling();
    });

    backBtn.addEventListener('click', () => {
        showConvList();
    });

    // ── Show conversation list ──
    function showConvList() {
        currentView = 'list';
        currentConvId = 0;
        lastMsgId = 0;
        stopPolling();

        headerName.textContent = 'Conversas';
        if (headerStatus) headerStatus.textContent = '';
        backBtn.style.display = 'none';
        // Reset header avatar to default chat icon
        if (headerAvatar) headerAvatar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>';
        convList.style.display = '';
        msgArea.style.display = 'none';
        inputBar.style.display = 'none';
        deliveryBar.style.display = 'none';
        deliveryMsg.style.display = 'none';
        dlvDone.style.display = 'none';
        codeBoxes.forEach(b => { b.value = ''; b.classList.remove('filled'); });

        loadConversations();
    }

    // ── Format last-seen timestamp ──
    function formatLastSeen(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        const now = new Date();
        const diffMs = now - d;
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 5) return 'Online';
        if (diffMin < 60) return 'visto há ' + diffMin + ' min';
        const diffH = Math.floor(diffMin / 60);
        if (diffH < 24) return 'visto há ' + diffH + 'h';
        if (diffH < 48) return 'visto ontem';
        return 'visto ' + d.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'});
    }

    const headerStatus = document.getElementById('vchatHeaderStatus');

    // ── Show thread ──
    function showThread(convId, name, avatar, productName, lastSeen) {
        currentView = 'thread';
        currentConvId = convId;
        lastMsgId = 0;

        // Show buyer name + product name in header
        headerName.textContent = name + (productName ? ' \u00b7 \ud83d\udce6 ' + productName : '');
        // Show buyer last-seen status
        if (headerStatus) headerStatus.textContent = formatLastSeen(lastSeen) || '';
        // Show buyer avatar in header
        if (headerAvatar) {
            headerAvatar.innerHTML = avatar
                ? '<img src="' + escHtml(avatar) + '" alt="">'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>';
        }
        backBtn.style.display = 'flex';
        convList.style.display = 'none';
        msgArea.style.display = 'flex';
        inputBar.style.display = 'flex';
        deliveryBar.style.display = 'block';
        deliveryMsg.style.display = 'none';
        dlvDone.style.display = 'none';
        codeBoxes.forEach(b => { b.value = ''; b.classList.remove('filled'); });
        msgArea.innerHTML = '';

        loadMessages();
        startPolling();
        if (input) input.focus();
        // Check if delivery already confirmed for this conversation
        fetchDeliveryStatus(convId);
    }

    // ── Fetch delivery status (vendor side) ──
    async function fetchDeliveryStatus(convId) {
        try {
            const r = await fetch(CHAT_API + '?action=get_delivery_status&conversation_id=' + convId);
            const j = await r.json();
            if (j.ok && j.delivered) {
                deliveryBar.style.display = 'none';
                dlvDone.style.display = 'block';
            }
        } catch(e) {}
    }

    // ── Load conversations ──
    async function loadConversations() {
        try {
            const r = await fetch(CHAT_API + '?action=conversations');
            const j = await r.json();
            if (!j.ok) return;

            // Clear old items but keep empty state
            convList.querySelectorAll('.vchat-conv-item').forEach(el => el.remove());

            if (j.conversations.length === 0) {
                emptyConv.style.display = '';
                return;
            }

            emptyConv.style.display = 'none';

            j.conversations.forEach(c => {
                const item = document.createElement('div');
                item.className = 'vchat-conv-item';
                item.onclick = () => showThread(c.id, c.other_name || 'Comprador', c.other_avatar || '', c.product_name || '', c.last_seen_at || '');

                const initials = (c.other_name || '?').charAt(0).toUpperCase();

                item.innerHTML = `
                    <div class="vconv-avatar">
                        ${c.other_avatar
                            ? '<img src="' + escHtml(c.other_avatar) + '" alt="">'
                            : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>'}
                    </div>
                    <div class="vconv-info">
                        <div class="vconv-name">${escHtml(c.other_name || 'Comprador')}</div>
                        <div class="vconv-preview">${c.product_name ? '📦 ' + escHtml(c.product_name) + ' · ' : ''}${escHtml(c.last_message || 'Sem mensagens')}</div>
                    </div>
                    <div class="vconv-meta">
                        <span class="vconv-time">${formatRelative(c.last_msg_time)}</span>
                        ${c.unread_count > 0 ? '<span class="vconv-unread">' + c.unread_count + '</span>' : ''}
                    </div>
                `;
                convList.appendChild(item);
            });
        } catch(e) { console.error('Vendor chat load convs error', e); }
    }

    // ── Load messages ──
    async function loadMessages() {
        if (!currentConvId) return;
        try {
            const r = await fetch(CHAT_API + '?action=messages&conversation_id=' + currentConvId);
            const j = await r.json();
            if (!j.ok) return;
            renderMessages(j.messages);
        } catch(e) { console.error('Vendor chat load msgs error', e); }
    }

    function renderMessages(msgs) {
        msgArea.innerHTML = '';
        if (!msgs.length) {
            msgArea.innerHTML = '<div class="vchat-empty"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg><p>Envie uma mensagem para iniciar a conversa</p></div>';
            return;
        }
        let lastDate = '';
        msgs.forEach(m => {
            const d = formatDate(m.criado_em);
            if (d !== lastDate) {
                lastDate = d;
                const dateEl = document.createElement('div');
                dateEl.className = 'vchat-msg-date';
                dateEl.textContent = d;
                msgArea.appendChild(dateEl);
            }
            appendMessage(m);
        });
        scrollToBottom();
    }

    function appendMessage(m) {
        const txt = m.message || '';
        const sysMatch = txt.match(/^\[(INSTRUCOES_VENDA|ENTREGA_AUTO|CODIGO_ENTREGA|SISTEMA)\]\n/);
        const el = document.createElement('div');
        el.dataset.msgId = m.id;

        if (sysMatch) {
            const tMap = {'INSTRUCOES_VENDA':'inst','ENTREGA_AUTO':'dlvr','CODIGO_ENTREGA':'code','SISTEMA':'sys'};
            const st = tMap[sysMatch[1]] || 'sys';
            const body = txt.substring(sysMatch[0].length);
            el.className = 'vchat-msg theirs sys-msg t-' + st;

            const hd = document.createElement('div'); hd.className = 'sys-hd';
            const icons = {'inst':'📋 Instruções','dlvr':'📦 Produto entregue','code':'🔑 Código de entrega','sys':'ℹ️ Sistema'};
            hd.textContent = icons[st] || 'Sistema'; el.appendChild(hd);

            if (st === 'dlvr') {
                const bx = body.match(/━+\n([\s\S]*?)\n━+/);
                if (bx) {
                    const before = body.substring(0, body.indexOf('━')).trim();
                    const boxTxt = bx[1].trim();
                    const after = body.substring(body.lastIndexOf('━') + 1).trim();
                    if (before) { const bt = document.createElement('div'); bt.className = 'sys-ct'; bt.textContent = before; el.appendChild(bt); }
                    const box = document.createElement('div'); box.className = 'sys-bx';
                    box.innerHTML = '<button class="sys-cp" onclick="navigator.clipboard.writeText(this.nextElementSibling.textContent.trim());this.textContent=\'✓\';setTimeout(()=>this.textContent=\'Copiar\',2000)">Copiar</button><span>' + escHtml(boxTxt) + '</span>';
                    el.appendChild(box);
                    if (after) { const at = document.createElement('div'); at.className = 'sys-ct'; at.textContent = after; el.appendChild(at); }
                } else {
                    const ct = document.createElement('div'); ct.className = 'sys-ct'; ct.textContent = body; el.appendChild(ct);
                }
            } else {
                const ct = document.createElement('div'); ct.className = 'sys-ct'; ct.textContent = body; el.appendChild(ct);
            }
        } else {
            el.className = 'vchat-msg ' + (m.is_mine ? 'mine' : 'theirs');
            const text = document.createElement('div');
            text.textContent = txt;
            el.appendChild(text);
        }

        const time = document.createElement('div');
        time.className = 'vmsg-time';
        time.textContent = formatTime(m.criado_em);
        el.appendChild(time);

        msgArea.appendChild(el);
        if (m.id > lastMsgId) lastMsgId = m.id;
        // If this is a delivery confirmed system message, hide code bar and show check
        if (sysMatch && txt.indexOf('Entrega confirmada') >= 0) {
            deliveryBar.style.display = 'none';
            dlvDone.style.display = 'block';
        }
    }

    // ── Send message ──
    async function send() {
        if (sending || !input || !currentConvId) return;
        const text = input.value.trim();
        if (!text) return;

        sending = true;
        sendBtn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('conversation_id', currentConvId);
            fd.append('message', text);
            const r = await fetch(CHAT_API + '?action=send', { method: 'POST', body: fd });
            const j = await r.json();
            if (j.ok && j.msg) {
                // Remove empty state if present
                msgArea.querySelector('.vchat-empty')?.remove();
                appendMessage(j.msg);
                scrollToBottom();
                input.value = '';
                autoResize();
            }
        } catch(e) { console.error('Vendor chat send error', e); }

        sending = false;
        sendBtn.disabled = false;
        input.focus();
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });
    input.addEventListener('input', autoResize);

    function autoResize() {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 80) + 'px';
    }

    // ── Polling ──
    function startPolling() {
        stopPolling();
        pollTimer = setInterval(poll, 3000);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    async function poll() {
        if (!currentConvId || !isOpen || currentView !== 'thread') return;
        try {
            const r = await fetch(CHAT_API + '?action=poll&conversation_id=' + currentConvId + '&after_id=' + lastMsgId);
            const j = await r.json();
            if (j.ok && j.messages.length > 0) {
                msgArea.querySelector('.vchat-empty')?.remove();
                j.messages.forEach(m => appendMessage(m));
                scrollToBottom();
            }
        } catch(e) {}
    }

    // ── Unread badge polling ──
    async function pollUnread() {
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
    pollUnread();
    setInterval(pollUnread, 15000);

    // ── Helpers ──
    function scrollToBottom() {
        requestAnimationFrame(() => { msgArea.scrollTop = msgArea.scrollHeight; });
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
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

    function formatRelative(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        const now = new Date();
        const diff = Math.floor((now - d) / 1000);
        if (diff < 60) return 'agora';
        if (diff < 3600) return Math.floor(diff / 60) + ' min';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 172800) return 'ontem';
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    }

    // Offset FAB & panel when buyer chat widget is also on the page
    if (document.getElementById('chatFab')) {
        fab.style.bottom = '96px';
        panel.style.bottom = '164px';
    }

    // ── Delivery code 6-box auto-advance logic ──
    codeBoxes.forEach((box, i) => {
        box.addEventListener('input', (e) => {
            const v = e.target.value.toUpperCase();
            e.target.value = v;
            e.target.classList.toggle('filled', v.length > 0);
            if (v.length === 1 && i < 5) codeBoxes[i + 1].focus();
            // Handle paste of full code
            if (v.length > 1) {
                const chars = v.split('');
                chars.forEach((c, j) => {
                    if (i + j < 6) {
                        codeBoxes[i + j].value = c;
                        codeBoxes[i + j].classList.toggle('filled', c.length > 0);
                    }
                });
                const next = Math.min(i + chars.length, 5);
                codeBoxes[next].focus();
                e.target.value = v[0]; // Keep only first char in this box
            }
        });
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !box.value && i > 0) {
                codeBoxes[i - 1].focus();
                codeBoxes[i - 1].value = '';
                codeBoxes[i - 1].classList.remove('filled');
            }
            if (e.key === 'Enter') { e.preventDefault(); window.vchatConfirmDelivery(); }
        });
        // Handle paste event
        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData.getData('text') || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            for (let j = 0; j < 6; j++) {
                codeBoxes[j].value = pasted[j] || '';
                codeBoxes[j].classList.toggle('filled', !!pasted[j]);
            }
            codeBoxes[Math.min(pasted.length, 5)].focus();
        });
    });

    function getDeliveryCode() {
        return Array.from(codeBoxes).map(b => b.value.toUpperCase()).join('');
    }

    // ── Delivery code confirmation ──
    window.vchatConfirmDelivery = async function() {
        const code = getDeliveryCode();
        if (code.length !== 6) {
            deliveryMsg.textContent = 'O código deve ter 6 caracteres.';
            deliveryMsg.className = 'vd-msg err';
            deliveryMsg.style.display = '';
            return;
        }
        if (!currentConvId) return;
        deliveryBtn.disabled = true;
        deliveryMsg.style.display = 'none';
        try {
            const fd = new FormData();
            fd.append('conversation_id', currentConvId);
            fd.append('delivery_code', code);
            const r = await fetch(CHAT_API + '?action=confirm_delivery', { method: 'POST', body: fd });
            const j = await r.json();
            deliveryMsg.textContent = j.msg || (j.ok ? 'Entrega confirmada!' : 'Erro');
            deliveryMsg.className = 'vd-msg ' + (j.ok ? 'ok' : 'err');
            deliveryMsg.style.display = '';
            if (j.ok) {
                codeBoxes.forEach(b => { b.value = ''; b.classList.remove('filled'); });
                // Hide code bar, show completion animation
                deliveryBar.style.display = 'none';
                dlvDone.style.display = 'block';
                // Reload messages to show system confirmation
                setTimeout(() => { loadMessages(); }, 1000);
            }
        } catch(e) {
            deliveryMsg.textContent = 'Erro de conexão.';
            deliveryMsg.className = 'vd-msg err';
            deliveryMsg.style.display = '';
        }
        deliveryBtn.disabled = false;
    };

    // ── Global API: open vendor chat panel on specific conversation ──
    window.openVendorChat = function(convId) {
        if (!convId) return;
        isOpen = true;
        panel.classList.add('open');
        fetch(CHAT_API + '?action=conversations').then(r => r.json()).then(j => {
            if (!j.ok) return;
            const found = (j.conversations || []).find(c => c.id == convId);
            if (found) {
                showThread(found.id, found.other_name || 'Comprador', found.other_avatar || '', found.product_name || '', found.last_seen_at || '');
            } else {
                showThread(convId, 'Comprador', '', '', '');
            }
        }).catch(() => showThread(convId, 'Comprador', '', '', ''));
    };

    // ── Auto-open from URL param + drain queued requests ──
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const openChatParam = urlParams.get('open_chat');
        if (openChatParam) requestAnimationFrame(() => window.openVendorChat(parseInt(openChatParam)));
        if (Array.isArray(window.__openChatQueue)) {
            window.__openChatQueue.forEach(id => window.openVendorChat(parseInt(id)));
            window.__openChatQueue = [];
        }
    } catch(_) {}
})();
</script>
