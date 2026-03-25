# Teste de webhook BlackCat no Postman

## Endpoint

**Local:**
```
POST http://localhost/mercado_admin/public/webhooks/blackcat.php
```

**Produção (Railway):**
```
POST https://SEU-DOMINIO.railway.app/webhooks/blackcat.php
```

---

## Headers OBRIGATÓRIOS

| Header | Valor |
|--------|-------|
| `Content-Type` | `application/json` |

> **IMPORTANTE:** O header `Content-Type: application/json` é obrigatório. Sem ele, o PHP pode não conseguir ler o body.

---

## Como configurar no Postman

1. Método: **POST**
2. URL: cole o endpoint acima
3. Aba **Headers**: adicione `Content-Type` = `application/json`
4. Aba **Body**: selecione **raw** e escolha **JSON** no dropdown à direita
5. Cole o payload abaixo no body

> ⚠️ **NÃO** use `form-data` nem `x-www-form-urlencoded`. Use apenas **raw JSON**.

---

## Payload de teste: Pagamento de pedido (transaction.paid)

```json
{
  "event": "transaction.paid",
  "timestamp": "2026-02-25T20:35:00.000Z",
  "transactionId": "TXN-TEST-POSTMAN-001",
  "externalReference": "order:1",
  "status": "PAID",
  "amount": 9990,
  "netAmount": 9691,
  "fees": 299,
  "paymentMethod": "PIX",
  "customer": {
    "name": "Teste Postman",
    "email": "teste@local"
  }
}
```

### Campos obrigatórios

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `event` | string | Tipo do evento. Use `transaction.paid` |
| `transactionId` | string | ID único da transação (idempotência) |
| `externalReference` | string | `order:N` para pedidos, `wallet_topup:...` para recargas |
| `status` | string | Status do pagamento (ex: `PAID`) |
| `amount` | int | Valor em centavos |
| `timestamp` | string | Data/hora ISO 8601 |

---

## Resultado esperado (sucesso)

- HTTP `200`
- Body: `{"ok": true}`
- O pedido indicado em `externalReference` (ex: `order:1`) muda para status `pago`
- Código de entrega de 6 caracteres é gerado automaticamente
- `auto_release_at` é inicializado nos itens do pedido (padrão: 7 dias)

---

## Erros comuns e soluções

### ❌ "Payload inválido — não é um JSON válido"
**Causa:** O body não é JSON válido.
**Soluções:**
1. No Postman, selecione **Body > raw > JSON** (não form-data)
2. Verifique que o JSON não tem erros de sintaxe (vírgulas extras, aspas faltando)
3. Certifique-se que `Content-Type: application/json` está nos headers

### ❌ "Payload vazio"
**Causa:** Nenhum dado no body da requisição.
**Soluções:**
1. Verifique que colou o JSON no Body
2. Verifique que não está usando GET (precisa ser POST)
3. Se for via cURL: use `-d '{"event":...}'`

### ❌ "Campo event ausente"
**Causa:** O JSON não contém o campo `"event"`.
**Soluções:**
1. Adicione `"event": "transaction.paid"` no payload
2. Verifique se o JSON está correto (sem erro de parse)

### ❌ "Payload inválido" (genérico, antigo)
**Causa mais comum:** O gateway está enviando como `form-data` ou `x-www-form-urlencoded`.
**Soluções:**
1. Na config do gateway, certifique-se que o Content-Type é `application/json`
2. O body deve ser JSON puro (raw), não form-data
3. Confira se não há BOM (byte order mark) no início do payload

---

## Teste via cURL

```bash
curl -X POST http://localhost/mercado_admin/public/webhooks/blackcat.php \
  -H "Content-Type: application/json" \
  -d '{
    "event": "transaction.paid",
    "timestamp": "2026-02-25T20:35:00.000Z",
    "transactionId": "TXN-CURL-TEST-001",
    "externalReference": "order:1",
    "status": "PAID",
    "amount": 9990
  }'
```

## Teste via PowerShell

```powershell
$body = @{
    event = "transaction.paid"
    timestamp = "2026-02-25T20:35:00.000Z"
    transactionId = "TXN-PS-TEST-001"
    externalReference = "order:1"
    status = "PAID"
    amount = 9990
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost/mercado_admin/public/webhooks/blackcat.php" `
    -Method Post -ContentType "application/json" -Body $body
```

---

## Idempotência

Repetir o mesmo payload retorna sucesso sem duplicar processamento (SHA1 de event + transactionId + status + timestamp).

---

## Payload para testar recarga de carteira (wallet_topup)

```json
{
  "event": "transaction.paid",
  "timestamp": "2026-02-25T21:05:00.000Z",
  "transactionId": "TXN-WALLET-TEST-001",
  "externalReference": "wallet_topup:2:1771362300:test001",
  "status": "PAID",
  "amount": 1000,
  "netAmount": 970,
  "fees": 30,
  "paymentMethod": "PIX"
}
```

### Formato do externalReference para wallet_topup
```
wallet_topup:{user_id}:{timestamp}:{random_id}
```

---

## Logs de debug

Os webhooks são logados em `storage/logs/webhook_blackcat.log`.
Cada linha contém: timestamp | headers recebidos | body recebido.

### Como usar no front

- Gere a cobrança em Carteira > Adicionar saldo (isso abre o popup com QR e copia e cola)
- No Postman, envie o payload acima para o mesmo endpoint
- Volte no popup e clique em Atualizar status
- Quando estiver aprovado, aparece o check: "Pagamento aprovado. Saldo creditado."

Observação: para bater exatamente com a cobrança real, o ideal é usar o `external_ref` e `transactionId` da linha criada em `payment_transactions`.
