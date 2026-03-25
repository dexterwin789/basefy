param(
    [string]$MysqlBin = "c:\xampp\mysql\bin",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "",
    [string]$MigrationFile = "c:\xampp\htdocs\mercado_admin\sql\wallet_migration.sql"
)

$ErrorActionPreference = "Stop"

$mysqlExe = Join-Path $MysqlBin "mysql.exe"
if (!(Test-Path $mysqlExe)) {
    throw "mysql.exe não encontrado em: $mysqlExe"
}
if (!(Test-Path $MigrationFile)) {
    throw "Migration não encontrada: $MigrationFile"
}

$args = @("-h", $DbHost, "-P", "$Port", "-u", $User)
if ($Password -ne "") {
    $args += "-p$Password"
}

Write-Host "Aplicando migração de wallet..." -ForegroundColor Cyan
Get-Content -Path $MigrationFile -Raw | & $mysqlExe @args
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao aplicar migração"
}

Write-Host "Migração wallet aplicada com sucesso." -ForegroundColor Green
