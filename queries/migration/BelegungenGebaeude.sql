	SELECT
		id,
		ort,
		strasse,
		nr,
		etage,
		lage,
		SUM(MaxBelegung) AS MaxBelegung,
		SUM(Belegung) AS Belegung,
		SUM(moeglBelegung) AS moeglBelegung,
		SUM(FreiePlaetze) AS FreiePlaetze,
		typ,
		betreuerIn,
		Religion,
		Herkunftsland
	FROM [QRY]kontingenteZimmer[/QRY]
	GROUP BY kontingenteZimmer.id