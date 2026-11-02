SELECT Religion, COUNT(Religion) AS Anzahl, ort
FROM personen
LEFT JOIN unterkuenfte ON personen.unterkunftid = unterkuenfte.id
LEFT JOIN orte ON unterkuenfte.ort = orte.Name
WHERE statistischIrrelevant = 0 AND betreuerIn != 'keiner' AND personen.geloescht = 0
GROUP BY Religion, ort