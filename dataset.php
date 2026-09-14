<?php
require_once 'db.php';
$current_page = 'dataset';

// ---- Read filter/sort/page params ----
$f_brand    = trim($_GET['brand'] ?? '');
$f_model    = trim($_GET['model'] ?? '');
$f_year     = trim($_GET['year'] ?? '');
$f_location = trim($_GET['location'] ?? '');
$f_price_min = trim($_GET['price_min'] ?? '');
$f_price_max = trim($_GET['price_max'] ?? '');
$sort = $_GET['sort'] ?? 'newest_added';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where = [];
$params = [];

if ($f_brand !== '')    { $where[] = 'brand = ?';            $params[] = $f_brand; }
if ($f_model !== '')    { $where[] = 'model LIKE ?';         $params[] = '%' . $f_model . '%'; }
if ($f_year !== '' && ctype_digit($f_year)) { $where[] = 'year_manufactured = ?'; $params[] = (int)$f_year; }
if ($f_location !== '') { $where[] = 'location LIKE ?';      $params[] = '%' . $f_location . '%'; }
if ($f_price_min !== '' && is_numeric($f_price_min)) { $where[] = 'asking_price >= ?'; $params[] = (float)$f_price_min; }
if ($f_price_max !== '' && is_numeric($f_price_max)) { $where[] = 'asking_price <= ?'; $params[] = (float)$f_price_max; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sortMap = [
    'price_asc'   => 'asking_price ASC',
    'price_desc'  => 'asking_price DESC',
    'newest_vehicle' => 'year_manufactured DESC',
    'mileage_desc' => 'mileage DESC',
    'mileage_asc'  => 'mileage ASC',
    'newest_added' => 'date_added DESC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['newest_added'];

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) as c FROM vehicle_listings $whereSql");
$countStmt->execute($params);
$total_rows = (int)$countStmt->fetch()['c'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM vehicle_listings $whereSql ORDER BY $orderSql LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Options for the Brand and Year filter dropdowns, drawn from real data
$brandOptions = $pdo->query("SELECT DISTINCT brand FROM vehicle_listings ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
$yearOptions  = $pdo->query("SELECT DISTINCT year_manufactured FROM vehicle_listings ORDER BY year_manufactured DESC")->fetchAll(PDO::FETCH_COLUMN);

function qs(array $overrides = []) {
    $params = array_merge($_GET, $overrides);
    return htmlspecialchars('?' . http_build_query($params), ENT_QUOTES, 'UTF-8');
}

include 'header.php';
?>

<div class="view">
  <div class="panel">
    <div class="panel-head">
      <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="var(--indigo)" stroke-width="2"><rect x="3" y="4" width="18" height="4" rx="1"/><rect x="3" y="10" width="18" height="4" rx="1"/><rect x="3" y="16" width="18" height="4" rx="1"/></svg></div>
      <h3>Vehicle Dataset</h3>
    </div>
    <div class="panel-sub">Live listings gathered by the system — every prediction you run is added here automatically. Showing <?php echo number_format($total_rows); ?> listing<?php echo $total_rows === 1 ? '' : 's'; ?>.</div>

    <form method="GET" class="filters">
      <select name="brand">
        <option value="">All brands</option>
        <?php foreach ($brandOptions as $b): ?>
          <option value="<?php echo e($b); ?>" <?php echo $b === $f_brand ? 'selected' : ''; ?>><?php echo e($b); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="model" placeholder="Search model…" value="<?php echo e($f_model); ?>">
      <select name="year">
        <option value="">All years</option>
        <?php foreach ($yearOptions as $y): ?>
          <option value="<?php echo e($y); ?>" <?php echo (string)$y === $f_year ? 'selected' : ''; ?>><?php echo e($y); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="location" placeholder="Search location…" value="<?php echo e($f_location); ?>">
      <input type="number" name="price_min" placeholder="Min price" value="<?php echo e($f_price_min); ?>">
      <input type="number" name="price_max" placeholder="Max price" value="<?php echo e($f_price_max); ?>">
      <select name="sort">
        <option value="newest_added" <?php echo $sort === 'newest_added' ? 'selected' : ''; ?>>Recently added</option>
        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Lowest price</option>
        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Highest price</option>
        <option value="newest_vehicle" <?php echo $sort === 'newest_vehicle' ? 'selected' : ''; ?>>Newest vehicle</option>
        <option value="mileage_desc" <?php echo $sort === 'mileage_desc' ? 'selected' : ''; ?>>Highest mileage</option>
        <option value="mileage_asc" <?php echo $sort === 'mileage_asc' ? 'selected' : ''; ?>>Lowest mileage</option>
      </select>
      <button type="submit" class="filter-btn">Apply</button>
      <?php if ($f_brand || $f_model || $f_year || $f_location || $f_price_min || $f_price_max || $sort !== 'newest_added'): ?>
        <a href="dataset.php" class="filter-clear">Clear filters</a>
      <?php endif; ?>
    </form>

    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Vehicle</th><th>Type</th><th>Year</th><th>Mileage</th>
          <th>Location</th><th>Asking Price</th><th>Predicted Value</th><th>Added</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listings)): ?>
          <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:32px 0;">
            <?php echo $total_rows === 0 && empty($where)
                ? 'No listings yet — run a prediction to automatically add the first one.'
                : 'No listings match these filters.'; ?>
          </td></tr>
        <?php else: foreach ($listings as $row): ?>
          <tr>
            <?php [$bt_color, $bt_soft] = get_body_type_styles()[get_body_type($row['brand'], $row['model'])] ?? ['text-faint', 'paper']; ?>
            <td>
              <div class="vehicle-cell">
                <div class="vcell-icon" style="background:var(--<?php echo $bt_soft; ?>); color:var(--<?php echo $bt_color; ?>)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="<?php echo car_icon_path(); ?>"/></svg>
                </div>
                <div>
                  <div class="vcell-name"><?php echo e($row['brand']); ?> <?php echo e($row['model']); ?></div>
                  <div class="vcell-sub"><?php echo e(get_body_type($row['brand'], $row['model'])); ?></div>
                </div>
              </div>
            </td>
            <td><span class="badge"><?php echo e($row['vehicle_type']); ?></span></td>
            <td class="mono-cell"><?php echo e($row['year_manufactured']); ?></td>
            <td class="mono-cell"><?php echo number_format($row['mileage']); ?> km</td>
            <td><?php echo e($row['location'] ?: '—'); ?></td>
            <td class="mono-cell"><?php echo $row['asking_price'] !== null ? '₱' . number_format($row['asking_price']) : '—'; ?></td>
            <td class="mono-cell"><?php echo $row['predicted_value'] !== null ? '₱' . number_format($row['predicted_value']) : '—'; ?></td>
            <td class="mono-cell" style="color:var(--text-faint); font-size:11.5px;"><?php echo e(date('M j, Y', strtotime($row['date_added']))); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <a href="<?php echo qs(['page' => max(1, $page - 1)]); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">← Prev</a>
        <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
        <a href="<?php echo qs(['page' => min($total_pages, $page + 1)]); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next →</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>