-- PrecogNovo - Schema MySQL
-- Executar este script para criar o banco de dados

CREATE DATABASE IF NOT EXISTS precognovo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE precognovo;

-- Tabela de administradores
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabela de clientes
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    company VARCHAR(100),
    token VARCHAR(64) UNIQUE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Sensores vinculados a clientes
CREATE TABLE IF NOT EXISTS sensors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    device_id VARCHAR(50) NOT NULL,
    location VARCHAR(100) DEFAULT 'CPD',
    label VARCHAR(100) DEFAULT 'Sensor',
    temp_min DECIMAL(5,2) DEFAULT 18.00,
    temp_max DECIMAL(5,2) DEFAULT 28.00,
    hum_min DECIMAL(5,2) DEFAULT 40.00,
    hum_max DECIMAL(5,2) DEFAULT 70.00,
    activation_date DATE DEFAULT NULL,
    alert_state_temp VARCHAR(20) DEFAULT 'normal',
    alert_state_hum VARCHAR(20) DEFAULT 'normal',
    last_status VARCHAR(20) DEFAULT 'offline',
    last_seen TIMESTAMP NULL DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_device (device_id)
) ENGINE=InnoDB;

-- Contatos para alertas
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Histórico de alertas gerados
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT NOT NULL,
    type ENUM('temp_high','temp_low','hum_high','hum_low') NOT NULL,
    message TEXT,
    value DECIMAL(5,2),
    threshold DECIMAL(5,2),
    webhook_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE CASCADE,
    INDEX idx_sensor_type_date (sensor_id, type, created_at)
) ENGINE=InnoDB;

-- Histórico de eventos do sistema
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT,
    type ENUM('info','warning','error') DEFAULT 'info',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE SET NULL,
    INDEX idx_sensor_date (sensor_id, created_at)
) ENGINE=InnoDB;

-- Preferências de Alerta por Contato
CREATE TABLE IF NOT EXISTS contact_alert_preferences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    contact_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    days_of_week VARCHAR(30) NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    min_interval INT NOT NULL DEFAULT 30,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_contact_alert (contact_id, alert_type)
) ENGINE=InnoDB;

-- Registro de envio de alertas (para throttling)
CREATE TABLE IF NOT EXISTS sent_alerts_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    contact_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    INDEX idx_contact_alert (contact_id, alert_type, sent_at)
) ENGINE=InnoDB;

-- Admin padrão (senha: admin123 - ALTERAR EM PRODUÇÃO!)
INSERT INTO admins (username, password_hash) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
