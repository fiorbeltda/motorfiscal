param(
    [Parameter(Mandatory=$true)][string]$Uri,
    [Parameter(Mandatory=$true)][string]$OutputPath
)
$ErrorActionPreference = 'Stop'
if (Test-Path -LiteralPath $OutputPath) { throw 'Exclusive capture file already exists' }
# Native Windows trust validation remains enabled. No certificate bypass callback.
$response = Invoke-WebRequest -Uri $Uri -TimeoutSec 40 -MaximumRedirection 10
if ($response.StatusCode -ne 200) { throw 'Source did not return HTTP 200' }
$stream = [System.IO.File]::Open($OutputPath, [System.IO.FileMode]::CreateNew)
try {
    $response.RawContentStream.Position = 0
    $response.RawContentStream.CopyTo($stream)
} finally { $stream.Dispose() }
[pscustomobject]@{
    final_url = $response.BaseResponse.RequestMessage.RequestUri.AbsoluteUri
    content_type = [string]$response.BaseResponse.Content.Headers.ContentType
    encoding = $response.BaseResponse.Content.Headers.ContentType.CharSet
    tls_validation = 'WINDOWS_DEFAULT_ENABLED'
} | ConvertTo-Json -Compress
