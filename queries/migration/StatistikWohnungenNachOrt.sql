SELECT ort, typ, COUNT(id) AS Anzahl
FROM unterkuenfte
GROUP BY typ, ort