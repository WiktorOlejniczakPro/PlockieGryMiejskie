<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\Reward;
use App\Services\AuditLog;
use App\Services\RewardValidationService;

/**
 * Kreator nagród: katalog kodów rabatowych i kart podarunkowych,
 * które gracze wymieniają na punkty zdobyte w grach.
 * Przeglądać może każdy użytkownik panelu, tworzyć i zmieniać – administrator.
 */
final class RewardController
{
    public function index(Request $request): void
    {
        View::render('rewards/index', [
            'pageTitle' => 'Kreator nagród – PGM',
            'activeNav' => 'rewards',
            'rewards'   => Reward::all(),
            'stats'     => Reward::stats(),
            'canManage' => Auth::isAdmin(),
            'extraStyles' => ['css/rewards.css'],
        ], 'layouts/panel');
    }

    public function create(Request $request): void
    {
        $this->requireAdmin();
        $this->renderForm(null, [
            'reward_type' => 'discount_code', 'discount_kind' => 'percent', 'cost_points' => '50',
            'per_player_limit' => '1', 'is_active' => 1,
        ], []);
    }

    public function store(Request $request): void
    {
        $this->requireAdmin();
        $validator = new RewardValidationService();
        [$data, $errors] = $validator->validate($request->all());

        [$codes, $codesError] = $validator->parseCodes((string) $request->input('codes', ''));
        if ($codesError !== null) {
            $errors['codes'] = $codesError;
        }
        if ($errors) {
            $this->renderForm(null, $request->all(), $errors);
            return;
        }

        $id = Reward::create($data, Auth::id());
        $added = $codes ? Reward::addCodes($id, $codes) : 0;
        AuditLog::record('reward.created', Auth::id(), 'reward', $id, ['codes' => $added]);

        Session::flash('success', 'Nagroda została utworzona' . ($added ? " i dodano {$added} " . plural_pl($added, 'kod', 'kody', 'kodów') : '') . '.');
        redirect('/panel/nagrody/' . $id . '/edycja');
    }

    public function edit(Request $request, int $id): void
    {
        $this->requireAdmin();
        $reward = $this->findOr404($id);
        $this->renderForm($reward, $reward, []);
    }

    public function update(Request $request, int $id): void
    {
        $this->requireAdmin();
        $reward = $this->findOr404($id);
        [$data, $errors] = (new RewardValidationService())->validate($request->all());
        if ($errors) {
            $this->renderForm($reward, $request->all(), $errors);
            return;
        }
        Reward::update($id, $data);
        AuditLog::record('reward.updated', Auth::id(), 'reward', $id);
        Session::flash('success', 'Zmiany w nagrodzie zostały zapisane.');
        redirect('/panel/nagrody/' . $id . '/edycja');
    }

    public function toggle(Request $request, int $id): void
    {
        $this->requireAdmin();
        $reward = $this->findOr404($id);
        $active = !(bool) $reward['is_active'];
        Reward::setActive($id, $active);
        AuditLog::record($active ? 'reward.activated' : 'reward.deactivated', Auth::id(), 'reward', $id);
        Session::flash('success', $active
            ? 'Nagroda „' . $reward['title'] . '” jest teraz dostępna dla graczy.'
            : 'Nagroda „' . $reward['title'] . '” została ukryta przed graczami.');
        redirect('/panel/nagrody');
    }

    public function destroy(Request $request, int $id): void
    {
        $this->requireAdmin();
        $reward = $this->findOr404($id);
        if ((int) $reward['codes_redeemed'] > 0) {
            Session::flash('error', 'Tej nagrody nie można usunąć, bo gracze odebrali już jej kody. Możesz ją dezaktywować.');
            redirect('/panel/nagrody/' . $id . '/edycja');
        }
        Reward::delete($id);
        AuditLog::record('reward.deleted', Auth::id(), 'reward', $id, ['title' => $reward['title']]);
        Session::flash('success', 'Nagroda „' . $reward['title'] . '” została usunięta.');
        redirect('/panel/nagrody');
    }

    public function addCodes(Request $request, int $id): void
    {
        $this->requireAdmin();
        $this->findOr404($id);
        [$codes, $error] = (new RewardValidationService())->parseCodes((string) $request->input('codes', ''));

        if ($error !== null) {
            Session::flash('error', $error);
        } elseif (!$codes) {
            Session::flash('error', 'Wpisz co najmniej jeden kod (każdy w osobnej linii).');
        } else {
            $added = Reward::addCodes($id, $codes);
            $skipped = count($codes) - $added;
            AuditLog::record('reward.codes_added', Auth::id(), 'reward', $id, ['added' => $added]);
            Session::flash('success', "Dodano {$added} " . plural_pl($added, 'kod', 'kody', 'kodów')
                . ($skipped ? " (pominięto {$skipped} – już były w puli)" : '') . '.');
        }
        redirect('/panel/nagrody/' . $id . '/edycja#kody');
    }

    public function deleteCode(Request $request, int $id, int $codeId): void
    {
        $this->requireAdmin();
        $this->findOr404($id);
        if (Reward::deleteAvailableCode($id, $codeId)) {
            AuditLog::record('reward.code_deleted', Auth::id(), 'reward', $id);
            Session::flash('success', 'Kod został usunięty z puli.');
        } else {
            Session::flash('error', 'Nie można usunąć tego kodu – nie istnieje albo został już wydany graczowi.');
        }
        redirect('/panel/nagrody/' . $id . '/edycja#kody');
    }

    // ------------------------------------------------------------------

    private function renderForm(?array $reward, array $values, array $errors): void
    {
        if ($errors) {
            http_response_code(422);
        }
        View::render('rewards/form', [
            'pageTitle'    => ($reward ? 'Edycja nagrody' : 'Nowa nagroda') . ' – PGM',
            'activeNav'    => 'rewards',
            'reward'       => $reward,
            'values'       => $values,
            'errors'       => $errors,
            'codes'        => $reward ? Reward::codes((int) $reward['id']) : [],
            'extraStyles'  => ['css/rewards.css'],
            'extraScripts' => ['js/rewards.js'],
        ], 'layouts/panel');
    }

    private function findOr404(int $id): array
    {
        $reward = Reward::find($id);
        if ($reward === null) {
            throw new HttpException(404, 'Nie znaleziono nagrody.');
        }
        return $reward;
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new HttpException(403, 'Kreator nagród jest dostępny tylko dla administratorów.');
        }
    }
}
