<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Walidacja formularza kreatora nagród oraz list kodów.
 * validate() zwraca [dane gotowe do zapisu, błędy ['pole' => 'komunikat']].
 */
final class RewardValidationService
{
    public const TYPES = ['discount_code', 'gift_card'];
    public const DISCOUNT_KINDS = ['percent', 'amount'];
    public const MAX_CODES_PER_UPLOAD = 1000;

    /** @var array<string, string> */
    private array $errors = [];

    public function validate(array $in): array
    {
        $this->errors = [];

        $type = in_array($in['reward_type'] ?? null, self::TYPES, true) ? $in['reward_type'] : null;
        if ($type === null) {
            $this->errors['reward_type'] = 'Wybierz rodzaj nagrody.';
            $type = 'discount_code';
        }

        $kind = null;
        if ($type === 'discount_code') {
            $kind = in_array($in['discount_kind'] ?? null, self::DISCOUNT_KINDS, true) ? $in['discount_kind'] : null;
            if ($kind === null) {
                $this->errors['discount_kind'] = 'Wybierz rodzaj rabatu: procentowy lub kwotowy.';
                $kind = 'percent';
            }
        }

        $value = $this->decimal($in, 'value_amount', true);
        if ($value !== null) {
            if ($kind === 'percent' && ($value < 1 || $value > 100)) {
                $this->errors['value_amount'] = 'Rabat procentowy musi wynosić od 1 do 100%.';
            } elseif ($kind !== 'percent' && ($value < 1 || $value > 100000)) {
                $this->errors['value_amount'] = 'Kwota musi wynosić od 1 do 100 000 zł.';
            }
        }

        $data = [
            'reward_type'      => $type,
            'discount_kind'    => $kind,
            'title'            => $this->text($in, 'title', 150, 'Podaj nazwę nagrody.'),
            'partner_name'     => $this->text($in, 'partner_name', 120, 'Podaj nazwę sklepu lub partnera.'),
            'partner_url'      => $this->url($in, 'partner_url'),
            'description'      => $this->text($in, 'description', 2000),
            'value_amount'     => $value ?? 0,
            'min_order_amount' => $type === 'discount_code' ? $this->decimal($in, 'min_order_amount', false) : null,
            'cost_points'      => $this->int($in, 'cost_points', 1, 100000, null, 'Koszt nagrody: od 1 do 100 000 punktów.', true),
            'per_player_limit' => $this->int($in, 'per_player_limit', 1, 100, 1, 'Limit na gracza: od 1 do 100.') ?? 1,
            'terms'            => $this->text($in, 'terms', 2000),
            'valid_from'       => $this->date($in, 'valid_from'),
            'valid_until'      => $this->date($in, 'valid_until'),
            'is_active'        => !empty($in['is_active']) ? 1 : 0,
        ];

        if ($data['min_order_amount'] !== null && $data['min_order_amount'] > 1000000) {
            $this->errors['min_order_amount'] = 'Minimalna wartość zakupów jest za duża.';
        }
        if ($data['valid_from'] && $data['valid_until'] && $data['valid_until'] < $data['valid_from']) {
            $this->errors['valid_until'] = 'Data końca ważności nie może być wcześniejsza niż data początku.';
        }

        return [$data, $this->errors];
    }

    /**
     * Zamienia tekst z pola „kody” (jeden kod w linii, także oddzielone przecinkiem/średnikiem)
     * na listę unikalnych kodów.
     * @return array{0: list<string>, 1: ?string} [kody, błąd]
     */
    public function parseCodes(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $codes = [];
        foreach ($parts as $part) {
            $code = trim($part);
            if ($code === '') {
                continue;
            }
            if (mb_strlen($code) > 64) {
                return [[], 'Kod „' . mb_substr($code, 0, 20) . '…” jest za długi (maks. 64 znaki).'];
            }
            if (!preg_match('/^[A-Za-z0-9\-_.]+$/', $code)) {
                return [[], 'Kod „' . $code . '” zawiera niedozwolone znaki. Dozwolone: litery, cyfry, myślnik, podkreślnik i kropka.'];
            }
            $codes[$code] = $code;
        }
        if (count($codes) > self::MAX_CODES_PER_UPLOAD) {
            return [[], 'Jednorazowo możesz dodać najwyżej ' . self::MAX_CODES_PER_UPLOAD . ' kodów.'];
        }
        return [array_values($codes), null];
    }

    // ---------- pomocnicze ----------

    private function text(array $in, string $key, int $max, ?string $requiredMsg = null): ?string
    {
        $v = is_scalar($in[$key] ?? null) ? trim((string) $in[$key]) : '';
        $v = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
        if ($v === '') {
            if ($requiredMsg !== null) {
                $this->errors[$key] = $requiredMsg;
            }
            return null;
        }
        if (mb_strlen($v) > $max) {
            $this->errors[$key] = "Tekst jest za długi (maksymalnie {$max} znaków).";
            return mb_substr($v, 0, $max);
        }
        return $v;
    }

    private function decimal(array $in, string $key, bool $required): ?float
    {
        $raw = is_scalar($in[$key] ?? null) ? str_replace([' ', ','], ['', '.'], trim((string) $in[$key])) : '';
        if ($raw === '') {
            if ($required) {
                $this->errors[$key] = 'Podaj wartość.';
            }
            return null;
        }
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $raw)) {
            $this->errors[$key] = 'Podaj liczbę, np. 15 albo 12,50.';
            return null;
        }
        return (float) $raw;
    }

    private function int(array $in, string $key, int $min, int $max, ?int $default, string $msg, bool $required = false): ?int
    {
        $raw = is_scalar($in[$key] ?? null) ? trim((string) $in[$key]) : '';
        if ($raw === '') {
            if ($required) {
                $this->errors[$key] = $msg;
            }
            return $default;
        }
        if (!ctype_digit($raw) || (int) $raw < $min || (int) $raw > $max) {
            $this->errors[$key] = $msg;
            return $default;
        }
        return (int) $raw;
    }

    private function url(array $in, string $key): ?string
    {
        $v = is_scalar($in[$key] ?? null) ? trim((string) $in[$key]) : '';
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > 255 || !filter_var($v, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $v)) {
            $this->errors[$key] = 'Podaj pełny adres strony, np. https://sklep.pl';
            return null;
        }
        return $v;
    }

    private function date(array $in, string $key): ?string
    {
        $v = is_scalar($in[$key] ?? null) ? trim((string) $in[$key]) : '';
        if ($v === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if ($d === false || $d->format('Y-m-d') !== $v) {
            $this->errors[$key] = 'Nieprawidłowa data.';
            return null;
        }
        return $v;
    }
}
