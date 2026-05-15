<?php
require_once __DIR__ . '/includes/db.php';

try {
    Database::execute("
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
    ");
    echo "Table contact_alert_preferences created.<br>";

    Database::execute("
        CREATE TABLE IF NOT EXISTS sent_alerts_log (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            contact_id INT NOT NULL,
            alert_type VARCHAR(50) NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
            INDEX idx_contact_alert (contact_id, alert_type, sent_at)
        ) ENGINE=InnoDB;
    ");
    echo "Table sent_alerts_log created.<br>";

    echo "<b>Banco de dados atualizado com sucesso!</b>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
