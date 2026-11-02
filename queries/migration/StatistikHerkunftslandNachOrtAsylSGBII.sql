SELECT Herkunftsland, COUNT(Herkunftsland) AS Anzahl, ort, SUM(Asyl) AS 'Asyl', SUM(`SGBII`) AS 'SGBII'
FROM personen
LEFT JOIN unterkuenfte ON personen.unterkunftid = unterkuenfte.id
LEFT JOIN orte ON unterkuenfte.ort = orte.Name
WHERE statistischIrrelevant = 0 AND betreuerIn != 'keiner' AND personen.geloescht = 0
GROUP BY Herkunftsland, ort