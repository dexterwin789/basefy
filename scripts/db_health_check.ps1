param(
    [string]$MysqlBin = "c:\xampp\mysql\bin",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$User = "root",
    [string]$Password = "",
    [string]$Database = "mercado_admin"
)

$ErrorActionPreference = "Stop"

function Invoke-Mysql {
    param([string]$Query)

    $mysqlExe = Join-Path $MysqlBin "mysql.exe"
    if (!(Test-Path $mysqlExe)) {
        throw "mysql.exe não encontrado em: $mysqlExe"
    }

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

Write-Host "Verificando conectividade no banco '$Database'..." -ForegroundColor Cyan
Invoke-Mysql "SELECT 1;" | Out-Null

$tables = Invoke-Mysql "SHOW TABLES;"
if (!$tables -or $tables.Count -eq 0) {
    Write-Host "Nenhuma tabela encontrada em '$Database'." -ForegroundColor Yellow
    exit 0
}

$healthy = @()
$broken = @()

foreach ($table in $tables) {
    $tableName = $table.Trim()
    if ($tableName -eq "") {
        continue
    }

    try {
        Invoke-Mysql "SELECT 1 FROM $tableName LIMIT 1;" | Out-Null
        $healthy += $tableName
        Write-Host "[OK] $tableName" -ForegroundColor Green
    }
    catch {
        $broken += [PSCustomObject]@{
            Table = $tableName
            Error = $_.Exception.Message
        }
        Write-Host "[ERRO] $tableName" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Resumo:" -ForegroundColor Cyan
Write-Host "- Tabelas saudáveis: $($healthy.Count)"
Write-Host "- Tabelas com erro: $($broken.Count)"

if ($broken.Count -gt 0) {
    Write-Host ""
    Write-Host "Tabelas com erro:" -ForegroundColor Yellow
    $broken | Format-Table -AutoSize
    exit 2
}

exit 0
