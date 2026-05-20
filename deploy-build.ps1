# deploy-build.ps1
# Monta o ZIP de deploy para Hostinger
# Estrutura final:
#   precog-laravel/  -> vai para ~/precog-laravel/ no servidor
#   public_html/     -> vai para ~/public_html/ no servidor

$ErrorActionPreference = "Stop"
$projectRoot = "d:\projetos\precog-laravel"
$deployDir = "$projectRoot\deploy"
$laravelDest = "$deployDir\precog-laravel"
$publicDest = "$deployDir\public_html"
$zipOutput = "$projectRoot\precog-deploy.zip"

Write-Host "=== PrecogSystem Deploy Builder ===" -ForegroundColor Cyan
Write-Host ""

# Limpa deploy anterior (exceto arquivos já criados)
if (Test-Path $zipOutput) { Remove-Item $zipOutput -Force }

# --- PRECOG-LARAVEL (código do framework) ---
Write-Host "[1/5] Copiando app/, bootstrap/, config/..." -ForegroundColor Yellow
$folders = @("app", "bootstrap", "config", "database", "resources", "routes", "storage", "vendor")
foreach ($folder in $folders) {
    $src = "$projectRoot\$folder"
    $dst = "$laravelDest\$folder"
    if (Test-Path $src) {
        Write-Host "  -> $folder/" -ForegroundColor Gray
        Copy-Item -Path $src -Destination $dst -Recurse -Force
    }
}

# Copiar arquivos raiz necessários
Write-Host "[2/5] Copiando arquivos raiz..." -ForegroundColor Yellow
$rootFiles = @("artisan", "composer.json", "composer.lock")
foreach ($file in $rootFiles) {
    $src = "$projectRoot\$file"
    if (Test-Path $src) {
        Write-Host "  -> $file" -ForegroundColor Gray
        Copy-Item -Path $src -Destination "$laravelDest\$file" -Force
    }
}

# --- PUBLIC_HTML (document root) ---
Write-Host "[3/5] Preparando public_html/..." -ForegroundColor Yellow

# .htaccess do public original
Copy-Item -Path "$projectRoot\public\.htaccess" -Destination "$publicDest\.htaccess" -Force
Write-Host "  -> .htaccess" -ForegroundColor Gray

# favicon e robots
if (Test-Path "$projectRoot\public\favicon.ico") {
    Copy-Item -Path "$projectRoot\public\favicon.ico" -Destination "$publicDest\favicon.ico" -Force
    Write-Host "  -> favicon.ico" -ForegroundColor Gray
}
if (Test-Path "$projectRoot\public\robots.txt") {
    Copy-Item -Path "$projectRoot\public\robots.txt" -Destination "$publicDest\robots.txt" -Force
    Write-Host "  -> robots.txt" -ForegroundColor Gray
}

# images/
if (Test-Path "$projectRoot\public\images") {
    Copy-Item -Path "$projectRoot\public\images" -Destination "$publicDest\images" -Recurse -Force
    Write-Host "  -> images/" -ForegroundColor Gray
}

# index.php modificado já está em deploy/public_html/index.php
Write-Host "  -> index.php (modificado)" -ForegroundColor Gray

# --- GARANTIR ESTRUTURA DE STORAGE ---
Write-Host "[4/5] Criando estrutura de storage..." -ForegroundColor Yellow
$storageDirs = @(
    "$laravelDest\storage\framework\cache\data",
    "$laravelDest\storage\framework\sessions",
    "$laravelDest\storage\framework\views",
    "$laravelDest\storage\logs",
    "$laravelDest\storage\app\public"
)
foreach ($dir in $storageDirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}
# Criar .gitignore em storage/logs para manter a pasta no zip
"*`n!.gitignore" | Set-Content "$laravelDest\storage\logs\.gitignore" -Force

# Remover database.sqlite (não vai pro deploy MySQL)
$sqlitePath = "$laravelDest\database\database.sqlite"
if (Test-Path $sqlitePath) {
    Remove-Item $sqlitePath -Force
    Write-Host "  -> Removido database.sqlite" -ForegroundColor Gray
}

# --- CRIAR ZIP ---
Write-Host "[5/5] Criando ZIP..." -ForegroundColor Yellow
Write-Host "  Isso pode demorar (~1-2 min por causa do vendor/)..." -ForegroundColor Gray

Compress-Archive -Path "$deployDir\precog-laravel", "$deployDir\public_html" -DestinationPath $zipOutput -Force

$zipSize = [math]::Round((Get-Item $zipOutput).Length / 1MB, 1)
Write-Host ""
Write-Host "=== PRONTO! ===" -ForegroundColor Green
Write-Host "Arquivo: $zipOutput" -ForegroundColor Green
Write-Host "Tamanho: ${zipSize} MB" -ForegroundColor Green
Write-Host ""
Write-Host "=== INSTRUCOES ===" -ForegroundColor Cyan
Write-Host "1. Upload precog-deploy.zip via FTP para /home/usuario/" -ForegroundColor White
Write-Host "2. Via SSH: cd ~ && unzip precog-deploy.zip" -ForegroundColor White
Write-Host "3. Editar ~/precog-laravel/.env com credenciais MySQL da Hostinger" -ForegroundColor White
Write-Host "4. SSH: chmod -R 775 ~/precog-laravel/storage ~/precog-laravel/bootstrap/cache" -ForegroundColor White
Write-Host "5. SSH: cd ~/precog-laravel && php artisan config:cache && php artisan route:cache" -ForegroundColor White
Write-Host "6. Configurar Cron: * * * * * cd ~/precog-laravel && php artisan schedule:run >> /dev/null 2>&1" -ForegroundColor White
Write-Host ""
Write-Host "IMPORTANTE: Edite o .env ANTES de acessar o site!" -ForegroundColor Red
