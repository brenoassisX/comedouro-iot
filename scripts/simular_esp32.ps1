<#
  PetFeeder - simula UMA sincronizacao do ESP32 (para testar o servidor sem o hardware)

  Uso (com o servidor ligado), na pasta do projeto:
    powershell -ExecutionPolicy Bypass -File scripts\simular_esp32.ps1 -Mac 'AA:BB:CC:DD:EE:FF' -Distancia 15 -Peso 0

  Parametros:
    -Mac        (obrigatorio) MAC cadastrado em Dispositivos
    -Distancia  distancia do ultrassonico em cm (omita para simular sensor sem leitura)
    -Peso       peso da tigela em gramas (omita para simular ESP32 sem balanca)
    -Concluido  id do comando que o ESP32 acabou de executar (vem na resposta "alimentar")
    -Servidor   padrao http://localhost:8000

  Serve para separar o problema: se o simulador funciona e o ESP32 nao, o defeito esta na rede ou no
  firmware; se o simulador tambem falha, o defeito esta no servidor ou no banco.
#>
param(
    [Parameter(Mandatory = $true)][string]$Mac,
    [double]$Distancia = -1,
    [double]$Peso = [double]::NaN,
    [int]$Concluido = 0,
    [string]$Servidor = 'http://localhost:8000'
)

$corpo = @{ codigo_mac_esp = $Mac }
if ($Distancia -ge 0) { $corpo.distancia_cm = $Distancia }
if (-not [double]::IsNaN($Peso)) { $corpo.peso_tigela_g = $Peso }
if ($Concluido -gt 0) { $corpo.comando_concluido = $Concluido }

$json = $corpo | ConvertTo-Json -Compress
Write-Host "Enviando: $json" -ForegroundColor Cyan

try {
    $resposta = Invoke-RestMethod -Method Post -Uri "$Servidor/api/sincronizar" -ContentType 'application/json' -Body $json
    Write-Host ('Resposta: ' + ($resposta | ConvertTo-Json -Compress)) -ForegroundColor Green
} catch {
    $detalhe = $_.ErrorDetails.Message
    if (-not $detalhe) { $detalhe = $_.Exception.Message }
    Write-Host "ERRO: $detalhe" -ForegroundColor Red
}
