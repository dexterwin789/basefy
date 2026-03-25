param(
    [string]$MysqlBin = "c:\xampp\mysql\bin",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "",
    [string]$Database = "mercado_admin",
    [string]$OutputDir = "c:\xampp\htdocs\mercado_admin\backups"
)

$ErrorActionPreference = "Stop"

function Invoke-Mysql {
    param([string]$Query)

    $mysqlExe = Join-Path $MysqlBin "mysql.exe"
    $args = @("-h", $DbHost, "-P", "$Port", "-u", $User)
    if ($Password -ne "") {
        $args += "-p$Password"
    }
    $args += @("-D", $Database, "-N", "-e", $Query)

    $output = & $mysqlExe @args 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ($output -join "`n")
    }

    return $output
}

if (!(Test-Path $OutputDir)) {
    New-Item -Path $OutputDir -ItemType Directory | Out-Null
}

$mysqldumpExe = Join-Path $MysqlBin "mysqldump.exe"
if (!(Test-Path $mysqldumpExe)) {
    throw "mysqldump.exe não encontrado em: $mysqldumpExe"
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$schemaFile = Join-Path $OutputDir "${Database}_schema_${timestamp}.sql"
$dataFile = Join-Path $OutputDir "${Database}_data_${timestamp}.sql"

Write-Host "Coletando tabelas e validando leitura..." -ForegroundColor Cyan
$tables = Invoke-Mysql "SHOW TABLES;"

$healthyTables = @()
$brokenTables = @()

foreach ($table in $tables) {
    $tableName = $table.Trim()
    if ($tableName -eq "") {
        continue
    }

    try {
        Invoke-Mysql "SELECT 1 FROM $tableName LIMIT 1;" | Out-Null
        $healthyTables += $tableName
    }
    catch {
        $brokenTables += $tableName
    }
}

$baseArgs = @("-h", $DbHost, "-P", "$Port", "-u", $User)
if ($Password -ne "") {
    $baseArgs += "-p$Password"
}

Write-Host "Gerando backup de schema em: $schemaFile" -ForegroundColor Green
$schemaDumpSucceeded = $false

if ($healthyTables.Count -gt 0) {
    $schemaArgs = $baseArgs + @("--skip-lock-tables", "--routines", "--triggers", "--no-data", $Database) + $healthyTables
    & $mysqldumpExe @schemaArgs | Out-File -FilePath $schemaFile -Encoding utf8
    if ($LASTEXITCODE -eq 0) {
        $schemaDumpSucceeded = $true
    }
}

if (-not $schemaDumpSucceeded) {
    $minimalSchema = @(
        "-- Backup parcial gerado por db_backup.ps1"
        "-- Não foi possível extrair CREATE TABLE das tabelas atuais."
        "CREATE DATABASE IF NOT EXISTS `$Database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        "USE `$Database`;"
    )
    $minimalSchema -join "`r`n" | Out-File -FilePath $schemaFile -Encoding utf8
}

if ($healthyTables.Count -eq 0) {
    Write-Host "Nenhuma tabela saudável para exportar dados." -ForegroundColor Yellow
    Write-Host "Arquivo de schema mínimo gerado."
    exit 2
}

Write-Host "Gerando backup de dados em: $dataFile" -ForegroundColor Green
$dataArgs = $baseArgs + @("--single-transaction", "--quick", "--no-create-info", $Database) + $healthyTables
& $mysqldumpExe @dataArgs | Out-File -FilePath $dataFile -Encoding utf8
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao gerar dump de dados"
}

Write-Host ""
Write-Host "Backup concluído." -ForegroundColor Cyan
Write-Host "- Schema: $schemaFile"
Write-Host "- Dados:  $dataFile"

if ($brokenTables.Count -gt 0) {
    Write-Host ""
    Write-Host "Aviso: tabelas ignoradas por erro de leitura:" -ForegroundColor Yellow
    $brokenTables | ForEach-Object { Write-Host "- $_" }
    exit 2
}

exit 0
