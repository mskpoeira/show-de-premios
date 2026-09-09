<?php

namespace Database;

use App\Core\Database;
use PDO;

class Migrations
{
    public static function run(): void
    {
        $pdo = Database::getConnection();
        $driver = Database::getDriver();

        if ($driver === 'sqlite') {
            self::runSqlite($pdo);
        } else {
            self::runStandardSql($pdo, $driver);
        }

        // Incremental migrations
        $newColumns = [
            'winner_name',
            'round_name',
            'card_color',
            'prizes_count',
            'prize_3',
            'prize_1_title',
            'prize_2_title',
            'prize_3_title',
            'winner_1_name',
            'seller_1_name',
            'winner_2_name',
            'seller_2_name',
            'winner_3_name',
            'seller_3_name',
            'single_price',
            'bundle_quantity',
            'bundle_price',
            'called_numbers_json',
            'last_called_number',
            'last_called_at',
        ];

        $colType = $driver === 'pgsql' ? 'VARCHAR(255)' : ($driver === 'sqlite' ? 'TEXT' : 'VARCHAR(255)');
        foreach ($newColumns as $col) {
            try {
                if (in_array($col, ['prizes_count', 'bundle_quantity', 'last_called_number'], true)) {
                    $type = 'INTEGER DEFAULT NULL';
                } elseif (in_array($col, ['prize_3', 'single_price', 'bundle_price'], true)) {
                    $type = 'DECIMAL(10,2) DEFAULT NULL';
                } elseif ($col === 'called_numbers_json') {
                    $type = "TEXT DEFAULT '[]'";
                } else {
                    $type = $colType;
                }
                $pdo->exec("ALTER TABLE rounds ADD COLUMN {$col} {$type}");
            } catch (\Throwable $e) {
                // Column already exists
            }
        }

        // Migração incremental para métodos de pagamento em cash_movements e sales
        try {
            $pdo->exec("ALTER TABLE cash_movements ADD COLUMN payment_method VARCHAR(20) DEFAULT 'CASH'");
        } catch (\Throwable $e) {}
        try {
            $pdo->exec("ALTER TABLE cash_movements ADD COLUMN seller_id INTEGER DEFAULT NULL");
        } catch (\Throwable $e) {}
        try {
            $pdo->exec("ALTER TABLE sales ADD COLUMN payment_method VARCHAR(20) DEFAULT 'CASH'");
        } catch (\Throwable $e) {}

        // Migração incremental para fechamento segregado por método em cash_closings
        $closingCols = [
            'counted_money', 'counted_pix', 'counted_debit', 'counted_credit',
            'expected_money', 'expected_pix', 'expected_debit', 'expected_credit'
        ];
        foreach ($closingCols as $ccol) {
            try {
                $pdo->exec("ALTER TABLE cash_closings ADD COLUMN {$ccol} DECIMAL(10,2) DEFAULT NULL");
            } catch (\Throwable $e) {}
        }

        // Tabela de Cores de Cartelas
        try {
            $autoInc = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'SERIAL PRIMARY KEY';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS card_colors (
                    id {$autoInc},
                    name VARCHAR(100) NOT NULL UNIQUE,
                    bg_color VARCHAR(50) DEFAULT '#fef08a',
                    text_color VARCHAR(50) DEFAULT '#854d0e',
                    border_color VARCHAR(50) DEFAULT '#eab308',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Seed das cores padroes dos fabricantes de cartela
            $defaultColors = [
                ['name' => 'Amarela', 'bg' => '#fef08a', 'text' => '#854d0e', 'border' => '#eab308'],
                ['name' => 'Azul', 'bg' => '#bae6fd', 'text' => '#0369a1', 'border' => '#38bdf8'],
                ['name' => 'Verde', 'bg' => '#bbf7d0', 'text' => '#15803d', 'border' => '#4ade80'],
                ['name' => 'Vermelha', 'bg' => '#fecaca', 'text' => '#b91c1c', 'border' => '#f87171'],
                ['name' => 'Rosa', 'bg' => '#fbcfe8', 'text' => '#be185d', 'border' => '#f472b6'],
                ['name' => 'Branca', 'bg' => '#ffffff', 'text' => '#1e293b', 'border' => '#cbd5e1'],
                ['name' => 'Papel Jornal', 'bg' => '#e2e8f0', 'text' => '#334155', 'border' => '#94a3b8'],
                ['name' => 'Laranja', 'bg' => '#fed7aa', 'text' => '#c2410c', 'border' => '#fb923c'],
                ['name' => 'Lilás', 'bg' => '#e9d5ff', 'text' => '#7e22ce', 'border' => '#c084fc'],
                ['name' => 'Salmão', 'bg' => '#ffe4e6', 'text' => '#9f1239', 'border' => '#fda4af'],
                ['name' => 'Dourada', 'bg' => '#fef9c3', 'text' => '#a16207', 'border' => '#ca8a04'],
            ];

            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM card_colors WHERE LOWER(name) = LOWER(?)");
            $stmtInsert = $pdo->prepare("INSERT INTO card_colors (name, bg_color, text_color, border_color) VALUES (?, ?, ?, ?)");
            foreach ($defaultColors as $c) {
                $stmtCheck->execute([$c['name']]);
                if ((int)$stmtCheck->fetchColumn() === 0) {
                    $stmtInsert->execute([$c['name'], $c['bg'], $c['text'], $c['border']]);
                }
            }
        } catch (\Throwable $e) {
            // Ignora se der erro
        }

        // Garantir que o Administrador Master tcardozo existe, está ativo e com perfil ADMIN
        try {
            $stmtMaster = $pdo->prepare("SELECT id FROM users WHERE LOWER(login) = 'tcardozo'");
            $stmtMaster->execute();
            if ($stmtMaster->fetch()) {
                $pdo->exec("UPDATE users SET role = 'ADMIN', active = 1 WHERE LOWER(login) = 'tcardozo'");
            }
        } catch (\Throwable $e) {
            // Ignora se a tabela ainda não foi criada
        }

                // ---------------------------------------------------------------------
        // TABELAS DA MODERNIZACAO E INTELIGENCIA DO SHOW DE PREMIOS
        // ---------------------------------------------------------------------
        try {
            $autoInc = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : ($driver === 'pgsql' ? 'SERIAL PRIMARY KEY' : 'INT AUTO_INCREMENT PRIMARY KEY');
            $textType = $driver === 'sqlite' ? 'TEXT' : 'DATETIME';
            $jsonType = $driver === 'sqlite' ? 'TEXT' : 'TEXT';

            // 1. events
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS events (
                    id {$autoInc},
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
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 2. event_batches
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS event_batches (
                    id {$autoInc},
                    event_id INT NOT NULL,
                    batch_code VARCHAR(30) NOT NULL,
                    prefix VARCHAR(10) NOT NULL DEFAULT 'JDA',
                    start_sequence INT NOT NULL DEFAULT 1,
                    end_sequence INT NOT NULL DEFAULT 9999,
                    current_sequence INT NOT NULL DEFAULT 0,
                    batch_type VARCHAR(50) NOT NULL DEFAULT 'EXCLUSIVE_RANDOM',
                    matrix_template_json {$jsonType} NULL,
                    is_locked SMALLINT NOT NULL DEFAULT 0,
                    total_generated INT NOT NULL DEFAULT 0,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 3. prizes
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS prizes (
                    id {$autoInc},
                    event_id INT NOT NULL,
                    order_num INT NOT NULL DEFAULT 1,
                    title VARCHAR(150) NOT NULL,
                    description TEXT,
                    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    image_url VARCHAR(255) NULL,
                    victory_rule VARCHAR(50) NOT NULL DEFAULT 'FULL_CARD',
                    active SMALLINT NOT NULL DEFAULT 1,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 4. buyers
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS buyers (
                    id {$autoInc},
                    name VARCHAR(200) NOT NULL,
                    cpf VARCHAR(20) NOT NULL,
                    phone VARCHAR(30) NOT NULL,
                    email VARCHAR(200) NULL,
                    wants_email SMALLINT NOT NULL DEFAULT 0,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 5. orders
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS orders (
                    id {$autoInc},
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
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 6. order_items
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS order_items (
                    id {$autoInc},
                    order_id INT NOT NULL,
                    ticket_id INT NOT NULL,
                    unit_price DECIMAL(10,2) NOT NULL,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 7. payments
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS payments (
                    id {$autoInc},
                    order_id INT NOT NULL,
                    payment_code VARCHAR(100) NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    method VARCHAR(30) NOT NULL DEFAULT 'PIX',
                    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
                    pix_payload TEXT NULL,
                    pix_qr_code TEXT NULL,
                    paid_at {$textType} NULL,
                    confirmed_by INT NULL,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 8. tickets
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tickets (
                    id {$autoInc},
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
                    last_printed_at {$textType} NULL,
                    email_sent SMALLINT NOT NULL DEFAULT 0,
                    email_sent_at {$textType} NULL,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 9. ticket_numbers
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_numbers (
                    id {$autoInc},
                    ticket_id INT NOT NULL,
                    number_value INT NOT NULL,
                    column_letter VARCHAR(2) NOT NULL,
                    row_index INT NOT NULL,
                    col_index INT NOT NULL,
                    is_center SMALLINT NOT NULL DEFAULT 0,
                    is_hit SMALLINT NOT NULL DEFAULT 0,
                    hit_at_call_id INT NULL
                )
            ");

            // 10. ticket_prints
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_prints (
                    id {$autoInc},
                    ticket_id INT NOT NULL,
                    user_id INT NULL,
                    ip_address VARCHAR(45) NULL,
                    printed_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 11. ticket_validations
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_validations (
                    id {$autoInc},
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
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 12. ticket_access_logs
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_access_logs (
                    id {$autoInc},
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
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 13. game_rules
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS game_rules (
                    id {$autoInc},
                    event_id INT NOT NULL,
                    rule_name VARCHAR(100) NOT NULL,
                    rule_type VARCHAR(50) NOT NULL,
                    pattern_matrix_json {$jsonType} NULL,
                    description TEXT NULL
                )
            ");

            // 14. draws
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS draws (
                    id {$autoInc},
                    event_id INT NOT NULL,
                    round_id INT NULL,
                    prize_id INT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
                    total_numbers_called INT NOT NULL DEFAULT 0,
                    last_called_number INT NULL,
                    last_called_letter VARCHAR(2) NULL,
                    started_at {$textType} NULL,
                    finished_at {$textType} NULL,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 15. called_numbers
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS called_numbers (
                    id {$autoInc},
                    draw_id INT NOT NULL,
                    number_value INT NOT NULL,
                    letter VARCHAR(2) NOT NULL,
                    call_order INT NOT NULL,
                    called_by INT NULL,
                    called_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 16. ticket_game_state
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_game_state (
                    id {$autoInc},
                    draw_id INT NOT NULL,
                    ticket_id INT NOT NULL,
                    hits_count INT NOT NULL DEFAULT 0,
                    needed_count INT NOT NULL DEFAULT 24,
                    remaining_count INT NOT NULL DEFAULT 24,
                    is_winner SMALLINT NOT NULL DEFAULT 0,
                    winning_call_id INT NULL,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 17. ticket_scores
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_scores (
                    id {$autoInc},
                    draw_id INT NOT NULL,
                    score INT NOT NULL,
                    ticket_count INT NOT NULL DEFAULT 0,
                    updated_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 18. winner_events
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS winner_events (
                    id {$autoInc},
                    draw_id INT NOT NULL,
                    prize_id INT NOT NULL,
                    ticket_id INT NOT NULL,
                    buyer_id INT NULL,
                    winning_number INT NOT NULL,
                    call_order INT NOT NULL,
                    simultaneous_winners_count INT NOT NULL DEFAULT 1,
                    tie_resolved SMALLINT NOT NULL DEFAULT 0,
                    tie_resolution_type VARCHAR(50) NULL,
                    claimed_at {$textType} NULL,
                    claimed_by INT NULL,
                    created_at {$textType} DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Indices de Alta Performance
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_number ON tickets(ticket_number)"); } catch (\\Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_token ON tickets(secure_token)"); } catch (\\Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_check ON tickets(check_code)"); } catch (\\Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_event_status ON tickets(event_id, status)"); } catch (\\Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_numbers_tid ON ticket_numbers(ticket_id)"); } catch (\\Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ticket_numbers_val ON ticket_numbers(number_value)"); } catch (\\Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tgs_draw_rem ON ticket_game_state(draw_id, remaining_count)"); } catch (\\Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_access_logs_buyer ON ticket_access_logs(buyer_id)"); } catch (\\Throwable $e) {}

            // Seed do Evento Inicial e Lote Padrao se tabela estiver vazia
            $stmtEventCount = $pdo->query("SELECT COUNT(*) FROM events");
            if ((int)$stmtEventCount->fetchColumn() === 0) {
                $pdo->exec("
                    INSERT INTO events (name, description, event_date, event_time, location, status, ticket_prefix, max_tickets, single_price, bundle_qty, bundle_price, game_mode, center_free, card_format, tie_rule)
                    VALUES ('Show de Prêmios - Festa da Padroeira 2026', 'Festa da Padroeira Exaltação da Santa Cruz 2026', '2026-09-09', '20:00', 'Praça Central', 'ACTIVE', 'JDA', 9999, 10.00, 3, 25.00, 'BINGO_75', 1, '5x5', 'SPLIT')
                ");
                $eventId = (int)$pdo->lastInsertId();

                $pdo->exec("
                    INSERT INTO event_batches (event_id, batch_code, prefix, start_sequence, end_sequence, current_sequence, batch_type, total_generated)
                    VALUES ({$eventId}, 'LOTE-1', 'JDA', 1, 9999, 0, 'EXCLUSIVE_RANDOM', 0)
                ");

                // Seed dos 3 premios padrao
                $pdo->exec("
                    INSERT INTO prizes (event_id, order_num, title, description, value, victory_rule, active)
                    VALUES 
                    ({$eventId}, 1, '1º Prêmio', 'R$ 500,00 em Dinheiro / PIX', 500.00, 'FULL_CARD', 1),
                    ({$eventId}, 2, '2º Prêmio', 'R$ 1.000,00 em Dinheiro / PIX', 1000.00, 'FULL_CARD', 1),
                    ({$eventId}, 3, '3º Prêmio', 'R$ 3.000,00 ou Prêmio Especial', 3000.00, 'FULL_CARD', 1)
                ");
            }

        } catch (\\Throwable $e) {
            error_log('Error in modern migrations: ' . $e->getMessage());
        }

        self::seedSettings($pdo);
        self::runDatabaseCleanOnce($pdo);
    }

    private static function runSqlite(PDO $pdo): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            login TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'OPERATOR',
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS sellers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            nickname TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            started_at TEXT,
            ended_at TEXT,
            notes TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pricing_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            effective_from TEXT NOT NULL,
            effective_to TEXT,
            single_quantity INTEGER NOT NULL DEFAULT 1,
            single_price DECIMAL(10,2) NOT NULL DEFAULT 2.00,
            bundle_quantity INTEGER NOT NULL DEFAULT 3,
            bundle_price DECIMAL(10,2) NOT NULL DEFAULT 5.00,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS operation_days (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            operation_date TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT 'OPEN',
            initial_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            responsible_user_id INTEGER,
            notes TEXT,
            opened_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (responsible_user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS rounds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            operation_day_id INTEGER NOT NULL,
            round_number INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'OPEN',
            prize_1 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            prize_2 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            suggested_total_next DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            suggested_prize_1_next DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            suggested_prize_2_next DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            winner_name TEXT,
            notes TEXT,
            opened_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(operation_day_id, round_number),
            FOREIGN KEY (operation_day_id) REFERENCES operation_days(id)
        );

        CREATE TABLE IF NOT EXISTS sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            operation_day_id INTEGER NOT NULL,
            round_id INTEGER NOT NULL,
            seller_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            pricing_rule_id INTEGER,
            pricing_snapshot_json TEXT,
            notes TEXT,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_at TEXT,
            FOREIGN KEY (operation_day_id) REFERENCES operation_days(id),
            FOREIGN KEY (round_id) REFERENCES rounds(id),
            FOREIGN KEY (seller_id) REFERENCES sellers(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS cash_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            operation_day_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            reason TEXT NOT NULL,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (operation_day_id) REFERENCES operation_days(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS cash_closings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            operation_day_id INTEGER NOT NULL UNIQUE,
            expected_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            counted_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            difference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status TEXT NOT NULL DEFAULT 'OK',
            justification TEXT,
            responsible_user_id INTEGER,
            closed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (operation_day_id) REFERENCES operation_days(id),
            FOREIGN KEY (responsible_user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'string',
            updated_by INTEGER,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id INTEGER,
            old_values_json TEXT,
            new_values_json TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        ";

        $pdo->exec($sql);
    }

    private static function runStandardSql(PDO $pdo, string $driver): void
    {
        $idType = $driver === 'pgsql' ? 'SERIAL PRIMARY KEY' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $timeType = $driver === 'pgsql' ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP';

        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id {$idType},
            name VARCHAR(150) NOT NULL,
            login VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'OPERATOR',
            active SMALLINT NOT NULL DEFAULT 1,
            created_at {$timeType},
            updated_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS sellers (
            id {$idType},
            name VARCHAR(150) NOT NULL,
            nickname VARCHAR(100),
            active SMALLINT NOT NULL DEFAULT 1,
            started_at DATE,
            ended_at DATE,
            notes TEXT,
            created_at {$timeType},
            updated_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS pricing_rules (
            id {$idType},
            effective_from DATE NOT NULL,
            effective_to DATE,
            single_quantity INT NOT NULL DEFAULT 1,
            single_price DECIMAL(10,2) NOT NULL DEFAULT 2.00,
            bundle_quantity INT NOT NULL DEFAULT 3,
            bundle_price DECIMAL(10,2) NOT NULL DEFAULT 5.00,
            active SMALLINT NOT NULL DEFAULT 1,
            created_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS operation_days (
            id {$idType},
            operation_date DATE NOT NULL UNIQUE,
            status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
            initial_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            responsible_user_id INT,
            notes TEXT,
            opened_at {$timeType},
            closed_at {$timeType},
            created_at {$timeType},
            updated_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS rounds (
            id {$idType},
            operation_day_id INT NOT NULL,
            round_number INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
            prize_1 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            prize_2 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            suggested_total_next DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            suggested_prize_1_next DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            suggested_prize_2_next DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            winner_name VARCHAR(255),
            notes TEXT,
            opened_at {$timeType},
            closed_at {$timeType},
            created_at {$timeType},
            updated_at {$timeType},
            UNIQUE(operation_day_id, round_number)
        );

        CREATE TABLE IF NOT EXISTS sales (
            id {$idType},
            operation_day_id INT NOT NULL,
            round_id INT NOT NULL,
            seller_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            pricing_rule_id INT,
            pricing_snapshot_json TEXT,
            notes TEXT,
            created_by INT,
            created_at {$timeType},
            updated_at {$timeType},
            cancelled_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS cash_movements (
            id {$idType},
            operation_day_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            reason TEXT NOT NULL,
            created_by INT,
            created_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS cash_closings (
            id {$idType},
            operation_day_id INT NOT NULL UNIQUE,
            expected_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            counted_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            difference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'OK',
            justification TEXT,
            responsible_user_id INT,
            closed_at {$timeType},
            created_at {$timeType},
            updated_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS settings (
            key VARCHAR(100) PRIMARY KEY,
            value TEXT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'string',
            updated_by INT,
            updated_at {$timeType}
        );

        CREATE TABLE IF NOT EXISTS audit_logs (
            id {$idType},
            user_id INT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) NOT NULL,
            entity_id INT,
            old_values_json TEXT,
            new_values_json TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at {$timeType}
        );
        ";

        $pdo->exec($sql);
    }

    public static function seedSettings(PDO $pdo): void
    {
        $defaults = [
            'single_quantity' => ['1', 'int'],
            'single_price' => ['2.00', 'decimal'],
            'bundle_quantity' => ['3', 'int'],
            'bundle_price' => ['5.00', 'decimal'],
            'prize_pool_percent' => ['50', 'int'],
            'prize_1_percent' => ['65', 'int'],
            'prize_2_percent' => ['35', 'int'],
            'prize_rounding' => ['10.00', 'decimal'],
            'cash_tolerance' => ['0.01', 'decimal'],
            'locale' => ['pt-BR', 'string'],
            'timezone' => ['America/Sao_Paulo', 'string'],
            'history_start_date' => ['2026-09-06', 'string'],
            'carry_previous_prize_suggestion' => ['true', 'bool'],
            'system_title' => ['Show de Prêmios', 'string'],
            'pix_key' => ['', 'string'],
            'pix_key_type' => ['CHAVE_ALEATORIA', 'string'],
            'pix_receiver_name' => ['Show de Prêmios', 'string'],
            'pix_receiver_city' => ['São Paulo', 'string'],
            'pix_show_on_telao' => ['true', 'bool'],
            'pix_description' => ['Show de Prêmios', 'string'],
            'pix_banner_title' => ['PAGUE COM PIX DIRETO DO SEU LUGAR', 'string'],
        ];

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO settings (key, value, type) VALUES (?, ?, ?)");

        foreach ($defaults as $key => [$val, $type]) {
            $stmtCheck->execute([$key]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                $stmtInsert->execute([$key, $val, $type]);
            }
        }

        // Check pricing_rule initial
        $stmtPricing = $pdo->query("SELECT COUNT(*) FROM pricing_rules");
        if ((int)$stmtPricing->fetchColumn() === 0) {
            $stmtP = $pdo->prepare("INSERT INTO pricing_rules (effective_from, single_quantity, single_price, bundle_quantity, bundle_price, active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtP->execute(['2026-09-06', 1, 2.00, 3, 5.00, 1]);
        }
    }

    private static function runDatabaseCleanOnce(PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'clean_db_keep_users_sellers_v1'");
            $stmt->execute();
            if ($stmt->fetch()) {
                return; // Limpeza já realizada anteriormente
            }

            // Executa a limpeza preservando estritamente users, sellers, card_colors e pricing_rules
            \App\Services\BackupService::cleanDatabaseKeepUsersAndSellers();

            // Grava marcação na tabela settings para execução única
            $now = \App\Services\AuditService::getBrasiliaTime();
            $stmtSet = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value, type, updated_at) VALUES ('clean_db_keep_users_sellers_v1', ?, 'string', ?)");
            $stmtSet->execute([$now, $now]);
        } catch (\Throwable $e) {
            error_log("runDatabaseCleanOnce error: " . $e->getMessage());
        }
    }
}
