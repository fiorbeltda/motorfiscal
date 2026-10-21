$ErrorActionPreference = 'Stop'
$repoRoot = 'C:\xampp\htdocs\projeto-saas-motor'
$dockerExe = 'D:\Docker\DockerDesktop\resources\bin\docker.exe'
$desktopExe = 'D:\Docker\DockerDesktop\Docker Desktop.exe'
$receiptRoot = 'D:\ProjetoSaasExports\docker122-detached-relaunch-20261021-v1'
$preservationReport = 'D:\ProjetoSaasExports\container-preservation122-recovery-20261021-v3\container-preservation-validation.json'
$preservation = Get-Content -LiteralPath $preservationReport -Raw | ConvertFrom-Json
if (-not $preservation.all_inventoried_writable_entries_preserved -or $preservation.temporary_entries_verified -ne 11141) {
    throw 'Writable evidence must be preserved before restart'
}
if (Test-Path -LiteralPath $receiptRoot) { throw 'Exclusive restart receipt exists' }
Set-Location -LiteralPath $repoRoot
$allowed = @('projeto-saas-motor-app-1', 'projeto-saas-motor-web-1', 'projeto-saas-motor-postgres-1')
$running = @(& $dockerExe ps --format '{{.Names}}')
if ($LASTEXITCODE -ne 0 -or @($running | Where-Object { $_ -notin $allowed }).Count) {
    throw 'Do not restart Docker while unrelated containers are running'
}
$appProcesses = @(& $dockerExe top projeto-saas-motor-app-1 -eo pid,args)
if ($LASTEXITCODE -ne 0 -or @($appProcesses | Select-Object -Skip 1 | Where-Object { $_ -notmatch '^\s*\d+\s+php-fpm:' }).Count) {
    throw 'Active non-FPM application process; restart forbidden'
}
New-Item -ItemType Directory -Path $receiptRoot | Out-Null
[ordered]@{
    started_at = [DateTime]::UtcNow.ToString('o'); scoped_containers = $running
    app_processes = $appProcesses; preservation_report = $preservationReport
    preservation_report_sha256 = (Get-FileHash -LiteralPath $preservationReport -Algorithm SHA256).Hash.ToLowerInvariant()
} | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath (Join-Path $receiptRoot 'start.json') -Encoding utf8NoBOM
& $dockerExe compose stop --timeout 30 web app postgres
if ($LASTEXITCODE -ne 0) { throw 'Graceful project stop failed' }
& $dockerExe desktop stop --timeout 45
if ($LASTEXITCODE -ne 0) { throw 'Desktop stop did not complete; inspect before launch' }
# WMI's provider launches outside the executor's child-process/job tree.
# This is a one-time launch, not an installed scheduled task or startup service.
$startup = New-CimInstance -ClassName Win32_ProcessStartup -ClientOnly -Property @{ ShowWindow = [uint16]0 }
$created = Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{
    CommandLine = '"' + $desktopExe + '"'
    CurrentDirectory = 'D:\Docker\DockerDesktop'
    ProcessStartupInformation = $startup
}
if ($created.ReturnValue -ne 0) { throw "Detached Desktop launch failed: $($created.ReturnValue)" }
$launched = Get-CimInstance Win32_Process -Filter "ProcessId=$($created.ProcessId)"
[ordered]@{
    launched_at = [DateTime]::UtcNow.ToString('o'); method = 'Win32_Process.Create via WMI provider'
    hidden_startup_requested = $true; process_id = $created.ProcessId
    parent_process_id = $launched.ParentProcessId; executable = $desktopExe
    engine_ready_verified = $false; project_restarted = $false
    volumes_removed = $false; scheduled_task_installed = $false
} | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath (Join-Path $receiptRoot 'desktop-launched.json') -Encoding utf8NoBOM
Write-Output 'Desktop launch dispatched outside executor; verify engine readiness and start existing project containers next'
