<?php

namespace App\Policies;

/**
 * Überstunden-Auszahlungen sind Lohndaten: nur admin und büro.
 */
class OvertimePayoutPolicy extends FinancialPolicy {}
