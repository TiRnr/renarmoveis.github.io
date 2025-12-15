<?php
// Painel SIMPLES para ver os nomes coletados
$password = 'admin123'; // Mude esta senha!

// Verificar senha via GET para simplicidade
if (!isset($_GET['pass']) || $_GET['pass'] !== $password) {
    die('<h1>Acesso Negado</h1><p>Senha incorreta ou não fornecida.</p>');
}

// Ler arquivo de nomes
$logFile = 'nomes_identificados.txt';
$jsonFile = 'usuarios_completos.json';

// Estatísticas
$totalLines = 0;
$uniqueUsers = [];
$todayUsers = [];

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $totalLines = count($lines);
    
    $today = date('Y-m-d');
    
    foreach ($lines as $line) {
        $parts = explode(' | ', $line);
        if (count($parts) >= 2) {
            $username = $parts[1];
            $date = substr($parts[0], 0, 10);
            
            // Usuários únicos
            if (!in_array($username, $uniqueUsers)) {
                $uniqueUsers[] = $username;
            }
            
            // Usuários de hoje
            if ($date === $today) {
                $todayUsers[] = $username;
            }
        }
    }
}

// Ler JSON para mais detalhes
$detailedUsers = [];
if (file_exists($jsonFile)) {
    $detailedUsers = json_decode(file_get_contents($jsonFile), true);
    $detailedUsers = array_reverse($detailedUsers); // Mais recentes primeiro
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>👤 Painel de Nomes Identificados</title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f7fa;
        }
        .header {
            background: linear-gradient(135deg, #4a6ee0, #8a2be2);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: #4a6ee0;
            margin: 10px 0;
        }
        .users-table {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f0f7ff;
            color: #2c5282;
            font-weight: bold;
        }
        tr:hover {
            background: #f8fbff;
        }
        .user-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .badge-windows { background: #e3f2fd; color: #1976d2; }
        .badge-dns { background: #f3e5f5; color: #7b1fa2; }
        .badge-user { background: #e8f5e8; color: #388e3c; }
        .badge-other { background: #fff3e0; color: #ef6c00; }
        .search-box {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 10px;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #4a6ee0;
            border-radius: 8px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👤 Painel de Nomes Identificados</h1>
        <p>Sistema de Captura de Nomes de Usuários</p>
    </div>
    
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalLines; ?></div>
            <div>Total de Acessos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($uniqueUsers); ?></div>
            <div>Usuários Únicos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_unique($todayUsers)); ?></div>
            <div>Usuários Hoje</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($detailedUsers); ?></div>
            <div>Registros Detalhados</div>
        </div>
    </div>
    
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Buscar por nome, IP ou método..." 
               onkeyup="filterTable()">
    </div>
    
    <div class="users-table">
        <h2>📋 Últimos Usuários Identificados</h2>
        
        <table id="usersTable">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Nome do Usuário</th>
                    <th>Método</th>
                    <th>IP</th>
                    <th>Hostname</th>
                    <th>Confiança</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detailedUsers as $user): ?>
                <?php 
                    $method = $user['identification_method'];
                    $badgeClass = 'badge-other';
                    if (strpos($method, 'Windows') !== false) $badgeClass = 'badge-windows';
                    elseif (strpos($method, 'DNS') !== false) $badgeClass = 'badge-dns';
                    elseif (strpos($method, 'Fornecido') !== false) $badgeClass = 'badge-user';
                    
                    $confidence = $user['confidence'] ?? 0;
                    $confidenceColor = $confidence >= 80 ? '#38a169' : 
                                     ($confidence >= 60 ? '#d69e2e' : '#e53e3e');
                ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($user['timestamp'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($user['identified_user']); ?></strong></td>
                    <td>
                        <span class="user-badge <?php echo $badgeClass; ?>">
                            <?php echo htmlspecialchars($method); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($user['ip_address']); ?></td>
                    <td><?php echo htmlspecialchars($user['server_data']['reverse_dns']['hostname'] ?? 'N/A'); ?></td>
                    <td style="color: <?php echo $confidenceColor; ?>; font-weight: bold;">
                        <?php echo $confidence; ?>%
                    </td>
                    <td>
                        <button onclick="showDetails('<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES); ?>')">
                            🔍 Ver
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="users-table">
        <h2>👥 Todos os Usuários Únicos</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px;">
            <?php foreach ($uniqueUsers as $user): ?>
            <span style="background: #4a6ee0; color: white; padding: 8px 15px; 
                        border-radius: 20px; font-weight: bold;">
                <?php echo htmlspecialchars($user); ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let showRow = false;
                
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(input)) {
                        showRow = true;
                    }
                });
                
                row.style.display = showRow ? '' : 'none';
            });
        }
        
        function showDetails(userJson) {
            const user = JSON.parse(userJson);
            
            let details = `NOME: ${user.identified_user}\n`;
            details += `MÉTODO: ${user.identification_method}\n`;
            details += `CONFIANÇA: ${user.confidence}%\n`;
            details += `IP: ${user.ip_address}\n`;
            details += `DATA: ${new Date(user.timestamp).toLocaleString('pt-BR')}\n\n`;
            
            if (user.server_data.reverse_dns) {
                details += `HOSTNAME: ${user.server_data.reverse_dns.full || 'N/A'}\n`;
                if (user.server_data.reverse_dns.extracted_username) {
                    details += `NOME EXTRAÍDO: ${user.server_data.reverse_dns.extracted_username}\n`;
                }
            }
            
            if (user.server_data.windows_auth) {
                details += `\nAUTENTICAÇÃO WINDOWS:\n`;
                details += `Usuário: ${user.server_data.windows_auth.full}\n`;
                details += `Domínio: ${user.server_data.windows_auth.domain || 'N/A'}\n`;
            }
            
            alert(details);
        }
        
        // Auto-refresh a cada 30 segundos
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>