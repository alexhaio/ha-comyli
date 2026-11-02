SELECT
	IF(personen.Name <> '',personen.Name,`personen-sprachkurse-personen`.Name) AS Name,
	IF(personen.Vorname <> '',personen.Vorname,`personen-sprachkurse-personen`.Vorname) AS Vorname,
	`300hWiederholer`,
	CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',`personen-sprachkurse-kurse`.AZLand,'&amp;ext=',ext,''' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
FROM `personen-sprachkurse-kurse`
LEFT JOIN personen ON `personen-sprachkurse-kurse`.AZLand = personen.AZLand AND ext = '0'
LEFT JOIN `personen-sprachkurse-personen` ON `personen-sprachkurse-kurse`.AZLand = `personen-sprachkurse-personen`.ID AND ext = '1'
WHERE `300hWiederholer` <> ''