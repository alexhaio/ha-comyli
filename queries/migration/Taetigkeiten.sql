SELECT
	Name, Vorname, Geburtsdatum,
	IF(
		( (SchuleVon <= NOW() AND SchuleVon <> '0000-00-00' AND SchuleVon <> '1970-01-01') AND (SchuleBis >= NOW() OR SchuleBis = '0000-00-00' OR SchuleBis = '1970-01-01')),
		CONCAT('Schule: ab ',SchuleVon,' - ',Schule),
		IF(
			( (BerufVon <= NOW() AND BerufVon <> '0000-00-00' AND BerufVon <> '1970-01-01') AND (BerufBis >= NOW() OR BerufBis = '0000-00-00' OR BerufBis = '1970-01-01')),
			CONCAT('Beruf: ab ',BerufVon,' - ',Beruf),
			'untätig'
		)
	) AS Taetigkeit
FROM personen
WHERE geloescht = 0