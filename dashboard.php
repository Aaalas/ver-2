<?php
require_once 'db.php';
$current_page = 'dashboard';

// 1. Total listings gathered
$total_listings = (int)$pdo->query("SELECT COUNT(*) as total FROM vehicle_listings")->fetch()['total'];

// 2. Distinct brands covered (real count, not hardcoded)
$brands_covered = (int)$pdo->query("SELECT COUNT(DISTINCT brand) as c FROM vehicle_listings")->fetch()['c'];
// Fall back to the pricing catalog if no listings have been registered yet
if ($brands_covered === 0) {
    $brands_covered = (int)$pdo->query("SELECT COUNT(DISTINCT brand) as c FROM reference_weights")->fetch()['c'];
}

// 3. Averages grouped by vehicle type
$stmtAvg = $pdo->query("
    SELECT vehicle_type, AVG(asking_price) as avg_price, AVG(mileage) as avg_mileage
    FROM vehicle_listings
    WHERE asking_price IS NOT NULL
    GROUP BY vehicle_type
");
$avg_price   = ['Car' => 0, 'Motorcycle' => 0, 'Bicycle' => 0];
$avg_mileage = ['Car' => 0, 'Motorcycle' => 0, 'Bicycle' => 0];
foreach ($stmtAvg->fetchAll() as $row) {
    $type = $row['vehicle_type'];
    if (isset($avg_price[$type])) {
        $avg_price[$type]   = $row['avg_price'];
        $avg_mileage[$type] = $row['avg_mileage'];
    }
}

// 4. Real model performance, computed from stored predictions vs. actual asking prices
$metrics = compute_model_metrics($pdo);

include 'header.php';
?>

<div class="view">
  <div class="hero">
    <div class="hero-eyebrow">● Data-driven pricing, updated live</div>
    <h1>Find the right price.<br>Sell <em>smart.</em> Buy <em>better.</em></h1>
    <p class="hero-copy">Get an instant, data-backed estimate for any car, motorcycle, or bicycle — built from real listings, not guesswork.</p>
    <div class="hero-stats">
      <div class="hero-stat"><b><?php echo number_format($total_listings); ?></b><span>Listings gathered</span></div>
      <div class="hero-stat">
        <b><?php echo $metrics['r2'] !== null ? number_format($metrics['r2'] * 100, 1) . '%' : '—'; ?></b>
        <span>Prediction accuracy</span>
      </div>
      <div class="hero-stat">
        <b><?php echo $metrics['mae'] !== null ? '±₱' . number_format($metrics['mae']) : '—'; ?></b>
        <span>Avg. prediction error</span>
      </div>
      <div class="hero-stat"><b><?php echo number_format($brands_covered); ?></b><span>Brands covered</span></div>
    </div>
  </div>

  <div class="grid-4">
    <!-- Total Data Gathered -->
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-icon" style="background:var(--indigo-soft)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--indigo)" stroke-width="2"><rect x="3" y="4" width="18" height="4" rx="1"/><rect x="3" y="10" width="18" height="4" rx="1"/><rect x="3" y="16" width="18" height="4" rx="1"/></svg></div>
        <span class="delta" style="color:var(--teal)">Live</span>
      </div>
      <div class="val"><?php echo number_format($total_listings); ?></div>
      <div class="lbl">Total listings gathered</div>
    </div>

    <!-- Average Price Breakdown -->
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-icon" style="background:var(--teal-soft)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
        <span class="lbl" style="font-weight:600; color:var(--text); margin:0;">Avg Listing Price</span>
      </div>
      <div style="font-family:var(--font-mono); font-size:12.5px; margin-top:12px; display:flex; flex-direction:column; gap:6px;">
        <div style="display:flex; justify-content:space-between;"><span>Car</span> <b style="color:var(--text)">₱<?php echo number_format($avg_price['Car']); ?></b></div>
        <div style="display:flex; justify-content:space-between;"><span>Motor</span> <b style="color:var(--text)">₱<?php echo number_format($avg_price['Motorcycle']); ?></b></div>
        <div style="display:flex; justify-content:space-between;"><span>Bike</span> <b style="color:var(--text)">₱<?php echo number_format($avg_price['Bicycle']); ?></b></div>
      </div>
    </div>

    <!-- Average Mileage Breakdown -->
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-icon" style="background:#FFF4E0"><svg viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
        <span class="lbl" style="font-weight:600; color:var(--text); margin:0;">Average Mileage</span>
      </div>
      <div style="font-family:var(--font-mono); font-size:12.5px; margin-top:12px; display:flex; flex-direction:column; gap:6px;">
        <div style="display:flex; justify-content:space-between;"><span>Car</span> <b style="color:var(--text)"><?php echo number_format($avg_mileage['Car']); ?> km</b></div>
        <div style="display:flex; justify-content:space-between;"><span>Motor</span> <b style="color:var(--text)"><?php echo number_format($avg_mileage['Motorcycle']); ?> km</b></div>
        <div style="display:flex; justify-content:space-between;"><span>Bike</span> <b style="color:var(--text)"><?php echo number_format($avg_mileage['Bicycle']); ?> km</b></div>
      </div>
    </div>

    <!-- Model Status -->
    <div class="kpi">
      <div class="kpi-top">
        <div class="kpi-icon" style="background:var(--coral-soft)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--coral)" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/></svg></div>
      </div>
      <div class="val"><?php echo $metrics['r2'] !== null ? number_format($metrics['r2'] * 100, 1) . '%' : 'Building…'; ?></div>
      <div class="lbl">Prediction accuracy <span style="color:var(--text-faint)">(R² score)</span></div>
      <?php if ($metrics['n'] < 2): ?>
        <div class="lbl" style="margin-top:4px; color:var(--text-faint);">Accuracy will appear once enough listings with asking prices are on file.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
