<?php
/**
 * Shared helper functions used across the CarPrice Predictor system.
 */

/** Shorthand for htmlspecialchars(). */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize free-text-ish values (brand/model/location) into a single
 * consistent form so "toyota", "TOYOTA" and "Toyota" are always stored
 * and matched as the same category.
 */
function normalize_title(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    // Title-case every word, but keep common all-caps acronyms readable (e.g. "NMAX", "CR-V")
    $value = preg_replace('/\s+/', ' ', $value);
    $words = explode(' ', $value);
    foreach ($words as &$w) {
        if (preg_match('/^[A-Z0-9\-]{2,}$/', $w)) {
            // Looks like an acronym/model code (e.g. "CR-V", "NMAX") — leave as-is
            continue;
        }
        $w = strtoupper(substr($w, 0, 1)) . strtolower(substr($w, 1));
    }
    return implode(' ', $words);
}

/**
 * Builds a nested catalog of vehicle_type => brand => model => year range
 * from the reference_weights table (the set of vehicles the pricing model
 * can actually predict). Used to drive the cascading Type -> Brand -> Model
 * -> Year dropdowns, with the year options coming straight from the DB.
 */
function get_catalog(PDO $pdo): array {
    $currentYear = (int)date('Y');
    $hasYearColumns = true;

    try {
        $stmt = $pdo->query("SELECT vehicle_type, brand, model, year_start, year_end FROM reference_weights ORDER BY vehicle_type, brand, model");
    } catch (PDOException $e) {
        // year_start / year_end don't exist yet (add_year_range.sql not run) —
        // fall back gracefully instead of breaking the whole Predict page.
        $hasYearColumns = false;
        $stmt = $pdo->query("SELECT vehicle_type, brand, model FROM reference_weights ORDER BY vehicle_type, brand, model");
    }

    $catalog = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $catalog[$row['vehicle_type']][$row['brand']][$row['model']] = $hasYearColumns
            ? [
                'year_start' => (int)$row['year_start'],
                'year_end'   => $row['year_end'] !== null ? (int)$row['year_end'] : null,
              ]
            : [
                'year_start' => 1990,
                'year_end'   => null,
              ];
    }
    return $catalog;
}

/**
 * Computes real model-performance metrics (R² and mean absolute error)
 * by comparing stored predicted_value against the asking_price of
 * registered listings. Returns null metrics if there isn't enough
 * data yet to compute a meaningful score.
 */
function compute_model_metrics(PDO $pdo): array {
    $stmt = $pdo->query("SELECT asking_price, predicted_value FROM vehicle_listings WHERE asking_price IS NOT NULL AND predicted_value IS NOT NULL AND asking_price > 0");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $n = count($rows);

    if ($n < 2) {
        return ['r2' => null, 'mae' => null, 'n' => $n];
    }

    $mean = array_sum(array_map(fn($r) => (float)$r['asking_price'], $rows)) / $n;
    $ssRes = 0.0;
    $ssTot = 0.0;
    $absErrSum = 0.0;

    foreach ($rows as $r) {
        $actual = (float)$r['asking_price'];
        $pred   = (float)$r['predicted_value'];
        $ssRes += ($actual - $pred) ** 2;
        $ssTot += ($actual - $mean) ** 2;
        $absErrSum += abs($actual - $pred);
    }

    $r2  = $ssTot > 0 ? max(0, 1 - ($ssRes / $ssTot)) : null;
    $mae = $absErrSum / $n;

    return ['r2' => $r2, 'mae' => $mae, 'n' => $n];
}

/** Renders <option> tags, marking the one matching $current as selected. */
function option(string $value, ?string $current, ?string $label = null): string {
    $label = $label ?? $value;
    $sel = ($current !== null && strcasecmp($current, $value) === 0) ? ' selected' : '';
    return '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
}

/**
 * Real-world body-type classification for each catalog model (public
 * knowledge about these vehicles, not invented pricing data). Powers the
 * body-type badge shown on the Dataset table and the Predict page preview.
 * Anything not listed here falls back to a generic "Vehicle" badge.
 */
function get_body_types(): array {
    return [
        // Toyota
        'Toyota|Vios' => 'Sedan', 'Toyota|Fortuner' => 'SUV', 'Toyota|Innova' => 'MPV',
        'Toyota|Hilux' => 'Pickup', 'Toyota|Wigo' => 'Hatchback', 'Toyota|Hiace' => 'Van', 'Toyota|Avanza' => 'MPV',
        // Mitsubishi
        'Mitsubishi|Montero Sport' => 'SUV', 'Mitsubishi|Mirage G4' => 'Sedan', 'Mitsubishi|Xpander' => 'MPV',
        'Mitsubishi|Strada' => 'Pickup', 'Mitsubishi|L300' => 'Van',
        // Honda
        'Honda|City' => 'Sedan', 'Honda|Civic' => 'Sedan', 'Honda|Brio' => 'Hatchback', 'Honda|BR-V' => 'MPV',
        // Ford
        'Ford|Ranger' => 'Pickup', 'Ford|Everest' => 'SUV', 'Ford|Territory' => 'SUV', 'Ford|EcoSport' => 'SUV',
        // Nissan
        'Nissan|Navara' => 'Pickup', 'Nissan|Almera' => 'Sedan', 'Nissan|Terra' => 'SUV', 'Nissan|Urvan' => 'Van',
    ];
}

/** Body type -> [accent color var, soft background var] used for badge styling. */
function get_body_type_styles(): array {
    return [
        'Sedan'     => ['indigo', 'indigo-soft'],
        'SUV'       => ['coral', 'coral-soft'],
        'Pickup'    => ['amber', 'amber-soft'],
        'Van'       => ['teal', 'teal-soft'],
        'Hatchback' => ['violet', 'violet-soft'],
        'MPV'       => ['slate', 'slate-soft'],
    ];
}

function get_body_type(string $brand, string $model): string {
    $types = get_body_types();
    return $types["{$brand}|{$model}"] ?? 'Vehicle';
}

/** The single shared car glyph used everywhere in the app (sidebar brand mark, badges, previews). */
function car_icon_path(): string {
    return 'M3 12l1.5-5A2 2 0 016.4 5.5h11.2A2 2 0 0119.5 7L21 12M3 12v5a1 1 0 001 1h1a1 1 0 001-1v-1h12v1a1 1 0 001 1h1a1 1 0 001-1v-5M3 12h18M6.5 15.5h.01M17.5 15.5h.01';
}

/** Renders a small tinted icon badge + label for a vehicle's body type. */
function body_type_badge(string $brand, string $model): string {
    $type = get_body_type($brand, $model);
    [$color, $soft] = get_body_type_styles()[$type] ?? ['text-faint', 'paper'];
    $path = car_icon_path();
    return '<span class="vbadge" style="background:var(--' . $soft . '); color:var(--' . $color . ')">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="' . $path . '"/></svg>'
        . e($type) . '</span>';
}