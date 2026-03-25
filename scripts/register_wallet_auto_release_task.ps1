param(
    [string]$TaskName = "MercadoAdmin-WalletAutoRelease",
    [string]$PhpExe = "c:\xampp\php\php.exe",
    [string]$ScriptPath = "c:\xampp\htdocs\mercado_admin\scripts\process_wallet_auto_release.php",
    [string]$DailyAt = "02:00"
)

$ErrorActionPreference = "Stop"

if (!(Test-Path $PhpExe)) {
    throw "PHP não encontrado em: $PhpExe"
}
if (!(Test-Path $ScriptPath)) {
    throw "Script não encontrado em: $ScriptPath"
}

$action = New-ScheduledTaskAction -Execute $PhpExe -Argument $ScriptPath
$trigger = New-ScheduledTaskTrigger -Daily -At $DailyAt
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Force | Out-Null

Write-Host "Tarefa agendada criada/atualizada: $TaskName" -ForegroundColor Green
Write-Host "Horário diário: $DailyAt"
Write-Host "Comando: $PhpExe $ScriptPath"
