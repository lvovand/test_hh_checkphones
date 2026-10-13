<?php
declare(strict_types=1);

/**
 * Нормализация, валидация и дедупликация заявок.
 * Чистые функции без состояния: один и тот же вход всегда даёт один и тот же выход.
 */
final class Sync
{
    /** Приводит телефон к +7XXXXXXXXXX или возвращает null, если номер невалиден. */
    public static function normalizePhone(mixed $raw): ?string
    {
        if (!is_string($raw) && !is_int($raw)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string)$raw);

        if (strlen($digits) === 10) {              // 9991234567 — без кода страны
            $digits = '7' . $digits;
        }
        if (strlen($digits) !== 11) {
            return null;
        }
        if ($digits[0] === '8') {                  // 8XXXXXXXXXX → 7XXXXXXXXXX
            $digits = '7' . substr($digits, 1);
        }
        if ($digits[0] !== '7') {
            return null;
        }
        return '+' . $digits;
    }

    /**
     * @param array $orders сырой массив из /api/orders
     * @return array{clients: array, stats: array}
     */
    public static function process(array $orders): array
    {
        $received = count($orders);
        $invalid  = 0;
        $valid    = 0;
        /** @var array<string, array> $best phone => лучшая запись */
        $best = [];

        foreach ($orders as $o) {
            if (!is_array($o)) {
                $invalid++;
                continue;
            }
            $phone = self::normalizePhone($o['phone'] ?? null);
            if ($phone === null) {
                $invalid++;
                continue;
            }
            $valid++;

            $rec = [
                'id'     => is_numeric($o['id'] ?? null) ? (int)$o['id'] : 0,
                'name'   => is_string($o['name'] ?? null) ? trim($o['name']) : '',
                'phone'  => $phone,
                'email'  => is_string($o['email'] ?? null) ? trim($o['email']) : '',
                'amount' => is_numeric($o['amount'] ?? null) ? $o['amount'] + 0 : 0,
                'ts'     => self::ts($o['created_at'] ?? null),
            ];

            if (!isset($best[$phone]) || self::better($rec, $best[$phone])) {
                $best[$phone] = $rec;
            }
        }

        $clients = [];
        foreach ($best as $rec) {
            $clients[] = [
                'name'   => $rec['name'],
                'phone'  => $rec['phone'],
                'email'  => $rec['email'],
                'amount' => $rec['amount'],
            ];
        }

        $unique = count($clients);
        return [
            'clients' => $clients,
            'stats'   => [
                'received'          => $received,
                'unique'            => $unique,
                'duplicates_merged' => $valid - $unique,
                'invalid_dropped'   => $invalid,
            ],
        ];
    }

    /** Правило дубля: больший amount; при равенстве — более поздний created_at; затем больший id (детерминизм). */
    private static function better(array $a, array $b): bool
    {
        if ($a['amount'] != $b['amount']) {
            return $a['amount'] > $b['amount'];
        }
        if ($a['ts'] !== $b['ts']) {
            return $a['ts'] > $b['ts'];
        }
        return $a['id'] > $b['id'];
    }

    private static function ts(mixed $v): int
    {
        if (!is_string($v) || $v === '') {
            return 0;
        }
        $t = strtotime($v);
        return $t === false ? 0 : $t;
    }
}
