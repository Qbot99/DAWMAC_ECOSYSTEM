<?php
/**
 * Ogłoszenia „sprzedam” / „kupię”: lista z filtrami, szczegóły, dodawanie,
 * edycja, zmiana statusu, zdjęcia, obserwowane.
 */

declare(strict_types=1);

const CONDITIONS = ['new', 'used', 'damaged'];
const VOIVODESHIPS = [
    'dolnośląskie', 'kujawsko-pomorskie', 'lubelskie', 'lubuskie', 'łódzkie', 'małopolskie', 'mazowieckie',
    'opolskie', 'podkarpackie', 'podlaskie', 'pomorskie', 'śląskie', 'świętokrzyskie', 'warmińsko-mazurskie',
    'wielkopolskie', 'zachodniopomorskie',
];

function listing_days(): int
{
    return max(7, (int) env('LISTING_DAYS', '60'));
}

/**
 * Ogłoszenie z bazy razem z autorem i zdjęciami.
 * $viewer decyduje, co wolno pokazać (telefon, powód usunięcia).
 */
function load_listing(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT l.*, u.display_name, u.city AS user_city, u.seller_type, u.company_name, u.created_at AS user_since,
                u.phone AS user_phone, u.status AS user_status
         FROM g_listings l JOIN g_users u ON u.id = l.user_id WHERE l.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function listing_images(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_map('intval', $ids));
    $rows = db()->query("SELECT * FROM g_listing_images WHERE listing_id IN ($in) ORDER BY listing_id, position, id")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['listing_id']][] = image_view((int) $r['listing_id'], $r);
    }
    return $out;
}

function listing_view(array $l, array $images, ?array $viewer, bool $full = false): array
{
    $isOwner = $viewer && (int) $viewer['id'] === (int) $l['user_id'];
    $v = [
        'id'               => (int) $l['id'],
        'type'             => $l['type'],
        'status'           => $l['status'],
        'title'            => $l['title'],
        'car'              => [
            'brand_id'   => $l['car_brand_id'] !== null ? (int) $l['car_brand_id'] : null,
            'model_id'   => $l['car_model_id'] !== null ? (int) $l['car_model_id'] : null,
            'brand'      => $l['car_brand_name'],
            'model'      => $l['car_model_name'],
            'year_from'  => $l['car_year_from'] !== null ? (int) $l['car_year_from'] : null,
            'year_to'    => $l['car_year_to'] !== null ? (int) $l['car_year_to'] : null,
        ],
        'wheel'            => [
            'brand'       => $l['wheel_brand'],
            'model'       => $l['wheel_model'],
            'diameter'    => $l['diameter'] !== null ? (float) $l['diameter'] : null,
            'width'       => $l['width'] !== null ? (float) $l['width'] : null,
            'width_rear'  => $l['width_rear'] !== null ? (float) $l['width_rear'] : null,
            'pcd'         => $l['pcd'],
            'et'          => $l['et'] !== null ? (int) $l['et'] : null,
            'et_rear'     => $l['et_rear'] !== null ? (int) $l['et_rear'] : null,
            'center_bore' => $l['center_bore'] !== null ? (float) $l['center_bore'] : null,
            'quantity'    => $l['quantity'] !== null ? (int) $l['quantity'] : null,
            'condition'   => $l['condition'],
            'with_tyres'  => (bool) $l['with_tyres'],
            'tyre_info'   => $l['tyre_info'],
        ],
        'price'            => $l['price'] !== null ? (int) $l['price'] : null,
        'price_negotiable' => (bool) $l['price_negotiable'],
        'city'             => $l['city'],
        'voivodeship'      => $l['voivodeship'],
        'images'           => $images,
        'created_at'       => $l['created_at'],
        'expires_at'       => $l['expires_at'],
        'is_owner'         => $isOwner,
        'seller'           => [
            'id'           => (int) $l['user_id'],
            'display_name' => $l['display_name'],
            'seller_type'  => $l['seller_type'],
            'company_name' => $l['seller_type'] === 'company' ? $l['company_name'] : null,
            'since'        => substr((string) $l['user_since'], 0, 10),
        ],
    ];
    if ($full) {
        $v['description'] = $l['description'];
        $v['views'] = (int) $l['views'];
        $v['updated_at'] = $l['updated_at'];
        // Telefon tylko gdy autor zgodził się go pokazać — i tak zwracamy go
        // dopiero osobnym żądaniem (przycisk „Pokaż numer”).
        $v['has_phone'] = (bool) $l['show_phone'] && !empty($l['user_phone']);
        $v['show_phone'] = (bool) $l['show_phone'];
        if ($isOwner || is_staff($viewer)) {
            $v['removal_reason'] = $l['removal_reason'];
        }
    }
    return $v;
}

/** Kto może zobaczyć ogłoszenie, które nie jest aktywne. */
function can_see_listing(array $l, ?array $viewer): bool
{
    if ($l['user_status'] !== 'active' && !is_staff($viewer)) {
        return false;
    }
    if ($l['status'] === 'active' || $l['status'] === 'sold') {
        return true;
    }
    return $viewer && ((int) $viewer['id'] === (int) $l['user_id'] || is_staff($viewer));
}

function route_listings_index(): void
{
    $viewer = current_user();
    $g = $_GET;
    $where = ["l.status = 'active'", "u.status = 'active'", 'l.expires_at > NOW()'];
    $args = [];

    if (in_array($g['type'] ?? '', ['sell', 'buy'], true)) {
        $where[] = 'l.type = ?';
        $args[] = $g['type'];
    }
    foreach (['brand_id' => 'car_brand_id', 'model_id' => 'car_model_id'] as $param => $col) {
        if (isset($g[$param]) && ctype_digit((string) $g[$param])) {
            $where[] = "l.$col = ?";
            $args[] = (int) $g[$param];
        }
    }
    if (isset($g['diameter']) && is_numeric($g['diameter'])) {
        $where[] = 'l.diameter = ?';
        $args[] = (float) $g['diameter'];
    }
    if (!empty($g['pcd']) && ($pcd = normalize_pcd((string) $g['pcd']))) {
        $where[] = 'l.pcd = ?';
        $args[] = $pcd;
    }
    if (in_array($g['condition'] ?? '', CONDITIONS, true)) {
        $where[] = 'l.`condition` = ?';
        $args[] = $g['condition'];
    }
    if (in_array($g['voivodeship'] ?? '', VOIVODESHIPS, true)) {
        $where[] = 'l.voivodeship = ?';
        $args[] = $g['voivodeship'];
    }
    if (isset($g['price_max']) && ctype_digit((string) $g['price_max'])) {
        $where[] = 'l.price <= ?';
        $args[] = (int) $g['price_max'];
    }
    if (isset($g['with_tyres']) && $g['with_tyres'] === '1') {
        $where[] = 'l.with_tyres = 1';
    }
    if (isset($g['seller_type']) && in_array($g['seller_type'], ['private', 'company'], true)) {
        $where[] = 'u.seller_type = ?';
        $args[] = $g['seller_type'];
    }
    $q = trim((string) ($g['q'] ?? ''));
    if ($q !== '') {
        // LIKE zamiast FULLTEXT: przy kilku tysiącach ogłoszeń wystarczy,
        // a łapie fragmenty jak „5x11” czy „A4”, które FULLTEXT pomija.
        foreach (array_slice(preg_split('/\s+/u', mb_substr($q, 0, 80)), 0, 5) as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $where[] = '(l.title LIKE ? OR l.description LIKE ? OR l.wheel_brand LIKE ? OR l.wheel_model LIKE ?
                        OR l.car_brand_name LIKE ? OR l.car_model_name LIKE ? OR l.city LIKE ? OR l.pcd LIKE ?)';
            array_push($args, ...array_fill(0, 8, $like));
        }
    }

    // Kolejność opisana w regulaminie: domyślnie najnowsze. Bez promowania.
    $order = match ($g['sort'] ?? 'new') {
        'price_asc'  => 'l.price IS NULL, l.price ASC, l.id DESC',
        'price_desc' => 'l.price IS NULL, l.price DESC, l.id DESC',
        default      => 'l.created_at DESC, l.id DESC',
    };

    $perPage = 24;
    $page = max(1, (int) ($g['page'] ?? 1));
    $sqlWhere = implode(' AND ', $where);

    $count = db()->prepare("SELECT COUNT(*) FROM g_listings l JOIN g_users u ON u.id = l.user_id WHERE $sqlWhere");
    $count->execute($args);
    $total = (int) $count->fetchColumn();

    $stmt = db()->prepare(
        "SELECT l.*, u.display_name, u.seller_type, u.company_name, u.created_at AS user_since
         FROM g_listings l JOIN g_users u ON u.id = l.user_id
         WHERE $sqlWhere ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage)
    );
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    $images = listing_images(array_column($rows, 'id'));
    $items = array_map(fn ($r) => listing_view($r, array_slice($images[(int) $r['id']] ?? [], 0, 1), $viewer), $rows);

    json_out(['items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)]);
}

function route_listing_show(string $id): void
{
    $viewer = current_user();
    $l = load_listing((int) $id);
    if (!$l || !can_see_listing($l, $viewer)) {
        fail(404, 'Ogłoszenie nie istnieje albo zostało zakończone.');
    }
    if (!$viewer || (int) $viewer['id'] !== (int) $l['user_id']) {
        db()->prepare('UPDATE g_listings SET views = views + 1 WHERE id = ?')->execute([$l['id']]);
    }
    $view = listing_view($l, listing_images([(int) $l['id']])[(int) $l['id']] ?? [], $viewer, true);

    if ($viewer) {
        $fav = db()->prepare('SELECT 1 FROM g_favorites WHERE user_id = ? AND listing_id = ?');
        $fav->execute([$viewer['id'], $l['id']]);
        $view['is_favorite'] = (bool) $fav->fetchColumn();
    }
    json_out(['listing' => $view]);
}

/** „Pokaż numer” — osobne żądanie, żeby numer nie trafiał do robotów. */
function route_listing_phone(string $id): void
{
    $viewer = require_user();
    rate_limit('phone', (string) $viewer['id'], 60, 3600);
    $l = load_listing((int) $id);
    if (!$l || !can_see_listing($l, $viewer) || !$l['show_phone'] || empty($l['user_phone'])) {
        fail(404, 'Autor nie udostępnił numeru. Napisz wiadomość.');
    }
    json_out(['phone' => $l['user_phone']]);
}

/**
 * Wspólne sprawdzanie pól przy dodawaniu i edycji.
 * Zwraca kolumny do zapisu.
 */
function listing_fields(array $in, string $type): array
{
    $f = new Fields($in);
    $isSell = $type === 'sell';

    $d = [
        'title'       => $f->str('title', 120, true, 5, 'Tytuł'),
        'description' => $f->str('description', 5000, true, 10, 'Opis'),
        'wheel_brand' => $f->str('wheel_brand', 80, false, 0, 'Marka felg'),
        'wheel_model' => $f->str('wheel_model', 80, false, 0, 'Model felg'),
        'diameter'    => $f->dec('diameter', 12, 24, 'Średnica'),
        'width'       => $f->dec('width', 4, 14, 'Szerokość'),
        'width_rear'  => $f->dec('width_rear', 4, 14, 'Szerokość tył'),
        'et'          => $f->int('et', -80, 120, false, 'ET'),
        'et_rear'     => $f->int('et_rear', -80, 120, false, 'ET tył'),
        'center_bore' => $f->dec('center_bore', 40, 120, 'Otwór centralny'),
        'quantity'    => $f->int('quantity', 1, 8, false, 'Liczba sztuk'),
        'condition'   => $f->enum('condition', CONDITIONS, $isSell, 'Stan'),
        'with_tyres'  => (int) $f->bool('with_tyres'),
        'tyre_info'   => $f->str('tyre_info', 120, false, 0, 'Opony'),
        'price'       => $f->int('price', 0, 1000000, false, 'Cena'),
        'price_negotiable' => (int) $f->bool('price_negotiable'),
        'city'        => $f->str('city', 80, true, 2, 'Miasto'),
        'voivodeship' => $f->enum('voivodeship', VOIVODESHIPS, false, 'Województwo'),
        'show_phone'  => (int) $f->bool('show_phone'),
        'car_year_from' => $f->int('car_year_from', 1950, (int) date('Y') + 1, false, 'Rocznik od'),
        'car_year_to'   => $f->int('car_year_to', 1950, (int) date('Y') + 1, false, 'Rocznik do'),
    ];

    $pcd = normalize_pcd($f->str('pcd', 20, false, 0, 'Rozstaw'));
    if ($pcd === '') {
        $f->error('pcd', 'Rozstaw wpisz jak 5x112 (liczba śrub x średnica).');
    }
    $d['pcd'] = $pcd ?: null;

    // Wymagane pola z analizy prawnej dla „sprzedam”: rozmiar, rozstaw, ET, stan, cena/negocjacja.
    if ($isSell) {
        if ($d['diameter'] === null && !isset($f->errors['diameter'])) {
            $f->error('diameter', 'Podaj średnicę felg.');
        }
        if ($d['pcd'] === null && !isset($f->errors['pcd'])) {
            $f->error('pcd', 'Podaj rozstaw śrub.');
        }
        if ($d['et'] === null && !isset($f->errors['et'])) {
            $f->error('et', 'Podaj ET (odsadzenie).');
        }
        if ($d['price'] === null && !$d['price_negotiable'] && !isset($f->errors['price'])) {
            $f->error('price', 'Podaj cenę albo zaznacz „do negocjacji”.');
        }
    }
    if ($d['car_year_from'] && $d['car_year_to'] && $d['car_year_from'] > $d['car_year_to']) {
        $f->error('car_year_to', 'Rocznik „do” jest wcześniejszy niż „od”.');
    }

    // Auto ze słownika galerii.
    $brandId = $f->int('car_brand_id', 1, PHP_INT_MAX, $type === 'buy', 'Marka auta');
    $modelId = $f->int('car_model_id', 1, PHP_INT_MAX, false, 'Model auta');
    $d['car_brand_id'] = $d['car_model_id'] = $d['car_brand_name'] = $d['car_model_name'] = null;
    if ($brandId) {
        $s = cars_db()->prepare('SELECT name FROM car_brand WHERE id = ?');
        $s->execute([$brandId]);
        $brandName = $s->fetchColumn();
        if ($brandName === false) {
            $f->error('car_brand_id', 'Nie ma takiej marki auta.');
        } else {
            $d['car_brand_id'] = $brandId;
            $d['car_brand_name'] = $brandName;
        }
    }
    if ($modelId && $d['car_brand_id']) {
        $s = cars_db()->prepare('SELECT name FROM car_model WHERE id = ? AND car_brand_id = ?');
        $s->execute([$modelId, $d['car_brand_id']]);
        $modelName = $s->fetchColumn();
        if ($modelName === false) {
            $f->error('car_model_id', 'Ten model nie pasuje do wybranej marki.');
        } else {
            $d['car_model_id'] = $modelId;
            $d['car_model_name'] = $modelName;
        }
    }

    $f->check();
    return $d;
}

function route_listing_create(): void
{
    $user = require_user();
    rate_limit('listing-create', (string) $user['id'], 30, 86400);

    $in = input();
    $type = in_array($in['type'] ?? '', ['sell', 'buy'], true) ? $in['type'] : null;
    if (!$type) {
        fail(422, 'Wybierz rodzaj ogłoszenia.', ['type' => 'Wybierz „Sprzedam” albo „Kupię”.']);
    }
    $files = uploaded_images();
    $data = listing_fields($in, $type);

    if ($type === 'sell' && !$files) {
        fail(422, 'Dodaj co najmniej jedno zdjęcie felg.', ['images' => 'Dodaj co najmniej jedno zdjęcie.']);
    }
    if (count($files) > MAX_IMAGES) {
        fail(422, 'Najwyżej ' . MAX_IMAGES . ' zdjęć.', ['images' => 'Najwyżej ' . MAX_IMAGES . ' zdjęć.']);
    }
    if (empty($in['photo_rights']) && $files) {
        fail(422, 'Potwierdź, że masz prawa do zdjęć.', ['photo_rights' => 'Potwierdź prawa do zdjęć.']);
    }
    validate_images($files);

    $data['user_id'] = (int) $user['id'];
    $data['type'] = $type;
    $cols = array_keys($data);
    $sql = 'INSERT INTO g_listings (' . implode(', ', array_map(fn ($c) => "`$c`", $cols)) . ', expires_at) VALUES ('
        . implode(', ', array_fill(0, count($cols), '?')) . ', NOW() + INTERVAL ' . listing_days() . ' DAY)';
    db()->prepare($sql)->execute(array_values($data));
    $id = (int) db()->lastInsertId();

    try {
        add_images($id, $files, 0);
    } catch (Throwable $e) {
        delete_listing_files($id);
        db()->prepare('DELETE FROM g_listings WHERE id = ?')->execute([$id]);
        throw $e;
    }

    $l = load_listing($id);
    json_out(['listing' => listing_view($l, listing_images([$id])[$id] ?? [], $user, true)], 201);
}

function add_images(int $listingId, array $files, int $startPosition): void
{
    $stmt = db()->prepare('INSERT INTO g_listing_images (listing_id, file, width, height, position) VALUES (?, ?, ?, ?, ?)');
    foreach ($files as $i => $file) {
        [$name, $w, $h] = store_image($file, $listingId);
        $stmt->execute([$listingId, $name, $w, $h, $startPosition + $i]);
    }
}

/** Ogłoszenie, które bieżący użytkownik może zmieniać. */
function own_listing(string $id): array
{
    $user = require_user();
    $l = load_listing((int) $id);
    if (!$l || (int) $l['user_id'] !== (int) $user['id']) {
        fail(404, 'Nie ma takiego ogłoszenia na Twoim koncie.');
    }
    if ($l['status'] === 'removed') {
        fail(403, 'Ogłoszenie zostało usunięte przez moderację i nie można go zmieniać. Odwołanie: ' . env('CONTACT_EMAIL', ''));
    }
    return [$user, $l];
}

function route_listing_update(string $id): void
{
    [$user, $l] = own_listing($id);
    $data = listing_fields(input(), $l['type']);

    $set = implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($data)));
    db()->prepare("UPDATE g_listings SET $set WHERE id = ?")->execute([...array_values($data), $l['id']]);

    $fresh = load_listing((int) $l['id']);
    json_out(['listing' => listing_view($fresh, listing_images([(int) $l['id']])[(int) $l['id']] ?? [], $user, true)]);
}

/**
 * Zmiana statusu przez autora: sprzedane / zakończone / wznowione
 * (wznowienie przedłuża ważność o kolejne LISTING_DAYS dni).
 */
function route_listing_status(string $id): void
{
    [$user, $l] = own_listing($id);
    $status = input()['status'] ?? '';
    if (!in_array($status, ['active', 'sold', 'closed'], true)) {
        fail(422, 'Niedozwolony status.');
    }
    if ($status === 'active') {
        db()->prepare("UPDATE g_listings SET status = 'active', expires_at = NOW() + INTERVAL " . listing_days() . ' DAY WHERE id = ?')
            ->execute([$l['id']]);
    } else {
        db()->prepare('UPDATE g_listings SET status = ? WHERE id = ?')->execute([$status, $l['id']]);
    }
    $fresh = load_listing((int) $l['id']);
    json_out(['listing' => listing_view($fresh, listing_images([(int) $l['id']])[(int) $l['id']] ?? [], $user, true)]);
}

function route_listing_delete(string $id): void
{
    $user = require_user();
    $l = load_listing((int) $id);
    if (!$l || (int) $l['user_id'] !== (int) $user['id']) {
        fail(404, 'Nie ma takiego ogłoszenia na Twoim koncie.');
    }
    delete_listing_files((int) $l['id']);
    db()->prepare('DELETE FROM g_listings WHERE id = ?')->execute([$l['id']]);
    json_out(['ok' => true]);
}

function route_listing_add_images(string $id): void
{
    [$user, $l] = own_listing($id);
    $files = uploaded_images();
    if (!$files) {
        fail(422, 'Wybierz zdjęcia.');
    }
    if (empty(input()['photo_rights'])) {
        fail(422, 'Potwierdź, że masz prawa do zdjęć.', ['photo_rights' => 'Potwierdź prawa do zdjęć.']);
    }
    $stmt = db()->prepare('SELECT COUNT(*), COALESCE(MAX(position), -1) FROM g_listing_images WHERE listing_id = ?');
    $stmt->execute([$l['id']]);
    [$count, $maxPos] = $stmt->fetch(PDO::FETCH_NUM);
    if ($count + count($files) > MAX_IMAGES) {
        fail(422, 'Ogłoszenie może mieć najwyżej ' . MAX_IMAGES . ' zdjęć.');
    }
    validate_images($files);
    add_images((int) $l['id'], $files, (int) $maxPos + 1);
    json_out(['images' => listing_images([(int) $l['id']])[(int) $l['id']] ?? []]);
}

function route_listing_delete_image(string $id, string $imageId): void
{
    [$user, $l] = own_listing($id);
    $stmt = db()->prepare('SELECT * FROM g_listing_images WHERE id = ? AND listing_id = ?');
    $stmt->execute([(int) $imageId, $l['id']]);
    $img = $stmt->fetch();
    if (!$img) {
        fail(404, 'Nie ma takiego zdjęcia.');
    }
    $count = db()->prepare('SELECT COUNT(*) FROM g_listing_images WHERE listing_id = ?');
    $count->execute([$l['id']]);
    if ($l['type'] === 'sell' && (int) $count->fetchColumn() <= 1) {
        fail(422, 'Ogłoszenie „sprzedam” musi mieć co najmniej jedno zdjęcie. Dodaj nowe, zanim usuniesz ostatnie.');
    }
    delete_image_files((int) $l['id'], $img['file']);
    db()->prepare('DELETE FROM g_listing_images WHERE id = ?')->execute([$img['id']]);
    json_out(['images' => listing_images([(int) $l['id']])[(int) $l['id']] ?? []]);
}

/** Ustawia kolejność zdjęć; pierwsze jest okładką. */
function route_listing_order_images(string $id): void
{
    [$user, $l] = own_listing($id);
    $order = input()['order'] ?? [];
    if (!is_array($order)) {
        fail(422, 'Zła kolejność.');
    }
    $stmt = db()->prepare('UPDATE g_listing_images SET position = ? WHERE id = ? AND listing_id = ?');
    foreach (array_values($order) as $pos => $imgId) {
        $stmt->execute([$pos, (int) $imgId, $l['id']]);
    }
    json_out(['images' => listing_images([(int) $l['id']])[(int) $l['id']] ?? []]);
}

function route_my_listings(): void
{
    $user = require_user(true);
    $stmt = db()->prepare(
        'SELECT l.*, u.display_name, u.seller_type, u.company_name, u.created_at AS user_since,
                (SELECT COUNT(*) FROM g_conversations c WHERE c.listing_id = l.id) AS conversations
         FROM g_listings l JOIN g_users u ON u.id = l.user_id
         WHERE l.user_id = ? ORDER BY l.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    $images = listing_images(array_column($rows, 'id'));
    $items = array_map(function ($r) use ($images, $user) {
        $v = listing_view($r, array_slice($images[(int) $r['id']] ?? [], 0, 1), $user);
        $v['views'] = (int) $r['views'];
        $v['conversations'] = (int) $r['conversations'];
        $v['removal_reason'] = $r['removal_reason'];
        return $v;
    }, $rows);
    json_out(['items' => $items]);
}

function route_favorite_toggle(string $id): void
{
    $user = require_user(true);
    $l = load_listing((int) $id);
    if (!$l || !can_see_listing($l, $user)) {
        fail(404, 'Nie ma takiego ogłoszenia.');
    }
    $on = (bool) (input()['on'] ?? true);
    if ($on) {
        db()->prepare('INSERT IGNORE INTO g_favorites (user_id, listing_id) VALUES (?, ?)')->execute([$user['id'], $l['id']]);
    } else {
        db()->prepare('DELETE FROM g_favorites WHERE user_id = ? AND listing_id = ?')->execute([$user['id'], $l['id']]);
    }
    json_out(['is_favorite' => $on]);
}

function route_my_favorites(): void
{
    $user = require_user(true);
    $stmt = db()->prepare(
        "SELECT l.*, u.display_name, u.seller_type, u.company_name, u.created_at AS user_since
         FROM g_favorites f JOIN g_listings l ON l.id = f.listing_id JOIN g_users u ON u.id = l.user_id
         WHERE f.user_id = ? AND l.status IN ('active','sold') AND u.status = 'active'
         ORDER BY f.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    $images = listing_images(array_column($rows, 'id'));
    json_out(['items' => array_map(fn ($r) => listing_view($r, array_slice($images[(int) $r['id']] ?? [], 0, 1), $user), $rows)]);
}

/** Publiczny profil sprzedającego z jego aktywnymi ogłoszeniami. */
function route_user_profile(string $id): void
{
    $viewer = current_user();
    $stmt = db()->prepare("SELECT * FROM g_users WHERE id = ? AND status = 'active'");
    $stmt->execute([(int) $id]);
    $u = $stmt->fetch();
    if (!$u) {
        fail(404, 'Nie ma takiego użytkownika.');
    }
    $ls = db()->prepare(
        "SELECT l.*, u.display_name, u.seller_type, u.company_name, u.created_at AS user_since
         FROM g_listings l JOIN g_users u ON u.id = l.user_id
         WHERE l.user_id = ? AND l.status = 'active' AND l.expires_at > NOW() ORDER BY l.created_at DESC LIMIT 60"
    );
    $ls->execute([$u['id']]);
    $rows = $ls->fetchAll();
    $images = listing_images(array_column($rows, 'id'));
    json_out([
        'user'     => user_public_view($u),
        'listings' => array_map(fn ($r) => listing_view($r, array_slice($images[(int) $r['id']] ?? [], 0, 1), $viewer), $rows),
    ]);
}
