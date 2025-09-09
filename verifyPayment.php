<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido. Apenas POST é permitido.']);
    http_response_code(405);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$transactionId = isset($data['transactionId']) ? $data['transactionId'] : '';

if (empty($transactionId)) {
    echo json_encode(['success' => false, 'error' => 'ID da transação é obrigatório.']);
    http_response_code(400);
    exit();
}

$secretKey = '5847f369-4bd8-4f83-88cd-43fd5309bde2';

$url = 'https://app.ghostspaysv1.com/api/v1/transaction.getPayment?id=' . urlencode($transactionId);

$header = [
    'Authorization: ' . $secretKey,
    'Content-Type: application/json'
];

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        echo json_encode(['success' => false, 'error' => 'Erro de conexão: ' . $error]);
        http_response_code(500);
        exit();
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if ($http_status == 200 && isset($result['status'])) {
        $status = $result['status'];
        if (strtoupper($status) !== 'PENDING') {
            $status = 'PAID';
        }
        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
    } else {
        $errorMessage = isset($result['message']) ? $result['message'] : 'Erro desconhecido.';
        echo json_encode(['success' => false, 'error' => $errorMessage]);
        http_response_code($http_status);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro inesperado: ' . $e->getMessage()]);
    http_response_code(500);
}
?>
