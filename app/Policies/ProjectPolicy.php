<?php

namespace App\Policies;

/**
 * Projekte sieht jedes Firmenmitglied — die Baustelle braucht Termine,
 * Pläne und Fotos. Beträge im Projekt filtert der Controller über das
 * view-financials-Gate heraus.
 */
class ProjectPolicy extends MasterDataPolicy {}
