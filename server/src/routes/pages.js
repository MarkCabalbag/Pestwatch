// HTML page routes (login.php, signup.php, dashboard.php, logout.php).
const path = require('path');
const express = require('express');
const { isLoggedIn, requireLogin, logoutUser } = require('../auth');

const router = express.Router();

const view = (name) => path.join(__dirname, '..', '..', 'views', `${name}.html`);

router.get('/', (req, res) => {
  res.redirect(isLoggedIn(req) ? '/dashboard' : '/login');
});

// Already logged in? Skip straight to the dashboard (same as the PHP version).
router.get('/login', (req, res) => {
  if (isLoggedIn(req)) return res.redirect('/dashboard');
  res.sendFile(view('login'));
});

router.get('/signup', (req, res) => {
  if (isLoggedIn(req)) return res.redirect('/dashboard');
  res.sendFile(view('signup'));
});

router.get('/dashboard', requireLogin, (req, res) => {
  res.set('Cache-Control', 'no-store');
  res.sendFile(view('dashboard'));
});

router.get('/logout', (req, res) => {
  logoutUser(req, res);
});

module.exports = router;
