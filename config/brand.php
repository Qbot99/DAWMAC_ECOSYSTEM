<?php
/**
 * Szablon firmowy - tresc i uklad opisu oferty.
 *
 * Ten plik jest po to, zeby zmiana szablonu nie wymagala dotykania kodu.
 * Kolejnosc sekcji ustawia 'layout', tresc stalych blokow siedzi w 'blocks',
 * a class-template.php tylko sklada z tego strukture, ktorej oczekuje Allegro.
 *
 * ZASADY, KTORYCH NIE WOLNO ZLAMAC W TRESCI (Allegro odrzuca oferte):
 *  - zero linkow, adresow e-mail i numerow telefonu,
 *  - zero zachet do zakupu poza Allegro ("zapraszamy na nasza strone"),
 *  - w tekscie dzialaja tylko: h1, h2, p, ul, ol, li, b.
 * Sanitizer (class-text.php) i tak to wytnie, ale lepiej nie pisac tego wcale.
 *
 * >>> DO POTWIERDZENIA PRZEZ HUBERTA: wszystkie miejsca oznaczone [SPRAWDZ]
 *     to moje zalozenia o warunkach handlowych - podmien na realne.
 */

return [

	'brand' => [
		'name'  => 'DAWMAC',
		'claim' => 'Felgi aluminiowe i kute',
	],

	/**
	 * Grafiki szablonu. Allegro nie przyjmuje obrazkow z obcych serwerow -
	 * te pliki trafiaja raz przez POST /sale/images, a zwrocony URL laduje
	 * w cache (class-images.php) i jest podstawiany w kazdej ofercie.
	 *
	 * Zalecenie: szerokosc 1200 px, JPG, do 1 MB. Banery projektuj tak,
	 * zeby czytelnie skalowaly sie na telefonie - wiekszosc ruchu na Allegro
	 * jest mobilna, a sekcja dwukolumnowa schodzi tam do jednej kolumny.
	 */
	'images' => [
		'banner_top'    => 'assets/banners/dawmac-banner-top.jpg',
		'banner_trust'  => 'assets/banners/dawmac-trust.jpg',
		'banner_bottom' => 'assets/banners/dawmac-banner-bottom.jpg',
	],

	/**
	 * Kolejnosc sekcji w opisie. Usuniecie wpisu wylacza sekcje.
	 * Sekcje 'spec' i 'headline' sa budowane z danych produktu,
	 * reszta to stale bloki firmowe ponizej.
	 */
	'layout' => [
		'banner_top',
		'headline',
		'spec',          // parametry + zdjecie produktu, dwie kolumny
		'fitment',       // co dostajesz w zestawie / dopasowanie
		'about',         // o marce + grafika, dwie kolumny
		'shipping',      // wysylka + gwarancja, dwie kolumny
		'banner_bottom',
	],

	'blocks' => [

		'fitment' => [
			'title' => 'Co znajdziesz w zestawie',
			'html'  => '
				<ul>
					<li>Komplet felg w rozmiarze podanym w parametrach oferty.</li>
					<li>Felgi fabrycznie nowe, wolne od wad, z pelna dokumentacja.</li>
					<li>Zdjecia w ofercie sa <b>zdjeciami rzeczywistego produktu</b>.</li>
					<li>Srub, nakretek i pierscieni centrujacych <b>nie dolaczamy</b> - dobieramy je do konkretnego auta.</li>
				</ul>
				<p>Przed zakupem sprawdz rozstaw srub, srednice otworu centralnego i odsadzenie ET w parametrach oferty. Jesli nie masz pewnosci co do dopasowania, napisz przez formularz kontaktu Allegro i podaj marke, model oraz rocznik auta.</p>
			',
		],

		'about' => [
			'title' => 'Dlaczego DAWMAC',
			'html'  => '
				<ul>
					<li><b>Felgi to nasza jedyna specjalizacja</b> - nie handlujemy wszystkim po trochu.</li>
					<li>Wlasny magazyn - pozycje wystawione jako dostepne mamy fizycznie na miejscu.</li>
					<li>Doradzamy w doborze rozmiaru do konkretnego modelu auta.</li>
					<li>Obsluga po polsku, bez posrednikow i dropshippingu z zagranicy.</li>
				</ul>
			',
			'image' => 'banner_trust',
		],

		'shipping' => [
			'title' => 'Wysylka',
			'html'  => '
				<ul>
					<li>Nadajemy w <b>24 godziny</b> w dni robocze od zaksiegowania wplaty. [SPRAWDZ]</li>
					<li>Kazda felga pakowana osobno, komplet zabezpieczony na czas transportu.</li>
					<li>Przewoznika i koszt wybierasz w sekcji dostawy tej oferty.</li>
				</ul>
			',
		],

		'warranty' => [
			'title' => 'Gwarancja i zwrot',
			'html'  => '
				<ul>
					<li><b>24 miesiace gwarancji</b> na produkt. [SPRAWDZ]</li>
					<li><b>14 dni na zwrot</b> bez podawania przyczyny, na zasadach Allegro.</li>
					<li>Zwrot przyjmujemy w stanie nienaruszonym - felgi po montazu na aucie nie podlegaja zwrotowi.</li>
				</ul>
			',
		],
	],

	/**
	 * Limity. 'description_chars' to bezpiecznik na wypadek dlugich opisow
	 * z Woo - Allegro liczy caly opis, wiec obcinamy czesc produktowa,
	 * a nie stale bloki firmowe.
	 */
	'limits' => [
		'sections'         => 100,   // twardy limit Allegro
		'description_chars'=> 45000, // zapas wzgledem limitu serwisu
		'intro_chars'      => 900,   // opis z Woo w sekcji naglowkowej
		'title_chars'      => 75,    // tytul oferty Allegro
	],
];
