<?php
// Configurar fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo'); // Horário de Brasília

// Arquivo ESPECIALIZADO em pegar nome do usuário
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Configurações
$logFile = 'nomes_identificados.txt';
$jsonFile = 'usuarios_completos.json';

// Função para obter data/hora formatada corretamente
function getBrazilianDateTime($format = 'Y-m-d H:i:s') {
    return date($format);
}

// Função para formatar data/hora para exibição
function formatDateTimeForDisplay($timestamp = null) {
    if ($timestamp) {
        $date = new DateTime($timestamp, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $date->format('d/m/Y H:i:s');
    }
    return date('d/m/Y H:i:s');
}

// Coletar TODAS as informações possíveis do servidor
$serverData = [];

// 1. Headers HTTP que podem conter informações de autenticação
$authHeaders = [
    'PHP_AUTH_USER',
    'PHP_AUTH_PW',
    'AUTH_USER',
    'AUTH_TYPE',
    'REMOTE_USER',
    'REDIRECT_REMOTE_USER',
    'HTTP_AUTHORIZATION',
    'HTTP_X_FORWARDED_USER',
    'HTTP_X_REMOTE_USER',
    'HTTP_X_REMOTE_IDENT'
];

foreach ($authHeaders as $header) {
    if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
        $serverData['auth_headers'][$header] = $_SERVER[$header];
    }
}

// 2. Informações de conexão
$serverData['connection'] = [
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    'remote_port' => $_SERVER['REMOTE_PORT'] ?? '0',
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? '0.0.0.0',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'request_time' => $_SERVER['REQUEST_TIME'] ?? time(),
    'request_time_float' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)
];

// 3. TENTAR IDENTIFICAÇÃO WINDOWS/ACTIVE DIRECTORY
// Esta é a parte MAIS IMPORTANTE para redes corporativas

$windowsUser = null;
$computerName = null;
$userDomain = null;

// Método 1: PHP running as CGI with mod_auth_sspi on Apache
if (function_exists('apache_getenv')) {
    $windowsUser = apache_getenv('REMOTE_USER') ?: 
                   apache_getenv('REDIRECT_REMOTE_USER') ?: 
                   apache_getenv('AUTH_USER');
}

// Método 2: PHP running as ISAPI on IIS
if (!$windowsUser && isset($_SERVER['AUTH_USER'])) {
    $windowsUser = $_SERVER['AUTH_USER'];
}

// Método 3: Via HTTP headers (when using proxy or load balancer)
if (!$windowsUser && isset($_SERVER['HTTP_X_FORWARDED_USER'])) {
    $windowsUser = $_SERVER['HTTP_X_FORWARDED_USER'];
}

// Método 4: Check for Windows authentication via NTLM/Kerberos
if (!$windowsUser && isset($_SERVER['REMOTE_USER'])) {
    $windowsUser = $_SERVER['REMOTE_USER'];
}

// Método 5: Try to get from LOGON_USER (IIS specific)
if (!$windowsUser && isset($_SERVER['LOGON_USER'])) {
    $windowsUser = $_SERVER['LOGON_USER'];
}

// Método 6: Check for basic auth
if (!$windowsUser && isset($_SERVER['PHP_AUTH_USER'])) {
    $windowsUser = $_SERVER['PHP_AUTH_USER'];
}

// Processar nome do usuário Windows
if ($windowsUser) {
    // Formato pode ser: DOMINIO\usuario ou usuario@dominio.com
    if (strpos($windowsUser, '\\') !== false) {
        // Formato DOMINIO\usuario
        list($userDomain, $userName) = explode('\\', $windowsUser, 2);
        $serverData['windows_auth'] = [
            'full' => $windowsUser,
            'domain' => $userDomain,
            'username' => $userName,
            'format' => 'domain\\user'
        ];
    } elseif (strpos($windowsUser, '@') !== false) {
        // Formato usuario@dominio.com
        list($userName, $domain) = explode('@', $windowsUser, 2);
        $serverData['windows_auth'] = [
            'full' => $windowsUser,
            'domain' => $domain,
            'username' => $userName,
            'format' => 'user@domain'
        ];
    } else {
        // Apenas nome de usuário
        $serverData['windows_auth'] = [
            'full' => $windowsUser,
            'username' => $windowsUser,
            'format' => 'username_only'
        ];
    }
}

// 4. TENTAR REVERSE DNS PARA OBTER NOME DO COMPUTADOR
// Isso funciona muito bem em redes corporativas
$computerHostname = null;
$reverseDNS = null;

if (function_exists('gethostbyaddr')) {
    $clientIP = $_SERVER['REMOTE_ADDR'];
    
    // Verificar se é IP privado (redes internas)
    $isPrivateIP = filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    
    if ($isPrivateIP) {
        $reverseDNS = @gethostbyaddr($clientIP);
        
        if ($reverseDNS && $reverseDNS !== $clientIP) {
            $computerHostname = $reverseDNS;
            
            // Tentar extrair informações do hostname
            $hostnameParts = explode('.', $reverseDNS);
            $serverData['reverse_dns'] = [
                'full' => $reverseDNS,
                'hostname' => $hostnameParts[0],
                'domain' => implode('.', array_slice($hostnameParts, 1)),
                'parts' => $hostnameParts
            ];
            
            // Tentar extrair nome de usuário do hostname
            // Padrões comuns: PC-JOAO, NOTEBOOK-MARIA, WS-USER123
            $hostname = $hostnameParts[0];
            $possibleUsername = null;
            
            // Remove prefixos comuns
            $patterns = [
                '/^(PC|NOTE|NB|LT|WS|DESKTOP|SERVER|VM)-([A-Za-z]+)/i',
                '/^([A-Za-z]+)[-_]?([A-Za-z]+)?(?:[0-9]+)?$/',
                '/^([A-Z][a-z]+)(?:[0-9]+)?$/'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $hostname, $matches)) {
                    if (isset($matches[2]) && strlen($matches[2]) >= 2) {
                        $possibleUsername = ucfirst(strtolower($matches[2]));
                        break;
                    } elseif (isset($matches[1]) && strlen($matches[1]) >= 2) {
                        $possibleUsername = ucfirst(strtolower($matches[1]));
                        break;
                    }
                }
            }
            
            if ($possibleUsername) {
                $serverData['reverse_dns']['extracted_username'] = $possibleUsername;
            }
        }
    }
}

// 5. TENTAR IDENTIFICAÇÃO VIA BROWSER FINGERPRINTING
$browserFingerprint = [
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
    'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
    'connection' => $_SERVER['HTTP_CONNECTION'] ?? '',
    'referer' => $_SERVER['HTTP_REFERER'] ?? ''
];

// 6. PROCESSAR DADOS DO FRONTEND (se enviados)
$frontendData = json_decode(file_get_contents('php://input'), true);
if ($frontendData) {
    // Corrigir timestamp do frontend se necessário
    if (isset($frontendData['timestamp'])) {
        // Converter para DateTime com fuso correto
        try {
            $frontendDate = new DateTime($frontendData['timestamp']);
            $frontendDate->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            $frontendData['timestamp_brazil'] = $frontendDate->format('Y-m-d H:i:s');
            $frontendData['timestamp_brazil_display'] = $frontendDate->format('d/m/Y H:i:s');
        } catch (Exception $e) {
            $frontendData['timestamp_brazil'] = getBrazilianDateTime();
            $frontendData['timestamp_brazil_display'] = formatDateTimeForDisplay();
        }
    }
    
    $serverData['frontend'] = $frontendData;
}

// 7. DETERMINAR O NOME DO USUÁRIO (PRIORIDADE)
$identifiedUser = 'Visitante Desconhecido';
$identificationMethod = 'Não identificado';
$confidence = 0;

// Ordem de prioridade para identificação:
// 1. Autenticação Windows (mais confiável)
if (isset($serverData['windows_auth']['username'])) {
    $identifiedUser = $serverData['windows_auth']['username'];
    $identificationMethod = 'Autenticação Windows';
    $confidence = 95;
}
// 2. Reverse DNS com nome extraído
elseif (isset($serverData['reverse_dns']['extracted_username'])) {
    $identifiedUser = $serverData['reverse_dns']['extracted_username'];
    $identificationMethod = 'Nome do Computador (DNS)';
    $confidence = 80;
}
// 3. Nome do hostname completo
elseif (isset($serverData['reverse_dns']['hostname'])) {
    $identifiedUser = $serverData['reverse_dns']['hostname'];
    $identificationMethod = 'Hostname de Rede';
    $confidence = 70;
}
// 4. Dados do frontend (usuário digitou)
elseif (isset($frontendData['userName']) && $frontendData['userName'] !== 'Visitante') {
    $identifiedUser = $frontendData['userName'];
    $identificationMethod = 'Fornecido pelo usuário';
    $confidence = 100;
}
// 5. Nome do navegador/dispositivo do frontend
elseif (isset($frontendData['computerInfo']['specificAPIs']['possibleNameFromDevice'])) {
    $identifiedUser = $frontendData['computerInfo']['specificAPIs']['possibleNameFromDevice'];
    $identificationMethod = 'Dispositivo de Mídia';
    $confidence = 60;
}

// 8. Obter data/hora atual com fuso correto
$currentDateTime = getBrazilianDateTime();
$currentDateTimeDisplay = formatDateTimeForDisplay();

// 9. SALVAR LOG DETALHADO com hora correta
$logEntry = sprintf(
    "%s | %s | %s | %s | %s | %s | %s\n",
    $currentDateTime, // Data/hora no formato Y-m-d H:i:s
    $identifiedUser,
    $identificationMethod,
    ($serverData['connection']['remote_addr'] ?? '0.0.0.0'),
    ($serverData['reverse_dns']['full'] ?? 'N/A'),
    ($serverData['windows_auth']['full'] ?? 'N/A'),
    (substr($_SERVER['HTTP_USER_AGENT'] ?? 'N/A', 0, 100))
);

file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// 10. SALVAR EM JSON PARA HISTÓRICO
$userRecord = [
    'timestamp' => $currentDateTime, // UTC
    'timestamp_brazil' => $currentDateTime, // Horário de Brasília
    'timestamp_display' => $currentDateTimeDisplay, // Para exibição
    'identified_user' => $identifiedUser,
    'identification_method' => $identificationMethod,
    'confidence' => $confidence,
    'ip_address' => $serverData['connection']['remote_addr'] ?? '0.0.0.0',
    'server_data' => $serverData,
    'frontend_data' => $frontendData ?? null
];

// Ler histórico existente
$history = [];
if (file_exists($jsonFile)) {
    $history = json_decode(file_get_contents($jsonFile), true) ?: [];
}

// Adicionar novo registro
$history[] = $userRecord;

// Manter apenas últimos 1000 registros
if (count($history) > 1000) {
    $history = array_slice($history, -1000);
}

file_put_contents($jsonFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 11. ENVIAR EMAIL DE NOTIFICAÇÃO (se configurado)
$sendEmail = false; // Mude para true se quiser emails
$adminEmail = 'seu-email@exemplo.com'; // Configure seu email aqui

if ($sendEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    $subject = "👤 USUÁRIO IDENTIFICADO: $identifiedUser";
    
    $message = "NOVO USUÁRIO IDENTIFICADO NO SITE\n";
    $message .= "==================================\n\n";
    $message .= "Nome: $identifiedUser\n";
    $message .= "Método: $identificationMethod\n";
    $message .= "Confiança: $confidence%\n";
    $message .= "Data/Hora (Brasília): " . $currentDateTimeDisplay . "\n\n";
    
    $message .= "INFORMAÇÕES DE REDE:\n";
    $message .= "IP: " . ($serverData['connection']['remote_addr'] ?? 'N/A') . "\n";
    $message .= "Reverse DNS: " . ($serverData['reverse_dns']['full'] ?? 'N/A') . "\n";
    $message .= "Hostname: " . ($serverData['reverse_dns']['hostname'] ?? 'N/A') . "\n\n";
    
    if (isset($serverData['windows_auth'])) {
        $message .= "AUTENTICAÇÃO WINDOWS:\n";
        $message .= "Usuário: " . $serverData['windows_auth']['full'] . "\n";
        $message .= "Domínio: " . ($serverData['windows_auth']['domain'] ?? 'N/A') . "\n\n";
    }
    
    $message .= "USER AGENT:\n";
    $message .= ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
    
    mail($adminEmail, $subject, $message);
}

// 12. RESPONDER AO FRONTEND com hora correta
$response = [
    'status' => 'success',
    'user_identified' => true,
    'user_name' => $identifiedUser,
    'identification_method' => $identificationMethod,
    'confidence' => $confidence,
    'timestamps' => [
        'server_time_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'server_time_brazil' => $currentDateTime,
        'server_time_display' => $currentDateTimeDisplay,
        'timezone' => 'America/Sao_Paulo',
        'offset' => date('P')
    ],
    'server_data' => [
        'ip' => $serverData['connection']['remote_addr'] ?? '0.0.0.0',
        'reverse_dns' => $serverData['reverse_dns']['full'] ?? null,
        'windows_auth' => $serverData['windows_auth']['full'] ?? null,
        'extracted_username' => $serverData['reverse_dns']['extracted_username'] ?? null
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>