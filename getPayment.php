<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido. Apenas POST é permitido.']);
    http_response_code(405);
    exit();
}

function gerarCPF() {
    $cpf = '';
    for ($i = 0; $i < 9; $i++) $cpf .= rand(0, 9);
    for ($j = 0; $j < 2; $j++) {
        $soma = 0;
        for ($i = 0; $i < 9 + $j; $i++) {
            $soma += intval($cpf[$i]) * ((10 + $j) - $i);
        }
        $resto = $soma % 11;
        $cpf .= ($resto < 2) ? 0 : 11 - $resto;
    }
    return $cpf;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['amount'])) {
    echo json_encode(['error' => 'Valor (amount) não informado.']);
    exit();
}

$amount = intval($data['amount']); // Valor em centavos

if ($amount < 500) {
    echo json_encode(['error' => 'Valor mínimo permitido é R$ 5,00 (500 centavos).']);
    exit();
}

$type = isset($data['type']) ? $data['type'] : "Produto";
$utmQuery = isset($data['utmQuery']) ? $data['utmQuery'] : "?";

$nome = isset($data['nome']) ? $data['nome'] : 'Nome Aleatório';
// ✅ Sempre gera novo CPF
$cpf = gerarCPF();

$email = isset($data['email']) ? $data['email'] : strtolower(preg_replace("/[^a-zA-Z0-9]/", "", $nome)) . random_int(1000, 9999) . "@gmail.com";
$telefone = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : "11999999999";

$data = [
    'amount' => $amount,
    'paymentMethod' => 'PIX',
    'name' => $nome,
    'email' => $email,
    'phone' => $telefone,
    'cpf' => $cpf,
    'cep' => '13327221',
    'complement' => '',
    'number' => '12',
    'street' => 'Rua Kevork Panossian',
    'district' => 'Jardim Saltense',
    'city' => 'Salto',
    'state' => 'SP',
    "utmQuery" => $utmQuery,
    "traceable" => false,
    "checkoutUrl" => "",
    "postbackUrl" => "",
    'items' => [
        [
            'title' => strtoupper($type),
            'unitPrice' => $amount,
            'quantity' => 1,
            'tangible' => false,
        ]
    ],
];

$jsonData = json_encode($data);

$secretKey = '5847f369-4bd8-4f83-88cd-43fd5309bde2';

$ch = curl_init('https://app.ghostspaysv1.com/api/v1/transaction.purchase');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $secretKey,
    'Content-Type: application/json',
]);

// ✅ Ajustado: timeout de 10 segundos
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'Erro na requisição: ' . curl_error($ch)]);
    curl_close($ch);
    exit();
}

$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_status == 200 && isset($result['pixCode'])) {
    $newResult = [
        'pix' => $result['pixCode'],
        'id' => $result['id'],
        'amount' => $result['amount'],
        'status' => $result['status'],
    ];
    header("Connection: close");
    ignore_user_abort(true);
    echo json_encode(['data' => $newResult]);
    flush();
    exit;
} else {
    $errorMessage = isset($result['message']) ? $result['message'] : 'Erro ao processar pagamento.';
    echo json_encode(['error' => $errorMessage]);
}
?>
