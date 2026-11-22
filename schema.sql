-- ============================================================
--  Pest Monitor Database Schema
--  Run this once in phpMyAdmin or MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS pest_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pest_monitor;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,         -- bcrypt hash
    created_at DATETIME DEFAULT NOW()
);

-- Sensor logs from Arduino
CREATE TABLE IF NOT EXISTS sensor_logs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    temperature    FLOAT        NOT NULL,
    humidity       FLOAT        NOT NULL,
    pest_detected  TINYINT(1)   NOT NULL DEFAULT 0,
    sender         VARCHAR(20)  DEFAULT 'SIM800L',
    recipient      VARCHAR(20)  DEFAULT '',
    created_at     DATETIME     DEFAULT NOW()
);
