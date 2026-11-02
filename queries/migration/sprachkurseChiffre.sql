SELECT
	IF(personen.Name <> '',personen.Name,`personen-sprachkurse-personen`.Name) AS Name,
	IF(personen.Vorname <> '',personen.Vorname,`personen-sprachkurse-personen`.Vorname) AS Vorname,
	chiffre,
	sachbearbeiter,
	von,
	bis,
	bemerkungK,
	ergebnis,
	weiteresVorgehen,
	CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',`personen-sprachkurse-kurse`.AZLand,'&amp;ext=',`personen-sprachkurse-kurse`.ext,''' target=''_blank''>Zum Sprachkurs</a>') AS Sprachkurse
FROM `personen-sprachkurse-kurse`
LEFT JOIN personen ON `personen-sprachkurse-kurse`.AZLand = personen.AZLand AND `personen-sprachkurse-kurse`.ext = '0'
LEFT JOIN `personen-sprachkurse-personen` ON `personen-sprachkurse-kurse`.AZLand = `personen-sprachkurse-personen`.ID AND `personen-sprachkurse-kurse`.ext = '1'
LEFT JOIN `personen-sprachkurse` ON `personen-sprachkurse`.AZLand = `personen-sprachkurse-kurse`.AZLand AND `personen-sprachkurse`.ext = `personen-sprachkurse-kurse`.ext
WHERE `chiffre` <> ''