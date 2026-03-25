# Recuperação de Banco no XAMPP (MariaDB)

## Diagnóstico do seu erro atual

Se o MySQL mostra as tabelas no `SHOW TABLES`, mas ao consultar retorna:

`ERROR 1932 (42S02): Table '...table...' doesn't exist in engine`

isso indica, na prática, perda de metadados do InnoDB após reinstalação (normalmente quando só a pasta do banco é copiada, sem os arquivos globais corretos da instância original).

## Verificação rápida

No PowerShell:

`powershell -ExecutionPolicy Bypass -File .\scripts\db_health_check.ps1`

## Exportar backups (schema + dados legíveis)

`powershell -ExecutionPolicy Bypass -File .\scripts\db_backup.ps1`

Saída padrão em `backups/`:

- `mercado_admin_schema_YYYYMMDD_HHMMSS.sql`
- `mercado_admin_data_YYYYMMDD_HHMMSS.sql`

Se houver tabela quebrada, ela é ignorada no dump de dados e listada no final.

## Restaurar em outro banco/servidor

`powershell -ExecutionPolicy Bypass -File .\scripts\db_restore.ps1 -SchemaFile "C:\caminho\schema.sql" -DataFile "C:\caminho\data.sql" -Database "mercado_admin_novo"`

## Recuperação dos dados antigos (cenário atual)

Com erro 1932, há duas possibilidades:

1. **Você tem backup da pasta inteira do `data` original** (incluindo `ibdata1`, `ib_logfile*` e pasta do banco):
   - maior chance de recuperar tudo ao restaurar a instância completa em ambiente compatível.
2. **Você só tem `.frm` e `.ibd` da pasta do banco**:
   - recuperação completa é incerta e exige procedimento avançado de import de tablespace por tabela.

Sem os arquivos globais corretos do InnoDB do ambiente antigo, é comum não conseguir reabrir os dados.

## Subir o sistema com banco limpo (recomendado no seu cenário)

1. Recriar schema completo:

`powershell -ExecutionPolicy Bypass -File .\scripts\db_init_clean.ps1 -DropDatabaseFirst`

2. Criar um admin inicial:

`php .\scripts\create_admin.php "Seu Nome" email@dominio.com "SenhaForte123"`

3. Validar saúde das tabelas:

`powershell -ExecutionPolicy Bypass -File .\scripts\db_health_check.ps1`

Com isso, o sistema volta a abrir normalmente (sem os dados antigos).
