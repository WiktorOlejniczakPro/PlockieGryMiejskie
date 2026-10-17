<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLog;

/**
 * Panel: nagrody w sklepie punktów aplikacji mobilnej (tylko administrator).
 * Nagród się nie usuwa (są w historii wymian graczy) – można je wyłączyć.
 */
final class RewardController
{
    public const TYPES = [
        'discount' => 'Zniżka',
        'coupon'   => 'Kupon / voucher',
        'item'     => 'Nagroda rzeczowa',
        'other'    => 'Inna',
    ];

    public function index(Request $request): void
    {
        $user = self::admin();
        $rewards = Database::query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM reward_redemptions x WHERE x.reward_id = r.id AND x.status <> 'cancelled') AS issued
               FROM rewards r ORDER BY r.is_active DESC, r.cost_points, r.id"
        )->fetchAll();
        $redemptions = Database::query(
            'SELECT x.id, x.code, x.status, x.cost_points, x.created_at, x.used_at, r.title, p.nickname
               FROM reward_redemptions x
               JOIN rewards r ON r.id = x.reward_id
               JOIN players p ON p.id = x.player_id
              ORDER BY x.created_at DESC, x.id DESC LIMIT 50'
        )->fetchAll();

        View::render('rewards/index', [
            'pageTitle'   => 'Nagrody – PGM',
            'activeNav'   => 'rewards',
            'user'        => $user,
            'rewards'     => $rewards,
            'redemptions' => $redemptions,
        ], 'layouts/panel');
    }

    public function create(Request $request): void
    {
        $this->form(self::admin(), null);
    }

    public function edit(Request $request, int $id): void
    {
        $user = self::admin();
        $reward = Database::query('SELECT * FROM rewards WHERE id = ?', [$id])->fetch()
            ?: throw new HttpException(404, 'Nie znaleziono nagrody.');
        $this->form($user, $reward);
    }

    public function store(Request $request): void
    {
        $user = self::admin();
        $data = $this->validated($request, null);
        Database::query(
            'INSERT INTO rewards (title, description, reward_type, partner_name, cost_points, stock, valid_until,
                                  redemption_instructions, is_active, is_sample, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [...array_values($data), (int) $user['id']]
        );
        $id = (int) Database::connection()->lastInsertId();
        AuditLog::record('reward.created', (int) $user['id'], 'reward', $id);
        Session::flash('success', 'Dodano nagrodę „' . $data['title'] . '”.');
        redirect('/panel/nagrody');
    }

    public function update(Request $request, int $id): void
    {
        $user = self::admin();
        if (!Database::query('SELECT 1 FROM rewards WHERE id = ?', [$id])->fetchColumn()) {
            throw new HttpException(404, 'Nie znaleziono nagrody.');
        }
        $data = $this->validated($request, $id);
        Database::query(
            'UPDATE rewards SET title = ?, description = ?, reward_type = ?, partner_name = ?, cost_points = ?, stock = ?,
                    valid_until = ?, redemption_instructions = ?, is_active = ?, is_sample = ?
              WHERE id = ?',
            [...array_values($data), $id]
        );
        AuditLog::record('reward.updated', (int) $user['id'], 'reward', $id);
        Session::flash('success', 'Zapisano zmiany nagrody „' . $data['title'] . '”.');
        redirect('/panel/nagrody');
    }

    /** Oznaczenie wymiany jako zrealizowanej (np. po okazaniu kodu przy odbiorze). */
    public function markUsed(Request $request, int $id): void
    {
        $user = self::admin();
        $updated = Database::query(
            "UPDATE reward_redemptions SET status = 'used', used_at = NOW() WHERE id = ? AND status = 'issued'",
            [$id]
        )->rowCount();
        if ($updated) {
            AuditLog::record('reward.redemption_used', (int) $user['id'], 'reward_redemption', $id);
            Session::flash('success', 'Oznaczono kod jako wykorzystany.');
        } else {
            Session::flash('error', 'Tej wymiany nie można oznaczyć jako wykorzystanej.');
        }
        redirect('/panel/nagrody');
    }

    private function form(array $user, ?array $reward): void
    {
        View::render('rewards/form', [
            'pageTitle' => ($reward ? 'Edycja nagrody' : 'Nowa nagroda') . ' – PGM',
            'activeNav' => 'rewards',
            'user'      => $user,
            'reward'    => $reward,
            'types'     => self::TYPES,
        ], 'layouts/panel');
    }

    /** @return array<string, mixed> kolejność = kolumny w INSERT/UPDATE */
    private function validated(Request $request, ?int $id): array
    {
        $errors = [];
        $title = mb_substr($request->string('title'), 0, 150);
        if ($title === '') {
            $errors[] = 'Podaj nazwę nagrody.';
        }
        $cost = $request->string('cost_points');
        if (!ctype_digit($cost) || (int) $cost < 1 || (int) $cost > 1000000) {
            $errors[] = 'Koszt w punktach: liczba od 1 do 1 000 000.';
        }
        $stock = $request->string('stock');
        if ($stock !== '' && (!ctype_digit($stock) || (int) $stock > 1000000)) {
            $errors[] = 'Liczba sztuk: puste (bez limitu) albo liczba od 0 do 1 000 000.';
        }
        $type = $request->string('reward_type');
        if (!isset(self::TYPES[$type])) {
            $errors[] = 'Wybierz rodzaj nagrody.';
        }
        $validUntil = $request->string('valid_until');
        $validUntilDb = null;
        if ($validUntil !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $validUntil);
            if ($date === false) {
                $errors[] = 'Nieprawidłowa data ważności.';
            } else {
                $validUntilDb = $date->format('Y-m-d H:i:00');
            }
        }

        if ($errors) {
            Session::flashInput($request->all());
            Session::flash('error', implode(' ', $errors));
            redirect($id === null ? '/panel/nagrody/nowa' : '/panel/nagrody/' . $id . '/edycja');
        }

        $text = static fn (string $key, int $max): ?string => ($v = mb_substr($request->string($key), 0, $max)) === '' ? null : $v;
        return [
            'title'                   => $title,
            'description'             => $text('description', 2000),
            'reward_type'             => $type,
            'partner_name'            => $text('partner_name', 150),
            'cost_points'             => (int) $cost,
            'stock'                   => $stock === '' ? null : (int) $stock,
            'valid_until'             => $validUntilDb,
            'redemption_instructions' => $text('redemption_instructions', 2000),
            'is_active'               => $request->input('is_active') ? 1 : 0,
            'is_sample'               => $request->input('is_sample') ? 1 : 0,
        ];
    }

    private static function admin(): array
    {
        if (!Auth::isAdmin()) {
            throw new HttpException(403, 'Nagrodami zarządza administrator systemu.');
        }
        return Auth::user();
    }
}
