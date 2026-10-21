param(
    [switch]$Execute,
    [string]$PreservationReport = 'D:\ProjetoSaasExports\container-preservation122-recovery-20261021-v3\container-preservation-validation.json'
)

$ErrorActionPreference = 'Stop'
$repoRoot = 'C:\xampp\htdocs\projeto-saas-motor'
$sourceRoot = Join-Path $repoRoot 'storage\app'
$destinationRoot = 'D:\ProjetoSaasExports\preserved-storage-app'
$receiptRoot = 'D:\ProjetoSaasExports\storage-relocation122-20261021-v1'
$dockerExe = 'D:\Docker\DockerDesktop\resources\bin\docker.exe'
$historicalReport = 'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021\historical-replay-v1\credit122-historical-validation.json'
$names = @(
    'phase4b-private-consolidation48-draft-20261009-28',
    'phase4b-realization-consolidated-expansion47-draft-20261009-28'
)

function Assert-ChildPath([string]$Path, [string]$Parent) {
    $absolute = [IO.Path]::GetFullPath($Path).TrimEnd('\')
    $boundary = [IO.Path]::GetFullPath($Parent).TrimEnd('\') + '\'
    if (-not $absolute.StartsWith($boundary, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Unsafe target outside intended directory: $absolute"
    }
    return $absolute
}

function Save-NewJson([string]$Path, $Value) {
    if (Test-Path -LiteralPath $Path) { throw "Exclusive receipt already exists: $Path" }
    $Value | ConvertTo-Json -Depth 30 | Set-Content -LiteralPath $Path -Encoding utf8NoBOM
}

function Inventory([string]$Root) {
    $base = (Get-Item -LiteralPath $Root).FullName.TrimEnd('\')
    $entries = @(Get-ChildItem -LiteralPath $base -Force -Recurse)
    if (@($entries | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }).Count) {
        throw "Nested reparse point is not permitted: $Root"
    }
    $files = @($entries | Where-Object { -not $_.PSIsContainer } | Sort-Object FullName | ForEach-Object {
        [ordered]@{
            relative_path = $_.FullName.Substring($base.Length + 1).Replace('\', '/')
            bytes = $_.Length
            sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        }
    })
    $directories = @($entries | Where-Object PSIsContainer | ForEach-Object {
        $_.FullName.Substring($base.Length + 1).Replace('\', '/')
    } | Sort-Object)
    return [ordered]@{ files = $files; directories = $directories }
}

function Assert-SameInventory($Expected, $Actual) {
    if (($Expected | ConvertTo-Json -Depth 10 -Compress) -cne ($Actual | ConvertTo-Json -Depth 10 -Compress)) {
        throw 'Inventory mismatch; original data must remain recoverable'
    }
}

$plan = @($names | ForEach-Object {
    $source = Assert-ChildPath (Join-Path $sourceRoot $_) $sourceRoot
    $destination = Assert-ChildPath (Join-Path $destinationRoot $_) $destinationRoot
    $backup = Assert-ChildPath ($source + '.relocation122-original') $sourceRoot
    $item = Get-Item -LiteralPath $source -Force
    if (-not $item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw "Source is not an ordinary directory: $source"
    }
    if ((Test-Path -LiteralPath $destination) -or (Test-Path -LiteralPath $backup)) {
        throw "Destination or recovery path already exists: $_"
    }
    [ordered]@{ name = $_; source = $source; destination = $destination; recovery_path = $backup }
})
if (-not $Execute) {
    $plan | ConvertTo-Json -Depth 10
    Write-Output 'PLAN ONLY: no files copied, moved or removed. Execution requires a terminal historical report.'
    exit 0
}

if (-not (Test-Path -LiteralPath $historicalReport)) { throw 'Historical collector is not terminal; migration forbidden' }
$historical = Get-Content -LiteralPath $historicalReport -Raw | ConvertFrom-Json
if ($historical.status -ne 'CREDIT122_ALL_ACCEPTED119_AND_PRIOR_REPLAYS_EXACT' -or
    $historical.historical_exact_replays -ne 1441 -or -not $historical.input_inventory_unchanged) {
    throw 'Historical terminal receipt did not pass all gates'
}
if (Test-Path -LiteralPath $receiptRoot) { throw 'Exclusive migration attempt already exists' }
New-Item -ItemType Directory -Path $receiptRoot | Out-Null
New-Item -ItemType Directory -Path $destinationRoot -Force | Out-Null
Set-Location -LiteralPath $repoRoot
$gitBefore = @(& git status --porcelain=v1 --untracked-files=normal)
$cFreeBefore = (Get-PSDrive C).Free
Save-NewJson (Join-Path $receiptRoot 'start.json') ([ordered]@{
    started_at = [DateTime]::UtcNow.ToString('o'); authorization = 'User approved preservation on D: with C: junctions'
    historical_report = $historicalReport
    historical_report_sha256 = (Get-FileHash -LiteralPath $historicalReport -Algorithm SHA256).Hash.ToLowerInvariant()
    c_free_before = $cFreeBefore; plan = $plan; git_status_before = $gitBefore
})
if (-not (Test-Path -LiteralPath $preservationReport)) { throw 'Preserve old container before changing mounts' }
$preservation = Get-Content -LiteralPath $preservationReport -Raw | ConvertFrom-Json
if ($preservation.status -notin @('CONTAINER_ROOTFS_PRESERVED_WITH_TEMPORARY_EVIDENCE_HASHES', 'CONTAINER_WRITABLE_EVIDENCE_PRESERVED_FROM_RECOVERED_ARCHIVES')) {
    throw 'Old container preservation is not verified'
}
$currentContainer = (& $dockerExe compose ps -q app).Trim()
if ($LASTEXITCODE -ne 0 -or $currentContainer -ne $preservation.container) {
    throw 'Container changed after preservation; inspect before recreating'
}
$currentImage = (& $dockerExe inspect --format '{{.Image}}' $currentContainer).Trim()
if ($LASTEXITCODE -ne 0 -or -not $currentImage) { throw 'Cannot identify the retained base image' }
if ($preservation.status -eq 'CONTAINER_WRITABLE_EVIDENCE_PRESERVED_FROM_RECOVERED_ARCHIVES') {
    if (-not $preservation.all_inventoried_writable_entries_preserved -or
        -not $preservation.base_image_still_required_and_retained -or
        $preservation.temporary_entries_verified -ne 11141 -or
        $currentImage -ne $preservation.image_sha256) { throw 'Recovered writable evidence closure failed' }
    foreach ($archive in $preservation.archives) {
        if ((Get-Item -LiteralPath $archive.path).Length -ne $archive.bytes) { throw 'Preserved archive size changed' }
    }
    if ((Get-FileHash -LiteralPath $preservation.temporary_inventory -Algorithm SHA256).Hash.ToLowerInvariant() -ne $preservation.temporary_inventory_sha256) {
        throw 'Preserved inventory hash changed'
    }
}
$composeJson = & $dockerExe compose config --format json
if ($LASTEXITCODE -ne 0) { throw 'Compose configuration is invalid' }
$composeConfig = $composeJson | ConvertFrom-Json
foreach ($serviceName in @('app', 'web')) {
    foreach ($target in $plan) {
        $expectedTarget = '/var/www/html/storage/app/' + $target.name
        $mounts = @($composeConfig.services.$serviceName.volumes | Where-Object target -eq $expectedTarget)
        if ($mounts.Count -ne 1 -or -not $mounts[0].read_only -or
            $mounts[0].source.Replace('/', '\') -ne $target.destination -or $mounts[0].bind.create_host_path) {
            throw "Configured mount does not match the preserved destination: $serviceName $expectedTarget"
        }
    }
}
$inventories = @{}
foreach ($target in $plan) {
    Write-Output ('INVENTORY:' + $target.name)
    $before = Inventory $target.source
    $inventories[$target.name] = $before
    $manifest = Join-Path $receiptRoot ($target.name + '-inventory.json')
    Save-NewJson $manifest $before
    Write-Output ('COPY:' + $target.name)
    & robocopy $target.source $target.destination /E /COPY:DAT /DCOPY:DAT /XJ /J /R:1 /W:1 /NFL /NDL /NP /NJH /NJS
    if ($LASTEXITCODE -gt 7) { throw "Robocopy failed: $LASTEXITCODE" }
    Assert-SameInventory $before (Inventory $target.destination)
    Assert-SameInventory $before (Inventory $target.source)
    Save-NewJson (Join-Path $receiptRoot ($target.name + '-copy-verified.json')) ([ordered]@{
        verified_at = [DateTime]::UtcNow.ToString('o'); source = $target.source; destination = $target.destination
        inventory_sha256 = (Get-FileHash -LiteralPath $manifest -Algorithm SHA256).Hash.ToLowerInvariant()
        files = $before.files.Count; bytes = ($before.files | ForEach-Object { $_.bytes } | Measure-Object -Sum).Sum
    })
}
# Apply explicit read-only D: mounts only after both copies are complete.
# All writable evidence is verified; a recovered partial export is not called a full rootfs.
& $dockerExe compose up -d --no-deps --no-build --pull never app web
if ($LASTEXITCODE -ne 0) { throw 'Mount recreation failed; all original C: copies remain untouched' }
$container = (& $dockerExe compose ps -q app).Trim()
if ($LASTEXITCODE -ne 0 -or -not $container) { throw 'Application container unavailable after mount change' }
$replacementImage = (& $dockerExe inspect --format '{{.Image}}' $container).Trim()
if ($LASTEXITCODE -ne 0 -or $replacementImage -ne $currentImage) {
    throw 'Replacement image differs; all original C: copies remain intact'
}
$php = '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);$root=$argv[2];foreach($m["files"] as $f){$p=$root."/".$f["relative_path"];if(!is_file($p)||filesize($p)!==$f["bytes"]||hash_file("sha256",$p)!==$f["sha256"]){fwrite(STDERR,"MISMATCH:".$p.PHP_EOL);exit(1);}}foreach($m["directories"] as $d){if(!is_dir($root."/".$d)){exit(2);}}echo "PASS:container-evidence ".count($m["files"]).PHP_EOL;'
foreach ($target in $plan) {
    $manifest = Join-Path $receiptRoot ($target.name + '-inventory.json')
    $remoteManifest = '/tmp/relocation122-' + $target.name + '.json'
    & $dockerExe cp $manifest ($container + ':' + $remoteManifest)
    if ($LASTEXITCODE -ne 0) { throw 'Unable to copy hash manifest to container; originals intact' }
    & $dockerExe compose exec -T app php -r $php $remoteManifest ('/var/www/html/storage/app/' + $target.name)
    if ($LASTEXITCODE -ne 0) { throw 'Container hash verification failed; originals intact' }
}
$completed = @()
foreach ($target in $plan) {
    $before = $inventories[$target.name]
    $manifest = Join-Path $receiptRoot ($target.name + '-inventory.json')
    $renamed = $false
    $junctionCreated = $false
    $recoveryRemovalStarted = $false
    try {
        Assert-SameInventory $before (Inventory $target.source)
        Move-Item -LiteralPath $target.source -Destination $target.recovery_path
        $renamed = $true
        New-Item -ItemType Junction -Path $target.source -Target $target.destination | Out-Null
        $junctionCreated = $true
        $link = Get-Item -LiteralPath $target.source -Force
        if ($link.LinkType -ne 'Junction' -or $link.Target -ne $target.destination) { throw 'Junction target differs' }
        Assert-SameInventory $before (Inventory $target.source)
        $validatedBackup = Assert-ChildPath $target.recovery_path $sourceRoot
        if ($validatedBackup -cne $target.recovery_path -or
            (Get-Item -LiteralPath $validatedBackup).Attributes -band [IO.FileAttributes]::ReparsePoint) {
            throw 'Recovery directory failed final safety gate'
        }
        Save-NewJson (Join-Path $receiptRoot ($target.name + '-junction-verified.json')) ([ordered]@{
            verified_at = [DateTime]::UtcNow.ToString('o'); source = $target.source; destination = $target.destination
            host_all_hashes_match = $true; container_all_hashes_match = $true
            container_access = 'explicit read-only D: bind mount'; container_id = $container
            original_c_copy_recoverable_from = $target.destination
        })
        $recoveryRemovalStarted = $true
        Remove-Item -LiteralPath $validatedBackup -Recurse -Force
        $renamed = $false
        $completed += [ordered]@{
            name = $target.name; source = $target.source; destination = $target.destination
            files = $before.files.Count; bytes = ($before.files | ForEach-Object { $_.bytes } | Measure-Object -Sum).Sum
            inventory = $manifest
            inventory_sha256 = (Get-FileHash -LiteralPath $manifest -Algorithm SHA256).Hash.ToLowerInvariant()
            c_duplicate_removed = $true; container_read_only_mount_verified = $true
        }
        Write-Output ('PRESERVED_ON_D:' + $target.name)
    } catch {
        $failure = $_
        if ($renamed -and -not $recoveryRemovalStarted -and (Test-Path -LiteralPath $target.recovery_path)) {
            if ($junctionCreated) { [IO.Directory]::Delete($target.source) }
            Move-Item -LiteralPath $target.recovery_path -Destination $target.source
        }
        Save-NewJson (Join-Path $receiptRoot ($target.name + '-failure.json')) ([ordered]@{
            failed_at = [DateTime]::UtcNow.ToString('o'); error = $failure.ToString()
            source_exists = (Test-Path -LiteralPath $target.source); destination_preserved = $target.destination
        })
        throw $failure
    }
}
$gitAfter = @(& git status --porcelain=v1 --untracked-files=normal)
if (($gitBefore -join "`n") -cne ($gitAfter -join "`n")) { throw 'Git status changed; inspect before any further action' }
$result = [ordered]@{
    completed_at = [DateTime]::UtcNow.ToString('o'); status = 'PRESERVED_ON_D_WITH_C_JUNCTIONS'
    c_free_before = $cFreeBefore; c_free_after = (Get-PSDrive C).Free
    completed = $completed; history_deleted = $false; container_preservation_report = $preservationReport
    git_status_unchanged = $true; receipts = $receiptRoot
}
Save-NewJson (Join-Path $receiptRoot 'relocation-validation.json') $result
Save-NewJson (Join-Path $sourceRoot 'evidence-storage-index-20261021.json') $result
Write-Output 'PASS:preservation, host/container hashes, C: junctions and Git status verified'
