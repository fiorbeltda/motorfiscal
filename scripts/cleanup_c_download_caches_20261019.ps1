$ErrorActionPreference = 'Stop'
$reportRoot = 'D:\ProjetoSaasExports\cleanup-c-download-caches-20261019-v1'
if (Test-Path -LiteralPath $reportRoot) { throw 'Exclusive report directory already exists' }
New-Item -ItemType Directory -Path $reportRoot | Out-Null
$started = [DateTimeOffset]::Now.ToString('o')
$before = (Get-PSDrive C).Free
$cutoff = (Get-Date).AddHours(-48)
$targets = @(
    'C:\Users\gabriel.oliveira\AppData\Local\electron\Cache',
    'C:\Users\gabriel.oliveira\AppData\Local\Microsoft\Edge\User Data\Default\Cache',
    'C:\Users\gabriel.oliveira\AppData\Local\Microsoft\Windows\INetCache',
    'C:\Users\gabriel.oliveira\AppData\Local\SquirrelTemp'
)
$records = [System.Collections.Generic.List[object]]::new()
$skippedRecent = 0
$skippedProtected = 0
foreach ($target in $targets) {
    if (-not (Test-Path -LiteralPath $target -PathType Container)) { continue }
    $resolved = (Resolve-Path -LiteralPath $target).ProviderPath.TrimEnd('\')
    if ($resolved -cne $target) { throw "Unexpected resolved target: $resolved" }
    $ancestor = Get-Item -LiteralPath $resolved -Force
    while ($null -ne $ancestor) {
        if ($ancestor.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Reparse ancestor: $resolved" }
        $ancestor = $ancestor.Parent
    }
    $queue = [System.Collections.Generic.Queue[string]]::new()
    $queue.Enqueue($resolved)
    while ($queue.Count -gt 0) {
        $directory = $queue.Dequeue()
        foreach ($item in @(Get-ChildItem -LiteralPath $directory -Force -ErrorAction SilentlyContinue)) {
            if (-not $item.FullName.StartsWith($resolved + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Containment failure' }
            if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { $skippedProtected++; continue }
            if ($item.PSIsContainer) { $queue.Enqueue($item.FullName); continue }
            if ($item.Extension -in @('.vhd', '.vhdx', '.avhdx', '.vmdk', '.iso')) { $skippedProtected++; continue }
            if ($item.LastWriteTime -ge $cutoff) { $skippedRecent++; continue }
            $record = [ordered]@{ path = $item.FullName; bytes = $item.Length; status = 'SKIPPED'; backup = $null; sha256 = $null; reason = $null }
            try {
                $probe = [IO.File]::Open($item.FullName, [IO.FileMode]::Open, [IO.FileAccess]::Read, [IO.FileShare]::None)
                $probe.Dispose()
                $backup = Join-Path $reportRoot ('recoverable\' + $item.FullName.Substring(3))
                New-Item -ItemType Directory -Path ([IO.Path]::GetDirectoryName($backup)) -Force | Out-Null
                Copy-Item -LiteralPath $item.FullName -Destination $backup
                $sourceHash = (Get-FileHash -LiteralPath $item.FullName -Algorithm SHA256).Hash
                if ($sourceHash -ne (Get-FileHash -LiteralPath $backup -Algorithm SHA256).Hash) { throw 'Backup hash mismatch' }
                $current = Get-Item -LiteralPath $item.FullName -Force
                if ($current.Length -ne $item.Length -or $current.LastWriteTimeUtc -ne $item.LastWriteTimeUtc) { throw 'File changed during backup' }
                Remove-Item -LiteralPath $item.FullName -Force -ErrorAction Stop
                $record.status = 'REMOVED_VERIFIED_BACKUP'
                $record.backup = $backup
                $record.sha256 = $sourceHash
            } catch { $record.reason = $_.Exception.Message }
            $records.Add([pscustomobject]$record)
        }
    }
}
$removed = @($records | Where-Object status -eq 'REMOVED_VERIFIED_BACKUP')
$report = [ordered]@{
    started_at = $started; finished_at = [DateTimeOffset]::Now.ToString('o'); cutoff = $cutoff.ToString('o')
    targets = $targets; free_before_bytes = $before; free_after_bytes = (Get-PSDrive C).Free
    removed_files = $removed.Count; removed_logical_bytes = ($removed | Measure-Object bytes -Sum).Sum
    skipped_candidates = @($records | Where-Object status -eq 'SKIPPED').Count
    skipped_recent = $skippedRecent; skipped_protected = $skippedProtected
    note = 'Only files older than 48 hours; SHA256-verified recoverable copies on D. No recursive deletion. Virtual disks, reparse points, recent/locked files, fiscal project/evidence and runtime environments preserved. Free space delta includes concurrent OS activity.'
    records = @($records.ToArray())
}
$report | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $reportRoot 'cleanup-report.json') -Encoding UTF8
$report.Remove('records')
$report | ConvertTo-Json -Depth 5


