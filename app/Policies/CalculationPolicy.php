<?php

namespace App\Policies;

/**
 * Baukalkulationen tragen Preise und Angebotssummen — wie alle
 * Finanzdaten nur für admin und büro.
 */
class CalculationPolicy extends FinancialPolicy {}
