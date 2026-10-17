<?php
declare(strict_types=1);

/**
 * Generuje dane miejsc (zabytki, szkoły, przystanki, rower miejski,
 * obiekty komunalne, osiedla) oraz słownik ulic Płocka z plików
 * w database/dane-zrodlowe/ i wstawia je do database/seed.sql
 * pomiędzy znaczniki "MIEJSCA – POCZĄTEK" i "MIEJSCA – KONIEC".
 *
 * Co robi po drodze:
 *  - przelicza współrzędne z układu PUWG 1992 (EPSG:2180) na WGS84,
 *  - naprawia uszkodzone polskie znaki ("P3ocku", "Sob?tka", "Dluga"),
 *    korzystając ze słownika słów zbudowanego z poprawnie zapisanych
 *    źródeł (nazwy ulic TERYT, nazwy szkół z BDOT10k, obiekty WSK),
 *  - usuwa duplikaty i pomija dane techniczne (stacje transformatorowe,
 *    przepompownie, punkty pomiarowe liczników).
 *
 * Użycie (w katalogu pgm):
 *   C:\xampp\php\php.exe bin\build-places-seed.php
 *   php bin/build-places-seed.php --report   (lista poprawionych nazw)
 *
 * Skrypt nie łączy się z bazą – tylko przepisuje seed.sql.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const SRC = __DIR__ . '/../database/dane-zrodlowe';
const SEED = __DIR__ . '/../database/seed.sql';
const MARK_BEGIN = '-- >>> MIEJSCA – POCZĄTEK (generowane przez bin/build-places-seed.php, nie edytuj ręcznie)';
const MARK_END = '-- <<< MIEJSCA – KONIEC';

$report = in_array('--report', $argv, true);
$fixLog = [];

// =====================================================================
//  Kategorie miejsc: slug => [nazwa, źródło danych]
// =====================================================================
$categories = [
    'zabytek-rejestr'        => ['Zabytek – rejestr zabytków', 'Rejestr zabytków, UM Płock'],
    'zabytek-ewidencja'      => ['Zabytek – gminna ewidencja zabytków', 'Gminna ewidencja zabytków, UM Płock'],
    'przedszkole'            => ['Przedszkole', 'Placówki oświatowe, UM Płock'],
    'szkola-podstawowa'      => ['Szkoła podstawowa', 'Placówki oświatowe UM Płock / BDOT10k'],
    'szkola-ponadpodstawowa' => ['Szkoła ponadpodstawowa', 'Placówki oświatowe UM Płock / BDOT10k'],
    'szkola-specjalna'       => ['Szkoła specjalna', 'Placówki oświatowe, UM Płock'],
    'szkola-dla-doroslych'   => ['Szkoła dla dorosłych', 'Placówki oświatowe, UM Płock'],
    'szkola-wyzsza'          => ['Szkoła wyższa', 'BDOT10k – budynki szkół wyższych'],
    'placowka-pozaszkolna'   => ['Placówka wychowania pozaszkolnego', 'Placówki oświatowe, UM Płock'],
    'poradnia'               => ['Poradnia psychologiczno-pedagogiczna', 'Placówki oświatowe, UM Płock'],
    'internat'               => ['Internat / bursa', 'Placówki oświatowe, UM Płock'],
    'przystanek'             => ['Przystanek komunikacji miejskiej', 'Przystanki KM Płock'],
    'rower-miejski'          => ['Stacja roweru miejskiego', 'Płocki Rower Miejski'],
    'plac-zabaw'             => ['Plac zabaw', 'Wydział Spraw Komunalnych UM Płock'],
    'silownia-plenerowa'     => ['Siłownia pod chmurką', 'Wydział Spraw Komunalnych UM Płock'],
    'boisko-rekreacja'       => ['Boisko, stojak rowerowy, górka saneczkowa', 'Wydział Spraw Komunalnych UM Płock'],
    'park-skwer'             => ['Park / skwer', 'Wydział Spraw Komunalnych UM Płock'],
    'fontanna'               => ['Fontanna / wodotrysk', 'Wydział Spraw Komunalnych UM Płock'],
    'toaleta'                => ['Toaleta publiczna', 'Wydział Spraw Komunalnych UM Płock'],
    'parking'                => ['Parking', 'Wydział Spraw Komunalnych UM Płock'],
    'osiedle'                => ['Osiedle (obszar)', 'Wydział Spraw Komunalnych UM Płock'],
];

// Foldery mapy WSK.kml => kategoria (pozostałe foldery są pomijane)
$kmlFolders = [
    'Place zabaw'                                => 'plac-zabaw',
    'Siłownie pod chmurką'                       => 'silownia-plenerowa',
    'Boiska, stojaki rowerowe  i górki saneczkowe' => 'boisko-rekreacja',
    'Parki i skwery'                             => 'park-skwer',
    'Fontanny i wodotryski itp'                  => 'fontanna',
    'Toalety publiczne i TOI-TOI'                => 'toaleta',
    'Parkingi WKŚ'                               => 'parking',
    'Osiedla'                                    => 'osiedle',
];

// "Rodzaj" z pliku placówek oświatowych (po naprawie znaków) => kategoria
$schoolKinds = [
    'przedszkole'                         => 'przedszkole',
    'szkoła podstawowa'                   => 'szkola-podstawowa',
    'szkoła ponadpodstawowa'              => 'szkola-ponadpodstawowa',
    'szkoła ponapodstawowa'               => 'szkola-ponadpodstawowa',
    'szkioła ponadpodstawowa'             => 'szkola-ponadpodstawowa',
    'szkoła specjalna'                    => 'szkola-specjalna',
    'szkoła dla dorosłych'                => 'szkola-dla-doroslych',
    'szkoły dla dorosłych'                => 'szkola-dla-doroslych',
    'placówki wychowania pozaszkolnego'   => 'placowka-pozaszkolna',
    'placówki wychowania pozaszkolengo'   => 'placowka-pozaszkolna',
    'poradnia psychologiczno-pedagogiczna' => 'poradnia',
    'poradnie psychologiczno-pedagogiczne' => 'poradnia',
    'internaty'                           => 'internat',
];

// =====================================================================
//  Pomocnicze
// =====================================================================

function fail(string $text): never
{
    fwrite(STDERR, $text . PHP_EOL);
    exit(1);
}

/** @return list<array<string,string>> */
function readCsv(string $file): array
{
    $h = fopen(SRC . '/' . $file, 'rb') ?: fail("Brak pliku {$file}");
    $header = fgetcsv($h, null, ',', '"', '');
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $rows = [];
    while (($row = fgetcsv($h, null, ',', '"', '')) !== false) {
        if (count($row) === count($header)) {
            $rows[] = array_combine($header, $row);
        }
    }
    fclose($h);
    return $rows;
}

function loadXml(string $file): SimpleXMLElement
{
    $xml = simplexml_load_file(SRC . '/' . $file);
    return $xml instanceof SimpleXMLElement ? $xml : fail("Nie można odczytać pliku {$file}");
}

/** "POINT (lng lat)" / "MULTIPOINT ((lng lat))" => [x, y] */
function wktPoint(string $wkt): ?array
{
    if (!preg_match('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/', $wkt, $m)) {
        return null;
    }
    return [(float) $m[1], (float) $m[2]];
}

/**
 * PUWG 1992 (EPSG:2180, odwzorowanie Gaussa-Krügera, elipsoida GRS80)
 * => WGS84. Dokładność poniżej 1 m w obrębie Płocka.
 * @return array{0: float, 1: float} [lat, lng]
 */
function puwg92ToWgs84(float $easting, float $northing): array
{
    $a = 6378137.0;
    $f = 1 / 298.257222101;
    $k0 = 0.9993;
    $lon0 = deg2rad(19.0);
    $e2 = $f * (2 - $f);
    $ep2 = $e2 / (1 - $e2);

    $m = ($northing + 5300000.0) / $k0;
    $mu = $m / ($a * (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256));
    $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));
    $phi1 = $mu
        + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu)
        + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu)
        + (151 * $e1 ** 3 / 96) * sin(6 * $mu)
        + (1097 * $e1 ** 4 / 512) * sin(8 * $mu);

    $sin = sin($phi1);
    $cos = cos($phi1);
    $c1 = $ep2 * $cos ** 2;
    $t1 = tan($phi1) ** 2;
    $n1 = $a / sqrt(1 - $e2 * $sin ** 2);
    $r1 = $a * (1 - $e2) / (1 - $e2 * $sin ** 2) ** 1.5;
    $d = ($easting - 500000.0) / ($n1 * $k0);

    $lat = $phi1 - ($n1 * tan($phi1) / $r1) * (
        $d ** 2 / 2
        - (5 + 3 * $t1 + 10 * $c1 - 4 * $c1 ** 2 - 9 * $ep2) * $d ** 4 / 24
        + (61 + 90 * $t1 + 298 * $c1 + 45 * $t1 ** 2 - 252 * $ep2 - 3 * $c1 ** 2) * $d ** 6 / 720
    );
    $lng = $lon0 + (
        $d
        - (1 + 2 * $t1 + $c1) * $d ** 3 / 6
        + (5 - 2 * $c1 + 28 * $t1 - 3 * $c1 ** 2 + 8 * $ep2 + 24 * $t1 ** 2) * $d ** 5 / 120
    ) / $cos;

    return [rad2deg($lat), rad2deg($lng)];
}

function inPlockArea(float $lat, float $lng): bool
{
    return $lat > 52.40 && $lat < 52.70 && $lng > 19.45 && $lng < 19.95;
}

function fold(string $s): string
{
    return strtr(mb_strtolower($s), [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ]);
}

function cleanSpaces(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $s)));
}

/** Opis z KML (HTML z Google My Maps) => zwykły tekst bez zdjęć. */
function kmlText(string $html): string
{
    $html = preg_replace('#<img[^>]*>#i', '', $html);
    $html = preg_replace('#<br\s*/?>#i', "\n", (string) $html);
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_filter(array_map('cleanSpaces', explode("\n", $text)), fn ($l) => $l !== '');
    return implode("\n", $lines);
}

// =====================================================================
//  Słownik poprawnych słów do naprawy polskich znaków
// =====================================================================

/** @var array<string, array<string,int>> fold(słowo) => [forma => liczba wystąpień] */
$dictionary = [];

function learn(string $text): void
{
    global $dictionary;
    foreach (preg_split('/[^\p{L}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        if (mb_strlen($word) < 2) {
            continue;
        }
        $key = fold($word);
        $form = mb_strtolower($word);
        $dictionary[$key][$form] = ($dictionary[$key][$form] ?? 0) + 1;
    }
}

/** Najczęstsza forma słowa pasująca do klucza albo do wzorca z "?" (dowolna polska litera). */
function lookup(string $word): ?string
{
    global $dictionary;
    $lower = mb_strtolower($word);
    if (in_array($lower, ['os', 'ul', 'al', 'pl', 'od'], true)) {
        return null; // skróty (Os. = osiedle) zostawiamy bez zmian
    }

    if (str_contains($lower, '?')) {
        $pattern = '/^' . str_replace('\?', '[ąćęłńóśźż]', preg_quote($lower, '/')) . '$/u';
        $best = null;
        $bestCount = 0;
        foreach ($dictionary as $forms) {
            foreach ($forms as $form => $count) {
                if ($count > $bestCount && preg_match($pattern, (string) $form)) {
                    $best = (string) $form;
                    $bestCount = $count;
                }
            }
        }
        return $best;
    }

    $forms = $dictionary[fold($lower)] ?? null;
    if ($forms === null) {
        return null;
    }
    arsort($forms);
    $form = (string) array_key_first($forms);
    // Słowo z polskimi znakami zostawiamy, jeśli samo jest poprawną formą
    return isset($forms[$lower]) && $lower !== fold($lower) ? $lower : $form;
}

/** Zachowuje wielkość liter oryginału (pierwsza litera / całość wielkimi). */
function matchCase(string $original, string $fixed): string
{
    if (mb_strtoupper($original) === $original && mb_strlen($original) > 1) {
        return mb_strtoupper($fixed);
    }
    $first = mb_substr($original, 0, 1);
    // "?w." w środku nazwy to "św.", a nie "Św."
    if ($first === '?' && $fixed === 'św') {
        return $fixed;
    }
    if ($first === mb_strtoupper($first) && $first !== mb_strtolower($first) || $first === '?') {
        return mb_strtoupper(mb_substr($fixed, 0, 1)) . mb_substr($fixed, 1);
    }
    return $fixed;
}

/** Przywraca polskie znaki w tekście. $mojibake – także "3"=>ł, "1"=>ą (plik placówek). */
function repair(string $text, bool $mojibake = false): string
{
    global $fixLog;
    $original = $text;

    if ($mojibake) {
        // cyfry wewnątrz słów to zgubione znaki ISO-8859-2: ł (0xB3) i ą (0xB1)
        $text = preg_replace_callback('/(?<=\p{L})[13?]+(?=\p{L})|(?<=\p{L})3+\b|\b3(?=\p{Ll})/u', function ($m) {
            return strtr($m[0], ['3' => 'ł', '1' => 'ą']);
        }, $text);
    }

    // samotny "?" między wyrazami to zgubiona półpauza
    $text = preg_replace('/(?<=\s)\?(?=\s)/u', '–', (string) $text);

    $text = preg_replace_callback('/[\p{L}?]*\p{L}[\p{L}?]*|\?[\p{L}?]+/u', function ($m) {
        $word = $m[0];
        $fixed = lookup($word);
        return $fixed === null ? $word : matchCase($word, $fixed);
    }, (string) $text);

    if ($text !== $original) {
        $fixLog[$original] = $text;
    }
    return (string) $text;
}

// Słowa, których nie ma w poprawnych źródłach, a występują w uszkodzonych plikach
learn('Płock Płocka Płocku Płockiej Sobótka Słupno Łąck Sąd Ogród Karadżića Brama Świętej Św. Trójcy Szpital '
    . 'szkoła młyn straż plaża zajezdnia magazyny zakłady Stanisławówka Paśniki Łączniczek Kołłątaja '
    . 'Pieśni Tańca Młodzieżowy Branżowa Asyżu Dwujęzycznymi Księcia Książąt Dąbrowskiej Jagiełły Kościuszki '
    . 'Przyjaciół Małgosi Małego Dorosłych Zespołu Szkół Oddziałami Ogólnokształcące Ogólnokształcąca '
    . 'Przysposabiająca Zimińskiej Sygietyńskiej Druha Wacława Władysława Bolesława Mikołaja Skłodowskiej '
    . 'Marszałka Stanisława Małachowskiego Generała Jarosława Głowackiego Obrońców Pułku Chłopskich Wisła '
    . 'Złote Piaski Nadwiślański Zgliczyńskiego Źródlana Żyzna Rosicą Rosica Gmina Królewiecka Pasaż Zduńska '
    . 'Norbertańska Słoneczna Piękna Wesoła Długa Kościelna Łącka Liściasta Jaśminowa Młyńska Ośnica Gąbińska '
    . 'Kazimierza Wielkiego Popiełuszki Gałczyńskiego Rembielińskiego Kilińskiego Kobylińskiego Otolińska '
    . 'Łukasiewicza Piłsudskiego Mościckiego Spółdzielcza Przemysłowa Chemików Tysiąclecia Dobrzyńska Południowa');

// =====================================================================
//  1. Ulice (TERYT) – także źródło słownika
// =====================================================================

$gml = loadXml('ulice-plock-2023.gml');
$gml->registerXPathNamespace('ogr', 'http://ogr.maptools.org/');
$gml->registerXPathNamespace('gml', 'http://www.opengis.net/gml/3.2');

$streets = [];
foreach ($gml->xpath('//ogr:featureMember/*') as $feature) {
    $props = $feature->children('http://ogr.maptools.org/');
    $code = (string) $props->ulic;
    $name = cleanSpaces(html_entity_decode((string) $props->nazwa, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    if ($code === '' || $name === '') {
        continue;
    }
    learn($name);

    $feature->registerXPathNamespace('gml', 'http://www.opengis.net/gml/3.2');
    foreach ($feature->xpath('.//gml:posList') as $posList) {
        $nums = preg_split('/\s+/', trim((string) $posList));
        for ($i = 0; $i + 1 < count($nums); $i += 2) {
            // EPSG:2180 w GML: kolejność osi northing, easting
            [$lat, $lng] = puwg92ToWgs84((float) $nums[$i + 1], (float) $nums[$i]);
            $streets[$code]['name'] = $name;
            $streets[$code]['points'][] = [$lat, $lng];
        }
    }
}
// Nazwy ulic z PRG (także z okolicznych gmin) – tylko do słownika
$prg = (string) file_get_contents(SRC . '/prg-ulice.gml');
preg_match_all('#<ms:nazwa>(.*?)</ms:nazwa>#u', $prg, $m);
array_map('learn', $m[1]);

// =====================================================================
//  2. Wczytanie poprawnych tekstów do słownika (BDOT10k, WSK.kml)
// =====================================================================

$bdotFiles = [
    'budynki-szkol-podstawowych.csv'      => 'szkola-podstawowa',
    'budynki-szkol-ponadpodstawowych.csv' => 'szkola-ponadpodstawowa',
    'budynki-szkol-wyzszych.csv'          => 'szkola-wyzsza',
];
$bdot = [];
foreach ($bdotFiles as $file => $slug) {
    $bdot[$file] = readCsv($file);
    foreach ($bdot[$file] as $row) {
        learn($row['x_informDo']);
    }
}

$kml = loadXml('wsk-obiekty-komunalne.kml');
$kml->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');
foreach ($kml->xpath('//k:Placemark') as $pm) {
    learn((string) $pm->name);
}

// =====================================================================
//  3. Miejsca
// =====================================================================

/** @var list<array> $places */
$places = [];
$seen = [];

function addPlace(string $category, string $name, ?string $address, float $lat, float $lng,
                  ?string $description = null, ?string $sourceRef = null, ?array $area = null): void
{
    global $places, $seen;
    $name = cleanSpaces($name);
    if ($name === '' || !inPlockArea($lat, $lng)) {
        return;
    }
    // ta sama kategoria, nazwa i miejsce (do ~5 m) = duplikat
    $key = $category . '|' . mb_strtolower($name) . '|' . round($lat, 4) . '|' . round($lng, 4);
    if (isset($seen[$key])) {
        return;
    }
    $seen[$key] = true;
    $places[] = compact('category', 'name', 'address', 'lat', 'lng', 'description', 'sourceRef', 'area');
}

// --- 3a. Zabytki ----------------------------------------------------------
foreach (['zabytki-rejestr.csv' => 'zabytek-rejestr', 'zabytki-ewidencja.csv' => 'zabytek-ewidencja'] as $file => $slug) {
    foreach (readCsv($file) as $row) {
        $point = wktPoint($row['WKT']);
        if ($point === null) {
            continue;
        }
        $street = str_replace('Kaizmierza', 'Kazimierza', cleanSpaces(implode(' ', [
            $row['PRZEDROS_1'], $row['NAZWA_CZ1'], $row['NAZWA_CZ2'],
        ])));
        $street = repair($street, true);
        $prefix = match ($row['PRZEDROSTE']) {
            'Aleja' => 'al.',
            'Plac'  => 'pl.',
            default => $street === '' ? '' : 'ul.',
        };
        $number = cleanSpaces($row['NUMER'] . $row['PODNUMER']);
        $address = cleanSpaces("{$prefix} {$street} {$number}");
        $address = $address === '' ? null : $address . ', Płock';

        $name = 'Zabytek' . ($address ? ' – ' . preg_replace('/, Płock$/u', '', $address) : '');
        addPlace($slug, $name, $address, $point[1], $point[0], null, $row['ID_1'] ?: null);
    }
}

// --- 3b. Placówki oświatowe ----------------------------------------------
$schoolNames = [];
foreach (readCsv('placowki-oswiatowe.csv') as $row) {
    $point = wktPoint($row['WKT']);
    $name = trim($row['Nazwa_w3a']);
    if ($point === null || $name === '') {
        continue;
    }
    $kind = mb_strtolower(repair($row['Rodzaj'], true));
    $slug = $schoolKinds[$kind] ?? fail("Nieznany rodzaj placówki: {$row['Rodzaj']}");

    // kilka placówek w jednym wierszu (szkoły dla dorosłych) – osobne miejsca
    foreach (explode(',', $name) as $part) {
        $part = repair(cleanSpaces($part), true);
        $schoolNames[fold(preg_replace('/\s+w\s+Płocku\b.*$/u', '', $part))] = true;
        addPlace($slug, $part, null, $point[1], $point[0], null, 'placowki-oswiatowe:' . $row['Lp']);
    }
}

// --- 3c. Budynki szkół z BDOT10k (uzupełnienie: szkoły niepubliczne, uczelnie)
foreach ($bdotFiles as $file => $slug) {
    foreach ($bdot[$file] as $row) {
        $point = wktPoint($row['WKT']);
        $info = cleanSpaces($row['x_informDo']);
        if ($point === null || $info === '' || $row['x_katIstni'] === 'nieczynny') {
            continue;
        }
        [$lat, $lng] = $point[0] > 1000 ? puwg92ToWgs84($point[0], $point[1]) : [$point[1], $point[0]];

        // "Nazwa; nr rej.: 609 z 1988-12-29" – numer rejestru zabytków do opisu
        $description = null;
        if (preg_match('/^(.*?);\s*nr rej\.?:\s*(.+)$/u', $info, $mm)) {
            $info = $mm[1];
            $description = 'Budynek wpisany do rejestru zabytków, nr rej.: ' . $mm[2];
        }
        if ($row['x_katIstni'] === 'w budowie') {
            $description = trim(($description ?? '') . ' Budynek w budowie (stan BDOT10k 2020).');
        }
        $short = fold(preg_replace('/\s+w\s+Płocku\b.*$/u', '', $info));
        if (isset($schoolNames[$short])) {
            continue; // ta sama szkoła jest już w danych placówek oświatowych (albo inny jej budynek)
        }
        $schoolNames[$short] = true;
        addPlace($slug, mb_substr($info, 0, 255), null, $lat, $lng, $description, $row['lokalnyId']);
    }
}

// --- 3d. Przystanki --------------------------------------------------------
foreach (readCsv('przystanki.csv') as $row) {
    $lat = (float) $row['Field7'];
    $lng = (float) $row['Field11'];
    if (!$lat || !$lng) {
        [$lng, $lat] = wktPoint($row['WKT']) ?? [0.0, 0.0];
    }
    $stop = repair(cleanSpaces($row['Field2']));
    $street = repair(cleanSpaces($row['Field13']));
    $town = repair(cleanSpaces($row['Field14']));
    $address = trim(($street !== '' && $street !== $town ? $street . ', ' : '') . $town, ', ');
    $post = (int) $row['Field3'];
    addPlace('przystanek', "Przystanek {$stop}" . ($post ? " (słupek {$post})" : ''), $address ?: null,
             $lat, $lng, null, 'przystanki:' . $row['Field1']);
}

// --- 3e. Rower miejski ----------------------------------------------------
foreach (readCsv('rower-miejski.csv') as $row) {
    // Field2 = szerokość, Field3 = długość; w jednym wierszu Field2 zawiera "szer, dług" (a WKT jest pusty)
    $coords = array_map('floatval', explode(',', $row['Field2']));
    $lat = $coords[0];
    $lng = $coords[1] ?? (float) $row['Field3'];
    if ($lng > 9 && $lng < 10) {
        $lng += 10; // literówka w źródle: 9.696585 zamiast 19.696585 (1 Maja – Królewiecka)
    }
    $name = repair(cleanSpaces($row['Field1']));
    addPlace('rower-miejski', "Stacja roweru miejskiego – {$name}", null, $lat, $lng);
}

// --- 3f. Obiekty komunalne i osiedla (WSK.kml) ------------------------------
foreach ($kml->xpath('//k:Folder') as $folder) {
    $slug = $kmlFolders[(string) $folder->name] ?? null;
    if ($slug === null) {
        continue; // przepompownie, punkty pomiarowe liczników – dane techniczne
    }
    $label = $categories[$slug][0];
    foreach ($folder->Placemark as $pm) {
        $raw = cleanSpaces((string) $pm->name);
        $description = kmlText((string) $pm->description) ?: null;
        $pm->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');

        if ($point = $pm->xpath('.//k:Point/k:coordinates')) {
            [$lng, $lat] = array_map('floatval', explode(',', trim((string) $point[0])));
            $name = $slug === 'park-skwer' ? $raw : "{$label} – {$raw}";
            addPlace($slug, $name, null, $lat, $lng, $description);
            continue;
        }

        if ($ring = $pm->xpath('.//k:Polygon/k:outerBoundaryIs/k:LinearRing/k:coordinates')) {
            $coords = [];
            foreach (preg_split('/\s+/', trim((string) $ring[0])) as $pair) {
                [$x, $y] = array_map('floatval', explode(',', $pair));
                $coords[] = [round($x, 6), round($y, 6)];
            }
            $lat = array_sum(array_column($coords, 1)) / count($coords);
            $lng = array_sum(array_column($coords, 0)) / count($coords);
            $area = ['type' => 'Feature', 'properties' => new stdClass(),
                     'geometry' => ['type' => 'Polygon', 'coordinates' => [$coords]]];
            addPlace($slug, $raw, null, $lat, $lng, $description, null, $area);
        }
    }
}

// =====================================================================
//  4. SQL
// =====================================================================

$pdoQuote = fn (?string $v): string => $v === null
    ? 'NULL'
    : "'" . str_replace(["\\", "'", "\r", "\n"], ["\\\\", "''", '', '\n'], $v) . "'";
$num = fn (float $v): string => number_format($v, 7, '.', '');

$sql = [MARK_BEGIN, ''];
$sql[] = '-- Źródła: database/dane-zrodlowe/ (otwarte dane UM Płock, BDOT10k, TERYT/PRG).';
$sql[] = '-- Liczba miejsc: ' . count($places) . ', liczba ulic: ' . count($streets) . '.';
$sql[] = '';
$sql[] = 'INSERT INTO place_categories (slug, name, source_name, position) VALUES';
$rows = [];
$pos = 0;
foreach ($categories as $slug => [$name, $source]) {
    $rows[] = sprintf('  (%s, %s, %s, %d)', $pdoQuote($slug), $pdoQuote($name), $pdoQuote($source), ++$pos);
}
$sql[] = implode(",\n", $rows) . ';';
$sql[] = '';
foreach (array_keys($categories) as $slug) {
    $sql[] = sprintf("SET @pc_%s := (SELECT id FROM place_categories WHERE slug = '%s');", str_replace('-', '_', $slug), $slug);
}
$sql[] = '';

foreach (array_chunk($places, 200) as $chunk) {
    $sql[] = 'INSERT INTO places (category_id, name, address, latitude, longitude, description, source_ref, area_geojson) VALUES';
    $rows = [];
    foreach ($chunk as $p) {
        $area = $p['area'] ? json_encode($p['area'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $rows[] = sprintf('  (@pc_%s, %s, %s, %s, %s, %s, %s, %s)',
            str_replace('-', '_', $p['category']), $pdoQuote($p['name']), $pdoQuote($p['address']),
            $num($p['lat']), $num($p['lng']), $pdoQuote($p['description']), $pdoQuote($p['sourceRef']), $pdoQuote($area));
    }
    $sql[] = implode(",\n", $rows) . ';';
    $sql[] = '';
}

ksort($streets, SORT_STRING);
$rows = [];
foreach ($streets as $code => $s) {
    $lats = array_column($s['points'], 0);
    $lngs = array_column($s['points'], 1);
    $cLat = (min($lats) + max($lats)) / 2;
    $cLng = (min($lngs) + max($lngs)) / 2;
    // punkt odniesienia: wierzchołek ulicy najbliższy środka jej zasięgu (leży na ulicy)
    usort($s['points'], fn ($a, $b) => (($a[0] - $cLat) ** 2 + ($a[1] - $cLng) ** 2) <=> (($b[0] - $cLat) ** 2 + ($b[1] - $cLng) ** 2));
    $rows[] = sprintf('  (%s, %s, %s, %s, %s, %s, %s, %s)',
        $pdoQuote((string) $code), $pdoQuote($s['name']), $num($s['points'][0][0]), $num($s['points'][0][1]),
        $num(min($lats)), $num(min($lngs)), $num(max($lats)), $num(max($lngs)));
}
foreach (array_chunk($rows, 250) as $chunk) {
    $sql[] = 'INSERT INTO streets (ulic_code, name, latitude, longitude, min_latitude, min_longitude, max_latitude, max_longitude) VALUES';
    $sql[] = implode(",\n", $chunk) . ';';
    $sql[] = '';
}
$sql[] = MARK_END;
$generated = implode("\n", $sql);

// =====================================================================
//  5. Zapis do seed.sql
// =====================================================================

$seed = (string) file_get_contents(SEED);
$begin = strpos($seed, MARK_BEGIN);
$end = strpos($seed, MARK_END);
if ($begin !== false && $end !== false) {
    $seed = substr($seed, 0, $begin) . $generated . substr($seed, $end + strlen(MARK_END));
} else {
    $commit = strrpos($seed, 'COMMIT;') ?: fail('W seed.sql brakuje "COMMIT;" – nie wiem, gdzie wstawić dane.');
    $header = "-- =====================================================================\n"
        . "-- 4. MIEJSCA I ULICE PŁOCKA – dane referencyjne do edytora gier\n"
        . "-- =====================================================================\n";
    $seed = substr($seed, 0, $commit) . $header . $generated . "\n\n" . substr($seed, $commit);
}
file_put_contents(SEED, $seed);

$perCategory = array_count_values(array_column($places, 'category'));
fwrite(STDOUT, 'Zapisano ' . count($places) . ' miejsc i ' . count($streets) . " ulic do database/seed.sql\n");
foreach ($categories as $slug => [$name]) {
    fwrite(STDOUT, sprintf("  %-42s %4d\n", $name, $perCategory[$slug] ?? 0));
}
if ($report) {
    fwrite(STDOUT, "\nPoprawione teksty:\n");
    foreach ($fixLog as $from => $to) {
        fwrite(STDOUT, "  {$from}  =>  {$to}\n");
    }
}
