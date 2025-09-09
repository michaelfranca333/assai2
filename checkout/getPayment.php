<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido. Apenas POST é permitido.']);
    http_response_code(405);
    exit();
}

function gerarCPF()
{
    $cpf = '';
    for ($i = 0; $i < 9; $i++) {
        $cpf .= rand(0, 9);
    }

    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito1;

    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito2;

    $invalidos = [
        '00000000000', '11111111111', '22222222222', '33333333333', '44444444444',
        '55555555555', '66666666666', '77777777777', '88888888888', '99999999999'
    ];

    if (in_array($cpf, $invalidos)) {
        return gerarCPF();
    }

    return $cpf;
}

$nomes_masculinos = ['João', 'Pedro', 'Lucas', 'Miguel', 'Arthur', 'Gabriel', 'Bernardo', 'Rafael', 'Gustavo', 'Felipe', 'Daniel', 'Matheus', 'Bruno', 'Thiago', 'Carlos'];
$nomes_femininos = ['Maria', 'Ana', 'Julia', 'Sofia', 'Isabella', 'Helena', 'Valentina', 'Laura', 'Alice', 'Manuela', 'Beatriz', 'Clara', 'Luiza', 'Mariana', 'Sophia'];
$sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho', 'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa'];

$genero = rand(0, 1);
$nome_gen = $genero ? $nomes_masculinos[array_rand($nomes_masculinos)] : $nomes_femininos[array_rand($nomes_femininos)];
$sobrenome1 = $sobrenomes[array_rand($sobrenomes)];
$sobrenome2 = $sobrenomes[array_rand($sobrenomes)];
$nome = "$nome_gen $sobrenome1 $sobrenome2";

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$nome = "Beatriz Carvalho Goncalves";
$email = "BeatrizCarvalhoGoncalves@teleworm.us";
$cpf = "16436020713";
$telefone = "(16) 4336-8484"; // com DDD
$telefoneLimpo = preg_replace('/\D/', '', $telefone);
$cpfLimpo = preg_replace('/\D/', '', $cpf);
$amount = isset($data['amount']) ? intval(round(floatval($data['amount']) * 100)) : 0;
$type = "Ebook finanças 1";
$utmQuery = "?"; // pode manter

$cpfLimpo = preg_replace('/\D/', '', $cpf);
$telefoneLimpo = preg_replace('/\D/', '', $telefone);

if (empty($nome) || empty($cpf)) {
    exit();
}

$data = [
    'amount' => $amount,
    'paymentMethod' => 'PIX',
    'name' => $nome,
    'email' => $email,
    'phone' => $telefoneLimpo,
    'cpf' => $cpfLimpo,
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

// ⚡ Otimizações de tempo de resposta
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // tempo máximo de conexão
curl_setopt($ch, CURLOPT_TIMEOUT, 5);        // tempo máximo total

$response = curl_exec($ch);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    echo "Erro na requisição: $error_msg";
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

    // ⚡ Otimização para envio imediato da resposta
    ob_end_clean();
    header("Connection: close");
    ignore_user_abort(true);
    echo json_encode(['data' => $newResult]);
    flush();
    exit;
} else {
    $errorMessage = isset($result['message']) ? $result['message'] : 'Erro desconhecido.';
    echo json_encode(['error' => $errorMessage]);
}
?>
