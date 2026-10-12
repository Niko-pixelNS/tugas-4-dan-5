<?php
/**
 * inc/db.php — koneksi & fungsi query MySQLi (prepared statement, aman dari SQL injection).
 * Dipakai halaman web (lewat init.php) dan web service (api/*.php).
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/koneksi.php';

function db(): mysqli
{
    global $koneksi;
    return $koneksi;
}
function db_q(string $sql, array $params = []): mysqli_stmt
{
    $st = db()->prepare($sql);
    if ($params) {
        $params = array_values($params);
        $tipe = '';
        foreach ($params as $v) $tipe .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
        $st->bind_param($tipe, ...$params);
    }
    $st->execute();
    return $st;
}
function db_rows(string $sql, array $params = []): array
{
    return db_q($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function db_row(string $sql, array $params = []): ?array
{
    return db_q($sql, $params)->get_result()->fetch_assoc() ?: null;
}
function db_val(string $sql, array $params = [])
{
    $r = db_q($sql, $params)->get_result()->fetch_row();
    return $r[0] ?? null;
}
function db_exec(string $sql, array $params = []): int
{
    return db_q($sql, $params)->affected_rows;
}
function db_id(): int
{
    return (int) db()->insert_id;
}
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
