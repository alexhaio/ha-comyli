";
$von = $_SESSION['von'];
$bis = $_SESSION['bis'];
$qry="
SELECT unterkunft, CONCAT(ROUND(SUM(anzahlStunden * stundenlohn),2),' €') AS personalwert, CONCAT(SUM(materialwert),' €') AS materialwert, CONCAT(ROUND(SUM(anzahlStunden * stundenlohn + materialwert),2),' €') AS gesamt
FROM `arbeitsauftrag`
WHERE datum BETWEEN '".$von."' AND '".$bis."'
GROUP BY unterkunft
ORDER BY unterkunft