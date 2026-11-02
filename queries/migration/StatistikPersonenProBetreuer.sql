SELECT betreuerIn, COUNT(Name) AS Anzahl, SUM(HHVorstand) AS Familien
FROM (
	SELECT personen.Name, betreuerIn, IF(Familienpos = 'Haushaltsvorstand',1,0) AS HHVorstand
	FROM personen
	LEFT JOIN unterkuenfte ON personen.unterkunftid = unterkuenfte.id
	LEFT JOIN orte ON unterkuenfte.ort = orte.Name
	WHERE statistischIrrelevant = 0 AND betreuerIn != 'keiner' AND personen.geloescht = 0
) AS PersonenMitOrt
GROUP BY betreuerIn