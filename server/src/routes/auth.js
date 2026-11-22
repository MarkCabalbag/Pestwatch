// Login / signup JSON endpoints (the POST handling from login.php and signup.php).
const express = require('express');
const bcrypt = require('bcryptjs');
const prisma = require('../db');
const { loginUser } = require('../auth');

const router = express.Router();

// Rough equivalent of PHP's FILTER_VALIDATE_EMAIL
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const str = (v) => (v === undefined || v === null ? '' : String(v));

// Hashes created by PHP's password_hash() start with $2y$; bcryptjs expects the
// identical-algorithm $2b$ prefix, so existing users from the PHP app can still log in.
const verifyPassword = (plain, hash) => bcrypt.compare(plain, hash.replace(/^\$2y\$/, '$2b$'));

router.post('/api/login', async (req, res) => {
  const username = str(req.body?.username).trim();
  const password = str(req.body?.password);

  if (!username || !password) {
    return res.status(400).json({ error: 'Please fill in all fields.' });
  }

  try {
    // Log in with either username or email
    const user = await prisma.user.findFirst({
      where: { OR: [{ username }, { email: username }] },
    });

    if (user && (await verifyPassword(password, user.password))) {
      loginUser(req, user.id, user.username);
      return res.json({ success: true, redirect: '/dashboard' });
    }
    return res.status(401).json({ error: 'Invalid credentials.' });
  } catch (err) {
    console.error('[PestWatch] login error:', err);
    return res.status(500).json({ error: 'Server error. Please try again.' });
  }
});

router.post('/api/signup', async (req, res) => {
  const username = str(req.body?.username).trim();
  const email = str(req.body?.email).trim();
  const password = str(req.body?.password);
  const confirm = str(req.body?.confirm);

  let error = '';
  if (!username || !email || !password || !confirm) {
    error = 'All fields are required.';
  } else if (!EMAIL_RE.test(email)) {
    error = 'Invalid email address.';
  } else if (password !== confirm) {
    error = 'Passwords do not match.';
  } else if (Buffer.byteLength(password) < 6) {
    error = 'Password must be at least 6 characters.';
  }
  if (error) return res.status(400).json({ error });

  try {
    const hash = await bcrypt.hash(password, 10);
    await prisma.user.create({ data: { username, email, password: hash } });
    return res.status(201).json({ success: true, message: 'Account created!' });
  } catch (err) {
    if (err.code === 'P2002') {
      // unique constraint on username or email
      return res.status(409).json({ error: 'Username or email already exists.' });
    }
    console.error('[PestWatch] signup error:', err);
    return res.status(500).json({ error: 'Server error. Please try again.' });
  }
});

module.exports = router;
