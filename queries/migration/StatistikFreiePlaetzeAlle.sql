SELECT
	ort,
	strasse, nr, etage, lage,
	FreiePlaetze,
	MaxBelegung,
	moeglBelegung,
	typ,
	Religion,
	Herkunftsland
FROM (
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
	FROM (
		SELECT
			unterkuenfte.id,
			unterkuenfte.ort,
			unterkuenfte.strasse,
			unterkuenfte.nr,
			unterkuenfte.etage,
			unterkuenfte.lage,
			unterkuenfte.typ,
			unterkuenfte.betreuerIn,
			zimmer.Zimmer,
			zimmer.wohnTyp,
			IF(wohntypen.keineWeiterenPersonen=0,zimmer.MaxBelegung,COUNT(personen.AZLand)) AS MaxBelegung,
			zimmer.MaxBelegung AS moeglBelegung,
			wohntypen.keineWeiterenPersonen,
			COUNT(personen.AZLand) AS Belegung,
			(IF(wohntypen.keineWeiterenPersonen=0,zimmer.MaxBelegung,COUNT(personen.AZLand)) - COUNT(personen.AZLand)) AS FreiePlaetze,
			personen.Religion,
			personen.Herkunftsland
		FROM unterkuenfte
		LEFT JOIN zimmer ON unterkuenfte.id=zimmer.unterkunft
		LEFT JOIN orte ON unterkuenfte.ort = orte.Name
		LEFT JOIN personen ON
			zimmer.unterkunft = personen.unterkunftid
			AND zimmer.Zimmer = personen.Zimmer
			AND (personen.geloescht = 0 OR personen.geloescht IS NULL)
		LEFT JOIN wohntypen ON wohnTyp = wohntypen.ID
		WHERE statistischIrrelevant = 0 AND MaxBelegung IS NOT NULL
		GROUP BY id, Zimmer
	) AS kontingenteZimmer
	GROUP BY kontingenteZimmer.id
) AS belegungenGebaeude
WHERE FreiePlaetze >= 0