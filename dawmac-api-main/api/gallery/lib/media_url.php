<?php
/**
 * Linki zewnętrzne przy projekcie: film na YouTube i aukcja.
 *
 * Pola są opcjonalne i wklejane ręcznie, więc trafi tu wszystko — adres
 * z aplikacji mobilnej, skrócony link, adres z parametrami śledzącymi.
 * Sprowadzamy to do postaci, którą da się bezpiecznie wypuścić na stronę.
 */

if (!function_exists('dawmac_youtube_id')) {

    /**
     * Wyciąga 11-znakowe ID filmu z dowolnej postaci adresu YouTube:
     * watch?v=, youtu.be/, /shorts/, /embed/, /live/ albo samo ID.
     * Zwraca null, gdy to nie jest YouTube — wtedy link odrzucamy,
     * zamiast wstawiać na stronę odtwarzacz, który się nie uruchomi.
     */
    function dawmac_youtube_id(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
            return $url;
        }

        $wzorce = [
            '~[?&]v=([A-Za-z0-9_-]{11})~',
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~/shorts/([A-Za-z0-9_-]{11})~',
            '~/embed/([A-Za-z0-9_-]{11})~',
            '~/live/([A-Za-z0-9_-]{11})~',
        ];

        foreach ($wzorce as $wzorzec) {
            if (preg_match($wzorzec, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Kanoniczny adres filmu. Zapisujemy w jednej postaci, żeby ten sam film
     * wklejony raz jako youtu.be, a raz jako watch?v= nie wyglądał na dwa różne.
     */
    function dawmac_youtube_url(?string $url): string
    {
        $id = dawmac_youtube_id($url);

        return $id === null ? '' : 'https://www.youtube.com/watch?v=' . $id;
    }

    /**
     * Link do filmu — YouTube ALBO Facebook.
     *
     * Filmy trafiają na oba serwisy, więc pole musi przyjąć jedno i drugie.
     * YouTube sprowadzamy do postaci kanonicznej (ten sam film wklejony raz
     * jako youtu.be, a raz jako watch?v= to nie są dwa różne filmy).
     * Adresy Facebooka zostawiamy jak są — reels i /videos/ mają tam
     * identyfikatory, których nie ma sensu przepisywać.
     *
     * Wszystko inne odrzucamy: na stronie ma się pojawić odnośnik do filmu,
     * a nie do przypadkowej strony.
     */
    function dawmac_video_url(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        $kanoniczny = dawmac_youtube_url($url);

        if ($kanoniczny !== '') {
            return $kanoniczny;
        }

        if (preg_match('~^https?://([a-z0-9-]+\.)*(facebook\.com|fb\.watch)/~i', $url)) {
            return mb_substr($url, 0, 255);
        }

        return '';
    }

    /**
     * Czy adres wskazuje na Facebooka (do etykiety przy odnośniku).
     */
    function dawmac_video_is_facebook(?string $url): bool
    {
        return (bool) preg_match('~^https?://([a-z0-9-]+\.)*(facebook\.com|fb\.watch)/~i', (string) $url);
    }

    /**
     * Link do aukcji. Wymagamy http/https i przycinamy do długości kolumny.
     * Nie ograniczamy do Allegro — dziś sprzedajesz też gdzie indziej.
     */
    function dawmac_auction_url(?string $url, int $max = 500): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return mb_substr($url, 0, $max);
    }
}
