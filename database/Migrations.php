<?php

namespace Database;

use App\Core\Database;
use PDO;

class Migrations
{
    public const VERSION = '2026.09.13.1';

    public static function run(): void
    {
        $pdo = Database::getConnection();
        $driver = Database::getDriver();
        $pdo->beginTransaction();

        try {
            self::createCoreTables($pdo, $driver);
            self::createModernTables($pdo, $driver);
            self::applyAdditiveColumns($pdo, $driver);
            self::createIndexes($pdo, $driver);
            self::seedDefaults($pdo);
            self::migrateLegacyDrawHistory($pdo, $driver);
            self::recordVersion($pdo, $driver);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function idType(string $driver): string
    {
        return match ($driver) {
            'pgsql' => 'SERIAL PRIMARY KEY',
            'mysql' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
    }

    private static function timeType(string $driver): string
    {
        return $driver === 'sqlite' ? 'TEXT' : 'TIMESTAMP';
    }

    private static function createCoreTables(PDO $pdo, string $driver): void
    {
        $id = self::idType($driver);
        $time = self::timeType($driver);

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id {$id}, name VARCHAR(150) NOT NULL, login VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL DEFAULT 'OPERATOR', active SMALLINT NOT NULL DEFAULT 1,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sellers (
            id {$id}, name VARCHAR(150) NOT NULL, nickname VARCHAR(100) NULL, active SMALLINT NOT NULL DEFAULT 1,
            started_at DATE NULL, ended_at DATE NULL, notes TEXT NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pricing_rules (
            id {$id}, effective_from DATE NOT NULL, effective_to DATE NULL,
            single_quantity INTEGER NOT NULL DEFAULT 1, single_price DECIMAL(10,2) NOT NULL DEFAULT 2.00,
            bundle_quantity INTEGER NOT NULL DEFAULT 3, bundle_price DECIMAL(10,2) NOT NULL DEFAULT 5.00,
            active SMALLINT NOT NULL DEFAULT 1, created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS operation_days (
            id {$id}, operation_date DATE NOT NULL UNIQUE, status VARCHAR(30) NOT NULL DEFAULT 'OPEN', initial_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            responsible_user_id INTEGER NULL, notes TEXT NULL, opened_at {$time} DEFAULT CURRENT_TIMESTAMP, closed_at {$time} NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS rounds (
            id {$id}, operation_day_id INTEGER NOT NULL, round_number INTEGER NOT NULL,
            round_name VARCHAR(100) NULL, card_color VARCHAR(100) NULL, prizes_count INTEGER NOT NULL DEFAULT 2,
            status VARCHAR(30) NOT NULL DEFAULT 'OPEN', prize_1 DECIMAL(10,2) NOT NULL DEFAULT 0.00, prize_2 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            winner_name VARCHAR(150) NULL, notes TEXT NULL, opened_at {$time} DEFAULT CURRENT_TIMESTAMP, closed_at {$time} NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(operation_day_id, round_number)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
            id {$id}, operation_day_id INTEGER NOT NULL, round_id INTEGER NOT NULL, seller_id INTEGER NULL,
            quantity INTEGER NOT NULL DEFAULT 0, amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            pricing_rule_id INTEGER NULL, pricing_snapshot_json TEXT NULL, notes TEXT NULL, created_by INTEGER NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP, cancelled_at {$time} NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cash_movements (
            id {$id}, operation_day_id INTEGER NOT NULL, type VARCHAR(50) NOT NULL, amount DECIMAL(10,2) NOT NULL,
            reason TEXT NULL, created_by INTEGER NULL, created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cash_closings (
            id {$id}, operation_day_id INTEGER NOT NULL UNIQUE, expected_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            counted_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00, difference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'OK', justification TEXT NULL, responsible_user_id INTEGER NULL,
            closed_at {$time} DEFAULT CURRENT_TIMESTAMP, created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key VARCHAR(100) PRIMARY KEY, value TEXT NOT NULL, type VARCHAR(50) NOT NULL DEFAULT 'string',
            updated_by INTEGER NULL, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id {$id}, user_id INTEGER NULL, action VARCHAR(100) NOT NULL, entity_type VARCHAR(100) NOT NULL,
            entity_id INTEGER NULL, old_values_json TEXT NULL, new_values_json TEXT NULL,
            ip_address VARCHAR(45) NULL, user_agent TEXT NULL, created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS card_colors (
            id {$id}, name VARCHAR(100) NOT NULL UNIQUE, bg_color VARCHAR(50) DEFAULT '#fef08a', text_color VARCHAR(50) DEFAULT '#854d0e',
            border_color VARCHAR(50) DEFAULT '#eab308', created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private static function createModernTables(PDO $pdo, string $driver): void
    {
        $id = self::idType($driver);
        $time = self::timeType($driver);

        $pdo->exec("CREATE TABLE IF NOT EXISTS events (
            id {$id}, name VARCHAR(255) NOT NULL, description TEXT NULL, event_date DATE NULL, event_time VARCHAR(10) DEFAULT '20:00',
            location VARCHAR(255) DEFAULT 'Praça Central', status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE', modality VARCHAR(20) NOT NULL DEFAULT 'HYBRID',
            ticket_prefix VARCHAR(10) NOT NULL DEFAULT 'JDA', max_tickets INTEGER NOT NULL DEFAULT 9999,
            single_price DECIMAL(10,2) NOT NULL DEFAULT 2.00, bundle_qty INTEGER NOT NULL DEFAULT 3, bundle_price DECIMAL(10,2) NOT NULL DEFAULT 5.00,
            game_mode VARCHAR(50) NOT NULL DEFAULT 'BINGO_75', center_free SMALLINT NOT NULL DEFAULT 1, card_format VARCHAR(50) NOT NULL DEFAULT '5x5',
            tie_rule VARCHAR(50) NOT NULL DEFAULT 'SPLIT', is_locked SMALLINT NOT NULL DEFAULT 0,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS event_batches (
            id {$id}, event_id INTEGER NOT NULL, batch_code VARCHAR(30) NOT NULL, prefix VARCHAR(10) NOT NULL DEFAULT 'JDA',
            start_sequence INTEGER NOT NULL DEFAULT 1, end_sequence INTEGER NOT NULL DEFAULT 9999, current_sequence INTEGER NOT NULL DEFAULT 0,
            batch_type VARCHAR(50) NOT NULL DEFAULT 'EXCLUSIVE_RANDOM', matrix_template_json TEXT NULL, is_locked SMALLINT NOT NULL DEFAULT 0,
            total_generated INTEGER NOT NULL DEFAULT 0, created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS prizes (
            id {$id}, event_id INTEGER NOT NULL, order_num INTEGER NOT NULL DEFAULT 1, title VARCHAR(150) NOT NULL,
            description TEXT NULL, value DECIMAL(10,2) NOT NULL DEFAULT 0.00, image_url VARCHAR(255) NULL,
            victory_rule VARCHAR(50) NOT NULL DEFAULT 'FULL_CARD', active SMALLINT NOT NULL DEFAULT 1,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS buyers (
            id {$id}, name VARCHAR(200) NOT NULL, cpf VARCHAR(20) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(200) NULL,
            wants_email SMALLINT NOT NULL DEFAULT 0, created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id {$id}, event_id INTEGER NOT NULL, buyer_id INTEGER NOT NULL, seller_id INTEGER NULL, order_code VARCHAR(50) NOT NULL UNIQUE,
            quantity INTEGER NOT NULL DEFAULT 1, total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
            payment_method VARCHAR(30) NOT NULL DEFAULT 'PIX', notes TEXT NULL, created_by INTEGER NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
            id {$id}, order_id INTEGER NOT NULL, ticket_id INTEGER NOT NULL, unit_price DECIMAL(10,2) NOT NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            id {$id}, order_id INTEGER NOT NULL, payment_code VARCHAR(100) NULL, amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(30) NOT NULL DEFAULT 'PIX', status VARCHAR(30) NOT NULL DEFAULT 'PENDING', pix_payload TEXT NULL, pix_qr_code TEXT NULL,
            paid_at {$time} NULL, confirmed_by INTEGER NULL, created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
            id {$id}, event_id INTEGER NOT NULL, batch_id INTEGER NOT NULL, buyer_id INTEGER NULL, order_id INTEGER NULL,
            ticket_number VARCHAR(20) NOT NULL, sequence_number INTEGER NOT NULL, prefix VARCHAR(10) NOT NULL DEFAULT 'JDA',
            check_code VARCHAR(20) NOT NULL, secure_token VARCHAR(128) NOT NULL UNIQUE, grid_type VARCHAR(30) NOT NULL DEFAULT '5x5',
            status VARCHAR(30) NOT NULL DEFAULT 'RESERVED', print_count INTEGER NOT NULL DEFAULT 0, last_printed_at {$time} NULL,
            email_sent SMALLINT NOT NULL DEFAULT 0, email_sent_at {$time} NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_numbers (
            id {$id}, ticket_id INTEGER NOT NULL, number_value INTEGER NOT NULL, column_letter VARCHAR(2) NOT NULL,
            row_index INTEGER NOT NULL, col_index INTEGER NOT NULL, is_center SMALLINT NOT NULL DEFAULT 0,
            is_hit SMALLINT NOT NULL DEFAULT 0, hit_at_call_id INTEGER NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_prints (
            id {$id}, ticket_id INTEGER NOT NULL, user_id INTEGER NULL, ip_address VARCHAR(45) NULL, printed_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_validations (
            id {$id}, ticket_id INTEGER NULL, validation_method VARCHAR(30) NOT NULL, input_token VARCHAR(128) NULL,
            input_number VARCHAR(20) NULL, input_code VARCHAR(20) NULL, is_authenticated SMALLINT NOT NULL DEFAULT 0,
            user_id INTEGER NULL, ip_address VARCHAR(45) NULL, user_agent TEXT NULL, result_status VARCHAR(50) NOT NULL,
            created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_access_logs (
            id {$id}, user_id INTEGER NOT NULL, user_name VARCHAR(150) NOT NULL, user_role VARCHAR(50) NOT NULL,
            buyer_id INTEGER NULL, ticket_id INTEGER NULL, access_type VARCHAR(50) NOT NULL, method VARCHAR(30) NOT NULL,
            ip_address VARCHAR(45) NULL, session_id VARCHAR(100) NULL, user_agent TEXT NULL, result VARCHAR(50) NOT NULL DEFAULT 'SUCCESS',
            created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS game_rules (
            id {$id}, event_id INTEGER NOT NULL, rule_name VARCHAR(100) NOT NULL, rule_type VARCHAR(50) NOT NULL,
            pattern_matrix_json TEXT NULL, description TEXT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS draws (
            id {$id}, event_id INTEGER NOT NULL, round_id INTEGER NULL, prize_id INTEGER NULL, status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
            total_numbers_called INTEGER NOT NULL DEFAULT 0, last_called_number INTEGER NULL, last_called_letter VARCHAR(2) NULL,
            started_at {$time} NULL, finished_at {$time} NULL, created_at {$time} DEFAULT CURRENT_TIMESTAMP, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS draw_stones (
            id {$id}, draw_id INTEGER NOT NULL, number_value INTEGER NOT NULL, letter VARCHAR(2) NOT NULL,
            call_order INTEGER NOT NULL, called_by INTEGER NULL, called_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_game_state (
            id {$id}, draw_id INTEGER NOT NULL, ticket_id INTEGER NOT NULL, hits_count INTEGER NOT NULL DEFAULT 0,
            needed_count INTEGER NOT NULL DEFAULT 24, remaining_count INTEGER NOT NULL DEFAULT 24,
            is_winner SMALLINT NOT NULL DEFAULT 0, winning_call_id INTEGER NULL, updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_scores (
            id {$id}, draw_id INTEGER NOT NULL, score INTEGER NOT NULL, ticket_count INTEGER NOT NULL DEFAULT 0,
            updated_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS winner_claims (
            id {$id}, draw_id INTEGER NOT NULL, prize_id INTEGER NOT NULL, winner_type VARCHAR(20) NOT NULL,
            ticket_id INTEGER NULL, buyer_id INTEGER NULL, physical_name VARCHAR(200) NULL, physical_phone VARCHAR(30) NULL,
            physical_cpf VARCHAR(20) NULL, note TEXT NULL, winning_number INTEGER NOT NULL, call_order INTEGER NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'PENDING', simultaneous_winners_count INTEGER NOT NULL DEFAULT 1,
            tie_resolution_type VARCHAR(50) NULL, claimed_by INTEGER NULL, claimed_at {$time} DEFAULT CURRENT_TIMESTAMP,
            homologated_by INTEGER NULL, homologated_at {$time} NULL, created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS winner_events (
            id {$id}, draw_id INTEGER NOT NULL, prize_id INTEGER NOT NULL, ticket_id INTEGER NOT NULL, buyer_id INTEGER NULL,
            winning_number INTEGER NOT NULL, call_order INTEGER NOT NULL, simultaneous_winners_count INTEGER NOT NULL DEFAULT 1,
            tie_resolved SMALLINT NOT NULL DEFAULT 0, tie_resolution_type VARCHAR(50) NULL, claimed_at {$time} NULL,
            claimed_by INTEGER NULL, created_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS migration_history (
            version VARCHAR(50) PRIMARY KEY, applied_at {$time} DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private static function applyAdditiveColumns(PDO $pdo, string $driver): void
    {
        $roundColumns = [
            'round_name' => 'VARCHAR(100) NULL', 'card_color' => 'VARCHAR(100) NULL', 'prizes_count' => 'INTEGER DEFAULT 2',
            'prize_3' => 'DECIMAL(10,2) DEFAULT 0.00', 'prize_1_title' => 'VARCHAR(150) NULL', 'prize_2_title' => 'VARCHAR(150) NULL',
            'prize_3_title' => 'VARCHAR(150) NULL', 'winner_1_name' => 'VARCHAR(150) NULL', 'seller_1_name' => 'VARCHAR(150) NULL',
            'winner_2_name' => 'VARCHAR(150) NULL', 'seller_2_name' => 'VARCHAR(150) NULL', 'winner_3_name' => 'VARCHAR(150) NULL',
            'seller_3_name' => 'VARCHAR(150) NULL', 'single_price' => 'DECIMAL(10,2) NULL', 'bundle_quantity' => 'INTEGER NULL',
            'bundle_price' => 'DECIMAL(10,2) NULL', 'called_numbers_json' => "TEXT DEFAULT '[]'", 'last_called_number' => 'INTEGER NULL',
            'last_called_at' => self::timeType($driver) . ' NULL', 'suggested_total_next' => 'DECIMAL(10,2) DEFAULT 0.00',
            'suggested_prize_1_next' => 'DECIMAL(10,2) DEFAULT 0.00', 'suggested_prize_2_next' => 'DECIMAL(10,2) DEFAULT 0.00',
        ];
        foreach ($roundColumns as $name => $type) self::ensureColumn($pdo, 'rounds', $name, $type);

        self::ensureColumn($pdo, 'events', 'modality', "VARCHAR(20) NOT NULL DEFAULT 'HYBRID'");
        self::ensureColumn($pdo, 'sales', 'event_id', 'INTEGER NULL');
        self::ensureColumn($pdo, 'sales', 'sale_type', "VARCHAR(20) NOT NULL DEFAULT 'PHYSICAL'");
        self::ensureColumn($pdo, 'sales', 'payment_method', "VARCHAR(20) DEFAULT 'CASH'");
        self::ensureColumn($pdo, 'cash_movements', 'payment_method', "VARCHAR(20) DEFAULT 'CASH'");
        self::ensureColumn($pdo, 'cash_movements', 'seller_id', 'INTEGER NULL');
        foreach (['counted_money','counted_pix','counted_debit','counted_credit','expected_money','expected_pix','expected_debit','expected_credit'] as $name) {
            self::ensureColumn($pdo, 'cash_closings', $name, 'DECIMAL(10,2) NULL');
        }

        // Corrige somente o legado padrão incorreto 10/25; preços personalizados permanecem preservados.
        try {
            $pdo->exec("UPDATE events SET single_price=2.00,bundle_qty=3,bundle_price=5.00,updated_at=CURRENT_TIMESTAMP WHERE single_price=10.00 AND bundle_qty=3 AND bundle_price=25.00");
        } catch (\Throwable $e) {}
    }

    private static function createIndexes(PDO $pdo, string $driver): void
    {
        $indexes = [
            "CREATE UNIQUE INDEX IF NOT EXISTS uq_draw_stones_number ON draw_stones(draw_id, number_value)",
            "CREATE UNIQUE INDEX IF NOT EXISTS uq_draw_stones_order ON draw_stones(draw_id, call_order)",
            "CREATE UNIQUE INDEX IF NOT EXISTS uq_ticket_game_state ON ticket_game_state(draw_id, ticket_id)",
            "CREATE UNIQUE INDEX IF NOT EXISTS uq_tickets_event_number ON tickets(event_id, ticket_number)",
            "CREATE UNIQUE INDEX IF NOT EXISTS uq_event_batch_sequence ON tickets(batch_id, sequence_number)",
            "CREATE INDEX IF NOT EXISTS idx_tickets_token ON tickets(secure_token)",
            "CREATE INDEX IF NOT EXISTS idx_tickets_event_status ON tickets(event_id, status)",
            "CREATE INDEX IF NOT EXISTS idx_ticket_numbers_ticket_value ON ticket_numbers(ticket_id, number_value)",
            "CREATE INDEX IF NOT EXISTS idx_winner_claims_draw_status ON winner_claims(draw_id, prize_id, status, call_order)",
            "CREATE INDEX IF NOT EXISTS idx_buyers_cpf ON buyers(cpf)",
            "CREATE INDEX IF NOT EXISTS idx_access_logs_buyer ON ticket_access_logs(buyer_id)",
        ];
        foreach ($indexes as $sql) {
            try { $pdo->exec($sql); } catch (\Throwable $e) {
                if ($driver === 'mysql') {
                    // MySQL antigo pode não aceitar IF NOT EXISTS em CREATE INDEX; duplicidade é segura para ignorar.
                    continue;
                }
            }
        }
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $defaults = [
            'single_quantity' => ['1','int'], 'single_price' => ['2.00','decimal'],
            'bundle_quantity' => ['3','int'], 'bundle_price' => ['5.00','decimal'],
            'prize_pool_percent' => ['50','int'], 'prize_1_percent' => ['65','int'], 'prize_2_percent' => ['35','int'],
            'prize_rounding' => ['10.00','decimal'], 'cash_tolerance' => ['0.01','decimal'],
            'locale' => ['pt-BR','string'], 'timezone' => ['America/Sao_Paulo','string'],
            'carry_previous_prize_suggestion' => ['true','bool'], 'system_title' => ['Show de Prêmios','string'],
            'pix_key' => ['','string'], 'pix_key_type' => ['CHAVE_ALEATORIA','string'], 'pix_receiver_name' => ['Show de Prêmios','string'],
            'pix_receiver_city' => ['Ubatuba','string'], 'pix_show_on_telao' => ['true','bool'],
            'pix_description' => ['Show de Prêmios','string'], 'pix_banner_title' => ['PAGUE COM PIX DIRETO DO SEU LUGAR','string'],
        ];
        $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key=?");
        $insert = $pdo->prepare("INSERT INTO settings (key,value,type) VALUES (?,?,?)");
        foreach ($defaults as $key => [$value,$type]) {
            $check->execute([$key]);
            if ((int)$check->fetchColumn() === 0) $insert->execute([$key,$value,$type]);
        }

        if ((int)$pdo->query("SELECT COUNT(*) FROM pricing_rules")->fetchColumn() === 0) {
            $stmt = $pdo->prepare("INSERT INTO pricing_rules (effective_from,single_quantity,single_price,bundle_quantity,bundle_price,active) VALUES (?,?,?,?,?,1)");
            $stmt->execute([date('Y-m-d'),1,2.00,3,5.00]);
        }

        if ((int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn() === 0) {
            $stmt = $pdo->prepare("INSERT INTO events (name,description,event_date,event_time,location,status,modality,ticket_prefix,max_tickets,single_price,bundle_qty,bundle_price,game_mode,center_free,card_format,tie_rule) VALUES (?,?,?,?,?,'ACTIVE','HYBRID','JDA',9999,2.00,3,5.00,'BINGO_75',1,'5x5','SPLIT')");
            $stmt->execute(['Show de Prêmios','Evento inicial',date('Y-m-d'),'20:00','Ubatuba/SP']);
            $eventId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO event_batches (event_id,batch_code,prefix,start_sequence,end_sequence,current_sequence,batch_type,total_generated) VALUES (?,'LOTE-1','JDA',1,9999,0,'EXCLUSIVE_RANDOM',0)")->execute([$eventId]);
        }
    }

    private static function migrateLegacyDrawHistory(PDO $pdo, string $driver): void
    {
        if (self::tableExists($pdo, 'called_numbers')) {
            try {
                $rows = $pdo->query("SELECT draw_id,number_value,letter,call_order,called_by,called_at FROM called_numbers ORDER BY draw_id,call_order")->fetchAll(PDO::FETCH_ASSOC);
                $exists = $pdo->prepare("SELECT 1 FROM draw_stones WHERE draw_id=? AND (number_value=? OR call_order=?) LIMIT 1");
                $insert = $pdo->prepare("INSERT INTO draw_stones (draw_id,number_value,letter,call_order,called_by,called_at) VALUES (?,?,?,?,?,?)");
                foreach ($rows as $row) {
                    $exists->execute([(int)$row['draw_id'],(int)$row['number_value'],(int)$row['call_order']]);
                    if (!$exists->fetchColumn()) {
                        $insert->execute([(int)$row['draw_id'],(int)$row['number_value'],(string)$row['letter'],(int)$row['call_order'],$row['called_by'] ?: null,$row['called_at'] ?: date('Y-m-d H:i:s')]);
                    }
                }
            } catch (\Throwable $e) {
                error_log('[Migration called_numbers] '.$e->getMessage());
            }
        }

        try {
            $draws = $pdo->query("SELECT id,round_id FROM draws WHERE round_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM draw_stones WHERE draw_id=?");
            $roundStmt = $pdo->prepare("SELECT called_numbers_json FROM rounds WHERE id=?");
            $insert = $pdo->prepare("INSERT INTO draw_stones (draw_id,number_value,letter,call_order,called_by,called_at) VALUES (?,?,?,?,NULL,CURRENT_TIMESTAMP)");
            foreach ($draws as $draw) {
                $countStmt->execute([(int)$draw['id']]);
                if ((int)$countStmt->fetchColumn() > 0) continue;
                $roundStmt->execute([(int)$draw['round_id']]);
                $json = $roundStmt->fetchColumn();
                $numbers = is_string($json) ? json_decode($json,true) : [];
                if (!is_array($numbers)) continue;
                $order = 0;
                foreach ($numbers as $number) {
                    $number=(int)$number;
                    if ($number<1 || $number>75) continue;
                    $order++;
                    $letter = $number<=15?'B':($number<=30?'I':($number<=45?'N':($number<=60?'G':'O')));
                    try { $insert->execute([(int)$draw['id'],$number,$letter,$order]); } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            error_log('[Migration rounds JSON] '.$e->getMessage());
        }
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Throwable $e) {
            // Coluna existente ou tabela legada ainda ausente: migração idempotente.
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function recordVersion(PDO $pdo, string $driver): void
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM migration_history WHERE version=?");
        $stmt->execute([self::VERSION]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO migration_history (version,applied_at) VALUES (?,CURRENT_TIMESTAMP)")->execute([self::VERSION]);
        }
    }
}
