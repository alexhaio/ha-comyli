SELECT betreuerIn, typ, (COUNT(Name)-SUM(SGBII)) AS Asyl, SUM(SGBII) AS SGBII, SUM(Alt15) AS 'Ab 15', SUM(Jung15) AS 'Jünger 15', COUNT(Name) AS Anzahl
FROM (
	SELECT
		personen.Name, Vorname, ort, strasse, nr, etage, lage, Zimmer, typ,
		IF(FLOOR(DATEDIFF(NOW(),Geburtsdatum)/365.25) >= 15,'1','0') AS Alt15,
		IF(FLOOR(DATEDIFF(NOW(),Geburtsdatum)/365.25) < 15,'1','0') AS Jung15,
		Geschlecht, Herkunftsland, Religion, betreuerIn,
		Familienstand, LAGSGBII, Sprachkurs, Sonstiges, Kontaktinfo, Ausweisablaufdatum, personen.lastChanged,
		EinzugAm, Familienbindung, WichtigeKontakte, Dokumente, Krankheiten, Schule,
		Beruf, Freizeit, Aufenthaltstitel, Schluessel, Verlustersatz, AZLand, Geburtsort, Sprache, Fremdsprache,
		Familienpos, Familienzuordnung, ZugewiesenAm, betreuungEnde, `LAG`, SGBII
	FROM Personen
	LEFT JOIN unterkuenfte ON personen.unterkunftid = unterkuenfte.id
	LEFT JOIN orte ON unterkuenfte.ort = orte.Name
	WHERE statistischIrrelevant = 0 AND betreuerIn != 'keiner' AND personen.geloescht = 0
) AS PersonenMitOrt
GROUP BY betreuerIn, typ