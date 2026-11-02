	SELECT
		`personen-sprachkurse-personen`.ID,
		'1' AS nurSprachkurse,
		`personen-sprachkurse-personen`.Name,
		`personen-sprachkurse-personen`.Vorname,
		`personen-sprachkurse-personen`.Geburtsdatum,
		`personen-sprachkurse-personen`.Geschlecht,
		`personen-sprachkurse-personen`.StrasseNr,
		CONCAT(`personen-sprachkurse-personen`.PLZ,' ',`personen-sprachkurse-personen`.Wohnort) AS Adresse,
		`personen-sprachkurse-personen`.SGBII,
		CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',ID,'&amp;ext=1'' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
	FROM `personen-sprachkurse-personen`
UNION
	SELECT
		personen.AZLand,
		'0' AS nurSprachkurse,
		personen.Name,
		personen.Vorname,
		personen.Geburtsdatum,
		personen.Geschlecht,
		CONCAT(unterkuenfte.strasse, ' ', unterkuenfte.nr) AS StrasseNR,
		orte.Adresse,
		personen.SGBII,
		CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',AZLand,'&amp;ext=0'' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
	FROM personen
	LEFT JOIN unterkuenfte ON unterkunftid = unterkuenfte.id
	LEFT JOIN orte ON unterkuenfte.ort = orte.Name