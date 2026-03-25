<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\verificar_email.php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/email.php';

$conn  = (new Database())->connect();
$token = trim((string)($_GET['token'] ?? ''));

$success = false;
$errorMsg = '';

if ($token === '') {
    $errorMsg = 'Token de verificação não fornecido.';
} else {
    $uid = validarTokenVerificacao($conn, $token, 'email_verify');
    if ($uid === null) {
        $errorMsg = 'Token inválido ou expirado. Solicite um novo e-mail de verificação.';
    } else {
        // Ensure user_verifications table exists
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS user_verifications (
                id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL, tipo VARCHAR(30) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pendente', dados TEXT, observacao TEXT,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, atualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, tipo))");
        } catch (\Throwable $e) {}

        // Get user email
        $st = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $st->bind_param('i', $uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $email = (string)($row['email'] ?? '');

        // Mark email as verified
        // Try update first
        $upSt = $conn->prepare("UPDATE user_verifications SET status = 'verificado', dados = ?, atualizado = CURRENT_TIMESTAMP WHERE user_id = ? AND tipo = 'email'");
        $dados = json_encode(['email' => $email, 'verified_at' => date('Y-m-d H:i:s')]);
        $upSt->bind_param('si', $dados, $uid);
        $upSt->execute();
        if ($upSt->affected_rows === 0) {
            $upSt->close();
            $inSt = $conn->prepare("INSERT INTO user_verifications (user_id, tipo, status, dados) VALUES (?, 'email', 'verificado', ?)");
            $inSt->bind_param('is', $uid, $dados);
            $inSt->execute();
            $inSt->close();
        } else {
            $upSt->close();
        }

        $success = true;
    }
}

$appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
$baseUrl = defined('BASE_PATH') ? BASE_PATH : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $success ? 'E-mail confirmado' : 'Erro na verificação' ?> – <?= htmlspecialchars($appName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{blackx:'#0d0d0d',blackx2:'#141414',blackx3:'#1f1f1f',greenx:'#00e676'}}}}</script>
</head>
<body class="bg-blackx text-white min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md text-center space-y-6">

<?php if ($success): ?>
  <div class="bg-blackx2 border border-greenx/30 rounded-2xl p-8">
    <div class="w-16 h-16 bg-greenx/15 rounded-full flex items-center justify-center mx-auto mb-4">
      <svg class="w-8 h-8 text-greenx" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
      </svg>
    </div>
    <h1 class="text-xl font-bold text-greenx mb-2">E-mail confirmado!</h1>
    <p class="text-sm text-zinc-400 mb-6">Seu e-mail foi verificado com sucesso. Agora sua conta está mais segura.</p>
    <a href="<?= $baseUrl ?>/verificacao"
       class="inline-flex items-center gap-2 rounded-xl bg-greenx/15 border border-greenx/30 px-6 py-3 text-sm font-semibold text-greenx hover:bg-greenx/20 transition">
      Voltar para Verificação
    </a>
  </div>
<?php else: ?>
  <div class="bg-blackx2 border border-red-500/30 rounded-2xl p-8">
    <div class="w-16 h-16 bg-red-500/15 rounded-full flex items-center justify-center mx-auto mb-4">
      <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
      </svg>
    </div>
    <h1 class="text-xl font-bold text-red-400 mb-2">Erro na verificação</h1>
    <p class="text-sm text-zinc-400 mb-6"><?= htmlspecialchars($errorMsg) ?></p>
    <a href="<?= $baseUrl ?>/verificacao"
       class="inline-flex items-center gap-2 rounded-xl bg-greenx/15 border border-greenx/30 px-6 py-3 text-sm font-semibold text-purple-400 hover:bg-greenx/20 transition">
      Ir para Verificação
    </a>
  </div>
<?php endif; ?>

</div>
</body>
</html>
