<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\api_usuario_action.php
// ...existing code...
$scope = (string)($_POST['scope'] ?? 'usuarios'); // usuarios|vendedores|admins

$rolesPermitidos = match ($scope) {
    'admins' => ['admin'],
    'vendedores' => ['vendedor'],
    default => ['usuario'], // tela Usuários
};

[$ok, $msg] = atualizarStatusAtivoUsuario($conn, $id, $ativo, $rolesPermitidos);
echo json_encode(['ok' => true, 'msg' => 'Usuário atualizado.']);
// ...existing code...