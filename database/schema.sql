-- =============================================================================
-- SHOW DE PRÊMIOS — ESQUEMA COMPLETO DO BANCO DE DADOS (DDL)
-- Marco Inicial Operacional: 06/09/2026
-- Compatível com: SQLite, MySQL / MariaDB e PostgreSQL
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(150) NOT NULL,
    login VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'OPERATOR',
    active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS operation_days (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    operation_date DATE NOT NULL UNIQUE,
    status VARCHAR(50) NOT NULL DEFAULT 'OPEN',
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS card_colors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    bg_color VARCHAR(50) DEFAULT '#fef08a',
    text_color VARCHAR(50) DEFAULT '#854d0e',
    border_color VARCHAR(50) DEFAULT '#eab308',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pricing_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    single_quantity INTEGER NOT NULL DEFAULT 1,
    single_price DECIMAL(10,2) NOT NULL DEFAULT 2.00,
    bundle_quantity INTEGER NOT NULL DEFAULT 3,
    bundle_price DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rounds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    operation_day_id INTEGER NOT NULL,
    round_number INTEGER NOT NULL,
    round_name VARCHAR(100) NULL,
    card_color VARCHAR(100) NULL,
    prizes_count INTEGER NOT NULL DEFAULT 2,
    prize_1 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prize_1_title VARCHAR(150) NULL,
    prize_2 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prize_2_title VARCHAR(150) NULL,
    prize_3 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prize_3_title VARCHAR(150) NULL,
    winner_1_name VARCHAR(150) NULL,
    seller_1_name VARCHAR(150) NULL,
    winner_2_name VARCHAR(150) NULL,
    seller_2_name VARCHAR(150) NULL,
    winner_3_name VARCHAR(150) NULL,
    seller_3_name VARCHAR(150) NULL,
    single_price DECIMAL(10,2) NULL,
    bundle_quantity INTEGER NULL,
    bundle_price DECIMAL(10,2) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'OPEN',
    called_numbers_json TEXT DEFAULT '[]',
    last_called_number INTEGER NULL,
    last_called_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operation_day_id) REFERENCES operation_days(id)
);

CREATE TABLE IF NOT EXISTS sellers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NULL,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    round_id INTEGER NOT NULL,
    seller_id INTEGER NULL,
    seller_name VARCHAR(150) NULL,
    quantity INTEGER NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'CASH',
    created_by INTEGER NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (round_id) REFERENCES rounds(id),
    FOREIGN KEY (seller_id) REFERENCES sellers(id)
);

CREATE TABLE IF NOT EXISTS cash_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    operation_day_id INTEGER NOT NULL,
    type VARCHAR(50) NOT NULL, -- INFLOW, OUTFLOW, REINFORCEMENT, SANGRIA
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'CASH',
    description VARCHAR(255) NOT NULL,
    seller_id INTEGER NULL,
    created_by INTEGER NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operation_day_id) REFERENCES operation_days(id)
);

CREATE TABLE IF NOT EXISTS cash_closings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    operation_day_id INTEGER NOT NULL UNIQUE,
    closing_balance DECIMAL(10,2) NOT NULL,
    counted_money DECIMAL(10,2) DEFAULT NULL,
    counted_pix DECIMAL(10,2) DEFAULT NULL,
    counted_debit DECIMAL(10,2) DEFAULT NULL,
    counted_credit DECIMAL(10,2) DEFAULT NULL,
    expected_money DECIMAL(10,2) DEFAULT NULL,
    expected_pix DECIMAL(10,2) DEFAULT NULL,
    expected_debit DECIMAL(10,2) DEFAULT NULL,
    expected_credit DECIMAL(10,2) DEFAULT NULL,
    difference DECIMAL(10,2) DEFAULT NULL,
    closed_by INTEGER NOT NULL,
    closed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (operation_day_id) REFERENCES operation_days(id)
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key VARCHAR(100) NOT NULL UNIQUE,
    value TEXT NULL,
    type VARCHAR(50) DEFAULT 'string',
    updated_by INTEGER NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    action VARCHAR(100) NOT NULL,
    entity VARCHAR(100) NOT NULL,
    entity_id INTEGER NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    ip_address VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
