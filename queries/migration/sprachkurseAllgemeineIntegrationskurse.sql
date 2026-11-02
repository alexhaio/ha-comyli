SELECT
	Anrede,
	Name,
	Vorname,
	Geburtsdatum,
	IF(p.StrasseNr <> '', p.StrasseNr, sprachkurseBasisdaten.StrasseNr) AS StrasseNr,
	IF(p.StrasseNr <> '', CONCAT(p.PLZ, ' ', p.Wohnort), sprachkurseBasisdaten.Adresse) AS Adresse,
	Kontaktinfo,
	email,
	PkzBAMF,
	PkzWiederholer,
	modul,
	sachbearbeiter,
	SGBII,
	ablageFach,
	'' AS EGV,
	bemerkung,
	Sprachkurse
FROM [QRY]sprachkurseBasisdaten[/QRY]
LEFT JOIN `personen-sprachkurse-ext-adressen` AS p ON p.ID = sprachkurseBasisdaten.ID AND sprachkurseBasisdaten.ext = 0
GROUP BY sprachkurseBasisdaten.ID
ORDER BY Name
