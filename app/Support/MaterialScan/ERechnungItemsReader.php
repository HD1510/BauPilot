<?php

namespace App\Support\MaterialScan;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use Throwable;

/**
 * Stufe 1 des Material-Scans: E-Rechnungen (ZUGFeRD/Factur-X) tragen
 * ihre Positionen als XML — Bezeichnung, Artikelnummer, Einheit und
 * Netto-Einzelpreis sind exakt, ganz ohne Heuristik.
 */
class ERechnungItemsReader
{
    /** UN/ECE-Einheitencodes → lesbare Einheit. */
    private const UNIT_CODES = [
        'C62' => 'Stk',
        'H87' => 'Stk',
        'MTR' => 'm',
        'MTK' => 'm²',
        'MTQ' => 'm³',
        'KGM' => 'kg',
        'TNE' => 't',
        'LTR' => 'l',
        'HUR' => 'h',
        'XPK' => 'Pkg',
        'XBG' => 'Sack',
        'XRO' => 'Rolle',
        'XPX' => 'Pal',
        'XCT' => 'Karton',
        'PR' => 'Paar',
        'LS' => 'psch',
    ];

    /**
     * @return list<ScannedMaterialItem>|null null, wenn kein E-Rechnungs-XML eingebettet ist
     */
    public function readItems(string $pdfContent): ?array
    {
        try {
            $reader = ZugferdDocumentPdfReader::readAndGuessFromContent($pdfContent);
        } catch (Throwable) {
            return null;
        }

        try {
            $items = [];

            if ($reader->firstDocumentPosition()) {
                do {
                    $name = $description = $sellerAssignedId = $buyerAssignedId = $globalIdType = $globalId = null;
                    $reader->getDocumentPositionProductDetails($name, $description, $sellerAssignedId, $buyerAssignedId, $globalIdType, $globalId);

                    $price = $basisQuantity = null;
                    $basisUnitCode = null;
                    $reader->getDocumentPositionNetPrice($price, $basisQuantity, $basisUnitCode);

                    $billedQuantity = $chargeFreeQuantity = $packageQuantity = null;
                    $billedUnitCode = $chargeFreeUnitCode = $packageUnitCode = null;
                    $reader->getDocumentPositionQuantity($billedQuantity, $billedUnitCode, $chargeFreeQuantity, $chargeFreeUnitCode, $packageQuantity, $packageUnitCode);

                    if ($name !== null && trim($name) !== '') {
                        $items[] = new ScannedMaterialItem(
                            name: trim($name),
                            articleNo: $sellerAssignedId !== null && trim($sellerAssignedId) !== '' ? trim($sellerAssignedId) : null,
                            unit: $this->unitFrom($billedUnitCode ?? $basisUnitCode),
                            priceNet: $price !== null && $price > 0 ? $price : null,
                        );
                    }
                } while ($reader->nextDocumentPosition());
            }

            return $items;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function unitFrom(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return self::UNIT_CODES[strtoupper(trim($code))] ?? null;
    }
}
