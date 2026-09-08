<?php
/**
 * Testes Unitários e de Regras de Negócio — Show de Prêmios
 */

require_once __DIR__ . '/../app/Services/PricingService.php';
require_once __DIR__ . '/../app/Services/PrizeSuggestionService.php';
require_once __DIR__ . '/../app/Core/View.php';

use App\Services\PricingService;
use App\Services\PrizeSuggestionService;
use App\Core\View;

$failed = 0;
$passed = 0;

function assertEqual($expected, $actual, $message) {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  [PASS] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message} (Esperado: " . json_encode($expected) . ", Obtido: " . json_encode($actual) . ")\n";
        $failed++;
    }
}

echo "========================================================\n";
echo " TESTES OBRIGATÓRIOS — REGRAS DE VENDA (SEÇÃO 36)\n";
echo "========================================================\n";

$testCases = [
    0  => 0.00,
    1  => 2.00,
    2  => 4.00,
    3  => 5.00,
    4  => 7.00,
    5  => 9.00,
    6  => 10.00,
    7  => 12.00,
    11 => 19.00,
    15 => 25.00,
    23 => 39.00,
];

$rule = [
    'single_quantity' => 1,
    'single_price' => 2.00,
    'bundle_quantity' => 3,
    'bundle_price' => 5.00,
];

foreach ($testCases as $qty => $expectedAmount) {
    $result = PricingService::calculate($qty, $rule);
    assertEqual($expectedAmount, $result['amount'], "Quantidade {$qty} => " . View::money($expectedAmount));
}

echo "\n========================================================\n";
echo " TESTES DE SUGESTÃO DE PREMIAÇÃO (SEÇÃO 16)\n";
echo "========================================================\n";

// Vendas de R$ 1.000,00 -> 50% = R$ 500,00
// 1º Prêmio 65% de 500 = 325 -> arredondado para múltiplo de 10 = 330 (ou 320 dependendo da precisão)
// 2º Prêmio = 500 - 330 = 170 (ou 180)
// Total = 500
$suggestion = PrizeSuggestionService::calculateNextRoundSuggestion(1000.00);
assertEqual(500.00, $suggestion['total'], "Total sugerido para R$ 1.000,00 de vendas deve ser R$ 500,00");
assertEqual(true, $suggestion['prize_1'] >= $suggestion['prize_2'], "1º prêmio deve ser >= 2º prêmio");
assertEqual(500.00, round($suggestion['prize_1'] + $suggestion['prize_2'], 2), "Soma dos prêmios deve bater exatamente o total sugerido");

echo "\n========================================================\n";
echo " RESULTADO DOS TESTES\n";
echo "========================================================\n";
echo "Passaram: {$passed} | Falharam: {$failed}\n";

exit($failed === 0 ? 0 : 1);
