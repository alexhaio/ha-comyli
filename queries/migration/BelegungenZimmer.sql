SELECT
	sucheFreieZimmer.*,
	personen.Name,
	personen.Vorname,
	personen.Religion,
	personen.Herkunftsland,
	personen.Geschlecht,
	personen.Geburtsdatum,
	TIMESTAMPDIFF(YEAR, personen.Geburtsdatum, CURDATE()) AS Jahre
FROM (
	SELECT
		kontingenteZimmer.id,
		kontingenteZimmer.ort,
		kontingenteZimmer.strasse,
		kontingenteZimmer.nr,
		kontingenteZimmer.etage,
		kontingenteZimmer.lage,
		kontingenteZimmer.Zimmer AS ZimmerNr,
		kontingenteZimmer.MaxBelegung,
		kontingenteZimmer.wohnTyp,
		COUNT(personen.Name) AS Belegung,
		(MaxBelegung-COUNT(personen.Name)) AS freiePlaetze,
		unterkuenfte.betreuerIn,
		unterkuenfte.typ
	FROM [QRY]kontingenteZimmer[/QRY]
	LEFT JOIN personen ON (kontingenteZimmer.Zimmer=personen.Zimmer) AND (kontingenteZimmer.id=personen.unterkunftid) AND (personen.geloescht = 0 OR personen.geloescht IS NULL)
	LEFT JOIN unterkuenfte ON (unterkuenfte.ort=kontingenteZimmer.ort) AND (unterkuenfte.id=kontingenteZimmer.id)
	WHERE unterkuenfte.typ <> 'privat'
	GROUP BY kontingenteZimmer.Ort, kontingenteZimmer.id, kontingenteZimmer.Zimmer
	HAVING freiePlaetze > 0
) AS sucheFreieZimmer
LEFT JOIN personen ON (sucheFreieZimmer.ZimmerNr=personen.Zimmer) AND (sucheFreieZimmer.id=personen.unterkunftid) AND (personen.geloescht = 0 OR personen.geloescht IS NULL)
ORDER BY id, ZimmerNr