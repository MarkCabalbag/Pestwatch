<?php
require_once 'db.php';
require_once 'auth.php';
requireLogin();

// Fetch last 20 logs
$logs = $pdo->query("SELECT * FROM sensor_logs ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Latest reading
$latest = $logs[0] ?? null;

// Stats
$pestCount = $pdo->query("SELECT COUNT(*) FROM sensor_logs WHERE pest_detected = 1")->fetchColumn();
$totalLogs = $pdo->query("SELECT COUNT(*) FROM sensor_logs")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="10"> <!-- auto-refresh every 10s -->
<title>Dashboard — PestWatch</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0f0d; --panel: #111a14; --border: #1e3a24;
    --green: #2ecc71; --green-dim: #1a7a43; --text: #d4f0dc;
    --muted: #5a8a66; --red: #e74c3c; --amber: #f39c12;
    --font-head: 'Syne', sans-serif; --font-mono: 'Space Mono', monospace;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg); color: var(--text); font-family: var(--font-mono);
    min-height: 100vh;
    background-image: radial-gradient(ellipse at 10% 80%, #0d2e1a 0%, transparent 55%);
  }

  /* ── Nav ── */
  nav {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 2rem; border-bottom: 1px solid var(--border);
    background: rgba(10,15,13,0.9); position: sticky; top: 0; z-index: 10;
    backdrop-filter: blur(8px);
  }
  .nav-logo { font-family: var(--font-head); font-weight: 800; font-size: 1.2rem; color: var(--green); }
  .nav-user { font-size: 0.75rem; color: var(--muted); }
  .nav-user span { color: var(--text); }
  .logout { color: var(--red); text-decoration: none; font-size: 0.75rem; margin-left: 1.5rem; }
  .logout:hover { text-decoration: underline; }

  /* ── Layout ── */
  main { max-width: 1200px; margin: 0 auto; padding: 2rem; }
  h1 { font-family: var(--font-head); font-size: 1.6rem; font-weight: 800; margin-bottom: 0.25rem; }
  .subtitle { color: var(--muted); font-size: 0.75rem; margin-bottom: 2rem; }

  /* ── Stat Cards ── */
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
  .stat-card {
    background: var(--panel); border: 1px solid var(--border); border-radius: 4px;
    padding: 1.5rem; position: relative; overflow: hidden;
  }
  .stat-card::before {
    content: ''; position: absolute; top: 0; left: 0;
    width: 3px; height: 100%; background: var(--green);
  }
  .stat-card.alert::before { background: var(--red); }
  .stat-card.warn::before  { background: var(--amber); }
  .stat-label { font-size: 0.65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; }
  .stat-value { font-family: var(--font-head); font-size: 2rem; font-weight: 800; }
  .stat-value.red    { color: var(--red); }
  .stat-value.green  { color: var(--green); }
  .stat-value.amber  { color: var(--amber); }
  .stat-unit { font-size: 0.8rem; color: var(--muted); margin-left: 4px; }

  /* ── Status Banner ── */
  .status-banner {
    padding: 1rem 1.5rem; border-radius: 4px; margin-bottom: 2rem;
    display: flex; align-items: center; gap: 1rem;
    font-family: var(--font-head); font-weight: 700; font-size: 1rem;
  }
  .status-banner.safe   { background: rgba(46,204,113,0.1); border: 1px solid var(--green-dim); color: var(--green); }
  .status-banner.danger { background: rgba(231,76,60,0.15); border: 1px solid var(--red);       color: var(--red); animation: pulse 1s infinite alternate; }
  @keyframes pulse { from { opacity: 0.8; } to { opacity: 1; } }
  .dot { width: 10px; height: 10px; border-radius: 50%; background: currentColor; flex-shrink: 0; }

  /* ── Table ── */
  .table-wrap { background: var(--panel); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
  .table-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
  table { width: 100%; border-collapse: collapse; }
  th { padding: 0.75rem 1rem; text-align: left; font-size: 0.65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border); }
  td { padding: 0.75rem 1rem; font-size: 0.8rem; border-bottom: 1px solid rgba(30,58,36,0.4); }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: rgba(46,204,113,0.03); }
  .badge {
    display: inline-block; padding: 0.2rem 0.5rem; border-radius: 2px;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;
  }
  .badge-alert  { background: rgba(231,76,60,0.2);  color: var(--red);   border: 1px solid rgba(231,76,60,0.4); }
  .badge-normal { background: rgba(46,204,113,0.15); color: var(--green); border: 1px solid rgba(46,204,113,0.3); }

  .refresh-note { text-align: right; font-size: 0.65rem; color: var(--muted); margin-top: 1rem; }
</style>
</head>
<body>

<nav>
  <div class="nav-logo">🌿 PestWatch</div>
  <div>
    <span class="nav-user">Logged in as <span><?= htmlspecialchars($_SESSION['username']) ?></span></span>
    <a class="logout" href="logout.php">Logout</a>
  </div>
</nav>

<main>
  <h1>Sensor Dashboard</h1>
  <div class="subtitle">// Live IoT monitoring — auto-refreshes every 10 seconds</div>

  <!-- ── Stat Cards ── -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-label">Temperature</div>
      <div class="stat-value green">
        <?= $latest ? number_format($latest['temperature'], 1) : '--' ?><span class="stat-unit">°C</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Humidity</div>
      <div class="stat-value green">
        <?= $latest ? number_format($latest['humidity'], 0) : '--' ?><span class="stat-unit">%</span>
      </div>
    </div>
    <div class="stat-card <?= ($latest && $latest['pest_detected']) ? 'alert' : '' ?>">
      <div class="stat-label">Proximity / IR</div>
      <div class="stat-value <?= ($latest && $latest['pest_detected']) ? 'red' : 'green' ?>">
        <?= ($latest && $latest['pest_detected']) ? 'DETECTED' : 'CLEAR' ?>
      </div>
    </div>
    <div class="stat-card warn">
      <div class="stat-label">Pest Alerts Total</div>
      <div class="stat-value amber"><?= $pestCount ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Readings</div>
      <div class="stat-value"><?= $totalLogs ?></div>
    </div>
  </div>

  <!-- ── Status Banner ── -->
  <?php if ($latest && $latest['pest_detected']): ?>
    <div class="status-banner danger">
      <div class="dot"></div>
      ⚠ PEST DETECTED — Alert SMS sent to <?= htmlspecialchars($latest['recipient']) ?>
    </div>
  <?php else: ?>
    <div class="status-banner safe">
      <div class="dot"></div>
      ✓ All clear — Monitoring active
    </div>
  <?php endif; ?>

  <!-- ── Recent Logs Table ── -->
  <div class="table-wrap">
    <div class="table-header">Recent Sensor Logs (last 20)</div>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Timestamp</th>
          <th>Temp (°C)</th>
          <th>Humidity (%)</th>
          <th>Proximity</th>
          <th>Sender</th>
          <th>Recipient</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem;">No data yet. Waiting for Arduino...</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $i => $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td><?= htmlspecialchars(number_format($row['temperature'], 1)) ?></td>
            <td><?= htmlspecialchars(number_format($row['humidity'], 0)) ?></td>
            <td>
              <span class="badge <?= $row['pest_detected'] ? 'badge-alert' : 'badge-normal' ?>">
                <?= $row['pest_detected'] ? 'PEST' : 'CLEAR' ?>
              </span>
            </td>
            <td><?= htmlspecialchars($row['sender'])    ?></td>
            <td><?= htmlspecialchars($row['recipient']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="refresh-note">Page auto-refreshes every 10s &nbsp;|&nbsp; <?= date('H:i:s') ?></div>
</main>
</body>
</html>
