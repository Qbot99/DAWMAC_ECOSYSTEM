<?php
/**
 * Test całego API na żywej bazie (MariaDB/MySQL) i wbudowanym serwerze PHP.
 *
 *   ./tests/run.sh            (zakłada bazę z tests/test.env, czyści ją i odpala ten plik)
 *
 * Przechodzi ścieżkę: rejestracja → potwierdzenie e-maila → ogłoszenia ze
 * zdjęciami (EXIF musi zniknąć) → filtry → wiadomości → zgłoszenie bez konta →
 * moderacja z uzasadnieniem → blokada → usunięcie konta.
 */

declare(strict_types=1);

$base = getenv('API_BASE') ?: 'http://127.0.0.1:8099/api';
$mailLog = __DIR__ . '/../storage/mail.log';
$failures = 0;
$checks = 0;

function check(bool $cond, string $label, mixed $debug = null): void
{
    global $failures, $checks;
    $checks++;
    if ($cond) {
        echo "  ok  $label\n";
    } else {
        $failures++;
        echo "  FAIL $label\n";
        if ($debug !== null) {
            echo '       ' . substr(json_encode($debug, JSON_UNESCAPED_UNICODE), 0, 600) . "\n";
        }
    }
}

final class Client
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function call(string $method, string $path, array|null $json = null, array $files = [], array $form = [], bool $header = true): array
    {
        $ch = curl_init($this->base . $path);
        $headers = $header ? ['X-Gielda: 1'] : [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
        ]);
        if ($files || $form) {
            $post = $form;
            foreach ($files as $i => $f) {
                $post["images[$i]"] = new CURLFile($f, mime_content_type($f), basename($f));
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        } elseif ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return [$status, json_decode((string) $body, true) ?? $body];
    }

    public function get(string $p): array { return $this->call('GET', $p); }
    public function post(string $p, array $j = []): array { return $this->call('POST', $p, $j); }
}

function last_link(string $kind): string
{
    global $mailLog;
    preg_match_all('~/' . $kind . '\?token=([a-f0-9]+)~', (string) @file_get_contents($mailLog), $m);
    return end($m[1]) ?: '';
}

/** JPEG z EXIF-em (GPS) — sprawdzamy, że po uploadzie metadane znikają. */
function jpeg_with_exif(string $path): void
{
    $img = imagecreatetruecolor(1200, 800);
    imagefill($img, 0, 0, imagecolorallocate($img, 40, 40, 48));
    imagefilledellipse($img, 600, 400, 600, 600, imagecolorallocate($img, 180, 180, 190));
    ob_start();
    imagejpeg($img, null, 90);
    $jpeg = ob_get_clean();
    // Wstawiamy segment APP1 „Exif” z oznaczeniem GPS zaraz po SOI.
    $payload = "Exif\0\0" . 'GPS-SECRET-LOCATION-52.2297N-21.0122E';
    $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
    file_put_contents($path, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));
}

$tmp = sys_get_temp_dir() . '/gielda-test';
@mkdir($tmp);
jpeg_with_exif("$tmp/felga1.jpg");
jpeg_with_exif("$tmp/felga2.jpg");
file_put_contents("$tmp/nie-zdjecie.jpg", 'to nie jest obrazek');

$anon = new Client($base);
$seller = new Client($base);
$buyer = new Client($base);
$mod = new Client($base);

echo "Konfiguracja i słownik\n";
[$s, $r] = $anon->get('/config');
check($s === 200 && $r['contact_email'] === 'gielda@dawmac.pl', 'GET /config');
[$s, $r] = $anon->get('/cars/brands');
check($s === 200 && count($r['items']) === 5, 'marki aut ze słownika', $r);
[$s, $r] = $anon->get('/cars/models?brand_id=1');
check($s === 200 && in_array('A4', array_column($r['items'], 'name'), true), 'modele Audi');
[$s, $r] = $anon->get('/cars/models?brand_id=1%20OR%201=1');
check($s === 422, 'brand_id z SQL injection odrzucone');

echo "Rejestracja\n";
[$s, $r] = $seller->post('/auth/register', ['email' => 'zly', 'password' => '123', 'display_name' => 'X']);
check($s === 422 && isset($r['fields']['email'], $r['fields']['password'], $r['fields']['accept_terms']), 'walidacja rejestracji', $r);
[$s, $r] = $anon->call('POST', '/auth/register', ['email' => 'a@b.pl'], header: false);
check($s === 403, 'zapis bez nagłówka X-Gielda odrzucony');

$reg = fn (Client $c, string $email, string $name, array $extra = []) => $c->post('/auth/register', [
    'email' => $email, 'password' => 'tajnehaslo1', 'display_name' => $name, 'city' => 'Kraków',
    'phone' => '+48 600 100 200', 'accept_terms' => true, 'adult' => true, ...$extra,
]);
[$s, $r] = $reg($seller, 'Sprzedawca@test.pl', 'Felgarz');
check($s === 201 && $r['user']['email'] === 'sprzedawca@test.pl' && !$r['user']['email_verified'], 'rejestracja sprzedającego', $r);
check($r['user']['marketing_consent'] === false, 'zgoda marketingowa domyślnie wyłączona');
[$s, $r] = $reg(new Client($base), 'sprzedawca@test.pl', 'Duplikat');
check($s === 409, 'drugie konto na ten sam e-mail odrzucone');

[$s, $r] = $seller->call('POST', '/listings', form: ['type' => 'sell']);
check($s === 403, 'bez potwierdzenia e-maila nie da się dodać ogłoszenia', $r);

[$s, $r] = $seller->post('/auth/verify', ['token' => last_link('potwierdz')]);
check($s === 200 && $r['user']['email_verified'], 'potwierdzenie e-maila', $r);
[$s, $r] = $seller->post('/auth/verify', ['token' => last_link('potwierdz')]);
check($s === 400, 'link potwierdzający jednorazowy');

[$s, $r] = $reg($buyer, 'kupujacy@test.pl', 'Kupiec', ['marketing_consent' => true]);
check($r['user']['marketing_consent'] === true, 'zgoda marketingowa gdy zaznaczona');
$buyer->post('/auth/verify', ['token' => last_link('potwierdz')]);

[$s, $r] = $reg($mod, 'moderator@test.pl', 'Moderator DAWMAC');
$mod->post('/auth/verify', ['token' => last_link('potwierdz')]);
exec('php ' . escapeshellarg(__DIR__ . '/../tools/nadaj_role.php') . ' moderator@test.pl admin', $out);
check(str_contains(implode('', $out), 'OK'), 'nadanie roli admin narzędziem', $out);

echo "Ogłoszenia\n";
$sellForm = [
    'type' => 'sell', 'title' => 'Audi Rotor 18 cali 5x112', 'description' => 'Felgi oryginalne, stan bardzo dobry, bez krzywizn.',
    'car_brand_id' => '1', 'car_model_id' => '2', 'wheel_brand' => 'Audi', 'wheel_model' => 'Rotor',
    'diameter' => '18', 'width' => '8,5', 'pcd' => '5 x 112', 'et' => '35', 'center_bore' => '66.6', 'quantity' => '4',
    'condition' => 'used', 'price' => '2400', 'city' => 'Kraków', 'voivodeship' => 'małopolskie', 'show_phone' => '1',
    'photo_rights' => '1',
];
[$s, $r] = $seller->call('POST', '/listings', form: [...$sellForm, 'pcd' => 'abc', 'et' => '']);
check($s === 422 && isset($r['fields']['pcd'], $r['fields']['et']), 'walidacja: zły rozstaw i brak ET', $r);
[$s, $r] = $seller->call('POST', '/listings', form: $sellForm);
check($s === 422 && isset($r['fields']['images']), '„sprzedam” bez zdjęcia odrzucone', $r);
[$s, $r] = $seller->call('POST', '/listings', files: ["$tmp/nie-zdjecie.jpg"], form: $sellForm);
check($s === 400, 'plik, który nie jest zdjęciem, odrzucony', $r);
[$s, $r] = $seller->call('POST', '/listings', files: ["$tmp/felga1.jpg", "$tmp/felga2.jpg"], form: [...$sellForm, 'car_model_id' => '5']);
check($s === 422 && isset($r['fields']['car_model_id']), 'model niepasujący do marki odrzucony', $r);
[$s, $r] = $seller->call('POST', '/listings', files: ["$tmp/felga1.jpg", "$tmp/felga2.jpg"], form: $sellForm);
check($s === 201, 'dodanie „sprzedam” z 2 zdjęciami', $r);
$sellId = $r['listing']['id'] ?? 0;
check(($r['listing']['wheel']['pcd'] ?? '') === '5x112' && ($r['listing']['wheel']['width'] ?? 0) == 8.5, 'rozstaw i szerokość znormalizowane', $r['listing']['wheel'] ?? null);
check(($r['listing']['car']['model'] ?? '') === 'A4', 'nazwa modelu zapisana ze słownika');
check(count($r['listing']['images'] ?? []) === 2, 'dwa zdjęcia przy ogłoszeniu');

$imgUrl = str_replace('/api', '', $base) . ($r['listing']['images'][0]['url'] ?? '');
$imgBytes = (string) @file_get_contents($imgUrl);
check(str_starts_with($imgBytes, 'RIFF') && str_contains(substr($imgBytes, 0, 16), 'WEBP'), 'zdjęcie zapisane jako WebP', $imgUrl);
check($imgBytes !== '' && !str_contains($imgBytes, 'GPS-SECRET') && !str_contains($imgBytes, 'Exif'), 'EXIF/GPS usunięty ze zdjęcia');
$thumb = (string) @file_get_contents(str_replace('/api', '', $base) . ($r['listing']['images'][0]['thumb'] ?? ''));
check(strlen($thumb) > 100, 'miniatura istnieje');

[$s, $r] = $buyer->call('POST', '/listings', form: [
    'type' => 'buy', 'title' => 'Kupię felgi 18 do Audi A4 B8', 'description' => 'Szukam kompletu 18 cali, 5x112, ET 35-45.',
    'car_brand_id' => '1', 'car_model_id' => '2', 'car_year_from' => '2010', 'car_year_to' => '2015',
    'diameter' => '18', 'pcd' => '5x112', 'price' => '2500', 'city' => 'Warszawa',
]);
check($s === 201 && $r['listing']['type'] === 'buy', '„kupię” bez zdjęć', $r);
$buyId = $r['listing']['id'] ?? 0;

echo "Lista i filtry\n";
[$s, $r] = $anon->get('/listings');
check($s === 200 && $r['total'] === 2, 'lista publiczna: 2 ogłoszenia', $r);
check(!isset($r['items'][0]['seller']['email']) && !isset($r['items'][0]['phone']), 'lista bez e-maili i telefonów');
[$s, $r] = $anon->get('/listings?type=buy');
check($r['total'] === 1 && $r['items'][0]['id'] === $buyId, 'filtr typu „kupię”');
[$s, $r] = $anon->get('/listings?brand_id=1&model_id=2&diameter=18&pcd=5x112&type=sell');
check($r['total'] === 1, 'filtr auto + rozmiar + rozstaw');
[$s, $r] = $anon->get('/listings?brand_id=2');
check($r['total'] === 0, 'filtr innej marki pusty');
[$s, $r] = $anon->get('/listings?q=rotor');
check($r['total'] === 1, 'wyszukiwanie tekstowe');
[$s, $r] = $anon->get('/listings?q=' . urlencode("'; DROP TABLE g_users; --"));
check($s === 200 && $r['total'] === 0, 'wyszukiwanie odporne na SQL injection');

[$s, $r] = $anon->get("/listings/$sellId");
check($s === 200 && $r['listing']['has_phone'] === true && !isset($r['listing']['phone']), 'szczegóły: numer ukryty do kliknięcia', $r);
[$s, $r] = $anon->get("/listings/$sellId/phone");
check($s === 401, '„Pokaż numer” wymaga logowania');
[$s, $r] = $buyer->get("/listings/$sellId/phone");
check($s === 200 && $r['phone'] === '+48 600 100 200', '„Pokaż numer” dla zalogowanego');

echo "Edycja i statusy\n";
[$s, $r] = $buyer->call('POST', "/listings/$sellId", form: [...$sellForm, 'price' => '1']);
check($s === 404, 'cudzego ogłoszenia nie da się edytować');
[$s, $r] = $seller->call('POST', "/listings/$sellId", form: [...$sellForm, 'price' => '2200']);
check($s === 200 && $r['listing']['price'] === 2200, 'edycja ceny');
[$s, $r] = $seller->call('POST', "/listings/$sellId/images", files: ["$tmp/felga1.jpg"], form: ['photo_rights' => '1']);
check($s === 200 && count($r['images']) === 3, 'dodanie zdjęcia do ogłoszenia', $r);
$imgIds = array_column($r['images'], 'id');
[$s, $r] = $seller->post("/listings/$sellId/images/order", ['order' => array_reverse($imgIds)]);
check($r['images'][0]['id'] === end($imgIds), 'zmiana okładki (kolejność zdjęć)');
[$s, $r] = $seller->call('DELETE', "/listings/$sellId/images/" . $imgIds[0]);
check($s === 200 && count($r['images']) === 2, 'usunięcie zdjęcia');

[$s, $r] = $buyer->post("/listings/$sellId/favorite", ['on' => true]);
[$s, $r] = $buyer->get('/me/favorites');
check(count($r['items']) === 1, 'obserwowane');

echo "Wiadomości\n";
[$s, $r] = $seller->post("/listings/$sellId/messages", ['body' => 'Hej']);
check($s === 422, 'nie można pisać do własnego ogłoszenia');
[$s, $r] = $buyer->post("/listings/$sellId/messages", ['body' => 'Dzień dobry, czy felgi są proste? Mogę obejrzeć w sobotę?']);
check($s === 201, 'kupujący pisze do sprzedającego', $r);
$convId = $r['conversation_id'] ?? 0;
$buyer->post("/listings/$sellId/messages", ['body' => 'Druga wiadomość']);
[$s, $r] = $seller->get('/me');
check($r['unread_conversations'] === 1 && $r['unread_notifications'] === 1, 'sprzedający ma 1 nieprzeczytaną rozmowę i 1 powiadomienie (bez spamu)', $r);
[$s, $r] = $seller->get("/conversations/$convId");
check($s === 200 && count($r['messages']) === 2 && !$r['messages'][0]['mine'], 'sprzedający czyta rozmowę');
[$s, $r] = $seller->post("/conversations/$convId", ['body' => 'Proste, zapraszam.']);
check($s === 201, 'odpowiedź sprzedającego');
[$s, $r] = $seller->get('/me');
check($r['unread_conversations'] === 0, 'po przeczytaniu brak nieprzeczytanych');
[$s, $r] = $buyer->get('/conversations');
check(count($r['items']) === 1 && $r['items'][0]['unread'] && $r['items'][0]['last_message'] === 'Proste, zapraszam.', 'kupujący widzi odpowiedź', $r);
[$s, $r] = $anon->get("/conversations/$convId");
check($s === 401, 'rozmowa niedostępna bez logowania');
check(str_contains((string) file_get_contents($mailLog), 'Nowa wiadomość: Audi Rotor'), 'e-mail o nowej wiadomości');

echo "Zgłoszenia i moderacja\n";
[$s, $r] = $anon->post('/reports', ['listing_id' => $sellId, 'reason' => 'stolen', 'details' => 'krótko']);
check($s === 422 && isset($r['fields']['reporter_email'], $r['fields']['good_faith'], $r['fields']['details']), 'walidacja zgłoszenia bez konta', $r);
[$s, $r] = $anon->post('/reports', [
    'listing_id' => $sellId, 'reason' => 'stolen', 'details' => 'Te felgi zniknęły mi z garażu w zeszłym tygodniu.',
    'reporter_name' => 'Jan Kowalski', 'reporter_email' => 'jan@example.com', 'good_faith' => true,
]);
check($s === 201, 'zgłoszenie bez konta');
$reportId = $r['id'] ?? 0;
check(str_contains((string) file_get_contents($mailLog), "Przyjęliśmy zgłoszenie nr $reportId"), 'potwierdzenie przyjęcia zgłoszenia e-mailem');

[$s, $r] = $buyer->get('/mod/reports');
check($s === 403, 'zwykły użytkownik nie wchodzi do panelu');
[$s, $r] = $mod->get('/mod/stats');
check($s === 200 && $r['open_reports'] === 1 && $r['active_listings'] === 2, 'statystyki panelu', $r);
[$s, $r] = $mod->get('/mod/reports');
check(count($r['items']) === 1 && $r['items'][0]['reporter']['email'] === 'jan@example.com', 'moderator widzi zgłoszenie');
[$s, $r] = $mod->post("/mod/reports/$reportId", ['action' => 'remove_listing', 'reason' => '']);
check($s === 422, 'decyzja bez uzasadnienia odrzucona');
[$s, $r] = $mod->post("/mod/reports/$reportId", ['action' => 'remove_listing', 'reason' => 'Uzasadnione podejrzenie, że felgi pochodzą z kradzieży (pkt 5.3 regulaminu).']);
check($s === 200 && $r['status'] === 'resolved', 'usunięcie ogłoszenia po zgłoszeniu', $r);

[$s, $r] = $anon->get("/listings/$sellId");
check($s === 404, 'usunięte ogłoszenie niewidoczne publicznie');
[$s, $r] = $seller->get('/me/listings');
$mine = array_values(array_filter($r['items'], fn ($i) => $i['id'] === $sellId))[0] ?? [];
check(($mine['status'] ?? '') === 'removed' && str_contains($mine['removal_reason'] ?? '', 'kradzieży'), 'autor widzi powód usunięcia', $mine);
[$s, $r] = $seller->get('/me/notifications');
$modNote = array_values(array_filter($r['items'], fn ($n) => $n['type'] === 'moderation'))[0] ?? [];
check(str_contains($modNote['body'] ?? '', 'pracownik') && str_contains($modNote['body'] ?? '', '14 dni'), 'uzasadnienie DSA art. 17 (kto zdecydował, jak się odwołać)', $modNote);
[$s, $r] = $seller->call('POST', "/listings/$sellId", form: $sellForm);
check($s === 403, 'usuniętego przez moderację nie da się edytować');
$log = (string) file_get_contents($mailLog);
check(str_contains($log, "Decyzja w sprawie zgłoszenia nr $reportId"), 'zgłaszający dostał decyzję');

[$s, $r] = $mod->post("/mod/listings/$sellId/restore", ['reason' => 'Właściciel przedstawił fakturę zakupu.']);
check($s === 200, 'przywrócenie ogłoszenia');
[$s, $r] = $anon->get("/listings/$sellId");
check($s === 200, 'przywrócone znów widoczne');

[$s, $r] = $mod->get('/mod/users?q=kupujacy');
$buyerId = $r['items'][0]['id'] ?? 0;
[$s, $r] = $mod->post("/mod/users/$buyerId/ban", ['reason' => 'Wielokrotne próby wyłudzenia przedpłaty.']);
check($s === 200, 'blokada konta');
[$s, $r] = $buyer->post("/conversations/$convId", ['body' => 'hej']);
check($s === 403, 'zablokowany nie może pisać');
[$s, $r] = $anon->get('/listings?type=buy');
check($r['total'] === 0, 'ogłoszenia zablokowanego znikają z listy');
[$s, $r] = (new Client($base))->post('/auth/login', ['email' => 'kupujacy@test.pl', 'password' => 'tajnehaslo1']);
check($s === 403 && str_contains($r['error'], 'wyłudzenia'), 'zablokowany nie zaloguje się, widzi powód', $r);
[$s, $r] = $mod->post("/mod/users/$buyerId/unban", ['reason' => 'Wyjaśnione — pomyłka.']);
check($s === 200, 'odblokowanie');
[$s, $r] = $mod->get('/mod/log');
check(count($r['items']) >= 5, 'log decyzji pracowników', count($r['items'] ?? []));

echo "Statusy, logowanie, reset hasła\n";
[$s, $r] = $seller->post("/listings/$sellId/status", ['status' => 'sold']);
check($s === 200 && $r['listing']['status'] === 'sold', 'oznaczenie jako sprzedane');
[$s, $r] = $anon->get('/listings?type=sell');
check($r['total'] === 0, 'sprzedane znika z listy');
[$s, $r] = $seller->post('/auth/logout');
[$s, $r] = $seller->get('/me');
check($r['user'] === null, 'wylogowanie');
[$s, $r] = $seller->post('/auth/login', ['email' => 'sprzedawca@test.pl', 'password' => 'zle']);
check($s === 401, 'złe hasło');
$seller->post('/auth/forgot', ['email' => 'sprzedawca@test.pl']);
[$s, $r] = $seller->post('/auth/reset', ['token' => last_link('nowe-haslo'), 'password' => 'nowehaslo123']);
check($s === 200, 'reset hasła', $r);
[$s, $r] = $seller->post('/auth/login', ['email' => 'sprzedawca@test.pl', 'password' => 'nowehaslo123']);
check($s === 200, 'logowanie nowym hasłem');

[$s, $r] = $seller->post('/me', ['marketing_consent' => true, 'seller_type' => 'company', 'company_name' => 'Felgi Kraków s.c.', 'company_nip' => '6762345678']);
check($s === 200 && $r['user']['marketing_consent'] && $r['user']['seller_type'] === 'company', 'zmiana ustawień: firma + zgoda', $r);
[$s, $r] = $anon->get("/users/" . $r['user']['id']);
check($s === 200 && $r['user']['company_name'] === 'Felgi Kraków s.c.' && !isset($r['user']['email']), 'profil publiczny z plakietką firmy');

echo "Usunięcie konta\n";
[$s, $r] = $seller->get('/me/export');
check($s === 200 && isset($r['konto'], $r['ogloszenia']), 'eksport danych');
[$s, $r] = $seller->post('/me/delete', ['password' => 'zle']);
check($s === 422, 'usunięcie konta wymaga hasła');
[$s, $r] = $seller->post('/me/delete', ['password' => 'nowehaslo123']);
check($s === 200, 'usunięcie konta');
[$s, $r] = $anon->get("/listings/$sellId");
check($s === 404, 'ogłoszenia usuniętego konta znikają');
[$s, $r] = (new Client($base))->post('/auth/login', ['email' => 'sprzedawca@test.pl', 'password' => 'nowehaslo123']);
check($s === 401, 'po usunięciu nie da się zalogować');
check(!is_dir(__DIR__ . "/../uploads/$sellId"), 'zdjęcia usuniętego konta skasowane z dysku');

[$s, $r] = $anon->get('/nie-ma-takiego');
check($s === 404, '404 dla nieznanego adresu');

echo "\n$checks sprawdzeń, błędów: $failures\n";
exit($failures ? 1 : 0);
