<?php
// filepath: c:\xampp\htdocs\mercado_admin\views\partials\sidebar_vendedor.php
declare(strict_types=1);

$sidebarTitle = 'Painel Vendedor';
$menuSections = [
    [
        'title' => 'MÓDULOS',
        'items' => [
            ['key' => 'dashboard',        'label' => 'Dashboard',         'href' => BASE_PATH . '/vendedor/dashboard',         'icon' => 'layout-dashboard'],
            ['key' => 'meus_produtos',    'label' => 'Meus Produtos',     'href' => BASE_PATH . '/vendedor/produtos',          'icon' => 'package'],
            ['key' => 'vendas_aprovadas', 'label' => 'Vendas Aprovadas',  'href' => BASE_PATH . '/vendedor/vendas_aprovadas', 'icon' => 'badge-check'],
            ['key' => 'vendas_analise',   'label' => 'Vendas em Análise', 'href' => BASE_PATH . '/vendedor/vendas_analise',   'icon' => 'hourglass'],
            ['key' => 'saques_wallet',    'label' => 'Saques',            'href' => BASE_PATH . '/vendedor/saques',           'icon' => 'wallet'],
            ['key' => 'chat',             'label' => 'Chat',              'href' => BASE_PATH . '/vendedor/chat',             'icon' => 'message-circle'],
        ],
    ],
];