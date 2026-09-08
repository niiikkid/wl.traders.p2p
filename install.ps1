#Requires -Version 5.1
[CmdletBinding()]
param([switch]$Help)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Invoke-NativeProbe {
    param([string]$FilePath, [string[]]$Arguments)
    $previousPreference = $ErrorActionPreference
    try {
        # PowerShell 5.1 can promote redirected native stderr to an exception.
        $ErrorActionPreference = 'Continue'
        $output = & $FilePath @Arguments 2>$null
        $code = $LASTEXITCODE
        return @{ Output = ($output -join "`n"); Code = $code }
    }
    finally { $ErrorActionPreference = $previousPreference }
}

if ($Help) {
    Write-Host 'WL Traders — установка через Docker Desktop (Linux containers).'
    Write-Host 'Нужны запущенный Docker Desktop с WSL 2 и Python 3.10+.'
    Write-Host 'Запуск: powershell -ExecutionPolicy Bypass -File .\install.ps1'
    exit 0
}

$tempSource = $null
try {
    $port = 8787
    if ($env:WL_TRADERS_INSTALLER_PORT) {
        if ($env:WL_TRADERS_INSTALLER_PORT -notmatch '^\d{4,5}$') { throw 'Порт установщика должен быть числом от 1024 до 65535.' }
        $port = [int]$env:WL_TRADERS_INSTALLER_PORT
        if ($port -lt 1024 -or $port -gt 65535) { throw 'Порт установщика должен быть числом от 1024 до 65535.' }
    }
    $bindAddress = '127.0.0.1'
    if ($env:WL_TRADERS_INSTALLER_HOST) { $bindAddress = $env:WL_TRADERS_INSTALLER_HOST }
    if ($bindAddress -notin @('127.0.0.1', '0.0.0.0')) { throw 'Адрес панели: только 127.0.0.1 или 0.0.0.0.' }
    $docker = Get-Command docker -ErrorAction SilentlyContinue
    if (-not $docker) { throw 'Установите Docker Desktop: https://www.docker.com/products/docker-desktop/ . Включите WSL 2, запустите Docker и повторите команду.' }
    $engine = Invoke-NativeProbe -FilePath $docker.Source -Arguments @('info', '--format', '{{.OSType}}')
    if ($engine.Code -ne 0 -or $engine.Output.Trim() -ne 'linux') { throw 'Запустите Docker Desktop в режиме Linux containers (WSL 2) и повторите команду.' }
    $compose = Invoke-NativeProbe -FilePath $docker.Source -Arguments @('compose', 'version')
    if ($compose.Code -ne 0) { throw 'Обновите Docker Desktop: нужен Docker Compose v2.30+ или v5.' }

    $python = $null
    $pythonArgs = @()
    foreach ($name in @('py', 'python', 'python3')) {
        $candidate = Get-Command $name -ErrorAction SilentlyContinue
        if (-not $candidate) { continue }
        $prefix = @()
        if ($name -eq 'py') { $prefix = @('-3') }
        $probe = Invoke-NativeProbe -FilePath $candidate.Source -Arguments ($prefix + @('-c', 'import sys; sys.exit(0 if sys.version_info >= (3, 10) else 1)'))
        if ($probe.Code -eq 0) { $python = $candidate.Source; $pythonArgs = $prefix; break }
    }
    if (-not $python) { throw 'Установите Python 3.10+ с https://www.python.org/downloads/windows/ (отметьте Add Python to PATH), откройте PowerShell заново и повторите команду.' }

    $source = $PSScriptRoot
    if (-not $source -or -not (Test-Path (Join-Path $source 'installer/server.py'))) {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        $tempSource = Join-Path ([IO.Path]::GetTempPath()) ('wl-traders-source-' + [guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Path $tempSource | Out-Null
        $archive = Join-Path $tempSource 'source.zip'
        $url = 'https://codeload.github.com/niiikkid/wl.traders.p2p/zip/refs/heads/main'
        if ($env:WL_TRADERS_SOURCE_ZIP_URL) { $url = $env:WL_TRADERS_SOURCE_ZIP_URL }
        Write-Host 'Скачиваю WL Traders…'
        for ($attempt = 1; $attempt -le 3; $attempt++) {
            try { Invoke-WebRequest -UseBasicParsing -Uri $url -OutFile $archive -TimeoutSec 600; break }
            catch { if ($attempt -eq 3) { throw }; Start-Sleep -Seconds 2 }
        }
        Expand-Archive -LiteralPath $archive -DestinationPath (Join-Path $tempSource 'unpacked')
        $folders = @(Get-ChildItem -LiteralPath (Join-Path $tempSource 'unpacked') -Directory)
        if ($folders.Count -ne 1) { throw 'Архив проекта неполный. Повторите загрузку.' }
        $source = $folders[0].FullName
    }
    foreach ($relative in @('installer/server.py', 'installer/page.html', 'compose.yaml')) {
        if (-not (Test-Path -LiteralPath (Join-Path $source $relative) -PathType Leaf)) { throw "В проекте отсутствует $relative. Загрузите полный архив репозитория." }
    }
    if ($bindAddress -eq '0.0.0.0') { Write-Warning 'Панель доступна по сети без HTTPS. Рекомендуется адрес 127.0.0.1.' }
    & $python @pythonArgs (Join-Path $source 'installer/server.py') --host $bindAddress --port $port --expires-in 2700
    if ($LASTEXITCODE -ne 0) { throw "Установщик завершился с кодом $LASTEXITCODE. Исправьте причину выше и повторите команду; данные не удаляются." }
}
catch {
    Write-Host "`n$($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
finally {
    if ($tempSource -and (Test-Path -LiteralPath $tempSource)) { Remove-Item -LiteralPath $tempSource -Recurse -Force }
}
