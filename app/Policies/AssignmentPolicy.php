<?php

namespace App\Policies;

/**
 * Einteilung: sehen darf jedes Firmenmitglied (die Baustelle soll
 * wissen, wer wo hinfährt), planen dürfen admin und büro.
 */
class AssignmentPolicy extends MasterDataPolicy {}
