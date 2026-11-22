require('dotenv').config({ quiet: true });

const crypto = require('crypto');
const express = require('express');
const cookieSession = require('cookie-session');
const prisma = require('./db');

const app = express();
const PORT = process.env.PORT || 3000;

let secret = process.env.SESSION_SECRET;
if (!secret) {
  secret = crypto.randomBytes(32).toString('hex');
  console.warn('[PestWatch] SESSION_SECRET is not set — using a temporary one (logins reset on restart).');
}

app.disable('x-powered-by');

// The Arduino posts form-encoded data (like a PHP $_POST); JSON is accepted too.
app.use(express.urlencoded({ extended: false }));
app.use(express.json());

app.use(
  cookieSession({
    name: 'pestwatch_session',
    keys: [secret],
    httpOnly: true,
    sameSite: 'lax',
  })
);

app.use(require('./routes/pages'));
app.use(require('./routes/auth'));
app.use(require('./routes/sensors'));

app.use((req, res) => {
  res.status(404).type('text/plain').send('Not found');
});

// Bad request bodies (e.g. malformed JSON) and anything unexpected
app.use((err, req, res, next) => {
  const status = err.status || err.statusCode || 500;
  if (status >= 500) console.error('[PestWatch] Unhandled error:', err);
  res.status(status).json({ error: status >= 500 ? 'Internal server error' : 'Bad request' });
});

const server = app.listen(PORT, () => {
  console.log(`PestWatch running → http://localhost:${PORT}`);
});

async function shutdown() {
  server.close();
  await prisma.$disconnect();
  process.exit(0);
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
