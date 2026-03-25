# Wallet Escrow + BlackCat (base)

## O que foi implementado

- Escrow com liberação manual pelo comprador (`Confirmar entrega`) e automática por prazo.
- Regras configuráveis no admin (`/public/admin/wallet_config.php`):
  - Dias para auto-liberação
  - Taxa da plataforma (%)
  - Admin recebedor da taxa
  - Liga/desliga auto-liberação
- Webhook BlackCat (`/public/webhooks/blackcat.php`) com idempotência.
- Cliente BlackCat em `src/blackcat_api.php`.
- Endpoint para criar cobrança PIX de pedido: `POST /public/api/blackcat_create_sale.php`.
- Job CLI para auto-liberação: `php scripts/process_wallet_auto_release.php`.

## Configuração

1. Configure sua API key em `src/config.php`:

```php
const BLACKCAT_API_KEY = 'SUA_API_KEY';
```

2. Aplique migração de wallet:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\db_wallet_migrate.ps1
```

3. Configure as regras no painel admin:

`/mercado_admin/public/admin/wallet_config.php`

4. Agende o job de auto-liberação (Task Scheduler):

```bash
php c:\xampp\htdocs\mercado_admin\scripts\process_wallet_auto_release.php
```

Ou crie a tarefa automática diária com:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\register_wallet_auto_release_task.ps1
```

## Fluxo resumido

1. Compra cria cobrança PIX na BlackCat.
2. Webhook `transaction.paid` marca pedido como `pago` e prepara `auto_release_at`.
3. Comprador confirma entrega em `pedido_detalhes.php` para liberar na hora.
4. Se não confirmar, job automático libera após `N` dias.
5. Liberação aplica taxa, credita vendedor (líquido) e admin (taxa).

## Teste Postman (webhook)

Guia completo em `docs/postman-webhook-blackcat.md`.
