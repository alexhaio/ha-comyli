SELECT
	IF(personen.Name <> '',personen.Name,`personen-sprachkurse-personen`.Name) AS Name,
	IF(personen.Vorname <> '',personen.Vorname,`personen-sprachkurse-personen`.Vorname) AS Vorname,
	einstufungstest, ergebnisKursart,
	CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',`personen-sprachkurse`.AZLand,'&amp;ext=',`personen-sprachkurse`.ext,''' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
FROM `personen-sprachkurse`
LEFT JOIN personen ON `personen-sprachkurse`.AZLand = personen.AZLand
LEFT JOIN `personen-sprachkurse-personen` ON `personen-sprachkurse`.AZLand = `personen-sprachkurse-personen`.ID
LEFT JOIN `personen-sprachkurse-kurse` ON `personen-sprachkurse-kurse`.`AZLand` = `personen-sprachkurse`.AZLand AND `personen-sprachkurse-kurse`.ext = `personen-sprachkurse`.`ext`
WHERE wiedervorlage != '' AND einstufungstest != '' AND `personen-sprachkurse-kurse`.typ IS NULL