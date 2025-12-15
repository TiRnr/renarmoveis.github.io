<?php
// Configuração de segurança
$senha = 'admin123'; // ALTERE ESTA SENHA!

session_start();
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
        if ($_POST['senha'] === $senha) {
            $_SESSION['autenticado'] = true;
        } else {
            die('Senha incorreta!');
        }
    } else {
        echo '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Acesso ao Painel</title>
            <style>
                body { font-family: Arial; display: flex; justify-content: center; 
                       align-items: center; height: 100vh; background: linear-gradient(135deg, #4a90e2 0%, #2c5282 100%); }
                .login-box { background: white; padding: 40px; border-radius: 15px; 
                            box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
                input { padding: 12px; margin: 15px; width: 250px; border: 2px solid #ddd; 
                       border-radius: 8px; font-size: 1rem; }
                button { background: #4a90e2; color: white; border: none; 
                        padding: 12px 30px; border-radius: 8px; cursor: pointer; 
                        font-size: 1rem; margin-top: 10px; }
                button:hover { background: #357abd; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>🔐 Painel de Usuários</h2>
                <p style="color: #666; margin-bottom: 20px;">Acesso restrito ao administrador</p>
                <form method="POST">
                    <input type="password" name="senha" placeholder="Digite a senha de administrador" required>
                    <br>
                    <button type="submit">🔓 Acessar Painel</button>
                </form>
            </div>
        </body>
        </html>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Painel de Usuários Identificados</title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #4a90e2 0%, #2c5282 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 5px solid #4a90e2;
        }
        
        .stat-number {
            font-size: 2.8em;
            font-weight: bold;
            color: #2c5282;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95em;
        }
        
        .user-table {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f0f7ff;
            font-weight: 600;
            color: #2c5282;
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background: #f8fbff;
        }
        
        .user-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-dns { background: #e3f2fd; color: #1976d2; }
        .badge-windows { background: #f3e5f5; color: #7b1fa2; }
        .badge-user { background: #e8f5e8; color: #388e3c; }
        .badge-unknown { background: #f5f5f5; color: #757575; }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #4a90e2;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .user-cell {
            display: flex;
            align-items: center;
        }
        
        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .filter-select {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 1rem;
        }
        
        .btn {
            padding: 12px 25px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #357abd;
        }
        
        .btn-logout {
            background: #e53e3e;
            float: right;
        }
        
        .btn-logout:hover {
            background: #c53030;
        }
        
        .user-details {
            background: #f8fbff;
            padding: 20px;
            border-radius: 10px;
            margin-top: 10px;
            border-left: 4px solid #4a90e2;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
        }
        
        .export-btn {
            background: #38a169;
            margin-left: 10px;
        }
        
        .export-btn:hover {
            background: #2f855a;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👤 Painel de Usuários Identificados</h1>
        <p>Monitoramento detalhado de acessos e identificação de usuários</p>
        <a href="?logout=true" class="btn btn-logout">🚪 Sair</a>
    </div>
    
    <?php
    // Ler dados dos usuários
    $usuarios = [];
    $usuariosUnicos = [];
    $fontesIdentificacao = [];
    $hoje = date('Y-m-d');
    $acessosHoje = 0;
    
    if (file_exists('usuarios_registrados.json')) {
        $usuarios = json_decode(file_get_contents('usuarios_registrados.json'), true) ?: [];
        $usuarios = array_reverse($usuarios); // Mais recentes primeiro
        
        // Contar estatísticas
        foreach ($usuarios as $usuario) {
            $nome = $usuario['usuario'];
            $fonte = $usuario['fonte_identificacao'];
            
            // Contar usuários únicos
            if (!in_array($nome, $usuariosUnicos)) {
                $usuariosUnicos[] = $nome;
            }
            
            // Contar fontes de identificação
            if (!isset($fontesIdentificacao[$fonte])) {
                $fontesIdentificacao[$fonte] = 0;
            }
            $fontesIdentificacao[$fonte]++;
            
            // Contar acessos hoje
            if (strpos($usuario['timestamp'], $hoje) === 0) {
                $acessosHoje++;
            }
        }
    }
    
    $totalAcessos = count($usuarios);
    $totalUnicos = count($usuariosUnicos);
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalAcessos; ?></div>
            <div class="stat-label">Total de Acessos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $totalUnicos; ?></div>
            <div class="stat-label">Usuários Únicos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $acessosHoje; ?></div>
            <div class="stat-label">Acessos Hoje</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo count($fontesIdentificacao); ?></div>
            <div class="stat-label">Métodos de Identificação</div>
        </div>
    </div>
    
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Buscar por usuário, IP, navegador..." 
               class="search-input" onkeyup="filterTable()">
        
        <select id="fonteFilter" class="filter-select" onchange="filterTable()">
            <option value="">Todas fontes</option>
            <?php foreach ($fontesIdentificacao as $fonte => $count): ?>
            <option value="<?php echo htmlspecialchars($fonte); ?>">
                <?php echo htmlspecialchars($fonte) . " ($count)"; ?>
            </option>
            <?php endforeach; ?>
        </select>
        
        <select id="dateFilter" class="filter-select" onchange="filterTable()">
            <option value="">Todo período</option>
            <option value="today">Hoje</option>
            <option value="week">Esta semana</option>
            <option value="month">Este mês</option>
        </select>
        
        <button class="btn" onclick="exportCSV()">📥 Exportar CSV</button>
        <button class="btn export-btn" onclick="showStats()">📊 Estatísticas</button>
    </div>
    
    <div class="user-table">
        <h2>📋 Últimos Usuários Identificados</h2>
        
        <table id="userTable">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Fonte</th>
                    <th>IP</th>
                    <th>Data/Hora</th>
                    <th>Navegador</th>
                    <th>Sistema</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $index => $usuario): ?>
                <?php 
                    $dados = $usuario['dados'];
                    $nome = $usuario['usuario'];
                    $fonte = $usuario['fonte_identificacao'];
                    $dataHora = date('d/m/Y H:i', strtotime($usuario['timestamp']));
                    $ip = $dados['userIP'] ?? $dados['remote_addr'] ?? 'N/A';
                    $navegador = $dados['userAgent'] ?? 'N/A';
                    $sistema = $dados['platform'] ?? 'N/A';
                    
                    // Determinar classe do badge
                    $badgeClass = 'badge-unknown';
                    if (strpos($fonte, 'DNS') !== false) $badgeClass = 'badge-dns';
                    elseif (strpos($fonte, 'Windows') !== false) $badgeClass = 'badge-windows';
                    elseif (strpos($fonte, 'Fornecido') !== false) $badgeClass = 'badge-user';
                    
                    // Iniciais para avatar
                    $iniciais = substr($nome, 0, 2);
                ?>
                <tr data-index="<?php echo $index; ?>">
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar"><?php echo strtoupper($iniciais); ?></div>
                            <div>
                                <strong><?php echo htmlspecialchars($nome); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($dados['identificationType'] ?? ''); ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="user-badge <?php echo $badgeClass; ?>">
                            <?php echo htmlspecialchars($fonte); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($ip); ?></td>
                    <td><?php echo $dataHora; ?></td>
                    <td><?php echo htmlspecialchars(substr($navegador, 0, 40)); ?>...</td>
                    <td><?php echo htmlspecialchars($sistema); ?></td>
                    <td>
                        <button class="btn" onclick="showDetails(<?php echo $index; ?>)">
                            🔍 Detalhes
                        </button>
                    </td>
                </tr>
                <tr id="details-<?php echo $index; ?>" style="display: none;">
                    <td colspan="7">
                        <div class="user-details">
                            <h3>📋 Detalhes Completo do Acesso</h3>
                            
                            <div class="details-grid">
                                <div class="detail-item">
                                    <strong>👤 Usuário:</strong><br>
                                    <?php echo htmlspecialchars($nome); ?>
                                </div>
                                
                                <div class="detail-item">
                                    <strong>🎯 Fonte:</strong><br>
                                    <?php echo htmlspecialchars($fonte); ?>
                                </div>
                                
                                <div class="detail-item">
                                    <strong>🌐 IPs:</strong><br>
                                    Público: <?php echo htmlspecialchars($dados['userIP'] ?? 'N/A'); ?><br>
                                    Remoto: <?php echo htmlspecialchars($dados['remote_addr'] ?? 'N/A'); ?>
                                </div>
                                
                                <div class="detail-item">
                                    <strong>🖥️ Hostname:</strong><br>
                                    <?php echo htmlspecialchars($dados['reverse_dns'] ?? 'Não disponível'); ?>
                                </div>
                                
                                <div class="detail-item">
                                    <strong>🔗 URL:</strong><br>
                                    <?php echo htmlspecialchars($dados['url'] ?? 'N/A'); ?>
                                </div>
                                
                                <div class="detail-item">
                                    <strong>↪️ Referência:</strong><br>
                                    <?php echo htmlspecialchars($dados['referrer'] ?? 'Acesso direto'); ?>
                                </div>
                                
                                <div class="detail-item">
                                    <strong>🌍 Localização:</strong><br>
                                    <?php 
                                    if (isset($dados['location'])) {
                                        echo htmlspecialchars($dados['location']);
                                    } else {
                                        echo 'Não detectada';
                                    }
                                    ?>
                                </div>
                                
                                <div class="detail-item">
                                    <strong>💻 User Agent:</strong><br>
                                    <small><?php echo htmlspecialchars($dados['userAgent'] ?? 'N/A'); ?></small>
                                </div>
                                
                                <?php if (!empty($dados['extracted_hostname_info'])): ?>
                                <div class="detail-item">
                                    <strong>🔍 Hostname Info:</strong><br>
                                    <?php 
                                    foreach ($dados['extracted_hostname_info'] as $key => $value) {
                                        if ($value) {
                                            echo htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '<br>';
                                        }
                                    }
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($dados['possible_client_info'])): ?>
                                <div class="detail-item">
                                    <strong>🔐 Headers Auth:</strong><br>
                                    <?php 
                                    foreach ($dados['possible_client_info'] as $key => $value) {
                                        echo htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '<br>';
                                    }
                                    ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: 15px; text-align: right;">
                                <button class="btn" onclick="hideDetails(<?php echo $index; ?>)">
                                    Fechar
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div id="statsPanel" style="display: none;" class="user-table">
        <h2>📊 Estatísticas de Identificação</h2>
        
        <h3>Fontes de Identificação:</h3>
        <ul>
            <?php foreach ($fontesIdentificacao as $fonte => $count): 
                $percent = $totalAcessos > 0 ? round(($count / $totalAcessos) * 100, 1) : 0;
            ?>
            <li>
                <strong><?php echo htmlspecialchars($fonte); ?>:</strong>
                <?php echo $count; ?> acessos (<?php echo $percent; ?>%)
                <div style="background: #4a90e2; height: 20px; width: <?php echo $percent * 2; ?>px; 
                           margin: 5px 0; border-radius: 3px;"></div>
            </li>
            <?php endforeach; ?>
        </ul>
        
        <h3>Top 10 Usuários:</h3>
        <ol>
            <?php 
            $contagemUsuarios = [];
            foreach ($usuarios as $usuario) {
                $nome = $usuario['usuario'];
                if (!isset($contagemUsuarios[$nome])) {
                    $contagemUsuarios[$nome] = 0;
                }
                $contagemUsuarios[$nome]++;
            }
            arsort($contagemUsuarios);
            $top10 = array_slice($contagemUsuarios, 0, 10);
            
            foreach ($top10 as $nome => $count): ?>
            <li><strong><?php echo htmlspecialchars($nome); ?>:</strong> <?php echo $count; ?> acessos</li>
            <?php endforeach; ?>
        </ol>
    </div>

    <script>
        // Funções do painel
        
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const fonte = document.getElementById('fonteFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const rows = document.querySelectorAll('#userTable tbody tr[data-index]');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let showRow = true;
                
                // Filtro de busca
                if (search) {
                    let found = false;
                    cells.forEach(cell => {
                        if (cell.textContent.toLowerCase().includes(search)) {
                            found = true;
                        }
                    });
                    showRow = found;
                }
                
                // Filtro de fonte
                if (fonte && showRow) {
                    const fonteCell = cells[1];
                    if (!fonteCell.textContent.includes(fonte)) {
                        showRow = false;
                    }
                }
                
                // Filtro de data
                if (dateFilter && showRow) {
                    const dataCell = cells[3].textContent;
                    const hoje = new Date();
                    
                    switch(dateFilter) {
                        case 'today':
                            if (!dataCell.includes(hoje.toLocaleDateString('pt-BR').split('/')[0])) {
                                showRow = false;
                            }
                            break;
                        case 'week':
                            // Implementação simplificada
                            const semanaPassada = new Date(hoje.getTime() - 7 * 24 * 60 * 60 * 1000);
                            // Aqui você precisaria de uma lógica mais complexa
                            break;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
                
                // Esconder detalhes se a linha estiver oculta
                const detailsRow = document.getElementById('details-' + row.dataset.index);
                if (detailsRow) {
                    detailsRow.style.display = 'none';
                }
            });
        }
        
        function showDetails(index) {
            // Esconder todos os detalhes primeiro
            document.querySelectorAll('[id^="details-"]').forEach(row => {
                row.style.display = 'none';
            });
            
            // Mostrar os detalhes solicitados
            const detailsRow = document.getElementById('details-' + index);
            if (detailsRow) {
                detailsRow.style.display = 'table-row';
                detailsRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        function hideDetails(index) {
            const detailsRow = document.getElementById('details-' + index);
            if (detailsRow) {
                detailsRow.style.display = 'none';
            }
        }
        
        function exportCSV() {
            let csv = 'Usuário,Fonte,IP,Data/Hora,Navegador,Sistema,Hostname,URL\n';
            
            document.querySelectorAll('#userTable tbody tr[data-index]').forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    const rowData = [];
                    
                    cells.forEach((cell, index) => {
                        if (index < 6) { // Primeiras 6 colunas
                            let text = cell.textContent.trim();
                            
                            // Remover quebras de linha e vírgulas
                            text = text.replace(/\n/g, ' ').replace(/,/g, ';');
                            
                            // Adicionar aspas se contiver ponto e vírgula
                            if (text.includes(';')) {
                                text = '"' + text + '"';
                            }
                            
                            rowData.push(text);
                        }
                    });
                    
                    csv += rowData.join(',') + '\n';
                }
            });
            
            // Criar e baixar arquivo
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'usuarios_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function showStats() {
            const statsPanel = document.getElementById('statsPanel');
            if (statsPanel.style.display === 'none') {
                statsPanel.style.display = 'block';
                statsPanel.scrollIntoView({ behavior: 'smooth' });
            } else {
                statsPanel.style.display = 'none';
            }
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Ordenar por data (já está ordenado no PHP)
            console.log('Painel de usuários carregado com ' + <?php echo count($usuarios); ?> + ' registros.');
        });
    </script>
    
    <?php
    // Logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: admin.php');
        exit;
    }
    ?>
</body>
</html>