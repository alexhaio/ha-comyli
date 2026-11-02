SELECT Woche AS Wochennummer, SUM(Zugaenge) AS Zugaenge, SUM(Abgaenge) AS Abgaenge
FROM (
	SELECT Woche, '' AS Zugaenge, Abgaenge
	FROM (
		SELECT WEEKOFYEAR(`AbgangAm`) AS Woche, COUNT(WEEKOFYEAR(`AbgangAm`)) AS Abgaenge
		FROM `personen`
		WHERE (`AbgangAm` > '2016-01-01' AND `AbgangAm` < '2016-05-16' AND (geloescht = 0 OR geloescht IS NULL))
		GROUP BY WEEKOFYEAR(`AbgangAm`)
	)AS a
	UNION
		SELECT Woche, Zugaenge, ''
		FROM (SELECT WEEKOFYEAR(`ZugewiesenAm`) AS Woche, COUNT(WEEKOFYEAR(`ZugewiesenAm`)) AS Zugaenge
			FROM `personen`
			WHERE (`ZugewiesenAm` > '2016-01-01' AND `ZugewiesenAm` < '2016-05-16')
			GROUP BY WEEKOFYEAR(`ZugewiesenAm`))
			AS b)
		as c
	GROUP BY Woche
	ORDER BY Woche