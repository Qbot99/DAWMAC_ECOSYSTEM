import { useEffect } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useSession } from '../auth'

/*
 * Treść regulaminu i polityki prywatności to PROJEKT oparty na analizie
 * prawnej giełdy („DAWMAC Giełda – analiza prawna i wymagania”). Przed
 * startem musi go sprawdzić prawnik i trzeba uzupełnić dane firmy
 * (OPERATOR_INFO w .env). Przy każdej zmianie podbij TERMS_VERSION w .env —
 * użytkownicy dostaną prośbę o ponowną akceptację.
 */

function Draft() {
  return (
    <div className="notice notice-warn small">
      Projekt dokumentu do weryfikacji przez prawnika przed uruchomieniem giełdy.
    </div>
  )
}

function useHashScroll() {
  const { hash } = useLocation()
  useEffect(() => {
    if (hash) document.getElementById(hash.slice(1))?.scrollIntoView()
  }, [hash])
}

export function Terms() {
  const { config } = useSession()
  useHashScroll()
  const mail = config?.contact_email ?? ''
  return (
    <div className="container narrow legal">
      <h1>Regulamin giełdy felg</h1>
      <Draft />
      <p className="muted small">Wersja {config?.terms_version}</p>

      <h2>1. Postanowienia ogólne</h2>
      <ol>
        <li>Giełdę prowadzi {config?.operator} („DAWMAC”, „usługodawca”).</li>
        <li>Punkt kontaktowy dla użytkowników: <a href={`mailto:${mail}`}>{mail}</a>. Punkt kontaktowy dla organów państw członkowskich, Komisji Europejskiej i Rady (art. 11 DSA): <a href={`mailto:${config?.authority_email}`}>{config?.authority_email}</a>, języki: polski, angielski.</li>
        <li>Giełda jest bezpłatna. Nie płacisz za konto, ogłoszenia, wiadomości ani powiadomienia.</li>
        <li>Do korzystania potrzebujesz aktualnej przeglądarki internetowej i adresu e-mail.</li>
      </ol>

      <h2>2. Rola DAWMAC</h2>
      <ol>
        <li>DAWMAC udostępnia miejsce na ogłoszenia (hosting) i <b>nie jest stroną umów</b> zawieranych między użytkownikami.</li>
        <li>DAWMAC nie sprawdza stanu felg, tożsamości ogłoszeniodawców ani prawdziwości ogłoszeń. Nie sprawdza też ogłoszeń przed publikacją, ale reaguje na zgłoszenia.</li>
        <li>Jeśli w giełdzie pojawią się propozycje produktów lub realizacji DAWMAC, będą zawsze oznaczone jako „Propozycja DAWMAC – reklama” i pokazywane osobno od ogłoszeń użytkowników.</li>
      </ol>

      <h2>3. Konto</h2>
      <ol>
        <li>Konto może założyć osoba pełnoletnia. Jedna osoba — jedno konto.</li>
        <li>Przy koncie wskazujesz, czy ogłaszasz się jako osoba prywatna, czy przedsiębiorca. Przedsiębiorca podaje nazwę firmy i NIP; przy jego ogłoszeniach widnieje plakietka „Firma”. Przedsiębiorca sprzedający konsumentowi ma wszystkie obowiązki sprzedawcy wynikające z przepisów.</li>
        <li>Możesz w każdej chwili usunąć konto w ustawieniach konta. Razem z kontem usuwamy ogłoszenia, zdjęcia i wiadomości.</li>
      </ol>

      <h2>4. Ogłoszenia „Sprzedam” i „Kupię”</h2>
      <ol>
        <li>Ogłoszenie „Sprzedam” musi zawierać co najmniej jedno zdjęcie, średnicę, rozstaw śrub, ET, stan, cenę lub informację „do negocjacji” oraz miasto.</li>
        <li>Ogłoszenie jest widoczne przez {config?.listing_days ?? 60} dni. Przed końcem przypomnimy o tym e-mailem; ogłoszenie możesz wznowić, oznaczyć jako sprzedane lub zakończyć.</li>
        <li>Opis musi być zgodny z prawdą. Wady (krzywizny, pęknięcia, spawy, naprawy) trzeba opisać.</li>
      </ol>

      <h2 id="zakazane">5. Zakazane treści</h2>
      <ol>
        <li>Felgi pochodzące z kradzieży lub z nieznanego źródła.</li>
        <li>Podróbki i „repliki” oznaczone cudzym znakiem towarowym.</li>
        <li>Felgi z ukrytymi wadami konstrukcyjnymi opisywane jako sprawne.</li>
        <li>Zdjęcia skopiowane z cudzych ogłoszeń lub sklepów.</li>
        <li>Dane osobowe osób trzecich, linki do zewnętrznych płatności, prośby o przedpłatę „za kuriera”, treści obraźliwe lub niezgodne z prawem.</li>
      </ol>

      <h2 id="zdjecia">6. Zdjęcia i licencja</h2>
      <ol>
        <li>Dodając zdjęcie, oświadczasz, że wykonałeś je sam lub masz zgodę autora oraz zgodę osób widocznych na zdjęciu.</li>
        <li>Zdjęcia pozostają Twoją własnością. Udzielasz DAWMAC niewyłącznej, nieodpłatnej licencji, bez ograniczeń terytorialnych, na czas publikacji ogłoszenia i [X] miesięcy po jego zakończeniu, na polach eksploatacji: utrwalanie i zwielokrotnianie techniką cyfrową, publiczne udostępnianie w giełdzie i jej aplikacji, w tym zmniejszanie i kadrowanie.</li>
        <li>Z każdego zdjęcia automatycznie usuwamy metadane (np. lokalizację GPS). Zalecamy zasłonięcie tablic rejestracyjnych i twarzy.</li>
        <li>Użycie Twojego zdjęcia w reklamie DAWMAC wymaga Twojej osobnej zgody na konkretne zdjęcie.</li>
      </ol>

      <h2 id="kolejnosc">7. Kolejność ogłoszeń, dopasowania i powiadomienia</h2>
      <ol>
        <li>Ogłoszenia wyświetlamy domyślnie od najnowszych; możesz sortować według ceny i filtrować według auta, rozmiaru, rozstawu, stanu, regionu i typu ogłoszeniodawcy. Nikt nie płaci za wyższą pozycję, a DAWMAC nie obniża widoczności ogłoszeń konkurujących z jego ofertą.</li>
        <li>Giełda może informować o pasujących ogłoszeniach („Kupię” ↔ „Sprzedam”) na podstawie marki i modelu auta, rocznika, średnicy, rozstawu, ET i lokalizacji. Te powiadomienia są częścią usługi; możesz je wyłączyć w ustawieniach konta.</li>
        <li>Wiadomości z propozycjami DAWMAC wysyłamy tylko po osobnej, dobrowolnej zgodzie marketingowej, którą możesz wycofać jednym kliknięciem.</li>
      </ol>

      <h2 id="moderacja">8. Zgłaszanie treści i moderacja</h2>
      <ol>
        <li>Każdy, także bez konta, może zgłosić ogłoszenie lub użytkownika przyciskiem „Zgłoś”, podając powód, wyjaśnienie, imię i nazwisko, e-mail oraz oświadczenie o dobrej wierze. Potwierdzamy przyjęcie zgłoszenia i informujemy o decyzji.</li>
        <li>Moderatorzy DAWMAC mogą usunąć ogłoszenie albo zablokować konto naruszające regulamin lub prawo. Obecnie decyzje podejmują ludzie, bez narzędzi automatycznych.</li>
        <li>O każdej decyzji informujemy osobę, której dotyczy, podając co zrobiliśmy, dlaczego i na jakiej podstawie.</li>
        <li>Od decyzji możesz się odwołać w ciągu 14 dni, pisząc na <a href={`mailto:${mail}`}>{mail}</a>. Możesz też skorzystać z pozasądowego rozstrzygania sporów (art. 21 DSA) lub drogi sądowej.</li>
        <li>Przy podejrzeniu przestępstwa zagrażającego życiu lub bezpieczeństwu informujemy organy ścigania.</li>
      </ol>

      <h2>9. Odpowiedzialność</h2>
      <ol>
        <li>DAWMAC nie odpowiada za treść ogłoszeń ani za transakcje między użytkownikami, o ile nie wiedział o bezprawnym charakterze treści.</li>
        <li>Wobec konsumentów DAWMAC odpowiada zgodnie z przepisami, których nie można wyłączyć umową.</li>
      </ol>

      <h2>10. Reklamacje, zmiany, postanowienia końcowe</h2>
      <ol>
        <li>Reklamacje dotyczące działania giełdy przyjmujemy na <a href={`mailto:${mail}`}>{mail}</a> i odpowiadamy w ciągu 14 dni.</li>
        <li>O zmianach regulaminu informujemy z co najmniej 15-dniowym wyprzedzeniem. Możesz wtedy usunąć konto. Zakończenie działania giełdy ogłosimy z wyprzedzeniem, z możliwością pobrania swoich danych.</li>
        <li>Zasady przetwarzania danych opisuje <Link to="/prywatnosc">polityka prywatności</Link>.</li>
        <li>Regulamin podlega prawu polskiemu, co nie pozbawia konsumenta ochrony przepisów państwa jego zamieszkania.</li>
      </ol>
    </div>
  )
}

export function Privacy() {
  const { config } = useSession()
  const mail = config?.contact_email ?? ''
  return (
    <div className="container narrow legal">
      <h1>Polityka prywatności</h1>
      <Draft />
      <h2>Administrator</h2>
      <p>Administratorem danych jest {config?.operator}. Kontakt: <a href={`mailto:${mail}`}>{mail}</a>.</p>

      <h2>Jakie dane, po co i na jakiej podstawie</h2>
      <table className="table">
        <thead><tr><th>Cel</th><th>Dane</th><th>Podstawa</th></tr></thead>
        <tbody>
          <tr><td>Konto, ogłoszenia, wiadomości między użytkownikami</td><td>e-mail, nazwa, miasto, telefon (opcjonalnie), treść ogłoszeń i wiadomości, zdjęcia</td><td>umowa (art. 6 ust. 1 lit. b RODO)</td></tr>
          <tr><td>Powiadomienia o pasujących ogłoszeniach</td><td>parametry ogłoszeń, e-mail</td><td>umowa; możesz je wyłączyć</td></tr>
          <tr><td>Propozycje felg i realizacji DAWMAC</td><td>e-mail, ogłoszenia „Kupię”</td><td>Twoja zgoda (art. 6 ust. 1 lit. a RODO, art. 398 Prawa komunikacji elektronicznej)</td></tr>
          <tr><td>Moderacja, bezpieczeństwo, dowody w sporach</td><td>zgłoszenia, decyzje, adres IP przy logowaniu i zgłoszeniach</td><td>obowiązek prawny (DSA) i uzasadniony interes</td></tr>
        </tbody>
      </table>

      <h2>Jak długo</h2>
      <ul>
        <li>Zakończone ogłoszenia (sprzedane, wygasłe, usunięte) — kasujemy po 12 miesiącach razem ze zdjęciami.</li>
        <li>Konto — do jego usunięcia przez Ciebie. Usunięcie kasuje ogłoszenia, zdjęcia i wiadomości.</li>
        <li>Zgłoszenia i log decyzji moderacji — do czasu przedawnienia roszczeń.</li>
      </ul>

      <h2>Komu przekazujemy dane</h2>
      <p>Dostawcy hostingu i poczty e-mail, którzy przetwarzają je w naszym imieniu na podstawie umów powierzenia. Inni użytkownicy widzą Twoją nazwę, miasto, typ ogłoszeniodawcy i treść ogłoszeń; numer telefonu tylko wtedy, gdy włączysz „Pokaż numer” w ogłoszeniu, a e-maila — nigdy.</p>

      <h2>Zdjęcia</h2>
      <p>Z każdego zdjęcia automatycznie usuwamy metadane EXIF, w tym lokalizację GPS.</p>

      <h2>Twoje prawa</h2>
      <p>Masz prawo dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia, sprzeciwu oraz wycofania zgody w dowolnym momencie. Dane pobierzesz i konto usuniesz sam w ustawieniach konta. Możesz złożyć skargę do Prezesa UODO.</p>

      <h2>Ciasteczka</h2>
      <p>Używamy wyłącznie niezbędnego ciasteczka sesji (logowanie). Nie używamy ciasteczek reklamowych ani analitycznych.</p>
    </div>
  )
}

export function Safety() {
  return (
    <div className="container narrow legal">
      <h1>Bezpieczne kupowanie i sprzedawanie</h1>
      <ul>
        <li><b>Odbiór osobisty</b> to najbezpieczniejsza forma. Obejrzyj felgi na żywo, najlepiej na wyważarce.</li>
        <li><b>Nie płać przedpłat</b> nieznanym osobom, zwłaszcza „za kuriera” lub „za rezerwację”.</li>
        <li><b>Nie klikaj w linki do płatności</b> przesłane w wiadomości — to najczęstsza metoda wyłudzeń.</li>
        <li><b>Sprawdź felgi</b>: bicie, pęknięcia przy szprychach i rancie, ślady spawania, prostowania i szpachli.</li>
        <li><b>Sprawdź dopasowanie</b>: rozstaw (PCD), ET, otwór centralny i szerokość muszą pasować do auta. W razie wątpliwości zapytaj w serwisie.</li>
        <li><b>Felgi z kradzieży</b>: jeśli cena jest podejrzanie niska, a sprzedający nie wie nic o pochodzeniu, zrezygnuj i zgłoś ogłoszenie.</li>
        <li>Rozmawiaj przez wiadomości w giełdzie — zostaje ślad, a Twój e-mail nie jest nikomu pokazywany.</li>
      </ul>
    </div>
  )
}

export function Contact() {
  const { config } = useSession()
  return (
    <div className="container narrow legal">
      <h1>Kontakt</h1>
      <p>Giełdę prowadzi {config?.operator}.</p>
      <p>Pytania, reklamacje i odwołania od decyzji moderacji: <a href={`mailto:${config?.contact_email}`}>{config?.contact_email}</a></p>
      <p>Punkt kontaktowy dla organów (art. 11 DSA): <a href={`mailto:${config?.authority_email}`}>{config?.authority_email}</a> — język polski lub angielski.</p>
      <p>Naruszenie w konkretnym ogłoszeniu najszybciej zgłosisz przyciskiem „Zgłoś” przy ogłoszeniu.</p>
    </div>
  )
}
