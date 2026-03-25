<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\minha_conta.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/media.php';

exigirAdmin();

$conn = (new Database())->connect();
$uid = (int)($_SESSION['user_id'] ?? 0);

$activeMenu = 'minha_conta';
$pageTitle  = 'Minha Conta';

function adminPickCol(array $cols, array $candidates): ?string
{
	foreach ($candidates as $candidate) {
		if (in_array(strtolower($candidate), $cols, true)) {
			return $candidate;
		}
	}
	return null;
}

$cols = [];
$rs = $conn->query("SHOW COLUMNS FROM users");
if ($rs) {
	while ($row = $rs->fetch_assoc()) {
		$cols[] = strtolower((string)$row['Field']);
	}
}

$nameCol  = adminPickCol($cols, ['nome', 'name', 'username']);
$emailCol = adminPickCol($cols, ['email', 'mail']);
$passCol  = adminPickCol($cols, ['senha', 'senha_hash', 'password', 'password_hash']);
$photoCol = adminPickCol($cols, ['foto_perfil', 'foto', 'avatar', 'profile_photo']);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = (string)($_POST['action'] ?? '');

	if ($action === 'perfil') {
		$nome = trim((string)($_POST['nome'] ?? ''));
		$email = trim((string)($_POST['email'] ?? ''));

		if (!$nameCol || !$emailCol) {
			$err = 'Colunas de nome/email não encontradas.';
		} elseif ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$err = 'Dados inválidos.';
		} else {
			$sets = ["`{$nameCol}` = ?", "`{$emailCol}` = ?"];
			$types = 'ss';
			$values = [$nome, $email];

			if ($photoCol && isset($_FILES['foto']) && (int)($_FILES['foto']['error'] ?? 4) === UPLOAD_ERR_OK) {
				$mediaId = mediaSaveFromUpload($_FILES['foto'], 'avatar', $uid, true);
				if ($mediaId) {
					$dbPath = 'media:' . $mediaId;
					$sets[] = "`{$photoCol}` = ?";
					$types .= 's';
					$values[] = $dbPath;
				} else {
					$err = 'Foto inválida (JPG/PNG/WEBP até 5MB).';
				}
			}

			if ($err === '') {
				$sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
				$types .= 'i';
				$values[] = $uid;

				$up = $conn->prepare($sql);
				$up->bind_param($types, ...$values);
				if ($up->execute()) {
					$msg = 'Perfil atualizado com sucesso.';
					if (isset($_SESSION['user'])) {
						$_SESSION['user']['nome'] = $nome;
						$_SESSION['user']['email'] = $email;
						if (isset($dbPath)) {
							$_SESSION['user']['avatar'] = $dbPath;
						}
					}
				} else {
					$err = 'Não foi possível atualizar.';
				}
				$up->close();
			}
		}
	}

	if ($action === 'senha') {
		$isGoogleUser = !empty($_SESSION['is_google']) || !empty($_SESSION['user']['is_google']);
		$atual = (string)($_POST['senha_atual'] ?? '');
		$nova = (string)($_POST['senha_nova'] ?? '');
		$conf = (string)($_POST['senha_confirma'] ?? '');

		// All fields empty = skip silently
		if ($atual === '' && $nova === '' && $conf === '') {
			// do nothing
		} elseif (!$passCol) {
			$err = 'Coluna de senha não encontrada.';
		} elseif ($nova === '' || strlen($nova) < 6 || $nova !== $conf) {
			$err = 'Confira os dados da nova senha.';
		} else {
			if ($isGoogleUser && $atual === '') {
				$newHash = password_hash($nova, PASSWORD_DEFAULT);
				$up = $conn->prepare("UPDATE users SET `{$passCol}` = ? WHERE id = ? LIMIT 1");
				$up->bind_param('si', $newHash, $uid);
				if ($up->execute()) $msg = 'Senha definida com sucesso.';
				else $err = 'Não foi possível definir senha.';
				$up->close();
			} else {
				$st = $conn->prepare("SELECT `{$passCol}` AS senha FROM users WHERE id = ? LIMIT 1");
				$st->bind_param('i', $uid);
				$st->execute();
				$user = $st->get_result()->fetch_assoc() ?: [];
				$st->close();

				$hash = (string)($user['senha'] ?? '');
				$okAtual = password_verify($atual, $hash) || hash_equals($hash, $atual);

				if (!$okAtual) {
					$err = 'Senha atual incorreta.';
				} else {
					$newHash = password_hash($nova, PASSWORD_DEFAULT);
					$up = $conn->prepare("UPDATE users SET `{$passCol}` = ? WHERE id = ? LIMIT 1");
					$up->bind_param('si', $newHash, $uid);
					if ($up->execute()) $msg = 'Senha alterada com sucesso.';
					else $err = 'Não foi possível alterar senha.';
					$up->close();
				}
			}
		}
	}
}

$select = ['id'];
if ($nameCol) {
	$select[] = "`{$nameCol}` AS nome";
}
if ($emailCol) {
	$select[] = "`{$emailCol}` AS email";
}
if ($photoCol) {
	$select[] = "`{$photoCol}` AS foto";
}

$st = $conn->prepare("SELECT " . implode(', ', $select) . " FROM users WHERE id = ? LIMIT 1");
$st->bind_param('i', $uid);
$st->execute();
$user = $st->get_result()->fetch_assoc() ?: [];
$st->close();

$fotoRaw = trim((string)($user['foto'] ?? ''));
$foto = mediaResolveUrl($fotoRaw, 'https://placehold.co/160x160/111827/9ca3af?text=Admin');

include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<div class="space-y-5">
  <?php if ($msg): ?><div class="text-greenx text-sm"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($err): ?><div class="text-red-300 text-sm"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div class="grid xl:grid-cols-3 gap-5">
	<form method="post" enctype="multipart/form-data" class="xl:col-span-2 bg-blackx2 border border-blackx3 rounded-2xl p-6 space-y-4">
	  <input type="hidden" name="action" value="perfil">
	  <h2 class="text-lg font-semibold">Perfil do administrador</h2>

	  <div class="grid lg:grid-cols-5 gap-5">
		<div class="lg:col-span-2 bg-blackx rounded-2xl border border-blackx3 p-5 flex flex-col items-center gap-4"
		     x-data="avatarUpload('<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>')"
		     @dragover.prevent="dragging = true"
		     @dragleave.prevent="dragging = false"
		     @drop.prevent="handleDrop($event)">

		  <div class="relative group cursor-pointer" @click="$refs.fotoInput.click()">
			<img :src="preview" alt="Avatar"
			     class="w-40 h-40 rounded-full object-cover border-2 transition-all"
			     :class="dragging ? 'border-greenx scale-105' : 'border-blackx3'">
			<div class="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
			  <div class="text-center">
				<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white mx-auto mb-1"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
				<span class="text-xs text-white font-medium">Alterar foto</span>
			  </div>
			</div>
		  </div>
		  <p class="text-xs text-zinc-600">Arraste ou clique para alterar a foto</p>
		  <input type="file" name="foto" x-ref="fotoInput" accept="image/*"
		         class="hidden" @change="handleFile($event)">
		</div>

		<div class="lg:col-span-3 space-y-4">
		  <input name="nome" value="<?= htmlspecialchars((string)($user['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-3">
		  <input type="email" name="email" value="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-3">
		  <button class="bg-greenx text-white font-semibold rounded-xl px-5 py-3">Salvar perfil</button>
		</div>
	  </div>
	</form>

	<form method="post" class="bg-blackx2 border border-blackx3 rounded-2xl p-6 space-y-4">
	  <input type="hidden" name="action" value="senha">
	  <?php $_isGoogleUser = !empty($_SESSION['is_google']) || !empty($_SESSION['user']['is_google']); ?>
	  <h2 class="text-lg font-semibold"><?= $_isGoogleUser ? 'Definir Senha' : 'Segurança' ?></h2>
	  <?php if ($_isGoogleUser): ?>
	  <div class="rounded-xl border border-greenx/30 bg-greenx/10 px-4 py-3 text-sm text-purple-300 flex items-center gap-2">
		<svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
		Você entrou com o Google. A senha é opcional.
	  </div>
	  <?php else: ?>
	  <input type="password" name="senha_atual" placeholder="Senha atual" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-3">
	  <?php endif; ?>
	  <input type="password" name="senha_nova" placeholder="<?= $_isGoogleUser ? 'Criar senha (opcional)' : 'Nova senha' ?>" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-3">
	  <input type="password" name="senha_confirma" placeholder="Confirmar nova senha" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-3">
	  <button class="bg-greenx text-white font-semibold rounded-xl px-5 py-3"><?= $_isGoogleUser ? 'Definir senha' : 'Alterar senha' ?></button>
	</form>
  </div>
</div>

<script>
function avatarUpload(existingUrl) {
    return {
        preview: existingUrl || '',
        dragging: false,
        handleFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.processFile(file);
        },
        handleDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0];
            if (!file || !file.type.startsWith('image/')) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.fotoInput.files = dt.files;
            this.processFile(file);
        },
        processFile(file) {
            if (file.size > 5 * 1024 * 1024) {
                alert('Arquivo muito grande. Máximo 5MB.');
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => { this.preview = e.target.result; };
            reader.readAsDataURL(file);
        }
    };
}
</script>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';