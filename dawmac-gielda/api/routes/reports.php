<?php
/**
 * Zgłoszenia treści (DSA art. 16) i słownik aut.
 *
 * Zgłosić może każdy, także bez konta — wtedy podaje imię i e-mail.
 * Zgłaszający dostaje potwierdzenie przyjęcia, a potem informację o decyzji.
 */

declare(strict_types=1);

const REPORT_REASONS = [
    'stolen'         => 'Felgi mogą pochodzić z kradzieży',
    'counterfeit'    => 'Podróbka / replika z cudzym logo',
    'hidden_damage'  => 'Ukryte uszkodzenia',
    'foreign_photos' => 'Cudze zdjęcia',
    'scam'           => 'Próba oszustwa',
    'personal_data'  => 'Dane osobowe osób trzecich',
    'offensive'      => 'Treści obraźliwe',
    'other'          => 'Inne naruszenie regulaminu lub prawa',
];

function route_report_create(): void
{
    rate_limit('report', client_ip(), 20, 3600);
    $viewer = current_user();
    $f = new Fields(input());

    $listingId = $f->int('listing_id', 1, PHP_INT_MAX, false);
    $userId = $f->int('user_id', 1, PHP_INT_MAX, false);
    $reason = $f->enum('reason', array_keys(REPORT_REASONS), true, 'Powód');
    $details = $f->str('details', 4000, true, 10, 'Opis');
    $name = $viewer ? null : $f->str('reporter_name', 100, true, 2, 'Imię i nazwisko');
    $email = $viewer ? null : $f->email('reporter_email');
    if (!$f->bool('good_faith')) {
        $f->error('good_faith', 'Potwierdź, że zgłoszenie jest zgodne z prawdą.');
    }
    if (!$listingId && !$userId) {
        $f->error('listing_id', 'Brak ogłoszenia lub użytkownika do zgłoszenia.');
    }
    $f->check();

    if ($listingId) {
        $l = load_listing($listingId);
        if (!$l) {
            fail(404, 'Nie ma takiego ogłoszenia.');
        }
        $userId = (int) $l['user_id'];
    }

    db()->prepare(
        'INSERT INTO g_reports (listing_id, reported_user_id, reporter_id, reporter_name, reporter_email, reason, details, good_faith)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    )->execute([$listingId, $userId, $viewer['id'] ?? null, $name, $email, $reason, $details]);
    $id = (int) db()->lastInsertId();

    $to = $viewer['email'] ?? $email;
    if ($to) {
        send_mail(
            $to,
            'Przyjęliśmy zgłoszenie nr ' . $id . ' — DAWMAC Giełda',
            "Dziękujemy. Twoje zgłoszenie nr $id (powód: " . REPORT_REASONS[$reason] . ") trafiło do moderacji.\n"
            . 'Napiszemy, gdy zapadnie decyzja.'
        );
    }
    foreach (staff_ids() as $sid) {
        notify($sid, 'report', 'Nowe zgłoszenie: ' . REPORT_REASONS[$reason], mb_substr($details, 0, 200), '/panel');
    }
    json_out(['id' => $id], 201);
}

function staff_ids(): array
{
    return array_map('intval', db()->query("SELECT id FROM g_users WHERE role IN ('moderator','admin') AND status = 'active'")
        ->fetchAll(PDO::FETCH_COLUMN));
}

function route_report_reasons(): void
{
    json_out(['reasons' => REPORT_REASONS]);
}

// ---------------------------------------------------------------- słownik aut

function route_car_brands(): void
{
    header('Cache-Control: public, max-age=3600');
    $rows = cars_db()->query('SELECT id, name FROM car_brand ORDER BY name')->fetchAll();
    json_out(['items' => array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name']], $rows)]);
}

function route_car_models(): void
{
    header('Cache-Control: public, max-age=3600');
    $brand = $_GET['brand_id'] ?? '';
    if (!ctype_digit((string) $brand)) {
        fail(422, 'Podaj markę auta.');
    }
    $stmt = cars_db()->prepare('SELECT id, name FROM car_model WHERE car_brand_id = ? ORDER BY name');
    $stmt->execute([(int) $brand]);
    json_out(['items' => array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name']], $stmt->fetchAll())]);
}

/** Dane, które frontend pokazuje w stopce i regulaminie. */
function route_config(): void
{
    json_out([
        'contact_email'   => env('CONTACT_EMAIL', 'gielda@dawmac.pl'),
        'authority_email' => env('AUTHORITY_EMAIL', env('CONTACT_EMAIL', 'gielda@dawmac.pl')),
        'operator'        => env('OPERATOR_INFO', 'DAWMAC [pełna nazwa, adres, NIP — uzupełnić]'),
        'terms_version'   => terms_version(),
        'listing_days'    => listing_days(),
        'max_images'      => MAX_IMAGES,
        'voivodeships'    => VOIVODESHIPS,
        'report_reasons'  => REPORT_REASONS,
    ]);
}
