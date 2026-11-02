SELECT
	ID AS 'ID/AZLand',
	PkzBAMF,
	BerNr,
	Name,
	Vorname,
	Geburtsdatum,
	StrasseNr,
	Adresse,
	typ,
	CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',ID,'&amp;ext=1'' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
FROM [QRY]sprachkurseBasisdaten[/QRY]
ORDER BY Name
