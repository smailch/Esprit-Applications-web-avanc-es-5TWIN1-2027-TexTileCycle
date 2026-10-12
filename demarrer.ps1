# Lance tout le projet : le microservice IA de diagnostic (port 8001) en arrière-plan, puis Laravel (port 8000).
# Usage, depuis n'importe quel dossier :  powershell -ExecutionPolicy Bypass -File demarrer.ps1
# Ctrl+C arrête Laravel et le service IA lancé par ce script.
$ErrorActionPreference = 'Stop'
$ici = Split-Path -Parent $MyInvocation.MyCommand.Path

function Test-IaEnLigne {
    try { $null = Invoke-RestMethod 'http://127.0.0.1:8001/sante' -TimeoutSec 2; $true } catch { $false }
}

$ia = $null
if (Test-IaEnLigne) {
    Write-Host 'Service IA déjà lancé sur http://127.0.0.1:8001 : il est réutilisé.'
} else {
    # uvicorn écrit son journal sur la sortie d'erreur ; Start-Process exige deux fichiers distincts.
    $journal = Join-Path $ici 'storage\logs\ia-diagnostic.log'
    $sortie = Join-Path $ici 'storage\logs\ia-diagnostic-sortie.log'
    $lanceur = Join-Path $ici 'ia-diagnostic\lancer.ps1'
    # PowerShell 5.1 ne met pas les arguments entre guillemets : le chemin contient des espaces.
    $ia = Start-Process powershell -PassThru -WindowStyle Hidden `
        -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$lanceur`"" `
        -RedirectStandardError $journal -RedirectStandardOutput $sortie

    # Une erreur de configuration (jeton absent, venv introuvable) arrête le lanceur en quelques secondes.
    Start-Sleep -Seconds 3
    if ($ia.HasExited) {
        Write-Warning 'Le service IA n''a pas démarré : le diagnostic par photo sera indisponible.'
        Get-Content $journal, $sortie -ErrorAction SilentlyContinue | Select-Object -Last 10 | Write-Host
        $ia = $null
    } else {
        Write-Host "Service IA en cours de chargement (environ 20 s) sur http://127.0.0.1:8001 - journal : $journal"
    }
}

try {
    & php (Join-Path $ici 'artisan') serve --host=127.0.0.1 --port=8000
} finally {
    if ($ia) {
        # /T arrête aussi le processus Python lancé par lancer.ps1.
        taskkill /PID $ia.Id /T /F | Out-Null
        Write-Host 'Service IA arrêté.'
    }
}
