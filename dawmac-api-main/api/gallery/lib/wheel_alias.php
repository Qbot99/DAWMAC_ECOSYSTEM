<?php
/**
 * Aliasy felg — trwałe powiązania zapisów, których normalizacja nie złapie.
 *
 * Normalizacja radzi sobie z wielkością liter, spacjami i myślnikami:
 * "hx 03 6", "HX-036" i "Hx036" to ten sam klucz HX036. Nie poradzi sobie
 * z literówką w słowie: FORZA i FORZZA to dla niej dwie różne marki.
 *
 * Takie przypadki rozstrzyga człowiek — raz — a wynik ląduje w wheel_alias.
 * Świadomie NIE zgadujemy tego automatycznie: w danych są pary różniące się
 * jednym znakiem, które są innymi felgami (Stuttgart ST4 vs ST3, Platin P115
 * vs P113), a ROTA i YOTA to dwie prawdziwe marki w katalogu.
 */

if (!function_exists('dawmac_wheel_aliases')) {

    /** Wszystkie aliasy, wczytane raz na żądanie. */
    function dawmac_wheel_aliases(mysqli $conn): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];

        // Tabela może jeszcze nie istnieć (migracja 03 nieuruchomiona) —
        // wtedy po prostu nie ma aliasów i wszystko działa jak dotąd.
        $res = @$conn->query("SELECT from_brand, from_model, to_brand, to_model FROM wheel_alias");

        if ($res) {
            while ($a = $res->fetch_assoc()) {
                $cache[$a['from_brand'] . "\x1f" . $a['from_model']] = [$a['to_brand'], $a['to_model']];
            }
        }

        return $cache;
    }

    /**
     * Sprowadza parę do postaci docelowej.
     * Najpierw alias pełnej pary (marka+model), potem alias samej marki.
     * Zwraca [brand_norm, model_norm].
     */
    function dawmac_wheel_resolve(mysqli $conn, string $brandNorm, string $modelNorm): array
    {
        $aliasy = dawmac_wheel_aliases($conn);

        $para = $aliasy[$brandNorm . "\x1f" . $modelNorm] ?? null;
        if ($para !== null) {
            return [$para[0], $para[1] !== '' ? $para[1] : $modelNorm];
        }

        $marka = $aliasy[$brandNorm . "\x1f"] ?? null;
        if ($marka !== null) {
            return [$marka[0], $modelNorm];
        }

        return [$brandNorm, $modelNorm];
    }
}
