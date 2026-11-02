SELECT ort, SUM(Belegung) AS belegt, SUM(MaxBelegung) AS MaxBeleg, (SUM(MaxBelegung)-SUM(Belegung)) AS freiePlaetze, typ
FROM [QRY]BelegungenGebaeude[/QRY]
GROUP BY ort,typ