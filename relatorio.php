<?php
date_default_timezone_set('America/Sao_Paulo');

$db_host = '212.1.210.112';
$db_user = 'asfindbr_esp32';
$db_pass = 'nminfo*4990';
$db_name = 'asfindbr_esp32';
$periodo_inicio = '2026-03-01 00:00:00';
$periodo_fim    = '2026-03-31 23:59:00';
$periodo_label  = 'Março/2026';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<b>Erro de conexao:</b> " . htmlspecialchars($e->getMessage()));
}

$sql = "
SELECT
    DATE_FORMAT(data_leitura,'%d/%m/%Y') AS data,
    DATE_FORMAT(data_leitura,'%d')       AS dia,
    sensor,
    ROUND(MIN(temp),1)           AS minima_raw,
    ROUND(MAX(temp),1)           AS maxima_raw,
    ROUND(AVG(temp),1)           AS media_raw,
    ROUND(MAX(temp)-MIN(temp),1) AS amplitude_raw,
    CONCAT(ROUND(MIN(temp),1),'° C')           AS minima,
    CONCAT(ROUND(MAX(temp),1),'° C')           AS maxima,
    CONCAT(ROUND(AVG(temp),1),'° C')           AS media,
    CONCAT(ROUND(MAX(temp)-MIN(temp),1),'° C') AS amplitude,
    (SELECT TIME_FORMAT(data_leitura,'%H:%i') FROM sensorData t2
     WHERE t2.sensor=t1.sensor AND DATE(t2.data_leitura)=DATE(t1.data_leitura)
       AND t2.temp=MIN(t1.temp) AND t2.temp>15 LIMIT 1) AS hora_min,
    (SELECT TIME_FORMAT(data_leitura,'%H:%i') FROM sensorData t2
     WHERE t2.sensor=t1.sensor AND DATE(t2.data_leitura)=DATE(t1.data_leitura)
       AND t2.temp=MAX(t1.temp) AND t2.temp>15 LIMIT 1) AS hora_max,
    CASE
        WHEN MAX(temp)-MIN(temp)<=2 THEN 'Estavel'
        WHEN MAX(temp)-MIN(temp)<=5 THEN 'Moderada'
        ELSE 'Instavel'
    END AS condicao
FROM sensorData t1
WHERE data_leitura>=:ini AND data_leitura<:fim AND temp>15
GROUP BY DATE(data_leitura),sensor
ORDER BY data_leitura ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':ini'=>$periodo_inicio, ':fim'=>$periodo_fim]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($dados)) die('<b>Nenhum dado encontrado.</b>');

$cnt_normal = 0;
$cnt_alerta = 0;

$jLbl=[];$jLblDia=[];$jMin=[];$jMax=[];$jMed=[];$jAmp=[];$jCor=[];

foreach ($dados as $r) {
    if ((float)$r['maxima_raw'] > 24) $cnt_alerta++;
    else $cnt_normal++;

    $jLbl[]    = $r['data'];
    $jLblDia[] = $r['dia'];
    $jMin[]    = (float)$r['minima_raw'];
    $jMax[]    = (float)$r['maxima_raw'];
    $jMed[]    = (float)$r['media_raw'];
    $jAmp[]    = (float)$r['amplitude_raw'];

    if ($r['condicao']=='Moderada')       $jCor[] = '#f39c12';
    elseif ($r['condicao']=='Instavel')   $jCor[] = '#c0392b';
    else                                  $jCor[] = '#27ae60';
}

$total = count($dados);
$pN = $total ? round($cnt_normal/$total*100) : 0;
$pA = $total ? round($cnt_alerta/$total*100)  : 0;

$rows = '';
foreach ($dados as $r) {
    $is_alerta = ((float)$r['maxima_raw'] > 24);

    if ($is_alerta) {
        $bg='#fdecea'; $bb='#c0392b'; $bt='#fff'; $lbl_txt='Alerta';
    } else {
        $bg='#eafaf1'; $bb='#27ae60'; $bt='#fff'; $lbl_txt='Normal';
    }

    $rows .= "<tr style=\"background:{$bg}\">
      <td class=\"c\">{$r['data']}</td><td class=\"c\"><b>{$r['sensor']}</b></td>
      <td class=\"c\">{$r['minima']}</td><td class=\"c\">{$r['hora_min']}</td>
      <td class=\"c\">{$r['maxima']}</td><td class=\"c\">{$r['hora_max']}</td>
      <td class=\"c\">{$r['media']}</td><td class=\"c\">{$r['amplitude']}</td>
      <td class=\"c\"><span style=\"background:{$bb};color:{$bt};padding:3px 9px;border-radius:12px;font-size:10px;font-weight:700\">{$lbl_txt}</span></td>
    </tr>\n";
}

// --- Logos embutidos em base64 (coloque logo1.png e logo2.png na mesma pasta) ---
function imgBase64($path) {
    if (!file_exists($path)) return '';
    $mime = mime_content_type($path);
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}
$logo1 = imgBase64(__DIR__ . '/gsf.png');
$logo2 = imgBase64(__DIR__ . '/precog.png');
$img1  = $logo1
    ? "<img src=\"{$logo1}\" alt=\"Logo 1\" style=\"height:48px;object-fit:contain;\">"
    : "<div style=\"width:48px\"></div>";
$img2  = $logo2
    ? "<img src=\"{$logo2}\" alt=\"Logo 2\" style=\"height:28px;object-fit:contain;\">"
    : "<div style=\"width:28px\"></div>";

$jLblJ    = json_encode($jLbl);
$jLblDiaJ = json_encode($jLblDia);
$jMinJ    = json_encode($jMin);
$jMaxJ    = json_encode($jMax);
$jMedJ    = json_encode($jMed);
$jAmpJ    = json_encode($jAmp);
$jCorJ    = json_encode($jCor);
$now      = date('d/m/Y H:i');
$yr       = date('Y');

header('Content-Type: text/html; charset=UTF-8');
echo "<!DOCTYPE html>\n<html lang=\"pt-BR\">\n<head>\n<meta charset=\"UTF-8\">\n";
echo "<title>Relatório de Monitoramento de Temperatura Datacenter - {$periodo_label}</title>\n";
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;font-size:12px;color:#2c3e50;margin:0;background:#eef1f5}
.page{width:1100px;margin:20px auto;background:#fff;box-shadow:0 2px 24px #0003;border-radius:8px;overflow:hidden}
.hdr{background:linear-gradient(135deg,#1a252f,#2c3e50);color:#fff;padding:22px 28px}
.hdr h1{margin:0 0 5px;font-size:21px}.hdr p{margin:2px 0;font-size:11px;color:#bdc3c7}
.sec{padding:18px 28px}
.sec-t{font-size:14px;font-weight:700;color:#2980b9;border-bottom:2px solid #2980b9;padding-bottom:5px;margin:0 0 14px}
.cards{display:flex;gap:12px}
.card{flex:1;border-radius:8px;border:1px solid #e0e0e0;padding:14px;text-align:center;background:#fafafa}
.card .v{font-size:26px;font-weight:800;margin-bottom:2px}.card .l{font-size:10px;color:#7f8c8d}
.az{border-top:4px solid #2980b9}.az .v{color:#2980b9}
.vd{border-top:4px solid #27ae60}.vd .v{color:#27ae60}
.vm{border-top:4px solid #c0392b}.vm .v{color:#c0392b}
.cbox{border:1px solid #e8e8e8;border-radius:8px;padding:12px;background:#fafafa}
.cap{font-size:10px;color:#999;text-align:center;margin-top:6px;font-style:italic}
table{width:100%;border-collapse:collapse;font-size:11px}
th{background:#2c3e50;color:#fff;padding:8px 7px;text-align:left;white-space:nowrap}
td{padding:7px;border-bottom:1px solid #e8e8e8;white-space:nowrap}
.c{text-align:center}.g{color:#aaa}
.foot{text-align:center;font-size:10px;color:#aaa;padding:14px 28px;border-top:1px solid #eee}
.btn{position:fixed;top:18px;right:24px;z-index:999;background:#2980b9;color:#fff;border:none;
     border-radius:6px;padding:10px 20px;font-size:13px;cursor:pointer;box-shadow:0 2px 8px #0003}
.btn:hover{background:#1f6391}
@media print{
  body{background:#fff}
  .page{width:100%;box-shadow:none;border-radius:0;margin:0}
  .btn{display:none}
  .sec{padding:12px 16px}
  @page{size:A4 landscape;margin:10mm 8mm}
}
</style>
</head><body>
<?php
echo "<button class=\"btn\" onclick=\"gerarPDF()\">&#8681; Baixar PDF</button>\n";

echo "<div class=\"page\">\n";

// CABEÇALHO COM LOGOS
echo "<div class=\"hdr\" style=\"display:flex;align-items:center;justify-content:space-between;gap:16px\">";
echo $img1;
echo "<div style=\"flex:1;text-align:center\">";
echo "<h1 style=\"margin:0 0 5px\">Relatório de Monitoramento de Temperatura Datacenter</h1>";
echo "<p style=\"margin:2px 0;font-size:11px;color:#bdc3c7\">Período: <b>{$periodo_label}</b> &nbsp;|&nbsp; Gerado em: {$now}</p>";
echo "<p style=\"margin:2px 0;font-size:11px;color:#bdc3c7\">Relatório Gerado automaticamente por IA</p>";
echo "</div>";
echo $img2;
echo "</div>\n";

// RESUMO
echo "<div class=\"sec\" style=\"padding-bottom:8px\">\n";
echo "<p class=\"sec-t\">Resumo do Período</p>\n";
echo "<div class=\"cards\">";
echo "<div class=\"card az\"><div class=\"v\">{$total}</div><div class=\"l\">Dias analisados</div></div>";
echo "<div class=\"card vd\"><div class=\"v\">{$cnt_normal} <small style=\"font-size:13px\">({$pN}%)</small></div><div class=\"l\">Dias Normais (max &le; 24&deg;C)</div></div>";
echo "<div class=\"card vm\"><div class=\"v\">{$cnt_alerta} <small style=\"font-size:13px\">({$pA}%)</small></div><div class=\"l\">Dias em Alerta (max &gt; 24&deg;C)</div></div>";
echo "</div></div>\n";

// GRÁFICO DE LINHA
echo "<div class=\"sec\" style=\"padding-top:10px;padding-bottom:10px\">\n";
echo "<p class=\"sec-t\">Evolução das Temperaturas</p>\n";
echo "<div class=\"cbox\" style=\"width:100%\"><canvas id=\"cL\" height=\"100\"></canvas>";
echo "<p class=\"cap\">Temperatura Miníma, Média e Máxima diária</p></div></div>\n";

// GRÁFICO DE AMPLITUDE
echo "<div class=\"sec\" style=\"padding-top:10px;padding-bottom:10px\">\n";
echo "<p class=\"sec-t\">Amplitude Térmica Diária</p>\n";
echo "<div class=\"cbox\" style=\"width:100%\"><canvas id=\"cA\" height=\"90\"></canvas>";
echo "<p class=\"cap\">Diretrizes ASHRAE (American Society of Heating, Refrigerating and Air-Conditioning Engineers) 0C a 2C Estável, 2C a 5C Moderada, acima de 5C Instável</p></div></div>\n";

// TABELA
echo "<div class=\"sec\" style=\"padding-top:10px\">\n";
echo "<p class=\"sec-t\">Detalhamento</p>\n";
echo "<table><tr><th class=\"c\">Data</th><th class=\"c\">Sensor</th>";
echo "<th class=\"c\">Miníma</th><th class=\"c\">Hora Min</th>";
echo "<th class=\"c\">Máxima</th><th class=\"c\">Hora Max</th>";
echo "<th class=\"c\">Média</th><th class=\"c\">Amplitude</th><th class=\"c\">Condição</th></tr>\n";
echo $rows;
echo "</table></div>\n";
echo "<div class=\"foot\">Relatório de Temperatura | {$periodo_label} | www.precogsystem.com.br</div>\n";
echo "</div>\n";

echo "<script>\n";
echo "const labels={$jLblJ};\n";
echo "const labelsDia={$jLblDiaJ};\n";
echo "const dMin={$jMinJ};\n";
echo "const dMax={$jMaxJ};\n";
echo "const dMed={$jMedJ};\n";
echo "const dAmp={$jAmpJ};\n";
echo "const dCor={$jCorJ};\n";
?>
async function gerarPDF() {
    const btn = document.querySelector('.btn');
    btn.textContent = 'Gerando...';
    btn.disabled = true;

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    const pdfW = pdf.internal.pageSize.getWidth();
    const pdfH = pdf.internal.pageSize.getHeight();
    const margin = 8;
    const usableW = pdfW - margin * 2;
    const usableH = pdfH - margin * 2;

    const blocos = document.querySelectorAll('.hdr, .sec');
    let currentPageHeight = margin;
    let primeiraBloco = true;

    for (let i = 0; i < blocos.length; i++) {
        const bloco = blocos[i];

        const canvas = await html2canvas(bloco, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        });

        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const imgW = usableW;
        const imgH = (canvas.height * imgW) / canvas.width;

        if (!primeiraBloco && currentPageHeight + imgH > pdfH - margin) {
            pdf.addPage();
            currentPageHeight = margin;
        }

        if (imgH > usableH) {
            const scaledH = usableH;
            const scaledW = (canvas.width * scaledH) / canvas.height;
            const offsetX = margin + (usableW - scaledW) / 2;
            pdf.addImage(imgData, 'JPEG', offsetX, currentPageHeight, scaledW, scaledH);
            currentPageHeight += scaledH + 4;
        } else {
            pdf.addImage(imgData, 'JPEG', margin, currentPageHeight, imgW, imgH);
            currentPageHeight += imgH + 4;
        }

        primeiraBloco = false;
    }

    pdf.save(`Relatorio_Temperatura_<?= $periodo_label ?>.pdf`);
    btn.textContent = '⬇ Baixar PDF';
    btn.disabled = false;
}

const gc='rgba(0,0,0,0.07)';const f={size:11};

new Chart(document.getElementById('cL'),{type:'line',data:{labels,datasets:[
  {label:'Máxima',data:dMax,borderColor:'#c0392b',backgroundColor:'rgba(192,57,43,0.07)',pointRadius:2,fill:false,tension:0.3,borderWidth:2},
  {label:'Média', data:dMed,borderColor:'#27ae60',backgroundColor:'rgba(39,174,96,0.07)', pointRadius:2,fill:false,tension:0.3,borderWidth:2},
  {label:'Miníma',data:dMin,borderColor:'#2980b9',backgroundColor:'rgba(41,128,185,0.07)',pointRadius:2,fill:false,tension:0.3,borderWidth:2}
]},options:{responsive:true,
  plugins:{legend:{position:'top',labels:{font:f,boxWidth:13}},
           tooltip:{callbacks:{label:c=>` ${c.dataset.label}: ${c.parsed.y}C`}}},
  scales:{x:{ticks:{font:f,maxRotation:45,autoSkip:true,maxTicksLimit:14,
                    callback:(val,i)=>labelsDia[i]},
             grid:{color:gc}},
          y:{ticks:{font:f,callback:v=>v+'C'},grid:{color:gc}}}}});

new Chart(document.getElementById('cA'),{type:'bar',
  data:{labels:labelsDia,datasets:[{label:'Amplitude',data:dAmp,backgroundColor:dCor,borderColor:dCor,borderWidth:1,borderRadius:3}]},
  options:{responsive:true,
    plugins:{legend:{display:false},tooltip:{callbacks:{
      title:t=>labels[t[0].dataIndex],
      label:c=>` Amplitude: ${c.parsed.y}C`
    }}},
    scales:{x:{ticks:{font:f,maxRotation:45,autoSkip:true,maxTicksLimit:31},grid:{color:gc}},
            y:{min:0,ticks:{font:f,callback:v=>v+'C'},grid:{color:gc}}}},
  plugins:[{afterDraw(ch){const{ctx,chartArea:{left,right},scales:{y}}=ch;
    [[2,'#27ae60','2C'],[5,'#c0392b','5C']].forEach(([v,cor,txt])=>{
      const yp=y.getPixelForValue(v);ctx.save();ctx.beginPath();ctx.setLineDash([6,4]);
      ctx.strokeStyle=cor;ctx.lineWidth=1.5;ctx.moveTo(left,yp);ctx.lineTo(right,yp);ctx.stroke();
      ctx.fillStyle=cor;ctx.font='10px Arial';ctx.textAlign='right';
      ctx.fillText(txt,right,yp-4);ctx.restore();});}}]});
<?php echo "</script>\n</body>\n</html>"; ?>
