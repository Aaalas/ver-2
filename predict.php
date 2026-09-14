<?php
require_once 'db.php';
$current_page = 'predict';

$catalog = get_catalog($pdo); // [vehicle_type => [brand => [model => ['year_start'=>.., 'year_end'=>..]]]]
$current_year = (int)date('Y');

$estimated_price = null;
$breakdown = [];
$errors = [];
$saved_listing = false;

// Dropdown option sets
$TRANSMISSIONS = ['Automatic', 'Manual', 'CVT'];
$FUEL_TYPES    = ['Gasoline', 'Diesel', 'Hybrid', 'Electric'];
$MODIFICATIONS = ['Stock / None', 'Minor Modifications', 'Major Modifications'];
$REGISTRATIONS = ['Updated', 'Expired', 'Unknown'];
$MAINTENANCE   = ['Excellent', 'Good', 'Average', 'Poor'];
$ACCIDENTS     = ['None', 'Minor', 'Major', 'Unknown'];

// Peso effect of each condition/history option
$REGISTRATION_ADJ = ['Updated' => 0, 'Expired' => -8000, 'Unknown' => -3000];
$MAINTENANCE_ADJ  = ['Excellent' => 15000, 'Good' => 5000, 'Average' => 0, 'Poor' => -10000];
$ACCIDENT_ADJ     = ['None' => 0, 'Minor' => -12000, 'Major' => -45000, 'Unknown' => -5000];
$MODIFICATION_ADJ = ['Stock / None' => 0, 'Minor Modifications' => -3000, 'Major Modifications' => -20000];

$f = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['predict'])) {

    $type  = trim($_POST['vehicle_type'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year_raw = trim($_POST['year'] ?? '');
    $mileage_raw = trim($_POST['mileage'] ?? '');
    $asking_price_raw = trim($_POST['asking_price'] ?? '');

    $transmission  = in_array($_POST['transmission'] ?? '', $TRANSMISSIONS, true) ? $_POST['transmission'] : $TRANSMISSIONS[0];
    $fuel_type     = in_array($_POST['fuel_type'] ?? '', $FUEL_TYPES, true) ? $_POST['fuel_type'] : $FUEL_TYPES[0];
    $modifications = in_array($_POST['modifications'] ?? '', $MODIFICATIONS, true) ? $_POST['modifications'] : $MODIFICATIONS[0];
    $registration  = in_array($_POST['registration'] ?? '', $REGISTRATIONS, true) ? $_POST['registration'] : $REGISTRATIONS[0];
    $maintenance   = in_array($_POST['maintenance'] ?? '', $MAINTENANCE, true) ? $_POST['maintenance'] : $MAINTENANCE[2];
    $accidents     = in_array($_POST['accidents'] ?? '', $ACCIDENTS, true) ? $_POST['accidents'] : $ACCIDENTS[0];

    $color = normalize_title($_POST['color'] ?? '');

    // ---- Validation ----
    $modelInfo = null; // will hold ['year_start'=>.., 'year_end'=>..] once brand+model are confirmed valid

    if (!isset($catalog[$type])) {
        $errors['vehicle_type'] = 'Please select a vehicle type.';
    }
    if ($brand === '') {
        $errors['brand'] = 'Please select a brand.';
    } elseif (isset($catalog[$type]) && !isset($catalog[$type][$brand])) {
        $errors['brand'] = 'Please choose a brand from the list.';
    }
    if ($model === '') {
        $errors['model'] = 'Please select a model.';
    } elseif (isset($catalog[$type][$brand]) && !array_key_exists($model, $catalog[$type][$brand])) {
        $errors['model'] = 'Please choose a model from the list.';
    } elseif (isset($catalog[$type][$brand][$model])) {
        $modelInfo = $catalog[$type][$brand][$model];
    }

    // Year is validated against THIS model's real production range, pulled from the database
    if ($year_raw === '' || !ctype_digit($year_raw)) {
        $errors['year'] = 'Please select a year.';
    } elseif ($modelInfo !== null) {
        $yearMin = $modelInfo['year_start'];
        $yearMax = $modelInfo['year_end'] ?? $current_year;
        if ((int)$year_raw < $yearMin || (int)$year_raw > $yearMax) {
            $errors['year'] = "Please select a year between {$yearMin} and {$yearMax} for this model.";
        }
    } elseif ((int)$year_raw < 1900 || (int)$year_raw > $current_year) {
        $errors['year'] = "Please select a valid year (up to {$current_year}).";
    }

    if ($mileage_raw === '' || !ctype_digit($mileage_raw) || (int)$mileage_raw < 0) {
        $errors['mileage'] = 'Please enter a valid mileage (0 or greater).';
    }

    // Asking price is optional, but if provided it must be a sane positive number
    if ($asking_price_raw !== '' && (!is_numeric($asking_price_raw) || (float)$asking_price_raw < 0)) {
        $errors['asking_price'] = 'Asking price must be a positive number.';
    }

    if (empty($errors)) {
        $year = (int)$year_raw;
        $mileage = (int)$mileage_raw;
        $asking_price = $asking_price_raw !== '' ? (float)$asking_price_raw : null;

        $stmt = $pdo->prepare("SELECT * FROM reference_weights WHERE vehicle_type = ? AND LOWER(brand) = LOWER(?) AND LOWER(model) = LOWER(?) LIMIT 1");
        $stmt->execute([$type, $brand, $model]);
        $vehicle_data = $stmt->fetch();

        if ($vehicle_data) {
            $age = max(0, $current_year - $year);
            $base = (float)$vehicle_data['base_price'];

            $year_adj    = $age * (float)$vehicle_data['yearly_depreciation'];
            $mileage_adj = $mileage * (float)$vehicle_data['mileage_penalty'];

            $cond_adj = $REGISTRATION_ADJ[$registration]
                      + $MAINTENANCE_ADJ[$maintenance]
                      + $ACCIDENT_ADJ[$accidents]
                      + $MODIFICATION_ADJ[$modifications];

            $final_price = $base - $year_adj - $mileage_adj + $cond_adj;
            $estimated_price = max(1000, $final_price);

            $range_low  = $estimated_price * 0.947;
            $range_high = $estimated_price * 1.053;

            $breakdown = [
                'base' => $base,
                'year_adj' => $year_adj,
                'mileage_adj' => $mileage_adj,
                'cond_adj' => $cond_adj,
            ];

            // Auto-register the vehicle. asking_price is optional — NULL if the user skipped it.
            $insert = $pdo->prepare("INSERT INTO vehicle_listings
                (vehicle_type, brand, model, year_manufactured, mileage, color, transmission, fuel_type,
                 modifications, registration_status, maintenance_history, accident_history,
                 asking_price, predicted_value)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $insert->execute([
                $type,
                normalize_title($brand),
                normalize_title($model),
                $year,
                $mileage,
                $color !== '' ? $color : null,
                $transmission,
                $fuel_type,
                $modifications,
                $registration,
                $maintenance,
                $accidents,
                $asking_price,
                $estimated_price,
            ]);
            $saved_listing = true;
        } else {
            $errors['model'] = "We don't have pricing data for this exact brand and model yet. Please choose another option from the list.";
        }
    }
}

include 'header.php';
?>

<div class="view">
  <div class="two-col">
    <!-- FORM PANEL -->
    <div class="panel">
      <div class="panel-head">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4"/></svg></div>
        <h3>Vehicle Price Estimator</h3>
      </div>
      <div class="panel-sub">Fill in the vehicle's details below. Fields marked <span class="req">*</span> are required.</div>

      <div class="vehicle-preview hidden" id="vehiclePreview">
        <div class="vp-icon" id="vpIcon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="<?php echo car_icon_path(); ?>"/></svg>
        </div>
        <div>
          <div class="vp-name" id="vpName"></div>
          <div class="vp-meta" id="vpMeta"></div>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16v.01"/></svg>
          <span>Please fix the highlighted field<?php echo count($errors) > 1 ? 's' : ''; ?> below before predicting a price.</span>
        </div>
      <?php elseif ($saved_listing): ?>
        <div class="alert alert-success">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
          <span>This vehicle has been priced and added to the <a href="dataset.php">dataset</a> automatically.</span>
        </div>
      <?php endif; ?>

      <form method="POST" id="predictForm" novalidate>

        <!-- Section: Vehicle Information -->
        <div class="form-section">
          <div class="form-section-title">Vehicle Information</div>
          <div class="form-grid">
            <div class="field"><label>Vehicle Type <span class="req">*</span></label>
              <select name="vehicle_type" id="vehicle_type">
                <option value="">Select vehicle type</option>
                <?php foreach (array_keys($catalog) as $t): ?>
                  <?php echo option($t, $f['vehicle_type'] ?? ''); ?>
                <?php endforeach; ?>
              </select>
              <div class="field-error" data-for="vehicle_type"><?php echo e($errors['vehicle_type'] ?? ''); ?></div>
            </div>

            <div class="field"><label>Brand <span class="req">*</span></label>
              <select name="brand" id="brand" disabled>
                <option value="">Select brand</option>
              </select>
              <div class="field-error" data-for="brand"><?php echo e($errors['brand'] ?? ''); ?></div>
            </div>

            <div class="field"><label>Model <span class="req">*</span></label>
              <select name="model" id="model" disabled>
                <option value="">Select model</option>
              </select>
              <div class="field-error" data-for="model"><?php echo e($errors['model'] ?? ''); ?></div>
            </div>

            <div class="field"><label>Year of Manufacture <span class="req">*</span></label>
              <select name="year" id="year" disabled>
                <option value="">Select year</option>
              </select>
              <div class="field-error" data-for="year"><?php echo e($errors['year'] ?? ''); ?></div>
            </div>

            <div class="field" style="grid-column: span 2;"><label>Mileage <span class="req">*</span></label>
              <div class="input-suffix">
                <input type="number" name="mileage" id="mileage" min="0" step="1" inputmode="numeric"
                       value="<?php echo e($f['mileage'] ?? ''); ?>">
                <span>km</span>
              </div>
              <div class="field-error" data-for="mileage"><?php echo e($errors['mileage'] ?? ''); ?></div>
            </div>
          </div>
        </div>

        <!-- Section: Specifications -->
        <div class="form-section">
          <div class="form-section-title">Specifications</div>
          <div class="form-grid">
            <div class="field"><label>Transmission</label>
              <select name="transmission">
                <?php foreach ($TRANSMISSIONS as $opt): echo option($opt, $f['transmission'] ?? null); endforeach; ?>
              </select>
            </div>
            <div class="field"><label>Fuel Type</label>
              <select name="fuel_type">
                <?php foreach ($FUEL_TYPES as $opt): echo option($opt, $f['fuel_type'] ?? null); endforeach; ?>
              </select>
            </div>
            <div class="field"><label>Color <span class="opt">(optional)</span></label>
              <input type="text" name="color" placeholder="e.g., Pearl White" value="<?php echo e($f['color'] ?? ''); ?>">
            </div>
            <div class="field"><label>Modifications</label>
              <select name="modifications">
                <?php foreach ($MODIFICATIONS as $opt): echo option($opt, $f['modifications'] ?? null); endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Section: Condition & History -->
        <div class="form-section">
          <div class="form-section-title">Condition &amp; History</div>
          <div class="form-grid">
            <div class="field"><label>Registration Status (LTO)</label>
              <select name="registration">
                <?php foreach ($REGISTRATIONS as $opt): echo option($opt, $f['registration'] ?? null); endforeach; ?>
              </select>
            </div>
            <div class="field"><label>Maintenance History</label>
              <select name="maintenance">
                <?php foreach ($MAINTENANCE as $opt): echo option($opt, $f['maintenance'] ?? 'Average'); endforeach; ?>
              </select>
            </div>
            <div class="field" style="grid-column: span 2;"><label>Accident History</label>
              <select name="accidents">
                <?php foreach ($ACCIDENTS as $opt): echo option($opt, $f['accidents'] ?? null); endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Section: Pricing (optional) -->
        <div class="form-section">
          <div class="form-section-title">Pricing <span class="opt">(optional — powers the Dashboard's accuracy tracking)</span></div>
          <div class="form-grid">
            <div class="field" style="grid-column: span 2;"><label>Your Asking Price <span class="opt">(optional)</span></label>
              <input type="number" name="asking_price" min="0" step="1" placeholder="e.g., 700000" value="<?php echo e($f['asking_price'] ?? ''); ?>">
              <div class="field-error" data-for="asking_price"><?php echo e($errors['asking_price'] ?? ''); ?></div>
            </div>
          </div>
        </div>

        <div class="btn-row">
          <input type="hidden" name="predict" value="1">
          <button type="submit" class="predict-btn" id="predictBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4"/></svg>
            <span id="predictBtnLabel">Predict Price</span>
          </button>
          <button type="reset" class="reset-btn" id="resetBtn">Reset</button>
        </div>
      </form>
    </div>

    <!-- RESULT PANEL -->
    <div>
      <div class="panel">
        <div class="panel-head">
          <div class="ic" style="background:var(--teal-soft);"><svg viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2"><path d="M12 3l2.5 5.5L20 9l-4 4 1 6-5-3-5 3 1-6-4-4 5.5-.5z"/></svg></div>
          <h3>Estimated Price</h3>
        </div>

        <?php if ($estimated_price !== null): ?>
            <div class="price-box">
              <div class="lbl">Estimated Market Value</div>
              <div class="amt">₱<?php echo number_format($estimated_price); ?></div>
              <div class="range">Possible Range<br><b>₱<?php echo number_format($range_low); ?> – ₱<?php echo number_format($range_high); ?></b></div>
            </div>

            <div class="breakdown">
              <div class="row"><span>Base price (similar listings)</span><span class="amt">₱<?php echo number_format($breakdown['base']); ?></span></div>
              <div class="row"><span>Age depreciation</span><span class="amt neg">− ₱<?php echo number_format($breakdown['year_adj']); ?></span></div>
              <div class="row"><span>Mileage adjustment</span><span class="amt neg">− ₱<?php echo number_format($breakdown['mileage_adj']); ?></span></div>
              <div class="row">
                  <span>Condition &amp; history impact</span>
                  <?php if ($breakdown['cond_adj'] >= 0): ?>
                      <span class="amt pos">+ ₱<?php echo number_format($breakdown['cond_adj']); ?></span>
                  <?php else: ?>
                      <span class="amt neg">− ₱<?php echo number_format(abs($breakdown['cond_adj'])); ?></span>
                  <?php endif; ?>
              </div>
              <div class="row total"><span>Estimated price</span><span class="amt">₱<?php echo number_format($estimated_price); ?></span></div>
            </div>

            <div class="note">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v.01M12 11v5"/></svg>
              <span>This is a data-based estimate. Actual selling price may vary with real-world condition and negotiation.</span>
            </div>
        <?php else: ?>
            <div class="empty-state">
                Fill out the form and click <b>Predict Price</b> to generate a valuation.
            </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// CATALOG carries each model's real year_start / year_end straight from the database.
const CATALOG = <?php echo json_encode($catalog, JSON_UNESCAPED_UNICODE); ?>;
const BODY_TYPES = <?php echo json_encode(get_body_types(), JSON_UNESCAPED_UNICODE); ?>;
const BODY_STYLES = <?php echo json_encode(get_body_type_styles(), JSON_UNESCAPED_UNICODE); ?>;
const INITIAL = {
  vehicle_type: <?php echo json_encode($f['vehicle_type'] ?? ''); ?>,
  brand: <?php echo json_encode($f['brand'] ?? ''); ?>,
  model: <?php echo json_encode($f['model'] ?? ''); ?>,
  year: <?php echo json_encode($f['year'] ?? ''); ?>
};
const CURRENT_YEAR = <?php echo $current_year; ?>;

const typeSelect  = document.getElementById('vehicle_type');
const brandSelect = document.getElementById('brand');
const modelSelect = document.getElementById('model');
const yearSelect  = document.getElementById('year');

const vehiclePreview = document.getElementById('vehiclePreview');
const vpIcon = document.getElementById('vpIcon');
const vpName = document.getElementById('vpName');
const vpMeta = document.getElementById('vpMeta');

function updatePreview() {
  const brand = brandSelect.value;
  const model = modelSelect.value;
  if (!brand || !model) {
    vehiclePreview.classList.add('hidden');
    return;
  }
  const bodyType = BODY_TYPES[brand + '|' + model] || 'Vehicle';
  const style = BODY_STYLES[bodyType] || ['text-faint', 'paper'];
  vpIcon.style.background = 'var(--' + style[1] + ')';
  vpIcon.style.color = 'var(--' + style[0] + ')';
  vpName.textContent = brand + ' ' + model;
  vpMeta.textContent = bodyType + (yearSelect.value ? ' · ' + yearSelect.value : '');
  vehiclePreview.classList.remove('hidden');
}

function resetSelect(select, placeholder, disabled) {
  select.innerHTML = '';
  const opt = document.createElement('option');
  opt.value = '';
  opt.textContent = placeholder;
  select.appendChild(opt);
  select.disabled = disabled;
}

function fillSelect(select, values, selected, placeholder) {
  resetSelect(select, placeholder, false);
  values.forEach(function(v) {
    const opt = document.createElement('option');
    opt.value = v;
    opt.textContent = v;
    if (String(v) === String(selected)) opt.selected = true;
    select.appendChild(opt);
  });
}

function refreshBrands(selectedBrand) {
  const type = typeSelect.value;
  if (!type || !CATALOG[type]) {
    resetSelect(brandSelect, 'Select brand', true);
    return;
  }
  const brands = Object.keys(CATALOG[type]);
  fillSelect(brandSelect, brands, selectedBrand, 'Select brand');
}

function refreshModels(selectedModel) {
  const type = typeSelect.value;
  const brand = brandSelect.value;
  if (!type || !brand || !CATALOG[type] || !CATALOG[type][brand]) {
    resetSelect(modelSelect, 'Select model', true);
    return;
  }
  const models = Object.keys(CATALOG[type][brand]);
  fillSelect(modelSelect, models, selectedModel, 'Select model');
}

// Years come straight from this model's year_start / year_end in the database.
function refreshYears(selectedYear) {
  const type = typeSelect.value;
  const brand = brandSelect.value;
  const model = modelSelect.value;
  const info = (type && brand && model && CATALOG[type] && CATALOG[type][brand]) ? CATALOG[type][brand][model] : null;

  if (!info) {
    resetSelect(yearSelect, 'Select year', true);
    return;
  }

  const minYear = info.year_start;
  const maxYear = info.year_end || CURRENT_YEAR; // null year_end = "still in production"
  const years = [];
  for (let y = maxYear; y >= minYear; y--) years.push(y);
  fillSelect(yearSelect, years, selectedYear, 'Select year');
}

typeSelect.addEventListener('change', function() {
  refreshBrands(null);
  resetSelect(modelSelect, 'Select model', true);
  resetSelect(yearSelect, 'Select year', true);
  updatePreview();
});

brandSelect.addEventListener('change', function() {
  refreshModels(null);
  resetSelect(yearSelect, 'Select year', true);
  updatePreview();
});

modelSelect.addEventListener('change', function() {
  refreshYears(null);
  updatePreview();
});

yearSelect.addEventListener('change', updatePreview);

(function initCascade() {
  if (INITIAL.vehicle_type) {
    refreshBrands(INITIAL.brand);
    if (INITIAL.brand) {
      refreshModels(INITIAL.model);
      if (INITIAL.model) {
        refreshYears(INITIAL.year);
      }
    }
  } else {
    resetSelect(brandSelect, 'Select brand', true);
    resetSelect(modelSelect, 'Select model', true);
    resetSelect(yearSelect, 'Select year', true);
  }
  updatePreview();
})();

// ---- Client-side validation ----
const form = document.getElementById('predictForm');
const predictBtn = document.getElementById('predictBtn');
const predictBtnLabel = document.getElementById('predictBtnLabel');

function setError(fieldName, message) {
  const el = form.querySelector('.field-error[data-for="' + fieldName + '"]');
  if (el) el.textContent = message || '';
}

function validateForm() {
  let valid = true;
  ['vehicle_type','brand','model','year','mileage','asking_price'].forEach(function(f){ setError(f, ''); });

  if (!typeSelect.value)  { setError('vehicle_type', 'Please select a vehicle type.'); valid = false; }
  if (!brandSelect.value) { setError('brand', 'Please select a brand.'); valid = false; }
  if (!modelSelect.value) { setError('model', 'Please select a model.'); valid = false; }
  if (!yearSelect.value)  { setError('year', 'Please select a year.'); valid = false; }

  const mileageVal = form.mileage.value;
  if (mileageVal === '' || isNaN(mileageVal) || parseInt(mileageVal, 10) < 0) {
    setError('mileage', 'Please enter a valid mileage (0 or greater).');
    valid = false;
  }

  const askingVal = form.asking_price.value;
  if (askingVal !== '' && (isNaN(askingVal) || parseFloat(askingVal) < 0)) {
    setError('asking_price', 'Asking price must be a positive number.');
    valid = false;
  }
  return valid;
}

form.addEventListener('submit', function(evt) {
  if (!validateForm()) {
    evt.preventDefault();
    return;
  }
  predictBtn.disabled = true;
  predictBtn.classList.add('loading');
  predictBtnLabel.textContent = 'Predicting…';
});

document.getElementById('resetBtn').addEventListener('click', function() {
  window.setTimeout(function() {
    typeSelect.value = '';
    resetSelect(brandSelect, 'Select brand', true);
    resetSelect(modelSelect, 'Select model', true);
    resetSelect(yearSelect, 'Select year', true);
    ['vehicle_type','brand','model','year','mileage','asking_price'].forEach(function(f){ setError(f, ''); });
    updatePreview();
  }, 0);
});
</script>

<?php include 'footer.php'; ?>