SELECT
	unterkuenfte.betreuerIn,
	personen.Name,
	personen.Vorname,
	unterkuenfte.ort,
	unterkuenfte.strasse,
	unterkuenfte.nr,
	Geschlecht,
	Geburtsdatum,
	Herkunftsland,
	Sprachkurs,
	personen.lastChanged
FROM personen
LEFT JOIN unterkuenfte ON personen.unterkunftid = unterkuenfte.id
LEFT JOIN orte ON unterkuenfte.ort = orte.Name
WHERE orte.statistischIrrelevant = 0 AND unterkuenfte.betreuerIn != 'keiner' AND personen.geloescht = 0