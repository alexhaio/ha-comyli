SELECT
	a.Name,
	a.Vorname,
	a.Geburtsdatum,
	a.Unterkunft,
	a.Einzug,
	MIN(b.Einzug) as Auszug,
	unterkuenfte.ort,
	unterkuenfte.strasse,
	unterkuenfte.nr,
	unterkuenfte.etage,
	unterkuenfte.lage
FROM (
	SELECT `Name`,`Vorname`,`Geburtsdatum`,`Unterkunft`,`Einzug` FROM `personen-unterkunft-history`
	UNION
	SELECT `Name`,`Vorname`,`Geburtsdatum`,`unterkunftid`,`EinzugAm` FROM personen WHERE unterkunftid <> '' AND geloescht=0
) AS a
LEFT JOIN (
	SELECT `Name`,`Vorname`,`Geburtsdatum`,`Unterkunft`,`Einzug` FROM `personen-unterkunft-history`
	UNION
	SELECT `Name`,`Vorname`,`Geburtsdatum`,`unterkunftid`,`EinzugAm` FROM personen WHERE unterkunftid <> '' AND geloescht=0
) AS b ON
	a.Name = b.Name
	AND a.Vorname = b.Vorname
	AND a.Geburtsdatum = b.Geburtsdatum
	AND a.Einzug < b.Einzug
LEFT JOIN unterkuenfte ON unterkuenfte.id = a.Unterkunft
GROUP BY a.Name, a.Vorname, a.`Geburtsdatum`, a.`Einzug`, a.Unterkunft
ORDER BY a.Name, a.Vorname, a.`Geburtsdatum`, a.`Einzug`