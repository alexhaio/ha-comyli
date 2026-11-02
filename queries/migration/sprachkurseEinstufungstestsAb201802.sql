SELECT
	IF(personen.Name IS NOT NULL,personen.Name,`personen-sprachkurse-personen`.Name) AS Name,
	IF(personen.Vorname IS NOT NULL,personen.Vorname,`personen-sprachkurse-personen`.Vorname) AS Vorname,
    IF(personen.Geburtsdatum IS NOT NULL,personen.Geburtsdatum,`personen-sprachkurse-personen`.Geburtsdatum) AS Geburtsdatum,
    personen.Geschlecht,
	IF(`personen-sprachkurse`.`ext` = 0,
		IF(p.StrasseNr <> '', p.StrasseNr, CONCAT(strasse, ' ', nr, ' ', etage, ' ', lage)),
		`personen-sprachkurse-personen`.StrasseNr
	) AS StrasseNr,
	IF(`personen-sprachkurse`.`ext` = 0,
		IF(p.StrasseNr <> '', CONCAT(p.PLZ, ' ', p.Wohnort), orte.Adresse),
		CONCAT(`personen-sprachkurse-personen`.PLZ,' ',`personen-sprachkurse-personen`.Wohnort)
	) AS Adresse,
	`personen-sprachkurse`.`einstufungstest`,
    `personen-sprachkurse`.`ergebnisKursart`,
    `personen-sprachkurse`.`modul`,
    `personen-sprachkurse`.`sachbearbeiter`,
	CONCAT('<a href=''./?f=sprachkurse&amp;AZLand=',`personen-sprachkurse`.AZLand,'&amp;ext=',`personen-sprachkurse`.ext,''' target=''_blank''>Zum Sprachkurs</a>') AS Spachkurse
FROM `personen-sprachkurse`
LEFT JOIN personen ON `personen-sprachkurse`.`AZLand` = personen.AZLand AND `personen-sprachkurse`.`ext` = 0
LEFT JOIN `personen-sprachkurse-personen` ON `personen-sprachkurse`.`AZLand` = `personen-sprachkurse-personen`.`ID` AND `personen-sprachkurse`.`ext` = 1
LEFT JOIN `personen-sprachkurse-kurse` ON `personen-sprachkurse-kurse`.`AZLand` = `personen-sprachkurse`.AZLand AND `personen-sprachkurse-kurse`.ext = `personen-sprachkurse`.`ext`
LEFT JOIN `personen-sprachkurse-ext-adressen` AS p ON p.ID = `personen-sprachkurse`.AZLand AND `personen-sprachkurse`.`ext` = 0
LEFT JOIN unterkuenfte ON unterkuenfte.id=personen.unterkunftid
LEFT JOIN orte ON unterkuenfte.ort = orte.Name
WHERE einstufungstest >= '2018-02-01' /* AND (`personen-sprachkurse-kurse`.typ IS NULL /*OR `personen-sprachkurse-kurse`.von > NOW()  )*/
GROUP BY `personen-sprachkurse`.AZLand, `personen-sprachkurse`.`ext`
ORDER BY einstufungstest