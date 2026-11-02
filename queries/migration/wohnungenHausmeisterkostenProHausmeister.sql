";
$von = $_SESSION['von'];
$bis = $_SESSION['bis'];
$qry="
SELECT hausmeister, SUM(arbeitsstunden) AS arbeitsstunden
FROM `hausmeister_arbeitsauftrag_zuordnung`
WHERE arbeitsauftrag BETWEEN '".$von."' AND '".$bis."'
GROUP BY hausmeister
ORDER BY hausmeister