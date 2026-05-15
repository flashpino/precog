# PrecogSystem

Sistema de monitoramento ambiental de alta precisão (Temperatura e Umidade) utilizando ESP32, DHT22 e InfluxDB v2.

## Estrutura do Projeto

- **/api**: Endpoints PHP para recepção de alertas e processamento de resumos.
- **/admin**: Painel administrativo para gerenciamento de clientes, sensores e diagnósticos.
- **/firmware**: Código Arduino (ESP32) com suporte a Watchdog, NTP, WiFiManager e tratamento de erros.
- **/includes**: Bibliotecas e funções principais do sistema PHP.
- **/sql**: Esquemas de banco de dados e migrações.

## Recursos Principais

- **Monitoramento em Tempo Real**: Envio de dados via Line Protocol para InfluxDB.
- **Resiliência**: Watchdog de hardware e software no ESP32 para auto-recuperação.
- **Alertas Inteligentes**: Sistema de thresholds com notificações via webhook (n8n).
- **Interface Premium**: Dashboard moderno com visualização tática de dados.

## Configuração

1. Configure as credenciais no arquivo `config.php`.
2. Carregue o firmware na pasta `/firmware` no seu ESP32.
3. Configure o sensor via portal WiFi ou interface administrativa.
