SELECT
	personen.AZLand,
	personen.Name,
	personen.Vorname,
	personen.Geburtsdatum,
	personen.Geschlecht,
	unterkuenfte.strasse,
	unterkuenfte.nr,
	orte.Adresse,
	history.EinzugAm
	
FROM personen
LEFT JOIN (
	SELECT
		Name,
		Vorname,
		Geburtsdatum,
		unterkunftid,
		CONCAT(EinzugAm, ' (Aktuell)') AS EinzugAm
	FROM personen
	
	UNION
	
	SELECT
		Name,
		Vorname,
		Geburtsdatum,
		Unterkunft,
		Einzug
	FROM `personen-unterkunft-history`
) AS history ON personen.Name = history.Name AND personen.Vorname = history.Vorname AND personen.Geburtsdatum = history.Geburtsdatum
LEFT JOIN unterkuenfte ON history.unterkunftid = unterkuenfte.id
LEFT JOIN orte ON unterkuenfte.ort = orte.Name
WHERE strasse IS NOT NULL
GROUP BY personen.Name, personen.Vorname, personen.Geburtsdatum, history.unterkunftid
ORDER BY personen.Name, personen.Vorname, personen.Geburtsdatum, history.EinzugAm