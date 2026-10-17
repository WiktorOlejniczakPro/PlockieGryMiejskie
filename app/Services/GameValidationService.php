<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Walidacja danych gry przesłanych z edytora.
 *
 * normalize() – przygotowuje dane do zapisu (typy, przycięcie, długości, zakresy)
 *               i sprawdza minimum potrzebne do zapisania SZKICU:
 *               tytuł gry, nazwy punktów, poprawne współrzędne oraz tytuł
 *               i treść zadania, jeśli zadanie jest włączone.
 * forPublish() – dodatkowe reguły kompletności dla gier opublikowanych.
 *
 * Każdy błąd: ['path' => 'waypoints.2.task.title', 'field' => 'title',
 *              'waypoint' => 2 (indeks od 0) lub null, 'message' => '...'].
 */
final class GameValidationService
{
    public const MAX_WAYPOINTS = 200;
    public const MAX_OPTIONS = 10;

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];
    public const TASK_TYPES = ['single_choice', 'multiple_choice', 'open_answer', 'photo', 'video', 'info'];
    public const CHOICE_TYPES = ['single_choice', 'multiple_choice'];
    public const UNLOCK_MODES = ['always', 'on_arrival', 'after_previous'];

    /** @var list<array{path:string, field:string, waypoint:?int, message:string}> */
    private array $errors = [];

    /**
     * @param array<int, array{id:int, is_other:int}> $categories aktywne kategorie
     * @return array{0: array, 1: list<array>} [dane po normalizacji, błędy]
     */
    public function normalize(array $input, array $categories): array
    {
        $this->errors = [];

        $data = [
            'title'                      => $this->text($input, 'title', 150, true, 'Podaj tytuł gry.'),
            'short_description'          => $this->text($input, 'short_description', 300),
            'description'                => $this->text($input, 'description', 10000),
            'estimated_duration_minutes' => $this->int($input, 'estimated_duration_minutes', 1, 1440, null, 'Czas gry musi wynosić od 1 do 1440 minut.'),
            'difficulty'                 => in_array($input['difficulty'] ?? null, self::DIFFICULTIES, true) ? $input['difficulty'] : 'medium',
            'completion_points'          => $this->int($input, 'completion_points', 0, 1000, 5, 'Punkty za ukończenie: od 0 do 1000.') ?? 5,
            'available_from'             => $this->date($input, 'available_from'),
            'available_until'            => $this->date($input, 'available_until'),
        ];

        if ($data['available_from'] && $data['available_until'] && $data['available_until'] <= $data['available_from']) {
            $this->error('available_until', 'available_until', null, 'Koniec dostępności musi być późniejszy niż początek.');
        }

        // Kategorie: tylko istniejące i aktywne
        $validIds = [];
        $otherId = null;
        foreach ($categories as $c) {
            $validIds[(int) $c['id']] = true;
            if ((int) $c['is_other'] === 1) {
                $otherId = (int) $c['id'];
            }
        }
        $selected = [];
        foreach ((array) ($input['categories'] ?? []) as $id) {
            if (is_numeric($id) && isset($validIds[(int) $id])) {
                $selected[(int) $id] = (int) $id;
            } else {
                $this->error('categories', 'categories', null, 'Wybrano nieprawidłową kategorię.');
            }
        }
        $data['categories'] = array_values($selected);
        $data['custom_category'] = ($otherId !== null && isset($selected[$otherId]))
            ? $this->text($input, 'custom_category', 60)
            : null;
        $data['_other_category_id'] = $otherId;

        // Punkty trasy
        $waypoints = $input['waypoints'] ?? [];
        if (!is_array($waypoints)) {
            $waypoints = [];
        }
        if (count($waypoints) > self::MAX_WAYPOINTS) {
            $this->error('waypoints', 'waypoints', null, 'Gra może mieć najwyżej ' . self::MAX_WAYPOINTS . ' punktów.');
            $waypoints = array_slice($waypoints, 0, self::MAX_WAYPOINTS);
        }
        $data['waypoints'] = [];
        foreach (array_values($waypoints) as $i => $wp) {
            $data['waypoints'][] = $this->waypoint(is_array($wp) ? $wp : [], $i);
        }

        return [$data, $this->errors];
    }

    /** Reguły kompletności sprawdzane przed publikacją (i przy zapisie opublikowanej gry). */
    public function forPublish(array $data): array
    {
        $this->errors = [];

        if (($data['short_description'] ?? null) === null && ($data['description'] ?? null) === null) {
            $this->error('short_description', 'short_description', null, 'Opublikowana gra musi mieć opis (krótki lub pełny).');
        }
        if (!$data['waypoints']) {
            $this->error('waypoints', 'waypoints', null, 'Opublikowana gra musi mieć co najmniej jeden punkt trasy.');
        }
        if (in_array($data['_other_category_id'], $data['categories'], true) && !$data['custom_category']) {
            $this->error('custom_category', 'custom_category', null, 'Wybrano kategorię „Inne” – wpisz jej nazwę.');
        }

        foreach ($data['waypoints'] as $i => $wp) {
            $task = $wp['task'];
            if ($task === null) {
                continue;
            }
            $n = $i + 1;
            $base = "waypoints.{$i}.task";
            if (in_array($task['task_type'], self::CHOICE_TYPES, true)) {
                $correct = count(array_filter($task['options'], static fn ($o) => $o['is_correct']));
                if (count($task['options']) < 2) {
                    $this->error("{$base}.options", 'options', $i, "Punkt {$n}: pytanie zamknięte musi mieć co najmniej 2 odpowiedzi.");
                } elseif ($task['task_type'] === 'single_choice' && $correct !== 1) {
                    $this->error("{$base}.options", 'options', $i, "Punkt {$n}: zaznacz dokładnie jedną poprawną odpowiedź.");
                } elseif ($task['task_type'] === 'multiple_choice' && $correct < 1) {
                    $this->error("{$base}.options", 'options', $i, "Punkt {$n}: zaznacz co najmniej jedną poprawną odpowiedź.");
                }
            }
            if ($task['task_type'] === 'open_answer' && !$task['expected_answer'] && !$task['evaluation_instructions']) {
                $this->error("{$base}.expected_answer", 'expected_answer', $i, "Punkt {$n}: podaj oczekiwaną odpowiedź lub instrukcję oceny.");
            }
            if (in_array($task['task_type'], ['photo', 'video'], true) && !$task['media_requirement']) {
                $this->error("{$base}.media_requirement", 'media_requirement', $i, "Punkt {$n}: opisz, jakie zdjęcie lub film ma przesłać gracz.");
            }
        }

        return $this->errors;
    }

    // ------------------------------------------------------------------

    private function waypoint(array $wp, int $i): array
    {
        $n = $i + 1;
        $p = "waypoints.{$i}";

        $name = $this->text($wp, 'name', 120, false, null, $p, $i);
        if ($name === null) {
            $this->error("{$p}.name", 'name', $i, "Punkt {$n}: podaj nazwę punktu.");
        }

        $lat = $this->coordinate($wp['latitude'] ?? null, -90, 90);
        $lng = $this->coordinate($wp['longitude'] ?? null, -180, 180);
        if ($lat === null) {
            $this->error("{$p}.latitude", 'latitude', $i, "Punkt {$n}: szerokość geograficzna musi być liczbą od -90 do 90.");
        }
        if ($lng === null) {
            $this->error("{$p}.longitude", 'longitude', $i, "Punkt {$n}: długość geograficzna musi być liczbą od -180 do 180.");
        }

        return [
            'id'          => (isset($wp['id']) && is_numeric($wp['id']) && (int) $wp['id'] > 0) ? (int) $wp['id'] : null,
            'name'        => $name ?? '',
            'description' => $this->text($wp, 'description', 5000, false, null, $p, $i),
            'player_hint' => $this->text($wp, 'player_hint', 500, false, null, $p, $i),
            'latitude'    => $lat,
            'longitude'   => $lng,
            'task'        => (isset($wp['task']) && is_array($wp['task'])) ? $this->task($wp['task'], $i) : null,
        ];
    }

    private function task(array $t, int $i): array
    {
        $n = $i + 1;
        $p = "waypoints.{$i}.task";

        $type = in_array($t['task_type'] ?? null, self::TASK_TYPES, true) ? $t['task_type'] : null;
        if ($type === null) {
            $this->error("{$p}.task_type", 'task_type', $i, "Punkt {$n}: wybierz typ zadania.");
            $type = 'info';
        }

        $title = $this->text($t, 'title', 150, false, null, $p, $i);
        if ($title === null) {
            $this->error("{$p}.title", 'title', $i, "Punkt {$n}: podaj tytuł zadania (albo wyłącz zadanie).");
        }
        $description = $this->text($t, 'description', 5000, false, null, $p, $i);
        if ($description === null) {
            $this->error("{$p}.description", 'description', $i, "Punkt {$n}: wpisz treść polecenia zadania.");
        }

        $isChoice = in_array($type, self::CHOICE_TYPES, true);
        $isMedia = in_array($type, ['photo', 'video'], true);

        $options = [];
        if ($isChoice) {
            $raw = is_array($t['options'] ?? null) ? array_values($t['options']) : [];
            if (count($raw) > self::MAX_OPTIONS) {
                $this->error("{$p}.options", 'options', $i, "Punkt {$n}: najwyżej " . self::MAX_OPTIONS . ' odpowiedzi.');
                $raw = array_slice($raw, 0, self::MAX_OPTIONS);
            }
            foreach ($raw as $opt) {
                $text = is_array($opt) && is_string($opt['text'] ?? null) ? trim($opt['text']) : '';
                if ($text === '') {
                    continue; // puste wiersze odpowiedzi pomijamy
                }
                if (mb_strlen($text) > 300) {
                    $this->error("{$p}.options", 'options', $i, "Punkt {$n}: odpowiedź może mieć najwyżej 300 znaków.");
                    $text = mb_substr($text, 0, 300);
                }
                $options[] = ['text' => $text, 'is_correct' => !empty($opt['is_correct'])];
            }
            if ($type === 'single_choice') {
                // Przy jednokrotnym wyborze zostaje najwyżej jedna poprawna odpowiedź
                $seen = false;
                foreach ($options as &$o) {
                    $o['is_correct'] = $o['is_correct'] && !$seen;
                    $seen = $seen || $o['is_correct'];
                }
                unset($o);
            }
        }

        $unlock = in_array($t['unlock_mode'] ?? null, self::UNLOCK_MODES, true) ? $t['unlock_mode'] : 'on_arrival';

        return [
            'task_type'               => $type,
            'title'                   => $title ?? '',
            'description'             => $description ?? '',
            'hint'                    => $this->text($t, 'hint', 500, false, null, $p, $i),
            'max_points'              => $type === 'info'
                                          ? 0
                                          : ($this->int($t, 'max_points', 0, 1000, 1, "Punkt {$n}: punkty za zadanie od 0 do 1000.", $p, $i) ?? 1),
            'expected_answer'         => $type === 'open_answer' ? $this->text($t, 'expected_answer', 5000, false, null, $p, $i) : null,
            'evaluation_instructions' => ($type === 'open_answer' || $isMedia) ? $this->text($t, 'evaluation_instructions', 5000, false, null, $p, $i) : null,
            'media_requirement'       => $isMedia ? $this->text($t, 'media_requirement', 500, false, null, $p, $i) : null,
            'media_max_seconds'       => $type === 'video'
                                          ? $this->int($t, 'media_max_seconds', 1, 600, null, "Punkt {$n}: długość filmu od 1 do 600 sekund.", $p, $i)
                                          : null,
            'unlock_mode'             => $unlock,
            'unlock_radius_m'         => $this->int($t, 'unlock_radius_m', 5, 1000, 30, "Punkt {$n}: promień odblokowania od 5 do 1000 m.", $p, $i) ?? 30,
            'options'                 => $options,
        ];
    }

    // ---------- pomocnicze ----------

    private function text(array $src, string $key, int $max, bool $required = false, ?string $requiredMsg = null,
                          string $prefix = '', ?int $wp = null): ?string
    {
        $value = $src[$key] ?? null;
        $value = is_scalar($value) ? trim((string) $value) : '';
        // usuń znaki sterujące (poza nową linią i tabulatorem)
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $path = $prefix !== '' ? "{$prefix}.{$key}" : $key;

        if ($value === '') {
            if ($required) {
                $this->error($path, $key, $wp, $requiredMsg ?? 'To pole jest wymagane.');
            }
            return null;
        }
        if (mb_strlen($value) > $max) {
            $label = $wp !== null ? 'Punkt ' . ($wp + 1) . ': ' : '';
            $this->error($path, $key, $wp, "{$label}tekst jest za długi (maksymalnie {$max} znaków).");
            return mb_substr($value, 0, $max);
        }
        return $value;
    }

    private function int(array $src, string $key, int $min, int $max, ?int $default, string $msg,
                         string $prefix = '', ?int $wp = null): ?int
    {
        $value = $src[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value) || (float) $value != (int) $value || (int) $value < $min || (int) $value > $max) {
            $this->error($prefix !== '' ? "{$prefix}.{$key}" : $key, $key, $wp, $msg);
            return $default;
        }
        return (int) $value;
    }

    private function coordinate(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $f = (float) $value;
        if (!is_finite($f) || $f < $min || $f > $max) {
            return null;
        }
        return round($f, 7);
    }

    private function date(array $src, string $key): ?string
    {
        $value = $src[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $d = DateTimeImmutable::createFromFormat('!' . $format, trim($value));
            if ($d !== false && $d->format($format) === trim($value)) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        $this->error($key, $key, null, 'Nieprawidłowa data dostępności.');
        return null;
    }

    private function error(string $path, string $field, ?int $wp, string $message): void
    {
        $this->errors[] = ['path' => $path, 'field' => $field, 'waypoint' => $wp, 'message' => $message];
    }
}
