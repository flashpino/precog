# PrecogNovo - Sistema de Monitoramento SaaS

Sistema completo em PHP para monitoramento de sensores ESP32 + DHT22 com armazenamento no InfluxDB e gestão multi-cliente no MySQL.

## 🚀 Instalação e Configuração

### 1. Banco de Dados (MySQL)
1. Crie um banco de dados chamado `precognovo`.
2. Importe o arquivo `sql/schema.sql`.
3. O usuário padrão é `admin` e a senha é `admin123`. **Altere imediatamente após o login!**

### 2. Configurações
Abra o arquivo `config.php` e preencha as informações:
- **DB_***: Suas credenciais do MySQL local/servidor.
- **INFLUXDB_***: Token, Bucket e Org que você forneceu do ESP32.
- **N8N_WEBHOOK_URL**: A URL do seu webhook no n8n.

### 3. Servidor Web
- Certifique-se de que a extensão `php-curl` e `php-pdo_mysql` estejam habilitadas.
- O projeto deve ser acessível via `http://localhost/precognovo` ou similar.

## 📱 Acesso

### Painel Administrativo
- URL: `http://seu-dominio/admin`
- Funções: Cadastrar clientes, associar sensores (device_id), configurar limites de temperatura/umidade e contatos para alertas.

### Dashboard do Cliente
- URL: `http://seu-dominio/client/dashboard.php?token=TOKEN_GERADO`
- O token é gerado automaticamente ao cadastrar o cliente no painel admin.
- O cliente tem acesso **apenas visual** aos seus dados.

## 🔔 Sistema de Alertas
- O dashboard verifica os limites a cada 30 segundos.
- Se um limite for atingido, um alerta é gravado no banco e disparado para o **n8n**.
- O n8n recebe o payload JSON com o nome do cliente, valor lido, limite e a lista de telefones dos contatos para enviar via WhatsApp/SMS.

## 🛠 Tecnologias
- PHP 7.4+
- MySQL 5.7+ / MariaDB
- InfluxDB (Flux API)
- Chart.js (Gráficos)
- Vanilla CSS (Design Dark Premium)
