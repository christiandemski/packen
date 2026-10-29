# Übergabe: Packliste mit Outfit-Planer

Dieses Dokument beschreibt ein neues, eigenständiges Webprojekt. Es fasst alle Entscheidungen aus der Konzeptphase zusammen und ist die Arbeitsgrundlage für die Umsetzung. Bitte zuerst komplett lesen, dann mit Meilenstein 1 beginnen.

---

## 1. Ziel

Eine Web-App, mit der ich (Admin) und eingeladene Freunde Reisen packen:

- **Packliste**, die sich aus wiederverwendbaren Vorlagen (Modulen) zusammensetzt und mit jeder Reise dazulernt.
- **Outfit-Planer**, der pro Tag festlegt, was man trägt. Daraus berechnen sich die Kleidungsmengen in der Packliste, damit man nichts Überflüssiges mitnimmt.
- **Gemeinsame Reisen** mit Freunden, inklusive Gruppen-Gegenständen und Anfragen in die Gruppe („Hat jemand einen Föhn?“).

Die App ist ein eigenes Projekt. Später soll sie per API an meinen Aktivitäten-Planer „unterwegs“ angebunden werden (siehe Abschnitt 9), darf davon aber nicht abhängen.

---

## 2. Rahmenbedingungen

| Thema | Entscheidung |
|---|---|
| Hosting | Netcup Webhosting 8000, **kein Docker**, PHP + MariaDB |
| Framework | Laravel (aktuelle LTS-/Stable-Version), Document Root zeigt auf `/public` |
| Frontend | Blade + Alpine.js, mobile-first; kein separates SPA-Projekt |
| Offline | PWA mit Service Worker, Packliste muss offline abhakbar sein und später synchronisieren |
| Domain | `packen.allpixx.de` (Netcup Plesk, PHP 8.5, Deployment per Git-Webhook, siehe README) |
| Sprache | Oberfläche komplett Deutsch |
| Tests | Pest (Feature-Tests für Rechte und Kernlogik sind Pflicht) |

---

## 3. Benutzer und Rollen

### Anmeldung

- **Admin (ich):** Anmeldung über meine zentrale Anmeldung **BearID** (`id.codekorb.de`). Vor der Umsetzung prüfen, welches Protokoll BearID anbietet (bevorzugt OpenID Connect, dann z. B. per Socialite-Provider). Bis das geklärt ist, darf es einen lokalen Admin-Login als Fallback geben.
- **Freunde:** **kein Zugang zu BearID.** Sie haben lokale Konten nur in dieser App und melden sich **passwortlos per E-Mail-Link** an.
- **Keine offene Registrierung.** Konten entstehen nur durch Einladung des Admins.

### Magic-Link-Regeln

- Link ist einmalig gültig und läuft nach 15 Minuten ab.
- Rate-Limit pro E-Mail-Adresse und pro IP.
- Die Antwort auf „Link anfordern“ verrät nie, ob eine Adresse existiert.
- Nach dem Login bleibt die Sitzung lange bestehen (z. B. 30 Tage, „Angemeldet bleiben“).
- Gesperrte Konten können keinen Link anfordern; bestehende Sitzungen werden beim Sperren beendet.

### Rollen

| Rolle | Darf |
|---|---|
| `admin` | alles eines Freundes, zusätzlich Freunde einladen, sperren, entsperren, löschen; Admin-Seite sehen |
| `friend` | eigenen Katalog und eigene Vorlagen pflegen, Reisen anlegen, zu Reisen eingeladen werden |

Pro Reise gibt es zusätzlich `owner` (Ersteller) und `member`.

### Grundregel für Rechte

**Jede Abfrage prüft Besitz bzw. Mitgliedschaft** (Laravel Policies, von Anfang an):

- Katalog, Vorlagen: nur der Besitzer.
- Reise: nur Mitglieder. Reisedaten ändern, Mitglieder einladen/entfernen, Reise löschen: nur `owner`.
- Persönliche Packliste und Outfits: nur der Besitzer; Outfits zusätzlich für Mitreisende sichtbar, wenn freigegeben.
- Packlisten-Freigabe: Jede Person kann in einer Reise einzelnen Mitreisenden erlauben, ihre Packliste zu sehen und abzuhaken (z. B. Partner, Kind, Freundin, die mitpackt). Die Freigabe gilt nur in eine Richtung und nur für diese Reise.
- Gruppen-Gegenstände und Anfragen: alle Mitglieder der Reise.

---

## 4. Datenmodell (Entwurf)

```
users             id, name, email, auth_source (bearid|local), bearid_sub (nullable),
                  role (admin|friend), active (bool), last_login_at, timestamps,
                  avatar_path (nullable)                  // 256-px-Ausschnitt wie in „unterwegs“, privat ausgeliefert

invitations       id, email, name, invited_by, token_hash, expires_at, accepted_at

items             id, owner_id, name, category (feste Liste, siehe App\Enums\ItemCategory),
                  category_id (nullable → eigene Kategorie; category steht dann auf misc),
                  weight_g (nullable),
                  is_clothing (bool, aus Kategorie abgeleitet), per_day (bool),   // per_day z. B. Socken, Tageslinsen
                  pack_last (bool),                       // landet in „Am Abfahrtstag“
                  rain_protection (bool),                 // für den Regenhinweis; neu angelegt nach Namen vorbelegt
                  notes, timestamps

templates         id, owner_id, name, description (nullable)   // z. B. Basis, Städtetrip, Bikepacking
template_items    template_id, item_id, quantity

trips             id, owner_id, name, start_date, end_date,
                  type (citytrip|beach|hiking|bikepacking|running_event|winter|other),
                  accommodation, transports (JSON-Liste, z. B. Flugzeug + Mietwagen), location (nullable), timestamps
trip_members      trip_id, user_id, role (owner|member), outfits_shared (bool), joined_at
trip_templates    trip_id, user_id, template_id           // welche Module jemand gewählt hat
packlist_grants   trip_id, owner_id, grantee_id           // grantee darf die Packliste von owner sehen/abhaken

containers        id, owner_id, name, kind (suitcase|backpack|box|bag|other),
                  capacity_l (nullable), max_weight_g (nullable), empty_weight_g (nullable)
trip_containers   trip_id, user_id, container_id          // was auf diese Reise mitkommt
items             + default_container_id (nullable)       // Standardplatz, z. B. Kocher → Eurobox Küche

packlist_entries  id, trip_id, user_id, item_id (nullable), custom_name (nullable),
                  quantity, group (departure_day|clothing|documents|...|custom),
                  container_id (nullable), packed (bool), packed_at,
                  quantity_locked (bool), excluded (bool), note (nullable),
                  source (template|outfit|manual|group_item),
                  group_item_id (nullable),
                  review (null|unused|missing), timestamps

outfit_days       id, trip_id, user_id, date, title (nullable), laundry_day (bool)
outfit_slots      id, outfit_day_id, name (Tag|Abend|Sport|Anreise|...), position
outfit_slot_items outfit_slot_id, item_id

group_items       id, trip_id, name, note (nullable),
                  kind (planned|request),
                  requested_by (nullable), assigned_user_id (nullable),
                  status (open|claimed|withdrawn), claimed_at (nullable), timestamps
```

Hinweise:

- `item_id` in `packlist_entries` ist nullable für spontane Freitext-Einträge.
- `container_id` und `quantity` hängen am Eintrag, nicht am Gegenstand: dasselbe Teil kann je Reise in unterschiedlichen Behältnissen landen. `default_container_id` am Gegenstand ist nur der Vorschlag.
- Zusammenführen mehrerer Module: pro Gegenstand zählt die **größte** Menge (nicht die Summe); „pro Tag“ = Menge × Reisetage. Von Hand geänderte Mengen setzen `quantity_locked`, entfernte Vorlagen-Einträge werden nur `excluded` – beides übersteht jedes Neuberechnen. Gruppe ergibt sich aus dem Gegenstand (`pack_last` → „Am Abfahrtstag“, Kleidungskategorien → „Kleidung“), nur Freitext speichert `group`. Eigene Kategorien (`categories`: owner_id, name, icon) bekommen eine eigene Karte vor „Sonstiges“, Schlüssel `c:<id>`. Weggelassene Vorlagen-Einträge zeigt die Reise unter „Diesmal nicht dabei“ und holt sie auf Wunsch zurück („Liste anpassen“). Wurde ein Gegenstand schon auf früheren Reisen weggelassen, schlägt die Liste vor, ihn aus der Vorlage zu streichen. Die feste Kategorie „Vor der Abfahrt erledigen“ (`tasks`) enthält Aufgaben statt Gegenständen: eigene Karte direkt nach „Am Abfahrtstag“, ohne Gewicht und Behältnis. Wetterhinweise gibt es für Regen, Kälte (Tiefstwert ≤ 5 °C) und Sonne (≥ 25 °C, höchstens leicht bewölkt), gesteuert über `rain_protection`, `cold_protection` und `sun_protection` am Gegenstand (siehe App\Enums\WeatherNeed). Feste Kategorien lassen sich je Person umbenennen oder ausblenden (`category_settings`, App\Support\CategorySettings; „Sonstiges“ bleibt immer sichtbar). Am Abfahrtstag erinnert Web Push an Offenes aus „Am Abfahrtstag“ und den Aufgaben (App\Services\DepartureReminder, Auslöser `/cron/abfahrt?token=…`, siehe README).
- Kleidungsmengen werden aus den Outfits **berechnet** (siehe 5.4) und als Einträge mit `source = outfit` gepflegt. Manuelle Korrekturen dürfen nicht beim nächsten Neuberechnen verloren gehen.

---

## 5. Funktionen nach Meilensteinen

Meilenstein 1–4 ergeben ein vollständig nutzbares Tool für mich allein. Ab Meilenstein 5 wird es eine Gruppen-App.

### 5.1 Meilenstein 1: Benutzer und Admin

- Login Admin (BearID, Fallback lokal), Login Freunde per Magic Link.
- Admin-Seite: Freunde einladen (Name, E-Mail), Liste mit Status, sperren/entsperren, löschen (mit Bestätigung; löscht bzw. anonymisiert die Daten der Person).
- Profilseite: Name ändern, Abmelden, „Mein Konto löschen“.
- Mailversand über SMTP des Hostings (Zugangsdaten in `.env`).

### 5.2 Meilenstein 2: Katalog und Vorlagen

- Eigener Gegenstands-Katalog mit Kategorie, optional Gewicht, Kennzeichen „Kleidung“ und „pro Tag“.
- Vorlagen (Module) anlegen, Gegenstände mit Menge zuordnen.
- Beim ersten Login ein paar Start-Vorlagen anbieten (Basis, Städtetrip, Strand, Wandern, Bikepacking), die man übernehmen oder ignorieren kann.

### 5.3 Meilenstein 3: Reisen und Packliste

- Reise anlegen: Name, Zeitraum, Reiseart, Unterkunft, Transport, optional Ort.
- Module auswählen und kombinieren; doppelte Gegenstände werden zusammengeführt.
- Packliste abhaken, Fortschritt („18 von 42 gepackt“), Filter „Nur offene“.
- Gruppe **„Am Abfahrtstag“** steht immer oben (Ladekabel, Zahnbürste, Medikamente …).
- Freitext-Einträge, Mengen ändern, Einträge löschen.
- Liste von einer früheren Reise kopieren.

### 5.3b Meilenstein 3b: Behältnisse (optional nutzbar) – umgesetzt

Für Reisen, bei denen auf Koffer, Rucksäcke und Euroboxen verteilt wird (typisch mit dem Auto).

- Eigene Behältnisse pflegen: Name, Art, optional Volumen (l), Maximal- und Leergewicht.
- Pro Reise auswählen, welche Behältnisse mitkommen; Einträge zuordnen (beim Abhaken oder per Mehrfachauswahl).
- Packliste umschaltbar: nach Gruppe oder nach Behältnis; pro Behältnis Anzahl Einträge, Gewicht und Hinweis, wenn Grenzen überschritten werden.
- Standardplatz am Gegenstand übernimmt neue Einträge automatisch ins passende Behältnis, sofern es auf der Reise dabei ist.
- Übersicht „Du brauchst: 1 Koffer, 1 Rucksack, 2 Euroboxen“ sowie ungeordnete Einträge.
- Wer nichts zuordnet, sieht davon nichts.
- **Gesamtgewicht** (`ContainerService::totalWeight`) in der Fortschrittskarte: alle sichtbaren Einträge × Menge plus Leergewicht der gewählten Behältnisse. Kleidung aus dem Outfit-Slot „Anreise“ am ersten Tag (sonst dessen Slot „Tag“) trägt man am Körper: je Teil ein Stück zählt weder zum Gesamtgewicht noch zum Behältnis („ohne 1,2 kg Anreise-Kleidung“; API: `worn_g`). Fehlt bei Einträgen das Gewicht, steht „mind.“ davor und die Anzahl dahinter; ohne jedes Gewicht bleibt die Zeile weg.
- Offen: Volumen pro Gegenstand, um den Bedarf automatisch zu schätzen – erst, wenn sich die Zuordnung von Hand als zu mühsam erweist.

### 5.4 Meilenstein 4: Outfit-Planer

- Tage werden aus dem Reisezeitraum erzeugt.
- Pro Tag Slots (Standard: Tag, optional Abend/Sport/Anreise), pro Slot Kleidungsteile aus dem Katalog.
- **Wochenübersicht** als Tabelle: Zeilen = Kleidungsteile (gruppiert nach Kategorie), Spalten = Tage, Punkt = getragen, rechts Summe. Teile mit nur einem Einsatz werden orange markiert („streichen oder kombinieren?“).
- Tag antippen → darunter Detailansicht der Outfits dieses Tages.
- **Waschtag** pro Tag markierbar.
- **Mengenberechnung für die Packliste:**
  - Teil mit `per_day = false`: Menge 1, wenn es in mindestens einem Slot vorkommt.
  - Teil mit `per_day = true`: höchste Anzahl an Tagen zwischen zwei Waschtagen (bzw. Reisebeginn/-ende).
- Button „In die Packliste übernehmen“ aktualisiert die Kleidungseinträge.
- Bei mehr als 7 Tagen: Spalten horizontal scrollbar.

**Umgesetzt – Präzisierungen:**

- Ein Waschtag beendet einen Abschnitt (gewaschen wird abends); „pro Tag“ zählt die **Einsätze** im Abschnitt – Socken in „Tag“ und „Sport“ am selben Tag = 2.
- „In die Packliste übernehmen“ setzt die Kleidungseinträge auf `source = outfit`. Von Hand geänderte Mengen (`quantity_locked`) bleiben; Teile, die in keinem Outfit mehr vorkommen, fallen an die Vorlagen zurück (oder weg, wenn keine Vorlage sie enthält).
- Zusätzlich: „Wie am Vortag“ kopiert Slots und Teile des vorigen Tages; im Auswahlblatt lässt sich ein neues Kleidungsstück direkt anlegen.
- **Wetter** (Open-Meteo, ohne Schlüssel; `WeatherService`): Das Reiseziel wird per Ortssuche in Koordinaten umgesetzt (nur der Teil vor dem ersten Komma, 30 Tage zwischengespeichert), die Vorhersage für die Reisetage im 16-Tage-Fenster 3 Stunden. Tageskacheln zeigen die Höchsttemperatur, die Tageskarte Wetterlage, Min/Max und Regenwahrscheinlichkeit ab 20 %. Fällt der Dienst aus, fehlt nur das Wetter. Übertragen werden nur Ortsname und Koordinaten.

### 5.5 Meilenstein 5: Gemeinsame Reisen und Gruppen-Gegenstände – umgesetzt

- Owner lädt Mitglieder ein (aus bestehenden Freunden der App; Admin kann neue Freunde einladen, die nach Registrierung direkt in der Reise landen).
- Jedes Mitglied hat **eigene** Packliste und **eigene** Outfits; Zeitraum, Ort und Reisedaten sind gemeinsam.
- Outfits optional für Mitreisende freigeben (`outfits_shared`), Ansicht dann nur lesend.

**Gruppen-Gegenstände** (`group_items`) gibt es in zwei Arten:

1. **Geplant** (`kind = planned`): Dinge, die nur einer mitbringen muss (Adapter, Sonnencreme, Zelt). Wird beim Anlegen direkt jemandem zugewiesen oder bleibt offen.
2. **Anfrage in die Gruppe** (`kind = request`), siehe nächster Abschnitt.

#### Anfrage in die Gruppe („Kann jemand … mitnehmen?“)

Ein Mitglied merkt beim Planen, dass ihm etwas fehlt, und fragt die Gruppe.

Ablauf:

1. Aus der eigenen Packliste oder über einen Button „In die Gruppe fragen“ wird eine Anfrage erstellt: Gegenstand (Freitext oder aus dem Katalog), optionale Notiz („USB-C reicht“).
2. Die Anfrage erscheint bei allen anderen Mitgliedern in einem Bereich **„Offene Anfragen“** oben in ihrer Packliste, mit Name der fragenden Person.
3. Ein Mitglied tippt **„Annehmen“**:
   - Status wird `claimed`, `assigned_user_id` ist diese Person.
   - In **deren** Packliste entsteht automatisch ein Eintrag mit `source = group_item` und Hinweis „für Anna“.
   - Die fragende Person sieht „Bringt Max mit“; in ihrer eigenen Liste wird der Gegenstand als erledigt angezeigt bzw. entfernt.
4. **Nur eine Person kann annehmen.** Gleichzeitiges Annehmen muss sauber abgefangen werden (Datenbank-Lock bzw. bedingtes Update auf `status = open`); der Zweite bekommt „Wurde schon übernommen“.
5. Die annehmende Person kann wieder **abgeben** → Status zurück auf `open`, Eintrag aus ihrer Liste entfernt, fragende Person wird informiert.
6. Die fragende Person kann die Anfrage **zurückziehen** (`withdrawn`), solange sie offen ist; ist sie schon angenommen, wird die annehmende Person informiert und ihr Eintrag entfernt.

Benachrichtigung: in der App (Badge „1 neue Anfrage“). E-Mail-Benachrichtigung optional pro Person einstellbar, standardmäßig aus.

#### Packlisten-Freigabe („Für jemanden mitpacken“) – umgesetzt

Statt eines eigenen Pärchenmodus: In einer gemeinsamen Reise kann jede Person einzelnen Mitreisenden ihre Packliste freigeben (`packlist_grants`). Die freigegebene Person sieht die Liste lesend und darf abhaken; Einträge anlegen oder löschen bleibt beim Besitzer. Die Freigabe gilt nur in eine Richtung und lässt sich jederzeit zurücknehmen. Beim Abhaken wird gespeichert, wer es war („gepackt von Max“).

### 5.6 Meilenstein 6: Rückblick – umgesetzt

- Nach Reiseende pro Eintrag markieren: „nicht gebraucht“ / „hat gefehlt“; für Outfits „nicht getragen“.
- „Hat gefehlt“ lässt sich per Klick in eine Vorlage übernehmen.
- Kleine Auswertung pro Reise.
- Umgesetzt zusätzlich: „nicht gebraucht“ lässt sich per Klick aus Vorlagen streichen (wirkt nur auf kommende Reisen); bei Kleidung heißt die Markierung „nicht getragen“; „Was hat gefehlt?“ nimmt auch Dinge auf, die gar nicht auf der Liste standen.

### Export der Packliste – umgesetzt

Button „Exportieren“ in der Fortschrittskarte (`PacklistExport`, `PacklistExportController`); Optionen „nur offene“ und „nach Behältnis“, auch für freigegebene Listen (`?liste=`).

- **Drucken / PDF:** eigene Druckansicht `/reisen/{id}/drucken` (A4, zweispaltig, Kästchen); PDF über „Als PDF sichern“ im Druckdialog – bewusst ohne PDF-Bibliothek.
- **Notiz-Apps:** `/reisen/{id}/export` liefert Text mit ☐/☑ (Teilen-Menü bzw. Kopieren → Apple Notizen, Google Notizen) oder mit `format=md` Markdown mit `- [ ]` (Obsidian, Bear, Notion); `download=1` als Datei.

### 5.7 Später

- Passkeys als zusätzlicher Login für Freunde.
- **Haushalt:** Zwei oder mehr Konten dauerhaft verknüpfen, die Katalog und Vorlagen teilen (eine Reiseapotheke, ein Zelt). Neue gemeinsame Reisen setzen die Packlisten-Freigabe innerhalb des Haushalts automatisch. Katalog und Vorlagen deshalb schon in Meilenstein 2 so bauen, dass der Besitz später auch an einem Haushalt hängen kann (Besitzprüfung zentral in den Policies, nicht verstreut über `owner_id = user`).

---

## 6. Design

Die Entwürfe aus der Konzeptphase sind die gestalterische Basis. Mobile-first, Referenzbreite 390 px; auf dem Desktop zentrierte Spalte bzw. später Zweispalter.

### Design-Tokens

```css
:root {
  --bg:          #F3F5F4;  /* Seitenhintergrund */
  --surface:     #FFFFFF;  /* Karten, Header */
  --ink:         #17211E;  /* Text, aktive Chips, dunkle Tageskacheln */
  --muted:       #5A6662;  /* Sekundärtext, Labels */
  --line:        #DDE3E0;  /* Kartenrahmen */
  --line-soft:   #EDF1EF;  /* Trennlinien in Listen */
  --accent:      #0E6B5E;  /* Primär: Checkboxen, Fortschritt, Buttons, Punkte */
  --accent-soft: #E1EFEC;  /* Hinweis-Badges, Waschtag */
  --accent-ink:  #0A5248;  /* Text auf accent-soft */
  --warn:        #C2571A;  /* Markierung „nur 1× genutzt“ (Punkte) */
  --warn-soft:   #FBEDE4;  /* Hintergrund Warnzeilen/Chips */
  --warn-ink:    #7A3410;  /* Text auf warn-soft */
  --radius-card: 18px;
  --radius-chip: 10px;
}
```

- Schrift: **Manrope** (400–800). Titel 30 px/800, Kartentitel 16 px/700, Listentext 15 px/600, Labels 11–12 px Großbuchstaben mit Laufweite.
- Touch-Ziele mindestens 44 px, Listenzeilen mindestens 52 px.
- Kontrast mindestens 4,5:1 auch für kleine graue Labels.
- Keine Emojis als Icons; schlichte Linien-Icons (z. B. Lucide).
- Dark Mode später, Tokens so anlegen, dass er ergänzt werden kann.

### Screens aus den Entwürfen

**Kopfbereich (alle Reise-Screens):** Zurück-Button, Reisename groß, darunter „12.–16. Mai · 5 Tage · Städtetrip“, Segmentschalter **Packliste | Outfits | Rückblick**.

**Packliste:**
- Fortschrittsbalken mit „x von y gepackt“ und Prozent.
- Zeile „Zusammengesetzt aus“ mit Modul-Chips (dunkel) und „+ Modul“ (gestrichelt), rechts Umschalter „Nur offene“.
- Karten je Gruppe; „Am Abfahrtstag“ zuerst mit Badge „Zuletzt“, Kleidung mit Badge „Aus Outfits“ (verlinkt zum Planer).
- Zeile: Checkbox (abgehakt = grün gefüllt, Text durchgestrichen), Name, Zusatzinfo, rechts Mengen-Pill „3×“.
- Schwebender Button „+ Gegenstand“ unten rechts.
- Neu ab Meilenstein 5: Bereich „Offene Anfragen“ oben und Karte „Gemeinsam“ mit Gruppen-Gegenständen; Button „In die Gruppe fragen“.

**Outfits:**
- Wochenübersicht als Tabelle in einer Karte: Kopfzeile mit Tageskacheln (Wochentag, Datum, Temperatur; gewählter Tag dunkel, Waschtag grün hinterlegt), Zeilen nach Kategorie gruppiert, Punkte, Summe rechts, Warnzeilen orange.
- Darunter Karte des gewählten Tages: Titel „Di, 13. Mai · Alfama & Belém“, Wetter, Slots mit Teile-Chips und „+“.
- Primärbutton „In die Packliste übernehmen“.

---

## 7. Sicherheit und Datenschutz

- Policies für jedes Modell, Feature-Tests für jede Rechte-Regel (insbesondere: Freund A sieht nichts von Freund B außerhalb gemeinsamer Reisen).
- Tokens (Einladung, Magic Link) nur gehasht speichern.
- CSRF, sichere Cookies, HTTPS erzwingen.
- Minimal speichern: Name, E-Mail, Reisedaten. Konto löschen entfernt bzw. anonymisiert alle personenbezogenen Daten; Einträge in gemeinsamen Reisen werden „Ehemaliges Mitglied“.
- Kein Tracking, keine externen Skripte außer selbst gehosteten Fonts.

---

## 8. Offline und Synchronisation

- Packliste einer Reise wird beim Öffnen im Service Worker zwischengespeichert.
- Abhaken offline möglich; Änderungen werden in einer lokalen Warteschlange gesammelt und beim nächsten Netz übertragen.
- Konfliktregel: letzter Stand pro Eintrag gewinnt (Zeitstempel). Gruppen-Anfragen annehmen nur online (wegen Exklusivität).

**Umsetzung (Stand Meilenstein 3):**

- `public/sw.js`: Seiten (`/`, `/reisen/{id}`) erst Netz, sonst Cache, sonst `offline.html`; `/build/*` Cache zuerst; Titel- und Benutzerbilder stale-while-revalidate. Beim Ändern des Workers `VERSION` hochzählen.
- Laufende und kommende Reisen werden von der Startseite aus vorgeladen, auch wenn man sie noch nicht geöffnet hat.
- `resources/js/offline.js`: Warteschlange in `localStorage` (pro Eintrag nur die letzte Änderung, mit Gerätezeit). Übertragen sofort, bei `online` und alle 15 s, solange etwas aussteht. Bei 419 wird über `/csrf-token` ein frischer Token geholt.
- Server: `packed_changed_at` am Eintrag; ältere Änderungen werden verworfen, Zeitpunkte in der Zukunft auf „jetzt“ gekappt.
- Jede App-Antwort trägt `X-Packen: 1`. Antworten ohne diese Kennung (Router, Proxy, WLAN-Vorschaltseite) gelten als „kein Netz“ – sonst würde z. B. eine fremde 404 eine Änderung verwerfen.
- Offline sind nur Abhaken und Lesen möglich; Formulare werden abgefangen und mit Hinweis blockiert.
- Abmelden bzw. jede Gästeseite löscht die Caches mit persönlichen Daten.

---

## 9. Anbindung an „unterwegs“

„unterwegs“ ist mein separater Planer für sportliche Aktivitäten (Strecke, Unterkünfte, Kosten, Livetracking). Er soll beim Anlegen einer Aktivität automatisch eine Reise in dieser App erzeugen.

**Umgesetzt** (`routes/api.php`, `Api\TripController`):

Gegenstück in „unterwegs“: `src/Packen.php` (Zugang je Benutzer unter „Einstellungen“, Verbinden je Tour unter „Listen“, Abgleich nach jeder Änderung an Tour, Etappen und Tourbuch; Kennung `unterwegs-<id>`). Beschrieben in der README von „unterwegs“ unter „Packliste in Packen“.

- **Zugang:** Im Profil unter „Zugänge für andere Apps“ einen Token anlegen (`packen_…`, nur einmal sichtbar, gespeichert als SHA-256-Hash in `api_tokens`). Jede Anfrage mit `Authorization: Bearer <token>` und `Accept: application/json`. Widerrufen im Profil; gesperrte Konten verlieren ihre Tokens. Limit 60 Anfragen pro Minute.
- `GET /api/me` – `{data: {name}}` der Token-Person, zum Prüfen eines eingetragenen Tokens. Der Token wird vor dem Laden einer Reise geprüft (Middleware-Priorität), ohne gültigen Token gibt es also immer 401.
- `POST /api/trips` – legt eine private Reise der Token-Person an (201). Felder: `name`, `start_date`, `end_date` (Pflicht, `YYYY-MM-DD`), `type` (Standard `other`), `accommodation`, `transports[]`, `location`, `external_id` (Kennung in „unterwegs“), `external_url` (Link zurück, http/https), `stages[]` mit `{date, location}`. Gibt es die `external_id` bei dieser Person schon, wird die Reise aktualisiert (200) – wiederholtes Senden verdoppelt nichts.
- `PATCH /api/trips/{id}` – nur mitgeschickte Felder; `stages` ersetzt alle Etappen. Wird der Zeitraum ohne `stages` verkürzt, fallen Etappen außerhalb weg. Nur der Owner darf ändern.
- `DELETE /api/trips/{id}` – löscht die Reise samt Packlisten, Outfits und Etappen (204). Nur der Owner; danach 404. Gemeinsame Reisen mit Mitreisenden lehnt die API mit 409 ab (`message`, `url`) – die lassen sich nur in Packen löschen.
- `GET /api/trips/{id}` – Reise wie in den Antworten oben: `id, external_id, name, start_date, end_date, days, nights, type, mode, accommodation, transports, location, stages, url`.
- `GET /api/trips/{id}/summary` – Packstand der eigenen Liste: `packed, total, progress` („18/42“), `weight_g` (ohne Anreise-Kleidung), `worn_g`, `weight_complete` (false, wenn Einträgen das Gewicht fehlt), `url`.
- Erlaubte Werte: `type` citytrip, beach, hiking, bikepacking, running_event, winter, other · `accommodation` hotel, apartment, hostel, camping, hut, friends, other · `transports` plane, train, car, rental_car, bus, ferry, bike, on_foot, other.
- Antworten stecken in `data`; Fehler als JSON (401 ohne gültigen Token, 403 fremde Reise, 422 mit `errors` je Feld).
- **Etappen** (`trip_stages`, Ort je Tag) stehen im Outfit-Planer am Tag; das Wetter kommt pro Tag vom Etappenort, sonst vom Reiseziel. Bearbeitet werden sie **bewusst nur in „unterwegs“** – Packen bekommt keine eigene Etappen-Bearbeitung. Unabhängig davon kann jede Person im Outfit-Planer pro Tag einen **eigenen Ort** setzen (`outfit_days.location`, z. B. Tagesausflug); er gilt nur für ihre Outfits und ihr Wetter und überschreibt Etappe und Reiseziel. Ist an einem Tag Regen angesagt (ab 50 %) und steht weder auf der Liste noch im Outfit Regenschutz (Häkchen „schützt vor Regen“ am Gegenstand; freie Einträge per Namen, `App\Support\RainGear`), zeigen Packliste und Outfit-Planer einen Hinweis mit Ein-Klick-Hinzufügen. Mit `external_url` (Link auf die Aktivität) zeigt Packen im Outfit-Planer, in den Reisedetails und im Bearbeiten-Formular einen Link dorthin.

---

## 10. Offene Punkte

- Welches Protokoll bietet BearID (OIDC? eigenes Token?) und welche Daten liefert es?
- ~~Name der App und der Subdomain~~ → Packen, packen.allpixx.de
- SMTP-Zugang für den Mailversand.
- ~~Wetterdienst~~ → Open-Meteo (frei für nicht-kommerzielle Nutzung, ohne personenbezogene Daten)

---

## 11. Arbeitsweise

- Mit **Meilenstein 1** beginnen, jeden Meilenstein lauffähig und getestet abschließen, bevor der nächste startet.
- Vor größeren Architekturentscheidungen oder neuen Abhängigkeiten kurz nachfragen.
- Migrationen statt manueller Datenbankänderungen; Seeder mit Beispieldaten (eine Lissabon-Reise wie in den Entwürfen) für die Entwicklung.
- Deployment auf Webhosting ohne Docker berücksichtigen: keine Dienste voraussetzen, die dort nicht laufen (Queues über Datenbank + Cronjob, kein Redis voraussetzen).
