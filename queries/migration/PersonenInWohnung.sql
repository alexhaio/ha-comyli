SELECT
	zimmer.unterkunft,
	zimmer.Zimmer,
	zimmer.wohnTyp,
	personen.Name,
	personen.Vorname,
	personen.Geburtsdatum,
	personen.Geschlecht,
	personen.Herkunftsland,
	personen.Religion,
	personen.Aufenthaltstitel,
	CASE
		WHEN personen.Asyl = 1 THEN 'Asyl'
		WHEN personen.SGBII = 1 THEN 'SGB II'
		WHEN personen.SGB_XII = 1 THEN 'SGB XII'
		WHEN personen.keinLeistungsbezug = 1 THEN 'k. L.Bezug'
	END AS Rechtskreis,
	personen.gebuehrenVon,
	personen.gebuehrenBis,
	unterkuenfte.betreuerIn,
	personen.wichtigeKontakte
FROM zimmer
LEFT JOIN personen ON personen.unterkunftid = zimmer.unterkunft AND personen.Zimmer = zimmer.Zimmer
LEFT JOIN unterkuenfte ON zimmer.unterkunft = unterkuenfte.id
WHERE personen.AZLand IS NULL OR personen.geloescht = 0
