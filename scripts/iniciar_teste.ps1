<#
  PetSync - prepara e inicia o servidor para o teste com o ESP32 (Windows / PowerShell)

  Uso, na pasta do projeto (terminal do VS Code):
    powershell -ExecutionPolicy Bypass -File scripts\iniciar_teste.ps1

  Opcoes:
    -Porta 8080          usa outra porta (lembre de trocar tambem na URL do sketch)
    -LiberarFirewall     cria a regra do firewall para a porta (abra o terminal como Administrador)

  O que faz:
    1) confere PHP (versao) e MySQL (porta 3306)
    2) mostra os IPs do computador
    3) monta e COPIA a linha da URL_SINCRONIZAR para colar no sketch
    4) inicia o servidor (deixe esta janela aberta: ela mostra os erros e cada chamada do ESP32)
#>
param(
    [int]$Porta = 8000,
    [switch]$LiberarFirewall
)

$raiz = Split-Path -Parent $PSScriptRoot
Set-Location $raiz

function Dizer([string]$texto, [string]$cor = 'White') { Write-Host $texto -ForegroundColor $cor }

Dizer '=== PetSync: preparando o teste ===' Cyan

# ---------- 1) PHP ----------
$php = $null
$cmd = Get-Command php -ErrorAction SilentlyContinue
if ($cmd) { $php = $cmd.Source }
elseif (Test-Path 'C:\xampp\php\php.exe') { $php = 'C:\xampp\php\php.exe' }

if (-not $php) {
    Dizer 'ERRO: PHP nao encontrado. Instale o XAMPP ou coloque o PHP no PATH.' Red
    exit 1
}
Dizer "[ok] PHP: $php" Green

$versao = & $php -r 'echo PHP_VERSION;'
$numero = $versao -replace '[^0-9.].*$', ''
if ([version]$numero -lt [version]'8.1') {
    Dizer "[!] PHP $versao e antigo: o projeto precisa do PHP 8.1 ou mais novo." Yellow
} else {
    Dizer "[ok] Versao do PHP: $versao" Green
}

# ---------- 2) MySQL ----------
$mysqlOk = $false
try {
    $mysqlOk = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -InformationLevel Quiet -WarningAction SilentlyContinue
} catch { }

if ($mysqlOk) {
    Dizer '[ok] MySQL respondendo na porta 3306' Green
} else {
    Dizer '[!] MySQL NAO responde na porta 3306. Abra o XAMPP e clique em Start no MySQL.' Yellow
}

# ---------- 3) IPs e linha da URL para o sketch ----------
$ips = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' })

Dizer ''
Dizer 'IPs deste computador:' Cyan
foreach ($ip in $ips) { Dizer ('  {0,-18} {1}' -f $ip.IPAddress, $ip.InterfaceAlias) }

$escolhido = $ips | Where-Object { $_.InterfaceAlias -match 'Wi-?Fi|Wireless|WLAN|Sem fio' } | Select-Object -First 1
if (-not $escolhido) { $escolhido = $ips | Select-Object -First 1 }

if ($escolhido) {
    $linha = 'const char* URL_SINCRONIZAR = "http://' + $escolhido.IPAddress + ':' + $Porta + '/api/sincronizar";'
    Dizer ''
    Dizer 'Cole esta linha no sketch (comedouro_esp32.ino):' Cyan
    Dizer "  $linha" Yellow
    try { Set-Clipboard -Value $linha; Dizer '  (ja copiada para a area de transferencia)' DarkGray } catch { }
    Dizer '  Se o IP certo for outro da lista acima, troque so o numero na linha.' DarkGray
} else {
    Dizer '[!] Nenhum IP de rede encontrado. Conecte o computador ao Wi-Fi ou ao hotspot.' Yellow
}

# ---------- 4) Firewall (opcional) ----------
if ($LiberarFirewall) {
    $admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $admin) {
        Dizer '[!] Para usar -LiberarFirewall abra o terminal como Administrador.' Yellow
    } else {
        $nome = "PetSync porta $Porta"
        if (-not (Get-NetFirewallRule -DisplayName $nome -ErrorAction SilentlyContinue)) {
            New-NetFirewallRule -DisplayName $nome -Direction Inbound -Protocol TCP -LocalPort $Porta -Action Allow -Profile Any | Out-Null
            Dizer "[ok] Regra de firewall criada: $nome" Green
        } else {
            Dizer "[ok] Regra de firewall ja existe: $nome" Green
        }
    }
}

# ---------- 5) Servidor ----------
Dizer ''
Dizer "Servidor em http://localhost:$Porta   (Ctrl+C para parar)" Cyan
Dizer 'Os erros do PHP e cada chamada do ESP32 aparecem abaixo.' DarkGray
Dizer ''
& $php -S "0.0.0.0:$Porta" -t public public/index.php
