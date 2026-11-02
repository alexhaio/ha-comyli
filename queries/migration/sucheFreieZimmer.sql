SELECT
	kontingenteZimmer.id,
	kontingenteZimmer.ort,
	kontingenteZimmer.strasse,
	kontingenteZimmer.nr,
	kontingenteZimmer.etage,
	kontingenteZimmer.lage,
	kontingenteZimmer.Zimmer AS ZimmerNr,
	kontingenteZimmer.wohnTyp,
	kontingenteZimmer.MaxBelegung,
	kontingenteZimmer.Belegung,
	kontingenteZimmer.FreiePlaetze,
	personen.Religion,
	personen.Herkunftsland,
	personen.Geschlecht,
	kontingenteZimmer.betreuerIn,
	kontingenteZimmer.typ
FROM [QRY]kontingenteZimmer[/QRY]
LEFT JOIN personen ON (kontingenteZimmer.Zimmer=personen.Zimmer) AND (kontingenteZimmer.id=personen.unterkunftid) AND (personen.geloescht = 0 OR personen.geloescht IS NULL)
WHERE kontingenteZimmer.typ <> 'privat'
GROUP BY kontingenteZimmer.id, kontingenteZimmer.Zimmer
HAVING freiePlaetze > 0