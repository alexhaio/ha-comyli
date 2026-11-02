SELECT
	Anrede,
	Name,
	Vorname,
	Geburtsdatum,
	StrasseNr,
	Adresse,
	Kontaktinfo,
	PkzBAMF,
	berechtigung,
	verpflichtung,
	ergebnisKursart,
	einstufungstest,
	sachbearbeiter,
	SGBII,
	ablageFach,
	'' AS EGV,
	bemerkung,
	Sprachkurse
FROM [QRY]sprachkurseBasisdaten[/QRY]
GROUP BY ID
ORDER BY Name
