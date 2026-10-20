$ErrorActionPreference = 'Stop'
$target = 'C:\Users\gabriel.oliveira\AppData\Local\Temp\DockerDesktopUpdates\Docker Desktop Updater-239619 (242831).exe'
$destination = 'D:\ProjetoSaasExports\cleanup-c-docker-update-20261019-v1'
if (Test-Path -LiteralPath $destination) { throw 'Exclusive recovery directory already exists' }
$resolved = (Resolve-Path -LiteralPath $target).ProviderPath
if ($resolved -cne $target) { throw 'Unexpected target path' }
$item = Get-Item -LiteralPath $resolved -Force
$ancestor = $item.Directory
while ($null -ne $ancestor) {
    if ($ancestor.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Reparse ancestor not allowed' }
    $ancestor = $ancestor.Parent
}
if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Reparse file not allowed' }
$live = @(Get-CimInstance Win32_Process | Where-Object { $_.Name -eq $item.Name -or $_.ExecutablePath -eq $target })
if ($live.Count -gt 0) { throw 'The updater executable is running;preserve it' }
$probe = [IO.File]::Open($target, [IO.FileMode]::Open, [IO.FileAccess]::Read, [IO.FileShare]::None)
$probe.Dispose()
$before = (Get-PSDrive C).Free
New-Item -ItemType Directory -Path $destination | Out-Null
$backup = Join-Path $destination $item.Name
Copy-Item -LiteralPath $target -Destination $backup
$hash = (Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash
if ($hash -ne (Get-FileHash -LiteralPath $backup -Algorithm SHA256).Hash) { throw 'Recovery copy hash mismatch' }
$current = Get-Item -LiteralPath $target -Force
if ($current.Length -ne $item.Length -or $current.LastWriteTimeUtc -ne $item.LastWriteTimeUtc) { throw 'Updater cache changed during copy' }
Remove-Item -LiteralPath $target -Force
$report = [ordered]@{
    created_at = [DateTimeOffset]::Now.ToString('o')
    status = 'UNUSED_TEMP_INSTALLER_REMOVED_VERIFIED_RECOVERABLE_COPY'
    path = $target; backup = $backup; sha256 = $hash; removed_files = 1
    removed_logical_bytes = $item.Length; free_before_bytes = $before; free_after_bytes = (Get-PSDrive C).Free
    note = 'Only the explicitly resolved temporary installer. Installed Docker on D remains untouched;no updater process or exclusive file lock found. Pagefile,swapfile,virtual disks and fiscal evidence preserved. Free delta includes concurrent OS activity.'
}
$report | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $destination 'cleanup-report.json') -Encoding UTF8
$report | ConvertTo-Json -Depth 5
