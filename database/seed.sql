-- =============================================================================
-- SHOW DE PRÊMIOS — SEEDS INICIAIS
-- Marco Inicial Operacional: 06/09/2026
-- =============================================================================

-- Cores Oficiais de Cartelas
INSERT OR IGNORE INTO card_colors (name, bg_color, text_color, border_color) VALUES 
('Amarela', '#fef08a', '#854d0e', '#eab308'),
('Azul',    '#bae6fd', '#0369a1', '#0284c7'),
('Verde',   '#bbf7d0', '#15803d', '#22c55e'),
('Rosa',    '#fbcfe8', '#be185d', '#ec4899'),
('Laranja', '#fed7aa', '#c2410c', '#f97316'),
('Branca',  '#f8fafc', '#334155', '#94a3b8'),
('Lilás',   '#e9d5ff', '#7e22ce', '#a855f7'),
('Dourada', '#fef9c3', '#a16207', '#ca8a04');

-- Regra de Preço Inicial Vigente (Marco 06/09/2026)
INSERT OR IGNORE INTO pricing_rules (effective_from, single_quantity, single_price, bundle_quantity, bundle_price, active)
VALUES ('2026-09-06', 1, 2.00, 3, 5.00, 1);

-- Configurações Padrão do Sistema e do Evento
INSERT OR REPLACE INTO settings (key, value, type) VALUES
('system_title', 'Show de Prêmios', 'string'),
('prize_pool_percent', '50', 'string'),
('prize_1_percent', '65', 'string'),
('prize_2_percent', '35', 'string'),
('prize_rounding', '10.00', 'string'),
('cash_tolerance', '0.01', 'string'),
('pix_key', 'mskpoeira@gmail.com', 'string'),
('pix_key_type', 'EMAIL', 'string'),
('pix_receiver_name', 'Show de Prêmios Retiro', 'string'),
('pix_receiver_city', 'São Paulo', 'string'),
('pix_description', 'Show de Prêmios', 'string'),
('pix_banner_title', 'PAGUE COM PIX DIRETO DO SEU LUGAR', 'string'),
('pix_show_on_telao', 'true', 'string'),
('system_start_date', '2026-09-06', 'string');
