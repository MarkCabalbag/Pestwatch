// Session helpers (replacement for auth.php).
// Sessions are signed cookies handled by cookie-session (see server.js).

function isLoggedIn(req) {
  return Boolean(req.session && req.session.userId);
}

// For pages: send anonymous visitors to the login page.
function requireLogin(req, res, next) {
  if (!isLoggedIn(req)) return res.redirect('/login');
  next();
}

// For JSON endpoints: answer 401 instead of redirecting.
function requireLoginApi(req, res, next) {
  if (!isLoggedIn(req)) return res.status(401).json({ error: 'Not logged in.' });
  next();
}

function loginUser(req, id, username) {
  req.session.userId = id;
  req.session.username = username;
}

function logoutUser(req, res) {
  req.session = null; // destroys the session cookie
  res.redirect('/login');
}

module.exports = { isLoggedIn, requireLogin, requireLoginApi, loginUser, logoutUser };
