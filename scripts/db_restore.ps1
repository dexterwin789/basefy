param(
    [Parameter(Mandatory = $true)]
    [string]$SchemaFile,
    [string]$DataFile = "",
    [string]$MysqlBin = "c:\xampp\mysql\bin",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "",
    [string]$Database = "mercado_admin"
)

$ErrorActionPreference = "Stop"

$mysqlExe = Join-Path $MysqlBin "mysql.exe"
if (!(Test-Path $mysqlExe)) {
    throw "mysql.exe não encontrado em: $mysqlExe"
}

if (!(Test-Path $SchemaFile)) {
    throw "Arquivo de schema não encontrado: $SchemaFile"
}

if ($DataFile -ne "" -and !(Test-Path $DataFile)) {
    throw "Arquivo de dados não encontrado: $DataFile"
}

$args = @("-h", $DbHost, "-P", "$Port", "-u", $User)
if ($Password -ne "") {
    $args += "-p$Password"
}

Write-Host "Garantindo que o banco '$Database' exista..." -ForegroundColor Cyan
$createDbSql = "CREATE DATABASE IF NOT EXISTS $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& $mysqlExe @args -e $createDbSql
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao criar/validar banco"
}

Write-Host "Importando schema: $SchemaFile" -ForegroundColor Green
Get-Content -Path $SchemaFile -Raw | & $mysqlExe @args
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao importar schema"
}

if ($DataFile -ne "") {
    Write-Host "Importando dados: $DataFile" -ForegroundColor Green
    Get-Content -Path $DataFile -Raw | & $mysqlExe @args $Database
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao importar dados"
    }
}

Write-Host "Restore concluído com sucesso." -ForegroundColor Cyan
