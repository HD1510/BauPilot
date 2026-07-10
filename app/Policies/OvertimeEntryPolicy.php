<?php

namespace App\Policies;

/**
 * Überstunden sind Lohndaten: nur admin und büro (Finanz-Gate).
 */
class OvertimeEntryPolicy extends FinancialPolicy {}
