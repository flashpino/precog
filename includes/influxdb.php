<?php
/**
 * Cliente InfluxDB via API HTTP (Flux queries)
 */

require_once __DIR__ . '/../config.php';

class InfluxDB {

    /**
     * Executa uma query Flux no InfluxDB e retorna os dados parseados
     */
    public static function query($fluxQuery, $org = null, $token = null) {
        $org   = $org ?: INFLUXDB_ORG;
        $token = $token ?: INFLUXDB_TOKEN;
        
        $url = INFLUXDB_URL . '/api/v2/query?org=' . urlencode($org);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fluxQuery,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Token ' . $token,
                'Content-Type: application/vnd.flux',
                'Accept: application/csv',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("InfluxDB cURL error: " . $error);
            return [];
        }

        if ($httpCode !== 200) {
            error_log("InfluxDB HTTP error {$httpCode}: " . $response);
            return [];
        }

        return self::parseCSV($response);
    }

    /**
     * Retorna as últimas leituras de um sensor
     */
    public static function getLatestReading($deviceId, $bucket = null, $org = null, $token = null) {
        $bucket = $bucket ?: INFLUXDB_BUCKET;
        
        $flux = 'from(bucket: "' . $bucket . '")
            |> range(start: -7d)
            |> filter(fn: (r) => r["device_id"] == "' . $deviceId . '")
            |> filter(fn: (r) => r["_field"] == "temperatura" or r["_field"] == "umidade" or r["_field"] == "ip")
            |> tail(n: 3)';

        $data = self::query($flux, $org, $token);
        $result = ['temperature' => null, 'humidity' => null, 'time' => null, 'ip' => null, 'mac' => null];
        
        $now = time();
        $temps = [];
        $hums = [];

        foreach ($data as $row) {
            if (isset($row['_field']) && isset($row['_value']) && isset($row['_time'])) {
                $rowTime = strtotime($row['_time']);
                
                if ($row['_field'] === 'ip') {
                    $result['ip'] = $row['_value']; // Pega o último IP encontrado nos 3 registros
                } else {
                    // Ignora temperatura/umidade se for mais velha que 5 minutos
                    if (($now - $rowTime) <= 300) {
                        if ($row['_field'] === 'temperatura') {
                            $temps[] = floatval($row['_value']);
                        } elseif ($row['_field'] === 'umidade') {
                            $hums[] = floatval($row['_value']);
                        }
                        
                        // Atualiza o tempo principal do resultado com o mais recente
                        if ($result['time'] === null || $rowTime > strtotime($result['time'])) {
                            $result['time'] = $row['_time'];
                        }
                    }
                }
                
                // Extrair MAC da tag, se existir
                if (isset($row['mac'])) $result['mac'] = $row['mac'];
                elseif (isset($row['mac_address'])) $result['mac'] = $row['mac_address'];
            }
        }

        // Função para calcular mediana (filtra picos/glitches isolados)
        $calcMedian = function($arr) {
            if (empty($arr)) return null;
            sort($arr);
            $count = count($arr);
            $middle = floor(($count - 1) / 2);
            if ($count % 2 == 0) {
                return round(($arr[$middle] + $arr[$middle + 1]) / 2.0, 1);
            } else {
                return round($arr[$middle], 1);
            }
        };

        $result['temperature'] = $calcMedian($temps);
        $result['humidity'] = $calcMedian($hums);

        return $result;
    }

    /**
     * Retorna dados históricos para gráficos
     */
    public static function getHistory($deviceId, $range = '-24h', $field = 'temperature', $bucket = null, $org = null, $token = null) {
        $bucket = $bucket ?: INFLUXDB_BUCKET;
        $window = '5m';
        if ($range === '-7d') $window = '30m';
        if ($range === '-30d') $window = '2h';

        $fluxField = $field === 'temperature' ? 'temperatura' : ($field === 'humidity' ? 'umidade' : $field);
        
        $flux = 'from(bucket: "' . $bucket . '")
            |> range(start: ' . $range . ')
            |> filter(fn: (r) => r["device_id"] == "' . $deviceId . '")
            |> filter(fn: (r) => r["_field"] == "' . $fluxField . '")
            |> aggregateWindow(every: ' . $window . ', fn: mean, createEmpty: false)
            |> yield(name: "mean")';

        $data = self::query($flux, $org, $token);
        $result = [];

        foreach ($data as $row) {
            if (isset($row['_time']) && isset($row['_value'])) {
                $result[] = [
                    'time'  => $row['_time'],
                    'value' => round(floatval($row['_value']), 1),
                ];
            }
        }

        return $result;
    }

    /**
     * Verifica se o sensor está online (enviou dados nos últimos 2 minutos)
     */
    public static function isSensorOnline($deviceId, $bucket = null, $org = null, $token = null) {
        $bucket = $bucket ?: INFLUXDB_BUCKET;
        
        $flux = 'from(bucket: "' . $bucket . '")
            |> range(start: -2m)
            |> filter(fn: (r) => r["device_id"] == "' . $deviceId . '")
            |> count()';

        $data = self::query($flux, $org, $token);
        return !empty($data);
    }

    /**
     * Detecta reboot do ESP32 analisando gaps entre leituras consecutivas.
     * O ESP32 envia dados a cada ~11 segundos. Se houver um gap > $thresholdSeconds,
     * significa que o dispositivo reiniciou (queda de energia, reset, etc).
     * 
     * @param int $thresholdSeconds Gap mínimo em segundos para considerar reboot (padrão: 30s)
     * @return array|null ['gap_seconds' => int, 'time_before' => string, 'time_after' => string] ou null
     */
    public static function detectReboot($deviceId, $bucket = null, $org = null, $token = null, $thresholdSeconds = 30) {
        $bucket = $bucket ?: INFLUXDB_BUCKET;
        
        // Busca leituras dos últimos 10 minutos e calcula o tempo entre cada uma
        // Se houver gap > threshold, retorna o ponto logo após o gap (= momento do reboot)
        $flux = 'from(bucket: "' . $bucket . '")
            |> range(start: -10m)
            |> filter(fn: (r) => r._measurement == "ambiente")
            |> filter(fn: (r) => r["device_id"] == "' . $deviceId . '")
            |> filter(fn: (r) => r._field == "temperatura")
            |> elapsed(unit: 1s)
            |> filter(fn: (r) => r.elapsed > ' . $thresholdSeconds . ')
            |> last()';

        $data = self::query($flux, $org, $token);
        
        if (!empty($data)) {
            $row = $data[0];
            $gapSeconds = isset($row['elapsed']) ? (int)$row['elapsed'] : 0;
            return [
                'gap_seconds' => $gapSeconds,
                'time_after'  => $row['_time'],  // Momento que o ESP32 voltou
            ];
        }

        return null;
    }

    /**
     * Parseia o CSV retornado pela API do InfluxDB
     */
    private static function parseCSV($csv) {
        $lines = explode("\n", trim($csv));
        if (count($lines) < 2) return [];

        $results = [];
        $headers = [];

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;

            $cols = str_getcsv($line);

            // Se for uma linha de cabeçalho (segunda coluna é 'result')
            if (count($cols) > 1 && $cols[1] === 'result') {
                $headers = $cols;
                continue;
            }

            if (!empty($headers) && count($cols) === count($headers)) {
                $row = array_combine($headers, $cols);
                // Ignora a coluna vazia de resultado e tabela
                unset($row[''], $row['result'], $row['table']);
                $results[] = $row;
            }
        }

        return $results;
    }
}
