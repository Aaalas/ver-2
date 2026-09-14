<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CarPrice Predictor — Vehicle Price Estimator</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none"><path d="M3 12l1.5-5A2 2 0 016.4 5.5h11.2A2 2 0 0119.5 7L21 12M3 12v5a1 1 0 001 1h1a1 1 0 001-1v-1h12v1a1 1 0 001 1h1a1 1 0 001-1v-5M3 12h18M6.5 15.5h.01M17.5 15.5h.01" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div>
        <div class="brand-text">Car<span>Price</span></div>
        <div class="brand-sub">PREDICTOR · SMART VALUATION</div>
      </div>
    </div>

    <div class="nav-label">Predict</div>
    <div class="nav">
      <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Dashboard
      </a>
      <a href="predict.php" class="nav-item <?php echo ($current_page == 'predict') ? 'active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
        Predict Price
      </a>
    </div>

    <div class="nav-label">System</div>
    <div class="nav">
      <a href="dataset.php" class="nav-item <?php echo ($current_page == 'dataset') ? 'active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3"/></svg>
        Dataset
      </a>
      <a href="about.php" class="nav-item <?php echo ($current_page == 'about') ? 'active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><circle cx="12" cy="8.2" r="0.6" fill="currentColor"/></svg>
        About
      </a>
    </div>

    <div class="sidebar-footer">
      <div class="sf-eyebrow"><span class="sf-dot"></span>Model status: live</div>
      <p>Estimates are generated from real listing data and update automatically as new vehicles are added to the system.</p>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Toggle menu" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <div class="topbar-title">
        <span style="color:var(--text-faint); font-weight:500;">
          <?php echo ucfirst(str_replace('_', ' ', $current_page)); ?>
        </span>
      </div>
      <div style="width:32px;"></div>
    </div>
