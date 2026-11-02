	SELECT
		ID AS 'ID/AZLand',
		`personen-sprachkurse`.PkzBAMF,
		Name,
		Vorname,
		Geburtsdatum,
		CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',ID,'&amp;ext=1'' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
	FROM `personen-sprachkurse-personen`
	LEFT JOIN `personen-sprachkurse` ON ID=AZLand AND ext=1
UNION
	SELECT
		personen.AZLand,
		`personen-sprachkurse`.PkzBAMF,
		personen.Name,
		personen.Vorname,
		personen.Geburtsdatum,
		CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',personen.AZLand,'&amp;ext=0'' target=''_blank''>Zum Sprachkurs</a>')
	FROM personen
	LEFT JOIN `personen-sprachkurse` ON personen.AZLand=`personen-sprachkurse`.AZLand AND ext=0
ORDER BY Name