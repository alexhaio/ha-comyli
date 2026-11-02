SELECT Geschlecht,
	IF((DateDiff(NOW(),Geburtsdatum)/365.25) < 18,'Kinder','Erwachsene') AS Bezeichnung,
	COUNT(IF((DateDiff(NOW(),Geburtsdatum)/365.25) < 18,'Kinder','Erwachsene')) AS Anzahl
FROM personen
LEFT JOIN unterkuenfte ON personen.unterkunftid = unterkuenfte.id
LEFT JOIN orte ON unterkuenfte.ort = orte.Name
WHERE statistischIrrelevant = 0 AND betreuerIn != 'keiner' AND personen.geloescht = 0
GROUP BY Geschlecht, IF((DateDiff(NOW(),Geburtsdatum)/365.25)<18,'Kinder','Erwachsene')