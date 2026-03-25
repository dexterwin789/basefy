param(
    [string]$MysqlBin = "c:\xampp\mysql\bin",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "",
    [string]$Database = "mercado_admin",
    [string]$SchemaFile = "c:\xampp\htdocs\mercado_admin\sql\schema.sql",
    [switch]$DropDatabaseFirst
)

$ErrorActionPreference = "Stop"

$mysqlExe = Join-Path $MysqlBin "mysql.exe"
if (!(Test-Path $mysqlExe)) {
    throw "mysql.exe não encontrado em: $mysqlExe"
}

if (!(Test-Path $SchemaFile)) {
    throw "Schema não encontrado em: $SchemaFile"
}

$args = @("-h", $DbHost, "-P", "$Port", "-u", $User)
if ($Password -ne "") {
    $args += "-p$Password"
}

if ($DropDatabaseFirst.IsPresent) {
    Write-Host "Removendo banco '$Database' (reset completo)..." -ForegroundColor Yellow
    & $mysqlExe @args -e "DROP DATABASE IF EXISTS $Database;"
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao remover banco"
    }
}

Write-Host "Importando schema em banco limpo..." -ForegroundColor Cyan
Get-Content -Path $SchemaFile -Raw | & $mysqlExe @args
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao importar schema"
}

Write-Host "Banco inicializado com sucesso." -ForegroundColor Green
Write-Host "Próximo passo: criar o admin inicial com:"
Write-Host "php scripts/create_admin.php \"Seu Nome\" email@dominio.com \"SenhaForte123\""
