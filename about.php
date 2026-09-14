<?php
require_once 'db.php';
$current_page = 'about';
include 'header.php';
?>

<div class="view">
  <div class="panel">
    <div class="panel-head">
      <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="var(--indigo)" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><circle cx="12" cy="8.2" r="0.6" fill="currentColor"/></svg></div>
      <h3>About CarPrice Predictor</h3>
    </div>
    <div class="panel-sub" style="margin-left:0; max-width:680px; line-height:1.7;">
      CarPrice Predictor helps buyers and sellers of used cars, motorcycles, and bicycles get a fair,
      data-backed price estimate in seconds. Instead of guessing, the system looks at the vehicle's
      brand, age, mileage, and condition, and compares it against real listings already on file.
    </div>
  </div>

  <div class="grid-4" style="grid-template-columns: repeat(3, 1fr); margin-top:20px;">
    <div class="kpi">
      <div class="lbl" style="font-weight:600; color:var(--text); margin-bottom:8px;">How it works</div>
      <div class="lbl">Every prediction is built from a starting price for that exact brand and model, then
      adjusted for the vehicle's age, mileage, and condition history.</div>
    </div>
    <div class="kpi">
      <div class="lbl" style="font-weight:600; color:var(--text); margin-bottom:8px;">Always improving</div>
      <div class="lbl">Every vehicle priced through the system is saved to the dataset, so accuracy tracked
      on the Dashboard improves as more listings come in.</div>
    </div>
    <div class="kpi">
      <div class="lbl" style="font-weight:600; color:var(--text); margin-bottom:8px;">Just an estimate</div>
      <div class="lbl">Final selling prices can still vary with real-world condition, negotiation, and market
      demand — use the estimate as a starting point.</div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
