<?php

namespace App\Support\InvoiceScan;

use App\Support\Duplicates\NameNormalizer;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Deterministische Auslese von Belegtext (Stufe 2 der Scan-Leiter):
 * feste Muster für UID, IBAN, Nummer, Datum, Beträge, Zahlungsziel,
 * Skonto und Reverse Charge — ganz ohne KI und ohne externe Dienste.
 * Die Belegart bestimmt den gesuchten Partner: bei Eingangsrechnungen
 * der Aussteller, bei eigenen Belegen der Empfänger. Was kein Muster
 * trifft, bleibt null; bestätigt wird ohnehin von Hand.
 */
class TextInvoiceParser
{
    public static function parse(
        string $text,
        ScanDocumentKind $kind,
        ?string $knownPartnerName = null,
        ?string $ownVatId = null,
        ?string $ownCompanyName = null,
    ): ScannedInvoice {
        $docDate = self::docDate($text, $kind);
        $skonto = self::skonto($text);

        // Die Angebots-/Auftragssumme ist nur bei Angeboten der gesuchte
        // Betrag — auf Rechnungen wäre sie die (viel größere) Gesamtsumme
        // des dahinterliegenden Auftrags.
        $netLabels = $kind === ScanDocumentKind::Offer
            ? 'Netto(?:betrag|summe)?|Zwischensumme|(?:Angebots|Auftrags)summe(?:\s+netto)?'
            : 'Netto(?:betrag|summe)?|Zwischensumme';

        return new ScannedInvoice(
            partnerName: $knownPartnerName ?? self::partnerNameHeuristic($text, $kind, $ownCompanyName),
            partnerUid: self::vatId($text, $ownVatId),
            // Auf eigenen Belegen gehören IBAN, E-Mail und Telefon dem
            // Absender (uns) — dort nicht raten.
            partnerIban: $kind->partnerIsSeller() ? self::iban($text) : null,
            partnerEmail: $kind->partnerIsSeller() ? self::email($text) : null,
            partnerPhone: $kind->partnerIsSeller() ? self::phone($text) : null,
            paymentTargetDays: self::paymentTargetDays($text, $docDate),
            skontoPercent: $skonto['percent'],
            skontoDays: $skonto['days'],
            docNumber: self::docNumber($text, $kind),
            docDate: $docDate,
            net: self::amount($text, $netLabels),
            vatRate: self::vatRate($text),
            gross: self::amount($text, 'Gesamtbetrag|Rechnungsbetrag|Brutto(?:betrag)?|Endbetrag|Gesamt|zu\s+zahlen(?:der\s+Betrag)?'),
            reverseCharge: self::reverseCharge($text),
        );
    }

    /**
     * Skonto-Konditionen aus freiem Text — auch für die Beschreibung der
     * Zahlungsbedingungen einer E-Rechnung wiederverwendbar.
     *
     * @return array{percent: float|null, days: int|null}
     */
    public static function skonto(string $text): array
    {
        $patterns = [
            // „3 % Skonto bei Zahlung innerhalb von 14 Tagen"
            '/(\d+(?:[.,]\d+)?)\s*%\s*Skonto[^.\n]{0,60}?(\d{1,3})\s*Tage/iu',
            // „Skonto 3 % binnen 14 Tagen"
            '/Skonto[^.\n]{0,30}?(\d+(?:[.,]\d+)?)\s*%[^.\n]{0,60}?(\d{1,3})\s*Tage/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return [
                    'percent' => (float) str_replace(',', '.', $m[1]),
                    'days' => (int) $m[2],
                ];
            }
        }

        return ['percent' => null, 'days' => null];
    }

    /**
     * Zahlungsziel in Tagen — direkt genannt oder aus dem Fälligkeitsdatum
     * gerechnet.
     */
    public static function paymentTargetDays(string $text, ?string $invoiceDate): ?int
    {
        $patterns = [
            '/zahlbar\s+(?:innerhalb|binnen)\s+(?:von\s+)?(\d{1,3})\s*Tagen/iu',
            '/Zahlungsziel\s*:?\s*(\d{1,3})\s*Tage/iu',
            '/(?:innerhalb|binnen)\s+(?:von\s+)?(\d{1,3})\s*Tagen?\s+(?:netto|ohne\s+Abzug)/iu',
            '/(\d{1,3})\s*Tage\s+netto/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return (int) $m[1];
            }
        }

        if ($invoiceDate !== null && preg_match('/f[äa]llig\s+(?:am|bis)\s*:?\s*(\d{1,2}\.\d{1,2}\.\d{2,4}|\d{4}-\d{2}-\d{2})/iu', $text, $m) === 1) {
            $due = self::toIsoDate($m[1]);

            if ($due !== null) {
                $days = CarbonImmutable::parse($invoiceDate)->diffInDays(CarbonImmutable::parse($due), false);

                return $days >= 0 && $days <= 365 ? (int) $days : null;
            }
        }

        return null;
    }

    private static function vatId(string $text, ?string $ownVatId): ?string
    {
        if (preg_match_all('/\b(ATU\s?\d{8}|DE\s?\d{9}|CHE[-\s]?\d{3}\.?\d{3}\.?\d{3})\b/iu', $text, $m) < 1) {
            return null;
        }

        $own = $ownVatId !== null ? strtoupper((string) preg_replace('/\s+/', '', $ownVatId)) : null;

        foreach ($m[1] as $candidate) {
            $normalized = strtoupper((string) preg_replace('/\s+/', '', $candidate));

            // Die eigene UID steht auf jedem Beleg — überspringen.
            if ($own === null || $normalized !== $own) {
                return $normalized;
            }
        }

        return null;
    }

    private static function iban(string $text): ?string
    {
        if (preg_match('/\b([A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){2,7}(?:\s?[A-Z0-9]{1,3})?)\b/u', $text, $m) === 1) {
            $iban = (string) preg_replace('/\s+/', '', $m[1]);

            if (strlen($iban) >= 15 && strlen($iban) <= 34) {
                return $iban;
            }
        }

        return null;
    }

    private static function email(string $text): ?string
    {
        return preg_match('/\b([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})\b/u', $text, $m) === 1
            ? strtolower($m[1])
            : null;
    }

    private static function phone(string $text): ?string
    {
        // IBANs vorher ausblenden — deren Zifferngruppen sähen sonst
        // wie eine Telefonnummer aus.
        $cleaned = (string) preg_replace('/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){2,7}(?:\s?[A-Z0-9]{1,3})?\b/u', ' ', $text);

        // Internationale Schreibweise (+43 …) oder österreichische
        // Vorwahl (0…), mindestens 8 Ziffern insgesamt.
        if (preg_match('/(\+\d{1,3}[\d\s\/\-()]{7,20}\d|\b0\d{2,4}[\s\/\-]\d[\d\s\/\-]{5,15}\d)/u', $cleaned, $m) === 1) {
            $phone = trim($m[1]);

            return strlen((string) preg_replace('/\D+/', '', $phone)) >= 8 ? $phone : null;
        }

        return null;
    }

    private static function docNumber(string $text, ScanDocumentKind $kind): ?string
    {
        $patterns = $kind === ScanDocumentKind::Offer
            ? [
                '/Angebots?\s?(?:-\s?)?(?:Nr|Nummer)\.?\s*:?\s*([A-Za-z0-9][A-Za-z0-9\/\-._]{0,29})/iu',
                // Ohne Ziffer keine Nummer — sonst finge „Angebot vom …" das Wort „vom".
                '/Angebot\s+(?:Nr\.?\s*)?((?=[A-Za-z0-9\/\-._]{0,29}\d)[A-Za-z0-9][A-Za-z0-9\/\-._]{1,29})/iu',
            ]
            : [
                '/Rechnungs?\s?(?:-\s?)?(?:Nr|Nummer)\.?\s*:?\s*([A-Za-z0-9][A-Za-z0-9\/\-._]{0,29})/iu',
                '/(?:Beleg|Faktura|Rg)\.?\s?(?:-\s?)?Nr\.?\s*:?\s*([A-Za-z0-9][A-Za-z0-9\/\-._]{0,29})/iu',
            ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return rtrim($m[1], '.');
            }
        }

        return null;
    }

    private static function docDate(string $text, ScanDocumentKind $kind): ?string
    {
        // Je Belegart das eigene Datum — auf Rechnungen darf ein
        // erwähntes „Angebotsdatum" das Rechnungsdatum nicht schlagen.
        $patterns = [
            $kind === ScanDocumentKind::Offer
                ? '/Angebotsdatum\s*:?\s*(\d{1,2}\.\d{1,2}\.\d{2,4}|\d{4}-\d{2}-\d{2})/iu'
                : '/Rechnungsdatum\s*:?\s*(\d{1,2}\.\d{1,2}\.\d{2,4}|\d{4}-\d{2}-\d{2})/iu',
            '/\bDatum\s*:?\s*(\d{1,2}\.\d{1,2}\.\d{2,4}|\d{4}-\d{2}-\d{2})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return self::toIsoDate($m[1]);
            }
        }

        return null;
    }

    /**
     * Betrag hinter einem der Schlagworte, deutsches Zahlenformat
     * (1.234,56) und internationales (1234.56).
     */
    private static function amount(string $text, string $labels): ?float
    {
        $number = '((?:\d{1,3}(?:\.\d{3})+,\d{2})|(?:\d+,\d{2})|(?:\d+\.\d{2}))';

        if (preg_match("/(?:{$labels})[^\\d\\n]{0,25}{$number}/iu", $text, $m) !== 1) {
            return null;
        }

        $raw = $m[1];

        // 1.234,56 → 1234.56; 1234.56 bleibt.
        $value = str_contains($raw, ',')
            ? str_replace(',', '.', str_replace('.', '', $raw))
            : $raw;

        return (float) $value;
    }

    private static function vatRate(string $text): ?float
    {
        if (preg_match('/(\d{1,2}(?:[.,]\d+)?)\s*%\s*(?:USt|MwSt|Umsatzsteuer|Mehrwertsteuer)/iu', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        if (preg_match('/(?:USt|MwSt|Umsatzsteuer|Mehrwertsteuer)\s*\(?\s*(\d{1,2}(?:[.,]\d+)?)\s*%/iu', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private static function reverseCharge(string $text): bool
    {
        return preg_match('/Übergang\s+der\s+Steuerschuld|Reverse[-\s]?Charge|§\s?19\s?(?:Abs|UStG)/iu', $text) === 1;
    }

    // Kurzformen (AG/KG/OG) nur als eigenes Wort — sonst träfe das
    // Muster Wörter wie „BETRAG" oder „WERKZEUG".
    private const LEGAL_FORM_PATTERN = '/^(.{2,60}?(?:GmbH\s?&\s?Co\.?\s?KG|Ges\.?m\.?b\.?H\.?|GmbH|e\.\s?U\.|(?<=\s)(?:AG|KG|OG)))(?=\s|$|[,;:])/u';

    /**
     * Kein bekannter Partner im Text — dann dort suchen, wo er auf
     * echten Belegen steht (nur ein Vorschlag, kein Fakt):
     *
     *  - Eingangsrechnung: Aussteller im Briefkopf ODER in der Fußzeile
     *    (dort stehen meist Firmenname, UID, IBAN — oft mit | · getrennt)
     *  - Eigene Belege (Ausgangsrechnung/Angebot): Empfänger im
     *    Anschriftenfeld (Brieffenster: Name über Straße über PLZ Ort) —
     *    das findet auch Privatkunden ohne Rechtsform
     */
    private static function partnerNameHeuristic(string $text, ScanDocumentKind $kind, ?string $ownCompanyName): ?string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn (string $line): bool => $line !== ''));
        $ownNormalized = $ownCompanyName !== null ? NameNormalizer::normalize($ownCompanyName) : null;
        $isOwn = fn (string $value): bool => $ownNormalized !== null && $ownNormalized !== ''
            && str_contains(NameNormalizer::normalize($value), $ownNormalized);

        if (! $kind->partnerIsSeller()) {
            return self::addressWindowName($lines, $isOwn);
        }

        // Briefkopf zuerst, dann die Fußzeile.
        $regions = [array_slice($lines, 0, 15)];

        if (count($lines) > 15) {
            $regions[] = array_slice($lines, -15);
        }

        foreach ($regions as $region) {
            foreach ($region as $line) {
                if ($isOwn($line)) {
                    continue;
                }

                // Fußzeilen trennen Angaben mit | · • oder breiten Lücken;
                // Beschriftungen wie „Firma:" fallen vor dem Muster weg.
                foreach (preg_split('/\s*[|·•]\s*|\s{3,}/u', $line) ?: [] as $segment) {
                    $segment = trim((string) preg_replace('/^[^:]{0,30}:\s*/u', '', trim($segment)));

                    if ($segment !== '' && strlen($segment) <= 80 && preg_match(self::LEGAL_FORM_PATTERN, $segment, $m) === 1) {
                        return trim($m[1]);
                    }
                }
            }
        }

        // Keine Rechtsform gefunden (Einzelunternehmer wie „Herbert
        // Dienbauer — HD5"): die klassische Absenderzeile lesen —
        // „Name · Straße · PLZ Ort [· E-Mail · Telefon]".
        foreach ($regions as $region) {
            foreach ($region as $line) {
                if ($isOwn($line)) {
                    continue;
                }

                $name = self::senderLineName($line);

                if ($name !== null) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Erste Angabe einer Absenderzeile, wenn eine spätere Angabe nach
     * Adresse aussieht (Straße mit Hausnummer oder PLZ Ort) — nur dann
     * ist die Zeile wirklich eine Absenderzeile.
     */
    private static function senderLineName(string $line): ?string
    {
        $segments = array_values(array_filter(array_map('trim', preg_split('/\s*[|·•]\s*|\s{3,}/u', $line) ?: [])));

        if (count($segments) < 2) {
            return null;
        }

        $rest = implode(' · ', array_slice($segments, 1));

        if (preg_match('/\b\d{4,5}\s+\p{L}|(?:straße|strasse|gasse|weg|platz|allee)\s*\d/iu', $rest) !== 1) {
            return null;
        }

        $name = $segments[0];

        // Der Name steht vorne — E-Mail, Web, Telefon oder Adressen
        // an erster Stelle disqualifizieren die Zeile.
        if (strlen($name) > 60 || preg_match('/@|www\.|https?:|\+?\d{4,}|\b\d{4,5}\s/u', $name) === 1) {
            return null;
        }

        return $name;
    }

    /**
     * Empfänger aus dem Anschriftenfeld (DIN-Brieffenster): über der
     * PLZ-Ort-Zeile steht die Straße, darüber der Name. So findet die
     * Heuristik auch Privatkunden — eine Rechtsform braucht es nicht.
     *
     * @param  array<int, string>  $lines
     * @param  callable(string): bool  $isOwn
     */
    private static function addressWindowName(array $lines, callable $isOwn): ?string
    {
        foreach (array_slice($lines, 0, 20) as $i => $line) {
            // PLZ-Ort-Zeile (AT 4-stellig, DE 5-stellig); Ortsnamen
            // klein zu schreiben kommt vor — nur ein Buchstabe zählt.
            if (preg_match('/^(?:A-|D-)?\d{4,5}\s+\p{L}/u', $line) !== 1) {
                continue;
            }

            // Üblich: Name/Straße/PLZ (Versatz 2); mit z.H.-Zeile Versatz 3;
            // knapp: Name/PLZ (Versatz 1).
            foreach ([2, 3, 1] as $offset) {
                $index = $i - $offset;

                if ($index < 0) {
                    continue;
                }

                $candidate = trim((string) preg_replace('/^(?:An:?|z\.\s?H\.:?|Firma)\s+/iu', '', $lines[$index]));

                // Straßen und Absender-Einzeiler scheiden aus: Ziffern
                // gehören in keine Namenszeile.
                if ($candidate === '' || strlen($candidate) > 80 || preg_match('/\d/', $candidate) === 1 || $isOwn($candidate)) {
                    continue;
                }

                return $candidate;
            }
        }

        return null;
    }

    private static function toIsoDate(string $value): ?string
    {
        try {
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $value, $m) === 1) {
                $year = (int) $m[3];
                $year = $year < 100 ? 2000 + $year : $year;

                return CarbonImmutable::create($year, (int) $m[2], (int) $m[1])?->toDateString();
            }

            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
