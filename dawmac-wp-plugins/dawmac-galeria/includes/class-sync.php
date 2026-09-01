<?php
/**
 * Codzienne odświeżanie słownika felg (sklep -> tabela wheel_dict w galerii).
 *
 * DLACZEGO PRZEZ WP-CRON, A NIE ZWYKŁY CRON: konto SSH na dhosting nie ma
 * prawa do polecenia `crontab` ("You are not allowed to use this program").
 * Harmonogram WordPressa natomiast działa — DISABLE_WP_CRON jest włączone,
 * a zadania mają terminy w przyszłości, czyli wyzwala je coś z zewnątrz.
 * Podpinamy się pod ten działający mechanizm.
 *
 * Sama synchronizacja zostaje w sync_wheel_dict.php i tylko ją odpalamy.
 * Nie kopiujemy logiki do wtyczki, żeby nie rozjechały się dwie wersje.
 *
 * db.php z API NIE MOŻE być dołączony do WordPressa — wysyła nagłówki
 * Content-Type: application/json i CORS. Dlatego osobny proces, nie require.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Galeria_Sync {

	const HOOK      = 'dawmac_galeria_sync_slownik';
	const OPT_LOG   = 'dawmac_galeria_sync_log';
	const SKRYPT    = '/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/tools/cron-sync-slownik.sh';

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'uruchom' ] );
		add_action( 'init', [ __CLASS__, 'zaplanuj' ] );
	}

	/** Raz dziennie; pierwszy przebieg nad ranem, gdy ruch jest najmniejszy. */
	public static function zaplanuj(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		$jutro = strtotime( 'tomorrow 4:17', current_time( 'timestamp' ) );
		wp_schedule_event( $jutro ?: time() + DAY_IN_SECONDS, 'daily', self::HOOK );
	}

	public static function odplanuj(): void {
		$czas = wp_next_scheduled( self::HOOK );

		if ( $czas ) {
			wp_unschedule_event( $czas, self::HOOK );
		}
	}

	/**
	 * Odpala skrypt i zapisuje wynik, żeby dało się sprawdzić w kokpicie,
	 * czy synchronizacja w ogóle chodzi. Bez tego cicha awaria wyszłaby
	 * dopiero wtedy, gdy ktoś zauważy brakującą podpowiedź w panelu.
	 */
	public static function uruchom(): array {
		$wynik = [ 'kiedy' => current_time( 'mysql' ), 'ok' => false, 'tekst' => '' ];

		if ( ! function_exists( 'exec' ) ) {
			$wynik['tekst'] = 'Funkcja exec jest wyłączona na tym serwerze.';
		} elseif ( ! is_readable( self::SKRYPT ) ) {
			$wynik['tekst'] = 'Nie znalazłem skryptu: ' . self::SKRYPT;
		} else {
			$linie = [];
			$kod   = 1;
			exec( escapeshellcmd( self::SKRYPT ) . ' 2>&1', $linie, $kod );

			$wynik['ok']    = ( 0 === (int) $kod );
			$wynik['tekst'] = trim( implode( "\n", array_slice( $linie, -12 ) ) );

			if ( '' === $wynik['tekst'] ) {
				$wynik['tekst'] = $wynik['ok']
					? 'Gotowe (skrypt nic nie wypisał).'
					: 'Skrypt zakończył się kodem ' . (int) $kod . '.';
			}
		}

		update_option( self::OPT_LOG, $wynik, false );

		// Nowe pary w słowniku mogą zmienić to, co pokazujemy na kartach.
		Dawmac_Galeria_API::wyczysc_cache();

		return $wynik;
	}

	public static function ostatni(): array {
		$l = get_option( self::OPT_LOG );

		return is_array( $l ) ? $l : [];
	}
}
