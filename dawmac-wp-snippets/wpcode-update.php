<?php
/**
 * Bezpieczna edycja snippetu WPCode z linii poleceń.
 *
 * DLACZEGO TO ISTNIEJE: WPCode trzyma skompilowany kod w opcji
 * `wpcode_snippets` i zapis samego post_content jej NIE odświeża — snippet
 * dalej działa po staremu. Skasowanie opcji "żeby się odbudowała" wyłącza
 * WSZYSTKIE snippety naraz (sprawdzone na produkcji, formularz zniknął
 * z kart produktów). Trzeba podmienić pola `code` i `compiled_code`
 * w tej opcji dla konkretnego id.
 *
 * URUCHAMIANIE (z katalogu WordPressa):
 *
 *   wp eval-file wpcode-update.php 201790 /sciezka/nowy-kod.php
 *   wp eval-file wpcode-update.php 336378 - "Nowa nazwa snippetu"
 *   wp eval-file wpcode-update.php 328701 off
 *
 * Argumenty:
 *   <id>            id snippetu (post WPCode)
 *   <plik|-|off>    plik z nowym kodem, "-" gdy zmieniamy tylko nazwę,
 *                   "off" gdy snippet ma przestać się wykonywać
 *   [nazwa]         opcjonalna nowa nazwa
 *
 * Wymaga --skip-themes i podniesionej pamięci (motyw Astra wywraca się
 * na domyślnym limicie 128 MB).
 */

$argumenty = array_values( $args ?? [] );

if ( count( $argumenty ) < 2 ) {
	WP_CLI::error( 'Użycie: eval-file wpcode-update.php <id> <plik|-|off> [nowa nazwa]' );
}

$id     = (int) $argumenty[0];
$zrodlo = (string) $argumenty[1];
$nazwa  = $argumenty[2] ?? null;

$post = get_post( $id );

if ( ! $post ) {
	WP_CLI::error( "Nie ma snippetu o id $id." );
}

WP_CLI::log( sprintf( 'Snippet #%d: %s', $id, $post->post_title ) );

/* ------------------------------------------------------------------ */
/* Wyłączenie                                                          */
/* ------------------------------------------------------------------ */

if ( 'off' === $zrodlo ) {
	wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ] );

	$opcja = get_option( 'wpcode_snippets' );
	$usuniete = 0;

	if ( is_array( $opcja ) ) {
		foreach ( $opcja as $grupa => $lista ) {
			foreach ( $lista as $i => $s ) {
				if ( is_array( $s ) && (int) ( $s['id'] ?? 0 ) === $id ) {
					unset( $opcja[ $grupa ][ $i ] );
					$opcja[ $grupa ] = array_values( $opcja[ $grupa ] );
					$usuniete++;
				}
			}
		}
		update_option( 'wpcode_snippets', $opcja );
	}

	WP_CLI::success( "Wyłączony (szkic) i usunięty z cache: $usuniete wpisów." );
	return;
}

/* ------------------------------------------------------------------ */
/* Podmiana kodu i/lub nazwy                                           */
/* ------------------------------------------------------------------ */

$nowyKod = null;

if ( '-' !== $zrodlo ) {
	if ( ! is_readable( $zrodlo ) ) {
		WP_CLI::error( "Nie mogę odczytać pliku: $zrodlo" );
	}
	$nowyKod = (string) file_get_contents( $zrodlo );

	if ( '' === trim( $nowyKod ) ) {
		WP_CLI::error( 'Plik jest pusty — to by wyłączyło snippet po cichu.' );
	}
}

$zmiany = [ 'ID' => $id ];

if ( null !== $nowyKod ) {
	$zmiany['post_content'] = $nowyKod;
}
if ( null !== $nazwa ) {
	$zmiany['post_title'] = $nazwa;
}

wp_update_post( $zmiany );

/* Cache WPCode — bez tego zmiana nie zadziała. */
$opcja      = get_option( 'wpcode_snippets' );
$znalezione = 0;

if ( is_array( $opcja ) ) {
	foreach ( $opcja as $grupa => $lista ) {
		foreach ( $lista as $i => $s ) {
			if ( ! is_array( $s ) || (int) ( $s['id'] ?? 0 ) !== $id ) {
				continue;
			}

			if ( null !== $nowyKod ) {
				$opcja[ $grupa ][ $i ]['code'] = $nowyKod;
				/*
				 * compiled_code MUSI zostać puste.
				 *
				 * U snippetów nietkniętych przez to narzędzie to pole jest
				 * pustym stringiem — WPCode wykonuje wtedy `code`. Pierwsza
				 * wersja tego narzędzia kopiowała tu kod "dla pewności"
				 * i zdjęła stronę sklepu: snippet ukrywający opony przestał
				 * działać poprawnie, <main> zszedł ze 152 tys. znaków do 8.
				 *
				 * Diagnoza zajęła długo, bo objaw wyglądał na błąd w samym
				 * snippecie — a zapytanie było poprawne i w izolacji zwracało
				 * 32 596 z 32 630 produktów.
				 */
				if ( array_key_exists( 'compiled_code', $s ) ) {
					$opcja[ $grupa ][ $i ]['compiled_code'] = '';
				}
			}
			if ( null !== $nazwa ) {
				$opcja[ $grupa ][ $i ]['title'] = $nazwa;
			}

			$opcja[ $grupa ][ $i ]['modified'] = time();
			$znalezione++;
		}
	}

	update_option( 'wpcode_snippets', $opcja );
}

if ( 0 === $znalezione ) {
	WP_CLI::warning( 'Snippetu nie ma w cache wpcode_snippets — sprawdź, czy jest aktywny.' );
} else {
	WP_CLI::success( sprintf( 'Zaktualizowany (cache: %d wpisów).', $znalezione ) );
}

if ( function_exists( 'do_action' ) ) {
	do_action( 'litespeed_purge_all' );
}
