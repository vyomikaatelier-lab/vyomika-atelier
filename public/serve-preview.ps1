# VYOMIKA ATELIER — local preview server (Node)
$port = 8765
Set-Location $PSScriptRoot
$url = "http://127.0.0.1:$port/preview.html"
Write-Host ""
Write-Host "  Vyomika Atelier LLP preview: $url"
Write-Host "  Press Ctrl+C to stop"
Write-Host ""
Start-Process $url
node serve-preview.js
