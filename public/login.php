<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\login.php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

iniciarSessao();

function firstExistingPublicRoute(array $candidates): string
{
    foreach ($candidates as $rel) {
        $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($file)) {
            return BASE_PATH . $rel;
        }
    }
    return BASE_PATH . '/';
}

$db = new Database();
$conn = $db->connect();

$erro = '';
$redirect = (string)($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$redirect = strtolower(trim($redirect));
$allowedRedirects = [
    'checkout' => BASE_PATH . '/checkout',
];
$redirectUrl = $allowedRedirects[$redirect] ?? '';

// Support arbitrary return_to URL (must be local path)
$returnTo = trim((string)($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo !== '' && str_starts_with($returnTo, '/') && !str_contains($returnTo, '://')) {
    $redirectUrl = $returnTo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string)($_POST['email'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');

    $stmt = $conn->prepare("SELECT id, nome, email, senha, role, ativo, status_vendedor FROM users WHERE lower(btrim(email)) = lower(btrim(?)) LIMIT 1");
    $stmt->execute([$email]);

    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null; // <- CORRETO

    if (!$user) {
        $erro = 'Credenciais inválidas.';
    } else {
        // ajuste conforme sua regra atual de senha:
        $okSenha = password_verify($senha, (string)$user['senha']) || $senha === (string)$user['senha'];

        if (!$okSenha) {
            $erro = 'Credenciais inválidas.';
        } elseif (!valorBooleano($user['ativo'] ?? true, true)) {
            $erro = 'Conta inativa.';
        } else {
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $guestCart = $_SESSION['store_cart'] ?? [];
            session_regenerate_id(true);
            $_SESSION = []; // limpa sessão antiga misturada
            if (is_array($guestCart)) {
                $_SESSION['store_cart'] = $guestCart;
            }

            $role = normalizarRole((string)($user['role'] ?? 'usuario'));
            $statusVendedor = normalizarStatusVendedor((string)($user['status_vendedor'] ?? 'nao_solicitado'));

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['nome'] = (string)$user['nome'];
            $_SESSION['role'] = $role;
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'nome' => (string)$user['nome'],
                'email' => (string)$user['email'],
                'role' => $role,
                'status_vendedor' => $statusVendedor,
            ];

            // Handle favorite product addition after login
            $favProduct = (int)($_GET['fav_product'] ?? $_POST['fav_product'] ?? 0);
            if ($favProduct > 0) {
                require_once __DIR__ . '/../src/favorites.php';
                try {
                    favoritesToggle($conn, (int)$user['id'], $favProduct);
                    $_SESSION['_fav_toast'] = 'Produto adicionado aos favoritos!';
                } catch (\Throwable $e) {}
            }

            // Send "Novo Login Detectado" email (non-blocking)
            try {
                require_once __DIR__ . '/../src/email.php';
                if (smtpConfigured($conn)) {
                    $loginIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
                    if (str_contains($loginIp, ',')) $loginIp = trim(explode(',', $loginIp)[0]);
                    $loginDevice = $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido';
                    $loginDataHora = date('d/m/Y H:i:s');
                    $loginHtml = emailNovoLogin((string)$user['nome'], $loginDevice, $loginIp, $loginDataHora, '', $conn);
                    smtpSend((string)$user['email'], 'Novo login detectado – ' . APP_NAME, $loginHtml);
                }
            } catch (\Throwable $e) {
                error_log('[Login] new login email error: ' . $e->getMessage());
            }

            if ($role === 'admin') {
                header('Location: ' . BASE_PATH . '/admin/dashboard');
                exit;
            }
            // Unified dashboard for all users
            if ($redirectUrl !== '') {
                header('Location: ' . $redirectUrl);
                exit;
            }
            header('Location: ' . BASE_PATH . '/dashboard');
            exit;
        }
    }
}

$pageTitle = 'Entrar';
include __DIR__ . '/../views/partials/header.php';

// Check if Google OAuth is configured
require_once __DIR__ . '/../src/google_auth.php';
$googleConfigured = googleIsConfigured($conn);
$googleError = isset($_GET['google_error']) ? htmlspecialchars((string)$_GET['google_error']) : '';
$googleAuthUrl = $googleConfigured ? googleAuthUrl($conn, $redirectUrl, 'login') : '';
?>
<style>
  @keyframes blob{0%,100%{transform:translate(0,0) scale(1)}25%{transform:translate(30px,-50px) scale(1.1)}50%{transform:translate(-20px,20px) scale(0.9)}75%{transform:translate(20px,40px) scale(1.05)}}
  @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
  .auth-blob{position:absolute;border-radius:50%;filter:blur(80px);opacity:.15;animation:blob 20s ease-in-out infinite}
  .auth-input{transition:all .3s cubic-bezier(.4,0,.2,1)}
  .auth-input:focus{box-shadow:0 0 0 3px rgba(var(--t-accent-rgb),.15);border-color:var(--t-accent)!important;transform:translateY(-1px)}
  .auth-input:focus ~ .auth-icon-fix, .auth-input:focus + .auth-icon-fix{} /* keep icons visible */
  .auth-icon{position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);width:1rem;height:1rem;color:#71717a;pointer-events:none;z-index:10;transition:color .3s}
  .auth-input:focus ~ .auth-icon, .relative:focus-within .auth-icon{color:var(--t-accent)}
  .auth-btn{position:relative;overflow:hidden;transition:all .3s cubic-bezier(.4,0,.2,1)}
  .auth-btn::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);transform:translateX(-100%);transition:transform .5s}
  .auth-btn:hover::before{transform:translateX(100%)}
  .auth-btn:hover{transform:translateY(-1px);box-shadow:0 8px 30px -4px rgba(var(--t-accent-rgb),.4)}
  .auth-card{backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);animation:fadeInUp .6s ease-out both}
  .auth-link{position:relative}.auth-link::after{content:'';position:absolute;bottom:-2px;left:0;width:0;height:2px;background:var(--t-accent);transition:width .3s ease}.auth-link:hover::after{width:100%}
  .auth-features li{animation:fadeInUp .5s ease-out both}
</style>

<div class="min-h-screen relative overflow-hidden flex items-center justify-center px-4 py-8">
  <!-- Animated background blobs -->
  <div class="auth-blob w-[500px] h-[500px] bg-greenx -top-40 -left-40" style="animation-delay:0s"></div>
  <div class="auth-blob w-[400px] h-[400px] bg-purple-500 top-1/2 -right-32" style="animation-delay:-7s"></div>
  <div class="auth-blob w-[350px] h-[350px] bg-greenx -bottom-32 left-1/3" style="animation-delay:-14s"></div>

  <!-- Grid overlay -->
  <div class="absolute inset-0 opacity-[0.03]" style="background-image:linear-gradient(rgba(255,255,255,.1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.1) 1px,transparent 1px);background-size:60px 60px"></div>

  <div class="w-full max-w-[960px] relative z-10">
    <div class="grid md:grid-cols-2 gap-0 rounded-3xl overflow-hidden shadow-2xl shadow-black/40">

      <!-- Left panel — branding -->
      <div class="auth-left-panel hidden md:flex flex-col justify-between p-10 relative overflow-hidden" style="background:linear-gradient(135deg,rgba(var(--t-accent-rgb),.12),rgba(var(--t-accent-rgb),.03))">
        <div class="absolute inset-0 border-r border-white/[0.06]"></div>
        <div class="relative z-10">
          <a href="<?= BASE_PATH ?>/" class="inline-flex items-center gap-3 group mb-10">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-greenx to-greenxd flex items-center justify-center shadow-lg shadow-greenx/20">
              <i data-lucide="store" class="w-5 h-5 text-white"></i>
            </div>
            <span class="font-bold text-white text-lg tracking-tight">Base<span class="text-greenx">fy</span></span>
          </a>
          <h2 class="text-2xl font-bold text-white leading-tight mb-3">Bem-vindo de volta!</h2>
          <p class="text-zinc-400 text-sm leading-relaxed mb-8">Acesse sua conta para gerenciar vendas, acompanhar pedidos e muito mais.</p>
          <ul class="auth-features space-y-4">
            <li class="flex items-center gap-3 stagger-1">
              <div class="w-9 h-9 rounded-xl bg-greenx/10 flex items-center justify-center flex-shrink-0"><i data-lucide="shield-check" class="w-4 h-4 text-greenx"></i></div>
              <span class="text-sm text-zinc-300">Pagamentos seguros via PIX</span>
            </li>
            <li class="flex items-center gap-3 stagger-2">
              <div class="w-9 h-9 rounded-xl bg-greenx/10 flex items-center justify-center flex-shrink-0"><i data-lucide="wallet" class="w-4 h-4 text-greenx"></i></div>
              <span class="text-sm text-zinc-300">Carteira digital integrada</span>
            </li>
            <li class="flex items-center gap-3 stagger-3">
              <div class="w-9 h-9 rounded-xl bg-greenx/10 flex items-center justify-center flex-shrink-0"><i data-lucide="message-circle" class="w-4 h-4 text-greenx"></i></div>
              <span class="text-sm text-zinc-300">Chat em tempo real</span>
            </li>
            <li class="flex items-center gap-3 stagger-4">
              <div class="w-9 h-9 rounded-xl bg-greenx/10 flex items-center justify-center flex-shrink-0"><i data-lucide="trending-up" class="w-4 h-4 text-greenx"></i></div>
              <span class="text-sm text-zinc-300">Painel completo de vendas</span>
            </li>
          </ul>
        </div>
        <p class="text-xs text-zinc-600 relative z-10 mt-8">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>. Todos os direitos reservados.</p>
      </div>

      <!-- Right panel — form -->
      <div class="auth-card bg-blackx2/80 border border-white/[0.06] p-8 md:p-10 flex flex-col justify-center relative">
        <!-- Theme toggle (inside card, top-right) -->
        <button onclick="toggleThemeMode()"
                class="theme-toggle-btn absolute top-4 right-4 z-20"
                title="Alternar tema">
          <i data-lucide="sun" class="w-4 h-4 theme-icon-sun" style="display:<?= ($_themeMode ?? 'dark') === 'dark' ? 'block' : 'none' ?>"></i>
          <i data-lucide="moon" class="w-4 h-4 theme-icon-moon" style="display:<?= ($_themeMode ?? 'dark') === 'light' ? 'block' : 'none' ?>"></i>
        </button>
        <!-- Mobile logo -->
        <div class="flex md:hidden items-center gap-3 mb-8">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-greenx to-greenxd flex items-center justify-center shadow-lg shadow-greenx/20">
            <i data-lucide="store" class="w-5 h-5 text-white"></i>
          </div>
          <span class="font-bold text-white text-lg tracking-tight">Base<span class="text-greenx">fy</span></span>
        </div>

        <div class="mb-6">
          <a href="javascript:void(0)" onclick="window.history.length>1?window.history.back():window.location='<?= BASE_PATH ?>/'"
             class="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-greenx transition-colors mb-3">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar
          </a>
          <h1 class="text-2xl font-bold text-white">Entrar</h1>
          <p class="text-sm text-zinc-500 mt-1">Insira seus dados para acessar</p>
        </div>

        <?php if ($erro): ?>
        <div class="mb-5 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 text-sm flex items-center gap-2.5 animate-fade-in">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
          <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <?php if ($googleError): ?>
        <div class="mb-5 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 text-sm flex items-center gap-2.5 animate-fade-in">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
          <?= $googleError ?>
        </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
          <?php if ($redirectUrl !== ''): ?>
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>

          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1.5 uppercase tracking-wider">E-mail</label>
            <div class="relative">
              <i data-lucide="mail" class="auth-icon"></i>
              <input type="email" name="email" placeholder="seu@email.com" required
                     class="auth-input w-full rounded-xl bg-white/[0.04] border border-white/[0.08] pl-11 pr-4 py-3 outline-none text-white placeholder:text-zinc-600 text-sm">
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-zinc-400 mb-1.5 uppercase tracking-wider">Senha</label>
            <div class="relative" x-data="{show:false}">
              <i data-lucide="lock" class="auth-icon"></i>
              <input :type="show?'text':'password'" name="senha" placeholder="Sua senha" required
                     class="auth-input w-full rounded-xl bg-white/[0.04] border border-white/[0.08] pl-11 pr-11 py-3 outline-none text-white placeholder:text-zinc-600 text-sm">
              <button type="button" @click="show=!show" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300 transition-colors">
                <i :data-lucide="show?'eye-off':'eye'" class="w-4 h-4"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="auth-btn w-full rounded-xl bg-gradient-to-r from-greenx to-greenxd text-white font-bold py-3.5 text-sm tracking-wide mt-2">
            Entrar na conta
          </button>
        </form>

        <?php if ($googleConfigured): ?>
        <div class="relative my-6">
          <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-white/[0.06]"></div></div>
          <div class="relative flex justify-center text-xs"><span class="px-4 bg-blackx2/80 text-zinc-500 font-medium">ou entre com Google</span></div>
        </div>

        <a href="<?= BASE_PATH ?>/google_redirect?mode=login<?= $redirectUrl !== '' ? '&return_to=' . urlencode($redirectUrl) : '' ?>"
           class="flex items-center justify-center gap-3 w-full rounded-xl border border-white/[0.1] bg-white/[0.03] hover:bg-white/[0.07] text-white font-medium py-3 transition-all hover:border-white/[0.2] hover:shadow-lg group">
          <svg width="20" height="20" viewBox="0 0 48 48" class="transition-transform group-hover:scale-110"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
          Entrar com Google
        </a>
        <?php endif; ?>

        <p class="text-sm text-zinc-500 mt-6 text-center">
          Não possui conta?
          <a href="register" class="auth-link text-greenx font-semibold hover:text-greenx2 transition-colors">Criar conta</a>
        </p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>