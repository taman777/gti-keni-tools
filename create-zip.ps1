$pluginName = "gti-keni-tools"
$zipFileName = "$pluginName.zip"
$excludeItems = @(".git", ".gitignore", ".vscode", ".DS_Store", "create-zip.ps1", "*.zip", "*.code-workspace", "node_modules", "tests")

Write-Host "Creating ZIP package for $pluginName..."

# Remove existing zip if it exists
if (Test-Path $zipFileName) {
    Remove-Item $zipFileName
    Write-Host "Removed existing $zipFileName"
}

# Create a temporary directory for staging
$tempDir = Join-Path $env:TEMP $pluginName
if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $tempDir | Out-Null

# Copy files to temp directory
$items = Get-ChildItem -Path .
foreach ($item in $items) {
    $shouldExclude = $false
    foreach ($exclude in $excludeItems) {
        if ($item.Name -like $exclude) {
            $shouldExclude = $true
            break
        }
    }

    if (-not $shouldExclude) {
        Copy-Item -Path $item.FullName -Destination $tempDir -Recurse
    }
}

# Create ZIP file
$destinationPath = Join-Path $PWD $zipFileName
Compress-Archive -Path "$tempDir\*" -DestinationPath $destinationPath

# Cleanup
Remove-Item $tempDir -Recurse -Force

Write-Host "Successfully created $zipFileName"
