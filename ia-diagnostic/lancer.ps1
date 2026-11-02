# Lance le microservice de diagnostic avec le jeton lu dans le .env de Laravel (un seul secret à gérer).
# Usage, depuis n'importe quel dossier :  powershell -ExecutionPolicy Bypass -File ia-diagnostic\lancer.ps1
$ErrorActionPreference = 'Stop'
$ici = Split-Path -Parent $MyInvocation.MyCommand.Path

$ligne = Get-Content (Join-Path $ici '..\.env') | Where-Object { $_ -match '^IA_DIAGNOSTIC_JETON=.+' } | Select-Object -First 1
if (-not $ligne) {
    throw "IA_DIAGNOSTIC_JETON est absent ou vide dans le fichier .env de Laravel."
}

$python = Join-Path $env:USERPROFILE '.textilecycle-ia\venv\Scripts\python.exe'
if (-not (Test-Path $python)) {
    throw "Environnement Python introuvable ($python) : voir ia-diagnostic\README.md, section Installation."
}

$env:IA_DIAGNOSTIC_JETON = ($ligne -replace '^IA_DIAGNOSTIC_JETON=', '').Trim().Trim('"')
$env:HF_HUB_OFFLINE = '1'

Set-Location $ici
Write-Host 'Chargement des modèles (environ 20 s), puis le service écoute sur http://127.0.0.1:8001 …'
& $python -m uvicorn service:app --host 127.0.0.1 --port 8001
