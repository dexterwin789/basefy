<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';

$userId     = (int)($_SESSION['user_id'] ?? 0);
$userRole   = (string)($_SESSION['user']['role'] ?? 'usuario');
$isLoggedIn = $userId > 0;

$conn = (new Database())->connect();

$feedback = '';

$removeKey = (string)($_GET['remove'] ?? '');
if ($removeKey !== '') {
    sfCartRemoveByKey($removeKey);
    $feedback = 'Item removido do carrinho.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update_single') {
        $cartKey = (string)($_POST['cart_key'] ?? '');
        $qty = (int)($_POST['qty'] ?? 1);
        if ($cartKey !== '') {
            [$pid, $vname] = sfCartParseKey($cartKey);
            sfCartSetQty($pid, $qty, $vname);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'msg' => 'Quantidade atualizada.',
            'cart_count' => sfCartCount(),
        ]);
        exit;
    } elseif ($action === 'update_qty') {
        $quantities = $_POST['qty'] ?? [];
        if (is_array($quantities)) {
            foreach ($quantities as $key => $qty) {
                [$pid, $vname] = sfCartParseKey((string)$key);
                sfCartSetQty($pid, (int)$qty, $vname);
            }
            $feedback = 'Carrinho atualizado.';
        }
    } elseif ($action === 'clear') {
        sfCartClear();
        $feedback = 'Carrinho limpo.';
    }
}

$summary   = sfCartSummary($conn);
$items     = $summary['items'];
$total     = (float)$summary['total'];
$count     = (int)$summary['count'];
$cartCount = sfCartCount();

$currentPage = 'carrinho';
$pageTitle   = 'Carrinho';
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<div class="min-h-screen bg-blackx">
    <!-- Page header -->
    <div class="border-b border-white/[0.04] bg-white/[0.01]">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-5">
            <div class="flex items-center gap-2 text-sm text-zinc-500 mb-2 animate-fade-in">
                <a href="<?= BASE_PATH ?>/" class="hover:text-greenx transition-colors">Início</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-white font-medium">Carrinho</span>
            </div>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold animate-fade-in-up">
                    Carrinho
                    <?php if ($count > 0): ?>
                    <span class="text-base font-normal text-zinc-500 ml-2">(<?= $count ?> <?= $count === 1 ? 'item' : 'itens' ?>)</span>
                    <?php endif; ?>
                </h1>
                <a href="<?= BASE_PATH ?>/categorias"
                   class="hidden sm:inline-flex items-center gap-2 text-sm text-zinc-400 hover:text-greenx transition-colors">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Continuar comprando
                </a>
            </div>
        </div>
    </div>

    <?php if ($feedback !== ''): ?>
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 mt-4 animate-scale-in">
        <div class="flex items-center gap-3 rounded-2xl border border-greenx/30 bg-greenx/[0.06] px-5 py-3.5">
            <div class="w-8 h-8 rounded-full bg-greenx/20 flex items-center justify-center flex-shrink-0"><i data-lucide="check" class="w-4 h-4 text-greenx"></i></div>
            <p class="text-sm text-greenx"><?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
    <?php endif; ?>

    <main class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
        <?php if (!$items): ?>
        <!-- Empty state -->
        <div class="rounded-3xl border border-white/[0.06] bg-white/[0.02] p-16 text-center animate-fade-in-up">
            <div class="w-20 h-20 rounded-3xl bg-white/[0.04] border border-white/[0.06] flex items-center justify-center mx-auto mb-5">
                <i data-lucide="shopping-bag" class="w-8 h-8 text-zinc-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-zinc-200">Seu carrinho está vazio</h2>
            <p class="text-zinc-500 mt-2 max-w-md mx-auto">Explore nosso catálogo e adicione produtos para começar suas compras com pagamento via PIX.</p>
            <a href="<?= BASE_PATH ?>/categorias"
               class="inline-flex items-center gap-2 mt-6 px-6 py-3 rounded-2xl bg-gradient-to-r from-greenx to-greenxd text-white font-bold text-sm shadow-lg shadow-greenx/20 hover:shadow-greenx/30 transition-all">
                <i data-lucide="grid-3x3" class="w-4 h-4"></i>
                Explorar catálogo
            </a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Cart items -->
            <section class="xl:col-span-2">
                <form method="post" id="cart-form">
                    <input type="hidden" name="action" value="update_qty">
                    <div class="space-y-3">
                        <?php foreach ($items as $i => $item): ?>
                        <article data-cart-item data-unit-price="<?= number_format((float)$item['preco'], 2, '.', '') ?>" class="group p-4 sm:p-5 rounded-2xl border border-white/[0.06] bg-blackx2 hover:border-white/[0.1] transition-all animate-fade-in-up stagger-<?= min($i + 1, 8) ?>">
                            <div class="flex gap-4">
                                <!-- Image -->
                                <a href="<?= sfProductUrl($item) ?>"
                                   class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden border border-white/[0.06] flex-shrink-0 bg-blackx">
                                    <img src="<?= htmlspecialchars(sfImageUrl((string)($item['imagem'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars((string)$item['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                         class="w-full h-full object-cover">
                                </a>

                                <!-- Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <a href="<?= sfProductUrl($item) ?>"
                                               class="font-semibold text-[15px] hover:text-greenx transition-colors block truncate">
                                                <?= htmlspecialchars((string)$item['nome'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <?php if (!empty($item['variante_nome'])): ?>
                                            <p class="text-xs text-greenx/70 mt-0.5"><?= htmlspecialchars((string)$item['variante_nome'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                            <p class="text-xs text-zinc-500 mt-0.5"><?= htmlspecialchars((string)($item['categoria_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <a href="<?= BASE_PATH ?>/carrinho?remove=<?= urlencode($item['cart_key']) ?>"
                                           class="flex-shrink-0 w-8 h-8 rounded-lg border border-white/[0.06] flex items-center justify-center text-zinc-500 hover:text-red-400 hover:border-red-400/30 transition-all"
                                           title="Remover item">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2">
                                        <!-- Qty input -->
                                        <div class="flex items-center gap-0 rounded-xl border border-white/[0.08] bg-white/[0.03] overflow-hidden">
                                            <button type="button" data-qty-action="minus"
                                                    class="w-8 h-8 flex items-center justify-center text-zinc-500 hover:text-white hover:bg-white/[0.06] transition-all">
                                                <i data-lucide="minus" class="w-3 h-3"></i>
                                            </button>
                                            <input type="number" min="1" max="99"
                                                   name="qty[<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>]" value="<?= (int)$item['qty'] ?>"
                                                  data-cart-key="<?= htmlspecialchars($item['cart_key'], ENT_QUOTES, 'UTF-8') ?>"
                                                   data-qty-input
                                                   class="w-10 h-8 text-center bg-transparent text-sm font-semibold text-white border-x border-white/[0.08] focus:outline-none">
                                            <button type="button" data-qty-action="plus"
                                                    class="w-8 h-8 flex items-center justify-center text-zinc-500 hover:text-white hover:bg-white/[0.06] transition-all">
                                                <i data-lucide="plus" class="w-3 h-3"></i>
                                            </button>
                                        </div>

                                        <div class="flex items-center gap-4 text-sm">
                                            <span class="text-zinc-500">R$&nbsp;<?= number_format((float)$item['preco'], 2, ',', '.') ?> un.</span>
                                            <span class="font-bold text-greenx js-line-total">R$&nbsp;<?= number_format((float)$item['line_total'], 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 flex gap-3">
                        <button class="inline-flex items-center gap-2 rounded-xl border border-white/[0.08] hover:border-greenx/40 px-5 py-2.5 text-sm text-zinc-300 hover:text-white transition-all">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            Atualizar carrinho
                        </button>
                    </div>
                </form>
            </section>

            <!-- Summary sidebar -->
            <aside class="animate-fade-in-up stagger-2">
                <div class="sticky top-20 rounded-3xl border border-white/[0.06] bg-blackx2 p-5 sm:p-6 space-y-5">
                    <h2 class="text-lg font-bold">Resumo do pedido</h2>

                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-400">Subtotal (<span id="cart-count"><?= $count ?></span> <span id="cart-count-word"><?= $count === 1 ? 'item' : 'itens' ?></span>)</span>
                            <span class="font-semibold" id="cart-subtotal">R$&nbsp;<?= number_format($total, 2, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-400">Entrega</span>
                            <span class="font-semibold text-greenx">Digital</span>
                        </div>
                        <div class="border-t border-white/[0.06] pt-3 flex justify-between">
                            <span class="font-semibold">Total</span>
                            <span class="text-xl font-black text-greenx" id="cart-total">R$&nbsp;<?= number_format($total, 2, ',', '.') ?></span>
                        </div>
                    </div>

                    <?php if ($isLoggedIn): ?>
                    <a href="<?= BASE_PATH ?>/checkout"
                       class="w-full flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold px-5 py-3.5 text-sm shadow-lg shadow-greenx/20 hover:shadow-greenx/30 transition-all">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                        Ir para checkout
                    </a>
                    <?php else: ?>
                    <a href="<?= BASE_PATH ?>/login?redirect=checkout"
                       class="w-full flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold px-5 py-3.5 text-sm shadow-lg shadow-greenx/20 hover:shadow-greenx/30 transition-all">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Entrar para finalizar
                    </a>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="action" value="clear">
                        <button class="w-full flex items-center justify-center gap-2 rounded-xl border border-white/[0.06] hover:border-red-500/30 px-4 py-2.5 text-sm text-zinc-400 hover:text-red-400 transition-all">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            Limpar carrinho
                        </button>
                    </form>

                    <!-- Trust -->
                    <div class="pt-2 border-t border-white/[0.06] space-y-2.5">
                        <div class="flex items-center gap-2.5 text-xs text-zinc-500">
                            <i data-lucide="shield-check" class="w-4 h-4 text-greenx flex-shrink-0"></i>
                            Pagamento protegido por escrow
                        </div>
                        <div class="flex items-center gap-2.5 text-xs text-zinc-500">
                            <i data-lucide="wallet" class="w-4 h-4 text-purple-400 flex-shrink-0"></i>
                            Use saldo da carteira como desconto
                        </div>
                        <div class="flex items-center gap-2.5 text-xs text-zinc-500">
                            <i data-lucide="qr-code" class="w-4 h-4 text-purple-400 flex-shrink-0"></i>
                            PIX instantâneo no checkout
                        </div>
                    </div>
                </div>
            </aside>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';

if ($items):
?>
<script>
    (function () {
        const qtyInputs = Array.from(document.querySelectorAll('[data-qty-input]'));
        if (!qtyInputs.length) return;

        const subtotalEl = document.getElementById('cart-subtotal');
        const totalEl = document.getElementById('cart-total');
        const countEl = document.getElementById('cart-count');
        const countWordEl = document.getElementById('cart-count-word');
        const saveTimers = new Map();

        function clampQty(value) {
            const parsed = Number.parseInt(String(value), 10);
            if (!Number.isFinite(parsed)) return 1;
            return Math.max(1, Math.min(99, parsed));
        }

        function formatBRL(value) {
            return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function saveQty(input, immediate) {
            const cartKey = input.getAttribute('data-cart-key') || '';
            const qty = clampQty(input.value);
            input.value = String(qty);

            if (!cartKey) return;

            const runSave = () => {
                const body = new URLSearchParams({
                    action: 'update_single',
                    cart_key: cartKey,
                    qty: String(qty),
                });

                fetch('<?= BASE_PATH ?>/carrinho', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body,
                })
                .then((res) => res.json())
                .then((data) => {
                    if (!data || data.ok !== true) return;
                    const badges = document.querySelectorAll('[data-cart-count]');
                    badges.forEach((badge) => {
                        badge.textContent = String(data.cart_count ?? 0);
                        const count = Number.parseInt(String(data.cart_count ?? 0), 10);
                        badge.classList.toggle('hidden', !Number.isFinite(count) || count <= 0);
                    });
                })
                .catch(() => {
                });
            };

            if (immediate) {
                if (saveTimers.has(cartKey)) {
                    clearTimeout(saveTimers.get(cartKey));
                    saveTimers.delete(cartKey);
                }
                runSave();
                return;
            }

            if (saveTimers.has(cartKey)) {
                clearTimeout(saveTimers.get(cartKey));
            }
            const timer = setTimeout(() => {
                runSave();
                saveTimers.delete(cartKey);
            }, 300);
            saveTimers.set(cartKey, timer);
        }

        function recalcCart() {
            let total = 0;
            let count = 0;

            qtyInputs.forEach((input) => {
                const qty = clampQty(input.value);
                input.value = String(qty);

                const itemCard = input.closest('[data-cart-item]');
                if (!itemCard) return;

                const unitPrice = Number.parseFloat(itemCard.getAttribute('data-unit-price') || '0');
                const lineTotal = qty * (Number.isFinite(unitPrice) ? unitPrice : 0);

                total += lineTotal;
                count += qty;

                const lineEl = itemCard.querySelector('.js-line-total');
                if (lineEl) {
                    lineEl.textContent = 'R$\u00A0' + formatBRL(lineTotal);
                }
            });

            if (subtotalEl) subtotalEl.textContent = 'R$\u00A0' + formatBRL(total);
            if (totalEl) totalEl.textContent = 'R$\u00A0' + formatBRL(total);
            if (countEl) countEl.textContent = String(count);
            if (countWordEl) countWordEl.textContent = count === 1 ? 'item' : 'itens';
        }

        document.querySelectorAll('[data-qty-action]').forEach((button) => {
            button.addEventListener('click', function () {
                const wrapper = this.closest('div');
                if (!wrapper) return;
                const input = wrapper.querySelector('[data-qty-input]');
                if (!input) return;

                const current = clampQty(input.value);
                const isMinus = this.getAttribute('data-qty-action') === 'minus';

                // If qty is 1 and user clicks minus, remove the item
                if (isMinus && current <= 1) {
                    const cartKey = input.getAttribute('data-cart-key') || '';
                    if (cartKey) {
                        window.location.href = '<?= BASE_PATH ?>/carrinho?remove=' + encodeURIComponent(cartKey);
                    }
                    return;
                }

                const next = isMinus ? current - 1 : current + 1;
                input.value = String(clampQty(next));
                recalcCart();
                saveQty(input, true);
            });
        });

        qtyInputs.forEach((input) => {
            input.addEventListener('input', () => {
                recalcCart();
                saveQty(input, false);
            });
            input.addEventListener('change', () => {
                recalcCart();
                saveQty(input, true);
            });
        });

        recalcCart();
    })();
</script>
<?php endif; ?>
