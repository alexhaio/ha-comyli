SELECT
	IF(personen.Name <> '',personen.Name,`personen-sprachkurse-personen`.Name) AS Name,
	IF(personen.Vorname <> '',personen.Vorname,`personen-sprachkurse-personen`.Vorname) AS Vorname,
	verpflichtung,
	CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',`personen-sprachkurse`.AZLand,'&amp;ext=',ext,''' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
FROM `personen-sprachkurse`
LEFT JOIN personen ON `personen-sprachkurse`.AZLand = personen.AZLand
LEFT JOIN `personen-sprachkurse-personen` ON `personen-sprachkurse`.AZLand = `personen-sprachkurse-personen`.ID
WHERE wiedervorlage != '' AND verpflichtung != ''