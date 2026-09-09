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


-- =============================================================================
-- TABELAS MODERNAS: EVENTOS, LOTES, COMPRADORES, PEDIDOS, CARTELAS, AUDITORIA
-- =============================================================================

CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE,
    event_time VARCHAR(10) DEFAULT '20:00',
    location VARCHAR(255) DEFAULT 'Praça Central',
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    ticket_prefix VARCHAR(10) NOT NULL DEFAULT 'JDA',
    max_tickets INT NOT NULL DEFAULT 9999,
    single_price DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    bundle_qty INT NOT NULL DEFAULT 3,
    bundle_price DECIMAL(10,2) NOT NULL DEFAULT 25.00,
    game_mode VARCHAR(50) NOT NULL DEFAULT 'BINGO_75',
    center_free SMALLINT NOT NULL DEFAULT 1,
    card_format VARCHAR(50) NOT NULL DEFAULT '5x5',
    tie_rule VARCHAR(50) NOT NULL DEFAULT 'SPLIT',
    is_locked SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS event_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INT NOT NULL,
    batch_code VARCHAR(30) NOT NULL,
    prefix VARCHAR(10) NOT NULL DEFAULT 'JDA',
    start_sequence INT NOT NULL DEFAULT 1,
    end_sequence INT NOT NULL DEFAULT 9999,
    current_sequence INT NOT NULL DEFAULT 0,
    batch_type VARCHAR(50) NOT NULL DEFAULT 'EXCLUSIVE_RANDOM',
    matrix_template_json TEXT NULL,
    is_locked SMALLINT NOT NULL DEFAULT 0,
    total_generated INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS prizes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INT NOT NULL,
    order_num INT NOT NULL DEFAULT 1,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_url VARCHAR(255) NULL,
    victory_rule VARCHAR(50) NOT NULL DEFAULT 'FULL_CARD',
    active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS buyers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(200) NOT NULL,
    cpf VARCHAR(20) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(200) NULL,
    wants_email SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NULL,
    order_code VARCHAR(50) NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    payment_method VARCHAR(30) NOT NULL DEFAULT 'PIX',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INT NOT NULL,
    ticket_id INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INT NOT NULL,
    payment_code VARCHAR(100) NULL,
    amount DECIMAL(10,2) NOT NULL,
    method VARCHAR(30) NOT NULL DEFAULT 'PIX',
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    pix_payload TEXT NULL,
    pix_qr_code TEXT NULL,
    paid_at TIMESTAMP NULL,
    confirmed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INT NOT NULL,
    batch_id INT NOT NULL,
    buyer_id INT NULL,
    order_id INT NULL,
    ticket_number VARCHAR(20) NOT NULL,
    sequence_number INT NOT NULL,
    prefix VARCHAR(10) NOT NULL DEFAULT 'JDA',
    check_code VARCHAR(20) NOT NULL,
    secure_token VARCHAR(64) NOT NULL UNIQUE,
    grid_type VARCHAR(30) NOT NULL DEFAULT '5x5',
    status VARCHAR(30) NOT NULL DEFAULT 'RESERVED',
    print_count INT NOT NULL DEFAULT 0,
    last_printed_at TIMESTAMP NULL,
    email_sent SMALLINT NOT NULL DEFAULT 0,
    email_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_numbers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INT NOT NULL,
    number_value INT NOT NULL,
    column_letter VARCHAR(2) NOT NULL,
    row_index INT NOT NULL,
    col_index INT NOT NULL,
    is_center SMALLINT NOT NULL DEFAULT 0,
    is_hit SMALLINT NOT NULL DEFAULT 0,
    hit_at_call_id INT NULL
);

CREATE TABLE IF NOT EXISTS ticket_prints (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_validations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INT NULL,
    validation_method VARCHAR(30) NOT NULL,
    input_token VARCHAR(64) NULL,
    input_number VARCHAR(20) NULL,
    input_code VARCHAR(20) NULL,
    is_authenticated SMALLINT NOT NULL DEFAULT 0,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    result_status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_access_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    buyer_id INT NULL,
    ticket_id INT NULL,
    access_type VARCHAR(50) NOT NULL,
    method VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NULL,
    session_id VARCHAR(100) NULL,
    user_agent TEXT NULL,
    result VARCHAR(50) NOT NULL DEFAULT 'SUCCESS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS game_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INT NOT NULL,
    rule_name VARCHAR(100) NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    pattern_matrix_json TEXT NULL,
    description TEXT NULL
);

CREATE TABLE IF NOT EXISTS draws (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INT NOT NULL,
    round_id INT NULL,
    prize_id INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    total_numbers_called INT NOT NULL DEFAULT 0,
    last_called_number INT NULL,
    last_called_letter VARCHAR(2) NULL,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS called_numbers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    draw_id INT NOT NULL,
    number_value INT NOT NULL,
    letter VARCHAR(2) NOT NULL,
    call_order INT NOT NULL,
    called_by INT NULL,
    called_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_game_state (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    draw_id INT NOT NULL,
    ticket_id INT NOT NULL,
    hits_count INT NOT NULL DEFAULT 0,
    needed_count INT NOT NULL DEFAULT 24,
    remaining_count INT NOT NULL DEFAULT 24,
    is_winner SMALLINT NOT NULL DEFAULT 0,
    winning_call_id INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    draw_id INT NOT NULL,
    score INT NOT NULL,
    ticket_count INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS winner_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    draw_id INT NOT NULL,
    prize_id INT NOT NULL,
    ticket_id INT NOT NULL,
    buyer_id INT NULL,
    winning_number INT NOT NULL,
    call_order INT NOT NULL,
    simultaneous_winners_count INT NOT NULL DEFAULT 1,
    tie_resolved SMALLINT NOT NULL DEFAULT 0,
    tie_resolution_type VARCHAR(50) NULL,
    claimed_at TIMESTAMP NULL,
    claimed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tickets_number ON tickets(ticket_number);
CREATE INDEX IF NOT EXISTS idx_tickets_token ON tickets(secure_token);
CREATE INDEX IF NOT EXISTS idx_tickets_check ON tickets(check_code);
CREATE INDEX IF NOT EXISTS idx_tickets_event_status ON tickets(event_id, status);
CREATE INDEX IF NOT EXISTS idx_ticket_numbers_tid ON ticket_numbers(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_numbers_val ON ticket_numbers(number_value);
CREATE INDEX IF NOT EXISTS idx_tgs_draw_rem ON ticket_game_state(draw_id, remaining_count);
CREATE INDEX IF NOT EXISTS idx_access_logs_buyer ON ticket_access_logs(buyer_id);
