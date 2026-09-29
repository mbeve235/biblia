<?php
/**
 * Logger simples em ficheiro (logs/app-YYYY-MM-DD.log).
 */

declare(strict_types=1);

function log_message(string $level, string $message, array $context = []): void
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf(
        "[%s] %s: %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );
    @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/** Regista acções administrativas na tabela activity_log. */
function log_activity(?int $userId, string $action, string $detail = ''): void
{
    try {
        db_exec(
            'INSERT INTO activity_log (user_id, action, detail, ip) VALUES (?, ?, ?, ?)',
            [$userId, mb_substr($action, 0, 60), mb_substr($detail, 0, 255), mb_substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]
        );
    } catch (Throwable $e) {
        log_message('error', 'activity_log: ' . $e->getMessage());
    }
    log_message('info', $action . ($detail ? ' — ' . $detail : ''), ['user_id' => $userId]);
}
