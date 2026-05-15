-- Executar no banco de dados para adicionar as colunas faltantes na tabela sensors
-- Isso resolve o problema de falha ao atualizar o estado de alerta (flapping e normalização) e status online/offline

ALTER TABLE sensors 
    ADD COLUMN IF NOT EXISTS alert_state_temp VARCHAR(20) DEFAULT 'normal',
    ADD COLUMN IF NOT EXISTS alert_state_hum VARCHAR(20) DEFAULT 'normal',
    ADD COLUMN IF NOT EXISTS last_status VARCHAR(20) DEFAULT 'offline',
    ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP NULL DEFAULT NULL;
