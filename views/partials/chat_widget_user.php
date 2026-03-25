<?php
/**
 * User Floating Chat Widget — Shopee-style inline panel.
 * Shows a floating chat button that opens an inline panel with conversation list + messages.
 * Include in user panel pages via unified_layout_end.php.
 *
 * Exposes: window.openUserChat(convId) to open the panel on a specific conversation.
 */
$chatUserWidgetId = (int)($_SESSION['user_id'] ?? 0);
if ($chatUserWidgetId <= 0) return;

$_uchatUnread = 0;
try {
    require_once __DIR__ . '/../../src/chat.php';
    chatEnsureTables((new Database())->connect());
    $_uchatUnread = chatUnreadCount((new Database())->connect(), $chatUserWidgetId);
} catch (\Throwable $e) {}
?>

<!-- User Floating Chat Widget -->
<style>
/* ========== USER CHAT FAB ========== */
.uchat-fab{position:fixed;bottom:28px;right:28px;z-index:1000;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--t-accent) 0%,var(--t-accent-hover) 100%);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(var(--t-accent-rgb),.35),0 2px 8px rgba(0,0,0,.2);transition:all .3s cubic-bezier(.4,0,.2,1);animation:uchatPulse 3s infinite}
.uchat-fab:hover{transform:scale(1.08) translateY(-2px);box-shadow:0 8px 32px rgba(var(--t-accent-rgb),.45),0 4px 12px rgba(0,0,0,.25)}
.uchat-fab:active{transform:scale(.95)}
.uchat-fab svg{width:26px;height:26px;color:#000}
.uchat-fab-badge{position:absolute;top:-2px;right:-2px;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#ef4444;color:#fff;font-size:11px;font-weight:700;display:none;align-items:center;justify-content:center;line-height:1;border:2px solid var(--t-bg-body);animation:uchatBadgeBounce .4s ease-out}
.uchat-fab-badge.show{display:flex}
@keyframes uchatPulse{0%,100%{box-shadow:0 4px 20px rgba(var(--t-accent-rgb),.35)}50%{box-shadow:0 4px 28px rgba(var(--t-accent-rgb),.50),0 0 0 8px rgba(var(--t-accent-rgb),.08)}}
@keyframes uchatBadgeBounce{0%{transform:scale(0)}60%{transform:scale(1.2)}100%{transform:scale(1)}}

/* ========== USER CHAT PANEL ========== */
.uchat-panel{position:fixed;bottom:96px;right:28px;z-index:1001;width:380px;max-width:calc(100vw - 32px);height:520px;max-height:calc(100vh - 140px);background:var(--t-bg-card,#0f0f0f);border-radius:20px;border:1px solid rgba(255,255,255,.08);box-shadow:0 20px 60px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.04);display:flex;flex-direction:column;overflow:hidden;opacity:0;visibility:hidden;transform:translateY(16px) scale(.96);transition:all .3s cubic-bezier(.4,0,.2,1)}
.uchat-panel.open{opacity:1;visibility:visible;transform:translateY(0) scale(1)}

.uchat-hdr{display:flex;align-items:center;gap:12px;padding:16px;background:linear-gradient(180deg,rgba(var(--t-accent-rgb),.08) 0%,transparent 100%);border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0}
.uchat-hdr .uc-av{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--t-accent),var(--t-accent-hover));display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.uchat-hdr .uc-av svg{width:20px;height:20px;color:#000}
.uchat-hdr .uc-av img{width:100%;height:100%;object-fit:cover;border-radius:12px}
.uchat-hdr .uc-nfo{flex:1;min-width:0}
.uchat-hdr .uc-nm{font-size:15px;font-weight:700;color:var(--t-text-primary,#fff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.uchat-hdr .uc-st{font-size:12px;color:var(--t-accent);display:flex;align-items:center;gap:4px}
.uchat-hdr .uc-st::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--t-accent);display:inline-block}
.uchat-close,.uchat-back{width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t-text-muted,#888);transition:all .2s;flex-shrink:0}
.uchat-close:hover,.uchat-back:hover{background:rgba(255,255,255,.1);color:var(--t-text-primary,#fff)}
.uchat-close svg,.uchat-back svg{width:16px;height:16px}
.uchat-back{display:none}

/* ========== CONV LIST ========== */
.uchat-list{flex:1;overflow-y:auto;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.1) transparent}
.uchat-ci{display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;transition:background .15s;border-bottom:1px solid rgba(255,255,255,.03);text-decoration:none;color:inherit}
.uchat-ci:hover{background:rgba(255,255,255,.04)}
.uchat-ci .ci-av{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.uchat-ci .ci-av img{width:100%;height:100%;object-fit:cover}
.uchat-ci .ci-av svg{width:20px;height:20px;color:var(--t-text-muted,#888)}
.uchat-ci .ci-nfo{flex:1;min-width:0}
.uchat-ci .ci-nm{font-size:14px;font-weight:600;color:var(--t-text-primary,#e5e5e5);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.uchat-ci .ci-pv{font-size:12px;color:var(--t-text-muted,#666);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.uchat-ci .ci-mt{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.uchat-ci .ci-tm{font-size:11px;color:var(--t-text-muted,#555)}
.uchat-ci .ci-ur{min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--t-accent);color:var(--t-text-on-accent,#000);font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}

/* ========== MESSAGES ========== */
.uchat-msgs{flex:1;overflow-y:auto;padding:16px;display:none;flex-direction:column;gap:6px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.1) transparent}
.uchat-msgs::-webkit-scrollbar{width:4px}
.uchat-msgs::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px}

.uchat-m{max-width:82%;padding:10px 14px;border-radius:16px;font-size:13px;line-height:1.5;word-wrap:break-word;animation:ucMsgIn .25s ease-out}
.uchat-m.mine{align-self:flex-end;background:linear-gradient(135deg,var(--t-accent),var(--t-accent-hover));color:var(--t-text-on-accent,#000);border-bottom-right-radius:6px}
.uchat-m.theirs{align-self:flex-start;background:rgba(255,255,255,.06);color:var(--t-text-primary,#e5e5e5);border-bottom-left-radius:6px;border:1px solid rgba(255,255,255,.06)}
.uchat-m .um-t{font-size:10px;margin-top:4px;opacity:.6}
.uchat-m.mine .um-t{text-align:right;color:rgba(0,0,0,.5)}
.uchat-m.theirs .um-t{color:var(--t-text-muted,#555)}
.uchat-md{text-align:center;font-size:11px;color:var(--t-text-muted,#555);padding:8px 0;font-weight:500}
@keyframes ucMsgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ── System messages ── */
.uchat-m.sys{max-width:92%;align-self:flex-start;background:linear-gradient(135deg,rgba(136,0,228,.08),rgba(136,0,228,.03))!important;border:1px solid rgba(136,0,228,.2)!important;border-radius:14px!important;padding:12px 14px!important;color:var(--t-text-primary,#e5e5e5)!important}
.uchat-m.sys .s-hd{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.06)}
.uchat-m.sys.t-inst .s-hd{color:#8800E4}
.uchat-m.sys.t-dlvr .s-hd{color:#8800E4}
.uchat-m.sys.t-dlvr{background:linear-gradient(135deg,rgba(136,0,228,.08),rgba(136,0,228,.03))!important;border-color:rgba(136,0,228,.2)!important}
.uchat-m.sys .s-ct{white-space:pre-wrap;font-size:12px;line-height:1.55}
.uchat-m.sys .s-bx{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:10px;margin:6px 0;font-family:'Courier New',monospace;font-size:11px;color:#d1d5db;white-space:pre-wrap;word-break:break-all;position:relative}
.uchat-m.sys .s-cp{position:absolute;top:4px;right:4px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:5px;padding:3px 6px;font-size:9px;color:#a3a3a3;cursor:pointer;transition:all .2s}
.uchat-m.sys .s-cp:hover{background:rgba(255,255,255,.15);color:#fff}

/* ========== INPUT BAR ========== */
.uchat-bar{display:none;align-items:flex-end;gap:8px;padding:12px 16px;border-top:1px solid rgba(255,255,255,.06);background:rgba(0,0,0,.3);flex-shrink:0}
.uchat-bar textarea{flex:1;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:10px 14px;color:var(--t-text-primary,#fff);font-size:13px;line-height:1.4;resize:none;outline:none;max-height:80px;min-height:40px;font-family:inherit;transition:border-color .2s}
.uchat-bar textarea:focus{border-color:rgba(var(--t-accent-rgb),.4)}
.uchat-bar textarea::placeholder{color:var(--t-text-muted,#555)}
.uchat-snd{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--t-accent),var(--t-accent-hover));border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;flex-shrink:0}
.uchat-snd:hover{filter:brightness(1.1);transform:scale(1.05)}
.uchat-snd:active{transform:scale(.95)}
.uchat-snd:disabled{opacity:.4;cursor:not-allowed;transform:none}
.uchat-snd svg{width:18px;height:18px;color:#000}

/* ========== BUYER DELIVERY CODE BAR (matches vendor design) ========== */
.uchat-dlv{display:none;padding:8px 12px;border-top:1px solid rgba(255,255,255,.06);background:linear-gradient(180deg,rgba(136,0,228,.04),rgba(0,0,0,.15));flex-shrink:0}
.uchat-dlv .ud-hd{display:flex;align-items:center;gap:6px;margin-bottom:6px}
.uchat-dlv .ud-icon{display:none}
.uchat-dlv .ud-title{font-size:11px;font-weight:700;color:var(--t-text-primary,#fff)}
.uchat-dlv .ud-sub{font-size:9px;color:var(--t-accent);margin-top:0}
.uchat-dlv .ud-boxes{display:flex;gap:4px;justify-content:center;margin-bottom:6px}
.uchat-dlv .ud-box{width:30px;height:34px;text-align:center;font-size:14px;font-family:'Courier New',monospace;font-weight:700;background:rgba(136,0,228,.06);border:1.5px solid rgba(136,0,228,.4);border-radius:8px;color:#A855F7;text-transform:uppercase;display:flex;align-items:center;justify-content:center}
.uchat-dlv .ud-box.filled{border-color:rgba(136,0,228,.6);background:rgba(136,0,228,.10)}
.uchat-dlv .ud-btn{width:100%;padding:7px;border-radius:10px;border:none;font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:4px}
.uchat-dlv .ud-btn.step1{background:linear-gradient(135deg,#8800E4,#7200C0);color:#fff}
.uchat-dlv .ud-btn.step1:hover{filter:brightness(1.1);transform:translateY(-1px);box-shadow:0 3px 12px rgba(136,0,228,.25)}
.uchat-dlv .ud-btn.step1:active{transform:translateY(0)}
.uchat-dlv .ud-btn.step1:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}
.uchat-dlv .ud-btn.step1 svg{width:13px;height:13px}
.uchat-dlv .ud-btn.step2{background:linear-gradient(135deg,#f59e0b,#d97706);color:#000;animation:ucDlvPulse 1.5s infinite}
.uchat-dlv .ud-btn.step2:hover{filter:brightness(1.1)}
.uchat-dlv .ud-msg{font-size:10px;margin-top:5px;padding:4px 8px;border-radius:6px;text-align:center}
.uchat-dlv .ud-msg.ok{color:#8800E4;background:rgba(136,0,228,.1);border:1px solid rgba(136,0,228,.2)}
/* ── Delivery confirmed animation ── */
.uchat-dlv-done{display:none;padding:12px;border-top:1px solid rgba(136,0,228,.15);background:linear-gradient(180deg,rgba(136,0,228,.06),rgba(0,0,0,.1));flex-shrink:0;text-align:center}
.uchat-dlv-done .ud-check{width:48px;height:48px;margin:0 auto 8px;border-radius:50%;background:linear-gradient(135deg,#8800E4,#6200AA);display:flex;align-items:center;justify-content:center;animation:udCheckPop .5s cubic-bezier(.175,.885,.32,1.275)}
.uchat-dlv-done .ud-check svg{width:24px;height:24px;color:#fff;stroke-dasharray:40;stroke-dashoffset:40;animation:udCheckDraw .6s .3s ease-out forwards}
.uchat-dlv-done .ud-done-title{font-size:13px;font-weight:700;color:#8800E4;margin-bottom:2px}
.uchat-dlv-done .ud-done-sub{font-size:10px;color:var(--t-text-muted,#666)}
@keyframes ucDlvPulse{0%,100%{opacity:1}50%{opacity:.8}}
@keyframes udCheckPop{0%{transform:scale(0);opacity:0}60%{transform:scale(1.15)}100%{transform:scale(1);opacity:1}}
@keyframes udCheckDraw{to{stroke-dashoffset:0}}

.uchat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;text-align:center}
.uchat-empty svg{width:48px;height:48px;color:#333;margin-bottom:12px}
.uchat-empty p{font-size:13px;color:var(--t-text-muted,#666);line-height:1.5}

@media(max-width:480px){
.uchat-panel{bottom:0;right:0;width:100vw;height:100vh;max-height:100vh;border-radius:0;max-width:100vw}
.uchat-fab{bottom:calc(20px + env(safe-area-inset-bottom,0px));right:20px}
}
</style>

<!-- User Chat FAB -->
<button class="uchat-fab" id="uchatFab" title="Chat">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
    </svg>
    <span class="uchat-fab-badge <?= $_uchatUnread > 0 ? 'show' : '' ?>" id="uchatBadge"><?= $_uchatUnread ?></span>
</button>

<!-- User Chat Panel -->
<div class="uchat-panel" id="uchatPanel">
    <div class="uchat-hdr">
        <button class="uchat-back" id="uchatBack" title="Voltar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="uc-av" id="uchatHdAv">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </div>
        <div class="uc-nfo">
            <div class="uc-nm" id="uchatHdNm">Conversas</div>
            <div class="uc-st" id="uchatHdSt" style="display:none"></div>
        </div>
        <button class="uchat-close" id="uchatClose" title="Fechar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="uchat-list" id="uchatList">
        <div class="uchat-empty" id="uchatNoConv">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <p>Nenhuma conversa ainda.<br>Após realizar uma compra, o chat aparecerá aqui.</p>
        </div>
    </div>
    <div class="uchat-msgs" id="uchatMsgs"></div>
    <!-- Buyer delivery code 6-box (pre-filled, read-only, 2-click send) -->
    <div class="uchat-dlv" id="uchatDlv">
        <div class="ud-hd">
            <div>
                <div class="ud-title">🔑 Código de entrega</div>
                <div class="ud-sub">Envie este código ao vendedor para liberar o pagamento</div>
            </div>
        </div>
        <div class="ud-boxes" id="uchatDlvBoxes">
            <span class="ud-box" data-idx="0">-</span>
            <span class="ud-box" data-idx="1">-</span>
            <span class="ud-box" data-idx="2">-</span>
            <span class="ud-box" data-idx="3">-</span>
            <span class="ud-box" data-idx="4">-</span>
            <span class="ud-box" data-idx="5">-</span>
        </div>
        <button class="ud-btn step1" id="uchatDlvBtn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            Enviar código ao vendedor
        </button>
        <div class="ud-msg ok" id="uchatDlvMsg" style="display:none"></div>
    </div>
    <!-- Delivery confirmed animation -->
    <div class="uchat-dlv-done" id="uchatDlvDone">
        <div class="ud-check">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="ud-done-title">Pedido concluído!</div>
        <div class="ud-done-sub">Entrega confirmada com sucesso</div>
    </div>
    <div class="uchat-bar" id="uchatBar">
        <textarea id="uchatIn" rows="1" placeholder="Digite uma mensagem..." maxlength="2000"></textarea>
        <button class="uchat-snd" id="uchatSnd" title="Enviar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
        </button>
    </div>
</div>

<script>
(function(){
    const API = '<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/api/chat';
    const UID = <?= $chatUserWidgetId ?>;
    const $ = id => document.getElementById(id);
    const fab=$('uchatFab'), panel=$('uchatPanel'), closeB=$('uchatClose'), backB=$('uchatBack');
    const list=$('uchatList'), noConv=$('uchatNoConv'), msgs=$('uchatMsgs'), bar=$('uchatBar');
    const inp=$('uchatIn'), sndB=$('uchatSnd'), badge=$('uchatBadge');
    const hdNm=$('uchatHdNm'), hdAv=$('uchatHdAv'), hdSt=$('uchatHdSt');
    const dlvStrip=$('uchatDlv'), dlvBtn=$('uchatDlvBtn'), dlvDone=$('uchatDlvDone'), dlvMsg=$('uchatDlvMsg');
    const dlvBoxes=document.querySelectorAll('#uchatDlvBoxes .ud-box');
    const PERSON='<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>';
    const CHAT_ICON='<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>';

    let isOpen=false, view='list', curConv=0, lastId=0, pollTmr=null, sending=false;
    let dlvStep=0, dlvCurrentCode='';

    /* ── Toggle ── */
    fab.onclick = ()=>{ isOpen=!isOpen; panel.classList.toggle('open',isOpen); if(isOpen) showList(); else stopPoll(); };
    closeB.onclick = ()=>{ isOpen=false; panel.classList.remove('open'); stopPoll(); };
    backB.onclick = ()=> showList();

    /* ── List view ── */
    function showList(){
        view='list'; curConv=0; lastId=0; stopPoll();
        hdNm.textContent='Conversas'; hdSt.style.display='none'; backB.style.display='none';
        hdAv.innerHTML=CHAT_ICON;
        list.style.display=''; msgs.style.display='none'; bar.style.display='none';
        dlvStrip.style.display='none'; dlvDone.style.display='none'; dlvStep=0; dlvCurrentCode='';
        loadConvs();
    }

    /* ── Thread view ── */
    function showThread(cid, name, avatar, product, lastSeen){
        view='thread'; curConv=cid; lastId=0;
        hdNm.textContent = name + (product ? ' · 📦 '+product : '');
        hdSt.style.display=''; hdSt.textContent=fmtSeen(lastSeen)||'';
        hdAv.innerHTML = avatar ? '<img src="'+esc(avatar)+'" alt="">' : PERSON;
        backB.style.display='flex';
        list.style.display='none'; msgs.style.display='flex'; bar.style.display='flex';
        dlvStrip.style.display='none'; dlvDone.style.display='none'; dlvStep=0; dlvCurrentCode='';
        msgs.innerHTML=''; loadMsgs(); startPoll();
        if(inp) inp.focus();
        // Fetch buyer delivery code for this conversation
        fetchDeliveryCode(cid);
    }

    /* ── Buyer delivery code ── */
    async function fetchDeliveryCode(convId){
        try{
            const r=await fetch(API+'?action=get_buyer_code&conversation_id='+convId);
            const j=await r.json();
            if(!j.ok || !j.code) return;
            dlvCurrentCode=j.code;
            const chars=j.code.split('');
            dlvBoxes.forEach((box,i)=>{ box.textContent=chars[i]||'-'; box.classList.toggle('filled',!!chars[i]); });
            // Check if delivery already confirmed (order status)
            if(j.delivered){
                dlvStrip.style.display='none';
                dlvDone.style.display='block';
                return;
            }
            // Show code strip ready to send
            dlvStrip.style.display='block';
            dlvDone.style.display='none';
            dlvStep=0;
            dlvMsg.style.display='none';
        }catch(e){}
    }
    dlvBtn.onclick=function(){
        if(dlvStep===0){
            dlvStep=1;
            dlvBtn.innerHTML='⚠️ Confirmar envio?';
            dlvBtn.className='ud-btn step2';
        }else if(dlvStep===1){
            sendDeliveryCode();
        }
    };
    async function sendDeliveryCode(){
        if(!curConv||!dlvCurrentCode) return;
        dlvBtn.disabled=true;
        try{
            const fd=new FormData();
            fd.append('conversation_id',curConv);
            fd.append('message','🔑 Código de entrega: '+dlvCurrentCode);
            const r=await fetch(API+'?action=send',{method:'POST',body:fd});
            const j=await r.json();
            if(j.ok&&j.msg){
                msgs.querySelector('.uchat-empty')?.remove();
                appendMsg(j.msg); scrollEnd();
                // Hide code strip and show completion animation
                dlvStrip.style.display='none';
                dlvDone.style.display='block';
                dlvStep=2;
            }
        }catch(e){ dlvBtn.innerHTML='Erro, tente novamente'; dlvBtn.className='ud-btn step1'; dlvStep=0; }
        dlvBtn.disabled=false;
    }

    /* ── Load conversations ── */
    async function loadConvs(){
        try{
            const r=await fetch(API+'?action=conversations'); const j=await r.json();
            if(!j.ok) return;
            list.querySelectorAll('.uchat-ci').forEach(el=>el.remove());
            if(!j.conversations.length){ noConv.style.display=''; return; }
            noConv.style.display='none';
            j.conversations.forEach(c=>{
                const d=document.createElement('div'); d.className='uchat-ci';
                d.onclick=()=>showThread(c.id, c.other_name||'Vendedor', c.other_avatar||'', c.product_name||'', c.last_seen_at||'');
                d.innerHTML=
                    '<div class="ci-av">'+(c.other_avatar?'<img src="'+esc(c.other_avatar)+'" alt="">':PERSON)+'</div>'+
                    '<div class="ci-nfo"><div class="ci-nm">'+esc(c.other_name||'Vendedor')+'</div><div class="ci-pv">'+(c.product_name?'📦 '+esc(c.product_name)+' · ':'')+esc(c.last_message||'Sem mensagens')+'</div></div>'+
                    '<div class="ci-mt"><span class="ci-tm">'+fmtRel(c.last_msg_time)+'</span>'+(c.unread_count>0?'<span class="ci-ur">'+c.unread_count+'</span>':'')+'</div>';
                list.appendChild(d);
            });
        }catch(e){ console.error('uchat loadConvs',e); }
    }

    /* ── Load messages ── */
    async function loadMsgs(){
        if(!curConv) return;
        try{
            const r=await fetch(API+'?action=messages&conversation_id='+curConv); const j=await r.json();
            if(!j.ok) return;
            renderMsgs(j.messages);
        }catch(e){ console.error('uchat loadMsgs',e); }
    }

    function renderMsgs(arr){
        msgs.innerHTML='';
        if(!arr.length){ msgs.innerHTML='<div class="uchat-empty">'+CHAT_ICON.replace('stroke-width="2"','stroke-width="1"')+'<p>Envie uma mensagem para iniciar</p></div>'; return; }
        let lastD='';
        arr.forEach(m=>{
            const d=fmtDate(m.criado_em);
            if(d!==lastD){ lastD=d; const de=document.createElement('div'); de.className='uchat-md'; de.textContent=d; msgs.appendChild(de); }
            appendMsg(m);
        });
        scrollEnd();
    }

    /* ── Append single message ── */
    function appendMsg(m){
        const txt=m.message||'';
        const sm=txt.match(/^\[(INSTRUCOES_VENDA|ENTREGA_AUTO|CODIGO_ENTREGA|SISTEMA)\]\n/);
        const el=document.createElement('div');
        el.dataset.msgId=m.id;

        if(sm){
            const tMap={'INSTRUCOES_VENDA':'inst','ENTREGA_AUTO':'dlvr','CODIGO_ENTREGA':'code','SISTEMA':'sys'};
            const st=tMap[sm[1]]||'sys';
            const body=txt.substring(sm[0].length);
            el.className='uchat-m theirs sys t-'+st;

            const hd=document.createElement('div'); hd.className='s-hd';
            const icons={'inst':'📋 Instruções','dlvr':'📦 Produto entregue','code':'🔑 Código de entrega','sys':'ℹ️ Sistema'};
            hd.textContent=icons[st]||'Sistema'; el.appendChild(hd);

            if(st==='dlvr'){
                const bx=body.match(/━+\n([\s\S]*?)\n━+/);
                if(bx){
                    const before=body.substring(0,body.indexOf('━')).trim();
                    const boxTxt=bx[1].trim();
                    const after=body.substring(body.lastIndexOf('━')+1).trim();
                    if(before){const bt=document.createElement('div');bt.className='s-ct';bt.textContent=before;el.appendChild(bt);}
                    const box=document.createElement('div');box.className='s-bx';
                    box.innerHTML='<button class="s-cp" onclick="navigator.clipboard.writeText(this.nextElementSibling.textContent.trim());this.textContent=\'✓\';setTimeout(()=>this.textContent=\'Copiar\',2000)">Copiar</button><span>'+esc(boxTxt)+'</span>';
                    el.appendChild(box);
                    if(after){const at=document.createElement('div');at.className='s-ct';at.textContent=after;el.appendChild(at);}
                }else{ const ct=document.createElement('div');ct.className='s-ct';ct.textContent=body;el.appendChild(ct); }
            }else{
                const ct=document.createElement('div');ct.className='s-ct';ct.textContent=body;el.appendChild(ct);
            }
        }else{
            el.className='uchat-m '+(m.is_mine?'mine':'theirs');
            const t=document.createElement('div');t.textContent=txt;el.appendChild(t);
        }
        const tm=document.createElement('div');tm.className='um-t';tm.textContent=fmtTime(m.criado_em);el.appendChild(tm);
        msgs.appendChild(el);
        if(m.id>lastId) lastId=m.id;
        // If this is a delivery confirmed system message, hide code strip and show check
        if(sm && txt.indexOf('Entrega confirmada')>=0){
            dlvStrip.style.display='none';
            dlvDone.style.display='block';
        }
    }

    /* ── Send ── */
    async function send(){
        if(sending||!inp||!curConv) return;
        const txt=inp.value.trim(); if(!txt) return;
        sending=true; sndB.disabled=true;
        try{
            const fd=new FormData(); fd.append('conversation_id',curConv); fd.append('message',txt);
            const r=await fetch(API+'?action=send',{method:'POST',body:fd}); const j=await r.json();
            if(j.ok&&j.msg){ msgs.querySelector('.uchat-empty')?.remove(); appendMsg(j.msg); scrollEnd(); inp.value=''; autoH(); }
        }catch(e){ console.error('uchat send',e); }
        sending=false; sndB.disabled=false; inp.focus();
    }
    sndB.onclick=send;
    inp.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send();} });
    inp.addEventListener('input',autoH);
    function autoH(){ if(!inp)return; inp.style.height='auto'; inp.style.height=Math.min(inp.scrollHeight,80)+'px'; }

    /* ── Polling ── */
    function startPoll(){ stopPoll(); pollTmr=setInterval(poll,3000); }
    function stopPoll(){ if(pollTmr){clearInterval(pollTmr);pollTmr=null;} }
    async function poll(){
        if(!curConv||!isOpen||view!=='thread') return;
        try{
            const r=await fetch(API+'?action=poll&conversation_id='+curConv+'&after_id='+lastId); const j=await r.json();
            if(j.ok&&j.messages.length>0){ msgs.querySelector('.uchat-empty')?.remove(); j.messages.forEach(m=>appendMsg(m)); scrollEnd(); }
        }catch(e){}
    }

    /* ── Unread badge ── */
    async function pollBadge(){
        try{
            const r=await fetch(API+'?action=unread_count'); const j=await r.json();
            if(j.ok){ badge.textContent=j.count||0; badge.classList.toggle('show',(j.count||0)>0); }
        }catch(e){}
    }
    pollBadge(); setInterval(pollBadge,15000);

    /* ── Helpers ── */
    function scrollEnd(){ requestAnimationFrame(()=>{ msgs.scrollTop=msgs.scrollHeight; }); }
    function esc(s){ const d=document.createElement('div');d.textContent=s||'';return d.innerHTML; }
    function fmtDate(dt){ if(!dt)return''; const d=new Date(dt.replace(' ','T')),t=new Date(),y=new Date(t); y.setDate(y.getDate()-1); if(d.toDateString()===t.toDateString())return'Hoje'; if(d.toDateString()===y.toDateString())return'Ontem'; return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'short'}); }
    function fmtTime(dt){ if(!dt)return''; return new Date(dt.replace(' ','T')).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}); }
    function fmtRel(dt){ if(!dt)return''; const d=new Date(dt.replace(' ','T')),diff=Math.floor((new Date()-d)/1000); if(diff<60)return'agora'; if(diff<3600)return Math.floor(diff/60)+' min'; if(diff<86400)return Math.floor(diff/3600)+'h'; if(diff<172800)return'ontem'; return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'}); }
    function fmtSeen(dt){ if(!dt)return''; const d=new Date(dt.replace(' ','T')),diff=Math.floor((new Date()-d)/60000); if(diff<5)return'Online'; if(diff<60)return'visto há '+diff+' min'; const h=Math.floor(diff/60); if(h<24)return'visto há '+h+'h'; if(h<48)return'visto ontem'; return'visto '+d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'}); }

    /* ── Global API: open panel on specific conversation ── */
    window.openUserChat = function(convId){
        if(!convId) return;
        isOpen=true; panel.classList.add('open');
        fetch(API+'?action=conversations').then(r=>r.json()).then(j=>{
            if(!j.ok) return;
            const found=(j.conversations||[]).find(c=>c.id==convId);
            if(found) showThread(found.id,found.other_name||'Vendedor',found.other_avatar||'',found.product_name||'',found.last_seen_at||'');
            else showThread(convId,'Vendedor','','','');
        }).catch(()=>showThread(convId,'Vendedor','','',''));
    };

    /* ── Auto-open from URL param ── */
    const oc=new URLSearchParams(window.location.search).get('open_chat');
    if(oc) setTimeout(()=>window.openUserChat(parseInt(oc)),800);

    /* ── Offset if vendor widget also on page ── */
    if(document.querySelector('.vchat-fab')){ fab.style.bottom='96px'; panel.style.bottom='164px'; }
})();
</script>
