<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// CONFIGURAÇÕES
$config = [
    'enviar_email' => true,
    'email_destino' => 'seu-email@exemplo.com', // ALTERE AQUI
    'email_assunto' => '👤 NOVO USUÁRIO IDENTIFICADO - Sistema de Monitoramento',
    
    'salvar_log_detalhado' => true,
    'arquivo_log' => 'usuarios_identificados.log',
    
    'salvar_banco' => false, // Se quiser salvar em MySQL
    'database' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'monitoramento'
    ]
];

// Recebe os dados do frontend
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

// COLETAR INFORMAÇÕES DO SERVIDOR E REDE
// Estas informações podem conter dados do usuário/computador em redes corporativas

$serverInfo = [
    // Informações básicas
    'server_time' => date('Y-m-d H:i:s'),
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Desconhecido',
    
    // IP do cliente (pode ser proxy ou NAT)
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido',
    
    // HEADERS que podem conter informações do usuário em redes corporativas
    'http_headers' => [],
    
    // Possível nome do computador via reverse DNS
    'reverse_dns' => '',
    
    // Informações de proxy (se houver)
    'proxy_headers' => []
];

// Coletar headers HTTP que podem conter informações
$interestingHeaders = [
    'HTTP_CLIENT_IP',
    'HTTP_X_FORWARDED_FOR',
    'HTTP_X_FORWARDED',
    'HTTP_X_CLUSTER_CLIENT_IP',
    'HTTP_FORWARDED_FOR',
    'HTTP_FORWARDED',
    'REMOTE_ADDR',
    'REMOTE_HOST', // Pode conter nome do computador
    'HTTP_USER_AGENT',
    'HTTP_REFERER',
    'HTTP_ACCEPT_LANGUAGE',
    'HTTP_X_REAL_IP'
];

foreach ($interestingHeaders as $header) {
    if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
        $serverInfo['http_headers'][$header] = $_SERVER[$header];
    }
}

// Tentar reverse DNS para obter hostname (em redes internas)
if (function_exists('gethostbyaddr') && !empty($serverInfo['remote_addr'])) {
    // Só faz reverse DNS para IPs privados (redes internas)
    $ip = $serverInfo['remote_addr'];
    $isPrivateIP = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    
    if ($isPrivateIP) {
        $hostname = gethostbyaddr($ip);
        if ($hostname && $hostname != $ip) {
            $serverInfo['reverse_dns'] = $hostname;
            
            // Tentar extrair nome de usuário/computador do hostname
            $serverInfo['extracted_hostname_info'] = extractInfoFromHostname($hostname);
        }
    }
}

// Tentar detectar informações em headers de proxy
$serverInfo['possible_client_info'] = detectClientInfoFromHeaders();

// Extrair informações úteis do hostname
function extractInfoFromHostname($hostname) {
    $info = [
        'original' => $hostname,
        'username' => null,
        'computername' => null,
        'domain' => null,
        'type' => 'desconhecido'
    ];
    
    // Padrões comuns em redes corporativas
    $patterns = [
        // Windows Domain: usuario@dominio ou DOMINIO\usuario
        '/^([^@]+)@([^\.]+)\.?' => ['username' => 1, 'domain' => 2, 'type' => 'windows_user'],
        '/^([^\\\\]+)\\\\([^\\\\]+)$/i' => ['domain' => 1, 'username' => 2, 'type' => 'windows_domain'],
        
        // Nome de computador: PC-JOAO, NOTE-MARIA, WS-USER123
        '/^(PC|NOTE|WS|LT|DESKTOP)-([A-Za-z]+)/i' => ['computername' => 2, 'type' => 'computer_name'],
        
        // Padrão comum: usuario.pc, usuario.laptop
        '/^([a-z]+)\.(pc|laptop|desktop|notebook)\./i' => ['username' => 1, 'type' => 'user_device'],
        
        // Domínio completo: usuario.empresa.com
        '/^([a-z]+)\.([a-z]+)\.(com|net|org|local)$/i' => ['username' => 1, 'domain' => 2, 'type' => 'full_domain'],
    ];
    
    foreach ($patterns as $pattern => $mapping) {
        if (preg_match($pattern, $hostname, $matches)) {
            foreach ($mapping as $key => $index) {
                if ($key !== 'type' && isset($matches[$index])) {
                    $info[$key] = $matches[$index];
                }
            }
            $info['type'] = $mapping['type'];
            break;
        }
    }
    
    return $info;
}

// Detectar informações do cliente em headers
function detectClientInfoFromHeaders() {
    $info = [];
    
    // Verificar headers de autenticação
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        $info['auth_user'] = $_SERVER['PHP_AUTH_USER'];
    }
    
    if (isset($_SERVER['AUTH_USER'])) {
        $info['windows_auth_user'] = $_SERVER['AUTH_USER'];
    }
    
    // Verificar headers específicos de proxy
    $proxyHeaders = [
        'HTTP_X_FORWARDED_USER' => 'forwarded_user',
        'HTTP_X_REMOTE_USER' => 'remote_user',
        'HTTP_X_REMOTE_IDENT' => 'remote_ident'
    ];
    
    foreach ($proxyHeaders as $header => $key) {
        if (isset($_SERVER[$header])) {
            $info[$key] = $_SERVER[$header];
        }
    }
    
    return $info;
}

// Combina todos os dados
$fullData = array_merge($input, $serverInfo);

// PRIORIDADE PARA IDENTIFICAÇÃO DO USUÁRIO
// Ordem de prioridade (da mais confiável para a menos)

$identifiedUserName = 'Visitante Desconhecido';
$identificationSource = 'Não identificado';

// 1. Nome fornecido pelo frontend (usuário digitou)
if (!empty($fullData['userName']) && $fullData['userName'] !== 'Visitante') {
    $identifiedUserName = $fullData['userName'];
    $identificationSource = $fullData['identificationType'] ?? 'Fornecido pelo usuário';
}

// 2. Informações do reverse DNS (rede corporativa)
elseif (!empty($fullData['extracted_hostname_info']['username'])) {
    $identifiedUserName = ucfirst(strtolower($fullData['extracted_hostname_info']['username']));
    $identificationSource = 'Hostname de rede (DNS Reverse)';
    
    if (!empty($fullData['extracted_hostname_info']['domain'])) {
        $identificationSource .= ' - Domínio: ' . $fullData['extracted_hostname_info']['domain'];
    }
}

// 3. Autenticação Windows (IIS)
elseif (!empty($fullData['possible_client_info']['windows_auth_user'])) {
    $identifiedUserName = $fullData['possible_client_info']['windows_auth_user'];
    $identificationSource = 'Autenticação Windows (IIS)';
}

// 4. Autenticação Basic
elseif (!empty($fullData['possible_client_info']['auth_user'])) {
    $identifiedUserName = $fullData['possible_client_info']['auth_user'];
    $identificationSource = 'Autenticação Basic';
}

// 5. Headers de proxy
elseif (!empty($fullData['possible_client_info']['forwarded_user'])) {
    $identifiedUserName = $fullData['possible_client_info']['forwarded_user'];
    $identificationSource = 'Header de proxy (X-Forwarded-User)';
}

// 6. Nome do computador do hostname
elseif (!empty($fullData['extracted_hostname_info']['computername'])) {
    $identifiedUserName = 'Computador: ' . ucfirst($fullData['extracted_hostname_info']['computername']);
    $identificationSource = 'Nome do computador (DNS)';
}

// Adicionar informações de identificação aos dados
$fullData['identified_user'] = $identifiedUserName;
$fullData['identification_source'] = $identificationSource;

// 1. SALVAR EM ARQUIVO DE LOG
if ($config['salvar_log_detalhado']) {
    $logMessage = "\n" . str_repeat("=", 100) . "\n";
    $logMessage .= "👤 USUÁRIO IDENTIFICADO - " . date('d/m/Y H:i:s') . "\n";
    $logMessage .= str_repeat("-", 100) . "\n";
    
    $logMessage .= "✅ USUÁRIO: " . $identifiedUserName . "\n";
    $logMessage .= "📊 FONTE: " . $identificationSource . "\n";
    $logMessage .= "🆔 ID Sessão: " . ($fullData['sessionId'] ?? 'N/A') . "\n";
    $logMessage .= "⏰ Data/Hora: " . date('d/m/Y H:i:s', strtotime($fullData['timestamp'])) . "\n\n";
    
    $logMessage .= "🌐 INFORMAÇÕES DE REDE:\n";
    $logMessage .= "  • IP Público: " . ($fullData['userIP'] ?? 'N/A') . "\n";
    $logMessage .= "  • IP Servidor: " . $fullData['remote_addr'] . "\n";
    $logMessage .= "  • Reverse DNS: " . ($fullData['reverse_dns'] ?: 'Não disponível') . "\n";
    
    if (!empty($fullData['extracted_hostname_info'])) {
        $logMessage .= "  • Hostname Info: " . print_r($fullData['extracted_hostname_info'], true) . "\n";
    }
    
    $logMessage .= "\n💻 INFORMAÇÕES DO CLIENTE:\n";
    $logMessage .= "  • Navegador: " . ($fullData['browser'] ?? ($fullData['userAgent'] ?? 'N/A')) . "\n";
    $logMessage .= "  • Sistema: " . ($fullData['platform'] ?? 'N/A') . "\n";
    $logMessage .= "  • Idioma: " . ($fullData['language'] ?? 'N/A') . "\n";
    $logMessage .= "  • Resolução: " . ($fullData['screenResolution'] ?? 'N/A') . "\n";
    
    if (!empty($fullData['possible_client_info'])) {
        $logMessage .= "  • Headers Auth: " . print_r($fullData['possible_client_info'], true) . "\n";
    }
    
    $logMessage .= "\n🔗 INFORMAÇÕES DA VISITA:\n";
    $logMessage .= "  • URL: " . ($fullData['url'] ?? 'N/A') . "\n";
    $logMessage .= "  • Referência: " . ($fullData['referrer'] ?? 'Acesso direto') . "\n";
    
    $logMessage .= str_repeat("=", 100) . "\n";
    
    file_put_contents($config['arquivo_log'], $logMessage, FILE_APPEND | LOCK_EX);
}

// 2. ENVIAR EMAIL DE NOTIFICAÇÃO
if ($config['enviar_email'] && filter_var($config['email_destino'], FILTER_VALIDATE_EMAIL)) {
    
    // Determinar emoji baseado no tipo de identificação
    $emoji = "👤";
    if (strpos($identificationSource, 'DNS') !== false) $emoji = "🖥️";
    if (strpos($identificationSource, 'Windows') !== false) $emoji = "💼";
    if (strpos($identificationSource, 'Fornecido') !== false) $emoji = "✍️";
    
    $htmlMessage = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 700px; margin: 0 auto; background: #f9f9f9; padding: 20px; }
            .header { background: linear-gradient(135deg, #4a90e2 0%, #2c5282 100%); 
                     color: white; padding: 25px; text-align: center; border-radius: 10px 10px 0 0; }
            .user-card { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; 
                        border-left: 5px solid #4a90e2; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .user-name { font-size: 1.8em; color: #2c5282; margin: 10px 0; }
            .badge { display: inline-block; padding: 5px 15px; background: #e3f2fd; 
                    color: #1976d2; border-radius: 20px; font-size: 0.9em; }
            .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
                        gap: 15px; margin: 20px 0; }
            .info-item { background: #f7f9fc; padding: 15px; border-radius: 8px; }
            .label { font-weight: bold; color: #555; display: block; font-size: 0.9em; }
            .value { color: #2c5282; font-size: 1.1em; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; 
                     border-top: 1px solid #eee; margin-top: 20px; }
            .warning { background: #fff8e1; padding: 15px; border-radius: 8px; 
                      border-left: 5px solid #ffa000; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . $emoji . ' USUÁRIO IDENTIFICADO NO SITE</h1>
                <p>Sistema de Monitoramento - ' . date('d/m/Y H:i:s') . '</p>
            </div>
            
            <div class="user-card">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 3em; margin: 10px 0;">👤</div>
                    <div class="user-name">' . htmlspecialchars($identifiedUserName) . '</div>
                    <div class="badge">' . htmlspecialchars($identificationSource) . '</div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">📅 Data/Hora:</span>
                        <span class="value">' . date('d/m/Y H:i:s', strtotime($fullData['timestamp'])) . '</span>
                    </div>
                    
                    <div class="info-item">
                        <span class="label">🌐 IP:</span>
                        <span class="value">' . htmlspecialchars($fullData['userIP'] ?? $fullData['remote_addr']) . '</span>
                    </div>
                    
                    <div class="info-item">
                        <span class="label">🖥️ Navegador:</span>
                        <span class="value">' . htmlspecialchars(substr($fullData['userAgent'] ?? 'N/A', 0, 50)) . '...</span>
                    </div>
                    
                    <div class="info-item">
                        <span class="label">💻 Sistema:</span>
                        <span class="value">' . htmlspecialchars($fullData['platform'] ?? 'N/A') . '</span>
                    </div>
                </div>';
    
    // Adicionar informações de rede se disponíveis
    if (!empty($fullData['reverse_dns'])) {
        $htmlMessage .= '
                <div class="warning">
                    <strong>🔍 Informações de Rede Detectadas:</strong>
                    <p>Reverse DNS: ' . htmlspecialchars($fullData['reverse_dns']) . '</p>';
        
        if (!empty($fullData['extracted_hostname_info']['domain'])) {
            $htmlMessage .= '<p>Domínio: ' . htmlspecialchars($fullData['extracted_hostname_info']['domain']) . '</p>';
        }
        
        if (!empty($fullData['extracted_hostname_info']['computername'])) {
            $htmlMessage .= '<p>Computador: ' . htmlspecialchars($fullData['extracted_hostname_info']['computername']) . '</p>';
        }
        
        $htmlMessage .= '</div>';
    }
    
    // Adicionar informações de autenticação se disponíveis
    if (!empty($fullData['possible_client_info'])) {
        $htmlMessage .= '
                <div style="margin-top: 20px; background: #f0f7ff; padding: 15px; border-radius: 8px;">
                    <strong>🔐 Headers de Autenticação Detectados:</strong><br>';
        
        foreach ($fullData['possible_client_info'] as $key => $value) {
            $htmlMessage .= '<small>' . htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '</small><br>';
        }
        
        $htmlMessage .= '</div>';
    }
    
    $htmlMessage .= '
                <div style="margin-top: 20px; font-size: 0.9em; color: #666;">
                    <p><strong>📝 URL Acessada:</strong><br>' . htmlspecialchars($fullData['url'] ?? 'N/A') . '</p>
                    <p><strong>↪️ Referência:</strong><br>' . htmlspecialchars($fullData['referrer'] ?? 'Acesso direto') . '</p>
                </div>
            </div>
            
            <div class="footer">
                <p>📡 Sistema de Monitoramento de Acessos</p>
                <p>Email gerado automaticamente em ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Configuração do email
    $headers = "From: monitoramento@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "Reply-To: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    $headers .= "X-Priority: 1\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    mail($config['email_destino'], $config['email_assunto'] . ' - ' . $identifiedUserName, $htmlMessage, $headers);
}

// 3. SALVAR EM JSON
$jsonFile = 'usuarios_registrados.json';
$usuarios = [];

if (file_exists($jsonFile)) {
    $usuarios = json_decode(file_get_contents($jsonFile), true) ?: [];
}

$registro = [
    'id' => uniqid('user_', true),
    'timestamp' => date('c'),
    'usuario' => $identifiedUserName,
    'fonte_identificacao' => $identificationSource,
    'dados' => $fullData
];

$usuarios[] = $registro;

// Limitar a 1000 registros
if (count($usuarios) > 1000) {
    $usuarios = array_slice($usuarios, -1000);
}

file_put_contents($jsonFile, json_encode($usuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 4. SALVAR LOG SIMPLES
$logSimples = date('Y-m-d H:i:s') . " | " . 
             $identifiedUserName . " | " . 
             $identificationSource . " | " . 
             ($fullData['userIP'] ?? $fullData['remote_addr']) . " | " . 
             ($fullData['userAgent'] ?? 'N/A') . "\n";
file_put_contents('acessos_simples.log', $logSimples, FILE_APPEND | LOCK_EX);

// Resposta para o frontend
echo json_encode([
    'status' => 'success',
    'message' => 'Usuário identificado com sucesso',
    'usuario' => $identifiedUserName,
    'fonte_identificacao' => $identificationSource,
    'session_id' => $fullData['sessionId'] ?? null,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>