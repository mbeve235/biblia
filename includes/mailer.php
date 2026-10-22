<?php
/**
 * Mailer da aplicação — cliente SMTP em PHP puro (sem dependências).
 *
 * Activado quando MAIL_HOST está definido no config.php; nessa altura
 * send_app_mail() envia por SMTP autenticado (ex.: Gmail com palavra-passe
 * de aplicação). Sem MAIL_HOST, a app cai na função mail() do PHP.
 */

declare(strict_types=1);

/**
 * Envia uma mensagem por SMTP autenticado. Lança RuntimeException em falha.
 * $to          endereço do destinatário
 * $subject     assunto (UTF-8, codificado automaticamente)
 * $body        corpo em texto simples (UTF-8)
 * $fromEmail   remetente (envelope From)
 * $fromName    nome apresentado do remetente
 * $toList      endereços adicionais (opcional; cabeçalho To)
 */
function smtp_send(
    string $to,
    string $subject,
    string $body,
    string $fromEmail,
    string $fromName = '',
    array $toList = []
): bool {
    $port    = (int) MAIL_PORT;
    $encrypt = strtolower((string) MAIL_ENCRYPT);

    $host = ($encrypt === 'ssl' ? 'ssl://' : '') . MAIL_HOST;
    $socket = @stream_socket_client(
        "{$host}:{$port}",
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
    );
    if (!$socket) {
        throw new RuntimeException("SMTP: ligação falhou — $errstr ($errno)");
    }
    stream_set_timeout($socket, 15);

    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break; // última linha da resposta (código + espaço)
            }
        }
        return $data;
    };
    $expect = function (string $prefixes, string $stage) use ($read, $socket): string {
        $resp = $read();
        if (strpos($prefixes, substr($resp, 0, 1)) === false) {
            throw new RuntimeException("SMTP ($stage): resposta inesperada — " . trim($resp));
        }
        return $resp;
    };
    $cmd = function (string $command, ?string $expectPrefixes = null, ?string $stage = null) use ($socket, $expect): ?string {
        fwrite($socket, $command . "\r\n");
        return $expectPrefixes === null ? null : $expect($expectPrefixes, $stage ?? $command);
    };

    // Saudação e EHLO
    $expect('23', 'greeting');
    $ehloHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $ehlo = '';
    foreach (explode("\n", (string) @$cmd('EHLO ' . $ehloHost, '23', 'ehlo')) as $line) {
        if (stripos($line, 'STARTTLS') !== false) {
            $ehlo = 'starttls';
        }
    }

    // STARTTLS se disponível (e ainda não estivermos em ssl://)
    if ($ehlo === 'starttls' && $encrypt !== 'ssl') {
        $cmd('STARTTLS', '2', 'starttls');
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP: negociação TLS falhou');
        }
        $cmd('EHLO ' . $ehloHost, '23', 'ehlo-tls');
    }

    // Autenticação (se houver credenciais)
    if (MAIL_USER !== '') {
        $cmd('AUTH LOGIN', '3', 'auth');
        $cmd(base64_encode(MAIL_USER), '3', 'auth-user');
        $cmd(base64_encode(MAIL_PASS), '235', 'auth-pass');
    }

    // Envelope
    $cmd('MAIL FROM:<' . $fromEmail . '>', '2', 'mail-from');
    foreach (array_merge([$to], $toList) as $rcpt) {
        if ($rcpt !== '') {
            $cmd('RCPT TO:<' . $rcpt . '>', '2', 'rcpt-to');
        }
    }

    // Dados
    $cmd('DATA', '3', 'data');
    $dotStuff = static function (string $s): string {
        return preg_replace('/^\./m', '..', $s);
    };
    $headers = [
        'From: ' . ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '') . "<{$fromEmail}>",
        'To: ' . implode(', ', array_filter(array_merge([$to], $toList))),
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    // Normaliza tudo para \n primeiro e converte para \r\n UMA única vez
    // (evita CR CR LF quando os cabeçalhos já contêm \r\n)
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $payload = implode("\n", $headers) . "\n\n" . $dotStuff($normalized);
    $payload = str_replace("\n", "\r\n", $payload);
    $cmd($payload . "\r\n.", '2', 'body');
    $cmd('QUIT', '2', 'quit');

    fclose($socket);
    return true;
}
