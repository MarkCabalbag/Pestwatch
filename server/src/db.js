// Shared Prisma client — require this wherever the database is needed.
const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient({ log: ['error'] });

module.exports = prisma;
