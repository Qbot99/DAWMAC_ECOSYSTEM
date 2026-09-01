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
					<li>Doradzamy w doborze rozmiaru do konkretnego modelu auta.</li>
					<li>Felgi fabrycznie nowe, prosto od producenta.</li>
				</ul>
			',
			'image' => 'banner_trust',
		],

		/**
		 * ZAKAZANE W OPISIE. Allegro odrzucilo oferte z tymi sekcjami:
		 * "Usun z opisu przedmiotu dane dotyczace wysylki, dostawy, platnosci"
		 * oraz "W opisie oferty umieszczasz informacje dotyczace zwrotu towaru".
		 * Te tresci naleza wylacznie do zakladek Dostawa i platnosc oraz Zwroty.
		 * Kara: usuniecie oferty, a nawet zawieszenie konta.
		 *
		 * Bloki zostawione dla pamieci - NIE dopisuj ich z powrotem do 'layout'.
		 */
		'shipping' => [
			'title' => 'Wysylka',
			'html'  => '
				<ul>
					<li>Nadajemy w <b>48 godzin</b> w dni robocze od zaksiegowania wplaty.</li>
					<li>Kazda felga pakowana osobno, komplet zabezpieczony na czas transportu.</li>
					<li>Przewoznika i koszt wybierasz w sekcji dostawy tej oferty.</li>
				</ul>
			',
		],

		'warranty' => [
			'title' => 'Gwarancja i zwrot',
			'html'  => '
				<ul>
					<li>Reklamacje i zwroty na zasadach zapisanych w warunkach tej oferty.</li>
					<li><b>14 dni na zwrot</b> bez podawania przyczyny, na zasadach Allegro.</li>
					<li>Zwrot przyjmujemy w stanie nienaruszonym - felgi po montazu na aucie nie podlegaja zwrotowi.</li>
				</ul>
			',
		],
	],

	/**
	 * Obudowa oferty - odwzorowana z oferty 14692637195, ktora juz dziala
	 * na koncie. Nie wymyslamy wlasnych ustawien tam, gdzie sprzedawca ma
	 * juz swoje: rozbieznosc w warunkach dostawy czy zwrotow miedzy ofertami
	 * to pytania od kupujacych i ryzyko sporu.
	 *
	 * Identyfikatory pochodza z konta DawmacPolska i sa dla niego stale.
	 */
	'oferta' => [
		'kategoria'      => '257711',   // Felgi > Do samochodow > Aluminiowe
		'handling_time'  => 'PT48H',    // tyle deklaruje istniejaca oferta
		'jednostka'      => 'SET',      // cena dotyczy kompletu, nie sztuki
		'faktura'        => 'VAT',
		'stawka_vat'     => '23.00',

		'lokalizacja' => [
			'countryCode' => 'PL',
			'province'    => 'WIELKOPOLSKIE',
			'city'        => 'Perzów',
			'postCode'    => '63-642',
		],

		'po_sprzedazy' => [
			'impliedWarranty' => 'ba786cbb-f803-4fcf-b4b3-0b6fc1b5c417',
			'returnPolicy'    => '59d00829-42d7-4d44-a387-af963dfb122d',
		],

		/**
		 * GPSR - bez tego oferta nie przejdzie walidacji:
		 * RESPONSIBLE_PRODUCER_NOT_SPECIFIED i SAFETY_INFO_NOT_DEFINED.
		 *
		 * Identyfikatory z GET /sale/responsible-producers. Dla marek bez
		 * wlasnego wpisu producentem odpowiedzialnym jest DAWMAC Polska -
		 * jako importer wprowadzajacy towar na rynek UE, co jest zgodne
		 * z rozporzadzeniem.
		 *
		 * >>> DO POTWIERDZENIA: tresc 'bezpieczenstwo' to informacja
		 *     wymagana prawem. Napisalem ja rzeczowo, ale przejrzyj.
		 *     Deklaracja NO_SAFETY_INFORMATION przy czesci odpowiadajacej
		 *     za bezpieczenstwo jazdy byla by trudna do obrony.
		 */
		'gpsr' => [
			'producenci' => [
				// Concaver i Japan Racing to marki jednego producenta - JR Wheels.
				// Oba wpisy maja te same dane teleadresowe, roznia sie nazwa,
				// zeby kupujacy widzial na ofercie marke, ktora kupuje.
				'Japan Racing' => '6ad4e7f5-c7d3-4c4f-97b3-100f7fb0cdf2',
				'Concaver'     => 'f7a13193-9f21-4c59-9881-402b0b7e23b8',
				'domyslny'     => 'be1f5f5c-4148-4e23-92ec-776bb3da6f27', // DAWMAC Polska jako importer
			],
			'bezpieczenstwo' => 'Felgi aluminiowe do samochodow osobowych.'
				. "\n" . 'Przed zakupem sprawdz zgodnosc rozmiaru, rozstawu srub, srednicy otworu centralnego oraz odsadzenia ET z zaleceniami producenta pojazdu.'
				. "\n" . 'Montaz powierz serwisowi dysponujacemu odpowiednim wyposazeniem.'
				. "\n" . 'Stosuj sruby lub nakretki wlasciwego typu i dlugosci, dokrecane momentem podanym przez producenta pojazdu.'
				. "\n" . 'Po przejechaniu pierwszych 50-100 km sprawdz moment dokrecenia.'
				. "\n" . 'Nie uzywaj felg uszkodzonych, peknietych ani odksztalconych.',
		],

		// Cennik dostawy per producent; 'domyslny' lapie reszte.
		// Japan Racing i Concaver ida z wysylka 0 zl (ustalenie z 2026-09-01).
		'cenniki_dostawy' => [
			'Japan Racing' => '58445eb2-893e-4d75-95e8-dbe1e24d1c70', // "0zl"
			'Concaver'     => '58445eb2-893e-4d75-95e8-dbe1e24d1c70', // "0zl"
			'domyslny'     => '58445eb2-893e-4d75-95e8-dbe1e24d1c70',
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
