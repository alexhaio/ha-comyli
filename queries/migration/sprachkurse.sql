SELECT
	AZLand,
	ext,
	Name,
	Vorname,
	Geburtsdatum,
	Geschlecht,
	StrasseNr,
	Adresse,
	SGBII,

	typ,
	
	Spachkurse
FROM `personen-sprachkurse-kurse`
LEFT JOIN [QRY]sprachkursePersonenMerged[/QRY] ON AZLand = ID AND ext = nurSprachkurse