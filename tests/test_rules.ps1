# ========================================================
# Testes de Regras de Negócio — Show de Prêmios (PowerShell)
# ========================================================

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  TESTES OBRIGATÓRIOS — SHOW DE PRÊMIOS (SEÇÃO 36)" -ForegroundColor Yellow
Write-Host "========================================================" -ForegroundColor Cyan

$passed = 0
$failed = 0

function Calculate-Sales([int]$qty, [int]$bundleQty = 3, [double]$bundlePrice = 5.00, [double]$singlePrice = 2.00) {
    if ($qty -lt 0) { $qty = 0 }
    $packages = [Math]::Floor($qty / $bundleQty)
    $singles = $qty % $bundleQty
    return [Math]::Round(($packages * $bundlePrice) + ($singles * $singlePrice), 2)
}

function Calculate-PrizeSuggestion([double]$sales, [double]$poolPercent = 0.50, [double]$p1Percent = 0.65, [double]$rounding = 10.00) {
    $rawTotal = $sales * $poolPercent
    $total = [Math]::Round($rawTotal / $rounding) * $rounding
    if ($total -le 0) {
        return @{ Total = 0.0; Prize1 = 0.0; Prize2 = 0.0 }
    }
    $rawP1 = $total * $p1Percent
    $p1 = [Math]::Round($rawP1 / $rounding) * $rounding
    $p2 = $total - $p1
    if ($p2 -lt 0) {
        $p1 = $total
        $p2 = 0.0
    } elseif ($p1 -lt $p2) {
        $tmp = $p1; $p1 = $p2; $p2 = $tmp
    }
    return @{ Total = $total; Prize1 = $p1; Prize2 = $p2 }
}

function Assert-Test([bool]$condition, [string]$message) {
    if ($condition) {
        Write-Host "  [PASS] $message" -ForegroundColor Green
        $global:passed++
    } else {
        Write-Host "  [FAIL] $message" -ForegroundColor Red
        $global:failed++
    }
}

Write-Host "`n[1/3] Validando Tabela Obrigatória de Vendas:" -ForegroundColor Cyan
$mandatoryCases = @{
    0  = 0.00
    1  = 2.00
    2  = 4.00
    3  = 5.00
    4  = 7.00
    5  = 9.00
    6  = 10.00
    7  = 12.00
    11 = 19.00
    15 = 25.00
    23 = 39.00
}

foreach ($qty in ($mandatoryCases.Keys | Sort-Object)) {
    $expected = $mandatoryCases[$qty]
    $actual = Calculate-Sales -qty $qty
    Assert-Test ($expected -eq $actual) "Qtd $qty => Esperado: R$ $($expected.ToString('N2')) | Calculado: R$ $($actual.ToString('N2'))"
}

Write-Host "`n[2/3] Validando Motor de Sugestão de Premiações (Múltiplos de R$ 10,00):" -ForegroundColor Cyan
$s1000 = Calculate-PrizeSuggestion -sales 1000.00
Assert-Test ($s1000.Total -eq 500.00) "Vendas R$ 1.000,00 => Premiação Total: R$ $($s1000.Total.ToString('N2'))"
Assert-Test ($s1000.Prize1 -ge $s1000.Prize2) "1º Prêmio (R$ $($s1000.Prize1.ToString('N2'))) >= 2º Prêmio (R$ $($s1000.Prize2.ToString('N2')))"
Assert-Test (($s1000.Prize1 + $s1000.Prize2) -eq $s1000.Total) "Soma dos prêmios bate 100% o total sugerido"

$s750 = Calculate-PrizeSuggestion -sales 750.00
Assert-Test (($s750.Total % 10) -eq 0) "Arredondamento para múltiplo de 10: R$ $($s750.Total.ToString('N2'))"
Assert-Test (($s750.Prize1 + $s750.Prize2) -eq $s750.Total) "Soma dos prêmios para R$ 750 de vendas bate o total sugerido"

Write-Host "`n[3/3] Validando Integridade da Estrutura de Arquivos em /showdepremios:" -ForegroundColor Cyan
$requiredFiles = @(
    "index.php",
    ".htaccess",
    ".env.example",
    "Dockerfile",
    "app/Core/Database.php",
    "app/Core/Router.php",
    "app/Core/Session.php",
    "app/Core/Auth.php",
    "app/Core/Csrf.php",
    "app/Core/View.php",
    "app/Services/PricingService.php",
    "app/Services/PrizeSuggestionService.php",
    "app/Services/CashService.php",
    "app/Services/ReportService.php",
    "app/Services/BackupService.php",
    "app/Views/layouts/main.php",
    "app/Views/dashboard/index.php",
    "app/Views/day/index.php",
    "app/Views/rounds/operate.php",
    "app/Views/sellers/index.php",
    "app/Views/reports/managerial.php",
    "public/assets/css/style.css",
    "public/assets/js/app.js"
)

$basePath = "c:\Users\Thiago\retiro\showdepremios"
foreach ($file in $requiredFiles) {
    $full = Join-Path $basePath $file
    Assert-Test (Test-Path $full) "Arquivo presente: $file"
}

Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "  RESULTADO FINAL: Passaram: $passed | Falharam: $failed" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Red" })
Write-Host "========================================================" -ForegroundColor Cyan

if ($failed -gt 0) {
    exit 1
}
