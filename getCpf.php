<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permite requisições de qualquer origem (ajuste para produção se necessário)
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Se for uma requisição OPTIONS (preflight CORS), apenas retorne os headers e saia.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

$api_user = '2054e0d4-1301-480d-9aa0-55ca35ed9f08';
$api_base_url = 'https://apela-api.tech/';

// Verifica se o parâmetro cpf_value foi enviado
if (!isset($_GET['cpf_value'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Parâmetro cpf_value não fornecido.'
    ]);
    exit;
}

$cpf_cliente = $_GET['cpf_value'];

// Remove caracteres não numéricos do CPF
$cpf_numerico = preg_replace('/[^0-9]/', '', $cpf_cliente);

if (strlen($cpf_numerico) !== 11) {
    echo json_encode([
        'error' => true,
        'message' => 'CPF inválido. Deve conter 11 dígitos numéricos.'
    ]);
    exit;
}

// Monta a URL da nova API
$url_api = $api_base_url . '?user=' . $api_user . '&cpf=' . urlencode($cpf_numerico);

// Inicializa o cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desabilitar verificação SSL (não recomendado para produção se não for necessário)
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos para melhor performance

// Executa a requisição
$response_api_raw = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Verifica se houve erro na requisição cURL
if ($curl_error) {
    echo json_encode([
        'error' => true,
        'message' => 'Erro na comunicação com a API externa (cURL): ' . $curl_error
    ]);
    exit;
}

// Decodifica a resposta JSON da nova API
$response_api_json = json_decode($response_api_raw, true);

// Verifica se a decodificação JSON falhou
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao decodificar a resposta da API externa. Resposta recebida: ' . $response_api_raw,
        'status_code_api' => $http_code
    ]);
    exit;
}

// Verifica o código de status HTTP da API e se há um erro na resposta da API
if ($http_code !== 200 || isset($response_api_json['error']) || isset($response_api_json['erro'])) {
    $api_error_message = 'Erro desconhecido da API externa.';
    if (isset($response_api_json['message'])) {
        $api_error_message = $response_api_json['message'];
    } elseif (isset($response_api_json['msg'])) {
        $api_error_message = $response_api_json['msg'];
    } elseif (is_string($response_api_json)) { // Às vezes a API pode retornar uma string de erro
        $api_error_message = $response_api_json;
    }

    echo json_encode([
        'error' => true,
        'message' => 'API externa retornou um erro: ' . $api_error_message,
        'status_code_api' => $http_code,
        'api_response' => $response_api_json // Inclui a resposta completa da API para debug
    ]);
    exit;
}

// Verifica se os campos esperados existem na resposta
// A nova API pode retornar "nome" ou não.
// Se "nome" não existir, pode ser um CPF não encontrado ou uma resposta de erro estruturada de forma diferente.
if (!isset($response_api_json['nome'])) {
     echo json_encode([
        'error' => true,
        'message' => 'Dados não encontrados para o CPF consultado ou formato de resposta inesperado.',
        'api_response' => $response_api_json
    ]);
    exit;
}


// Mapeia os campos para o formato que seu frontend pode estar esperando (MAIÚSCULAS)
$dados_formatados = [
    'CPF_CONSULTADO' => isset($response_api_json['cpf']) ? $response_api_json['cpf'] : $cpf_numerico,
    'NOME' => isset($response_api_json['nome']) ? $response_api_json['nome'] : '',
    'SEXO' => isset($response_api_json['sexo']) ? $response_api_json['sexo'] : '',
    'NASCIMENTO' => isset($response_api_json['nascimento']) ? $response_api_json['nascimento'] : '',
    'MAE' => isset($response_api_json['nome_mae']) ? $response_api_json['nome_mae'] : '',
    // Adicione outros campos aqui se a nova API os retornar e você precisar deles
    // 'SITUACAO_CADASTRAL' => isset($response_api_json['situacao_cadastral']) ? $response_api_json['situacao_cadastral'] : '',
];

// Retorna os dados formatados como JSON para o frontend
echo json_encode([
    'error' => false,
    'message' => 'Consulta realizada com sucesso.',
    'data' => $dados_formatados,
    // 'raw_api_response' => $response_api_json // Opcional: para debug no frontend
]);

?>