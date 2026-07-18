<?php

namespace App\Support\InvoiceScan;

use App\Models\Customer;
use App\Models\Supplier;

/**
 * Welche Belegart die Scan-Leiter gerade liest — davon hängt ab, wer
 * der gesuchte Geschäftspartner ist: bei Eingangsrechnungen der
 * Aussteller (Lieferant), bei eigenen Ausgangsrechnungen und Angeboten
 * der Empfänger (Kunde).
 */
enum ScanDocumentKind: string
{
    case IncomingInvoice = 'incoming_invoice';
    case OutgoingInvoice = 'outgoing_invoice';
    case Offer = 'offer';

    public function partnerIsSeller(): bool
    {
        return $this === self::IncomingInvoice;
    }

    /**
     * @return class-string<Supplier|Customer>
     */
    public function partnerModel(): string
    {
        return $this->partnerIsSeller() ? Supplier::class : Customer::class;
    }

    public function partnerLabel(): string
    {
        return $this->partnerIsSeller() ? 'Lieferant' : 'Kunde';
    }
}
