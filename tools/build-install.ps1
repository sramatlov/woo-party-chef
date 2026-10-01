$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$pluginRoot = Join-Path $projectRoot 'woo-party-chef'
$bootstrap = Get-Content -LiteralPath (Join-Path $pluginRoot 'woo-party-chef.php') -Raw
if ($bootstrap -notmatch "define\( 'WOOPC_VERSION', '([^']+)' \)") {
    throw 'Plugin version was not found.'
}
$pluginVersion = $Matches[1]
$packagePath = Join-Path $projectRoot "woo-party-chef-v$pluginVersion-install.zip"
Add-Type -AssemblyName System.IO.Compression.FileSystem
$fileStream = [System.IO.File]::Open($packagePath, [System.IO.FileMode]::Create)
$archive = [System.IO.Compression.ZipArchive]::new($fileStream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -LiteralPath $pluginRoot -File -Recurse | ForEach-Object {
        $relativePath = $_.FullName.Substring($pluginRoot.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, "woo-party-chef/$relativePath") | Out-Null
    }
}
finally {
    $archive.Dispose()
    $fileStream.Dispose()
}
Get-Item -LiteralPath $packagePath | Select-Object FullName,Length
