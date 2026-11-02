SELECT
	unterkuenfte.betreuerIn,
	personen.Name,
	personen.Vorname,
	personen.Geburtsdatum,
	personen.basisdatenaenderung,
	personen.adminpruefung,
	personen.lastChanged,
	personen.Familienpos,
	a.AZLand
FROM personen
LEFT JOIN personen AS a ON personen.Familienzuordnung = a.AZLand
LEFT JOIN unterkuenfte ON unterkuenfte.id=personen.unterkunftid
WHERE
	(
		/* Geburtsdatum falsch */
		personen.Geburtsdatum < 1901
		OR (
			personen.Geburtsdatum='1970-01-01'
			AND personen.AZLand <> '902/1993'
			AND personen.AZLand <> '10128/2014'
		)
		
		/* lange nicht geändert */
		OR (
			personen.adminpruefung <> '0000-00-00'
			AND personen.adminpruefung < DATE_SUB(NOW(), INTERVAL 90 DAY)
		)
		OR (
			personen.adminpruefung = '0000-00-00'
			AND personen.lastChanged < DATE_SUB(NOW(), INTERVAL 90 DAY)
		)
		OR (
			personen.Familienpos <> 'Haushaltsvorstand'
			AND personen.Familienzuordnung <> ''
			AND (
				a.AZLand = ''
				OR a.geloescht = 1
			)
		)
	)
	AND personen.geloescht = 0