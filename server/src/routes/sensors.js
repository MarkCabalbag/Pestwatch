// Sensor data: the Arduino ingest endpoint (receive.php) and the dashboard data feed.
const express = require('express');
const prisma = require('../db');
const { requireLoginApi } = require('../auth');

const router = express.Router();

// ── Helpers that mimic PHP's loose casting so the Arduino sketch behaves the same ──
const isset = (v) => v !== undefined && v !== null;

// floatval(): leading numeric prefix, otherwise 0
const phpFloat = (v) => {
  if (typeof v === 'boolean') v = Number(v);
  const n = parseFloat(v);
  return Number.isFinite(n) ? n : 0;
};

// ── POST /receive — data from the Arduino / SIM800L ──
// Also answers on the old PHP paths so existing firmware URLs keep working.
const RECEIVE_PATHS = ['/receive', '/receive.php', '/iot/receive.php'];

router.post(RECEIVE_PATHS, async (req, res) => {
  const body = req.body || {};

  const temp      = isset(body.temp)     ? phpFloat(body.temp)                 : null;
  const humidity  = isset(body.humidity) ? phpFloat(body.humidity)             : null;
  const pestRaw   = isset(body.pest)     ? Math.trunc(phpFloat(body.pest))     : 0;
  const sender    = isset(body.sender)    ? String(body.sender).trim().slice(0, 20)    : 'SIM800L Module';
  const recipient = isset(body.recipient) ? String(body.recipient).trim().slice(0, 20) : 'Unknown';

  if (temp === null || humidity === null) {
    return res.status(400).json({ error: 'Missing required fields: temp and humidity are required' });
  }

  // Reject garbage sensor values
  if (temp < -40 || temp > 80) {
    return res.status(422).json({ error: 'Temperature out of valid range (-40 to 80 °C)' });
  }
  if (humidity < 0 || humidity > 100) {
    return res.status(422).json({ error: 'Humidity out of valid range (0–100%)' });
  }

  const pest = pestRaw === 1; // only 1 counts as "pest detected"

  try {
    const log = await prisma.sensorLog.create({
      data: { temperature: temp, humidity, pestDetected: pest, sender, recipient },
    });

    res.json({
      success: true,
      message: 'Data stored',
      id: String(log.id), // PHP's lastInsertId() was a string; keep the same wire format
    });
  } catch (err) {
    // Log the real error server-side; return a safe message
    console.error('[PestWatch] DB error:', err);
    res.status(500).json({ error: 'Database error — check server logs' });
  }
});

router.all(RECEIVE_PATHS, (req, res) => {
  res.status(405).json({ error: 'Method not allowed' });
});

// ── GET /api/dashboard — everything the dashboard page needs ──
router.get('/api/dashboard', requireLoginApi, async (req, res) => {
  try {
    const [logs, pestCount, totalLogs] = await Promise.all([
      prisma.sensorLog.findMany({
        orderBy: [{ createdAt: 'desc' }, { id: 'desc' }],
        take: 20,
      }),
      prisma.sensorLog.count({ where: { pestDetected: true } }),
      prisma.sensorLog.count(),
    ]);

    res.set('Cache-Control', 'no-store');
    res.json({ username: req.session.username, logs, pestCount, totalLogs });
  } catch (err) {
    console.error('[PestWatch] dashboard error:', err);
    res.status(500).json({ error: 'Database error — check server logs' });
  }
});

module.exports = router;
