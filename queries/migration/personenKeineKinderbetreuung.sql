SELECT
	CONCAT('<a href=\'./?f=formular-personen&amp;item=',PersonenMitOrt.Name,';;',PersonenMitOrt.Vorname,';;',PersonenMitOrt.Geburtsdatum,'\' target=\'_blank\'>',PersonenMitOrt.Name,'</a>') AS Name,
	PersonenMitOrt.Vorname,
	COUNT(personen.AZLAND) AS AnzahlKinder,
	'Link'
FROM [QRY]PersonenMitOrt[/QRY]
LEFT JOIN personen ON PersonenMitOrt.ID = personen.Familienzuordnung
WHERE
		PersonenMitOrt.aktuelleMassnahme = 'keine Kinderbetreuung'
	AND PersonenMitOrt.Familienpos = 'Haushaltsvorstand'
GROUP BY PersonenMitOrt.ID